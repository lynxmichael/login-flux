<?php
session_start();
require_once 'config/database.php';
require_once 'php/auth_check.php';
require_once 'php/TransactionManager.php';

// Nettoyer toute sortie précédente
while (ob_get_level()) ob_end_clean();

error_reporting(0);
ini_set('display_errors', 0);

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    ob_end_flush();
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    ob_end_flush();
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $amount = floatval($_POST['amount'] ?? 0);
    $operator = $_POST['operator'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $note = $_POST['note'] ?? '';

    // Validation
    $errors = [];
    
    if ($amount < 1000) {
        $errors[] = 'Le montant minimum est de 1000 FCFA';
    }
    
    if (!in_array($operator, ['orange', 'mtn', 'wave', 'moov'])) {
        $errors[] = 'Opérateur invalide';
    }
    
    if (!preg_match('/^(77|78|76|70|75)\d{7}$/', $phone)) {
        $errors[] = 'Numéro de téléphone invalide';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
        ob_end_flush();
        exit;
    }

    $database = new Database();
    $pdo = $database->getConnection();

    $wallet_stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
    $wallet_stmt->execute([$user_id]);
    $wallet = $wallet_stmt->fetch();

    if (!$wallet) {
        echo json_encode(['success' => false, 'error' => 'Wallet non trouvé']);
        ob_end_flush();
        exit;
    }

    $wallet_id = $wallet['id'];

    $transactionManager = new TransactionManager($pdo);
    $result = $transactionManager->transfer($user_id, $wallet_id, $amount, $operator, $phone, $note);

    ob_clean();
    echo json_encode($result);
    ob_end_flush();

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
    ob_end_flush();
}
?>