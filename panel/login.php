<?php
// public_html/pnl2/panel/login.php
// Yeni düzen: index.php ile aynı orta kutu (centered) tasarımı kullanır.
// Kullanım: FTP ile public_html/pnl2/panel/ içine koyun (mevcut dosyayı yedekleyin).

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

// Eğer zaten giriş yapılmışsa dashboard'a yönlendir
if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
// timeout mesajı varsa göster
if (isset($_GET['msg']) && $_GET['msg'] === 'timeout') {
    $error = 'Oturum zaman aşımına uğradı. Lütfen tekrar giriş yapın.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['_csrf'] ?? '';

    if (!verify_csrf($token)) {
        $error = 'Form doğrulama hatası (CSRF).';
    } elseif ($username === '' || $password === '') {
        $error = 'Kullanıcı adı ve şifre giriniz.';
    } else {
        if (login_user($username, $password)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Kullanıcı adı veya şifre hatalı.';
        }
    }
}

$csrf = csrf_token();
$logoUrl = asset('/assets/img/logo.png');
?>
<!doctype html>
<html lang="tr">
<head>
  <?php require_once __DIR__ . '/../inc/head.php'; ?>
  <title>Giriş - Panel</title>

  <!-- Kullanıcının istediği minimal, ortalanmış form CSS'i (index.php ile eşleşir) -->
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
    input[type="password"],
    input[type="file"] {
      margin: 10px 0;
      padding: 10px;
      width: 100%;
      border: 1px solid #ccc;
      border-radius: 5px;
    }
    input[type="submit"], .btn-primary {
      padding: 10px 20px;
      background-color: #007BFF;
      color: white;
      border: none;
      cursor: pointer;
      border-radius: 5px;
      margin-top: 10px;
      display: inline-block;
    }
    .message { margin-top: 15px; color: green; }
    .error { margin-top: 10px; color: red; }
    .preview { margin-top: 15px; display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
    .preview img { width: 80px; height: auto; border: 1px solid #ccc; border-radius: 5px; }
    .helper { font-size: 13px; color: #666; margin-top: 10px; }
  </style>
</head>
<body>
  <div class="container">
    <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="logo">

    <?php if ($error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="text" name="username" placeholder="Kullanıcı adı" required autocomplete="username">
      <input type="password" name="password" placeholder="Şifre" required autocomplete="current-password">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <div style="margin-top:8px">
        <button type="submit" class="btn-primary">Giriş Yap</button>
      </div>
    </form>

    <div class="helper">
      <p>Panel hesabınız yoksa sistem yöneticinizle iletişime geçin.</p>
    </div>
  </div>
</body>
</html>