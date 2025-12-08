<?php
// public_html/pnl2/panel/dashboard.php
// NOTE: Added output buffering at the very top to prevent "headers already sent" warnings
// when included modules (like includes/chat-init.php) call header(). This is a minimal,
// safe fix you can upload via FTP. If you prefer a different approach (move include
// earlier or modify chat-init.php), tell me and I can produce that variant.

if (session_status() === PHP_SESSION_NONE) {
    // start buffering before any output
    @ob_start();
}

require_once __DIR__ . '/../inc/auth.php';
require_login();
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../database.php';

$db   = db_connect();
$user = current_user();
$isAdmin = is_admin();

$branches = [
  '1'=>'Bursa',
  '2'=>'İzmit',
  '3'=>'Orhanlı',
  '4'=>'Hadımköy',
  '5'=>'Keşan'
];

// counts and phones
$counts = [];
$branchPhones = [];
foreach ($branches as $code => $name) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM uploads WHERE branch_code = :b");
    $stmt->execute([':b' => $code]);
    $counts[$code] = (int)$stmt->fetchColumn();

    $pstmt = $db->prepare("SELECT phone FROM users WHERE branch_code = :b AND phone IS NOT NULL AND phone <> '' LIMIT 1");
    $pstmt->execute([':b' => $code]);
    $phoneRow = $pstmt->fetch(PDO::FETCH_ASSOC);
    $branchPhones[$code] = $phoneRow['phone'] ?? null;
}

// Sistem kartları: herkese açık (admin + alt hesaplar)
$totalUploads = (int)$db->query("SELECT COUNT(*) FROM uploads")->fetchColumn();
$totalUsers   = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$lastRow      = $db->query("SELECT created_at FROM uploads ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$lastUpload   = $lastRow['created_at'] ?? null;
?>
<!doctype html>
<html lang="tr">
<head>
  <?php include __DIR__ . '/includes/chat-init.php'; ?>
  <?php require_once __DIR__ . '/../inc/head.php'; ?>
  <title>Panel - Dashboard</title>
  <style>
    /* Mevcut grid/card stillerine dokunmamak için sadece hedefli bir gizleme kuralı ekliyoruz */
    .branch-card.limited .for-admin { display: none !important; }

    /* Sistem canlı izleme bölümü için (senin mevcut stilinle uyumlu) */
    .sys-grid {
      display: grid;
      grid-template-columns: 1fr 360px;
      gap: 16px;
      align-items: start;
      max-width: 1100px;
      margin: 18px auto;
    }
    @media (max-width: 980px) { .sys-grid { grid-template-columns: 1fr; } }

    #sysbox {
      background: #000; color: #0f0; padding: 12px; border-radius: 6px;
      height: 260px; overflow:auto; font-family: monospace; font-size: 13px; line-height: 1.3;
      box-shadow: inset 0 0 18px rgba(0,255,0,0.02); white-space: pre-wrap; word-break: break-word;
    }
    .sys-cards { display: flex; flex-direction: column; gap: 12px; }
    .sys-card { background: #fff; border: 1px solid #e6e9ee; padding: 12px; border-radius: 8px;
      box-shadow: 0 6px 18px rgba(16,24,40,0.03); text-align: left; }
    .sys-card h4 { margin:0 0 6px 0; font-size:14px; }
    .sys-card .val { font-size:20px; font-weight:700; color:#111827; }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/header.php'; ?>
  <main style="padding:18px; max-width: 1100px; margin: 0 auto;">

    <!-- Şubeler grid'i -->
    <section class="panel-grid" aria-label="Şubeler">
      <?php foreach($branches as $code=>$name):

          // SADECE KENDİ ŞUBESİNİ GÖRSÜN İSTERSEN aşağıdaki satırı AÇ (yorumdan çıkar):
          // if (!$isAdmin && $code !== $user['branch_code']) continue;

          // WhatsApp linki (varsa)
          $waUrl = null;
          if (!empty($branchPhones[$code])) {
              $raw = preg_replace('/\D/', '', $branchPhones[$code]);
              if (strpos($raw, '0') === 0) $raw = ltrim($raw, '0');
              $waUrl = 'https://wa.me/90' . $raw;
          }

          // Alt hesaplarda kartı "limited" sınıfıyla işaretle
          $cardClass = 'branch-card' . ($isAdmin ? '' : ' limited');
      ?>
        <div class="<?php echo $cardClass; ?>" style="border-color:#<?php echo substr(md5($code.$name),0,6); ?>;">
          <!-- 1) Şube adı -->
          <h3><?php echo htmlspecialchars($name); ?></h3>
          <!-- 2) Şube kodu -->
          <p>Şube kodu: <?php echo $code; ?></p>
          <!-- 3) Garanti Yetkilisi ile Görüş (varsa) -->
          <?php if ($waUrl): ?>
            <p><a href="<?php echo htmlspecialchars($waUrl); ?>" target="_blank" rel="noopener">Garanti Yetkilisi ile Görüş</a></p>
          <?php endif; ?>

          <!-- Yalnızca adminlere görünen alan -->
          <div class="for-admin">
            <p>Kayıt sayısı: <strong><?php echo (int)$counts[$code]; ?></strong></p>
            <p><a href="branches.php?b=<?php echo $code; ?>">Şube kayıtlarını göster</a></p>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- Sistem Canlı İzleme (herkese açık) -->
    <section style="margin-top:20px">
      <h2>Sistem Canlı İzleme</h2>
      <div class="sys-grid" role="region" aria-label="Sistem Canlı İzleme">
        <div>
          <div id="sysbox" aria-live="polite" role="log"></div>
        </div>
        <aside class="sys-cards" aria-hidden="false">
          <div class="sys-card">
            <h4>Toplam Kayıt</h4>
            <div class="val"><?php echo (int)$totalUploads; ?></div>
          </div>
          <div class="sys-card">
            <h4>Toplam Kullanıcı</h4>
            <div class="val"><?php echo (int)$totalUsers-1; ?></div>
          </div>
          <div class="sys-card">
            <h4>Son Yükleme</h4>
            <div class="val"><?php echo htmlspecialchars($lastUpload ?? '-'); ?></div>
          </div>
        </aside>
      </div>
    </section>

  </main>

<script>
const BUF_MAX = 80;
let sysBuffer = [];

// Tek satır formatlayıcı
function formatStatLine(statsJson) {
  const now = new Date().toLocaleTimeString();
  const load1 = statsJson.load && statsJson.load['1'] !== undefined ? Number(statsJson.load['1']).toFixed(2) : 'n/a';
  const memPct = statsJson.mem && statsJson.mem.percent !== undefined ? Number(statsJson.mem.percent).toFixed(2) : 'n/a';
  const diskRoot = statsJson.disk && statsJson.disk.root && statsJson.disk.root.percent !== null ? Number(statsJson.disk.root.percent).toFixed(2) : 'n/a';
  return `[${now}] CPU_LOAD(1m): ${load1} | MEM: ${memPct}% | DISK: ${diskRoot}%`;
}

async function updateSysBox(){
  try {
    const res = await fetch('<?php echo asset('/system_stats.php'); ?>', {cache:'no-store'});
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const j = await res.json();
    const line = formatStatLine(j);

    sysBuffer.unshift(line);
    if (sysBuffer.length > BUF_MAX) sysBuffer.length = BUF_MAX;

    const box = document.getElementById('sysbox');
    box.textContent = sysBuffer.join('\n');
    box.scrollTop = 0;
  } catch (err) {
    console.error(err);
    const box = document.getElementById('sysbox');
    const errLine = `[${new Date().toLocaleTimeString()}] [error] ${err.message || 'fetch failed'}`;
    sysBuffer.unshift(errLine);
    if (sysBuffer.length > BUF_MAX) sysBuffer.length = BUF_MAX;
    box.textContent = sysBuffer.join('\n');
    box.scrollTop = 0;
  }
}

// initial + periyodik
updateSysBox();
setInterval(updateSysBox, 5000);
</script>
</body>
</html>