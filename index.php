<?php
// /public_html/pnl2/index.php
require_once __DIR__ . '/inc/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// show flash messages if set
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
// clear after read
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// logo resolution
$logo = asset('/assets/img/logo.png');
try {
    $dbFile = __DIR__ . '/database.php';
    if (file_exists($dbFile)) {
        require_once $dbFile;
        if (function_exists('db_connect')) {
            $db = db_connect();
            $s = $db->query("SELECT logo_path FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
            if ($s && !empty($s['logo_path'])) $logo = asset($s['logo_path']);
        }
    }
} catch (Throwable $e) {
    // ignore
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require_once __DIR__ . '/inc/head.php'; ?>
  <title>Görsel Yükle</title>

  <style>
    * { box-sizing: border-box; }
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background-color: #f5f5f5;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }
    .container {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
      text-align: center;
      width: 90%;
      max-width: 400px;
    }
    .logo {
      width: 100%;
      max-width: 300px;
      height: auto;
      margin-bottom: 20px;
    }
    input[type="text"],
    input[type="file"] {
      margin: 10px 0;
      padding: 10px;
      width: 100%;
      border: 1px solid #ccc;
      border-radius: 5px;
      text-transform: uppercase; /* otomatik büyük harf gösterim */
    }
    input[type="submit"] {
      padding: 10px 20px;
      background-color: #007BFF;
      color: white;
      border: none;
      cursor: pointer;
      border-radius: 5px;
      margin-top: 10px;
    }
    .message { margin-top: 15px; color: green; }
    .error { margin-top: 10px; color: red; }
    .preview { margin-top: 15px; display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
    .preview img { width: 80px; height: auto; border: 1px solid #ccc; border-radius: 5px; }
  </style>
</head>
<body>
  <div class="container">
    <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="logo">
    <?php if ($flashMessage): ?><div class="message"><?php echo htmlspecialchars($flashMessage); ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="error"><?php echo htmlspecialchars($flashError); ?></div><?php endif; ?>

    <form id="uploadForm" action="<?php echo asset('/upload.php'); ?>" method="POST" enctype="multipart/form-data">
      <input type="text" name="plaka" id="plaka" placeholder="Plaka (örn: 34ABC123)" required pattern="[A-Z0-9]+" title="Sadece büyük harf ve rakam kullanın" />
      <input type="text" name="isemri" id="isemri" placeholder="İş Emri No (8 haneli)" required maxlength="8" />
      <input type="file" id="files" name="files[]" multiple accept="image/*">
      <div class="preview" id="preview"></div>
      <input type="submit" value="Görsel Yükle">
    </form>
  </div>

<script>
// 🔹 Plaka: otomatik büyük harf dönüşümü ve geçersiz karakter engelleme
document.getElementById('plaka').addEventListener('input', function(e) {
  this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
});

document.getElementById('files').addEventListener('change', function(e){
  const preview = document.getElementById('preview');
  preview.innerHTML = '';
  Array.from(e.target.files).forEach(file => {
    if (!file.type.startsWith('image/')) return;
    const img = document.createElement('img');
    img.alt = file.name;
    preview.appendChild(img);
    const reader = new FileReader();
    reader.onload = function(ev){ img.src = ev.target.result; };
    reader.readAsDataURL(file);
  });
});
</script>
</body>
</html>
