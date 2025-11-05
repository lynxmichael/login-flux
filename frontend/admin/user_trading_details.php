<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_users.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$user_id = intval($_GET['id']);

// Récupérer les informations de l'utilisateur
$user_stmt = $pdo->prepare("
    SELECT 
        u.*,
        w.balance,
        w.currency,
        w.created_at as wallet_created
    FROM users u
    LEFT JOIN wallets w ON u.id = w.user_id
    WHERE u.id = ?
");

$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: manage_users.php?error=user_not_found');
    exit;
}

// Récupérer le portefeuille d'actions de l'utilisateur
$stocks_stmt = $pdo->prepare("
    SELECT 
        us.*,
        (SELECT price FROM transactions 
         WHERE user_id = ? AND stock_symbol = us.stock_symbol AND type = 'buy' 
         ORDER BY created_at DESC LIMIT 1) as last_price,
        (us.current_value - us.total_invested) as realized_pnl,
        CASE 
            WHEN us.total_invested > 0 THEN 
                ROUND(((us.current_value - us.total_invested) / us.total_invested) * 100, 2)
            ELSE 0 
        END as pnl_percentage
    FROM user_stocks us
    WHERE us.user_id = ?
    ORDER BY us.current_value DESC
");

$stocks_stmt->execute([$user_id, $user_id]);
$user_stocks = $stocks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques de trading
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN type = 'buy' THEN 1 ELSE 0 END) as buy_count,
        SUM(CASE WHEN type = 'sell' THEN 1 ELSE 0 END) as sell_count,
        SUM(CASE WHEN type = 'buy' THEN ABS(amount) ELSE 0 END) as total_buy_amount,
        SUM(CASE WHEN type = 'sell' THEN amount ELSE 0 END) as total_sell_amount,
        SUM(fees) as total_fees,
        COUNT(DISTINCT stock_symbol) as unique_stocks_traded
    FROM transactions 
    WHERE user_id = ? AND status = 'completed'
");

$stats_stmt->execute([$user_id]);
$trading_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Transactions récentes
$recent_transactions = $pdo->prepare("
    SELECT 
        t.*,
        CASE 
            WHEN t.type = 'buy' THEN 'Achat'
            WHEN t.type = 'sell' THEN 'Vente'
            WHEN t.type = 'deposit' THEN 'Dépôt'
            WHEN t.type = 'withdrawal' THEN 'Retrait'
            ELSE t.type 
        END as type_fr
    FROM transactions t
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");

$recent_transactions->execute([$user_id]);
$transactions = $recent_transactions->fetchAll(PDO::FETCH_ASSOC);

// Performance mensuelle
$monthly_performance = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as transaction_count,
        SUM(CASE WHEN type = 'buy' THEN ABS(amount) ELSE 0 END) as buy_amount,
        SUM(CASE WHEN type = 'sell' THEN amount ELSE 0 END) as sell_amount,
        SUM(fees) as fees_amount
    FROM transactions 
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
");

$monthly_performance->execute([$user_id]);
$monthly_stats = $monthly_performance->fetchAll(PDO::FETCH_ASSOC);

// Calculer la valeur totale du portefeuille
$total_portfolio_value = 0;
$total_invested = 0;
$total_unrealized_pnl = 0;

foreach ($user_stocks as $stock) {
    $total_portfolio_value += $stock['current_value'];
    $total_invested += $stock['total_invested'];
    $total_unrealized_pnl += $stock['unrealized_pnl'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Trading - <?php echo htmlspecialchars($user['full_name']); ?> | FLUX TRADING</title>
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

.user-profile {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.user-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    font-size: 1.5rem;
}

.user-details h1 {
    font-size: 1.75rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.user-meta {
    color: var(--text-secondary);
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.grid-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin: 0 auto 1rem;
}

.icon-balance { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.icon-portfolio { background: linear-gradient(135deg, #10b981, #34d399); }
.icon-pnl { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.icon-transactions { background: linear-gradient(135deg, #ec4899, #f472b6); }

.stat-value {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.amount-positive {
    color: #10b981;
    font-weight: 600;
}

.amount-negative {
    color: #ef4444;
    font-weight: 600;
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

.status-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-pending {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.type-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.type-buy {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.type-sell {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.type-deposit {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.type-withdrawal {
    background: rgba(168, 85, 247, 0.1);
    color: #a855f7;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
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

.btn-secondary {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text);
    border: 1px solid var(--card-border);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
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

.performance-chart {
    background: var(--card);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.chart-placeholder {
    height: 200px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    font-size: 0.9rem;
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
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .user-profile {
        flex-direction: column;
        text-align: center;
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
        <a href="manage_users.php">Utilisateurs</a>
        <span>/</span>
        <span>Profil Trading - <?php echo htmlspecialchars($user['full_name']); ?></span>
    </div>

    <!-- En-tête de page -->
    <div class="page-header">
        <div class="user-profile">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <div class="user-meta">
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                    <span><i class="fas fa-calendar"></i> Inscrit le <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                    <span class="status-badge status-<?php echo $user['is_active'] ? 'completed' : 'pending'; ?>">
                        <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                    </span>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 1rem;">
            <a href="user_transactions.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                <i class="fas fa-list"></i> Voir toutes les transactions
            </a>
            <a href="manage_users.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour aux utilisateurs
            </a>
        </div>
    </div>

    <!-- Cartes de statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-balance">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-value amount-positive">
                <?php echo number_format($user['balance'], 2, ',', ' '); ?> <?php echo $user['currency']; ?>
            </div>
            <div class="stat-label">Solde Actuel</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-portfolio">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value">
                <?php echo number_format($total_portfolio_value, 2, ',', ' '); ?> <?php echo $user['currency']; ?>
            </div>
            <div class="stat-label">Valeur du Portefeuille</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-pnl">
                <i class="fas fa-trending-up"></i>
            </div>
            <div class="stat-value <?php echo $total_unrealized_pnl >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                <?php echo $total_unrealized_pnl >= 0 ? '+' : ''; ?><?php echo number_format($total_unrealized_pnl, 2, ',', ' '); ?> <?php echo $user['currency']; ?>
            </div>
            <div class="stat-label">Plus-value Non Réalisée</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-transactions">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="stat-value"><?php echo number_format($trading_stats['total_transactions'] ?? 0, 0, ',', ' '); ?></div>
            <div class="stat-label">Transactions Total</div>
        </div>
    </div>

    <div class="grid-layout">
        <!-- Colonne de gauche -->
        <div>
            <!-- Portefeuille d'actions -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-coins"></i> Portefeuille d'Actions</h2>
                    <span class="stat-label"><?php echo count($user_stocks); ?> actions détenues</span>
                </div>
                
                <?php if (!empty($user_stocks)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Quantité</th>
                                <th>Prix Moyen</th>
                                <th>Valeur Actuelle</th>
                                <th>P&L</th>
                                <th>% P&L</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_stocks as $stock): ?>
                            <tr>
                                <td style="font-weight: 500;"><?php echo htmlspecialchars($stock['stock_symbol']); ?></td>
                                <td><?php echo number_format($stock['quantity'], 0, ',', ' '); ?></td>
                                <td><?php echo number_format($stock['average_buy_price'], 2, ',', ' '); ?> <?php echo $user['currency']; ?></td>
                                <td><?php echo number_format($stock['current_value'], 2, ',', ' '); ?> <?php echo $user['currency']; ?></td>
                                <td class="<?php echo $stock['unrealized_pnl'] >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo $stock['unrealized_pnl'] >= 0 ? '+' : ''; ?><?php echo number_format($stock['unrealized_pnl'], 2, ',', ' '); ?>
                                </td>
                                <td class="<?php echo $stock['pnl_percentage'] >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo $stock['pnl_percentage'] >= 0 ? '+' : ''; ?><?php echo number_format($stock['pnl_percentage'], 2, ',', ' '); ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <p>Aucune action détenue</p>
                    <p style="font-size: 0.9rem; margin-top: 0.5rem;">L'utilisateur n'a pas encore constitué de portefeuille</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Performance mensuelle -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2><i class="fas fa-chart-bar"></i> Performance Mensuelle</h2>
                </div>
                
                <?php if (!empty($monthly_stats)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Mois</th>
                                <th>Transactions</th>
                                <th>Achats</th>
                                <th>Ventes</th>
                                <th>Frais</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_stats as $month): ?>
                            <tr>
                                <td><?php echo DateTime::createFromFormat('Y-m', $month['month'])->format('m/Y'); ?></td>
                                <td><?php echo $month['transaction_count']; ?></td>
                                <td class="amount-negative">-<?php echo number_format($month['buy_amount'], 2, ',', ' '); ?></td>
                                <td class="amount-positive">+<?php echo number_format($month['sell_amount'], 2, ',', ' '); ?></td>
                                <td class="amount-negative">-<?php echo number_format($month['fees_amount'], 2, ',', ' '); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <p>Aucune donnée de performance</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Colonne de droite -->
        <div>
            <!-- Statistiques de trading -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-pie"></i> Statistiques de Trading</h2>
                </div>
                
                <div style="display: grid; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--card-border);">
                        <span style="color: var(--text-secondary);">Transactions Total</span>
                        <span style="font-weight: 500;"><?php echo number_format($trading_stats['total_transactions'] ?? 0, 0, ',', ' '); ?></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--card-border);">
                        <span style="color: var(--text-secondary);">Achats</span>
                        <div style="text-align: right;">
                            <div style="font-weight: 500;"><?php echo number_format($trading_stats['buy_count'] ?? 0, 0, ',', ' '); ?> opérations</div>
                            <div class="amount-negative" style="font-size: 0.875rem;">-<?php echo number_format($trading_stats['total_buy_amount'] ?? 0, 2, ',', ' '); ?> <?php echo $user['currency']; ?></div>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--card-border);">
                        <span style="color: var(--text-secondary);">Ventes</span>
                        <div style="text-align: right;">
                            <div style="font-weight: 500;"><?php echo number_format($trading_stats['sell_count'] ?? 0, 0, ',', ' '); ?> opérations</div>
                            <div class="amount-positive" style="font-size: 0.875rem;">+<?php echo number_format($trading_stats['total_sell_amount'] ?? 0, 2, ',', ' '); ?> <?php echo $user['currency']; ?></div>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--card-border);">
                        <span style="color: var(--text-secondary);">Frais Total</span>
                        <div class="amount-negative" style="font-weight: 500;">-<?php echo number_format($trading_stats['total_fees'] ?? 0, 2, ',', ' '); ?> <?php echo $user['currency']; ?></div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0;">
                        <span style="color: var(--text-secondary);">Actions Tradées</span>
                        <span style="font-weight: 500;"><?php echo number_format($trading_stats['unique_stocks_traded'] ?? 0, 0, ',', ' '); ?> différentes</span>
                    </div>
                </div>
            </div>

            <!-- Transactions récentes -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Transactions Récentes</h2>
                    <a href="user_transactions.php?id=<?php echo $user_id; ?>" class="btn btn-secondary btn-sm">
                        Voir tout
                    </a>
                </div>
                
                <?php if (!empty($transactions)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Action</th>
                                <th>Montant</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <span class="type-badge type-<?php echo $transaction['type']; ?>">
                                        <?php echo $transaction['type_fr']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($transaction['stock_symbol'] && $transaction['stock_symbol'] !== '0'): ?>
                                        <?php echo htmlspecialchars($transaction['stock_symbol']); ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="<?php echo $transaction['amount'] > 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo $transaction['amount'] > 0 ? '+' : ''; ?>
                                    <?php echo number_format($transaction['amount'], 2, ',', ' '); ?> 
                                    <?php echo $user['currency']; ?>
                                </td>
                                <td>
                                    <div><?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo date('H:i', strtotime($transaction['created_at'])); ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-exchange-alt"></i>
                    <p>Aucune transaction récente</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions rapides -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2><i class="fas fa-bolt"></i> Actions Rapides</h2>
                </div>
                
                <div style="display: grid; gap: 0.75rem;">
                    <a href="user_transactions.php?id=<?php echo $user_id; ?>" class="btn btn-primary" style="justify-content: center;">
                        <i class="fas fa-list"></i> Historique Complet
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="btn btn-secondary" style="justify-content: center;">
                        <i class="fas fa-envelope"></i> Contacter l'Utilisateur
                    </a>
                    <a href="manage_users.php" class="btn btn-secondary" style="justify-content: center;">
                        <i class="fas fa-arrow-left"></i> Retour aux Utilisateurs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Script pour des fonctionnalités interactives simples
document.addEventListener('DOMContentLoaded', function() {
    // Ajouter des tooltips ou autres interactions si nécessaire
    console.log('Page de profil trading chargée pour l\'utilisateur #<?php echo $user_id; ?>');
});
</script>
</body>
</html>