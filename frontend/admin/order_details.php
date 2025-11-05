<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: trading_orders.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$order_id = intval($_GET['id']);

// Récupérer les détails de l'ordre
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        u.full_name,
        u.email,
        u.phone,
        u.created_at as user_created_at,
        w.balance as user_balance,
        w.currency
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

// Récupérer les informations sur l'action
$stock_info = $pdo->prepare("
    SELECT 
        us.quantity as owned_quantity,
        us.average_buy_price,
        us.current_value,
        us.unrealized_pnl
    FROM user_stocks us
    WHERE us.user_id = ? AND us.stock_symbol = ?
");

$stock_info->execute([$order['user_id'], $order['stock_symbol']]);
$stock_data = $stock_info->fetch(PDO::FETCH_ASSOC);

// Récupérer les ordres similaires pour contexte
$similar_orders = $pdo->prepare("
    SELECT 
        id,
        order_type,
        quantity,
        price,
        status,
        created_at
    FROM orders 
    WHERE user_id = ? AND stock_symbol = ? AND id != ?
    ORDER BY created_at DESC
    LIMIT 5
");

$similar_orders->execute([$order['user_id'], $order['stock_symbol'], $order_id]);
$similar_orders = $similar_orders->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le prix actuel du marché (simulé)
$market_price_stmt = $pdo->prepare("
    SELECT 
        price_per_share as last_price,
        transaction_date
    FROM user_transactions 
    WHERE stock_symbol = ? 
    ORDER BY transaction_date DESC 
    LIMIT 1
");

$market_price_stmt->execute([$order['stock_symbol']]);
$market_data = $market_price_stmt->fetch(PDO::FETCH_ASSOC);

// Calculer la différence avec le prix du marché
$market_price = $market_data['last_price'] ?? $order['price'];
$price_difference = $market_price - $order['price'];
$price_difference_percentage = $order['price'] > 0 ? ($price_difference / $order['price']) * 100 : 0;

// Vérifier si l'ordre est expiré
$is_expired = strtotime($order['validity_date']) < time() && $order['status'] === 'pending';

// Actions sur l'ordre
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'execute':
            if ($order['status'] === 'pending' && !$is_expired) {
                // Simuler l'exécution de l'ordre
                $update_stmt = $pdo->prepare("
                    UPDATE orders 
                    SET status = 'executed', executed_at = NOW() 
                    WHERE id = ?
                ");
                $update_stmt->execute([$order_id]);
                
                // Créer une transaction correspondante
                $transaction_type = $order['order_type'] === 'buy' ? 'buy' : 'sell';
                $amount = $order['order_type'] === 'buy' ? -$order['total_amount'] : $order['total_amount'];
                
                $transaction_stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, wallet_id, type, stock_symbol, quantity, price, amount, fees, status, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'completed', ?)
                ");
                
                $description = $order['order_type'] === 'buy' 
                    ? "Achat de {$order['quantity']} actions {$order['stock_symbol']} via ordre #{$order_id}"
                    : "Vente de {$order['quantity']} actions {$order['stock_symbol']} via ordre #{$order_id}";
                
                $transaction_stmt->execute([
                    $order['user_id'],
                    $order['user_id'], // Simplification - normalement récupérer wallet_id
                    $transaction_type,
                    $order['stock_symbol'],
                    $order['quantity'],
                    $order['price'],
                    $amount,
                    $description
                ]);
                
                // Logger l'action
                $log_stmt = $pdo->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->execute(['info', 'orders', "Ordre #$order_id exécuté par l'administrateur", $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
                
                header('Location: order_details.php?id=' . $order_id . '&success=executed');
                exit;
            }
            break;
            
        case 'cancel':
            if ($order['status'] === 'pending') {
                $update_stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
                $update_stmt->execute([$order_id]);
                
                // Logger l'action
                $log_stmt = $pdo->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->execute(['info', 'orders', "Ordre #$order_id annulé par l'administrateur", $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
                
                header('Location: order_details.php?id=' . $order_id . '&success=cancelled');
                exit;
            }
            break;
            
        case 'expire':
            if ($order['status'] === 'pending') {
                $update_stmt = $pdo->prepare("UPDATE orders SET status = 'expired' WHERE id = ?");
                $update_stmt->execute([$order_id]);
                
                // Logger l'action
                $log_stmt = $pdo->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->execute(['info', 'orders', "Ordre #$order_id marqué comme expiré", $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
                
                header('Location: order_details.php?id=' . $order_id . '&success=expired');
                exit;
            }
            break;
    }
}

// Recharger les données après modification
if (isset($_GET['success'])) {
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_expired = strtotime($order['validity_date']) < time() && $order['status'] === 'pending';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Détails Ordre #<?php echo $order_id; ?> | FLUX TRADING</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --primary: #6366f1;
    --primary-hover: #4f46e5;
    --surface: #0f0f23;
    --card: #1a1b2f;
    --card-border: #2a2b45;
    --text: #ffffff;
    --text-secondary: #94a3b8;
    --error: #ef4444;
    --success: #10b981;
    --warning: #f59e0b;
    --info: #3b82f6;
    --gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: var(--surface);
    color: var(--text);
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
}

.header {
    background: var(--card);
    border-bottom: 1px solid var(--card-border);
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
}

.logo {
    font-size: 1.5rem;
    font-weight: 700;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    color: var(--text-secondary);
}

.nav-links {
    display: flex;
    gap: 1rem;
    margin-left: 2rem;
}

.nav-link {
    color: var(--text-secondary);
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.nav-link:hover, .nav-link.active {
    background: var(--gradient);
    color: white;
}

.logout {
    color: var(--text-secondary);
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.logout:hover {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
}

.container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 2rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.breadcrumb a {
    color: var(--text-secondary);
    text-decoration: none;
    transition: color 0.3s ease;
}

.breadcrumb a:hover {
    color: var(--primary);
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 600;
}

.grid-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.card {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--card-border);
}

.card-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
}

.info-value {
    font-size: 1rem;
    font-weight: 500;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    display: inline-block;
}

.status-pending {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-executed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-cancelled {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.status-expired {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.type-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    text-transform: uppercase;
    display: inline-block;
}

.type-buy {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.type-sell {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.amount-positive {
    color: #10b981;
    font-weight: 600;
    font-size: 1.1rem;
}

.amount-negative {
    color: #ef4444;
    font-weight: 600;
    font-size: 1.1rem;
}

.user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    font-size: 1.25rem;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: var(--gradient);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.btn-success:hover {
    background: rgba(16, 185, 129, 0.2);
}

.btn-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
}

.btn-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.btn-warning:hover {
    background: rgba(245, 158, 11, 0.2);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text);
    border: 1px solid var(--card-border);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.table-container {
    overflow-x: auto;
    border-radius: 8px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--card-border);
}

th {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 0.875rem;
}

td {
    font-size: 0.9rem;
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid var(--success);
    color: var(--success);
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid var(--warning);
    color: var(--warning);
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid var(--error);
    color: var(--error);
}

.floating-shapes {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: -1;
}

.shape {
    position: absolute;
    opacity: 0.05;
    background: var(--gradient);
    border-radius: 50%;
}

.shape-1 {
    width: 100px;
    height: 100px;
    top: 10%;
    left: 5%;
    animation: float 8s ease-in-out infinite;
}

.shape-2 {
    width: 60px;
    height: 60px;
    top: 70%;
    right: 5%;
    animation: float 6s ease-in-out infinite reverse;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-20px) rotate(180deg);
    }
}

.market-info {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.price-comparison {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
}

.price-difference {
    font-weight: 600;
}

.difference-positive {
    color: #10b981;
}

.difference-negative {
    color: #ef4444;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .header {
        padding: 1rem;
        flex-direction: column;
        gap: 1rem;
    }
    
    .nav-links {
        margin-left: 0;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .grid-layout {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    th, td {
        padding: 8px 12px;
    }
}
</style>
</head>
<body>
<div class="floating-shapes">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
</div>

<header class="header">
    <div style="display: flex; align-items: center;">
        <div class="logo">FLUX TRADING</div>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
            <a href="manage_users.php" class="nav-link">Utilisateurs</a>
            <a href="transaction_history.php" class="nav-link">Transactions</a>
            <a href="trading_orders.php" class="nav-link">Ordres</a>
            <a href="portfolio.php" class="nav-link">Portefeuilles</a>
            <a href="system_logs.php" class="nav-link">Logs</a>
        </div>
    </div>
    <div class="user-info">
        <span>Bonjour, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
        <a href="admin_logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </div>
</header>

<div class="container">
    <!-- Fil d'Ariane -->
    <div class="breadcrumb">
        <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <span>/</span>
        <a href="trading_orders.php">Ordres de Trading</a>
        <span>/</span>
        <span>Détails #<?php echo $order_id; ?></span>
    </div>

    <!-- Messages de succès -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php 
            switch ($_GET['success']) {
                case 'executed':
                    echo "Ordre exécuté avec succès.";
                    break;
                case 'cancelled':
                    echo "Ordre annulé avec succès.";
                    break;
                case 'expired':
                    echo "Ordre marqué comme expiré.";
                    break;
                default:
                    echo "Action effectuée avec succès.";
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- Alertes -->
    <?php if ($is_expired): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            Cet ordre a expiré le <?php echo date('d/m/Y', strtotime($order['validity_date'])); ?>
        </div>
    <?php endif; ?>

    <!-- En-tête de page -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Détails de l'Ordre</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                ID: #<?php echo $order_id; ?> • 
                Créé le <?php echo date('d/m/Y à H:i', strtotime($order['created_at'])); ?>
            </p>
        </div>
        <a href="trading_orders.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour aux ordres
        </a>
    </div>

    <div class="grid-layout">
        <!-- Colonne principale -->
        <div>
            <!-- Informations de l'ordre -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> Informations de l'Ordre</h2>
                    <div>
                        <span class="type-badge type-<?php echo $order['order_type']; ?>">
                            <?php echo strtoupper($order['order_type']); ?>
                        </span>
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-group">
                        <span class="info-label">Action</span>
                        <span class="info-value" style="font-weight: 600; font-size: 1.1rem;">
                            <?php echo htmlspecialchars($order['stock_symbol']); ?>
                        </span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Quantité</span>
                        <span class="info-value"><?php echo number_format($order['quantity'], 0, ',', ' '); ?> actions</span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Prix Limite</span>
                        <span class="info-value"><?php echo number_format($order['price'], 2, ',', ' '); ?> <?php echo $order['currency']; ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Montant Total</span>
                        <span class="<?php echo $order['order_type'] === 'buy' ? 'amount-negative' : 'amount-positive'; ?>">
                            <?php echo $order['order_type'] === 'buy' ? '-' : '+'; ?>
                            <?php echo number_format($order['total_amount'], 2, ',', ' '); ?> 
                            <?php echo $order['currency']; ?>
                        </span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Validité</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($order['validity_date'])); ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Statut</span>
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Date de création</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($order['created_at'])); ?></span>
                    </div>
                    
                    <?php if ($order['executed_at']): ?>
                    <div class="info-group">
                        <span class="info-label">Date d'exécution</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($order['executed_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($order['recipient']): ?>
                <div style="margin-top: 1.5rem;">
                    <span class="info-label">Bénéficiaire</span>
                    <p style="margin-top: 0.5rem; color: var(--text);"><?php echo htmlspecialchars($order['recipient']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Informations marché -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2><i class="fas fa-chart-line"></i> Informations Marché</h2>
                </div>
                
                <div class="market-info">
                    <div class="info-grid">
                        <div class="info-group">
                            <span class="info-label">Prix de l'ordre</span>
                            <span class="info-value"><?php echo number_format($order['price'], 2, ',', ' '); ?> <?php echo $order['currency']; ?></span>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Dernier prix marché</span>
                            <span class="info-value">
                                <?php echo number_format($market_price, 2, ',', ' '); ?> <?php echo $order['currency']; ?>
                                <?php if ($market_data): ?>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                        le <?php echo date('d/m/Y H:i', strtotime($market_data['transaction_date'])); ?>
                                    </div>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Différence</span>
                            <span class="price-difference <?php echo $price_difference >= 0 ? 'difference-positive' : 'difference-negative'; ?>">
                                <?php echo $price_difference >= 0 ? '+' : ''; ?>
                                <?php echo number_format($price_difference, 2, ',', ' '); ?> 
                                (<?php echo $price_difference >= 0 ? '+' : ''; ?><?php echo number_format($price_difference_percentage, 2, ',', ' '); ?>%)
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($order['status'] === 'pending'): ?>
                    <div class="price-comparison">
                        <span style="color: var(--text-secondary); font-size: 0.875rem;">
                            <?php if ($order['order_type'] === 'buy'): ?>
                                L'ordre d'achat est <?php echo $price_difference <= 0 ? 'au-dessus' : 'en-dessous'; ?> du marché
                            <?php else: ?>
                                L'ordre de vente est <?php echo $price_difference >= 0 ? 'au-dessus' : 'en-dessous'; ?> du marché
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ordres similaires -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Ordres Similaires</h2>
                </div>
                
                <?php if (!empty($similar_orders)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Quantité</th>
                                <th>Prix</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($similar_orders as $similar): ?>
                            <tr>
                                <td>#<?php echo $similar['id']; ?></td>
                                <td>
                                    <span class="type-badge type-<?php echo $similar['order_type']; ?>">
                                        <?php echo strtoupper($similar['order_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($similar['quantity'], 0, ',', ' '); ?></td>
                                <td><?php echo number_format($similar['price'], 2, ',', ' '); ?> FCFA</td>
                                <td>
                                    <span class="status-badge status-<?php echo $similar['status']; ?>">
                                        <?php echo ucfirst($similar['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($similar['created_at'])); ?></td>
                                <td>
                                    <a href="order_details.php?id=<?php echo $similar['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>Aucun ordre similaire trouvé</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Colonne latérale -->
        <div>
            <!-- Informations utilisateur -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user"></i> Informations Utilisateur</h2>
                </div>
                
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($order['full_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($order['full_name']); ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">ID: <?php echo $order['user_id']; ?></div>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-group">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['email']); ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Téléphone</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Inscription</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($order['user_created_at'])); ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Solde actuel</span>
                        <span class="amount-positive">
                            <?php echo number_format($order['user_balance'], 2, ',', ' '); ?> 
                            <?php echo $order['currency']; ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($stock_data): ?>
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--card-border);">
                    <span class="info-label">Actions détenues</span>
                    <div style="margin-top: 0.5rem;">
                        <div style="font-weight: 500;"><?php echo number_format($stock_data['owned_quantity'], 0, ',', ' '); ?> actions</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            Prix moyen: <?php echo number_format($stock_data['average_buy_price'], 2, ',', ' '); ?> FCFA
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                    <a href="user_trading_details.php?id=<?php echo $order['user_id']; ?>" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-chart-line"></i> Profil Trading
                    </a>
                    <a href="user_transactions.php?id=<?php echo $order['user_id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-list"></i>
                    </a>
                </div>
            </div>

            <!-- Actions -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2><i class="fas fa-cogs"></i> Actions</h2>
                </div>
                
                <div class="actions-grid">
                    <?php if ($order['status'] === 'pending' && !$is_expired): ?>
                    <form method="POST" style="grid-column: 1 / -1;">
                        <input type="hidden" name="action" value="execute">
                        <button type="submit" class="btn btn-success" style="width: 100%;">
                            <i class="fas fa-play"></i> Exécuter l'Ordre
                        </button>
                    </form>
                    
                    <form method="POST" style="grid-column: 1 / -1;">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-danger" style="width: 100%;" 
                                onclick="return confirm('Êtes-vous sûr de vouloir annuler cet ordre ?')">
                            <i class="fas fa-times"></i> Annuler l'Ordre
                        </button>
                    </form>
                    
                    <?php elseif ($is_expired): ?>
                    <form method="POST" style="grid-column: 1 / -1;">
                        <input type="hidden" name="action" value="expire">
                        <button type="submit" class="btn btn-warning" style="width: 100%;">
                            <i class="fas fa-clock"></i> Marquer comme Expiré
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="empty-state" style="padding: 1rem;">
                        <i class="fas fa-info-circle"></i>
                        <p>Aucune action disponible</p>
                        <p style="font-size: 0.8rem; margin-top: 0.5rem;">L'ordre est <?php echo $order['status']; ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <a href="trading_orders.php?search=<?php echo urlencode($order['full_name']); ?>" 
                       class="btn btn-secondary" style="grid-column: 1 / -1;">
                        <i class="fas fa-search"></i> Voir tous les ordres de cet utilisateur
                    </a>
                </div>
            </div>

            <!-- Informations techniques -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2><i class="fas fa-microchip"></i> Informations Techniques</h2>
                </div>
                
                <div class="info-grid">
                    <div class="info-group">
                        <span class="info-label">ID Ordre</span>
                        <span class="info-value">#<?php echo $order['id']; ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">ID Utilisateur</span>
                        <span class="info-value">#<?php echo $order['user_id']; ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Type</span>
                        <span class="info-value"><?php echo $order['order_type']; ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Symbole</span>
                        <span class="info-value"><?php echo $order['stock_symbol']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour confirmer les actions critiques
function confirmAction(action, orderId) {
    const messages = {
        'cancel': 'Êtes-vous sûr de vouloir annuler cet ordre ?',
        'execute': 'Êtes-vous sûr de vouloir exécuter cet ordre ?',
        'expire': 'Êtes-vous sûr de vouloir marquer cet ordre comme expiré ?'
    };
    
    return confirm(messages[action] || 'Confirmer cette action ?');
}

// Ajouter des confirmations aux formulaires
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const action = form.querySelector('input[name="action"]')?.value;
        if (action && ['cancel', 'execute'].includes(action)) {
            form.addEventListener('submit', function(e) {
                if (!confirmAction(action, <?php echo $order_id; ?>)) {
                    e.preventDefault();
                }
            });
        }
    });
});
</script>
</body>
</html>