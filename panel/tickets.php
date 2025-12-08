<?php
// panel/tickets.php
// Son hali: düzgün, güvenli dosya yükleme (png/jpg/jpeg/pdf/mp4 vb. destekli),
// per-ticket .htaccess oluşturmaz (uploads/.htaccess kullanın),
// auth/database/logger fonksiyonlarına bağımlıdır (mevcut projede var).

require_once __DIR__ . '/../inc/auth.php';
require_login();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../inc/logger.php';

$db = db_connect();
$user = current_user();

// Ayarlar
$MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB (videolar için genişletildi)
$MAX_FILES = 5;

$ALLOWED_MIMES = [
    'image/jpeg', 'image/png', 'image/gif',
    'application/pdf',
    'video/mp4'
];
$ALLOWED_EXTS = ['jpg','jpeg','png','gif','pdf','mp4'];

$msg = '';
$errors = [];

// Yeni ticket oluşturma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['title']) && !empty($_POST['message'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    if (mb_strlen($title) > 255) $title = mb_substr($title, 0, 255);

    try {
        $stmt = $db->prepare("INSERT INTO tickets (user_id, title, message, status, created_at) VALUES (:uid,:t,:m,'open',NOW())");
        $stmt->execute([':uid'=>$user['id'] ?? null, ':t'=>$title, ':m'=>$message]);
        $ticketId = $db->lastInsertId();

        if (function_exists('log_action')) log_action('ticket_create', ['title'=>$title, 'ticket_id'=>$ticketId]);

        // Dosya yükleme (varsa)
        if (!empty($_FILES['attachments']) && isset($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
            // En az bir dosya seçilip seçilmediğini kontrol et
            $hasAnyUpload = false;
            foreach ($_FILES['attachments']['error'] as $e) {
                if ($e !== UPLOAD_ERR_NO_FILE) { $hasAnyUpload = true; break; }
            }

            if ($hasAnyUpload) {
                $countFiles = count($_FILES['attachments']['name']);
                if ($countFiles > $MAX_FILES) {
                    $errors[] = "En fazla {$MAX_FILES} dosya yükleyebilirsiniz.";
                } else {
                    $uploadDir = __DIR__ . '/../uploads/tickets/' . $ticketId . '/';
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                            throw new RuntimeException("Yükleme dizini oluşturulamadı: {$uploadDir}");
                        }
                        // Not: artık her ticket dizinine .htaccess yazılmıyor.
                        // uploads/.htaccess kullanın.
                    }

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    for ($i = 0; $i < $countFiles; $i++) {
                        $err = $_FILES['attachments']['error'][$i];

                        // Dosya girişinde seçilmemişse atla
                        if ($err === UPLOAD_ERR_NO_FILE) continue;

                        $origName = $_FILES['attachments']['name'][$i] ?? '(isimsiz)';
                        if ($err !== UPLOAD_ERR_OK) {
                            $errors[] = "Dosya yüklenirken hata oluştu: " . htmlspecialchars($origName, ENT_QUOTES, 'UTF-8');
                            continue;
                        }

                        $tmpPath = $_FILES['attachments']['tmp_name'][$i];
                        $size = $_FILES['attachments']['size'][$i];

                        if ($size > $MAX_FILE_SIZE) {
                            $errors[] = "{$origName} dosyası izin verilen boyuttan büyük (maks " . ($MAX_FILE_SIZE/1024/1024) . "MB).";
                            continue;
                        }

                        // MIME ve uzantı kontrolü
                        $mime = $finfo->file($tmpPath);
                        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                        // Bazı sunucularda mp4 için mime farklı olabilir; gerekirse listede gevşetme yapılabilir
                        if (!in_array($mime, $ALLOWED_MIMES, true) || !in_array($ext, $ALLOWED_EXTS, true)) {
                            $errors[] = "{$origName} tipi/uzantısı izin verilenler arasında değil.";
                            continue;
                        }

                        // Güvenli isim üret ve taşı
                        $storedName = bin2hex(random_bytes(16)) . ($ext ? ".{$ext}" : '');
                        $destPath = $uploadDir . $storedName;
                        if (!move_uploaded_file($tmpPath, $destPath)) {
                            $errors[] = "{$origName} kaydedilemedi.";
                            continue;
                        }

                        // attachments tablosuna kaydet
                        $relPath = 'uploads/tickets/' . $ticketId . '/' . $storedName;
                        $insert = $db->prepare("INSERT INTO attachments (ticket_id, original_name, stored_name, mime_type, size, path, uploaded_at) VALUES (:ticket_id, :original_name, :stored_name, :mime_type, :size, :path, NOW())");
                        $insert->execute([
                            ':ticket_id' => $ticketId,
                            ':original_name' => $origName,
                            ':stored_name' => $storedName,
                            ':mime_type' => $mime,
                            ':size' => $size,
                            ':path' => $relPath
                        ]);
                    }
                }
            }
        }

        if (empty($errors)) {
            $msg = 'Ticket oluşturuldu.';
        } else {
            $msg = 'Ticket oluşturuldu, ancak bazı dosyalar yüklenemedi.';
        }
    } catch (Throwable $e) {
        error_log('tickets.php create error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        $errors[] = 'Sunucu tarafında bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
    }
}

// Listeleme
if (is_admin()) {
    $rows = $db->query("SELECT t.*, u.username FROM tickets t LEFT JOIN users u ON u.id = t.user_id ORDER BY created_at DESC")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT t.*, u.username FROM tickets t LEFT JOIN users u ON u.id = t.user_id WHERE t.user_id = :uid ORDER BY created_at DESC");
    $stmt->execute([':uid'=>$user['id'] ?? 0]);
    $rows = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="tr">
<head>
  <?php if (file_exists(__DIR__ . '/includes/chat-init.php')) include __DIR__ . '/includes/chat-init.php'; ?>
  <?php require_once __DIR__ . '/../inc/head.php'; ?>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Destek / Ticket</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/tickets.css">
</head>
<body>
<?php require_once __DIR__ . '/header.php'; ?>

<main style="padding:18px">
  <div class="ticket-card">
    <h1 class="h4 mb-3">Destek / Ticket</h1>

    <?php if(!empty($msg)): ?>
      <div class="alert alert-success message" role="alert"><?=htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')?></div>
    <?php endif; ?>

    <?php if(!empty($errors)): ?>
      <div class="alert alert-warning">
        <ul class="mb-0">
          <?php foreach($errors as $e): ?><li><?=htmlspecialchars($e, ENT_QUOTES, 'UTF-8')?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
      <div class="card-body">
        <h2 class="h5 card-title">Yeni Ticket Oluştur</h2>
        <form method="post" class="row g-3" id="ticket-create-form" enctype="multipart/form-data" action="">
          <div class="col-12">
            <label class="form-label">Başlık</label>
            <input name="title" class="form-control" required maxlength="255" placeholder="Kısa ve öz bir başlık girin">
          </div>

          <div class="col-12">
            <label class="form-label">Mesaj</label>
            <textarea name="message" class="form-control" rows="5" required placeholder="Sorununuzu veya isteğinizi detaylandırın"></textarea>
            <div class="form-text small-muted">İçerikte özel bilgi paylaşmaktan kaçının.</div>
          </div>

          <div class="col-12">
            <label class="form-label">Ek Dosyalar (isteğe bağlı) — izin verilen: png, jpg, jpeg, gif, pdf, mp4</label>
            <div id="dropZone" class="border rounded p-3 bg-white text-center">
              <input id="fileInput" name="attachments[]" type="file" multiple class="d-none" accept=".png,.jpg,.jpeg,.gif,.pdf,.mp4,image/*,video/mp4,application/pdf">
              <button type="button" id="chooseFilesBtn" class="btn btn-outline-primary btn-sm mb-2">Dosya Seç</button>
              <p class="small text-muted mb-0">Dosyaları buraya sürükleyip bırakabilir veya seçebilirsiniz.</p>
            </div>
            <div id="preview" class="mt-3 d-flex flex-wrap gap-2"></div>
          </div>

          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Gönder</button>
            <button class="btn btn-outline-secondary" type="reset">Temizle</button>
          </div>
        </form>
      </div>
    </div>

    <h2 class="h6 mb-2">Ticket Listesi</h2>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="mb-3 d-flex justify-content-between align-items-center">
          <div class="small-muted">Toplam: <strong><?=count($rows)?></strong></div>
          <div>
            <input id="searchInput" class="form-control form-control-sm" style="min-width:220px" placeholder="Başlıkta ara">
          </div>
        </div>

        <div class="table-wrap">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:70px">ID</th>
                <th>Başlık</th>
                <th style="width:110px">Durum</th>
                <?php if (is_admin()): ?><th style="width:160px">Kullanıcı</th><?php endif; ?>
                <th style="width:160px">Tarih</th>
              </tr>
            </thead>
            <tbody id="ticketsTable">
              <?php if (empty($rows)): ?>
                <tr><td colspan="<?= is_admin() ? 5 : 4 ?>" class="text-center text-muted py-4">Henüz ticket yok.</td></tr>
              <?php else: ?>
                <?php foreach($rows as $t): ?>
                  <tr>
                    <td><?=htmlspecialchars($t['id'])?></td>
                    <td><a href="ticket_view.php?id=<?=urlencode($t['id'])?>"><?=htmlspecialchars($t['title'])?></a></td>
                    <td>
                      <?php if ($t['status'] === 'open'): ?>
                        <span class="badge bg-success">Açık</span>
                      <?php else: ?>
                        <span class="badge bg-secondary"><?=htmlspecialchars($t['status'])?></span>
                      <?php endif; ?>
                    </td>
                    <?php if (is_admin()): ?>
                      <td><?=htmlspecialchars($t['username'] ?? ($t['user_id'] ?? '-'))?></td>
                    <?php endif; ?>
                    <td><?=htmlspecialchars($t['created_at'])?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/tickets.js"></script>
<script src="../assets/js/tickets-search.js"></script>
</body>
</html>