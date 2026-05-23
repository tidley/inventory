<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$id = (int) filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, [
  'options' => ['default' => 0, 'min_range' => 1],
]);

if ($id < 1) {
  http_response_code(404);
  exit;
}

inventory_ensure_schema();

$stmt = inventory_db()->prepare('SELECT photo_mime, photo_data, photo_updated_at FROM inventory_items WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row || !$row['photo_data'] || !$row['photo_mime']) {
  http_response_code(404);
  exit;
}

$updated = strtotime((string) ($row['photo_updated_at'] ?? '')) ?: time();
$etag = '"' . sha1($id . ':' . $updated) . '"';

header('Content-Type: ' . $row['photo_mime']);
header('Cache-Control: private, max-age=604800');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $updated) . ' GMT');
header('ETag: ' . $etag);

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
  http_response_code(304);
  exit;
}

echo $row['photo_data'];
