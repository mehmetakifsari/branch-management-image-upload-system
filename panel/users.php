<?php
// public_html/pnl2/panel/users.php
// Styled users management page (add / edit / delete).
// Requires: inc/auth.php, inc/helpers.php, database.php, inc/head.php, panel/header.php
// Place this file at: public_html/pnl2/panel/users.php

require_once __DIR__ . '/../inc/auth.php';
require_admin();
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db = db_connect();
$branches = ['1'=>'Bursa','2'=>'İzmit','3'=>'Orhanlı','4'=>'Hadımköy','5'=>'Keşan'];
$msg = '';
$err = '';

// Handle ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $branch = $_POST['branch_code'] ?? null;

    if ($username === '' || $password === '') {
        $err = 'Kullanıcı adı ve şifre zorunludur.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, fullname, password_hash, branch_code, phone) VALUES (:u,:f,:p,:b,:phone)");
        $stmt->execute([':u'=>$username,':f'=>$fullname,':p'=>$hash,':b'=>$branch,':phone'=>$phone]);
        if (function_exists('log_action')) log_action('user_add', ['username'=>$username,'branch'=>$branch,'phone'=>$phone]);
        $_SESSION['flash_message'] = 'Kullanıcı eklendi.';
        header('Location: users.php');
        exit;
    }
}

// Handle UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $branch = $_POST['branch_code'] ?? null;
    $password = $_POST['password'] ?? '';

    if ($username === '' || $id <= 0) {
        $err = 'Geçersiz veri.';
    } else {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET username=:u, fullname=:f, phone=:phone, branch_code=:b, password_hash=:p WHERE id = :id");
            $stmt->execute([':u'=>$username,':f'=>$fullname,':phone'=>$phone,':b'=>$branch,':p'=>$hash,':id'=>$id]);
        } else {
            $stmt = $db->prepare("UPDATE users SET username=:u, fullname=:f, phone=:phone, branch_code=:b WHERE id = :id");
            $stmt->execute([':u'=>$username,':f'=>$fullname,':phone'=>$phone,':b'=>$branch,':id'=>$id]);
        }
        if (function_exists('log_action')) log_action('user_update', ['user_id'=>$id,'username'=>$username,'branch'=>$branch,'phone'=>$phone]);
        $_SESSION['flash_message'] = 'Kullanıcı güncellendi.';
        header('Location: users.php');
        exit;
    }
}

// Handle DELETE (GET)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id === ($_SESSION['user']['id'] ?? 0)) {
        $_SESSION['flash_error'] = 'Kendinizi silemezsiniz.';
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id'=>$id]);
        if (function_exists('log_action')) log_action('user_delete', ['user_id'=>$id]);
        $_SESSION['flash_message'] = 'Kullanıcı silindi.';
    }
    header('Location: users.php');
    exit;
}

// EDIT FORM data (if any)
$editUser = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT id, username, fullname, phone, branch_code FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$eid]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Fetch users list
$users = $db->query("SELECT id, username, fullname, branch_code, phone FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Flash messages
$flashSuccess = $_SESSION['flash_message'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_message'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="tr">
<head>
  <?php include __DIR__ . '/includes/chat-init.php'; ?>
  <?php require_once __DIR__ . '/../inc/head.php'; ?>
  <link rel="stylesheet" href="<?php echo asset('/assets/css/panel-users.css'); ?>">
  <title>Kullanıcılar</title>
  <style>
    /* Scoped page styles to avoid global conflicts */
    .users-page { max-width:1100px; margin:18px auto; padding:12px; }
    .users-grid { display:grid; grid-template-columns: 380px 1fr; gap:18px; align-items:start; }
    @media (max-width:920px){ .users-grid { grid-template-columns: 1fr; } }

    .card { background:#fff; border:1px solid #eef2f7; border-radius:10px; padding:14px; box-shadow:0 6px 18px rgba(16,24,40,0.03); }
    .card h2 { margin:0 0 8px 0; font-size:18px; }
    .form-row { display:flex; gap:8px; }
    .form-row .col { flex:1; }

    .small-note { font-size:13px; color:#6b7280; margin-top:8px; }

    table.users-table { width:100%; border-collapse:collapse; }
    table.users-table thead th { text-align:left; padding:10px; background:#fafafa; border-bottom:1px solid #eee; font-size:13px; }
    table.users-table tbody td { padding:10px; border-bottom:1px solid #f3f4f6; vertical-align:middle; font-size:14px; color:#374151; }
    .action-links a { color:var(--accent); text-decoration:none; margin-right:8px; }
    .action-links a:hover { text-decoration:underline; }

    .form-actions { margin-top:12px; display:flex; gap:8px; align-items:center; }
    .hint { font-size:13px; color:#7b8794; }

    .flash { padding:10px 12px; border-radius:8px; margin-bottom:12px; }
    .flash-success { background: #ecfdf5; color:#065f46; border:1px solid rgba(16,185,129,0.12); }
    .flash-error { background:#fff1f2; color:#7f1d1d; border:1px solid rgba(220,38,38,0.08); }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/header.php'; ?>

  <main class="users-page">
    <?php if ($flashSuccess): ?>
      <div class="flash flash-success"><?php echo htmlspecialchars($flashSuccess); ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="flash flash-error"><?php echo htmlspecialchars($flashError); ?></div>
    <?php endif; ?>
    <?php if ($msg || $err): ?>
      <div class="flash <?php echo $err ? 'flash-error' : 'flash-success'; ?>"><?php echo htmlspecialchars($err ?: $msg); ?></div>
    <?php endif; ?>

    <div class="users-grid">
      <div class="card">
        <?php if ($editUser): ?>
          <h2>Kullanıcı Düzenle</h2>
          <form method="post" novalidate>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo (int)$editUser['id']; ?>">
            <label>Kullanıcı adı</label>
            <input name="username" required value="<?php echo htmlspecialchars($editUser['username']); ?>">
            <label>Ad Soyad</label>
            <input name="fullname" value="<?php echo htmlspecialchars($editUser['fullname']); ?>">
            <label>Telefon (5xxxxxxxxx)</label>
            <input name="phone" value="<?php echo htmlspecialchars($editUser['phone'] ?? ''); ?>" placeholder="5xxxxxxxxx">
            <label>Şifre (değiştirmek istersen)</label>
            <input type="password" name="password" placeholder="Yeni şifre (boş bırakılınca değişmez)">
            <label>Şube Kodu</label>
            <select name="branch_code">
              <option value="">Genel</option>
              <?php foreach($branches as $k=>$v): ?>
                <option value="<?php echo $k; ?>" <?php echo ($editUser['branch_code'] == $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
              <?php endforeach; ?>
            </select>

            <div class="form-actions">
              <button class="btn-primary" type="submit">Güncelle</button>
              <a class="btn-secondary" href="users.php">İptal</a>
            </div>
          </form>

        <?php else: ?>
          <h2>Yeni Kullanıcı Ekle</h2>
          <form method="post" novalidate>
            <input type="hidden" name="action" value="add">
            <label>Kullanıcı adı</label>
            <input name="username" required placeholder="örn: mehmet">
            <label>Ad Soyad</label>
            <input name="fullname" placeholder="Ad Soyad">
            <label>Şifre</label>
            <input type="password" name="password" required placeholder="Güçlü şifre">
            <label>Telefon (5xxxxxxxxx)</label>
            <input name="phone" placeholder="5xxxxxxxxx">
            <label>Şube Kodu</label>
            <select name="branch_code">
              <option value="">Genel</option>
              <?php foreach($branches as $k=>$v): ?>
                <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
              <?php endforeach; ?>
            </select>

            <div class="form-actions">
              <button class="btn-primary" type="submit">Ekle</button>
              <a class="btn-secondary" href="dashboard.php">Geri</a>
            </div>
            <div class="small-note">Parola zorunludur. Telefonu uluslararası formatta saklamıyoruz; sadece hızlı WhatsApp bağlantısı için kullanılır.</div>
          </form>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Mevcut Kullanıcılar (<?php echo count($users); ?>)</h2>

        <div style="overflow:auto; max-height:640px; margin-top:8px;">
          <table class="users-table" role="table">
            <thead>
              <tr>
                <th style="width:64px">ID</th>
                <th>Kullanıcı</th>
                <th>Ad Soyad</th>
                <th>Şube</th>
                <th>Telefon</th>
                <th style="width:160px">İşlem</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($users as $u): ?>
                <tr>
                  <td><?php echo (int)$u['id']; ?></td>
                  <td><?php echo htmlspecialchars($u['username']); ?></td>
                  <td><?php echo htmlspecialchars($u['fullname']); ?></td>
                  <td><?php echo htmlspecialchars($branches[$u['branch_code']] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                  <td class="action-links">
                    <a href="?edit=<?php echo (int)$u['id']; ?>">Düzenle</a>
                    <?php if ($u['id'] !== ($_SESSION['user']['id'] ?? 0)): ?>
                      | <a href="?delete=<?php echo (int)$u['id']; ?>" onclick="return confirm('Silinsin mi?')">Sil</a>
                    <?php else: ?>
                      <!-- can't delete self -->
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="small-note" style="margin-top:12px">Kullanıcı işlemleri loglanır (LoG Kayıtları). Parolalar güvenli şekilde hash'lenir.</div>
      </div>
    </div>
  </main>
</body>
</html>