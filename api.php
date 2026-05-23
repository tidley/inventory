<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

const ITEM_SELECT = 'id, sku, name, location_code, location_detail, quantity, category, notes, photo_mime, photo_updated_at, created_at, updated_at';
const MAX_PHOTO_BYTES = 3145728;

function read_json_input(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || trim($raw) === '') {
    return [];
  }

  $data = json_decode($raw, true);
  if (!is_array($data)) {
    json_response(['error' => 'Invalid JSON body'], 400);
  }

  return $data;
}

function clean_quantity($value): int {
  return (int) filter_var($value, FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1, 'max_range' => 9999],
  ]);
}

function iso_time($value): ?string {
  if (!$value) {
    return null;
  }

  $timestamp = strtotime((string) $value);
  if ($timestamp === false) {
    return (string) $value;
  }

  return gmdate('c', $timestamp);
}

function photo_version($value): string {
  $timestamp = strtotime((string) $value);
  return $timestamp === false ? '0' : (string) $timestamp;
}

function row_to_item(array $row): array {
  $hasPhoto = isset($row['photo_mime']) && (string) $row['photo_mime'] !== '';
  $id = (int) $row['id'];

  return [
    'id' => $id,
    'sku' => (string) ($row['sku'] ?? ''),
    'name' => (string) $row['name'],
    'locationCode' => (string) ($row['location_code'] ?? ''),
    'locationDetail' => (string) ($row['location_detail'] ?? ''),
    'quantity' => (int) $row['quantity'],
    'category' => (string) ($row['category'] ?? ''),
    'notes' => (string) ($row['notes'] ?? ''),
    'hasPhoto' => $hasPhoto,
    'photoUrl' => $hasPhoto ? 'photo.php?id=' . $id . '&v=' . photo_version($row['photo_updated_at'] ?? $row['updated_at'] ?? '') : '',
    'createdAt' => iso_time($row['created_at'] ?? null),
    'updatedAt' => iso_time($row['updated_at'] ?? null),
  ];
}

function item_input(array $data): array {
  $sku = strtoupper(clean_text($data['sku'] ?? '', 64));
  $name = clean_text($data['name'] ?? '', 160);
  $locationCode = strtoupper(clean_text($data['locationCode'] ?? '', 80));
  $locationDetail = clean_text($data['locationDetail'] ?? '', 160);
  $category = clean_text($data['category'] ?? '', 80);
  $notes = clean_notes($data['notes'] ?? '');
  $quantity = clean_quantity($data['quantity'] ?? 1);

  if ($name === '') {
    json_response(['error' => 'Item name is required'], 422);
  }

  if ($locationCode === '') {
    json_response(['error' => 'Bin/location is required'], 422);
  }

  return [
    'sku' => $sku,
    'name' => $name,
    'location_code' => $locationCode,
    'location_detail' => $locationDetail,
    'quantity' => $quantity,
    'category' => $category,
    'notes' => $notes,
  ];
}

function photo_input(array $data): ?array {
  if (!empty($data['removePhoto'])) {
    return ['remove' => true];
  }

  if (!isset($data['photoData']) || !is_string($data['photoData']) || $data['photoData'] === '') {
    return null;
  }

  $mime = clean_text($data['photoMime'] ?? 'image/jpeg', 80);
  $allowed = ['image/jpeg', 'image/png', 'image/webp'];
  if (!in_array($mime, $allowed, true)) {
    json_response(['error' => 'Photo must be JPEG, PNG, or WebP'], 422);
  }

  $bytes = base64_decode($data['photoData'], true);
  if ($bytes === false) {
    json_response(['error' => 'Photo could not be decoded'], 422);
  }

  if (strlen($bytes) > MAX_PHOTO_BYTES) {
    json_response(['error' => 'Photo is too large after compression'], 422);
  }

  return [
    'remove' => false,
    'mime' => $mime,
    'data' => $bytes,
  ];
}

function bind_and_execute(PDOStatement $stmt, array $params): void {
  foreach ($params as $key => $value) {
    if ($key === ':photo_data') {
      $stmt->bindValue($key, $value, PDO::PARAM_LOB);
    } elseif (is_int($value)) {
      $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } elseif ($value === null) {
      $stmt->bindValue($key, null, PDO::PARAM_NULL);
    } else {
      $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
  }
  $stmt->execute();
}

function query_tokens(string $query): array {
  $query = strtolower(clean_text($query, 120));
  if ($query === '') {
    return [];
  }

  return array_values(array_filter(preg_split('/\s+/', $query) ?: []));
}

function where_for_tokens(array $tokens, array &$params): string {
  if (!$tokens) {
    return '';
  }

  $clauses = [];
  foreach ($tokens as $index => $token) {
    $param = ':q' . $index;
    $clauses[] = "LOWER(CONCAT_WS(' ', sku, name, location_code, location_detail, category, notes)) LIKE $param";
    $params[$param] = '%' . $token . '%';
  }

  return 'WHERE ' . implode(' AND ', $clauses);
}

function list_items(string $query): array {
  $params = [];
  $where = where_for_tokens(query_tokens($query), $params);
  $sql = 'SELECT ' . ITEM_SELECT . " FROM inventory_items $where ORDER BY updated_at DESC, id DESC LIMIT 250";
  $stmt = inventory_db()->prepare($sql);
  $stmt->execute($params);

  return array_map('row_to_item', $stmt->fetchAll());
}

function get_item(int $id): array {
  $stmt = inventory_db()->prepare('SELECT ' . ITEM_SELECT . ' FROM inventory_items WHERE id = :id');
  $stmt->execute([':id' => $id]);
  $row = $stmt->fetch();
  if (!$row) {
    json_response(['error' => 'Item not found'], 404);
  }
  return row_to_item($row);
}

function stats(): array {
  $row = inventory_db()->query(
    "SELECT
      COUNT(*) AS item_count,
      COALESCE(SUM(quantity), 0) AS unit_count,
      COUNT(DISTINCT location_code) AS location_count,
      MAX(updated_at) AS last_updated
    FROM inventory_items"
  )->fetch();

  return [
    'itemCount' => (int) ($row['item_count'] ?? 0),
    'unitCount' => (int) ($row['unit_count'] ?? 0),
    'locationCount' => (int) ($row['location_count'] ?? 0),
    'lastUpdated' => iso_time($row['last_updated'] ?? null),
  ];
}

function distinct_values(string $column): array {
  $allowed = ['location_code', 'location_detail', 'category'];
  if (!in_array($column, $allowed, true)) {
    return [];
  }

  $stmt = inventory_db()->query("SELECT DISTINCT $column AS value FROM inventory_items WHERE $column <> '' ORDER BY $column LIMIT 200");
  return array_values(array_map(
    fn(array $row): string => (string) $row['value'],
    $stmt->fetchAll()
  ));
}

function meta(): array {
  return [
    'stats' => stats(),
    'locations' => distinct_values('location_code'),
    'locationDetails' => distinct_values('location_detail'),
    'categories' => distinct_values('category'),
  ];
}

function send_inventory(string $query = ''): void {
  json_response([
    'items' => list_items($query),
    'meta' => meta(),
  ]);
}

inventory_ensure_schema();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $query = isset($_GET['q']) ? (string) $_GET['q'] : '';
  send_inventory($query);
}

if ($method !== 'POST') {
  json_response(['error' => 'Method not allowed'], 405);
}

$input = read_json_input();
$action = clean_text($input['action'] ?? '', 20);

if ($action === 'create') {
  $item = item_input($input);
  $photo = photo_input($input);

  if ($photo && !$photo['remove']) {
    $stmt = inventory_db()->prepare(
      "INSERT INTO inventory_items
        (sku, name, location_code, location_detail, quantity, category, notes, photo_mime, photo_data, photo_updated_at)
      VALUES
        (:sku, :name, :location_code, :location_detail, :quantity, :category, :notes, :photo_mime, :photo_data, NOW())"
    );
    bind_and_execute($stmt, [
      ':sku' => $item['sku'],
      ':name' => $item['name'],
      ':location_code' => $item['location_code'],
      ':location_detail' => $item['location_detail'],
      ':quantity' => $item['quantity'],
      ':category' => $item['category'],
      ':notes' => $item['notes'],
      ':photo_mime' => $photo['mime'],
      ':photo_data' => $photo['data'],
    ]);
  } else {
    $stmt = inventory_db()->prepare(
      "INSERT INTO inventory_items
        (sku, name, location_code, location_detail, quantity, category, notes)
      VALUES
        (:sku, :name, :location_code, :location_detail, :quantity, :category, :notes)"
    );
    bind_and_execute($stmt, [
      ':sku' => $item['sku'],
      ':name' => $item['name'],
      ':location_code' => $item['location_code'],
      ':location_detail' => $item['location_detail'],
      ':quantity' => $item['quantity'],
      ':category' => $item['category'],
      ':notes' => $item['notes'],
    ]);
  }

  json_response([
    'item' => get_item((int) inventory_db()->lastInsertId()),
    'items' => list_items(''),
    'meta' => meta(),
  ], 201);
}

if ($action === 'update') {
  $id = (int) filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['default' => 0, 'min_range' => 1],
  ]);
  if ($id < 1) {
    json_response(['error' => 'Item id is required'], 422);
  }

  $item = item_input($input);
  $photo = photo_input($input);

  $sets = [
    'sku = :sku',
    'name = :name',
    'location_code = :location_code',
    'location_detail = :location_detail',
    'quantity = :quantity',
    'category = :category',
    'notes = :notes',
  ];
  $params = [
    ':sku' => $item['sku'],
    ':name' => $item['name'],
    ':location_code' => $item['location_code'],
    ':location_detail' => $item['location_detail'],
    ':quantity' => $item['quantity'],
    ':category' => $item['category'],
    ':notes' => $item['notes'],
    ':id' => $id,
  ];

  if ($photo && !empty($photo['remove'])) {
    $sets[] = "photo_mime = ''";
    $sets[] = 'photo_data = NULL';
    $sets[] = 'photo_updated_at = NULL';
  } elseif ($photo) {
    $sets[] = 'photo_mime = :photo_mime';
    $sets[] = 'photo_data = :photo_data';
    $sets[] = 'photo_updated_at = NOW()';
    $params[':photo_mime'] = $photo['mime'];
    $params[':photo_data'] = $photo['data'];
  }

  $stmt = inventory_db()->prepare('UPDATE inventory_items SET ' . implode(', ', $sets) . ' WHERE id = :id');
  bind_and_execute($stmt, $params);

  if ($stmt->rowCount() === 0) {
    $exists = inventory_db()->prepare('SELECT id FROM inventory_items WHERE id = :id');
    $exists->execute([':id' => $id]);
    if (!$exists->fetch()) {
      json_response(['error' => 'Item not found'], 404);
    }
  }

  json_response([
    'item' => get_item($id),
    'items' => list_items(''),
    'meta' => meta(),
  ]);
}

if ($action === 'adjust') {
  $id = (int) filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['default' => 0, 'min_range' => 1],
  ]);
  $delta = (int) filter_var($input['delta'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['default' => 0, 'min_range' => -9999, 'max_range' => 9999],
  ]);

  if ($id < 1 || $delta === 0) {
    json_response(['error' => 'Quantity adjustment is invalid'], 422);
  }

  $stmt = inventory_db()->prepare(
    'UPDATE inventory_items SET quantity = GREATEST(1, quantity + :delta) WHERE id = :id'
  );
  bind_and_execute($stmt, [':delta' => $delta, ':id' => $id]);
  if ($stmt->rowCount() === 0) {
    json_response(['error' => 'Item not found'], 404);
  }

  send_inventory();
}

if ($action === 'delete') {
  $id = (int) filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['default' => 0, 'min_range' => 1],
  ]);
  if ($id < 1) {
    json_response(['error' => 'Item id is required'], 422);
  }

  $stmt = inventory_db()->prepare('DELETE FROM inventory_items WHERE id = :id');
  bind_and_execute($stmt, [':id' => $id]);
  if ($stmt->rowCount() === 0) {
    json_response(['error' => 'Item not found'], 404);
  }

  json_response([
    'deleted' => true,
    'items' => list_items(''),
    'meta' => meta(),
  ]);
}

json_response(['error' => 'Unknown action'], 400);
