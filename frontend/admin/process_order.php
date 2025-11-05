<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['action'])) {
    header('Location: trading_orders.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$order_id = intval($_GET['id']);
$action = $_GET['action'];

// Récupérer les détails de l'ordre
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name, u.email, w.balance as user_balance
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN wallets w ON o.user_id = w.user_id
    WHERE o.id = ?
");

$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: trading_orders.php?error=order_not_found');
    exit;
}

// Vérifier si l'ordre est expiré
$is_expired = strtotime($order['validity_date']) < time() && $order['status'] === 'pending';

$success = false;
$message = '';

switch ($action) {
    case 'execute':
        if ($order['status'] === 'pending' && !$is_expired) {
            try {
                $pdo->beginTransaction();
                
                // Vérifier les fonds pour un ordre d'achat
                if ($order['order_type'] === 'buy' && $order['user_balance'] < $order['total_amount']) {
                    throw new Exception("Solde insuffisant pour exécuter l'ordre d'achat");
                }
                
                // Vérifier les actions disponibles pour un ordre de vente
                if ($order['order_type'] === 'sell') {
                    $stock_check = $pdo->prepare("SELECT quantity FROM user_stocks WHERE user_id = ? AND stock_symbol = ?");
                    $stock_check->execute([$order['user_id'], $order['stock_symbol']]);
                    $stock_data = $stock_check->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$stock_data || $stock_data['quantity'] < $order['quantity']) {
                        throw new Exception("Quantité d'actions insuffisante pour exécuter l'ordre de vente");
                    }
                }
                
                // Mettre à jour le statut de l'ordre
                $update_stmt = $pdo->prepare("
                    UPDATE orders 
                    SET status = 'executed', executed_at = NOW() 
                    WHERE id = ?
                ");
                $update_stmt->execute([$order_id]);
                
                // Créer une transaction correspondante
                $transaction_type = $order['order_type'];
                $amount = $order['order_type'] === 'buy' ? -$order['total_amount'] : $order['total_amount'];
                
                // Récupérer le wallet_id
                $wallet_stmt = $pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
                $wallet_stmt->execute([$order['user_id']]);
                $wallet = $wallet_stmt->fetch(PDO::FETCH_ASSOC);
                
                $transaction_stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, wallet_id, type, stock_symbol, quantity, price, amount, fees, status, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'completed', ?)
                ");
                
                $description = $order['order_type'] === 'buy' 
                    ? "Achat de {$order['quantity']} actions {$order['stock_symbol']} via ordre #{$order_id}"
                    : "Vente de {$order['quantity']} actions {$order['stock_symbol']} via ordre #{$order_id}";
                
                $transaction_stmt->execute([
                    $order['user_id'],
                    $wallet['id'],
                    $transaction_type,
                    $order['stock_symbol'],
                    $order['quantity'],
                    $order['price'],
                    $amount,
                    $description
                ]);
                
                // Mettre à jour le portefeuille d'actions
                if ($order['order_type'] === 'buy') {
                    // Ajouter ou mettre à jour les actions dans user_stocks
                    $stock_upsert = $pdo->prepare("
                        INSERT INTO user_stocks (user_id, stock_symbol, quantity, average_buy_price, total_invested, current_value, unrealized_pnl)
                        VALUES (?, ?, ?, ?, ?, ?, 0)
                        ON DUPLICATE KEY UPDATE 
                            quantity = quantity + VALUES(quantity),
                            total_invested = total_invested + VALUES(total_invested),
                            average_buy_price = (total_invested + VALUES(total_invested)) / (quantity + VALUES(quantity)),
                            current_value = (quantity + VALUES(quantity)) * ?
                    ");
                    
                    $current_price = $order['price']; // Utiliser le prix d'exécution
                    $stock_upsert->execute([
                        $order['user_id'],
                        $order['stock_symbol'],
                        $order['quantity'],
                        $order['price'],
                        $order['total_amount'],
                        $order['total_amount'],
                        $current_price
                    ]);
                } else {
                    // Vente - mettre à jour la quantité d'actions
                    $stock_update = $pdo->prepare("
                        UPDATE user_stocks 
                        SET quantity = quantity - ?,
                            current_value = (quantity - ?) * ?
                        WHERE user_id = ? AND stock_symbol = ?
                    ");
                    
                    $current_price = $order['price']; // Utiliser le prix d'exécution
                    $stock_update->execute([
                        $order['quantity'],
                        $order['quantity'],
                        $current_price,
                        $order['user_id'],
                        $order['stock_symbol']
                    ]);
                }
                
                // Mettre à jour le solde du portefeuille
                $wallet_update = $pdo->prepare("
                    UPDATE wallets 
                    SET balance = balance + ?,
                        updated_at = NOW()
                    WHERE user_id = ?
                ");
                
                $wallet_amount = $order['order_type'] === 'buy' ? -$order['total_amount'] : $order['total_amount'];
                $wallet_update->execute([$wallet_amount, $order['user_id']]);
                
                $pdo->commit();
                
                // Logger l'action
                $log_stmt = $pdo->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->execute(['info', 'orders', "Ordre #$order_id exécuté avec succès", $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
                
                $success = true;
                $message = "Ordre exécuté avec succès";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                
                // Logger l'erreur
                $log_stmt = $pdo->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->execute(['error', 'orders', "Erreur exécution ordre #$order_id: " . $e->getMessage(), $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
                
                $success = false;
                $message = "Erreur lors de l'exécution: " . $e->getMessage();
            }
        } else {
            $success = false;
            $message = $is_expired ? "Impossible d'exécuter un ordre expiré" : "Ordre non disponible pour exécution";
        }
        break;
        
    case 'cancel':
        if ($order['status'] === 'pending') {
            $update_stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $update_stmt->execute([$order_id]);
            
            // Logger l'action
            $log_stmt = $pdo->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute(['info', 'orders', "Ordre #$order_id annulé", $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
            
            $success = true;
            $message = "Ordre annulé avec succès";
        } else {
            $success = false;
            $message = "Impossible d'annuler un ordre " . $order['status'];
        }
        break;
        
    case 'expire':
        if ($order['status'] === 'pending') {
            $update_stmt = $pdo->prepare("UPDATE orders SET status = 'expired' WHERE id = ?");
            $update_stmt->execute([$order_id]);
            
            // Logger l'action
            $log_stmt = $pdo->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute(['info', 'orders', "Ordre #$order_id marqué comme expiré", $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
            
            $success = true;
            $message = "Ordre marqué comme expiré";
        } else {
            $success = false;
            $message = "Impossible de marquer comme expiré un ordre " . $order['status'];
        }
        break;
        
    default:
        $success = false;
        $message = "Action non reconnue";
        break;
}

// Redirection avec message
$redirect_url = "order_details.php?id=$order_id";
if ($success) {
    $redirect_url .= "&success=" . urlencode($action);
} else {
    $redirect_url .= "&error=" . urlencode($message);
}

header('Location: ' . $redirect_url);
exit;
?>