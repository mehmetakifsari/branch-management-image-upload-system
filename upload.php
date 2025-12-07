<?php
// public_html/pnl2/upload.php
// Updated: use client-provided branch (POST['branch']) when present (PDI flow).
// Validates branch; falls back to isemri-first-digit if provided; otherwise 0.

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

// Read inputs
$pdi = isset($_POST['pdi']) && (string)$_POST['pdi'] === '1';
$plaka = isset($_POST['plaka']) ? trim((string)$_POST['plaka']) : '';
$isemri = isset($_POST['isemri']) ? trim((string)$_POST['isemri']) : '';
$vin = isset($_POST['vin']) ? trim((string)$_POST['vin']) : '';
$branchPost = isset($_POST['branch']) ? trim((string)$_POST['branch']) : '';

// Basic server-side validation
if ($pdi) {
    if ($vin === '') {
        $_SESSION['flash_error'] = 'PDI seçili ise VIN (Şasi No) zorunludur.';
        header('Location: ' . asset('/'));
        exit;
    }
    // Also require branch selection server-side (frontend enforces, but validate here too)
    if ($branchPost === '') {
        $_SESSION['flash_error'] = 'PDI seçili ise lütfen şube seçin.';
        header('Location: ' . asset('/'));
        exit;
    }
} else {
    if ($plaka === '' || $isemri === '') {
        $_SESSION['flash_error'] = 'Plaka ve İş Emri zorunludur.';
        header('Location: ' . asset('/'));
        exit;
    }
}

// Normalize inputs
$plaka = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plaka));
$vin = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $vin));
$branchPost = preg_replace('/[^0-9]/', '', $branchPost); // keep digits only

// isemri validation if provided
if ($isemri !== '' && !preg_match('/^[0-9]{8}$/', $isemri)) {
    $_SESSION['flash_error'] = 'İş Emri 8 haneli olmalıdır.';
    header('Location: ' . asset('/'));
    exit;
}

// Branch map & validation
$branch_map = ['1'=>'Bursa','2'=>'İzmit','3'=>'Orhanlı','4'=>'Hadımköy','5'=>'Keşan'];
$branch_code = null;

// Priority 1: if branch provided in POST and valid, use it
if ($branchPost !== '') {
    if (isset($branch_map[$branchPost])) {
        $branch_code = (int)$branchPost;
    } else {
        $_SESSION['flash_error'] = 'Geçersiz şube seçimi.';
        header('Location: ' . asset('/'));
        exit;
    }
}

// Priority 2: if isemri present and valid, derive branch from first digit
if ($branch_code === null && $isemri !== '') {
    $first = $isemri[0];
    if (isset($branch_map[$first])) {
        $branch_code = (int)$first;
    } else {
        $_SESSION['flash_error'] = 'İş Emri numarası geçersiz şube kodu içeriyor.';
        header('Location: ' . asset('/'));
        exit;
    }
}

// If still null, set default (0) to satisfy DB NOT NULL constraint
$branch_code_int = ($branch_code !== null) ? (int)$branch_code : 0;

// Naming helpers
$year = date('Y');
$namePlate = $plaka !== '' ? $plaka : ($vin !== '' ? $vin : 'NOPLATE');
$nameIsemri = $isemri !== '' ? $isemri : 'PDI';

// Files check
if (empty($_FILES['files'])) {
    $_SESSION['flash_error'] = 'Dosya seçilmedi.';
    header('Location: ' . asset('/'));
    exit;
}

// Allowed extensions per your list
$allowed = ['tst','pdf','jpg','jpeg','png','mp4','oxps','zip','rar'];

// Prepare directories
$baseUploadDir = __DIR__ . '/uploads';
if (!is_dir($baseUploadDir) && !mkdir($baseUploadDir, 0755, true)) {
    $_SESSION['flash_error'] = 'Sunucu yapılandırma hatası: uploads dizini oluşturulamadı.';
    header('Location: ' . asset('/'));
    exit;
}

$targetDir = $baseUploadDir . '/' . $year . '/' . $nameIsemri . '/' . $namePlate;
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

// Helper functions
function next_index_for_base($dir, $base, $typePrefix = '', $exts = []) {
    $max = 0;
    $files = @scandir($dir);
    if (!is_array($files)) return 1;
    foreach ($files as $f) {
        if (!is_file($dir . '/' . $f)) continue;
        $name = pathinfo($f, PATHINFO_FILENAME);
        if ($typePrefix !== '') {
            if (preg_match('/^' . preg_quote($base . '-' . $typePrefix, '/') . '(?:-(\d+))?$/', $name, $m)) {
                $idx = (isset($m[1]) && is_numeric($m[1])) ? (int)$m[1] : 1;
                if ($idx > $max) $max = $idx;
            }
        } else {
            if (preg_match('/^' . preg_quote($base . '-', '/') . '(\d+)$/', $name, $m)) {
                $idx = (int)$m[1];
                if ($idx > $max) $max = $idx;
            }
        }
    }
    return $max + 1;
}

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
        if ($w > $maxWidth) {
            $newW = $maxWidth;
            $newH = (int)round($h * ($newW / $w));
            $tmp = imagecreatetruecolor($newW, $newH);
            if ($ext === 'png' || $ext === 'gif') {
                imagecolortransparent($tmp, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
                imagealphablending($tmp, false);
                imagesavealpha($tmp, true);
            }
            imagecopyresampled($tmp, $img, 0,0,0,0, $newW, $newH, $w, $h);
            imagedestroy($img);
            $img = $tmp;
        }
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($img, $srcPath, $quality);
                break;
            case 'png':
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
$counters = ['img'=>0,'pdf'=>0,'other'=>0];

foreach ($_FILES['files']['error'] as $i => $err) {
    if ($err !== UPLOAD_ERR_OK) continue;
    $tmp = $_FILES['files']['tmp_name'][$i];
    $orig = basename($_FILES['files']['name'][$i]);
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) continue;
    if (!is_uploaded_file($tmp)) continue;

    $base = "{$year}-{$nameIsemri}-{$namePlate}";

    if (in_array($ext, ['jpg','jpeg','png','gif'])) {
        // images
        $next = next_index_for_base($targetDir, $base, '', $allowed);
        $next += $counters['img'];
        $counters['img']++;
        $filename = "{$base}-{$next}.{$ext}";
        $dest = $targetDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $dest)) {
            error_log("upload: move_uploaded_file failed for {$orig}");
            continue;
        }

        optimize_image_inplace($dest, 1600, 85);

        $thumbName = "thumb-{$base}-{$next}.jpg";
        $thumbPath = $thumbDir . '/' . $thumbName;
        create_thumb($dest, $thumbPath, 320, 75);

        $relOriginal = 'uploads/' . $year . '/' . $nameIsemri . '/' . $namePlate . '/' . $filename;
        $relThumb = 'uploads/' . $year . '/' . $nameIsemri . '/' . $namePlate . '/thumbs/' . $thumbName;
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
        // pdf naming
        $next = next_index_for_base($targetDir, $base, 'JOB-CARD', ['pdf']);
        $next += $counters['pdf'];
        $counters['pdf']++;
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
        $relOriginal = 'uploads/' . $year . '/' . $nameIsemri . '/' . $namePlate . '/' . $filename;
        $publicOriginal = asset('/' . $relOriginal);
        $files_info[] = [
            'original_name' => $orig,
            'stored' => $filename,
            'ext' => $ext,
            'path' => $publicOriginal,
            'thumb' => null
        ];
    } else {
        // other allowed files
        $next = next_index_for_base($targetDir, $base, '', $allowed);
        $next += $counters['other'];
        $counters['other']++;
        $filename = "{$base}-{$next}.{$ext}";
        $dest = $targetDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            error_log("upload: move_uploaded_file failed for {$orig}");
            continue;
        }
        $relOriginal = 'uploads/' . $year . '/' . $nameIsemri . '/' . $namePlate . '/' . $filename;
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

// Insert into DB (branch_code from branch_code_int)
try {
    $db = db_connect();
    $stmt = $db->prepare("INSERT INTO uploads (plaka, isemri, branch_code, files_json, created_at) VALUES (:plaka, :isemri, :branch, :files, NOW())");
    $stmt->execute([
        ':plaka' => $plaka,
        ':isemri' => $isemri,
        ':branch' => $branch_code_int,
        ':files' => json_encode($files_info, JSON_UNESCAPED_UNICODE)
    ]);
    if (function_exists('log_action')) {
        log_action('upload', [
            'isemri' => $isemri,
            'plaka' => $plaka,
            'branch_code' => $branch_code_int,
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