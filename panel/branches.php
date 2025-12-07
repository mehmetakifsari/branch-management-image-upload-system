<?php
// branch.php  (based on original public_html/pnl2/panel/branches.php)
// - Şube içindeki iş emirlerini sayfalandırır (50/adet).
// - Her iş emrinin başlığında son yükleme zamanı gösterilir.
// - [+] toggle ile load_images.php?job=...&b=... üzerinden görseller çekilir.
// - Sıralama: isemri kodlamasına göre ay/gün/dosya DESC olacak şekilde (1. ay en altta, son ay/gün üstte).
// - Güncelleme: PDI akışları için (isemri = 'PDI' veya plaka boş olan kayıtlar) panel listesinde
//   "PDI" gösterilecek ve plaka yerine şasi (VIN) bilgisi gösterilmeye çalışılacaktır.
// - Ayrıca load_images'ten gelen "Sil" bağlantılarını yakalayıp AJAX ile silme (sayfa yönlendirmesi olmadan) yapılacaktır.
// - Yeni: PDI kayıtları yükleme tarihine göre (last_created) tüm listenin içinde uygun yere yerleştirilir,
//   fakat normal iş emirleri (non-PDI) birbirleriyle olan isemri tabanlı sıralamasını korur.

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

    // --- Yeni sıra mantığı: PDI'ları last_created'a göre konumlandır, non-PDI'lerin kendi arasındaki isemri sırasını bozmadan ---
    if (!empty($jobs)) {
        // parse fonksiyonu: isemri parçalarına ay/gün/dosya ayır
        $parseIsemri = function(string $code) {
            $c = str_pad($code, 8, "0", STR_PAD_LEFT);
            return [
                'branch' => (int) substr($c, 0, 1),
                'month'  => (int) substr($c, 1, 2),
                'day'    => (int) substr($c, 3, 2),
                'file'   => (int) substr($c, 5)
            ];
        };
        // isemri karşılaştırıcı (mevcut mantık)
        $isemriCompare = function($aCode, $bCode) use ($parseIsemri) {
            $pA = $parseIsemri((string)$aCode);
            $pB = $parseIsemri((string)$bCode);
            if ($pA['branch'] !== $pB['branch']) return $pA['branch'] < $pB['branch'] ? -1 : 1;
            if ($pA['month'] !== $pB['month']) return $pB['month'] - $pA['month'];
            if ($pA['day']   !== $pB['day'])   return $pB['day']   - $pA['day'];
            if ($pA['file']  !== $pB['file'])  return $pB['file']  - $pA['file'];
            return 0;
        };

        usort($jobs, function($A, $B) use ($isemriCompare) {
            $aI = isset($A['isemri']) ? (string)$A['isemri'] : '';
            $bI = isset($B['isemri']) ? (string)$B['isemri'] : '';

            $aIsPdi = ($aI === 'PDI');
            $bIsPdi = ($bI === 'PDI');

            // Eğer her iki kayıt PDI ise: last_created DESC
            if ($aIsPdi && $bIsPdi) {
                $ta = isset($A['last_created']) ? strtotime($A['last_created']) : 0;
                $tb = isset($B['last_created']) ? strtotime($B['last_created']) : 0;
                if ($ta === $tb) return $isemriCompare($aI, $bI);
                return ($ta < $tb) ? 1 : -1;
            }

            // Eğer hiçbiri PDI değilse: orijinal isemri sıralamasını koru
            if (!$aIsPdi && !$bIsPdi) {
                return $isemriCompare($aI, $bI);
            }

            // Bir taraf PDI diğer taraf normal ise: last_created ile karşılaştır (PDI'ların tarihine göre yerleşmesi)
            $ta = isset($A['last_created']) ? strtotime($A['last_created']) : 0;
            $tb = isset($B['last_created']) ? strtotime($B['last_created']) : 0;
            if ($ta === $tb) {
                // eşitse isemri karşılaştır (stabil sıra için)
                return $isemriCompare($aI, $bI);
            }
            return ($ta < $tb) ? 1 : -1;
        });
    }

    // If we have jobs, prepare a statement to fetch a files_json sample for a job (isemri)
    $stmtFiles = $db->prepare("SELECT files_json FROM uploads WHERE branch_code = :b AND isemri = :isemri AND files_json IS NOT NULL ORDER BY created_at DESC LIMIT 1");

    if (!empty($jobs)) {
        // For each job row, determine display values
        foreach ($jobs as $k => $row) {
            $origIsemri = isset($row['isemri']) ? trim($row['isemri']) : '';
            $origPlaka  = isset($row['plaka']) ? trim($row['plaka']) : '';

            // Default display values
            $displayIsemri = $origIsemri !== '' ? $origIsemri : '';
            $displayPlaka  = $origPlaka !== '' ? $origPlaka : '';

            // If this is a PDI job (isemri == 'PDI') or plaka empty, try to extract VIN from files_json
            if ($origIsemri === 'PDI' || $displayPlaka === '') {
                try {
                    $stmtFiles->execute([':b' => $b, ':isemri' => $origIsemri]);
                    $fj = $stmtFiles->fetchColumn();
                    if ($fj) {
                        $filesArr = json_decode($fj, true);
                        if (is_array($filesArr) && !empty($filesArr)) {
                            // Try to extract VIN from stored filename or original_name
                            $first = $filesArr[0];
                            $stored = $first['stored'] ?? '';
                            $origName = $first['original_name'] ?? '';

                            $vin = '';
                            if ($stored !== '') {
                                $nameNoExt = pathinfo($stored, PATHINFO_FILENAME);
                                // pattern YEAR-ISEMRI-NAMEPLATE-<num>
                                if (preg_match('/^\d{4}-[^-]+-(.+)-\d+$/', $nameNoExt, $m)) {
                                    $vin = $m[1];
                                } else {
                                    // fallback: remove prefix YEAR-ISEMRI-
                                    $parts = explode('-', $nameNoExt, 4);
                                    if (count($parts) >= 3) {
                                        $rest = $parts[2];
                                        if (isset($parts[3])) $rest .= '-' . $parts[3];
                                        $vin = preg_replace('/-\d+$/', '', $rest);
                                    } else {
                                        $vin = '';
                                    }
                                }
                                $vin = trim($vin);
                            }
                            if ($vin === '' && $origName !== '') {
                                $vin = trim(pathinfo($origName, PATHINFO_FILENAME));
                            }
                            if ($vin !== '') {
                                $displayPlaka = $vin;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // ignore extraction error, keep defaults
                    error_log("branches.php vin extraction error: " . $e->getMessage());
                }
            }

            // If still empty, show placeholder
            if ($displayPlaka === '') $displayPlaka = '—';

            if ($displayIsemri === '') $displayIsemri = '—';

            $jobs[$k]['display_isemri'] = $displayIsemri;
            $jobs[$k]['display_plaka']  = $displayPlaka;
        }
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
    .thumb-actions { margin-top:8px; font-size:13px; display:flex; gap:8px; justify-content:center; }
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
            $jobDisplay   = $row['display_isemri'] ?? $row['isemri'];
            $plakaDisplay = $row['display_plaka'] ?? $row['plaka'] ?? '—';
            $last  = $row['last_created'];
            // sanitize an id-friendly token for DOM ids (use original isemri for id)
            $safeId = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$row['isemri']);
        ?>
          <div class="job-row" aria-labelledby="job-<?php echo htmlspecialchars($safeId); ?>">
            <div style="display:flex;align-items:center;">
              <button id="toggle-<?php echo htmlspecialchars($safeId); ?>" class="job-toggle"
                      aria-expanded="false"
                      aria-controls="images-<?php echo htmlspecialchars($safeId); ?>"
                      onclick="toggleImages('<?php echo htmlspecialchars($safeId); ?>','<?php echo htmlspecialchars($row['isemri']); ?>')">[+]</button>
              <div>
                <div id="job-<?php echo htmlspecialchars($safeId); ?>" style="font-weight:700">
                  İş Emri: <?php echo htmlspecialchars($jobDisplay); ?> - <?php echo ($jobDisplay === 'PDI' ? 'Şasi' : 'Plaka'); ?>: <?php echo htmlspecialchars($plakaDisplay); ?>
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
        const txt = await res.text().catch(() => 'Sunucu hata mesajı okunamadı.');
        imagesDiv.innerHTML = '<div class="images-loading">Görseller yüklenemedi (sunucu hatası).<br><small>' + escapeHtml(txt) + '</small></div>';
        return;
      }
      const html = await res.text();
      imagesDiv.innerHTML = html;

      // After injecting HTML, attach delete interceptors so "Sil" links will work via AJAX
      attachDeleteInterceptors(imagesDiv);

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

// escape helper for debugging output
function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]); });
}

// Attach delete interceptors to elements in the loaded images HTML
function attachDeleteInterceptors(container) {
  if (!container) return;

  // compute script base (e.g. "/p1/panel") based on current page path
  // We take current path and remove the last segment (filename) to get folder
  const pathParts = window.location.pathname.split('/');
  pathParts.pop(); // remove last segment (e.g. branches.php)
  const scriptBase = pathParts.join('/') || '';

  // selector: anchors that link to delete_file.php or elements with data-delete-file
  const sel = 'a[href*="/delete_file.php"], a[href*="delete_file.php"], button[data-delete-file], [data-delete-file]';
  const els = container.querySelectorAll(sel);

  els.forEach(el => {
    if (el.dataset.deleteBound === '1') return;
    el.dataset.deleteBound = '1';

    el.addEventListener('click', async function(ev) {
      ev.preventDefault();
      ev.stopPropagation(); // ensure no other handlers run and no navigation happens

      // determine filename
      let file = this.dataset.file || this.getAttribute('data-file') || null;
      if (!file) {
        const href = this.getAttribute('href') || '';
        try {
          const u = new URL(href, window.location.origin);
          file = u.searchParams.get('file') || null;
        } catch (err) {
          // ignore
        }
      }
      if (!file) {
        alert('Silinecek dosya bilgisi bulunamadı.');
        return;
      }

      if (!confirm('Bu dosyayı kalıcı olarak silmek istediğinize emin misiniz?')) return;

      // build delete URL relative to the current script base (fixes /p1 prefix issues)
      let deleteUrl = (scriptBase ? scriptBase : '') + '/delete_file.php';

      // Prefer POST with form data; include ajax=1 so server returns JSON
      const form = new FormData();
      form.append('file', file);
      form.append('ajax', '1');

      try {
        const res = await fetch(deleteUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: form
        });

        // read text first, then parse JSON to avoid "body stream already read" issues
        const text = await res.text();
        let json = null;
        try {
          json = text ? JSON.parse(text) : null;
        } catch (e) {
          throw new Error('Sunucu beklenmedik yanıt verdi: ' + text);
        }

        if (!res.ok || !json || !json.success) {
          const msg = (json && json.message) ? json.message : ('Silme başarısız (HTTP ' + res.status + ')');
          alert('Silme işlemi başarısız: ' + msg);
          return;
        }

        // remove DOM .thumb-item ancestor (or nearest .thumb-item)
        const thumbItem = this.closest('.thumb-item');
        if (thumbItem) {
          thumbItem.remove();
        } else {
          // fallback: find anchor to file inside container and remove its closest .thumb-item
          const row = container.querySelector('.thumb-row');
          if (row) {
            const node = row.querySelector(`[href*="${file}"], [data-file="${file}"]`);
            if (node) {
              const item = node.closest('.thumb-item');
              if (item) item.remove();
            }
          }
        }

        // If no thumbs left show 'no images' hint
        const thumbRow = container.querySelector('.thumb-row');
        if (!thumbRow || thumbRow.children.length === 0) {
          container.innerHTML = '<div class="images-loading">Bu iş emrine ait görsel bulunamadı.</div>';
        }

        // Optional: show message
        if (json && json.message) {
          // use a simple alert; you can replace with a toast
          alert(json.message);
        }
      } catch (err) {
        console.error(err);
        alert('Sunucu hatası: ' + (err.message || 'Silme yapılamadı.'));
      }
    });
  });
}
</script>
</body>
</html>