<?php
session_start();
require_once 'config/database.php';
require_once 'notification_functions.php'; // Ajout pour les notifications

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Utilisateur non connecté']));
}

// Vérifier la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Méthode non autorisée']));
}

// Récupérer et valider les données
$orderType = $_POST['orderType'] ?? '';
$stockName = $_POST['stockName'] ?? '';
$quantity = intval($_POST['quantity'] ?? 0);
$price = floatval($_POST['price'] ?? 0);
$operationDate = $_POST['operationDate'] ?? date('Y-m-d H:i:s');
$user_id = $_SESSION['user_id'];

// Validation des données
if (empty($orderType) || !in_array($orderType, ['buy', 'sell'])) {
    die(json_encode(['success' => false, 'message' => 'Type d\'ordre invalide']));
}

if (empty($stockName)) {
    die(json_encode(['success' => false, 'message' => 'Nom d\'action invalide']));
}

if ($quantity <= 0) {
    die(json_encode(['success' => false, 'message' => 'Quantité invalide']));
}

if ($price <= 0) {
    die(json_encode(['success' => false, 'message' => 'Prix invalide']));
}

try {
    // Connexion à la base de données
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Commencer une transaction
    $pdo->beginTransaction();
    
    if ($orderType === 'buy') {
        // Logique pour l'achat
        $totalCost = $quantity * $price;
        $fees = $totalCost * 0.001; // 0.1% de frais
        $totalWithFees = $totalCost + $fees;
        
        // Vérifier le solde du wallet
        $wallet_stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
        $wallet_stmt->execute([$user_id]);
        $wallet = $wallet_stmt->fetch();
        
        if (!$wallet || $wallet['balance'] < $totalWithFees) {
            throw new Exception('Solde insuffisant pour cet achat');
        }
        
        // Déduire le montant du wallet
        $update_wallet = $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
        $update_wallet->execute([$totalWithFees, $user_id]);
        
        // Enregistrer la transaction
        $insert_transaction = $pdo->prepare("
            INSERT INTO transactions (user_id, type, stock_symbol, quantity, price, amount, description, status) 
            VALUES (?, 'buy', ?, ?, ?, ?, ?, 'completed')
        ");
        $transaction_desc = "Achat de $quantity actions $stockName à " . number_format($price, 2) . " FCFA";
        $insert_transaction->execute([$user_id, $stockName, $quantity, $price, $totalWithFees, $transaction_desc]);
        
        // Enregistrer l'ordre d'achat
        $insert_order = $pdo->prepare("
            INSERT INTO orders (user_id, stock_symbol, order_type, quantity, price, fees, total_amount, status, operation_date) 
            VALUES (?, ?, 'buy', ?, ?, ?, ?, 'completed', ?)
        ");
        $insert_order->execute([$user_id, $stockName, $quantity, $price, $fees, $totalWithFees, $operationDate]);
        
        // Mettre à jour ou insérer dans le portfolio
        $update_portfolio = $pdo->prepare("
            INSERT INTO user_portfolio (user_id, stock_symbol, quantity, average_price) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            quantity = quantity + VALUES(quantity),
            average_price = ((average_price * quantity) + (VALUES(average_price) * VALUES(quantity))) / (quantity + VALUES(quantity))
        ");
        $update_portfolio->execute([$user_id, $stockName, $quantity, $price]);
        
        // NOTIFICATION POUR ACHAT RÉUSSI
        $notification_message = "🟢 Achat exécuté: $quantity actions $stockName à " . number_format($price, 0, ',', ' ') . " FCFA";
        createNotification($user_id, $notification_message, 'success');
        
    } else {
        // Logique pour la vente
        // Vérifier si l'utilisateur possède suffisamment d'actions
        $portfolio_stmt = $pdo->prepare("SELECT quantity, average_price FROM user_portfolio WHERE user_id = ? AND stock_symbol = ? FOR UPDATE");
        $portfolio_stmt->execute([$user_id, $stockName]);
        $portfolio = $portfolio_stmt->fetch();
        
        if (!$portfolio || $portfolio['quantity'] < $quantity) {
            throw new Exception('Quantité d\'actions insuffisante pour cette vente');
        }
        
        $totalRevenue = $quantity * $price;
        $fees = $totalRevenue * 0.001; // 0.1% de frais
        $totalAfterFees = $totalRevenue - $fees;
        
        // Ajouter le montant au wallet
        $update_wallet = $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $update_wallet->execute([$totalAfterFees, $user_id]);
        
        // Enregistrer la transaction
        $insert_transaction = $pdo->prepare("
            INSERT INTO transactions (user_id, type, stock_symbol, quantity, price, amount, description, status) 
            VALUES (?, 'sell', ?, ?, ?, ?, ?, 'completed')
        ");
        $transaction_desc = "Vente de $quantity actions $stockName à " . number_format($price, 2) . " FCFA";
        $insert_transaction->execute([$user_id, $stockName, $quantity, $price, $totalAfterFees, $transaction_desc]);
        
        // Enregistrer l'ordre de vente
        $insert_order = $pdo->prepare("
            INSERT INTO orders (user_id, stock_symbol, order_type, quantity, price, fees, total_amount, status, operation_date) 
            VALUES (?, ?, 'sell', ?, ?, ?, ?, 'completed', ?)
        ");
        $insert_order->execute([$user_id, $stockName, $quantity, $price, $fees, $totalAfterFees, $operationDate]);
        
        // Mettre à jour le portfolio
        $new_quantity = $portfolio['quantity'] - $quantity;
        
        if ($new_quantity > 0) {
            // Mettre à jour la quantité
            $update_portfolio = $pdo->prepare("
                UPDATE user_portfolio SET quantity = ? WHERE user_id = ? AND stock_symbol = ?
            ");
            $update_portfolio->execute([$new_quantity, $user_id, $stockName]);
        } else {
            // Supprimer l'entrée si quantité = 0
            $delete_portfolio = $pdo->prepare("
                DELETE FROM user_portfolio WHERE user_id = ? AND stock_symbol = ?
            ");
            $delete_portfolio->execute([$user_id, $stockName]);
        }
        
        // NOTIFICATION POUR VENTE RÉUSSIE
        $notification_message = "🔴 Vente exécutée: $quantity actions $stockName à " . number_format($price, 0, ',', ' ') . " FCFA";
        createNotification($user_id, $notification_message, 'success');
        
        // Notification supplémentaire si profit/pertes
        $purchase_cost = $portfolio['average_price'] * $quantity;
        $profit_loss = $totalAfterFees - $purchase_cost;
        
        if ($profit_loss > 0) {
            $profit_notification = "💰 Profit: +" . number_format($profit_loss, 0, ',', ' ') . " FCFA sur $stockName";
            createNotification($user_id, $profit_notification, 'info');
        } elseif ($profit_loss < 0) {
            $loss_notification = "📉 Perte: " . number_format($profit_loss, 0, ',', ' ') . " FCFA sur $stockName";
            createNotification($user_id, $loss_notification, 'warning');
        }
    }
    
    // Récupérer le nouveau solde
    $new_balance_stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $new_balance_stmt->execute([$user_id]);
    $new_balance = $new_balance_stmt->fetch()['balance'];
    
    // Valider la transaction
    $pdo->commit();
    
    // Réponse JSON
    echo json_encode([
        'success' => true, 
        'message' => 'Ordre exécuté avec succès',
        'new_balance' => $new_balance,
        'order_type' => $orderType,
        'stock' => $stockName,
        'quantity' => $quantity,
        'price' => $price
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // NOTIFICATION D'ERREUR
    if (isset($user_id)) {
        $error_message = "❌ Échec de l'ordre: " . $e->getMessage();
        createNotification($user_id, $error_message, 'error');
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de l\'exécution de l\'ordre: ' . $e->getMessage()
    ]);
}
?>