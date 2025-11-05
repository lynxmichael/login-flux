<?php
require_once '../config/database.php';

function createNotification($user_id, $message, $type = 'info') {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, message, type) 
            VALUES (?, ?, ?)
        ");
        
        return $stmt->execute([$user_id, $message, $type]);
    } catch (Exception $e) {
        // Log l'erreur mais ne pas interrompre le processus principal
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

// Fonction pour récupérer les notifications non lues
function getUnreadNotifications($user_id) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur récupération notifications: " . $e->getMessage());
        return [];
    }
}
?>