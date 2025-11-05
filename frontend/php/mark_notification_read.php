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
    
    $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
    
    if ($notification_id <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'ID de notification invalide'
        ]);
        exit;
    }
    
    // Marquer comme lu
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notification_id, $user_id]);
    
    // Compter les notifications non lues restantes
    $stmt_count = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt_count->execute([$user_id]);
    $unread_count = $stmt_count->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>