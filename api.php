<?php

require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

define('ITEM_SELECT', 'id, sku, name, location_code, location_detail, quantity, category, notes, photo_mime, photo_updated_at, created_at, updated_at');
define('MAX_PHOTO_BYTES', 3145728);

function read_json_input() {
  $raw = file_get_contents('php://input');
  if ($raw === false || trim($raw) === '') {
    return array();
  }

  $data = json_decode($raw, true);
  if (!is_array($data)) {
    json_response(array('error' => 'Invalid JSON body'), 400);
  }

  return $data;
}

function clean_quantity($value) {
  return (int) filter_var($value, FILTER_VALIDATE_INT, array(
    'options' => array('default' => 1, 'min_range' => 1, 'max_range' => 9999),
  ));
}

function array_value($array, $key, $default = '') {
  return isset($array[$key]) ? $array[$key] : $default;
}

function iso_time($value) {
  if (!$value) {
    return null;
  }

  $timestamp = strtotime((string) $value);
  if ($timestamp === false) {
    return (string) $value;
  }

  return gmdate('c', $timestamp);
}

function photo_version($value) {
  $timestamp = strtotime((string) $value);
  return $timestamp === false ? '0' : (string) $timestamp;
}

function row_to_item($row) {
  $hasPhoto = isset($row['photo_mime']) && (string) $row['photo_mime'] !== '';
  $id = (int) $row['id'];
  $photoTime = array_value($row, 'photo_updated_at', array_value($row, 'updated_at', ''));

  return array(
    'id' => $id,
    'sku' => (string) array_value($row, 'sku', ''),
    'name' => (string) $row['name'],
    'locationCode' => (string) array_value($row, 'location_code', ''),
    'locationDetail' => (string) array_value($row, 'location_detail', ''),
    'quantity' => (int) $row['quantity'],
    'category' => (string) array_value($row, 'category', ''),
    'notes' => (string) array_value($row, 'notes', ''),
    'hasPhoto' => $hasPhoto,
    'photoUrl' => $hasPhoto ? 'photo.php?id=' . $id . '&v=' . photo_version($photoTime) : '',
    'createdAt' => iso_time(array_value($row, 'created_at', null)),
    'updatedAt' => iso_time(array_value($row, 'updated_at', null)),
  );
}

function item_input($data) {
  $sku = strtoupper(clean_text(array_value($data, 'sku', ''), 64));
  $name = clean_text(array_value($data, 'name', ''), 160);
  $locationCode = strtoupper(clean_text(array_value($data, 'locationCode', ''), 80));
  $locationDetail = clean_text(array_value($data, 'locationDetail', ''), 160);
  $category = clean_text(array_value($data, 'category', ''), 80);
  $notes = clean_notes(array_value($data, 'notes', ''));
  $quantity = clean_quantity(array_value($data, 'quantity', 1));

  if ($name === '') {
    json_response(array('error' => 'Item name is required'), 422);
  }

  if ($locationCode === '') {
    json_response(array('error' => 'Bin/location is required'), 422);
  }

  return array(
    'sku' => $sku,
    'name' => $name,
    'location_code' => $locationCode,
    'location_detail' => $locationDetail,
    'quantity' => $quantity,
    'category' => $category,
    'notes' => $notes,
  );
}

function photo_input($data) {
  if (!empty($data['removePhoto'])) {
    return array('remove' => true);
  }

  if (!isset($data['photoData']) || !is_string($data['photoData']) || $data['photoData'] === '') {
    return null;
  }

  $mime = clean_text(array_value($data, 'photoMime', 'image/jpeg'), 80);
  $allowed = array('image/jpeg', 'image/png', 'image/webp');
  if (!in_array($mime, $allowed, true)) {
    json_response(array('error' => 'Photo must be JPEG, PNG, or WebP'), 422);
  }

  $bytes = base64_decode($data['photoData'], true);
  if ($bytes === false) {
    json_response(array('error' => 'Photo could not be decoded'), 422);
  }

  if (strlen($bytes) > MAX_PHOTO_BYTES) {
    json_response(array('error' => 'Photo is too large after compression'), 422);
  }

  return array(
    'remove' => false,
    'mime' => $mime,
    'data' => $bytes,
  );
}

function bind_and_execute($stmt, $params) {
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

function query_tokens($query) {
  $query = strtolower(clean_text($query, 120));
  if ($query === '') {
    return array();
  }

  $tokens = preg_split('/\s+/', $query);
  return array_values(array_filter($tokens ? $tokens : array()));
}

function where_for_tokens($tokens, &$params) {
  if (!$tokens) {
    return '';
  }

  $clauses = array();
  foreach ($tokens as $index => $token) {
    $param = ':q' . $index;
    $clauses[] = "LOWER(CONCAT_WS(' ', sku, name, location_code, location_detail, category, notes)) LIKE $param";
    $params[$param] = '%' . $token . '%';
  }

  return 'WHERE ' . implode(' AND ', $clauses);
}

function list_items($query) {
  $params = array();
  $where = where_for_tokens(query_tokens($query), $params);
  $sql = 'SELECT ' . ITEM_SELECT . " FROM inventory_items $where ORDER BY updated_at DESC, id DESC LIMIT 250";
  $stmt = inventory_db()->prepare($sql);
  $stmt->execute($params);

  return array_map('row_to_item', $stmt->fetchAll());
}

function get_item($id) {
  $stmt = inventory_db()->prepare('SELECT ' . ITEM_SELECT . ' FROM inventory_items WHERE id = :id');
  $stmt->execute(array(':id' => $id));
  $row = $stmt->fetch();
  if (!$row) {
    json_response(array('error' => 'Item not found'), 404);
  }
  return row_to_item($row);
}

function stats() {
  $row = inventory_db()->query(
    "SELECT
      COUNT(*) AS item_count,
      COALESCE(SUM(quantity), 0) AS unit_count,
      COUNT(DISTINCT location_code) AS location_count,
      MAX(updated_at) AS last_updated
    FROM inventory_items"
  )->fetch();

  return array(
    'itemCount' => (int) array_value($row, 'item_count', 0),
    'unitCount' => (int) array_value($row, 'unit_count', 0),
    'locationCount' => (int) array_value($row, 'location_count', 0),
    'lastUpdated' => iso_time(array_value($row, 'last_updated', null)),
  );
}

function distinct_values($column) {
  $allowed = array('location_code', 'location_detail', 'category');
  if (!in_array($column, $allowed, true)) {
    return array();
  }

  $stmt = inventory_db()->query("SELECT DISTINCT $column AS value FROM inventory_items WHERE $column <> '' ORDER BY $column LIMIT 200");
  $values = array();
  foreach ($stmt->fetchAll() as $row) {
    $values[] = (string) $row['value'];
  }
  return $values;
}

function meta() {
  return array(
    'stats' => stats(),
    'locations' => distinct_values('location_code'),
    'locationDetails' => distinct_values('location_detail'),
    'categories' => distinct_values('category'),
  );
}

function send_inventory($query = '') {
  json_response(array(
    'items' => list_items($query),
    'meta' => meta(),
  ));
}

try {
  inventory_ensure_schema();

  $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

  if ($method === 'GET') {
    $query = isset($_GET['q']) ? (string) $_GET['q'] : '';
    send_inventory($query);
  }

  if ($method !== 'POST') {
    json_response(array('error' => 'Method not allowed'), 405);
  }

  $input = read_json_input();
  $action = clean_text(array_value($input, 'action', ''), 20);

  if ($action === 'create') {
    $item = item_input($input);
    $photo = photo_input($input);

    if ($photo && empty($photo['remove'])) {
      $stmt = inventory_db()->prepare(
        "INSERT INTO inventory_items
          (sku, name, location_code, location_detail, quantity, category, notes, photo_mime, photo_data, photo_updated_at)
        VALUES
          (:sku, :name, :location_code, :location_detail, :quantity, :category, :notes, :photo_mime, :photo_data, NOW())"
      );
      bind_and_execute($stmt, array(
        ':sku' => $item['sku'],
        ':name' => $item['name'],
        ':location_code' => $item['location_code'],
        ':location_detail' => $item['location_detail'],
        ':quantity' => $item['quantity'],
        ':category' => $item['category'],
        ':notes' => $item['notes'],
        ':photo_mime' => $photo['mime'],
        ':photo_data' => $photo['data'],
      ));
    } else {
      $stmt = inventory_db()->prepare(
        "INSERT INTO inventory_items
          (sku, name, location_code, location_detail, quantity, category, notes)
        VALUES
          (:sku, :name, :location_code, :location_detail, :quantity, :category, :notes)"
      );
      bind_and_execute($stmt, array(
        ':sku' => $item['sku'],
        ':name' => $item['name'],
        ':location_code' => $item['location_code'],
        ':location_detail' => $item['location_detail'],
        ':quantity' => $item['quantity'],
        ':category' => $item['category'],
        ':notes' => $item['notes'],
      ));
    }

    json_response(array(
      'item' => get_item((int) inventory_db()->lastInsertId()),
      'items' => list_items(''),
      'meta' => meta(),
    ), 201);
  }

  if ($action === 'update') {
    $id = (int) filter_var(array_value($input, 'id', 0), FILTER_VALIDATE_INT, array(
      'options' => array('default' => 0, 'min_range' => 1),
    ));
    if ($id < 1) {
      json_response(array('error' => 'Item id is required'), 422);
    }

    $item = item_input($input);
    $photo = photo_input($input);

    $sets = array(
      'sku = :sku',
      'name = :name',
      'location_code = :location_code',
      'location_detail = :location_detail',
      'quantity = :quantity',
      'category = :category',
      'notes = :notes',
    );
    $params = array(
      ':sku' => $item['sku'],
      ':name' => $item['name'],
      ':location_code' => $item['location_code'],
      ':location_detail' => $item['location_detail'],
      ':quantity' => $item['quantity'],
      ':category' => $item['category'],
      ':notes' => $item['notes'],
      ':id' => $id,
    );

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
      $exists->execute(array(':id' => $id));
      if (!$exists->fetch()) {
        json_response(array('error' => 'Item not found'), 404);
      }
    }

    json_response(array(
      'item' => get_item($id),
      'items' => list_items(''),
      'meta' => meta(),
    ));
  }

  if ($action === 'adjust') {
    $id = (int) filter_var(array_value($input, 'id', 0), FILTER_VALIDATE_INT, array(
      'options' => array('default' => 0, 'min_range' => 1),
    ));
    $delta = (int) filter_var(array_value($input, 'delta', 0), FILTER_VALIDATE_INT, array(
      'options' => array('default' => 0, 'min_range' => -9999, 'max_range' => 9999),
    ));

    if ($id < 1 || $delta === 0) {
      json_response(array('error' => 'Quantity adjustment is invalid'), 422);
    }

    $stmt = inventory_db()->prepare(
      'UPDATE inventory_items SET quantity = GREATEST(1, quantity + :delta) WHERE id = :id'
    );
    bind_and_execute($stmt, array(':delta' => $delta, ':id' => $id));
    if ($stmt->rowCount() === 0) {
      json_response(array('error' => 'Item not found'), 404);
    }

    send_inventory();
  }

  if ($action === 'delete') {
    $id = (int) filter_var(array_value($input, 'id', 0), FILTER_VALIDATE_INT, array(
      'options' => array('default' => 0, 'min_range' => 1),
    ));
    if ($id < 1) {
      json_response(array('error' => 'Item id is required'), 422);
    }

    $stmt = inventory_db()->prepare('DELETE FROM inventory_items WHERE id = :id');
    bind_and_execute($stmt, array(':id' => $id));
    if ($stmt->rowCount() === 0) {
      json_response(array('error' => 'Item not found'), 404);
    }

    json_response(array(
      'deleted' => true,
      'items' => list_items(''),
      'meta' => meta(),
    ));
  }

  json_response(array('error' => 'Unknown action'), 400);
} catch (Exception $error) {
  json_response(array('error' => 'Server error'), 500);
}
