<?php
declare(strict_types=1);

function json_response(array $payload, int $status = 200): void {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES);
  exit;
}

function inventory_config(): array {
  $env = inventory_env();
  $config = [
    'host' => 'localhost',
    'database' => $env['DB_DATABASE'] ?? '',
    'username' => $env['DB_USERNAME'] ?? '',
    'password' => $env['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
  ];

  if (($env['DB_HOST'] ?? '') !== '') {
    $config['host'] = $env['DB_HOST'];
  }
  if (($env['DB_CHARSET'] ?? '') !== '') {
    $config['charset'] = $env['DB_CHARSET'];
  }

  return $config;
}

function inventory_env(): array {
  $path = __DIR__ . '/.env';
  if (!file_exists($path)) {
    json_response(['error' => 'Missing .env database config'], 500);
  }

  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($lines === false) {
    json_response(['error' => '.env database config could not be read'], 500);
  }

  $env = [];
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
      continue;
    }

    [$key, $value] = explode('=', $line, 2);
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

  foreach (['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $required) {
    if (($env[$required] ?? '') === '') {
      json_response(['error' => 'Missing ' . $required . ' in .env'], 500);
    }
  }

  return $env;
}

function inventory_db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $config = inventory_config();
  $dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $config['host'],
    $config['database'],
    $config['charset']
  );

  try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  } catch (Throwable $error) {
    json_response(['error' => 'Database connection failed'], 500);
  }

  return $pdo;
}

function inventory_column_exists(string $column): bool {
  $stmt = inventory_db()->prepare('SHOW COLUMNS FROM inventory_items LIKE :column');
  $stmt->execute([':column' => $column]);
  return (bool) $stmt->fetch();
}

function inventory_ensure_column(string $column, string $definition): void {
  if (!inventory_column_exists($column)) {
    inventory_db()->exec("ALTER TABLE inventory_items ADD COLUMN $definition");
  }
}

function inventory_ensure_schema(): void {
  inventory_db()->exec(
    "CREATE TABLE IF NOT EXISTS inventory_items (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
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
      KEY idx_sku (sku),
      KEY idx_name (name),
      KEY idx_location_code (location_code),
      KEY idx_updated_at (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );

  inventory_ensure_column('sku', "sku VARCHAR(64) NOT NULL DEFAULT '' AFTER id");
  inventory_ensure_column('location_code', "location_code VARCHAR(80) NOT NULL DEFAULT '' AFTER name");
  inventory_ensure_column('location_detail', "location_detail VARCHAR(160) NOT NULL DEFAULT '' AFTER location_code");
  inventory_ensure_column('photo_mime', "photo_mime VARCHAR(80) NOT NULL DEFAULT '' AFTER notes");
  inventory_ensure_column('photo_data', 'photo_data MEDIUMBLOB NULL AFTER photo_mime');
  inventory_ensure_column('photo_updated_at', 'photo_updated_at TIMESTAMP NULL DEFAULT NULL AFTER photo_data');
}

function clean_text($value, int $maxLength): string {
  $text = is_string($value) ? $value : '';
  $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
  if (function_exists('mb_substr')) {
    return mb_substr($text, 0, $maxLength);
  }
  return substr($text, 0, $maxLength);
}

function clean_notes($value): string {
  $text = is_string($value) ? $value : '';
  $text = trim(preg_replace("/[ \t]+/u", ' ', $text) ?? '');
  if (function_exists('mb_substr')) {
    return mb_substr($text, 0, 1000);
  }
  return substr($text, 0, 1000);
}
