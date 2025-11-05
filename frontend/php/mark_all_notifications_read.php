<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    
    // Marquer toutes les notifications comme lues
    $update_stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    if ($update_stmt->execute([$user_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur de mise à jour']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Requête invalide']);
}
?>