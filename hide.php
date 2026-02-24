<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $data): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(405, ['ok' => false, 'error' => 'Use POST']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) respond(400, ['ok' => false, 'error' => 'Invalid JSON']);

$id = $data['id'] ?? null;
$hide = $data['hide'] ?? 1; // default: hide
$reason = isset($data['reason']) ? trim((string)$data['reason']) : null;

if (!is_numeric($id)) respond(400, ['ok' => false, 'error' => 'Missing/invalid id']);
$id = (int)$id;
$hide = (int)(!!$hide);

$dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'data.sqlite';

try {
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  respond(500, ['ok' => false, 'error' => 'DB open failed', 'detail' => $e->getMessage()]);
}

if ($hide === 1) {
  // hide
  $stmt = $pdo->prepare("
    UPDATE gps_points
    SET hidden_at = strftime('%Y-%m-%dT%H:%M:%fZ','now'),
        hidden_reason = :reason
    WHERE id = :id
  ");
  $stmt->execute([':reason' => $reason, ':id' => $id]);
} else {
  // unhide
  $stmt = $pdo->prepare("
    UPDATE gps_points
    SET hidden_at = NULL,
        hidden_reason = NULL
    WHERE id = :id
  ");
  $stmt->execute([':id' => $id]);
}

respond(200, ['ok' => true, 'id' => $id, 'hide' => $hide, 'changed' => $stmt->rowCount()]);