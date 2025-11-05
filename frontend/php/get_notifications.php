<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Non authentifié'
    ]);
    exit;
}

require_once '../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Récupérer les notifications non lues
    $stmt = $pdo->prepare("
        SELECT id, message, type, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter les non lues
    $stmt_count = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt_count->execute([$user_id]);
    $unread_count = $stmt_count->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>