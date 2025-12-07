<?php
// branch.php  (based on original public_html/pnl2/panel/branches.php)
// - Şube içindeki iş emirlerini sayfalandırır (50/adet).
// - Her iş emrinin başlığında son yükleme zamanı gösterilir.
// - [+] toggle ile load_images.php?job=...&b=... üzerinden görseller çekilir.
// - Sıralama: isemri kodlamasına göre ay/gün/dosya DESC olacak şekilde (1. ay en altta, son ay/gün üstte).

require_once __DIR__ . '/../inc/auth.php';
require_login();
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../database.php';

$db   = db_connect();
$user = current_user();

// Şube listesi
$branches = ['1'=>'Bursa','2'=>'İzmit','3'=>'Orhanlı','4'=>'Hadımköy','5'=>'Keşan'];

// seçili şube param
$b = isset($_GET['b']) ? $_GET['b'] : null;
if ($b && !isset($branches[$b])) $b = null;

// admin değilse kullanıcı sadece kendi şubesini görüntüleyebilir
if (!is_admin()) {
    $b = $user['branch_code'];
}

// Sayfalama
$perPage = 50;
$page    = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$offset  = ($page - 1) * $perPage;

// Liste veri yapısı
$jobs = [];
$totalJobs = 0;

if ($b) {
    // Toplam iş emri sayısı (distinct isemri)
    $stmtC = $db->prepare("SELECT COUNT(DISTINCT isemri) FROM uploads WHERE branch_code = :b");
    $stmtC->execute([':b' => $b]);
    $totalJobs = (int)$stmtC->fetchColumn();

    // Sayfalı liste:
    // plaka genelde iş emrine özgüdür; güvenli toplulaştırma için MIN(plaka) kullanıyoruz
    // isemri formatı beklenen şekilde (ör: 41129005) ise aşağıdaki SUBSTRING ifadeleri doğru çalışır.
    $sql = "
        SELECT 
            isemri,
            MIN(plaka)        AS plaka,
            MAX(created_at)   AS last_created
        FROM uploads
        WHERE branch_code = :b
        GROUP BY isemri
        ORDER BY
            CAST(SUBSTRING(isemri, 2, 2) AS UNSIGNED) DESC,  -- ay (MM) DESC: son ay üstte
            CAST(SUBSTRING(isemri, 4, 2) AS UNSIGNED) DESC,  -- gün (DD) DESC
            CAST(SUBSTRING(isemri, 6)      AS UNSIGNED) DESC -- dosya no (NNN) DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':b', $b);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: DB tarafında beklenmedik format olursa PHP ile aynı mantığı uygulayarak sıralama garanti edilir.
    if (!empty($jobs)) {
        usort($jobs, function($a, $bRow) {
            $codeA = isset($a['isemri']) ? trim($a['isemri']) : '';
            $codeB = isset($bRow['isemri']) ? trim($bRow['isemri']) : '';

            // Normalize: en az 8 hane bekliyoruz; kısa ise sol tarafı 0 ile doldur
            $codeA = str_pad($codeA, 8, "0", STR_PAD_LEFT);
            $codeB = str_pad($codeB, 8, "0", STR_PAD_LEFT);

            // PHP substr 0-based: branch(0,1), month(1,2), day(3,2), file(5)
            $parse = function($c) {
                return [
                    'branch' => (int) substr($c, 0, 1),
                    'month'  => (int) substr($c, 1, 2),
                    'day'    => (int) substr($c, 3, 2),
                    'file'   => (int) substr($c, 5)    // geri kalan tüm kısmı dosya numarası kabul ediyoruz
                ];
            };

            $pA = $parse($codeA);
            $pB = $parse($codeB);

            // Eğer farklı şubeler sıralanıyorsa şube artan sırada bırak (veya ihtiyaç varsa değiştir)
            if ($pA['branch'] !== $pB['branch']) {
                return $pA['branch'] < $pB['branch'] ? -1 : 1;
            }
            // İstenen ters mantık: month DESC, day DESC, file DESC
            if ($pA['month'] !== $pB['month']) return $pB['month'] - $pA['month'];
            if ($pA['day']   !== $pB['day'])   return $pB['day']   - $pA['day'];
            if ($pA['file']  !== $pB['file'])  return $pB['file']  - $pA['file'];
            return 0;
        });
    }
}

$totalPages = $b ? max((int)ceil($totalJobs / $perPage), 1) : 1;

// Mevcut query string’i (page hariç) sayfalandırmada koru
$baseQuery = $_GET;
unset($baseQuery['page']);
$qs = http_build_query($baseQuery);
$qsPrefix = $qs ? ('?' . $qs . '&') : '?';
?>
<!doctype html>
<html lang="tr">
<head>
  <?php require_once __DIR__ . '/../inc/head.php'; ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Şubeler</title>
  <style>
    /* küçük ekstra stiller toggle ve loader için (conflict azaltmak için target'lı) */
    .job-toggle {
      display:inline-block;
      width:44px;
      text-align:center;
      border-radius:6px;
      border:1px solid #e6e9ee;
      background:#fff;
      padding:6px 8px;
      cursor:pointer;
      font-weight:700;
      margin-right:8px;
    }
    .job-row { margin-bottom:12px; }
    .images-container { display:none; margin-top:10px; }
    .images-loading { color:#666; font-size:13px; padding:6px 0; }
    .thumb-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start; }
    .thumb-item { width:180px; border-radius:8px; overflow:hidden; background:#fff; border:1px solid #eee; padding:8px; text-align:center; }
    .thumb-item img { width:100%; height:120px; object-fit:cover; border-radius:6px; display:block; }
    .thumb-actions { margin-top:8px; font-size:13px; }
    .thumb-actions a { color:#0b63d6; text-decoration:none; margin:0 6px; }
    .branch-tabs { display:flex; gap:8px; flex-wrap:wrap; margin:10px 0 18px; }
    .branch-tab { padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; text-decoration:none; color:#111827; }
    .branch-tab.active { background:#2563eb; color:#fff; border-color:#2563eb; }
    .pagination { display:flex; gap:6px; justify-content:center; margin-top:18px; flex-wrap:wrap; }
    .pagination a, .pagination span {
      padding:6px 10px; border:1px solid #ddd; border-radius:6px;
      text-decoration:none; font-size:13px; color:#333;
    }
    .pagination a:hover { background:#f5f5f5; }
    .pagination .active { background:#2563eb; color:#fff; border-color:#2563eb; }
    .empty-note { color:#666; }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/header.php'; ?>
  <main style="padding:18px">
    <h1 style="text-align:center">Şubeler</h1>

    <div class="branch-tabs" role="tablist" aria-label="Şubeler">
      <?php foreach($branches as $code => $name):
          if (!is_admin() && $code !== $user['branch_code']) continue;
          $active = ($b == $code) ? 'active' : '';
      ?>
        <a class="branch-tab <?php echo $active; ?>" href="?b=<?php echo $code; ?>"><?php echo htmlspecialchars($name); ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$b): ?>
      <div style="text-align:center;margin-top:24px">
        <p class="empty-note">Lütfen bir şube seçin.</p>
      </div>
    <?php else: ?>
      <?php if (empty($jobs)): ?>
        <div style="text-align:center;margin-top:24px"><p class="empty-note">Bu şubeye ait kayıt bulunamadı.</p></div>
      <?php else: ?>
        <?php foreach ($jobs as $row):
            $job   = $row['isemri'];
            $plaka = $row['plaka'];
            $last  = $row['last_created'];
            // sanitize an id-friendly token for DOM ids
            $safeId = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$job);
        ?>
          <div class="job-row" aria-labelledby="job-<?php echo htmlspecialchars($safeId); ?>">
            <div style="display:flex;align-items:center;">
              <button id="toggle-<?php echo htmlspecialchars($safeId); ?>" class="job-toggle"
                      aria-expanded="false"
                      aria-controls="images-<?php echo htmlspecialchars($safeId); ?>"
                      onclick="toggleImages('<?php echo htmlspecialchars($safeId); ?>','<?php echo htmlspecialchars($job); ?>')">[+]</button>
              <div>
                <div id="job-<?php echo htmlspecialchars($safeId); ?>" style="font-weight:700">
                  İş Emri: <?php echo htmlspecialchars($job); ?> - Plaka: <?php echo htmlspecialchars($plaka); ?>
                </div>
                <div style="color:#666;font-size:13px">
                  Son yükleme: <?php echo htmlspecialchars($last); ?>
                </div>
              </div>
            </div>

            <div id="images-<?php echo htmlspecialchars($safeId); ?>" class="images-container" role="region" aria-live="polite" aria-hidden="true">
              <!-- Görseller burada dinamik olarak yüklenecek -->
            </div>
          </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php
              $maxShow = 7;
              $start = max(1, $page - 3);
              $end   = min($totalPages, $start + $maxShow - 1);
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
          <div style="text-align:center;margin-top:6px;font-size:12px;color:#6b7280">
            Toplam iş emri: <?php echo number_format($totalJobs, 0, ',', '.'); ?> • Sayfa başına <?php echo $perPage; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    <?php endif; ?>
  </main>

<script>
function getQueryParam(name) {
  const url = new URL(window.location.href);
  return url.searchParams.get(name);
}

async function toggleImages(safeId, job) {
  const imagesDiv = document.getElementById("images-" + safeId);
  const toggleBtn = document.getElementById("toggle-" + safeId);
  if (!imagesDiv || !toggleBtn) return;

  if (imagesDiv.style.display === "" || imagesDiv.style.display === "none") {
    // show loading placeholder
    imagesDiv.innerHTML = '<div class="images-loading">Yükleniyor...</div>';
    imagesDiv.style.display = "block";
    imagesDiv.setAttribute('aria-hidden', 'false');
    toggleBtn.textContent = "[-]";
    toggleBtn.setAttribute('aria-expanded', 'true');

    // build URL: include branch param if present in page URL
    const b = getQueryParam('b');
    let url = '<?php echo asset('/load_images.php'); ?>?job=' + encodeURIComponent(job);
    if (b) url += '&b=' + encodeURIComponent(b);

    try {
      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) {
        imagesDiv.innerHTML = '<div class="images-loading">Görseller yüklenemedi (sunucu hatası).</div>';
        return;
      }
      const html = await res.text();
      imagesDiv.innerHTML = html;
    } catch (err) {
      imagesDiv.innerHTML = '<div class="images-loading">Görseller yüklenemedi (ağ hatası).</div>';
      console.error(err);
    }
  } else {
    // hide
    imagesDiv.style.display = "none";
    imagesDiv.setAttribute('aria-hidden', 'true');
    toggleBtn.textContent = "[+]";
    toggleBtn.setAttribute('aria-expanded', 'false');
    // imagesDiv.innerHTML = ''; // istersen bellek için temizleyebilirsin
  }
}
</script>
</body>
</html>