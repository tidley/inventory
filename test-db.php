<?php

require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

try {
  $pdo = inventory_db();
  $schemaOk = false;
  $schemaError = null;
  try {
    inventory_ensure_schema();
    $schemaOk = true;
  } catch (Exception $error) {
    $schemaError = $error->getMessage();
  }

  $stmt = $pdo->query('SELECT DATABASE() AS db');
  $row = $stmt->fetch();
  json_response(array(
    'ok' => true,
    'database' => isset($row['db']) ? $row['db'] : null,
    'pdo_mysql' => in_array('mysql', PDO::getAvailableDrivers(), true),
    'schema_ok' => $schemaOk,
    'schema_error' => $schemaError,
  ));
} catch (Exception $error) {
  json_response(array(
    'ok' => false,
    'error' => 'Database test failed',
  ), 500);
}
