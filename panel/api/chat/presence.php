<?php
header('Content-Type: application/json; charset=utf-8');
// presence.php (manual-db.php based)

if (session_status() === PHP_SESSION_NONE) session_start();

// Use manual DB (test)
if (is_file(__DIR__ . '/manual-db.php')) {
    require_once __DIR__ . '/manual-db.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB helper missing', 'detail' => 'manual-db.php not found']);
    exit;
}

$pdo = $GLOBALS['pdo'] ?? null;
$conn = $GLOBALS['conn'] ?? null;
$dbError = $GLOBALS['MANUAL_DB_ERROR'] ?? null;

if (!empty($dbError)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed', 'detail' => $dbError]);
    exit;
}

// --- detect logged user (your session stores 'user' array per earlier debug) ---
$user = null;
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $ua = $_SESSION['user'];
    $uid = null; $uname = null;
    foreach (['id','user_id','uid','userid'] as $k) if ($uid === null && isset($ua[$k]) && is_numeric($ua[$k])) $uid = (int)$ua[$k];
    foreach (['username','user_name','name','email'] as $k) if ($uname === null && isset($ua[$k]) && is_string($ua[$k]) && $ua[$k] !== '') $uname = $ua[$k];
    if ($uid !== null && $uname !== null) $user = ['user_id' => $uid, 'username' => $uname];
}

// fallback: top-level session keys
if (!$user) {
    $uid = null; $uname = null;
    foreach (['user_id','id','uid','userid'] as $k) if (isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) { $uid = (int)$_SESSION[$k]; break; }
    foreach (['username','user_name','name','email'] as $k) if (isset($_SESSION[$k]) && is_string($_SESSION[$k]) && $_SESSION[$k] !== '') { $uname = $_SESSION[$k]; break; }
    if (!empty($uid) && !empty($uname)) $user = ['user_id' => $uid, 'username' => $uname];
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = (int)$user['user_id'];
$now = (new DateTime())->format('Y-m-d H:i:s');

// update last_activity if users table exists
try {
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare('UPDATE users SET last_activity = :now WHERE id = :id');
        $stmt->execute([':now' => $now, ':id' => $user_id]);
    } elseif ($conn instanceof mysqli) {
        $stmt = $conn->prepare('UPDATE users SET last_activity = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('si', $now, $user_id);
            $stmt->execute();
        }
    }
} catch (Exception $e) {
    // non-fatal
}

// if list requested, return users with online flag
if (isset($_GET['list']) && intval($_GET['list']) === 1) {
    $threshold_seconds = 60;
    try {
        $users = [];
        if ($pdo instanceof PDO) {
            $stmt = $pdo->prepare('SELECT id AS user_id, username, last_activity FROM users ORDER BY username LIMIT 500');
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $last = $row['last_activity'];
                $online = false;
                if ($last !== null) {
                    $diff = time() - strtotime($last);
                    if ($diff <= $threshold_seconds) $online = true;
                }
                $users[] = ['user_id' => (int)$row['user_id'], 'username' => $row['username'], 'last_activity' => $row['last_activity'], 'online' => $online];
            }
        } elseif ($conn instanceof mysqli) {
            $res = $conn->query('SELECT id AS user_id, username, last_activity FROM users ORDER BY username LIMIT 500');
            while ($row = $res->fetch_assoc()) {
                $last = $row['last_activity'];
                $online = false;
                if ($last !== null) {
                    $diff = time() - strtotime($last);
                    if ($diff <= $threshold_seconds) $online = true;
                }
                $users[] = ['user_id' => (int)$row['user_id'], 'username' => $row['username'], 'last_activity' => $row['last_activity'], 'online' => $online];
            }
        }
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Query failed', 'detail' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => true]);