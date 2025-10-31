<?php
// public_html/pnl2/panel/settings.php
// Updated settings page styled to match users.php (uses same panel-users.css).
// Place this file at: public_html/pnl2/panel/settings.php
// Requirements: inc/auth.php, inc/helpers.php (asset()), database.php, inc/head.php, panel/header.php

require_once __DIR__ . '/../inc/auth.php';
require_admin();
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$db = db_connect();
$msg = '';
$err = '';

// Ensure settings row exists
$exists = $db->prepare("SELECT id FROM settings WHERE id = 1");
$exists->execute();
if (!$exists->fetch()) {
    $db->prepare("INSERT INTO settings (id, site_title, header_text, logo_path, favicon_path, created_at) VALUES (1, '', '', '/assets/img/logo.png', '/assets/img/favicon.png', NOW())")->execute();
}

// Handle POST (save settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_title = trim($_POST['site_title'] ?? '');
    $header_text = trim($_POST['header_text'] ?? '');
    $logo_url_field = trim($_POST['logo_url'] ?? '');
    $favicon_url_field = trim($_POST['favicon_url'] ?? '');

    $logoPathToSave = null;
    $faviconPathToSave = null;

    // process logo upload
    if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $L = __DIR__ . '/../assets/img/';
        if (!is_dir($L)) mkdir($L, 0755, true);
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $ext = in_array($ext, ['png','jpg','jpeg','gif','svg']) ? $ext : 'png';
        $fn = 'logo.' . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $L . $fn)) {
            $logoPathToSave = '/assets/img/' . $fn;
        } else {
            $err = 'Logo dosyası yüklenemedi.';
        }
    } elseif ($logo_url_field !== '') {
        $logoPathToSave = $logo_url_field;
    }

    // process favicon upload
    if (!empty($_FILES['favicon']['tmp_name']) && is_uploaded_file($_FILES['favicon']['tmp_name'])) {
        $L = __DIR__ . '/../assets/img/';
        if (!is_dir($L)) mkdir($L, 0755, true);
        $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
        $ext = in_array($ext, ['ico','png','jpg','jpeg','svg']) ? $ext : 'png';
        $fn = 'favicon.' . $ext;
        if (move_uploaded_file($_FILES['favicon']['tmp_name'], $L . $fn)) {
            $faviconPathToSave = '/assets/img/' . $fn;
        } else {
            $err = $err ? $err . ' Favicon yüklenemedi.' : 'Favicon yüklenemedi.';
        }
    } elseif ($favicon_url_field !== '') {
        $faviconPathToSave = $favicon_url_field;
    }

    // Upsert settings row
    try {
        $sql = "UPDATE settings SET site_title = :st, header_text = :ht";
        $params = [':st' => $site_title, ':ht' => $header_text];
        if ($logoPathToSave !== null) {
            $sql .= ", logo_path = :logo";
            $params[':logo'] = $logoPathToSave;
        }
        if ($faviconPathToSave !== null) {
            $sql .= ", favicon_path = :favicon";
            $params[':favicon'] = $faviconPathToSave;
        }
        $sql .= ", updated_at = NOW() WHERE id = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if (function_exists('log_action')) log_action('settings_update', ['site_title'=>$site_title]);
        if (!$err) $msg = 'Ayarlar kaydedildi.';
    } catch (Throwable $e) {
        $err = 'Ayarlar kaydedilirken hata oluştu.';
        error_log('settings.php save error: ' . $e->getMessage());
    }

    // redirect to avoid repost
    header('Location: settings.php');
    exit;
}

// fetch settings
$settings = $db->query("SELECT * FROM settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$siteTitle = $settings['site_title'] ?? '';
$headerText = $settings['header_text'] ?? '';
$logoPath = $settings['logo_path'] ?? '/assets/img/logo.png';
$faviconPath = $settings['favicon_path'] ?? '/assets/img/favicon.png';

$logoUrl = asset($logoPath);
$faviconUrl = asset($faviconPath);
?>
<!doctype html>
<html lang="tr">
<head>
  <?php require_once __DIR__ . '/../inc/head.php'; ?>
  <link rel="stylesheet" href="<?php echo asset('/assets/css/panel-users.css'); ?>">
  <title>Ayarlar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    /* minor tweaks local to settings page if needed */
    .logo-preview { height: 60px; display:block; margin-top:8px; object-fit:contain; }
    .small-label { font-size:12px; color:#6b7280; margin-top:6px; display:block; }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/header.php'; ?>

  <main class="users-page" style="padding:18px;">
    <?php if (!empty($msg)): ?>
      <div class="flash flash-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <?php if (!empty($err)): ?>
      <div class="flash flash-error"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>

    <div class="users-grid">
      <div class="card">
        <h2>Genel Ayarlar</h2>
        <form method="post" enctype="multipart/form-data" novalidate>
          <label>Site Başlığı (title)</label>
          <input type="text" name="site_title" value="<?php echo htmlspecialchars($siteTitle); ?>">

          <label>Header Metni (panel başlığı)</label>
          <input type="text" name="header_text" value="<?php echo htmlspecialchars($headerText); ?>">

          <label class="small-label">Logo dosyası (veya tam URL)</label>
          <input type="file" name="logo" accept="image/*">
          <input type="text" name="logo_url" placeholder="https://..." value="<?php echo htmlspecialchars($settings['logo_path'] ?? ''); ?>" style="margin-top:8px">

          <?php if ($logoUrl): ?>
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" class="logo-preview">
          <?php endif; ?>

          <label class="small-label" style="margin-top:12px">Favicon dosyası (16x16/32x32 önerilir) veya tam URL</label>
          <input type="file" name="favicon" accept="image/*">
          <input type="text" name="favicon_url" placeholder="https://..." value="<?php echo htmlspecialchars($settings['favicon_path'] ?? ''); ?>" style="margin-top:8px">

          <?php if ($faviconUrl): ?>
            <div style="margin-top:8px">
              <img src="<?php echo htmlspecialchars($faviconUrl); ?>" alt="Favicon" style="height:32px;width:32px;object-fit:contain;">
            </div>
          <?php endif; ?>

          <div class="form-actions">
            <button class="btn-primary" type="submit">Kaydet</button>
            <a class="btn-secondary" href="dashboard.php">İptal</a>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>Geçerli Ayarlar</h2>
        <table class="users-table" style="width:100%">
          <tbody>
            <tr><td style="width:140px;font-weight:700">Site Başlığı</td><td><?php echo htmlspecialchars($siteTitle ?: '-'); ?></td></tr>
            <tr><td style="font-weight:700">Header Metni</td><td><?php echo htmlspecialchars($headerText ?: '-'); ?></td></tr>
            <tr><td style="font-weight:700">Logo</td><td><?php echo htmlspecialchars($logoPath ?: '-'); ?></td></tr>
            <tr><td style="font-weight:700">Favicon</td><td><?php echo htmlspecialchars($faviconPath ?: '-'); ?></td></tr>
          </tbody>
        </table>

        <div class="small-note">Logo ve favicon yükledikten sonra tarayıcı cache'ini temizleyin veya Cloudflare kullanıyorsanız cache'i temizleyin. Ayarlar sadece admin tarafından düzenlenebilir.</div>
      </div>
    </div>
  </main>
</body>
</html>