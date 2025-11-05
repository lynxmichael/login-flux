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
    
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';
    $user_id = $_SESSION['user_id'];

    switch ($action) {
        case 'get_wallet_data':
            $balance_result = $wallet->getBalance($user_id);
            $transactions_result = $wallet->getTransactionHistory($user_id, 10, 0);
            
            echo json_encode([
                'success' => true,
                'wallet_balance' => $balance_result['success'] ? $balance_result['balance'] : 0,
                'transactions' => $transactions_result['success'] ? $transactions_result['transactions'] : []
            ]);
            break;

        case 'buy_stock':
            $stock_symbol = $input['stock_symbol'] ?? '';
            $quantity = intval($input['quantity'] ?? 0);
            $price = floatval($input['price'] ?? 0);
            
            if (empty($stock_symbol) || $quantity <= 0 || $price <= 0) {
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                break;
            }
            
            $result = $wallet->executeBuyOrder($user_id, $stock_symbol, $quantity, $price);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'new_balance' => $result['new_balance'],
                    'transaction_id' => $result['transaction_id'],
                    'message' => 'Achat effectué avec succès'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
            break;

        case 'sell_stock':
            $stock_symbol = $input['stock_symbol'] ?? '';
            $quantity = intval($input['quantity'] ?? 0);
            $price = floatval($input['price'] ?? 0);
            
            if (empty($stock_symbol) || $quantity <= 0 || $price <= 0) {
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                break;
            }
            
            $result = $wallet->executeSellOrder($user_id, $stock_symbol, $quantity, $price);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'new_balance' => $result['new_balance'],
                    'transaction_id' => $result['transaction_id'],
                    'message' => 'Vente effectuée avec succès'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
            break;

        case 'get_portfolio':
            $result = $wallet->getUserPortfolio($user_id);
            echo json_encode($result);
            break;

        case 'validate_session':
            echo json_encode(['success' => true, 'user_id' => $user_id]);
            break;

        case 'get_user_data':
            $user_stmt = $pdo->prepare("SELECT id, full_name as name, email, created_at FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch();
            
            $balance_result = $wallet->getBalance($user_id);
            $wallet_balance = $balance_result['success'] ? $balance_result['balance'] : 0;
            
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'wallet_balance' => $wallet_balance,
                    'created_at' => $user['created_at']
                ]
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue: ' . $action]);
    }
    
} catch (Exception $e) {
    error_log("Wallet API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur: ' . $e->getMessage()]);
}
?>