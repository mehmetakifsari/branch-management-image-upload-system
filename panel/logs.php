<?php
// public_html/pnl2/panel/logs.php
require_once __DIR__ . '/../inc/auth.php';
require_admin();
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$dbError = false;
$rows = [];
$perPage = 50;
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$offset = ($page - 1) * $perPage;

// --- Tarih filtreleri (YYYY-MM-DD) ---
$rawStart = trim($_GET['start_date'] ?? '');
$rawEnd   = trim($_GET['end_date']   ?? '');

$startDate = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawStart)) ? $rawStart : '';
$endDate   = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawEnd))   ? $rawEnd   : '';

// endDate’i “ertesi gün 00:00” olacak şekilde exclusive bitişe çeviriyoruz
$endExclusive = '';
if ($endDate) {
    try {
        $dt = new DateTime($endDate);
        $dt->modify('+1 day');
        $endExclusive = $dt->format('Y-m-d');
    } catch (Throwable $e) {
        $endExclusive = '';
    }
}

$where = [];
$params = [];
if ($startDate) {
    $where[] = "created_at >= :sd";
    $params[':sd'] = $startDate . " 00:00:00";
}
if ($endExclusive) {
    $where[] = "created_at < :ed";
    $params[':ed'] = $endExclusive . " 00:00:00";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    $db = db_connect();

    // Toplam sayıyı filtreli çek
    $sqlCount = "SELECT COUNT(*) FROM logs $whereSql";
    $stmtC = $db->prepare($sqlCount);
    foreach ($params as $k => $v) $stmtC->bindValue($k, $v);
    $stmtC->execute();
    $total = (int)$stmtC->fetchColumn();

    // Sayfalı veri
    $sqlData = "SELECT * FROM logs $whereSql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sqlData);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPages = max(ceil($total / $perPage), 1);
} catch (Throwable $e) {
    error_log("panel/logs.php DB error: " . $e->getMessage());
    $dbError = true;
    $total = 0;
    $totalPages = 1;
}

$csrf = function_exists('csrf_token') ? csrf_token() : '';

// Mevcut query string’i (page hariç) sayfalandırmada koru
$baseQuery = $_GET;
unset($baseQuery['page']);
$qs = http_build_query($baseQuery);
$qsPrefix = $qs ? ('?' . $qs . '&') : '?';
?>
<!doctype html>
<html lang="tr">
<head>
  <?php include __DIR__ . '/includes/chat-init.php'; ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <?php require_once __DIR__ . '/../inc/head.php'; ?>
  <title>Log Kayıtları</title>
  <link rel="stylesheet" href="<?php echo asset('/assets/css/panel-users.css'); ?>">
  <style>
    .log-details { max-width:640px; white-space:pre-wrap; word-break:break-word; }
    .log-meta small { display:block; margin-top:4px; color:#6b7280; }
    .filters .row { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
    .filters label { display:block; font-size:12px; color:#555; margin-bottom:4px; }
    .filters input[type="date"] { padding:8px; border:1px solid #ddd; border-radius:8px; font-size:14px; }
    .pagination { display:flex; gap:6px; justify-content:center; margin-top:18px; flex-wrap:wrap; }
    .pagination a, .pagination span {
      padding:6px 10px; border:1px solid #ddd; border-radius:6px;
      text-decoration:none; font-size:13px; color:#333;
    }
    .pagination a:hover { background:#f5f5f5; }
    .pagination .active { background:#2563eb; color:#fff; border-color:#2563eb; }
    .small-note { font-size:12px; color:#6b7280; margin-top:6px; }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/header.php'; ?>

  <main class="users-page" style="padding:18px">
    <h1>Log Kayıtları</h1>

    <?php if ($dbError): ?>
      <div class="flash flash-error">Veritabanından loglar alınamadı. Lütfen hata kayıtlarını kontrol edin.</div>
    <?php endif; ?>

    <div class="card filters">
      <h2>Tarih Filtresi</h2>
      <form method="get" class="form" style="margin-top:8px">
        <?php if ($csrf): ?><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><?php endif; ?>
        <div class="row">
          <div>
            <label for="start_date">Başlangıç</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
          </div>
          <div>
            <label for="end_date">Bitiş</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
          </div>
          <div class="form-actions" style="margin-bottom:4px;">
            <button class="btn-primary" type="submit">Uygula</button>
            <a class="btn-secondary" href="logs.php">Sıfırla</a>
          </div>
        </div>
        <div class="small-note">Not: Bitiş tarihi dahildir (gün sonuna kadar).</div>
      </form>
    </div>

    <div class="card" style="margin-top:14px">
      <h2>
        <?php
          $hdr = "Kayıtlar (Sayfa {$page}/{$totalPages})";
          if ($startDate || $endDate) {
              $range = [];
              if ($startDate) $range[] = "başlangıç: " . htmlspecialchars($startDate);
              if ($endDate)   $range[] = "bitiş: " . htmlspecialchars($endDate);
              $hdr .= " – " . implode(', ', $range);
          }
          echo $hdr;
        ?>
      </h2>
      <div class="table-scroll">
        <table class="users-table">
          <thead>
            <tr>
              <th style="width:72px">ID</th>
              <th>Kullanıcı</th>
              <th>İşlem</th>
              <th>Detay</th>
              <th>IP / Cihaz</th>
              <th>Zaman</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="6" class="center" style="padding:16px">Kayıt bulunamadı.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo (int)$r['id']; ?></td>
                  <td><?php echo htmlspecialchars($r['username'] ?? ('UID:' . ($r['user_id'] ?? '-'))); ?></td>
                  <td><?php echo htmlspecialchars($r['action'] ?? '-'); ?></td>
                  <td class="log-details">
                    <?php
                      $details = (string)($r['details'] ?? '');
                      if (mb_strlen($details) > 800) {
                          $details = mb_substr($details, 0, 800) . '…';
                      }
                      echo htmlspecialchars($details);
                    ?>
                  </td>
                  <td class="log-meta">
                    <?php echo htmlspecialchars(($r['ip'] ?? '-') . ' / ' . ($r['device'] ?? '-')); ?>
                    <small><?php echo htmlspecialchars(mb_substr((string)($r['user_agent'] ?? ''), 0, 160)); ?></small>
                  </td>
                  <td><?php echo htmlspecialchars((string)$r['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php
            $maxShow = 7;
            $start = max(1, $page - 3);
            $end = min($totalPages, $start + $maxShow - 1);
            if ($start > 1) echo '<a href="'.$qsPrefix.'page=1">« İlk</a>';
            for ($i = $start; $i <= $end; $i++) {
                if ($i == $page)
                    echo '<span class="active">'.$i.'</span>';
                else
                    echo '<a href="'.$qsPrefix.'page='.$i.'">'.$i.'</a>';
            }
            if ($end < $totalPages) echo '<a href="'.$qsPrefix.'page='.$totalPages.'">Son »</a>';
          ?>
        </div>
      <?php endif; ?>

      <div class="small-note">
        Toplam: <?php echo number_format($total, 0, ',', '.'); ?> kayıt. Sayfa başına <?php echo $perPage; ?>.
      </div>
    </div>
  </main>
</body>
</html>
