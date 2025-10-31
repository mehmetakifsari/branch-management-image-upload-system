<?php
// inc/auth.php
// Güvenli oturum başlatma ve yetkilendirme yardımcıları.
// Oturum inaktivite süresi (saniye): 30 dakika = 1800s
define('INACTIVITY_TIMEOUT', 1800);

// Load config (base_url için)
$config = null;
$configPaths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
];
foreach ($configPaths as $p) {
    if (file_exists($p)) {
        $tmp = include $p;
        if (is_array($tmp)) { $config = $tmp; break; }
    }
}

// base path for cookie; fall back to '/'
$basePath = '/';
if (is_array($config) && isset($config['base_url'])) {
    $b = rtrim($config['base_url'], '/');
    $basePath = ($b === '') ? '/' : $b;
}

// Start session with secure parameters (only if not started)
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $basePath,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Include database helper (db_connect function)
$dbFile = __DIR__ . '/../database.php';
if (file_exists($dbFile)) {
    require_once $dbFile;
}

// CSRF helpers
function csrf_token() {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
}
function verify_csrf($token) {
    return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], (string)$token);
}

// Current user (returns array or null)
function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_admin() {
    $u = current_user();
    return $u && !empty($u['is_admin']);
}

// Update last activity time (call on each authenticated request)
function touch_last_activity() {
    $_SESSION['_last_activity'] = time();
}

// Check inactivity timeout; if timed out, destroy session and redirect to login with message
function check_session_timeout() {
    // if no last activity, set it and continue
    if (!isset($_SESSION['_last_activity'])) {
        $_SESSION['_last_activity'] = time();
        return;
    }
    $now = time();
    $last = (int)$_SESSION['_last_activity'];
    if (($now - $last) > INACTIVITY_TIMEOUT) {
        // timeout: destroy session and redirect to login with a message
        // store optional message in GET parameter
        logout_user();
        // Use relative redirect to panel/login.php
        $loginUrl = 'login.php';
        // If headers already sent, just exit
        if (!headers_sent()) {
            header('Location: ' . $loginUrl . '?msg=timeout');
            exit;
        } else {
            echo '<script>location.href="' . htmlspecialchars($loginUrl, ENT_QUOTES) . '?msg=timeout";</script>';
            exit;
        }
    }
    // otherwise update last activity timestamp
    $_SESSION['_last_activity'] = $now;
}

// Require login — if not logged in redirect to login page
function require_login() {
    // Only check timeout if user session exists
    if (current_user()) {
        check_session_timeout();
        // if still logged in, update last activity
        touch_last_activity();
    } else {
        // not logged in -> redirect
        $loginUrl = 'login.php';
        header('Location: ' . $loginUrl);
        exit;
    }
}

// Require admin
function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        echo "Yetkisiz erişim. Admin yetkisi gerekli.";
        exit;
    }
}

// Login function - validate password, set session, last_activity
function login_user($username, $password) {
    if (!function_exists('db_connect')) return false;
    $db = db_connect();
    $stmt = $db->prepare("SELECT id, username, fullname, password_hash, branch_code, is_admin FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    if (!$row) return false;
    if (!password_verify($password, $row['password_hash'])) return false;

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$row['id'],
        'username' => $row['username'],
        'fullname' => $row['fullname'],
        'branch_code' => $row['branch_code'],
        'is_admin' => (int)$row['is_admin']
    ];
    // set CSRF and last activity
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    $_SESSION['_last_activity'] = time();
    return true;
}

// Logout: destroy session
function logout_user() {
    // Clear session array
    $_SESSION = [];
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"] ?? '', $params["secure"], $params["httponly"]);
    }
    @session_destroy();
}