<?php
declare(strict_types=1);

// latest.php — show latest rows as an HTML table (SQLite)

$limit = 50;
if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
  $limit = max(1, min(500, (int)$_GET['limit'])); // clamp 1..500
}

$dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'data.sqlite';

function h(?string $s): string {
  return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
  $pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo "<h3>DB open failed</h3><pre>" . h($e->getMessage()) . "</pre>";
  exit;
}

// Make sure table exists (in case someone hits latest.php before save.php)
$pdo->exec("
  CREATE TABLE IF NOT EXISTS gps_points (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    received_at TEXT NOT NULL,
    sent_at TEXT,
    reason TEXT,

    category TEXT NOT NULL,
    user TEXT,
    captured_at TEXT NOT NULL,

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

    raw_json TEXT
  );
");

$stmt = $pdo->prepare("
  SELECT
    id, received_at, sent_at, reason,
    category, user, captured_at,
    p1_lat, p1_lon, p1_accuracy_m,
    p2_lat, p2_lon, p2_accuracy_m,
    dt_sec, distance_m, speed_kmh, direction_deg
  FROM gps_points
  ORDER BY id DESC
  LIMIT :limit
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$countStmt = $pdo->query("SELECT COUNT(*) AS c FROM gps_points");
$total = (int)($countStmt->fetch()['c'] ?? 0);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Latest GPS Points</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 16px; }
    .meta { margin: 8px 0 14px; color: #444; }
    table { border-collapse: collapse; width: 100%; font-size: 14px; }
    th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
    th { position: sticky; top: 0; background: #f6f6f6; }
    tr:nth-child(even) { background: #fafafa; }
    code { font-size: 12px; }
    .small { font-size: 12px; color: #555; }
  </style>
</head>
<body>
  <h2>Latest GPS Points</h2>
  <div class="meta">
    Showing latest <b><?= (int)$limit ?></b> of <b><?= (int)$total ?></b> total.
    &nbsp;|&nbsp; Try <code>?limit=100</code>
  </div>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Category</th>
        <th>User</th>
        <th>Captured</th>
        <th>Received</th>
        <th>P1 (lat,lon) acc</th>
        <th>P2 (lat,lon) acc</th>
        <th>dt (s)</th>
        <th>dist (m)</th>
        <th>speed (km/h)</th>
        <th>dir (deg)</th>
        <th class="small">Batch</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h((string)$r['category']) ?></td>
          <td><?= h($r['user'] !== null ? (string)$r['user'] : '') ?></td>
          <td><?= h((string)$r['captured_at']) ?></td>
          <td><?= h((string)$r['received_at']) ?></td>

          <td>
            <?= h(number_format((float)$r['p1_lat'], 6)) ?>,
            <?= h(number_format((float)$r['p1_lon'], 6)) ?>
            <div class="small">acc: <?= h($r['p1_accuracy_m'] !== null ? (string)round((float)$r['p1_accuracy_m']) : '') ?>m</div>
          </td>

          <td>
            <?= h(number_format((float)$r['p2_lat'], 6)) ?>,
            <?= h(number_format((float)$r['p2_lon'], 6)) ?>
            <div class="small">acc: <?= h($r['p2_accuracy_m'] !== null ? (string)round((float)$r['p2_accuracy_m']) : '') ?>m</div>
          </td>

          <td><?= h($r['dt_sec'] !== null ? number_format((float)$r['dt_sec'], 2) : '') ?></td>
          <td><?= h($r['distance_m'] !== null ? number_format((float)$r['distance_m'], 2) : '') ?></td>
          <td><?= h($r['speed_kmh'] !== null ? number_format((float)$r['speed_kmh'], 2) : '') ?></td>
          <td><?= h($r['direction_deg'] !== null ? number_format((float)$r['direction_deg'], 1) : '') ?></td>

          <td class="small">
            <div>sent: <?= h($r['sent_at'] !== null ? (string)$r['sent_at'] : '') ?></div>
            <div>reason: <?= h($r['reason'] !== null ? (string)$r['reason'] : '') ?></div>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (count($rows) === 0): ?>
        <tr><td colspan="12">No rows yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
