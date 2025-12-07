<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../database.php'; // <-- uyarlayın

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// PDO hazır değilse burayı uyarlayın
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

$input = json_decode(file_get_contents('php://input'), true);
$message = isset($input['message']) ? trim($input['message']) : '';

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

$user_id = intval($_SESSION['user_id']);
$username = $_SESSION['username'];

try {
    $stmt = $pdo->prepare('INSERT INTO chat_messages (user_id, username, message, created_at) VALUES (:user_id, :username, :message, NOW())');
    $stmt->execute([':user_id'=>$user_id, ':username'=>$username, ':message'=>$message]);
    $id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT id, user_id, username, message, created_at FROM chat_messages WHERE id = :id LIMIT 1');
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'message' => $row]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Insert failed']);
}