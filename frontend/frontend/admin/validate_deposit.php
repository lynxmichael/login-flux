<?php
session_start();
require_once '../config/database.php';
require_once '../models/Wallet.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$wallet = new Wallet();
$transaction_id = $_GET['id'] ?? null;

if ($transaction_id) {
    $pdo = (new Database())->getConnection();
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();

    if ($transaction) {
        $wallet->updateBalance($transaction['user_id'], $transaction['amount']);
        $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE id = ?")->execute([$transaction_id]);
        $_SESSION['message'] = "Dépôt validé avec succès ✅";
    }
}
header('Location: admin_dashboard.php');
exit;
?>
