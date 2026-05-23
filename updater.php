<?php

require_once __DIR__ . '/version.php';

if (!defined('INVENTORY_UPDATE_REPO')) {
  define('INVENTORY_UPDATE_REPO', 'github-owner/inventory');
}

class InventoryUpdater {
  const RELEASE_API = 'https://api.github.com/repos/%s/releases/latest';
  const PACKAGE_ASSET = 'inventory.zip';
  const STATUS_FILE = 'update-status.json';

  public static function getUpdateStatus() {
    $release = self::getLatestRelease();
    $asset = self::findAsset($release, self::PACKAGE_ASSET);

    $currentVersion = self::normalizeVersion(INVENTORY_VERSION);
    $latestTag = (string) (isset($release['tag_name']) ? $release['tag_name'] : '');
    $latestVersion = self::normalizeVersion($latestTag);
    $updateAvailable = $latestVersion !== '' && version_compare($latestVersion, $currentVersion, '>');

    return array(
      'success' => true,
      'currentVersion' => INVENTORY_VERSION,
      'latestVersion' => $latestVersion,
      'latestTag' => $latestTag,
      'releaseName' => isset($release['name']) ? $release['name'] : $latestTag,
      'releaseUrl' => isset($release['html_url']) ? $release['html_url'] : null,
      'publishedAt' => isset($release['published_at']) ? $release['published_at'] : null,
      'updateAvailable' => $updateAvailable,
      'zipAvailable' => class_exists('ZipArchive'),
      'assetName' => self::PACKAGE_ASSET,
      'assetUrl' => isset($asset['browser_download_url']) ? $asset['browser_download_url'] : null,
      'assetDigest' => isset($asset['digest']) ? $asset['digest'] : null,
      'assetSize' => isset($asset['size']) ? $asset['size'] : null,
    );
  }

  public static function installLatest() {
    if (function_exists('set_time_limit')) {
      @set_time_limit(300);
    }
    if (function_exists('ignore_user_abort')) {
      @ignore_user_abort(true);
    }

    try {
      self::writeInstallStatus('starting', 'Starting update...');

      if (!class_exists('ZipArchive')) {
        throw new Exception('PHP ZipArchive extension is required for updates.');
      }

      self::writeInstallStatus('checking', 'Checking latest release...');
      $status = self::getUpdateStatus();
      if (empty($status['updateAvailable'])) {
        $result = array(
          'success' => true,
          'updated' => false,
          'message' => 'Already up to date.',
          'currentVersion' => $status['currentVersion'],
          'latestVersion' => $status['latestVersion'],
        );
        self::writeInstallStatus('current', 'Already up to date.', $result);
        return $result;
      }

      $assetUrl = isset($status['assetUrl']) ? $status['assetUrl'] : '';
      self::validateAssetUrl($assetUrl, $status['latestTag']);

      $stamp = gmdate('Ymd-His') . '-' . self::randomSuffix();
      $updatesDir = self::dataDir() . '/updates';
      $backupDir = self::dataDir() . '/update-backups/' . $stamp;
      $workDir = $updatesDir . '/' . $stamp;
      $zipPath = $workDir . '/release.zip';
      $extractDir = $workDir . '/extract';

      self::ensureDataDir();
      self::ensureDir($workDir);
      self::ensureDir($extractDir);
      self::ensureDir($backupDir);

      self::writeInstallStatus('downloading', 'Downloading update package...', array(
        'tag' => $status['latestTag'],
        'assetSize' => isset($status['assetSize']) ? $status['assetSize'] : null,
      ));
      self::downloadFile($assetUrl, $zipPath);

      self::writeInstallStatus('verifying', 'Verifying update package...', array('tag' => $status['latestTag']));
      self::verifyDigest($zipPath, isset($status['assetDigest']) ? $status['assetDigest'] : null);

      self::writeInstallStatus('extracting', 'Extracting update package...', array('tag' => $status['latestTag']));
      self::extractZip($zipPath, $extractDir);

      $packageRoot = self::findPackageRoot($extractDir);
      $installRoot = __DIR__;
      $entries = self::listPackageEntries($packageRoot);

      self::writeInstallStatus('checking_files', 'Checking file permissions...', array('tag' => $status['latestTag']));
      self::preflightWritable($installRoot, $entries);

      self::writeInstallStatus('installing', 'Installing update files...', array('tag' => $status['latestTag']));
      self::copyPackage($packageRoot, $installRoot, $backupDir, $entries);

      if (function_exists('opcache_reset')) {
        @opcache_reset();
      }

      self::removeDir($workDir);

      $result = array(
        'success' => true,
        'updated' => true,
        'version' => $status['latestVersion'],
        'tag' => $status['latestTag'],
        'backupDir' => $backupDir,
      );
      self::writeInstallStatus('complete', 'Update installed.', $result);
      return $result;
    } catch (Exception $error) {
      self::writeInstallStatus('failed', $error->getMessage(), array(
        'updated' => false,
        'error' => $error->getMessage(),
      ));
      throw $error;
    }
  }

  public static function getInstallStatus() {
    $path = self::statusPath();
    if (!is_file($path)) {
      return array(
        'success' => true,
        'state' => 'idle',
        'message' => 'No update has been run yet.',
      );
    }

    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data)) {
      return array(
        'success' => true,
        'state' => 'unknown',
        'message' => 'Update status is unavailable.',
      );
    }

    $data['success'] = true;
    return $data;
  }

  private static function getLatestRelease() {
    $url = sprintf(self::RELEASE_API, INVENTORY_UPDATE_REPO);
    $response = self::httpGet($url, array(
      'Accept: application/vnd.github+json',
      'User-Agent: Inventory/' . INVENTORY_VERSION,
      'X-GitHub-Api-Version: 2022-11-28',
    ));

    $release = json_decode($response, true);
    if (!is_array($release)) {
      throw new Exception('Invalid GitHub release response.');
    }
    if (isset($release['message']) && !isset($release['tag_name'])) {
      throw new Exception('GitHub release lookup failed: ' . $release['message']);
    }

    return $release;
  }

  private static function findAsset($release, $name) {
    $assets = isset($release['assets']) && is_array($release['assets']) ? $release['assets'] : array();
    foreach ($assets as $asset) {
      if (isset($asset['name']) && $asset['name'] === $name) {
        return $asset;
      }
    }

    throw new Exception('Release asset not found: ' . $name);
  }

  private static function normalizeVersion($version) {
    return ltrim(trim((string) $version), "vV \t\n\r\0\x0B");
  }

  private static function validateAssetUrl($url, $tag) {
    $expectedPrefix = 'https://github.com/' . INVENTORY_UPDATE_REPO . '/releases/download/' . rawurlencode($tag) . '/';
    if ($url === '' || strncmp($url, $expectedPrefix, strlen($expectedPrefix)) !== 0) {
      throw new Exception('Unexpected release asset URL.');
    }
  }

  private static function httpGet($url, $headers = array()) {
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
      ));

      $body = curl_exec($ch);
      $error = curl_error($ch);
      $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($body === false || $status >= 400) {
        throw new Exception($error ? $error : 'HTTP request failed with status ' . $status);
      }
      return $body;
    }

    $context = stream_context_create(array(
      'http' => array(
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'timeout' => 30,
        'follow_location' => 1,
        'max_redirects' => 5,
      ),
    ));

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
      throw new Exception('HTTP request failed.');
    }
    return $body;
  }

  private static function downloadFile($url, $path) {
    $fp = fopen($path, 'wb');
    if ($fp === false) {
      throw new Exception('Could not write update package.');
    }

    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, array(
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_LOW_SPEED_LIMIT => 1024,
        CURLOPT_LOW_SPEED_TIME => 20,
        CURLOPT_HTTPHEADER => array('User-Agent: Inventory/' . INVENTORY_VERSION),
      ));
      $ok = curl_exec($ch);
      $error = curl_error($ch);
      $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      fclose($fp);

      if ($ok === false || $status >= 400) {
        @unlink($path);
        throw new Exception($error ? $error : 'Package download failed with status ' . $status);
      }
      return;
    }

    fclose($fp);
    $body = self::httpGet($url, array('User-Agent: Inventory/' . INVENTORY_VERSION));
    if (file_put_contents($path, $body) === false) {
      throw new Exception('Could not write update package.');
    }
  }

  private static function extractZip($zipPath, $extractDir) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
      throw new Exception('Could not open update package.');
    }

    if (!$zip->extractTo($extractDir)) {
      $zip->close();
      throw new Exception('Could not extract update package.');
    }

    $zip->close();
  }

  private static function verifyDigest($path, $digest) {
    if (!$digest || strpos($digest, 'sha256:') !== 0) {
      return;
    }

    $expected = substr($digest, strlen('sha256:'));
    $actual = hash_file('sha256', $path);

    if (!hash_equals(strtolower($expected), strtolower($actual))) {
      throw new Exception('Downloaded update package failed checksum verification.');
    }
  }

  private static function findPackageRoot($extractDir) {
    $expected = $extractDir . '/inventory';
    if (is_file($expected . '/index.php') && is_file($expected . '/api.php') && is_file($expected . '/version.php')) {
      return $expected;
    }

    $entries = scandir($extractDir);
    if (!$entries) {
      throw new Exception('Update package has an unexpected structure.');
    }

    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }
      $candidate = $extractDir . '/' . $entry;
      if (is_dir($candidate) && is_file($candidate . '/index.php') && is_file($candidate . '/api.php') && is_file($candidate . '/version.php')) {
        return $candidate;
      }
    }

    throw new Exception('Update package has an unexpected structure.');
  }

  private static function listPackageEntries($packageRoot) {
    $entries = array();
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($packageRoot, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
      $path = $item->getPathname();
      $relative = str_replace('\\', '/', substr($path, strlen($packageRoot) + 1));

      if (self::shouldSkip($relative) || $item->isLink()) {
        continue;
      }

      $entries[] = array(
        'relative' => $relative,
        'isDir' => $item->isDir(),
        'source' => $path,
      );
    }

    return $entries;
  }

  private static function shouldSkip($relative) {
    return $relative === '.env'
      || strpos($relative, '.env.') === 0
      || $relative === 'data'
      || strpos($relative, 'data/') === 0
      || $relative === '.git'
      || strpos($relative, '.git/') === 0
      || $relative === '.github'
      || strpos($relative, '.github/') === 0
      || $relative === 'build'
      || strpos($relative, 'build/') === 0;
  }

  private static function preflightWritable($installRoot, $entries) {
    foreach ($entries as $entry) {
      $destination = $installRoot . '/' . $entry['relative'];
      if (file_exists($destination)) {
        if (!is_writable($destination)) {
          throw new Exception('Not writable: ' . $entry['relative']);
        }
        continue;
      }

      $parent = dirname($destination);
      while (!is_dir($parent) && $parent !== dirname($parent)) {
        $parent = dirname($parent);
      }

      if (!is_writable($parent)) {
        throw new Exception('Not writable: ' . dirname($entry['relative']));
      }
    }
  }

  private static function copyPackage($packageRoot, $installRoot, $backupDir, $entries) {
    foreach ($entries as $entry) {
      $relative = $entry['relative'];
      $source = $packageRoot . '/' . $relative;
      $destination = $installRoot . '/' . $relative;

      if ($entry['isDir']) {
        self::ensureDir($destination);
        continue;
      }

      self::ensureDir(dirname($destination));

      if (is_file($destination)) {
        $backupPath = $backupDir . '/' . $relative;
        self::ensureDir(dirname($backupPath));
        if (!copy($destination, $backupPath)) {
          throw new Exception('Could not back up: ' . $relative);
        }
      }

      if (!copy($source, $destination)) {
        throw new Exception('Could not update: ' . $relative);
      }

      $perms = fileperms($source);
      if ($perms !== false) {
        @chmod($destination, $perms & 0777);
      }
    }
  }

  private static function ensureDataDir() {
    self::ensureDir(self::dataDir());
    $htaccess = self::dataDir() . '/.htaccess';
    if (!is_file($htaccess)) {
      @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }
    $index = self::dataDir() . '/index.html';
    if (!is_file($index)) {
      @file_put_contents($index, "<!doctype html><html><body></body></html>\n");
    }
  }

  private static function ensureDir($dir) {
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
      throw new Exception('Could not create directory: ' . $dir);
    }
  }

  private static function removeDir($dir) {
    if (!is_dir($dir)) {
      return;
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
      if ($item->isDir()) {
        @rmdir($item->getPathname());
      } else {
        @unlink($item->getPathname());
      }
    }

    @rmdir($dir);
  }

  private static function writeInstallStatus($state, $message, $extra = array()) {
    try {
      self::ensureDataDir();
      $status = array_merge($extra, array(
        'state' => $state,
        'message' => $message,
        'updatedAt' => gmdate('c'),
      ));

      @file_put_contents(self::statusPath(), json_encode($status));
    } catch (Exception $error) {
      // Status reporting must never block the update itself.
    }
  }

  private static function statusPath() {
    return rtrim(self::dataDir(), '/\\') . '/' . self::STATUS_FILE;
  }

  private static function dataDir() {
    return __DIR__ . '/data';
  }

  private static function randomSuffix() {
    try {
      return bin2hex(random_bytes(4));
    } catch (Exception $error) {
      return substr(str_replace('.', '', uniqid('', true)), -8);
    }
  }
}
