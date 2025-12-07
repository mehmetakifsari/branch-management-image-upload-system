<?php
header('Content-Type: application/json; charset=utf-8');
// get_messages.php (manual-db.php based)

if (session_status() === PHP_SESSION_NONE) session_start();

// Use manual DB (test) - file should be panel/api/chat/manual-db.php
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

// Optional: allow unauthenticated read of messages.
// If you want to restrict to logged-in users, add session-based auth check here.

$since_id = isset($_GET['since_id']) ? intval($_GET['since_id']) : 0;
$limit = isset($_GET['limit']) ? min(200, intval($_GET['limit'])) : 50;

try {
    $rows = [];
    if ($pdo instanceof PDO) {
        if ($since_id > 0) {
            $stmt = $pdo->prepare('SELECT id, user_id, username, message, created_at FROM chat_messages WHERE id > :since_id ORDER BY id ASC LIMIT :limit');
            $stmt->bindValue(':since_id', $since_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare('SELECT id, user_id, username, message, created_at FROM chat_messages ORDER BY id DESC LIMIT :limit');
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $rows = array_reverse($rows);
        }
    } elseif ($conn instanceof mysqli) {
        if ($since_id > 0) {
            $res = $conn->query("SELECT id, user_id, username, message, created_at FROM chat_messages WHERE id > {$since_id} ORDER BY id ASC LIMIT {$limit}");
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } else {
            $res = $conn->query("SELECT id, user_id, username, message, created_at FROM chat_messages ORDER BY id DESC LIMIT {$limit}");
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $rows = array_reverse($rows);
        }
    } else {
        throw new Exception('No DB connection available');
    }

    echo json_encode(['success' => true, 'messages' => $rows]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query failed', 'detail' => $e->getMessage()]);
    exit;
}