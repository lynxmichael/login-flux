<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

// Statistiques globales
$total_users = $pdo->query("SELECT COUNT(*) AS total FROM users")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$today_users = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$week_users = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$month_users = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Utilisateurs connectés aujourd'hui (via user_sessions)
$today_active = $pdo->query("SELECT COUNT(DISTINCT user_id) AS total FROM user_sessions WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Récupérer les utilisateurs avec leurs statistiques de trading
$users = $pdo->query("
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.created_at,
        u.is_active,
        u.wallet_balance,
        w.balance as wallet_actual_balance,
        COALESCE(SUM(CASE WHEN t.type = 'buy' THEN ABS(t.amount) ELSE 0 END), 0) AS total_achats,
        COALESCE(SUM(CASE WHEN t.type = 'sell' THEN t.amount ELSE 0 END), 0) AS total_ventes,
        COUNT(DISTINCT CASE WHEN t.type = 'buy' THEN t.id END) AS nb_achats,
        COUNT(DISTINCT CASE WHEN t.type = 'sell' THEN t.id END) AS nb_ventes,
        COUNT(DISTINCT us_stocks.stock_symbol) AS nb_actions_diff,
        COALESCE(SUM(us_stocks.quantity), 0) AS total_actions,
        COALESCE(SUM(us_stocks.current_value), 0) AS valeur_portfolio,
        (SELECT MAX(created_at) FROM user_sessions WHERE user_id = u.id) AS last_login
    FROM users u
    LEFT JOIN wallets w ON u.id = w.user_id
    LEFT JOIN transactions t ON u.id = t.user_id AND t.status = 'completed'
    LEFT JOIN user_stocks us_stocks ON u.id = us_stocks.user_id
    GROUP BY u.id, u.full_name, u.email, u.phone, u.created_at, u.is_active, u.wallet_balance, w.balance
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Filtres
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

if ($search) {
    $search_term = "%$search%";
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.created_at,
            u.is_active,
            u.wallet_balance,
            w.balance as wallet_actual_balance,
            COALESCE(SUM(CASE WHEN t.type = 'buy' THEN ABS(t.amount) ELSE 0 END), 0) AS total_achats,
            COALESCE(SUM(CASE WHEN t.type = 'sell' THEN t.amount ELSE 0 END), 0) AS total_ventes,
            COUNT(DISTINCT CASE WHEN t.type = 'buy' THEN t.id END) AS nb_achats,
            COUNT(DISTINCT CASE WHEN t.type = 'sell' THEN t.id END) AS nb_ventes,
            COUNT(DISTINCT us_stocks.stock_symbol) AS nb_actions_diff,
            COALESCE(SUM(us_stocks.quantity), 0) AS total_actions,
            COALESCE(SUM(us_stocks.current_value), 0) AS valeur_portfolio,
            (SELECT MAX(created_at) FROM user_sessions WHERE user_id = u.id) AS last_login
        FROM users u
        LEFT JOIN wallets w ON u.id = w.user_id
        LEFT JOIN transactions t ON u.id = t.user_id AND t.status = 'completed'
        LEFT JOIN user_stocks us_stocks ON u.id = us_stocks.user_id
        WHERE u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?
        GROUP BY u.id, u.full_name, u.email, u.phone, u.created_at, u.is_active, u.wallet_balance, w.balance
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$search_term, $search_term, $search_term]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($filter !== 'all') {
    $query = match($filter) {
        'today' => "WHERE DATE(u.created_at) = CURDATE()",
        'week' => "WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        'month' => "WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        'active' => "WHERE u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) OR u.last_login IS NULL",
        'traders' => "HAVING nb_achats > 0 OR nb_ventes > 0",
        default => ""
    };
    
    $base_query = "
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.created_at,
            u.is_active,
            u.wallet_balance,
            w.balance as wallet_actual_balance,
            COALESCE(SUM(CASE WHEN t.type = 'buy' THEN ABS(t.amount) ELSE 0 END), 0) AS total_achats,
            COALESCE(SUM(CASE WHEN t.type = 'sell' THEN t.amount ELSE 0 END), 0) AS total_ventes,
            COUNT(DISTINCT CASE WHEN t.type = 'buy' THEN t.id END) AS nb_achats,
            COUNT(DISTINCT CASE WHEN t.type = 'sell' THEN t.id END) AS nb_ventes,
            COUNT(DISTINCT us_stocks.stock_symbol) AS nb_actions_diff,
            COALESCE(SUM(us_stocks.quantity), 0) AS total_actions,
            COALESCE(SUM(us_stocks.current_value), 0) AS valeur_portfolio,
            (SELECT MAX(created_at) FROM user_sessions WHERE user_id = u.id) AS last_login
        FROM users u
        LEFT JOIN wallets w ON u.id = w.user_id
        LEFT JOIN transactions t ON u.id = t.user_id AND t.status = 'completed'
        LEFT JOIN user_stocks us_stocks ON u.id = us_stocks.user_id
    ";
    
    if ($filter === 'traders') {
        $users = $pdo->query($base_query . " GROUP BY u.id, u.full_name, u.email, u.phone, u.created_at, u.is_active, u.wallet_balance, w.balance HAVING nb_achats > 0 OR nb_ventes > 0 ORDER BY u.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $users = $pdo->query($base_query . " " . $query . " GROUP BY u.id, u.full_name, u.email, u.phone, u.created_at, u.is_active, u.wallet_balance, w.balance ORDER BY u.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestion Utilisateurs Trading | FLUX.IO</title>
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

.icon-total { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.icon-today { background: linear-gradient(135deg, #10b981, #34d399); }
.icon-week { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.icon-month { background: linear-gradient(135deg, #ec4899, #f472b6); }
.icon-active { background: linear-gradient(135deg, #3b82f6, #60a5fa); }

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

.filter-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 8px 16px;
    border: 1px solid var(--card-border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    font-size: 0.875rem;
}

.filter-btn:hover, .filter-btn.active {
    background: var(--gradient);
    color: white;
    border-color: transparent;
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
    justify-content: between;
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
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-active {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-inactive {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
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

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 500;
}

.badge-success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.badge-info {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: var(--gradient);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-sm {
    padding: 4px 8px;
    font-size: 0.7rem;
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
            <a href="manage_users.php" class="nav-link active">Utilisateurs</a>
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
    <!-- Cartes de statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-total">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3>Total Traders</h3>
                <div class="number"><?php echo number_format($total_users, 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-today">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stat-content">
                <h3>Inscrits Aujourd'hui</h3>
                <div class="number"><?php echo number_format($today_users, 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-week">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-content">
                <h3>Inscrits Cette Semaine</h3>
                <div class="number"><?php echo number_format($week_users, 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-month">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-content">
                <h3>Inscrits Ce Mois</h3>
                <div class="number"><?php echo number_format($month_users, 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-active">
                <i class="fas fa-sign-in-alt"></i>
            </div>
            <div class="stat-content">
                <h3>Connectés Aujourd'hui</h3>
                <div class="number"><?php echo number_format($today_active, 0, ',', ' '); ?></div>
            </div>
        </div>
    </div>

    <!-- Filtres et recherche -->
    <div class="filters">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Rechercher un trader..." 
                   value="<?php echo htmlspecialchars($search); ?>" 
                   onkeypress="if(event.keyCode==13) searchUsers()">
        </div>
        <div class="filter-buttons">
            <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">Tous</a>
            <a href="?filter=today" class="filter-btn <?php echo $filter === 'today' ? 'active' : ''; ?>">Aujourd'hui</a>
            <a href="?filter=week" class="filter-btn <?php echo $filter === 'week' ? 'active' : ''; ?>">Cette semaine</a>
            <a href="?filter=month" class="filter-btn <?php echo $filter === 'month' ? 'active' : ''; ?>">Ce mois</a>
            <a href="?filter=traders" class="filter-btn <?php echo $filter === 'traders' ? 'active' : ''; ?>">Actifs</a>
        </div>
    </div>

    <!-- Tableau des utilisateurs -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Liste des Traders (<?php echo count($users); ?>)</h2>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Trader</th>
                        <th>Contact</th>
                        <th>Inscription</th>
                        <th>Dernière connexion</th>
                        <th>Portefeuille</th>
                        <th>Portfolio</th>
                        <th>Achats</th>
                        <th>Ventes</th>
                        <th>Actions détenues</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">ID: <?php echo $user['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($user['email']); ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <div><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo date('H:i', strtotime($user['created_at'])); ?></div>
                        </td>
                        <td>
                            <?php if ($user['last_login']): ?>
                                <div><?php echo date('d/m/Y', strtotime($user['last_login'])); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo date('H:i', strtotime($user['last_login'])); ?></div>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">Jamais</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="amount-positive"><?php echo number_format($user['wallet_actual_balance'] ?? $user['wallet_balance'], 2, ',', ' '); ?> FCFA</div>
                        </td>
                        <td>
                            <div class="amount-info"><?php echo number_format($user['valeur_portfolio'], 2, ',', ' '); ?> FCFA</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo $user['nb_actions_diff']; ?> actions</div>
                        </td>
                        <td>
                            <div class="amount-negative">-<?php echo number_format($user['total_achats'], 2, ',', ' '); ?> FCFA</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo $user['nb_achats']; ?> opérations</div>
                        </td>
                        <td>
                            <div class="amount-positive">+<?php echo number_format($user['total_ventes'], 2, ',', ' '); ?> FCFA</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo $user['nb_ventes']; ?> opérations</div>
                        </td>
                        <td>
                            <div style="font-weight: 500;"><?php echo number_format($user['total_actions'], 0, ',', ' '); ?> actions</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo $user['nb_actions_diff']; ?> types</div>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="user_trading_details.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm" title="Détails trading">
                                    <i class="fas fa-chart-line"></i>
                                </a>
                                <a href="user_transactions.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm" title="Transactions">
                                    <i class="fas fa-exchange-alt"></i>
                                </a>
                                <a href="user_portfolio.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm" title="Portfolio">
                                    <i class="fas fa-coins"></i>
                                </a>
                                <button class="btn btn-primary btn-sm" title="Modifier" onclick="editUser(<?php echo $user['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function searchUsers() {
    const search = document.querySelector('.search-input').value;
    const url = new URL(window.location);
    url.searchParams.set('search', search);
    window.location.href = url.toString();
}

function editUser(userId) {
    // Implémenter l'édition d'utilisateur
    alert('Édition du trader ID: ' + userId);
}

// Auto-submit search when typing stops
let searchTimeout;
document.querySelector('.search-input').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(searchUsers, 500);
});
</script>
</body>
</html>