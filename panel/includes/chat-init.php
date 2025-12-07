<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * chat-init.php
 * - window.CHAT_BASE: proje alt-dizini (örn '/p1' veya '')
 * - window.CURRENT_USER: session'dan algılanan { user_id, username } veya null
 *
 * Daha esnek user-array tespiti içerir.
 */

function normalize_url_path($path) {
    $parts = [];
    foreach (explode('/', $path) as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') { array_pop($parts); continue; }
        $parts[] = $p;
    }
    return '/' . implode('/', $parts);
}

$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
$projectRoot = normalize_url_path($scriptDir . '/..');
if ($projectRoot === '/') $projectRoot = '';

// server-side detection for files (debug)
$possibleRoots = [
    realpath(__DIR__ . '/../../'),
    realpath(__DIR__ . '/../../../'),
    rtrim($_SERVER['DOCUMENT_ROOT'], '/'),
];

$css_url = ($projectRoot === '') ? '/assets/css/chat-widget.css' : $projectRoot . '/assets/css/chat-widget.css';
$js_url  = ($projectRoot === '') ? '/assets/js/chat-widget.js'  : $projectRoot . '/assets/js/chat-widget.js';

$css_fs = $js_fs = false;
foreach ($possibleRoots as $root) {
    if (!$root) continue;
    $css_check = $root . '/assets/css/chat-widget.css';
    $js_check  = $root . '/assets/js/chat-widget.js';
    if (!$css_fs && is_file($css_check)) $css_fs = $css_check;
    if (!$js_fs && is_file($js_check)) $js_fs = $js_check;
}

// --- Robust session -> CURRENT_USER mapping ---
// If $_SESSION['user'] is an array, try to extract id/username from common keys.
$session_keys = json_encode(array_keys($_SESSION ?? []));

$uid = null;
$uname = null;

// direct candidates
$idCandidates = ['user_id','id','uid','userid','ID'];
$nameCandidates = ['username','user_name','name','display_name','fullname','login','email'];

// check top-level keys first
foreach ($idCandidates as $k) {
    if ($uid === null && isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) $uid = (int)$_SESSION[$k];
}
foreach ($nameCandidates as $k) {
    if ($uname === null && isset($_SESSION[$k]) && is_string($_SESSION[$k]) && $_SESSION[$k] !== '') $uname = $_SESSION[$k];
}

// check nested user array
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $userArr = $_SESSION['user'];
    // scan possible keys in userArr
    foreach ($idCandidates as $k) {
        if ($uid === null && isset($userArr[$k]) && is_numeric($userArr[$k])) { $uid = (int)$userArr[$k]; break; }
    }
    foreach ($nameCandidates as $k) {
        if ($uname === null && isset($userArr[$k]) && is_string($userArr[$k]) && $userArr[$k] !== '') { $uname = $userArr[$k]; break; }
    }
    // some systems store 'id' and 'username' keys with different capitalization
    if (($uid === null || $uname === null) && isset($userArr['id']) && $uid === null) $uid = (int)$userArr['id'];
    if (($uname === null) && isset($userArr['username'])) $uname = $userArr['username'];
    // as fallback, if user array has numeric key 0 or '0', try first element keys
    if (($uid === null || $uname === null)) {
        foreach ($userArr as $k=>$v) {
            if ($uid === null && is_numeric($k) && is_array($v) && isset($v['id'])) { $uid = (int)$v['id']; }
            if ($uname === null && is_string($v) && strlen($v) < 64) { /* unlikely */ }
        }
    }
}

// Finalize CURRENT_USER
if ($uid && $uname) {
    echo "<script>console.log('DEBUG session keys: {$session_keys}'); window.CURRENT_USER = { user_id: {$uid}, username: '" . addslashes($uname) . "' }; console.log('chat-init: CURRENT_USER set');</script>";
} else {
    echo "<script>console.log('DEBUG session keys: {$session_keys}'); window.CURRENT_USER = null; console.log('chat-init: CURRENT_USER = null');</script>";
}

// server-side warnings (debug)
if (!$css_fs) echo "<script>console.warn('chat-init: server-side CSS not found. Client URL: {$css_url}');</script>";
if (!$js_fs)  echo "<script>console.warn('chat-init: server-side JS not found. Client URL: {$js_url}');</script>";

// expose base path and include assets
$projectRootForJs = $projectRoot;
echo "<script>window.CHAT_BASE = '" . addslashes($projectRootForJs) . "';</script>";
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($css_url, ENT_QUOTES); ?>">
<script>console.log('chat-init: loading script <?php echo htmlspecialchars($js_url, ENT_QUOTES); ?>');</script>
<script src="<?php echo htmlspecialchars($js_url, ENT_QUOTES); ?>" defer></script>