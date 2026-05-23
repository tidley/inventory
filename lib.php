<?php

function json_response($payload, $status = 200) {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES);
  exit;
}

function inventory_env() {
  $path = __DIR__ . '/.env';
  if (!file_exists($path)) {
    json_response(array('error' => 'Missing .env database config'), 500);
  }

  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($lines === false) {
    json_response(array('error' => '.env database config could not be read'), 500);
  }

  $env = array();
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
      continue;
    }

    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);
    if (
      ((substr($value, 0, 1) === '"') && (substr($value, -1) === '"')) ||
      ((substr($value, 0, 1) === "'") && (substr($value, -1) === "'"))
    ) {
      $value = substr($value, 1, -1);
    }
    $env[$key] = $value;
  }

  foreach (array('DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET', 'UPDATE_TOKEN', 'INVENTORY_UPDATE_REPO', 'INVENTORY_GITHUB_TOKEN', 'GITHUB_TOKEN', 'AUTH_USERNAME', 'AUTH_PIN_HASH', 'AUTH_PIN') as $key) {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
      $env[$key] = $value;
    }
  }

  foreach (array('DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD') as $required) {
    if (!isset($env[$required]) || $env[$required] === '') {
      json_response(array('error' => 'Missing ' . $required . ' in .env'), 500);
    }
  }

  return $env;
}

function inventory_config() {
  $env = inventory_env();
  $config = array(
    'host' => 'localhost',
    'database' => isset($env['DB_DATABASE']) ? $env['DB_DATABASE'] : '',
    'username' => isset($env['DB_USERNAME']) ? $env['DB_USERNAME'] : '',
    'password' => isset($env['DB_PASSWORD']) ? $env['DB_PASSWORD'] : '',
    'charset' => 'utf8mb4',
  );

  if (isset($env['DB_HOST']) && $env['DB_HOST'] !== '') {
    $config['host'] = $env['DB_HOST'];
  }
  if (isset($env['DB_CHARSET']) && $env['DB_CHARSET'] !== '') {
    $config['charset'] = $env['DB_CHARSET'];
  }

  return $config;
}

function inventory_db() {
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  if (!class_exists('PDO')) {
    json_response(array('error' => 'PDO is not enabled on this PHP installation'), 500);
  }

  $drivers = PDO::getAvailableDrivers();
  if (!in_array('mysql', $drivers, true)) {
    json_response(array('error' => 'PDO MySQL driver is not enabled on this PHP installation'), 500);
  }

  $config = inventory_config();
  $dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $config['host'],
    $config['database'],
    $config['charset']
  );

  try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], array(
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ));
  } catch (Exception $error) {
    json_response(array('error' => 'Database connection failed'), 500);
  }

  return $pdo;
}

function inventory_column_exists($column) {
  $stmt = inventory_db()->prepare(
    "SELECT COUNT(*) AS column_count
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'inventory_items'
        AND COLUMN_NAME = :column"
  );
  $stmt->execute(array(':column' => $column));
  $row = $stmt->fetch();
  return isset($row['column_count']) && (int) $row['column_count'] > 0;
}

function inventory_ensure_column($column, $definition) {
  if (!inventory_column_exists($column)) {
    inventory_db()->exec("ALTER TABLE inventory_items ADD COLUMN $definition");
  }
}

function inventory_ensure_bins_schema() {
  inventory_db()->exec(
    "CREATE TABLE IF NOT EXISTS inventory_bins (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      code VARCHAR(80) NOT NULL,
      label VARCHAR(160) NOT NULL DEFAULT '',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_code (code),
      KEY idx_label (label)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

function inventory_seed_bins_from_items() {
  inventory_db()->exec(
    "INSERT IGNORE INTO inventory_bins (code, label)
      SELECT DISTINCT location_code, location_code
      FROM inventory_items
      WHERE location_code <> ''"
  );
}

function inventory_ensure_categories_schema() {
  inventory_db()->exec(
    "CREATE TABLE IF NOT EXISTS inventory_categories (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      code VARCHAR(80) NOT NULL,
      label VARCHAR(160) NOT NULL DEFAULT '',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_code (code),
      KEY idx_label (label)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

function inventory_seed_categories_from_items() {
  inventory_db()->exec(
    "INSERT IGNORE INTO inventory_categories (code, label)
      SELECT DISTINCT category, category
      FROM inventory_items
      WHERE category <> ''"
  );
}

function inventory_ensure_schema() {
  inventory_db()->exec(
    "CREATE TABLE IF NOT EXISTS inventory_items (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      client_mutation_id VARCHAR(80) NULL DEFAULT NULL,
      sku VARCHAR(64) NOT NULL DEFAULT '',
      name VARCHAR(160) NOT NULL,
      location_code VARCHAR(80) NOT NULL,
      location_detail VARCHAR(160) NOT NULL DEFAULT '',
      quantity INT UNSIGNED NOT NULL DEFAULT 1,
      category VARCHAR(80) NOT NULL DEFAULT '',
      notes TEXT NULL,
      photo_mime VARCHAR(80) NOT NULL DEFAULT '',
      photo_data MEDIUMBLOB NULL,
      photo_updated_at TIMESTAMP NULL DEFAULT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_client_mutation_id (client_mutation_id),
      KEY idx_sku (sku),
      KEY idx_name (name),
      KEY idx_location_code (location_code),
      KEY idx_updated_at (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );

  inventory_ensure_column('client_mutation_id', 'client_mutation_id VARCHAR(80) NULL DEFAULT NULL AFTER id');
  inventory_ensure_column('sku', "sku VARCHAR(64) NOT NULL DEFAULT '' AFTER id");
  inventory_ensure_column('location_code', "location_code VARCHAR(80) NOT NULL DEFAULT '' AFTER name");
  inventory_ensure_column('location_detail', "location_detail VARCHAR(160) NOT NULL DEFAULT '' AFTER location_code");
  inventory_ensure_column('photo_mime', "photo_mime VARCHAR(80) NOT NULL DEFAULT '' AFTER notes");
  inventory_ensure_column('photo_data', 'photo_data MEDIUMBLOB NULL AFTER photo_mime');
  inventory_ensure_column('photo_updated_at', 'photo_updated_at TIMESTAMP NULL DEFAULT NULL AFTER photo_data');
  inventory_ensure_bins_schema();
  inventory_seed_bins_from_items();
  inventory_ensure_categories_schema();
  inventory_seed_categories_from_items();
}

function clean_text($value, $maxLength) {
  $text = is_string($value) ? $value : '';
  $text = preg_replace('/\s+/u', ' ', $text);
  $text = trim($text === null ? '' : $text);
  if (function_exists('mb_substr')) {
    return mb_substr($text, 0, $maxLength);
  }
  return substr($text, 0, $maxLength);
}

function clean_notes($value) {
  $text = is_string($value) ? $value : '';
  $text = preg_replace("/[ \t]+/u", ' ', $text);
  $text = trim($text === null ? '' : $text);
  if (function_exists('mb_substr')) {
    return mb_substr($text, 0, 1000);
  }
  return substr($text, 0, 1000);
}
