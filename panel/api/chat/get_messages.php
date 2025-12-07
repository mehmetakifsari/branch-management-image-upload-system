<?php
header('Content-Type: application/json; charset=utf-8');

/*
  DİKKAT: Projede DB bağlantısını sağlayan dosya database.php veya config.php olabilir.
  Aşağıdaki require_once yolunu repo yapınıza göre güncelleyin.
  Amaç: $pdo veya $conn ile PDO bağlantısı elde etmek.
*/
require_once __DIR__ . '/../../../database.php'; // <-- GEREKİRSE UYARLA

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    // İsteğe bağlı: giriş zorunlu yapmak istemezseniz bunu kaldırabilirsiniz
    // echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    // exit;
}

// Varsayılan DB handling: $pdo olarak PDO kullanılıyorsa devam eder.
// Eğer sizin projede $conn (mysqli) kullanılıyorsa burada PDO ile uyarlayın.
if (!isset($pdo)) {
    // Eğer proje PDO kullanmıyorsa, burayı repo'nuzun bağlantısına göre değiştirin.
    try {
        // Placeholder - değiştirin.
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=your_db;charset=utf8mb4','dbuser','dbpass',[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB connection failed']);
        exit;
    }
}

$since_id = isset($_GET['since_id']) ? intval($_GET['since_id']) : 0;
$limit = isset($_GET['limit']) ? min(200, intval($_GET['limit'])) : 50;

try {
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
    echo json_encode(['success' => true, 'messages' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query failed']);
}