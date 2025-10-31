<?php
// public_html/pnl2/load_images.php
// Returns HTML fragment for a given job (isemri).
// Ensures thumbnails are used for <img src> (if available) and <a href> points to original.
// Requires authentication.

require_once __DIR__ . '/inc/auth.php';
require_login();
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/database.php';

$db = db_connect();
$user = current_user();

$job = isset($_GET['job']) ? trim((string)$_GET['job']) : '';
if ($job === '') {
    http_response_code(400);
    echo '<div class="images-loading">Geçersiz iş emri.</div>';
    exit;
}

// branch constraint for non-admins
if (!is_admin()) {
    $branch = $user['branch_code'];
} else {
    $branch = isset($_GET['b']) ? $_GET['b'] : null;
}

try {
    if ($branch) {
        $stmt = $db->prepare("SELECT * FROM uploads WHERE isemri = :job AND branch_code = :b ORDER BY created_at DESC");
        $stmt->execute([':job' => $job, ':b' => $branch]);
    } else {
        $stmt = $db->prepare("SELECT * FROM uploads WHERE isemri = :job ORDER BY created_at DESC");
        $stmt->execute([':job' => $job]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<div class="images-loading">Veritabanı hatası.</div>';
    exit;
}

if (empty($rows)) {
    echo '<div class="images-loading">Bu iş emrine ait görsel bulunamadı.</div>';
    exit;
}

// Collect all file entries
$allFiles = [];
foreach ($rows as $rec) {
    $farr = json_decode($rec['files_json'], true);
    if (is_array($farr)) {
        foreach ($farr as $f) {
            $f['uploaded_at'] = $rec['created_at'];
            $f['upload_id'] = $rec['id'];
            $allFiles[] = $f;
        }
    }
}

if (empty($allFiles)) {
    echo '<div class="images-loading">Bu iş emrine ait dosya kaydı yok.</div>';
    exit;
}

// Helper: compute public URL for a stored value (which may be full URL, root-relative or relative)
function public_url_from_field($val) {
    $val = (string)$val;
    $val = trim($val);
    if ($val === '') return '';
    if (preg_match('#^https?://#i', $val)) return $val;
    // If it's root-relative like "/pnl2/uploads/..." or "/uploads/..."
    if (strpos($val, '/') === 0) return $val;
    // otherwise treat as relative to base: use asset()
    return asset('/' . ltrim($val, '/'));
}

// Helper: convert a public URL containing "/uploads/" to filesystem path
function public_url_to_fs($publicUrl) {
    // find "/uploads/" segment
    $pos = strpos($publicUrl, '/uploads/');
    if ($pos === false) return false;
    // take substring from 'uploads/...'
    $rel = substr($publicUrl, $pos + 1); // remove leading slash
    // __DIR__ is pnl2 folder
    $fs = __DIR__ . '/' . $rel;
    return $fs;
}

// Render thumbnails: prefer thumb (field), else computed thumb path, else original
?>
<div class="thumb-row" role="list">
  <?php foreach ($allFiles as $f):
      $origName = htmlspecialchars($f['original_name'] ?? ($f['stored'] ?? ''));
      $stored = $f['stored'] ?? '';
      $ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));

      // Determine original public URL
      $origCandidate = '';
      if (!empty($f['path'])) {
          $origCandidate = public_url_from_field($f['path']);
      } else {
          // fallback: build from stored name and known uploads layout
          // we expect stored files to be under uploads/YEAR/ISEMRI/PLAKA/filename
          $origCandidate = asset('/uploads/' . ltrim($stored, '/'));
      }
      $origPublic = $origCandidate;

      // Determine thumb public URL (preferred)
      $thumbPublic = '';
      if (!empty($f['thumb'])) {
          $thumbPublic = public_url_from_field($f['thumb']);
          // verify filesystem existence
          $thumbFs = public_url_to_fs($thumbPublic);
          if ($thumbFs === false || !file_exists($thumbFs)) {
              $thumbPublic = ''; // not valid, fallback later
          }
      }

      // If no thumbPublic yet and stored is present, try expected thumb path:
      if ($thumbPublic === '' && $stored !== '') {
          // try to find thumb file in same uploads path under /thumbs/thumb-{storedWithoutExt}.jpg
          // derive the uploads directory from original public url
          $origFs = public_url_to_fs($origPublic);
          if ($origFs !== false) {
              $dir = dirname($origFs); // .../PLAKA
              $thumbFsCandidate = $dir . '/thumbs/thumb-' . pathinfo($stored, PATHINFO_FILENAME) . '.jpg';
              if (file_exists($thumbFsCandidate)) {
                  // build thumb public path
                  // compute relative after '/uploads/' segment
                  $pos2 = strpos($thumbFsCandidate, '/uploads/');
                  if ($pos2 !== false) {
                      $rel2 = substr($thumbFsCandidate, $pos2 + 1);
                      $thumbPublic = asset('/' . $rel2);
                  } else {
                      // as fallback, try building from known structure (use dirname of original public)
                      $thumbPublic = dirname($origPublic) . '/thumbs/thumb-' . pathinfo($stored, PATHINFO_FILENAME) . '.jpg';
                  }
              }
          }
      }

      // Final fallback: if still no thumbPublic and file is image, we can use the original as last resort
      if ($thumbPublic === '') {
          if (in_array($ext, ['jpg','jpeg','png','gif'])) {
              $thumbPublic = $origPublic;
          } else {
              $thumbPublic = ''; // non-image, will show placeholder
          }
      }

      // esc urls
      $thumbEsc = htmlspecialchars($thumbPublic);
      $origEsc = htmlspecialchars($origPublic);
  ?>
    <div class="thumb-item" role="listitem">
      <?php if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
        <a href="<?php echo $origEsc; ?>" target="_blank" rel="noopener">
          <img src="<?php echo $thumbEsc; ?>" alt="<?php echo $origName; ?>" loading="lazy" width="180">
        </a>
      <?php else: ?>
        <div class="file-placeholder"><?php echo htmlspecialchars(strtoupper($ext)); ?></div>
      <?php endif; ?>

      <div class="thumb-actions">
        <?php if (!empty($origPublic)): ?>
          <a href="<?php echo $origEsc; ?>" download>İndir</a>
        <?php endif; ?>
        <?php if (is_admin()): ?>
          <?php if (!empty($stored)): ?>
            | <a href="delete_file.php?file=<?php echo urlencode($stored); ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" onclick="return confirm('Silinsin mi?')">Sil</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <div style="font-size:12px;color:#666;margin-top:6px"><?php echo htmlspecialchars($f['uploaded_at'] ?? ''); ?></div>
    </div>
  <?php endforeach; ?>
</div>