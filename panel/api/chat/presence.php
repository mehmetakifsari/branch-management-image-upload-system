<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../database.php'; // <-- uyarlayın

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// PDO uyarlaması
if (!isset($pdo)) {
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=your_db;charset=utf8mb4','dbuser','dbpass',[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB connection failed']);
        exit;
    }
}

$user_id = intval($_SESSION['user_id']);
$now = (new DateTime())->format('Y-m-d H:i:s');

try {
    // users tablosunda last_activity sütununun olduğunu varsayıyoruz
    $stmt = $pdo->prepare('UPDATE users SET last_activity = :now WHERE id = :id');
    $stmt->execute([':now'=>$now, ':id'=>$user_id]);
} catch (Exception $e) {
    // non-fatal
}

if (isset($_GET['list']) && intval($_GET['list']) === 1) {
    $threshold_seconds = 60;
    try {
        $stmt = $pdo->prepare('SELECT id AS user_id, username, last_activity FROM users ORDER BY username LIMIT 500');
        $stmt->execute();
        $users = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $last = $row['last_activity'];
            $online = false;
            if ($last !== null) {
                $diff = time() - strtotime($last);
                if ($diff <= $threshold_seconds) $online = true;
            }
            $users[] = [
                'user_id' => (int)$row['user_id'],
                'username' => $row['username'],
                'last_activity' => $row['last_activity'],
                'online' => $online,
            ];
        }
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Query failed']);
        exit;
    }
}

echo json_encode(['success' => true]);