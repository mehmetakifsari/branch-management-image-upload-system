<?php
header('Content-Type: application/json; charset=utf-8');

// Send message endpoint (test-ready)
// Expects JSON body: { "message": "..." }
// Requires manual-db.php (test) or database.php (production) to provide $pdo or $conn.

if (session_status() === PHP_SESSION_NONE) session_start();

// --- DB include (test) ---
if (is_file(__DIR__ . '/manual-db.php')) {
    require_once __DIR__ . '/manual-db.php';
} else {
    // fallback to project-level database.php if exists
    if (is_file(__DIR__ . '/../../../database.php')) {
        require_once __DIR__ . '/../../../database.php';
    } else {
        // try config-based inline (last resort)
        @include_once __DIR__ . '/../../../config.php';
    }
}

$pdo = $GLOBALS['pdo'] ?? null;
$conn = $GLOBALS['conn'] ?? null;
$dbError = $GLOBALS['MANUAL_DB_ERROR'] ?? ($GLOBALS['CHAT_DB_ERROR'] ?? null);

if (!empty($dbError)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed', 'detail' => $dbError]);
    exit;
}

// --- Debug logging (temporary) ---
@file_put_contents(__DIR__ . '/debug_send.log', date('c') . " REQUEST_BODY:" . file_get_contents('php://input') . " COOKIES:" . json_encode($_COOKIE) . " SESSION_KEYS:" . json_encode(array_keys($_SESSION ?? [])) . "\n", FILE_APPEND);

// --- Authentication: try to detect user from session ---
// The app stores user info in $_SESSION['user'] (array) per your debug output.
$user = null;
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $ua = $_SESSION['user'];
    $uid = null; $uname = null;
    // common keys
    foreach (['id','user_id','uid','userid'] as $k) {
        if ($uid === null && isset($ua[$k]) && is_numeric($ua[$k])) $uid = (int)$ua[$k];
    }
    foreach (['username','user_name','name','email'] as $k) {
        if ($uname === null && isset($ua[$k]) && is_string($ua[$k]) && $ua[$k] !== '') $uname = $ua[$k];
    }
    if ($uid !== null && $uname !== null) $user = ['user_id' => $uid, 'username' => $uname];
}

// fallback: top-level session keys (rare)
if (!$user) {
    foreach (['user_id','id','uid','userid'] as $k) {
        if (isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) {
            $uid = (int)$_SESSION[$k];
            break;
        }
    }
    foreach (['username','user_name','name','email'] as $k) {
        if (isset($_SESSION[$k]) && is_string($_SESSION[$k]) && $_SESSION[$k] !== '') {
            $uname = $_SESSION[$k];
            break;
        }
    }
    if (!empty($uid) && !empty($uname)) $user = ['user_id' => $uid, 'username' => $uname];
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// --- Read request body ---
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

// If client sends form-encoded, try fallback (not expected)
if ($input === null) {
    // try parse POST param 'message'
    if (isset($_POST['message'])) {
        $message = trim((string)$_POST['message']);
    } else {
        $message = '';
    }
} else {
    $message = isset($input['message']) ? trim((string)$input['message']) : '';
}

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty message']);
    exit;
}

if (mb_strlen($message) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message too long']);
    exit;
}

// Minimal server-side sanitization: trim and normalize newlines (store raw otherwise)
$message_to_store = $message;
$user_id = (int)$user['user_id'];
$username = (string)$user['username'];

try {
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare('INSERT INTO chat_messages (user_id, username, message, created_at) VALUES (:user_id, :username, :message, NOW())');
        $stmt->execute([':user_id' => $user_id, ':username' => $username, ':message' => $message_to_store]);
        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT id, user_id, username, message, created_at FROM chat_messages WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($conn instanceof mysqli) {
        $stmt = $conn->prepare('INSERT INTO chat_messages (user_id, username, message, created_at) VALUES (?, ?, ?, NOW())');
        if ($stmt === false) throw new Exception('MySQL prepare failed: ' . $conn->error);
        $stmt->bind_param('iss', $user_id, $username, $message_to_store);
        $stmt->execute();
        $id = $conn->insert_id;
        $res = $conn->query("SELECT id, user_id, username, message, created_at FROM chat_messages WHERE id = {$id} LIMIT 1");
        $row = $res ? $res->fetch_assoc() : null;
    } else {
        throw new Exception('No DB connection available');
    }

    if (!$row) {
        throw new Exception('Insert succeeded but fetching row failed');
    }

    // Return the inserted message
    echo json_encode(['success' => true, 'message' => $row]);
    exit;
} catch (Exception $e) {
    // Log server error for debugging
    @file_put_contents(__DIR__ . '/debug_send.log', date('c') . " ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Insert failed', 'detail' => $e->getMessage()]);
    exit;
}