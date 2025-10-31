<?php
require_once __DIR__ . '/../inc/auth.php';
require_login();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../inc/logger.php';

$db = db_connect();
$user = current_user();

// Yeni ticket ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['title']) && !empty($_POST['message'])) {
    $stmt = $db->prepare("INSERT INTO tickets (user_id, title, message, status, created_at) VALUES (:uid,:t,:m,'open',NOW())");
    $stmt->execute([':uid'=>$user['id'] ?? null, ':t'=>$_POST['title'], ':m'=>$_POST['message']]);
    if (function_exists('log_action')) log_action('ticket_create', ['title'=>$_POST['title']]);
    $msg = 'Ticket oluşturuldu.';
}

// Liste (admin hepsi, normal kullanıcı sadece kendi ticket'ları)
if (is_admin()) {
    $rows = $db->query("SELECT * FROM tickets ORDER BY created_at DESC")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT * FROM tickets WHERE user_id = :uid ORDER BY created_at DESC");
    $stmt->execute([':uid'=>$user['id'] ?? 0]);
    $rows = $stmt->fetchAll();
}
?>
<!doctype html><html lang="tr"><head>
<?php require_once __DIR__ . '/../inc/head.php'; ?>
<title>Destek / Ticket</title>
</head><body>
<?php require_once __DIR__ . '/header.php'; ?>
<main style="padding:18px">
  <h1>Destek / Ticket</h1>
  <?php if(!empty($msg)) echo '<div class="message">'.htmlspecialchars($msg).'</div>'; ?>
  <h2>Yeni Ticket</h2>
  <form method="post">
    <label>Başlık</label><input name="title" required>
    <label>Mesaj</label><textarea name="message" required></textarea>
    <button class="btn-primary" type="submit">Gönder</button>
  </form>

  <h2 style="margin-top:20px">Ticket Listesi</h2>
  <table style="width:100%;border-collapse:collapse"><thead><tr><th>ID</th><th>Başlık</th><th>Durum</th><th>Kullanıcı</th><th>Tarih</th></tr></thead><tbody>
  <?php foreach($rows as $t): ?>
    <tr>
      <td><?php echo $t['id']; ?></td>
      <td><?php echo htmlspecialchars($t['title']); ?></td>
      <td><?php echo htmlspecialchars($t['status']); ?></td>
      <td><?php echo htmlspecialchars($t['user_id'] ?? '-'); ?></td>
      <td><?php echo $t['created_at']; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</main>
</body></html>