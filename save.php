<?php
declare(strict_types=1);

// save.php — receives batch JSON and writes to SQLite (single table)

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $data): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(405, ['ok' => false, 'error' => 'Use POST']);
}

// Read raw JSON body
$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
  respond(400, ['ok' => false, 'error' => 'Empty body']);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
  respond(400, ['ok' => false, 'error' => 'Invalid JSON']);
}

// Expect batch shape
$items = $data['items'] ?? null;
if (!is_array($items)) {
  respond(400, ['ok' => false, 'error' => 'Missing items[]']);
}

$sentAt = (string)($data['sentAt'] ?? gmdate('c'));
$reason = (string)($data['reason'] ?? 'unknown');

// Open / create SQLite DB
$dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'data.sqlite';

try {
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  respond(500, ['ok' => false, 'error' => 'DB open failed', 'detail' => $e->getMessage()]);
}

// Create single table if not exists
$pdo->exec("
  CREATE TABLE IF NOT EXISTS gps_points (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    received_at TEXT NOT NULL,      -- server time
    sent_at TEXT,                   -- client batch sentAt
    reason TEXT,                    -- batch reason

    category TEXT NOT NULL,
    user TEXT,                      -- optional
    captured_at TEXT NOT NULL,      -- client ISO timestamp

    p1_lat REAL NOT NULL,
    p1_lon REAL NOT NULL,
    p1_accuracy_m REAL,
    p1_timestamp_ms INTEGER,

    p2_lat REAL NOT NULL,
    p2_lon REAL NOT NULL,
    p2_accuracy_m REAL,
    p2_timestamp_ms INTEGER,

    dt_sec REAL,
    distance_m REAL,
    speed_kmh REAL,
    direction_deg REAL,

    raw_json TEXT                  -- full original item for debugging/future-proofing
  );
");

// Prepared insert
$ins = $pdo->prepare("
  INSERT INTO gps_points (
    received_at, sent_at, reason,
    category, user, captured_at,
    p1_lat, p1_lon, p1_accuracy_m, p1_timestamp_ms,
    p2_lat, p2_lon, p2_accuracy_m, p2_timestamp_ms,
    dt_sec, distance_m, speed_kmh, direction_deg,
    raw_json
  ) VALUES (
    :received_at, :sent_at, :reason,
    :category, :user, :captured_at,
    :p1_lat, :p1_lon, :p1_accuracy_m, :p1_timestamp_ms,
    :p2_lat, :p2_lon, :p2_accuracy_m, :p2_timestamp_ms,
    :dt_sec, :distance_m, :speed_kmh, :direction_deg,
    :raw_json
  );
");

$receivedAt = gmdate('c');
$inserted = 0;
$errors = [];

$pdo->beginTransaction();
try {
  foreach ($items as $idx => $item) {
    if (!is_array($item)) {
      $errors[] = ['index' => $idx, 'error' => 'Item is not an object'];
      continue;
    }

    $category = strtoupper((string)($item['category'] ?? ''));
    $capturedAt = (string)($item['capturedAt'] ?? '');

    $p1 = $item['point1'] ?? null;
    $p2 = $item['point2'] ?? null;
    $derived = $item['derived'] ?? [];

    if ($category === '' || $capturedAt === '' || !is_array($p1) || !is_array($p2)) {
      $errors[] = ['index' => $idx, 'error' => 'Missing category/capturedAt/point1/point2'];
      continue;
    }

    // Required lat/lon
    $p1lat = $p1['lat'] ?? null; $p1lon = $p1['lon'] ?? null;
    $p2lat = $p2['lat'] ?? null; $p2lon = $p2['lon'] ?? null;
    if (!is_numeric($p1lat) || !is_numeric($p1lon) || !is_numeric($p2lat) || !is_numeric($p2lon)) {
      $errors[] = ['index' => $idx, 'error' => 'Invalid lat/lon'];
      continue;
    }

    $user = isset($item['user']) && trim((string)$item['user']) !== '' ? (string)$item['user'] : null;

    $ins->execute([
      ':received_at' => $receivedAt,
      ':sent_at' => $sentAt,
      ':reason' => $reason,

      ':category' => $category,
      ':user' => $user,
      ':captured_at' => $capturedAt,

      ':p1_lat' => (float)$p1lat,
      ':p1_lon' => (float)$p1lon,
      ':p1_accuracy_m' => isset($p1['accuracyM']) && is_numeric($p1['accuracyM']) ? (float)$p1['accuracyM'] : null,
      ':p1_timestamp_ms' => isset($p1['timestampMs']) && is_numeric($p1['timestampMs']) ? (int)$p1['timestampMs'] : null,

      ':p2_lat' => (float)$p2lat,
      ':p2_lon' => (float)$p2lon,
      ':p2_accuracy_m' => isset($p2['accuracyM']) && is_numeric($p2['accuracyM']) ? (float)$p2['accuracyM'] : null,
      ':p2_timestamp_ms' => isset($p2['timestampMs']) && is_numeric($p2['timestampMs']) ? (int)$p2['timestampMs'] : null,

      ':dt_sec' => isset($derived['dtSec']) && is_numeric($derived['dtSec']) ? (float)$derived['dtSec'] : null,
      ':distance_m' => isset($derived['distanceM']) && is_numeric($derived['distanceM']) ? (float)$derived['distanceM'] : null,
      ':speed_kmh' => isset($derived['speedKmh']) && is_numeric($derived['speedKmh']) ? (float)$derived['speedKmh'] : null,
      ':direction_deg' => isset($derived['directionDeg']) && is_numeric($derived['directionDeg']) ? (float)$derived['directionDeg'] : null,

      ':raw_json' => json_encode($item, JSON_UNESCAPED_SLASHES),
    ]);

    $inserted++;
  }

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  respond(500, ['ok' => false, 'error' => 'Insert failed', 'detail' => $e->getMessage()]);
}

respond(200, [
  'ok' => true,
  'inserted' => $inserted,
  'errors' => $errors,
]);
