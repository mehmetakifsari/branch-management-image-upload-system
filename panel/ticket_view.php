<?php
// panel/ticket_view.php
// Tek bir ticket'i, eklerini ve cevap akışını gösterir; sayfa üzerinden yanıt eklemeye izin verir.
// Bu sürüm: reply_attachments alanı boşken (dosya eklenmemişken) hata üretmez.

require_once __DIR__ . '/../inc/auth.php';
require_login();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../inc/logger.php';

$db = db_connect();
$user = current_user();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Geçersiz ticket id.';
    exit;
}

// Erişim kontrolü
if (is_admin()) {
    $stmt = $db->prepare("SELECT t.*, u.username FROM tickets t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = :id");
    $stmt->execute([':id'=>$id]);
} else {
    $stmt = $db->prepare("SELECT t.*, u.username FROM tickets t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = :id AND t.user_id = :uid");
    $stmt->execute([':id'=>$id, ':uid'=>$user['id'] ?? 0]);
}
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    echo 'Ticket bulunamadı veya erişim reddedildi.';
    exit;
}

// Helper: sütun var mı kontrolü
function columnExists(PDO $pdo, string $table, string $column): bool {
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :col");
    $q->execute([':table'=>$table, ':col'=>$column]);
    return (int)$q->fetchColumn() > 0;
}
$hasReplyId = columnExists($db, 'attachments', 'reply_id');

// POST: yeni yanıt ekleme
$errors = [];
$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message']) && trim($_POST['reply_message']) !== '') {
    $replyMessage = trim($_POST['reply_message']);
    try {
        $insert = $db->prepare("INSERT INTO replies (ticket_id, user_id, message, created_at) VALUES (:ticket_id, :user_id, :message, NOW())");
        $insert->execute([
            ':ticket_id' => $id,
            ':user_id' => $user['id'] ?? null,
            ':message' => $replyMessage
        ]);
        $replyId = $db->lastInsertId();

        if (function_exists('log_action')) log_action('ticket_reply', ['ticket_id'=>$id, 'reply_id'=>$replyId]);

        // Dosya yükleme (reply_attachments[]) - önce gerçekten dosya seçilip seçilmediğini kontrol et
        $hasAnyUpload = false;
        if (!empty($_FILES['reply_attachments']) && is_array($_FILES['reply_attachments']['name'])) {
            // Kontrol: en az bir dosya seçilmiş mi? (UPLOAD_ERR_NO_FILE ise seçilmemiş kabul edilir)
            foreach ($_FILES['reply_attachments']['error'] as $err) {
                if ($err !== UPLOAD_ERR_NO_FILE) { $hasAnyUpload = true; break; }
            }
        }

        if ($hasAnyUpload) {
            if (!$hasReplyId) {
                // Eğer reply_id yoksa kullanıcıya bilgi ver (sadece dosya ekleme denemesi varsa)
                $errors[] = 'Sunucuda ek dosya desteği yapılandırılmamış (reply_id yok).';
            } else {
                $ALLOWED_MIMES = [
                    'image/jpeg', 'image/png', 'image/gif',
                    'application/pdf',
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/zip', 'application/x-zip-compressed', 'video/mp4'
                ];
                $MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

                $countFiles = count($_FILES['reply_attachments']['name']);
                $uploadDir = __DIR__ . '/../uploads/tickets/' . $id . '/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        throw new RuntimeException("Yükleme dizini oluşturulamadı: {$uploadDir}");
                    }
                    @file_put_contents($uploadDir . '.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\n  Deny from all\n</FilesMatch>\n");
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                for ($i = 0; $i < $countFiles; $i++) {
                    $err = $_FILES['reply_attachments']['error'][$i];
                    // Eğer bu girişte dosya seçilmemişse atla
                    if ($err === UPLOAD_ERR_NO_FILE) continue;

                    $origName = $_FILES['reply_attachments']['name'][$i] ?? '(isimsiz)';
                    if ($err !== UPLOAD_ERR_OK) {
                        $errors[] = "Dosya yüklenirken hata oluştu: " . htmlspecialchars($origName, ENT_QUOTES, 'UTF-8');
                        continue;
                    }
                    $tmpPath = $_FILES['reply_attachments']['tmp_name'][$i];
                    $size = $_FILES['reply_attachments']['size'][$i];

                    if ($size > $MAX_FILE_SIZE) {
                        $errors[] = "{$origName} dosyası izin verilen boyuttan büyük.";
                        continue;
                    }

                    $mime = $finfo->file($tmpPath);
                    if (!in_array($mime, $ALLOWED_MIMES, true)) {
                        $errors[] = "{$origName} tipi izin verilen dosya tipleri arasında değil.";
                        continue;
                    }

                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $storedName = bin2hex(random_bytes(16)) . ($ext ? ".{$ext}" : '');
                    $destPath = $uploadDir . $storedName;
                    if (!move_uploaded_file($tmpPath, $destPath)) {
                        $errors[] = "{$origName} kaydedilemedi.";
                        continue;
                    }

                    $relPath = 'uploads/tickets/' . $id . '/' . $storedName;
                    $ins = $db->prepare("INSERT INTO attachments (ticket_id, reply_id, original_name, stored_name, mime_type, size, path, uploaded_at) VALUES (:ticket_id, :reply_id, :original_name, :stored_name, :mime_type, :size, :path, NOW())");
                    $ins->execute([
                        ':ticket_id' => $id,
                        ':reply_id' => $replyId,
                        ':original_name' => $origName,
                        ':stored_name' => $storedName,
                        ':mime_type' => $mime,
                        ':size' => $size,
                        ':path' => $relPath
                    ]);
                }
            }
        }

        if (empty($errors)) {
            $successMsg = 'Yanıt gönderildi.';
            // POST sonrası tekrar gönderimi engellemek için yönlendir
            header("Location: ticket_view.php?id=" . urlencode($id) . "&r=1");
            exit;
        } else {
            $successMsg = 'Yanıt kaydedildi, ancak bazı dosyalar yüklenemedi.';
        }
    } catch (Throwable $e) {
        error_log('ticket_view.php reply error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        $errors[] = 'Sunucu tarafında bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
    }
}

// Yorumları / cevapları çek
$replyStmt = $db->prepare("SELECT r.*, u.username FROM replies r LEFT JOIN users u ON u.id = r.user_id WHERE r.ticket_id = :id ORDER BY r.created_at ASC");
$replyStmt->execute([':id'=>$id]);
$replies = $replyStmt->fetchAll();

// Ticket eklerini çektik (reply_id varsa ona göre davran)
if ($hasReplyId) {
    $attStmt = $db->prepare("SELECT * FROM attachments WHERE ticket_id = :id AND (reply_id IS NULL OR reply_id = 0) ORDER BY uploaded_at DESC");
    $attStmt->execute([':id'=>$id]);
    $attachments = $attStmt->fetchAll();

    // Reply'lere ait ekleri grupla (reply_id => [attachments])
    $attByReply = [];
    $allAttStmt = $db->prepare("SELECT * FROM attachments WHERE ticket_id = :id ORDER BY uploaded_at ASC");
    $allAttStmt->execute([':id'=>$id]);
    foreach ($allAttStmt->fetchAll() as $a) {
        $rid = $a['reply_id'] ? intval($a['reply_id']) : 0;
        if (!isset($attByReply[$rid])) $attByReply[$rid] = [];
        $attByReply[$rid][] = $a;
    }
} else {
    // reply_id yoksa: tüm ekleri ticket-level olarak kabul et
    $attStmt = $db->prepare("SELECT * FROM attachments WHERE ticket_id = :id ORDER BY uploaded_at DESC");
    $attStmt->execute([':id'=>$id]);
    $attachments = $attStmt->fetchAll();
    $attByReply = [];
}

?>
<!doctype html>
<html lang="tr"><head>
  <?php require_once __DIR__ . '/../inc/head.php'; ?>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ticket #<?=htmlspecialchars($ticket['id'])?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/tickets.css">
  <style>
    .reply { border-left: 3px solid #e9ecef; padding-left:12px; margin-bottom:12px; }
    .reply .meta { font-size:.9rem; color:#6c757d; margin-bottom:6px; }
    .reply-form .file-preview { margin-top:8px; display:flex; gap:8px; flex-wrap:wrap; }
    .reply-form .file-thumb { width:100px; text-align:center; }
  </style>
</head><body>
<?php require_once __DIR__ . '/header.php'; ?>
<main style="padding:18px">
  <div class="card mx-auto" style="max-width:900px">
    <div class="card-body">
      <h4 class="card-title"><?=htmlspecialchars($ticket['title'])?> <small class="text-muted">#<?=htmlspecialchars($ticket['id'])?></small></h4>
      <div class="small-muted mb-2">Gönderen: <?=htmlspecialchars($ticket['username'] ?? ($ticket['user_id'] ?? '-'))?> • <?=htmlspecialchars($ticket['created_at'])?></div>
      <p><?=nl2br(htmlspecialchars($ticket['message']))?></p>

      <h6>Ekler</h6>
      <?php if (empty($attachments)): ?>
        <div class="text-muted">Eklenmiş dosya yok.</div>
      <?php else: ?>
        <div class="d-flex flex-wrap gap-3 mb-3">
          <?php foreach($attachments as $a):
            $isImage = str_starts_with($a['mime_type'], 'image/');
            $fileUrl = '../' . $a['path'];
          ?>
            <div style="width:140px;">
              <?php if ($isImage): ?>
                <a href="<?=htmlspecialchars($fileUrl)?>" target="_blank">
                  <img src="<?=htmlspecialchars($fileUrl)?>" style="width:100%;height:90px;object-fit:cover;border:1px solid #ddd;border-radius:6px">
                </a>
              <?php else: ?>
                <div class="border p-3 text-center" style="height:90px;display:flex;align-items:center;justify-content:center;">
                  <a href="<?=htmlspecialchars($fileUrl)?>" download><?=htmlspecialchars($a['original_name'])?></a>
                </div>
              <?php endif; ?>
              <div class="small text-muted mt-1"><?=htmlspecialchars($a['original_name'])?> (<?=round($a['size']/1024,1)?> KB)</div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <hr>

      <h5 class="mb-3">Yanıtlar</h5>
      <?php if (empty($replies)): ?>
        <div class="text-muted mb-3">Henüz yanıt yok.</div>
      <?php else: ?>
        <?php foreach ($replies as $r): ?>
          <div class="reply mb-3">
            <div class="meta"><?=htmlspecialchars($r['username'] ?? ($r['user_id'] ?? ''))?> • <?=htmlspecialchars($r['created_at'])?></div>
            <div><?=nl2br(htmlspecialchars($r['message']))?></div>

            <?php $rid = intval($r['id']); if (!empty($attByReply[$rid])): ?>
              <div class="d-flex flex-wrap gap-3 mt-2">
                <?php foreach ($attByReply[$rid] as $a):
                  $isImage = str_starts_with($a['mime_type'], 'image/');
                  $fileUrl = '../' . $a['path'];
                ?>
                  <div style="width:140px;">
                    <?php if ($isImage): ?>
                      <a href="<?=htmlspecialchars($fileUrl)?>" target="_blank">
                        <img src="<?=htmlspecialchars($fileUrl)?>" style="width:100%;height:90px;object-fit:cover;border:1px solid #ddd;border-radius:6px">
                      </a>
                    <?php else: ?>
                      <div class="border p-3 text-center" style="height:90px;display:flex;align-items:center;justify-content:center;">
                        <a href="<?=htmlspecialchars($fileUrl)?>" download><?=htmlspecialchars($a['original_name'])?></a>
                      </div>
                    <?php endif; ?>
                    <div class="small text-muted mt-1"><?=htmlspecialchars($a['original_name'])?> (<?=round($a['size']/1024,1)?> KB)</div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <hr>

      <h5 class="mb-3">Yanıtla</h5>
      <?php if(!empty($successMsg)): ?>
        <div class="alert alert-success"><?=htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8')?></div>
      <?php endif; ?>
      <?php if(!empty($errors)): ?>
        <div class="alert alert-warning"><ul class="mb-0"><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e, ENT_QUOTES, 'UTF-8').'</li>'; ?></ul></div>
      <?php endif; ?>

      <form id="replyForm" method="post" enctype="multipart/form-data" class="reply-form">
        <div class="mb-3">
          <label class="form-label">Mesajınız</label>
          <textarea name="reply_message" id="reply_message" class="form-control" rows="4" required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Ek Dosyalar (isteğe bağlı)</label>
          <div id="replyDropZone" class="border rounded p-3 bg-white text-center">
            <input id="replyFileInput" name="reply_attachments[]" type="file" multiple class="d-none" accept=".png,.jpg,.jpeg,.pdf,.mp4,image/*,video/mp4,application/pdf">
            <button type="button" id="replyChooseBtn" class="btn btn-outline-primary btn-sm mb-2">Dosya Seç</button>
            <p class="small text-muted mb-0">Dosyaları sürükleyip bırakabilir veya seçebilirsiniz.</p>
          </div>
          <div id="replyPreview" class="file-preview"></div>
          <?php if (!$hasReplyId): ?>
            <div class="form-text text-warning">Sunucuda reply-attachments desteği etkin değil; ek dosyalar şu anda kaydedilemez. (admin ile görüşün)</div>
          <?php endif; ?>
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-primary" type="submit">Gönder</button>
          <button class="btn btn-outline-secondary" type="reset">Temizle</button>
        </div>
      </form>

    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Reply dropzone preview (izole)
(function(){
  const dropZone = document.getElementById('replyDropZone');
  const fileInput = document.getElementById('replyFileInput');
  const chooseBtn = document.getElementById('replyChooseBtn');
  const preview = document.getElementById('replyPreview');
  const MAX_FILES = 5;
  const MAX_FILE_SIZE = 50 * 1024 * 1024;

  if (!dropZone || !fileInput) return;

  chooseBtn?.addEventListener('click', () => fileInput.click());
  dropZone.addEventListener('click', () => fileInput.click());

  ['dragenter','dragover'].forEach(ev => dropZone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dropZone.classList.add('dragover'); }));
  ['dragleave','dragend','drop'].forEach(ev => dropZone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dropZone.classList.remove('dragover'); }));

  dropZone.addEventListener('drop', function(e){
    const dt = e.dataTransfer;
    if (!dt || !dt.files || dt.files.length === 0) return;
    addFiles(dt.files);
  });

  fileInput.addEventListener('change', updatePreview);

  function addFiles(fileList) {
    const current = Array.from(fileInput.files || []);
    const incoming = Array.from(fileList || []);
    const combined = current.concat(incoming);
    if (combined.length > MAX_FILES) {
      alert(`En fazla ${MAX_FILES} dosya seçebilirsiniz.`);
      return;
    }
    for (const f of incoming) {
      if (f.size > MAX_FILE_SIZE) { alert(`"${f.name}" çok büyük.`); return; }
    }
    const dt = new DataTransfer();
    combined.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
    updatePreview();
  }

  function updatePreview() {
    preview.innerHTML = '';
    const files = Array.from(fileInput.files || []);
    if (files.length === 0) {
      preview.innerHTML = '<div class="text-muted small">Henüz dosya seçilmedi.</div>';
      return;
    }
    files.forEach((file, idx) => {
      const div = document.createElement('div');
      div.className = 'file-thumb';
      div.style.width = '100px';
      div.style.textAlign = 'center';
      div.style.position = 'relative';
      div.style.margin = '4px';

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.innerText = '×';
      removeBtn.style.position = 'absolute';
      removeBtn.style.top = '2px';
      removeBtn.style.right = '2px';
      removeBtn.className = 'remove-btn';
      removeBtn.addEventListener('click', () => removeFile(idx));
      div.appendChild(removeBtn);

      if (file.type && file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.style.maxWidth = '100%';
        img.style.maxHeight = '70px';
        const reader = new FileReader();
        reader.onload = e => img.src = e.target.result;
        reader.readAsDataURL(file);
        div.appendChild(img);
      } else {
        const ico = document.createElement('div');
        ico.innerHTML = '<svg width="32" height="32" viewBox="0 0 24 24"><path fill="#6c757d" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path></svg>';
        div.appendChild(ico);
      }

      const meta = document.createElement('div');
      meta.style.fontSize = '12px';
      meta.style.marginTop = '4px';
      meta.style.whiteSpace = 'nowrap';
      meta.style.overflow = 'hidden';
      meta.style.textOverflow = 'ellipsis';
      meta.textContent = file.name;
      div.appendChild(meta);

      preview.appendChild(div);
    });
  }

  function removeFile(index) {
    const files = Array.from(fileInput.files || []);
    files.splice(index, 1);
    const dt = new DataTransfer();
    files.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
    updatePreview();
  }

  updatePreview();
})();
</script>
</body></html>