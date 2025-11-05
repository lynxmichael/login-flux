<?php
session_start();
header('Content-Type: application/json');

// Debug - À retirer en production
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../models/Wallet.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    $wallet = new Wallet();
    
    // Utiliser $_POST directement au lieu de json_decode
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];

    // S'assurer que le wallet existe pour toutes les actions
    if (!in_array($action, ['create_wallet'])) {
        $walletCheck = $wallet->ensureWalletExists($user_id);
        if (!$walletCheck['success']) {
            echo json_encode(['success' => false, 'message' => 'Erreur wallet: ' . $walletCheck['message']]);
            exit;
        }
    }

    switch ($action) {
        case 'get_wallet_data':
            $balance_result = $wallet->getBalance($user_id);
            $transactions_result = $wallet->getTransactionHistory($user_id, 10, 0);
            
            if (!$balance_result['success']) {
                echo json_encode(['success' => false, 'message' => 'Erreur récupération solde: ' . $balance_result['message']]);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'wallet_balance' => $balance_result['balance'],
                'currency' => $balance_result['currency'],
                'transactions' => $transactions_result['success'] ? $transactions_result['transactions'] : []
            ]);
            break;

        case 'buy_stock':
            $stock_symbol = $_POST['stock_symbol'] ?? '';
            $quantity = intval($_POST['quantity'] ?? 0);
            $price = floatval($_POST['price'] ?? 0);
            
            if (empty($stock_symbol) || $quantity <= 0 || $price <= 0) {
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                break;
            }
            
            $result = $wallet->executeBuyOrder($user_id, $stock_symbol, $quantity, $price);
            echo json_encode($result);
            break;

        case 'sell_stock':
            $stock_symbol = $_POST['stock_symbol'] ?? '';
            $quantity = intval($_POST['quantity'] ?? 0);
            $price = floatval($_POST['price'] ?? 0);
            
            if (empty($stock_symbol) || $quantity <= 0 || $price <= 0) {
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                break;
            }
            
            $result = $wallet->executeSellOrder($user_id, $stock_symbol, $quantity, $price);
            echo json_encode($result);
            break;

        case 'get_balance':
            $result = $wallet->getBalance($user_id);
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'balance' => $result['balance'],
                    'currency' => $result['currency']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
            break;

        case 'validate_session':
            $balance_result = $wallet->getBalance($user_id);
            $balance = $balance_result['success'] ? $balance_result['balance'] : 0;
            
            echo json_encode([
                'success' => true, 
                'user_id' => $user_id,
                'wallet_balance' => $balance
            ]);
            break;

        case 'get_user_data':
            $user_stmt = $pdo->prepare("SELECT id, full_name as name, email, created_at FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch();
            
            $balance_result = $wallet->getBalance($user_id);
            
            if (!$balance_result['success']) {
                echo json_encode(['success' => false, 'message' => 'Erreur récupération solde: ' . $balance_result['message']]);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'wallet_balance' => $balance_result['balance'],
                    'currency' => $balance_result['currency'],
                    'created_at' => $user['created_at']
                ]
            ]);
            break;

        case 'create_wallet':
            $result = $wallet->ensureWalletExists($user_id);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue: ' . $action]);
    }
    
} catch (Exception $e) {
    error_log("Wallet API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur: ' . $e->getMessage()]);
}
exit;
?>