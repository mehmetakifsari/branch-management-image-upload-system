<?php
// panel/delete_file.php
// Deletes a single uploaded file (and its thumb) and removes its entry from uploads.files_json.
// Supports both normal (redirect) and AJAX (JSON) callers.
// Usage:
//  - browser redirect flow: panel/delete_file.php?file=<filename>&return=<redirect-path>
//  - AJAX flow: POST or GET to same URL with ajax=1 (or with X-Requested-With: XMLHttpRequest)
// Security: requires login; non-admins may only delete files from their own branch.

require_once __DIR__ . '/../inc/auth.php';
require_login();
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$filename = isset($_REQUEST['file']) ? trim((string)$_REQUEST['file']) : '';
$return   = isset($_REQUEST['return']) ? rawurldecode((string)$_REQUEST['return']) : '';
// detect ajax: explicit param or X-Requested-With header or Accept: application/json
$isAjax = (isset($_REQUEST['ajax']) && (string)$_REQUEST['ajax'] === '1')
    || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

if ($filename === '') {
    $msg = 'Silinecek dosya belirtilmedi.';
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>$msg]);
        exit;
    }
    $_SESSION['flash_error'] = $msg;
    header('Location: ' . asset('/panel/branches.php'));
    exit;
}

// sanitize filename to basename to avoid path traversal
$filename = basename($filename);

// DB lookup
try {
    $db = db_connect();
    // Use LIKE to find a row that references this stored filename (stored field)
    $like = '%"stored":"'.$filename.'"%';
    $stmt = $db->prepare("SELECT id, plaka, isemri, branch_code, files_json FROM uploads WHERE files_json LIKE :like LIMIT 1");
    $stmt->execute([':like' => $like]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("delete_file.php DB error (lookup): " . $e->getMessage());
    $msg = 'Veritabanı hatası.';
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>$msg]);
        exit;
    }
    $_SESSION['flash_error'] = $msg;
    header('Location: ' . (stripos($return, '/') === 0 ? $return : asset('/panel/branches.php')));
    exit;
}

if (!$row) {
    $msg = 'Dosya veritabanında bulunamadı.';
    if ($isAjax) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>$msg]);
        exit;
    }
    $_SESSION['flash_error'] = $msg;
    header('Location: ' . (stripos($return, '/') === 0 ? $return : asset('/panel/branches.php')));
    exit;
}

// Authorization: non-admins may only delete files from their branch
$user = current_user();
if (!is_admin()) {
    $userBranch = $user['branch_code'] ?? null;
    if ((string)$userBranch !== (string)$row['branch_code']) {
        $msg = 'Bu dosyayı silme yetkiniz yok.';
        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'message'=>$msg]);
            exit;
        }
        $_SESSION['flash_error'] = $msg;
        header('Location: ' . (stripos($return, '/') === 0 ? $return : asset('/panel/branches.php')));
        exit;
    }
}

// decode files_json and find matching entry
$filesJson = $row['files_json'];
$filesArr = json_decode($filesJson, true);
if (!is_array($filesArr)) $filesArr = [];

$foundIndex = null;
$foundItem = null;
foreach ($filesArr as $i => $it) {
    if (isset($it['stored']) && $it['stored'] === $filename) {
        $foundIndex = $i;
        $foundItem = $it;
        break;
    }
}
if ($foundIndex === null) {
    // fallback search by basename
    foreach ($filesArr as $i => $it) {
        $stored = $it['stored'] ?? '';
        if (basename($stored) === $filename) {
            $foundIndex = $i;
            $foundItem = $it;
            break;
        }
    }
}

if ($foundIndex === null) {
    $msg = 'Dosya kaydı bulunamadı (json içinde).';
    if ($isAjax) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>$msg]);
        exit;
    }
    $_SESSION['flash_error'] = $msg;
    header('Location: ' . (stripos($return, '/') === 0 ? $return : asset('/panel/branches.php')));
    exit;
}

// Determine filesystem paths
$year = substr($filename, 0, 4);
if (!preg_match('/^\d{4}$/', $year)) $year = date('Y');

$isemri = $row['isemri'] ?: 'PDI';
$plaka  = $row['plaka'] ?: ''; // may be VIN for PDI flows

$uploadsBase = realpath(__DIR__ . '/../uploads');
if ($uploadsBase === false) $uploadsBase = __DIR__ . '/../uploads';

$targetDir = $uploadsBase . '/' . $year . '/' . $isemri . '/' . $plaka;
$targetFile = $targetDir . '/' . $filename;

// delete original
$deletedOrig = false;
if (file_exists($targetFile) && is_file($targetFile)) {
    try { @unlink($targetFile); $deletedOrig = !file_exists($targetFile); } catch(Throwable $e){ error_log("delete_file.php unlink orig error: " . $e->getMessage()); $deletedOrig = false; }
} else {
    // try stored path if present
    if (!empty($foundItem['path'])) {
        $p = $foundItem['path'];
        $u = parse_url($p);
        $pPath = $u['path'] ?? $p;
        $pPath = ltrim($pPath, '/');
        $alt = __DIR__ . '/../' . $pPath;
        if (file_exists($alt) && is_file($alt)) {
            try { @unlink($alt); $deletedOrig = !file_exists($alt); } catch(Throwable $e){ error_log("delete_file.php unlink alt orig error: " . $e->getMessage()); }
        }
    }
}

// delete thumb
$deletedThumb = false;
if (!empty($foundItem['thumb'])) {
    $t = $foundItem['thumb'];
    $u = parse_url($t);
    $tPath = $u['path'] ?? $t;
    $tPath = ltrim($tPath, '/');
    $thumbLocal = __DIR__ . '/../' . $tPath;
    if (file_exists($thumbLocal) && is_file($thumbLocal)) {
        try { @unlink($thumbLocal); $deletedThumb = !file_exists($thumbLocal); } catch(Throwable $e){ error_log("delete_file.php unlink thumb error: ".$e->getMessage()); }
    }
} else {
    $base = $year . '-' . $isemri . '-' . $plaka;
    if (preg_match('/^' . preg_quote($base, '/') . '-(\d+)\.[a-z0-9]+$/i', $filename, $m)) {
        $thumbName = 'thumb-' . $base . '-' . $m[1] . '.jpg';
        $thumbLocal = $targetDir . '/thumbs/' . $thumbName;
        if (file_exists($thumbLocal) && is_file($thumbLocal)) {
            try { @unlink($thumbLocal); $deletedThumb = !file_exists($thumbLocal); } catch(Throwable $e){ error_log("delete_file.php unlink thumb2 error: ".$e->getMessage()); }
        }
    }
}

// remove from files array
array_splice($filesArr, $foundIndex, 1);
$updatedFilesJson = (count($filesArr) > 0) ? json_encode($filesArr, JSON_UNESCAPED_UNICODE) : null;

try {
    $uStmt = $db->prepare("UPDATE uploads SET files_json = :files WHERE id = :id");
    $uStmt->bindValue(':files', $updatedFilesJson, $updatedFilesJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $uStmt->bindValue(':id', $row['id'], PDO::PARAM_INT);
    $uStmt->execute();
} catch (Throwable $e) {
    error_log("delete_file.php DB update error: " . $e->getMessage());
    $msg = 'Dosya fiziksel olarak silindi ancak veritabanı güncellenemedi.';
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>$msg,'deletedOrig'=>$deletedOrig,'deletedThumb'=>$deletedThumb]);
        exit;
    }
    $_SESSION['flash_error'] = $msg;
    header('Location: ' . (stripos($return, '/') === 0 ? $return : asset('/panel/branches.php')));
    exit;
}

// success
$msgParts = ['Dosya silindi.'];
if ($deletedOrig) $msgParts[] = 'Fiziksel dosya kaldırıldı.';
if ($deletedThumb) $msgParts[] = 'Thumb kaldırıldı.';
$message = implode(' ', $msgParts);

// If ajax => return JSON; else redirect
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true,'message'=>$message,'deletedOrig'=>$deletedOrig,'deletedThumb'=>$deletedThumb]);
    exit;
}

// non-ajax flow: set flash and redirect
$_SESSION['flash_message'] = $message;
$redirect = asset('/panel/branches.php');
if ($return) {
    if (strpos($return, '/') === 0) {
        $redirect = $return;
    } elseif (stripos($return, 'http') === 0) {
        // ignore external
    } else {
        $redirect = '/' . ltrim($return, '/');
    }
}
header('Location: ' . $redirect);
exit;
?>