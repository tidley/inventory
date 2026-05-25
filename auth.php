<?php

function inventory_auth_array_value($array, $key, $default = '') {
  return isset($array[$key]) ? $array[$key] : $default;
}

function inventory_is_https() {
  if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
    return true;
  }
  if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    return true;
  }
  return false;
}

function inventory_start_session() {
  if (session_status() === PHP_SESSION_ACTIVE) {
    return;
  }

  $secure = inventory_is_https();
  session_name('inventory_session');
  ini_set('session.use_strict_mode', '1');
  ini_set('session.cookie_httponly', '1');
  ini_set('session.cookie_secure', $secure ? '1' : '0');
  ini_set('session.cookie_samesite', 'Lax');

  if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(array(
      'lifetime' => 0,
      'path' => '/',
      'secure' => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
    ));
  } else {
    session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
  }

  session_start();
}

function inventory_auth_config() {
  $env = inventory_env();
  $username = trim((string) inventory_auth_array_value($env, 'AUTH_USERNAME', ''));
  $pinHash = trim((string) inventory_auth_array_value($env, 'AUTH_PIN_HASH', ''));
  $pin = trim((string) inventory_auth_array_value($env, 'AUTH_PIN', ''));

  return array(
    'configured' => $username !== '' && ($pinHash !== '' || $pin !== ''),
    'username' => $username,
    'pinHash' => $pinHash,
    'pin' => $pin,
  );
}

function inventory_auth_configured() {
  $config = inventory_auth_config();
  return !empty($config['configured']);
}

function inventory_is_authenticated() {
  inventory_start_session();
  return !empty($_SESSION['inventory_auth']) && !empty($_SESSION['inventory_auth']['username']);
}

function inventory_current_username() {
  inventory_start_session();
  return inventory_is_authenticated() ? (string) $_SESSION['inventory_auth']['username'] : '';
}

function inventory_complete_login($username, $method) {
  inventory_start_session();
  session_regenerate_id(true);
  $_SESSION['inventory_auth'] = array(
    'username' => $username,
    'method' => $method,
    'loginAt' => gmdate('c'),
  );
}

function inventory_logout() {
  inventory_start_session();
  $_SESSION = array();
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  session_destroy();
}

function inventory_auth_check_pin($username, $pin) {
  $config = inventory_auth_config();
  if (empty($config['configured'])) {
    return false;
  }
  if (!hash_equals($config['username'], trim((string) $username))) {
    return false;
  }

  $pin = trim((string) $pin);
  if ($pin === '') {
    return false;
  }

  if ($config['pinHash'] !== '') {
    return password_verify($pin, $config['pinHash']);
  }

  return hash_equals($config['pin'], $pin);
}

function inventory_require_auth_json() {
  if (!inventory_auth_configured()) {
    json_response(array('error' => 'Access control is not configured'), 503);
  }
  if (!inventory_is_authenticated()) {
    json_response(array('error' => 'Login required', 'loginRequired' => true), 401);
  }
}

function inventory_auth_status_payload() {
  $configured = inventory_auth_configured();
  return array(
    'success' => true,
    'configured' => $configured,
    'authenticated' => $configured && inventory_is_authenticated(),
    'username' => $configured && inventory_is_authenticated() ? inventory_current_username() : '',
    'passkeyCount' => $configured ? inventory_passkey_count() : 0,
  );
}

function inventory_auth_login($input) {
  $username = inventory_auth_array_value($input, 'username', '');
  $pin = inventory_auth_array_value($input, 'pin', '');

  if (!inventory_auth_configured()) {
    json_response(array('error' => 'Access control is not configured'), 503);
  }

  if (!inventory_auth_check_pin($username, $pin)) {
    json_response(array('error' => 'Username or PIN is incorrect'), 401);
  }

  $config = inventory_auth_config();
  inventory_complete_login($config['username'], 'pin');
  json_response(inventory_auth_status_payload());
}

function inventory_auth_logout() {
  inventory_logout();
  json_response(array('success' => true));
}

function inventory_auth_data_dir() {
  $dir = __DIR__ . '/data';
  if (!is_dir($dir)) {
    @mkdir($dir, 0750, true);
  }

  $htaccess = $dir . '/.htaccess';
  if (!is_file($htaccess)) {
    @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
  }
  $index = $dir . '/index.html';
  if (!is_file($index)) {
    @file_put_contents($index, "<!doctype html><html><body></body></html>\n");
  }

  return $dir;
}

function inventory_passkey_store_path() {
  return inventory_auth_data_dir() . '/auth-passkeys.json';
}

function inventory_passkey_store() {
  $path = inventory_passkey_store_path();
  if (!is_file($path)) {
    return array('credentials' => array());
  }

  $data = json_decode((string) @file_get_contents($path), true);
  if (!is_array($data) || !isset($data['credentials']) || !is_array($data['credentials'])) {
    return array('credentials' => array());
  }

  return $data;
}

function inventory_save_passkey_store($store) {
  $path = inventory_passkey_store_path();
  @file_put_contents($path, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function inventory_passkey_credentials_for_username($username) {
  $store = inventory_passkey_store();
  $credentials = array();
  foreach ($store['credentials'] as $credential) {
    if (isset($credential['username']) && hash_equals((string) $credential['username'], (string) $username)) {
      $credentials[] = $credential;
    }
  }
  return $credentials;
}

function inventory_passkey_count() {
  $store = inventory_passkey_store();
  return count($store['credentials']);
}

function inventory_b64url_encode($data) {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function inventory_b64url_decode($value) {
  $value = strtr((string) $value, '-_', '+/');
  $padding = strlen($value) % 4;
  if ($padding) {
    $value .= str_repeat('=', 4 - $padding);
  }
  $decoded = base64_decode($value, true);
  if ($decoded === false) {
    throw new Exception('Invalid base64url value.');
  }
  return $decoded;
}

function inventory_random_challenge() {
  return inventory_b64url_encode(random_bytes(32));
}

function inventory_request_host() {
  return isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : 'localhost';
}

function inventory_rp_id() {
  $host = inventory_request_host();
  if (strpos($host, ':') !== false) {
    $host = explode(':', $host, 2)[0];
  }
  return $host;
}

function inventory_origin() {
  return (inventory_is_https() ? 'https' : 'http') . '://' . inventory_request_host();
}

function inventory_passkey_register_options() {
  inventory_require_auth_json();
  inventory_start_session();

  $username = inventory_current_username();
  $challenge = inventory_random_challenge();
  $_SESSION['webauthn_register_challenge'] = array(
    'challenge' => $challenge,
    'createdAt' => time(),
    'username' => $username,
  );

  $exclude = array();
  foreach (inventory_passkey_credentials_for_username($username) as $credential) {
    $exclude[] = array(
      'type' => 'public-key',
      'id' => $credential['id'],
    );
  }

  json_response(array(
    'success' => true,
    'publicKey' => array(
      'challenge' => $challenge,
      'rp' => array(
        'name' => 'Inventory',
        'id' => inventory_rp_id(),
      ),
      'user' => array(
        'id' => inventory_b64url_encode($username),
        'name' => $username,
        'displayName' => $username,
      ),
      'pubKeyCredParams' => array(
        array('type' => 'public-key', 'alg' => -7),
      ),
      'authenticatorSelection' => array(
        'authenticatorAttachment' => 'platform',
        'residentKey' => 'discouraged',
        'requireResidentKey' => false,
        'userVerification' => 'required',
      ),
      'timeout' => 60000,
      'attestation' => 'none',
      'excludeCredentials' => $exclude,
    ),
  ));
}

function inventory_passkey_login_options($input) {
  if (!inventory_auth_configured()) {
    json_response(array('error' => 'Access control is not configured'), 503);
  }

  $config = inventory_auth_config();
  $username = trim((string) inventory_auth_array_value($input, 'username', ''));
  if ($username === '') {
    $username = $config['username'];
  }
  if (!hash_equals($config['username'], $username)) {
    json_response(array('error' => 'Username is incorrect'), 401);
  }

  $credentials = inventory_passkey_credentials_for_username($username);
  if (!$credentials) {
    json_response(array('error' => 'No passkey is registered for this user'), 404);
  }

  inventory_start_session();
  $challenge = inventory_random_challenge();
  $_SESSION['webauthn_login_challenge'] = array(
    'challenge' => $challenge,
    'createdAt' => time(),
    'username' => $username,
  );

  $allow = array();
  foreach ($credentials as $credential) {
    $allow[] = array(
      'type' => 'public-key',
      'id' => $credential['id'],
    );
  }

  json_response(array(
    'success' => true,
    'publicKey' => array(
      'challenge' => $challenge,
      'rpId' => inventory_rp_id(),
      'allowCredentials' => $allow,
      'timeout' => 60000,
      'userVerification' => 'required',
    ),
  ));
}

function inventory_validate_challenge($sessionKey, $type, $clientDataJson) {
  inventory_start_session();
  if (empty($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
    throw new Exception('Passkey challenge has expired.');
  }

  $challenge = $_SESSION[$sessionKey];
  unset($_SESSION[$sessionKey]);

  if (time() - (int) inventory_auth_array_value($challenge, 'createdAt', 0) > 300) {
    throw new Exception('Passkey challenge has expired.');
  }

  $client = json_decode($clientDataJson, true);
  if (!is_array($client)) {
    throw new Exception('Invalid passkey client data.');
  }
  if ((string) inventory_auth_array_value($client, 'type', '') !== $type) {
    throw new Exception('Invalid passkey response type.');
  }
  if (!hash_equals((string) $challenge['challenge'], (string) inventory_auth_array_value($client, 'challenge', ''))) {
    throw new Exception('Passkey challenge did not match.');
  }
  if (!hash_equals(inventory_origin(), (string) inventory_auth_array_value($client, 'origin', ''))) {
    throw new Exception('Passkey origin did not match.');
  }

  return $challenge;
}

function inventory_assert_authenticator_flags($flags) {
  if (($flags & 0x01) !== 0x01) {
    throw new Exception('Passkey user presence was not verified.');
  }
  if (($flags & 0x04) !== 0x04) {
    throw new Exception('Passkey biometric/PIN verification was not completed.');
  }
}

function inventory_parse_authenticator_data($authData, $expectAttestedCredential) {
  if (strlen($authData) < 37) {
    throw new Exception('Invalid authenticator data.');
  }

  $rpIdHash = substr($authData, 0, 32);
  $expectedRpIdHash = hash('sha256', inventory_rp_id(), true);
  if (!hash_equals($expectedRpIdHash, $rpIdHash)) {
    throw new Exception('Passkey was not created for this site.');
  }

  $flags = ord($authData[32]);
  inventory_assert_authenticator_flags($flags);
  $counter = unpack('N', substr($authData, 33, 4));
  $signCount = isset($counter[1]) ? (int) $counter[1] : 0;

  $result = array(
    'flags' => $flags,
    'signCount' => $signCount,
  );

  if (!$expectAttestedCredential) {
    return $result;
  }

  if (($flags & 0x40) !== 0x40) {
    throw new Exception('Passkey credential data is missing.');
  }

  $offset = 37 + 16;
  if (strlen($authData) < $offset + 2) {
    throw new Exception('Invalid passkey credential data.');
  }
  $lengthData = unpack('n', substr($authData, $offset, 2));
  $credentialIdLength = isset($lengthData[1]) ? (int) $lengthData[1] : 0;
  $offset += 2;
  if ($credentialIdLength < 1 || strlen($authData) < $offset + $credentialIdLength) {
    throw new Exception('Invalid passkey credential id.');
  }

  $credentialId = substr($authData, $offset, $credentialIdLength);
  $offset += $credentialIdLength;
  $coseBytes = substr($authData, $offset);
  $cborOffset = 0;
  $coseKey = inventory_cbor_decode($coseBytes, $cborOffset);

  $result['credentialId'] = $credentialId;
  $result['publicKeyPem'] = inventory_cose_es256_to_pem($coseKey);
  return $result;
}

function inventory_cbor_length($data, &$offset, $additional) {
  if ($additional < 24) {
    return $additional;
  }
  if ($additional === 24) {
    if (strlen($data) < $offset + 1) throw new Exception('Invalid CBOR length.');
    return ord($data[$offset++]);
  }
  if ($additional === 25) {
    if (strlen($data) < $offset + 2) throw new Exception('Invalid CBOR length.');
    $value = unpack('n', substr($data, $offset, 2));
    $offset += 2;
    return (int) $value[1];
  }
  if ($additional === 26) {
    if (strlen($data) < $offset + 4) throw new Exception('Invalid CBOR length.');
    $value = unpack('N', substr($data, $offset, 4));
    $offset += 4;
    return (int) $value[1];
  }
  throw new Exception('Unsupported CBOR length.');
}

function inventory_cbor_decode($data, &$offset) {
  if ($offset >= strlen($data)) {
    throw new Exception('Unexpected end of CBOR data.');
  }

  $initial = ord($data[$offset++]);
  $major = $initial >> 5;
  $additional = $initial & 0x1f;

  if ($major === 0) {
    return inventory_cbor_length($data, $offset, $additional);
  }
  if ($major === 1) {
    return -1 - inventory_cbor_length($data, $offset, $additional);
  }
  if ($major === 2 || $major === 3) {
    $length = inventory_cbor_length($data, $offset, $additional);
    if (strlen($data) < $offset + $length) {
      throw new Exception('Invalid CBOR string.');
    }
    $value = substr($data, $offset, $length);
    $offset += $length;
    return $value;
  }
  if ($major === 4) {
    $length = inventory_cbor_length($data, $offset, $additional);
    $items = array();
    for ($i = 0; $i < $length; $i++) {
      $items[] = inventory_cbor_decode($data, $offset);
    }
    return $items;
  }
  if ($major === 5) {
    $length = inventory_cbor_length($data, $offset, $additional);
    $map = array();
    for ($i = 0; $i < $length; $i++) {
      $key = inventory_cbor_decode($data, $offset);
      $map[$key] = inventory_cbor_decode($data, $offset);
    }
    return $map;
  }
  if ($major === 7) {
    if ($additional === 20) return false;
    if ($additional === 21) return true;
    if ($additional === 22) return null;
  }

  throw new Exception('Unsupported CBOR value.');
}

function inventory_der_length($length) {
  if ($length < 128) {
    return chr($length);
  }

  $bytes = '';
  while ($length > 0) {
    $bytes = chr($length & 0xff) . $bytes;
    $length >>= 8;
  }
  return chr(0x80 | strlen($bytes)) . $bytes;
}

function inventory_der_tlv($tag, $value) {
  return chr($tag) . inventory_der_length(strlen($value)) . $value;
}

function inventory_der_sequence($value) {
  return inventory_der_tlv(0x30, $value);
}

function inventory_der_oid($bytes) {
  return inventory_der_tlv(0x06, $bytes);
}

function inventory_cose_es256_to_pem($coseKey) {
  if (!is_array($coseKey)) {
    throw new Exception('Invalid passkey public key.');
  }
  if ((int) inventory_auth_array_value($coseKey, 1, 0) !== 2 || (int) inventory_auth_array_value($coseKey, 3, 0) !== -7) {
    throw new Exception('Only ES256 passkeys are supported.');
  }

  $x = inventory_auth_array_value($coseKey, -2, '');
  $y = inventory_auth_array_value($coseKey, -3, '');
  if (!is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
    throw new Exception('Invalid ES256 passkey coordinates.');
  }

  $point = "\x04" . $x . $y;
  $algorithm = inventory_der_sequence(
    inventory_der_oid("\x2a\x86\x48\xce\x3d\x02\x01") .
    inventory_der_oid("\x2a\x86\x48\xce\x3d\x03\x01\x07")
  );
  $subjectPublicKey = inventory_der_tlv(0x03, "\x00" . $point);
  $der = inventory_der_sequence($algorithm . $subjectPublicKey);

  return "-----BEGIN PUBLIC KEY-----\n" .
    chunk_split(base64_encode($der), 64, "\n") .
    "-----END PUBLIC KEY-----\n";
}

function inventory_passkey_register($input) {
  inventory_require_auth_json();

  $response = inventory_auth_array_value($input, 'response', array());
  $clientDataJson = inventory_b64url_decode(inventory_auth_array_value($response, 'clientDataJSON', ''));
  $challenge = inventory_validate_challenge('webauthn_register_challenge', 'webauthn.create', $clientDataJson);

  $attestationObject = inventory_b64url_decode(inventory_auth_array_value($response, 'attestationObject', ''));
  $offset = 0;
  $attestation = inventory_cbor_decode($attestationObject, $offset);
  if (!is_array($attestation) || !isset($attestation['authData'])) {
    throw new Exception('Invalid passkey attestation.');
  }

  $authData = inventory_parse_authenticator_data($attestation['authData'], true);
  $credentialId = inventory_b64url_encode($authData['credentialId']);
  $username = (string) $challenge['username'];

  $store = inventory_passkey_store();
  foreach ($store['credentials'] as $credential) {
    if (isset($credential['id']) && hash_equals((string) $credential['id'], $credentialId)) {
      json_response(array('error' => 'This passkey is already registered'), 409);
    }
  }

  $store['credentials'][] = array(
    'id' => $credentialId,
    'username' => $username,
    'publicKeyPem' => $authData['publicKeyPem'],
    'signCount' => $authData['signCount'],
    'createdAt' => gmdate('c'),
  );
  inventory_save_passkey_store($store);

  json_response(array(
    'success' => true,
    'passkeyCount' => inventory_passkey_count(),
  ));
}

function inventory_find_passkey($credentialId, &$index = null) {
  $store = inventory_passkey_store();
  foreach ($store['credentials'] as $i => $credential) {
    if (isset($credential['id']) && hash_equals((string) $credential['id'], (string) $credentialId)) {
      $index = $i;
      return array($store, $credential);
    }
  }
  throw new Exception('Passkey is not registered.');
}

function inventory_passkey_login($input) {
  $credentialId = inventory_auth_array_value($input, 'id', '');
  $response = inventory_auth_array_value($input, 'response', array());
  $clientDataJson = inventory_b64url_decode(inventory_auth_array_value($response, 'clientDataJSON', ''));
  $challenge = inventory_validate_challenge('webauthn_login_challenge', 'webauthn.get', $clientDataJson);

  $index = null;
  list($store, $credential) = inventory_find_passkey($credentialId, $index);
  if (!hash_equals((string) $challenge['username'], (string) $credential['username'])) {
    throw new Exception('Passkey user did not match.');
  }

  $authenticatorData = inventory_b64url_decode(inventory_auth_array_value($response, 'authenticatorData', ''));
  $signature = inventory_b64url_decode(inventory_auth_array_value($response, 'signature', ''));
  $authData = inventory_parse_authenticator_data($authenticatorData, false);
  $signed = $authenticatorData . hash('sha256', $clientDataJson, true);

  $ok = openssl_verify($signed, $signature, $credential['publicKeyPem'], OPENSSL_ALGO_SHA256);
  if ($ok !== 1) {
    throw new Exception('Passkey signature could not be verified.');
  }

  if ($index !== null && $authData['signCount'] > (int) inventory_auth_array_value($credential, 'signCount', 0)) {
    $store['credentials'][$index]['signCount'] = $authData['signCount'];
    $store['credentials'][$index]['lastUsedAt'] = gmdate('c');
    inventory_save_passkey_store($store);
  }

  inventory_complete_login($credential['username'], 'passkey');
  json_response(inventory_auth_status_payload());
}

function inventory_auth_setup_message() {
  return 'Add AUTH_USERNAME and AUTH_PIN_HASH to .env to enable access control.';
}

function inventory_render_login_page($assetVersion) {
  $configured = inventory_auth_configured();
  http_response_code($configured ? 200 : 503);
  header('Content-Type: text/html; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: same-origin');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Inventory Login</title>
  <meta name="theme-color" content="#101418" />
  <link rel="stylesheet" href="styles.css?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>" />
</head>
<body>
  <main class="auth-shell">
    <section class="auth-panel" aria-labelledby="auth-title">
      <h1 id="auth-title">Inventory</h1>

      <?php if (!$configured): ?>
        <p class="status error auth-message"><?php echo htmlspecialchars(inventory_auth_setup_message(), ENT_QUOTES, 'UTF-8'); ?></p>
      <?php else: ?>
        <form id="auth-login-form" class="auth-form" autocomplete="off">
          <label class="field" for="auth-username">
            <span>Username</span>
            <input id="auth-username" type="text" inputmode="text" autocomplete="username" required />
          </label>
          <label class="field" for="auth-pin">
            <span>PIN</span>
            <input id="auth-pin" type="password" inputmode="numeric" autocomplete="current-password" required />
          </label>
          <div class="form-actions">
            <button class="primary-button" id="auth-login-button" type="submit">Sign in</button>
            <button class="small-button" id="auth-passkey-login" type="button">Use device unlock</button>
          </div>
          <p class="status" id="auth-status" role="status" aria-live="polite"></p>
        </form>
      <?php endif; ?>
    </section>
  </main>
  <script src="auth.js?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
<?php
  exit;
}

function inventory_require_auth_page($assetVersion) {
  if (!inventory_auth_configured() || !inventory_is_authenticated()) {
    inventory_render_login_page($assetVersion);
  }
}
