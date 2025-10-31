<?php
// panel/header.php
require_once __DIR__ . '/../inc/auth.php';
$user = current_user();
?>
<header style="text-align:center;padding:14px;border-bottom:1px solid #eee;background:#fff">
  <nav style="display:flex;justify-content:center;gap:12px;align-items:center;flex-wrap:wrap">
    <a href="dashboard.php">Dashboard</a>
    <a href="branches.php">Şubeler</a>
    <?php if (is_admin()): ?>
      <a href="users.php">Kullanıcılar</a>
      <a href="settings.php">Ayarlar</a>
      <a href="logs.php" style="font-weight:700;color:#111;">Log Kayıtları</a>
    <?php endif; ?>
    <a href="logout.php" style="margin-left:14px;color:#b00">Çıkış</a>
  </nav>
  <div style="margin-top:8px;color:#666;font-size:13px">
    <?php if ($user): ?>
      <?php echo htmlspecialchars($user['fullname'] ?? $user['username']); ?> - <?php echo htmlspecialchars($user['branch_code'] ? ('Şube: ' . $user['branch_code']) : 'Genel'); ?>
    <?php endif; ?>
  </div>
</header>