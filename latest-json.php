<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'data.sqlite';
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) 
         ? max(1, min(500, (int)$_GET['limit'])) : 50;

try {
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'DB open failed', 'detail' => $e->getMessage()]);
  exit;
}

// Get total count
$countStmt = $pdo->query("SELECT COUNT(*) AS c FROM gps_points");
$total = (int)($countStmt->fetch()['c'] ?? 0);

// Get latest rows with all fields
$stmt = $pdo->prepare("
  SELECT
    id, received_at, sent_at, reason,
    category, user, captured_at,
    p1_lat, p1_lon, p1_accuracy_m, p1_timestamp_ms,
    p2_lat, p2_lon, p2_accuracy_m, p2_timestamp_ms,
    dt_sec, distance_m, speed_kmh, direction_deg
  FROM gps_points
  ORDER BY id DESC
  LIMIT :limit
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll();

echo json_encode([
  'total' => $total,
  'limit' => $limit,
  'count' => count($rows),
  'rows' => $rows
], JSON_UNESCAPED_SLASHES);
