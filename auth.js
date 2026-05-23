const authApiUrl = 'api.php';

function authStatus(message, isError = false) {
  const status = document.getElementById('auth-status') || document.getElementById('security-status');
  if (!status) return;
  status.textContent = message;
  status.classList.toggle('error', isError);
}

async function authRequest(payload) {
  const response = await fetch(authApiUrl, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error || `Server returned ${response.status}`);
  }
  return data;
}

function bufferToBase64Url(buffer) {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

function base64UrlToBuffer(value) {
  const base64 = String(value).replace(/-/g, '+').replace(/_/g, '/');
  const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=');
  const binary = atob(padded);
  const bytes = new Uint8Array(binary.length);
  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index);
  }
  return bytes.buffer;
}

function publicKeyCreateOptions(options) {
  const publicKey = { ...options };
  publicKey.challenge = base64UrlToBuffer(publicKey.challenge);
  publicKey.user = {
    ...publicKey.user,
    id: base64UrlToBuffer(publicKey.user.id),
  };
  publicKey.excludeCredentials = (publicKey.excludeCredentials || []).map((credential) => ({
    ...credential,
    id: base64UrlToBuffer(credential.id),
  }));
  return publicKey;
}

function publicKeyGetOptions(options) {
  const publicKey = { ...options };
  publicKey.challenge = base64UrlToBuffer(publicKey.challenge);
  publicKey.allowCredentials = (publicKey.allowCredentials || []).map((credential) => ({
    ...credential,
    id: base64UrlToBuffer(credential.id),
  }));
  return publicKey;
}

function registrationPayload(credential) {
  return {
    action: 'passkeyRegister',
    id: credential.id,
    rawId: bufferToBase64Url(credential.rawId),
    type: credential.type,
    response: {
      clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
      attestationObject: bufferToBase64Url(credential.response.attestationObject),
    },
  };
}

function assertionPayload(credential) {
  return {
    action: 'passkeyLogin',
    id: credential.id,
    rawId: bufferToBase64Url(credential.rawId),
    type: credential.type,
    response: {
      clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
      authenticatorData: bufferToBase64Url(credential.response.authenticatorData),
      signature: bufferToBase64Url(credential.response.signature),
      userHandle: credential.response.userHandle ? bufferToBase64Url(credential.response.userHandle) : '',
    },
  };
}

function passkeysAvailable() {
  return Boolean(window.PublicKeyCredential && navigator.credentials);
}

async function signInWithPin(event) {
  event.preventDefault();
  const username = document.getElementById('auth-username').value;
  const pin = document.getElementById('auth-pin').value;
  const button = document.getElementById('auth-login-button');
  button.disabled = true;
  authStatus('Signing in...');

  try {
    await authRequest({ action: 'authLogin', username, pin });
    window.location.reload();
  } catch (error) {
    authStatus(error.message, true);
  } finally {
    button.disabled = false;
  }
}

async function signInWithPasskey() {
  const usernameInput = document.getElementById('auth-username');
  const username = usernameInput ? usernameInput.value.trim() : '';
  if (!passkeysAvailable()) {
    authStatus('Passkeys are not available in this browser.', true);
    return;
  }

  const button = document.getElementById('auth-passkey-login');
  button.disabled = true;
  authStatus('Waiting for device unlock...');

  try {
    const options = await authRequest({ action: 'passkeyLoginOptions', username });
    const credential = await navigator.credentials.get({
      publicKey: publicKeyGetOptions(options.publicKey),
    });
    if (!credential) throw new Error('Passkey sign in was cancelled.');
    await authRequest(assertionPayload(credential));
    window.location.reload();
  } catch (error) {
    authStatus(error.message, true);
  } finally {
    button.disabled = false;
  }
}

async function registerPasskey() {
  if (!passkeysAvailable()) {
    authStatus('Passkeys are not available in this browser.', true);
    return;
  }

  const button = document.getElementById('enable-passkey-button');
  button.disabled = true;
  authStatus('Waiting for device unlock...');

  try {
    const options = await authRequest({ action: 'passkeyRegisterOptions' });
    const credential = await navigator.credentials.create({
      publicKey: publicKeyCreateOptions(options.publicKey),
    });
    if (!credential) throw new Error('Passkey setup was cancelled.');
    const result = await authRequest(registrationPayload(credential));
    const count = document.getElementById('passkey-count');
    if (count) count.textContent = String(result.passkeyCount || 0);
    authStatus('Device unlock sign in enabled.');
  } catch (error) {
    authStatus(error.message, true);
  } finally {
    button.disabled = false;
  }
}

async function logout() {
  try {
    await authRequest({ action: 'authLogout' });
  } finally {
    window.location.reload();
  }
}

async function loadAuthStatus() {
  const currentUser = document.getElementById('auth-current-user');
  if (!currentUser) return;

  try {
    const status = await authRequest({ action: 'authStatus' });
    currentUser.textContent = status.username || 'Signed in';
    const count = document.getElementById('passkey-count');
    if (count) count.textContent = String(status.passkeyCount || 0);
  } catch (error) {
    authStatus(error.message, true);
  }
}

const loginForm = document.getElementById('auth-login-form');
if (loginForm) {
  loginForm.addEventListener('submit', signInWithPin);
}

const passkeyLoginButton = document.getElementById('auth-passkey-login');
if (passkeyLoginButton) {
  passkeyLoginButton.addEventListener('click', signInWithPasskey);
  passkeyLoginButton.disabled = !passkeysAvailable();
}

const enablePasskeyButton = document.getElementById('enable-passkey-button');
if (enablePasskeyButton) {
  enablePasskeyButton.addEventListener('click', registerPasskey);
  enablePasskeyButton.disabled = !passkeysAvailable();
}

const logoutButton = document.getElementById('logout-button');
if (logoutButton) {
  logoutButton.addEventListener('click', logout);
}

loadAuthStatus();
