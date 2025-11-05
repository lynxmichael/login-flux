<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    
    // Vérifier l'utilisateur
    $user_stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    // Vérifier le wallet
    $wallet_stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ?");
    $wallet_stmt->execute([$user_id]);
    $wallet = $wallet_stmt->fetch();
    
    // Vérifier les transactions
    $tx_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions WHERE user_id = ?");
    $tx_stmt->execute([$user_id]);
    $tx_count = $tx_stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'wallet' => $wallet ?: 'NON EXISTANT',
        'transaction_count' => $tx_count,
        'session_user_id' => $_SESSION['user_id']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>