<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

// Récupérer les statistiques globales du portefeuille
$portfolio_stats = $pdo->query("
    SELECT 
        COUNT(DISTINCT user_id) as total_investors,
        COUNT(DISTINCT stock_symbol) as total_stocks,
        SUM(quantity) as total_shares,
        SUM(current_value) as total_portfolio_value,
        SUM(total_invested) as total_amount_invested,
        SUM(unrealized_pnl) as total_unrealized_pnl,
        AVG(CASE WHEN total_invested > 0 THEN (unrealized_pnl / total_invested) * 100 ELSE 0 END) as avg_return_percentage
    FROM user_stocks
")->fetch(PDO::FETCH_ASSOC);

// Récupérer les portefeuilles des utilisateurs avec filtres
$user_filter = $_GET['user'] ?? 'all';
$stock_filter = $_GET['stock'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if ($user_filter !== 'all') {
    $where_conditions[] = "us.user_id = ?";
    $params[] = $user_filter;
}

if ($stock_filter !== 'all') {
    $where_conditions[] = "us.stock_symbol = ?";
    $params[] = $stock_filter;
}

if ($search) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR us.stock_symbol LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

// Récupérer les portefeuilles détaillés
$portfolios = $pdo->prepare("
    SELECT 
        us.*,
        u.full_name,
        u.email,
        u.phone,
        w.balance as wallet_balance,
        (SELECT price_per_share 
         FROM user_transactions 
         WHERE user_id = us.user_id AND stock_symbol = us.stock_symbol 
         ORDER BY transaction_date DESC LIMIT 1) as last_price,
        CASE 
            WHEN us.total_invested > 0 THEN 
                ROUND(((us.current_value - us.total_invested) / us.total_invested) * 100, 2)
            ELSE 0 
        END as return_percentage,
        (SELECT COUNT(*) 
         FROM user_transactions 
         WHERE user_id = us.user_id AND stock_symbol = us.stock_symbol) as transaction_count
    FROM user_stocks us
    LEFT JOIN users u ON us.user_id = u.id
    LEFT JOIN wallets w ON us.user_id = w.user_id
    $where_sql
    ORDER BY us.current_value DESC
");

$portfolios->execute($params);
$portfolios = $portfolios->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des utilisateurs et actions pour les filtres
$users = $pdo->query("SELECT DISTINCT u.id, u.full_name FROM user_stocks us JOIN users u ON us.user_id = u.id ORDER BY u.full_name")->fetchAll(PDO::FETCH_ASSOC);
$stocks = $pdo->query("SELECT DISTINCT stock_symbol FROM user_stocks ORDER BY stock_symbol")->fetchAll(PDO::FETCH_COLUMN);

// Calculer les métriques supplémentaires
$total_positions = count($portfolios);
$profitable_positions = 0;
$losing_positions = 0;

foreach ($portfolios as $portfolio) {
    if ($portfolio['unrealized_pnl'] > 0) {
        $profitable_positions++;
    } elseif ($portfolio['unrealized_pnl'] < 0) {
        $losing_positions++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestion des Portefeuilles | FLUX TRADING</title>
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
    max-width: 1800px;
    margin: 0 auto;
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
    display: flex;
    align-items: center;
    gap: 1rem;
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
}

.icon-investors { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.icon-stocks { background: linear-gradient(135deg, #10b981, #34d399); }
.icon-shares { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.icon-value { background: linear-gradient(135deg, #ec4899, #f472b6); }
.icon-invested { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
.icon-pnl { background: linear-gradient(135deg, #84cc16, #a3e635); }

.stat-content h3 {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.stat-content .number {
    font-size: 1.5rem;
    font-weight: 600;
}

.filters {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    flex: 1;
    min-width: 300px;
}

.search-input {
    width: 100%;
    padding: 12px 16px 12px 40px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--card-border);
    border-radius: 8px;
    color: var(--text);
    font-size: 0.95rem;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
}

.filter-group {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.filter-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
}

.filter-select {
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--card-border);
    border-radius: 6px;
    color: var(--text);
    font-size: 0.875rem;
    min-width: 120px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
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

.card {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.card-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
}

.table-container {
    overflow-x: auto;
    border-radius: 8px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
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

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    font-size: 0.8rem;
}

.amount-positive {
    color: #10b981;
    font-weight: 600;
}

.amount-negative {
    color: #ef4444;
    font-weight: 600;
}

.amount-neutral {
    color: var(--text-secondary);
    font-weight: 600;
}

.performance-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

.performance-positive {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.performance-negative {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.performance-neutral {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.stock-symbol {
    font-weight: 600;
    font-size: 0.95rem;
}

.position-size {
    display: inline-block;
    padding: 4px 8px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
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

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--card-border);
}

.summary-item {
    text-align: center;
}

.summary-value {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.summary-label {
    color: var(--text-secondary);
    font-size: 0.75rem;
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
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filters {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-box {
        min-width: auto;
    }
    
    .filter-group {
        flex-direction: column;
        align-items: stretch;
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
            <a href="portfolio.php" class="nav-link active">Portefeuilles</a>
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
    <!-- Cartes de statistiques globales -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-investors">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3>Investisseurs Actifs</h3>
                <div class="number"><?php echo number_format($portfolio_stats['total_investors'], 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-stocks">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h3>Actions Différentes</h3>
                <div class="number"><?php echo number_format($portfolio_stats['total_stocks'], 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-shares">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-content">
                <h3>Total Actions</h3>
                <div class="number"><?php echo number_format($portfolio_stats['total_shares'], 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-value">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-content">
                <h3>Valeur Totale</h3>
                <div class="number"><?php echo number_format($portfolio_stats['total_portfolio_value'], 2, ',', ' '); ?> FCFA</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-invested">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <h3>Capital Investi</h3>
                <div class="number"><?php echo number_format($portfolio_stats['total_amount_invested'], 2, ',', ' '); ?> FCFA</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-pnl">
                <i class="fas fa-trending-up"></i>
            </div>
            <div class="stat-content">
                <h3>Plus-value Totale</h3>
                <div class="number <?php echo $portfolio_stats['total_unrealized_pnl'] >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                    <?php echo $portfolio_stats['total_unrealized_pnl'] >= 0 ? '+' : ''; ?>
                    <?php echo number_format($portfolio_stats['total_unrealized_pnl'], 2, ',', ' '); ?> FCFA
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres et recherche -->
    <div class="filters">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Rechercher par utilisateur ou action..." 
                   value="<?php echo htmlspecialchars($search); ?>" 
                   onkeypress="if(event.keyCode==13) applyFilters()">
        </div>
        
        <div class="filter-group">
            <span class="filter-label">Utilisateur:</span>
            <select class="filter-select" id="userFilter" onchange="applyFilters()">
                <option value="all">Tous les utilisateurs</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <span class="filter-label">Action:</span>
            <select class="filter-select" id="stockFilter" onchange="applyFilters()">
                <option value="all">Toutes les actions</option>
                <?php foreach ($stocks as $stock): ?>
                    <option value="<?php echo $stock; ?>" <?php echo $stock_filter === $stock ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($stock); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <a href="portfolio.php" class="btn btn-secondary">
            <i class="fas fa-redo"></i> Réinitialiser
        </a>
        
        <button class="btn btn-primary" onclick="exportPortfolio()">
            <i class="fas fa-download"></i> Exporter
        </button>
    </div>

    <!-- Tableau des portefeuilles -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-pie"></i> Détails des Portefeuilles (<?php echo $total_positions; ?> positions)</h2>
        </div>
        
        <?php if (!empty($portfolios)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Investisseur</th>
                        <th>Action</th>
                        <th>Quantité</th>
                        <th>Prix Moyen</th>
                        <th>Dernier Prix</th>
                        <th>Investissement</th>
                        <th>Valeur Actuelle</th>
                        <th>P&L Non Réalisé</th>
                        <th>Performance</th>
                        <th>Transactions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($portfolios as $portfolio): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($portfolio['full_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($portfolio['full_name']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                        Solde: <?php echo number_format($portfolio['wallet_balance'], 2, ',', ' '); ?> FCFA
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="stock-symbol"><?php echo htmlspecialchars($portfolio['stock_symbol']); ?></div>
                        </td>
                        <td>
                            <span class="position-size"><?php echo number_format($portfolio['quantity'], 0, ',', ' '); ?> actions</span>
                        </td>
                        <td>
                            <div><?php echo number_format($portfolio['average_buy_price'], 2, ',', ' '); ?> FCFA</div>
                        </td>
                        <td>
                            <?php if ($portfolio['last_price']): ?>
                                <div><?php echo number_format($portfolio['last_price'], 2, ',', ' '); ?> FCFA</div>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo number_format($portfolio['total_invested'], 2, ',', ' '); ?> FCFA</div>
                        </td>
                        <td>
                            <div class="amount-positive"><?php echo number_format($portfolio['current_value'], 2, ',', ' '); ?> FCFA</div>
                        </td>
                        <td>
                            <div class="<?php echo $portfolio['unrealized_pnl'] >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                <?php echo $portfolio['unrealized_pnl'] >= 0 ? '+' : ''; ?>
                                <?php echo number_format($portfolio['unrealized_pnl'], 2, ',', ' '); ?> FCFA
                            </div>
                        </td>
                        <td>
                            <span class="performance-badge performance-<?php echo $portfolio['return_percentage'] >= 0 ? ($portfolio['return_percentage'] > 0 ? 'positive' : 'neutral') : 'negative'; ?>">
                                <?php echo $portfolio['return_percentage'] >= 0 ? '+' : ''; ?>
                                <?php echo number_format($portfolio['return_percentage'], 2, ',', ' '); ?>%
                            </span>
                        </td>
                        <td>
                            <div style="text-align: center;">
                                <div style="font-weight: 500;"><?php echo $portfolio['transaction_count']; ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">opérations</div>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="user_trading_details.php?id=<?php echo $portfolio['user_id']; ?>" class="btn btn-primary btn-sm" title="Profil Trading">
                                    <i class="fas fa-user"></i>
                                </a>
                                <a href="user_transactions.php?id=<?php echo $portfolio['user_id']; ?>&search=<?php echo urlencode($portfolio['stock_symbol']); ?>" 
                                   class="btn btn-primary btn-sm" title="Transactions">
                                    <i class="fas fa-exchange-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Résumé des positions -->
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value amount-positive"><?php echo $profitable_positions; ?></div>
                <div class="summary-label">Positions Gagnantes</div>
            </div>
            <div class="summary-item">
                <div class="summary-value amount-negative"><?php echo $losing_positions; ?></div>
                <div class="summary-label">Positions Perdantes</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $total_positions - $profitable_positions - $losing_positions; ?></div>
                <div class="summary-label">Positions Neutres</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">
                    <?php echo $total_positions > 0 ? number_format(($profitable_positions / $total_positions) * 100, 1, ',', ' ') : '0'; ?>%
                </div>
                <div class="summary-label">Taux de Succès</div>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chart-pie"></i>
            <p>Aucune position trouvée</p>
            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Aucune position ne correspond à vos critères de recherche</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function applyFilters() {
    const search = document.querySelector('.search-input').value;
    const user = document.getElementById('userFilter').value;
    const stock = document.getElementById('stockFilter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('user', user);
    url.searchParams.set('stock', stock);
    
    window.location.href = url.toString();
}

function exportPortfolio() {
    const search = document.querySelector('.search-input').value;
    const user = document.getElementById('userFilter').value;
    const stock = document.getElementById('stockFilter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('export', '1');
    url.searchParams.set('search', search);
    url.searchParams.set('user', user);
    url.searchParams.set('stock', stock);
    
    window.location.href = url.toString();
}

// Auto-apply filters when typing stops
let searchTimeout;
document.querySelector('.search-input').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
});
</script>
</body>
</html>