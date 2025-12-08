<?php
// panel/includes/chat-init.php
// Fixed, robust version — safe to drop in place of the broken file.
// - starts session safely
// - detects project base (e.g. '/p1' or '')
// - detects CURRENT_USER from session in a tolerant way
// - exposes window.CHAT_BASE and window.CURRENT_USER using json_encode (safe escaping)
// - preloads audio if present and prints asset tags
// - avoid header() warnings by checking headers_sent() and providing a fallback meta tag

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// normalize URL path helper
function normalize_url_path($path) {
    $parts = [];
    foreach (explode('/', $path) as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') { array_pop($parts); continue; }
        $parts[] = $p;
    }
    return '/' . implode('/', $parts);
}

// Determine script directory and project root (one level up from includes)
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
$projectRoot = normalize_url_path($scriptDir . '/..');
if ($projectRoot === '/') $projectRoot = '';

// Asset URLs (client-facing)
$css_url = ($projectRoot === '') ? '/assets/css/chat-widget.css' : $projectRoot . '/assets/css/chat-widget.css';
$js_url  = ($projectRoot === '') ? '/assets/js/chat-widget.js'  : $projectRoot . '/assets/js/chat-widget.js';
$audio_prefetch = ($projectRoot === '') ? '/assets/sounds/notify.mp3' : $projectRoot . '/assets/sounds/notify.mp3';

// Server-side checks for presence of files (informational only)
$possibleRoots = [
    realpath(__DIR__ . '/../../'),
    realpath(__DIR__ . '/../../../'),
    rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'),
];
$css_fs = $js_fs = $audio_fs = false;
foreach ($possibleRoots as $root) {
    if (!$root) continue;
    $css_check = $root . '/assets/css/chat-widget.css';
    $js_check  = $root . '/assets/js/chat-widget.js';
    $audio_check = $root . '/assets/sounds/notify.mp3';
    if (!$css_fs && is_file($css_check)) $css_fs = $css_check;
    if (!$js_fs && is_file($js_check)) $js_fs = $js_check;
    if (!$audio_fs && is_file($audio_check)) $audio_fs = $audio_check;
}

// --- Robust session -> CURRENT_USER mapping ---
$session_keys = json_encode(array_keys($_SESSION ?? []));

$uid = null;
$uname = null;

$idCandidates = ['user_id','id','uid','userid','ID','member_id','admin_id'];
$nameCandidates = ['username','user_name','name','display_name','fullname','login','email'];

// top-level scan
foreach ($idCandidates as $k) {
    if ($uid === null && isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) $uid = (int)$_SESSION[$k];
}
foreach ($nameCandidates as $k) {
    if ($uname === null && isset($_SESSION[$k]) && is_string($_SESSION[$k]) && $_SESSION[$k] !== '') $uname = $_SESSION[$k];
}

// nested user array scan
if (($uid === null || $uname === null) && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $ua = $_SESSION['user'];
    foreach ($idCandidates as $k) {
        if ($uid === null && isset($ua[$k]) && is_numeric($ua[$k])) { $uid = (int)$ua[$k]; break; }
    }
    foreach ($nameCandidates as $k) {
        if ($uname === null && isset($ua[$k]) && is_string($ua[$k]) && $ua[$k] !== '') { $uname = $ua[$k]; break; }
    }
    // deeper nested arrays
    if (($uid === null || $uname === null)) {
        foreach ($ua as $val) {
            if (!is_array($val)) continue;
            foreach ($idCandidates as $k) if ($uid === null && isset($val[$k]) && is_numeric($val[$k])) $uid = (int)$val[$k];
            foreach ($nameCandidates as $k) if ($uname === null && isset($val[$k]) && is_string($val[$k]) && $val[$k] !== '') $uname = $val[$k];
            if ($uid !== null && $uname !== null) {
                // break out of the outer loop once we've found both values
                break;
            }
        }
    }
}

// Build a safe js object for CURRENT_USER (null if not set)
$currentUserJs = null;
if (!empty($uid) && !empty($uname)) {
    $currentUserJs = ['user_id' => (int)$uid, 'username' => (string)$uname];
}

// Attempt to set a small security header, but avoid "headers already sent" warnings
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN'); // small safety header (optional)
} else {
    // fallback: print a meta tag and a console warning so problems are visible in browser devtools
    echo '<!-- chat-init: headers already sent, skipped header() call -->' . "\n";
    echo '<meta http-equiv="X-Frame-Options" content="SAMEORIGIN">' . "\n";
    echo '<script>console.warn("chat-init: headers already sent; header(X-Frame-Options) skipped.");</script>' . "\n";
}

echo '<!-- chat-init: start -->' . "\n";
echo '<script>console.log("chat-init: session keys: ' . addslashes($session_keys) . '");</script>' . "\n";
echo '<script>window.CHAT_BASE = ' . json_encode($projectRoot) . ';</script>' . "\n";
echo '<script>window.CURRENT_USER = ' . json_encode($currentUserJs) . ';</script>' . "\n";

// Non-fatal warnings for missing server-side assets (printed to console)
if (!$css_fs) {
    echo '<script>console.warn("chat-init: server-side CSS not found. Client URL: ' . addslashes($css_url) . '");</script>' . "\n";
}
if (!$js_fs) {
    echo '<script>console.warn("chat-init: server-side JS not found. Client URL: ' . addslashes($js_url) . '");</script>' . "\n";
}
if (!$audio_fs) {
    echo '<script>console.warn("chat-init: server-side audio not found. Expected: ' . addslashes($audio_prefetch) . '");</script>' . "\n";
}

// Preload audio (non-blocking)
echo '<link rel="preload" href="' . htmlspecialchars($audio_prefetch, ENT_QUOTES) . '" as="audio" type="audio/mpeg">' . "\n";

// Output asset tags
echo '<link rel="stylesheet" href="' . htmlspecialchars($css_url, ENT_QUOTES) . '">' . "\n";
echo '<script>console.log("chat-init: loading script ' . addslashes($js_url) . '");</script>' . "\n";
echo '<script src="' . htmlspecialchars($js_url, ENT_QUOTES) . '" defer></script>' . "\n";
echo '<!-- chat-init: end -->' . "\n";

return;