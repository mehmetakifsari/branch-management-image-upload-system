<?php
// public_html/pnl2/upload.php
// - Deterministic sequential naming: YEAR-ISEMRI-PLAKA-1.jpg, -2.jpg, ...
// - Optimize original image (resize / re-encode) and also create thumb files:
//     thumbs/thumb-YEAR-ISEMRI-PLAKA-1.jpg etc.
// - Store files_json entries with 'stored','path' (original public) and 'thumb' (thumb public).
// - On success redirect to home with flash message.

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/inc/logger.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// Basic inputs
$plaka = isset($_POST['plaka']) ? trim((string)$_POST['plaka']) : '';
$isemri = isset($_POST['isemri']) ? trim((string)$_POST['isemri']) : '';

if ($plaka === '' || $isemri === '') {
    $_SESSION['flash_error'] = 'Plaka ve İş Emri zorunludur.';
    header('Location: ' . asset('/'));
    exit;
}

// Normalize plaka (remove spaces/specials, uppercase)
$plaka = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plaka));
if ($plaka === '') {
    $_SESSION['flash_error'] = 'Geçersiz plaka.';
    header('Location: ' . asset('/'));
    exit;
}

// Validate isemri digits
if (!preg_match('/^[0-9]{8}$/', $isemri)) {
    $_SESSION['flash_error'] = 'İş Emri 8 haneli olmalıdır.';
    header('Location: ' . asset('/'));
    exit;
}

$year = date('Y');
$branch_map = ['1'=>'Bursa','2'=>'İzmit','3'=>'Orhanlı','4'=>'Hadımköy','5'=>'Keşan'];
$branch_code = $branch_map[$isemri[0]] ? $isemri[0] : null;
if (!$branch_code) {
    $_SESSION['flash_error'] = 'İş Emri numarası geçersiz şube kodu içeriyor.';
    header('Location: ' . asset('/'));
    exit;
}

if (empty($_FILES['files'])) {
    $_SESSION['flash_error'] = 'Dosya seçilmedi.';
    header('Location: ' . asset('/'));
    exit;
}

// Allowed extensions
$allowed = ['jpg','jpeg','png','gif','pdf','zip','rar','tst'];

// Prepare directories
$baseUploadDir = __DIR__ . '/uploads';
if (!is_dir($baseUploadDir) && !mkdir($baseUploadDir, 0755, true)) {
    $_SESSION['flash_error'] = 'Sunucu yapılandırma hatası: uploads dizini oluşturulamadı.';
    header('Location: ' . asset('/'));
    exit;
}

$targetDir = $baseUploadDir . '/' . $year . '/' . $isemri . '/' . $plaka;
if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
    $_SESSION['flash_error'] = 'Sunucu yapılandırma hatası: hedef dizin oluşturulamadı.';
    header('Location: ' . asset('/'));
    exit;
}
$thumbDir = $targetDir . '/thumbs';
if (!is_dir($thumbDir) && !mkdir($thumbDir, 0755, true)) {
    $_SESSION['flash_error'] = 'Sunucu yapılandırma hatası: thumbs dizini oluşturulamadı.';
    header('Location: ' . asset('/'));
    exit;
}

if (!is_writable($targetDir) || !is_writable($thumbDir)) {
    $_SESSION['flash_error'] = 'Sunucu yapılandırma hatası: hedef dizin yazılabilir değil.';
    header('Location: ' . asset('/'));
    exit;
}

// Helper: find next sequential index for base in a directory (checks existing files)
function next_index_for_base($dir, $base, $typePrefix = '', $exts = []) {
    $max = 0;
    $files = @scandir($dir);
    if (!is_array($files)) return 1;
    foreach ($files as $f) {
        if (!is_file($dir . '/' . $f)) continue;
        $name = pathinfo($f, PATHINFO_FILENAME); // without ext
        // For JOB-CARD pattern: base-JOB-CARD or base-JOB-CARD-2
        if ($typePrefix !== '') {
            if (preg_match('/^' . preg_quote($base . '-' . $typePrefix, '/') . '(?:-(\d+))?$/', $name, $m)) {
                $idx = (isset($m[1]) && is_numeric($m[1])) ? (int)$m[1] : 1;
                if ($idx > $max) $max = $idx;
            }
        } else {
            // images/other: base-1, base-2...
            if (preg_match('/^' . preg_quote($base . '-', '/') . '(\d+)$/', $name, $m)) {
                $idx = (int)$m[1];
                if ($idx > $max) $max = $idx;
            }
        }
    }
    return $max + 1;
}

// Image optimization functions using GD (fallback). Returns true on success.
function optimize_image_inplace($srcPath, $maxWidth = 1600, $quality = 85) {
    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif'])) return false;

    try {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $img = @imagecreatefromjpeg($srcPath);
                break;
            case 'png':
                $img = @imagecreatefrompng($srcPath);
                break;
            case 'gif':
                $img = @imagecreatefromgif($srcPath);
                break;
            default:
                return false;
        }
        if (!$img) return false;
        $w = imagesx($img);
        $h = imagesy($img);

        // if larger than maxWidth, scale down; otherwise keep size
        if ($w > $maxWidth) {
            $newW = $maxWidth;
            $newH = (int)round($h * ($newW / $w));
            $tmp = imagecreatetruecolor($newW, $newH);
            // preserve PNG transparency
            if ($ext === 'png' || $ext === 'gif') {
                imagecolortransparent($tmp, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
                imagealphablending($tmp, false);
                imagesavealpha($tmp, true);
            }
            imagecopyresampled($tmp, $img, 0,0,0,0, $newW, $newH, $w, $h);
            imagedestroy($img);
            $img = $tmp;
        }

        // overwrite optimized
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($img, $srcPath, $quality);
                break;
            case 'png':
                // convert quality 0-9 for PNG (invert)
                $pngQuality = (int)round((100 - $quality) / 10);
                imagepng($img, $srcPath, $pngQuality);
                break;
            case 'gif':
                imagegif($img, $srcPath);
                break;
        }
        imagedestroy($img);
        return true;
    } catch (Throwable $e) {
        error_log("optimize_image_inplace error: " . $e->getMessage());
        return false;
    }
}

// Create thumb (fixed width) - returns true on success
function create_thumb($srcPath, $destPath, $thumbWidth = 320, $quality = 75) {
    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif'])) return false;
    try {
        switch ($ext) {
            case 'jpg':
            case 'jpeg': $srcImg = @imagecreatefromjpeg($srcPath); break;
            case 'png': $srcImg = @imagecreatefrompng($srcPath); break;
            case 'gif': $srcImg = @imagecreatefromgif($srcPath); break;
            default: return false;
        }
        if (!$srcImg) return false;
        $w = imagesx($srcImg);
        $h = imagesy($srcImg);
        $ratio = $w > 0 ? ($thumbWidth / $w) : 1;
        $newW = (int)round($w * $ratio);
        $newH = (int)round($h * $ratio);
        $dstImg = imagecreatetruecolor($newW, $newH);
        if (in_array($ext,['png','gif'])) {
            imagecolortransparent($dstImg, imagecolorallocatealpha($dstImg, 0,0,0,127));
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
        }
        imagecopyresampled($dstImg, $srcImg, 0,0,0,0, $newW, $newH, $w, $h);
        // Save as JPEG for thumbs for smaller size
        imagejpeg($dstImg, $destPath, $quality);
        imagedestroy($srcImg);
        imagedestroy($dstImg);
        return true;
    } catch (Throwable $e) {
        error_log("create_thumb error: " . $e->getMessage());
        return false;
    }
}

// Process files
$files_info = [];
// counters to keep sequential numbers within same request
$counters = ['img'=>0,'pdf'=>0,'other'=>0];

foreach ($_FILES['files']['error'] as $i => $err) {
    if ($err !== UPLOAD_ERR_OK) continue;
    $tmp = $_FILES['files']['tmp_name'][$i];
    $orig = basename($_FILES['files']['name'][$i]);
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) continue;
    if (!is_uploaded_file($tmp)) continue;

    $base = "{$year}-{$isemri}-{$plaka}";

    if (in_array($ext, ['jpg','jpeg','png','gif'])) {
        // images
        $next = next_index_for_base($targetDir, $base, '', $allowed);
        // If multiple images in same request, next should increase sequentially
        $next += $counters['img'];
        $counters['img']++;
        $filename = "{$base}-{$next}.{$ext}";
        $dest = $targetDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $dest)) {
            error_log("upload: move_uploaded_file failed for {$orig}");
            continue;
        }

        // Optimize original in-place (resize & re-encode)
        optimize_image_inplace($dest, 1600, 85);

        // Create thumb file name and path: thumbs/thumb-{base}-{n}.jpg
        $thumbName = "thumb-{$base}-{$next}.jpg";
        $thumbPath = $thumbDir . '/' . $thumbName;
        // create thumb (jpeg)
        create_thumb($dest, $thumbPath, 320, 75);

        // public paths via asset()
        $relOriginal = 'uploads/' . $year . '/' . $isemri . '/' . $plaka . '/' . $filename;
        $relThumb = 'uploads/' . $year . '/' . $isemri . '/' . $plaka . '/thumbs/' . $thumbName;
        $publicOriginal = asset('/' . $relOriginal);
        $publicThumb = asset('/' . $relThumb);

        $files_info[] = [
            'original_name' => $orig,
            'stored' => $filename,
            'ext' => $ext,
            'path' => $publicOriginal,
            'thumb' => $publicThumb
        ];
    } elseif ($ext === 'pdf') {
        // pdf naming: base-JOB-CARD.pdf or base-JOB-CARD-2.pdf
        $next = next_index_for_base($targetDir, $base, 'JOB-CARD', ['pdf']);
        $next += $counters['pdf'];
        $counters['pdf']++;
        // if first and none exist, name base-JOB-CARD.pdf else base-JOB-CARD-<n>.pdf
        $existing_first = glob($targetDir . '/' . $base . '-JOB-CARD.*');
        if (empty($existing_first) && $next === 1) {
            $filename = "{$base}-JOB-CARD.{$ext}";
        } else {
            $filename = "{$base}-JOB-CARD-{$next}.{$ext}";
        }
        $dest = $targetDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            error_log("upload: move_uploaded_file failed for {$orig}");
            continue;
        }
        // PDFs - no thumb generation by default (could generate page preview later)
        $relOriginal = 'uploads/' . $year . '/' . $isemri . '/' . $plaka . '/' . $filename;
        $publicOriginal = asset('/' . $relOriginal);
        $files_info[] = [
            'original_name' => $orig,
            'stored' => $filename,
            'ext' => $ext,
            'path' => $publicOriginal,
            'thumb' => null
        ];
    } else {
        // other files - treat like images numbering
        $next = next_index_for_base($targetDir, $base, '', $allowed);
        $next += $counters['other'];
        $counters['other']++;
        $filename = "{$base}-{$next}.{$ext}";
        $dest = $targetDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            error_log("upload: move_uploaded_file failed for {$orig}");
            continue;
        }
        $relOriginal = 'uploads/' . $year . '/' . $isemri . '/' . $plaka . '/' . $filename;
        $publicOriginal = asset('/' . $relOriginal);
        $files_info[] = [
            'original_name' => $orig,
            'stored' => $filename,
            'ext' => $ext,
            'path' => $publicOriginal,
            'thumb' => null
        ];
    }
}

// Nothing uploaded successfully
if (empty($files_info)) {
    $_SESSION['flash_error'] = 'Geçerli dosya yüklenmedi veya izin verilmeyen uzantı.';
    header('Location: ' . asset('/'));
    exit;
}

// DB insert
try {
    $db = db_connect();
    $stmt = $db->prepare("INSERT INTO uploads (plaka, isemri, branch_code, files_json, created_at) VALUES (:plaka, :isemri, :branch, :files, NOW())");
    $stmt->execute([
        ':plaka' => $plaka,
        ':isemri' => $isemri,
        ':branch' => $branch_code,
        ':files' => json_encode($files_info, JSON_UNESCAPED_UNICODE)
    ]);
    if (function_exists('log_action')) {
        log_action('upload', [
            'isemri' => $isemri,
            'plaka' => $plaka,
            'branch_code' => $branch_code,
            'files' => array_map(function($f){ return $f['stored']; }, $files_info)
        ]);
    }
    $_SESSION['flash_message'] = 'Yükleme başarılı.';
    header('Location: ' . asset('/'));
    exit;
} catch (Throwable $e) {
    error_log("upload.php DB error: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Veritabanı hatası.';
    header('Location: ' . asset('/'));
    exit;
}
?>