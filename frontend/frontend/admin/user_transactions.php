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
        w.currency
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

// Filtres
$type_filter = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Construire la requête avec filtres
$where_conditions = ["t.user_id = ?"];
$params = [$user_id];

if ($type_filter !== 'all') {
    $where_conditions[] = "t.type = ?";
    $params[] = $type_filter;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(t.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(t.created_at) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $where_conditions[] = "(t.stock_symbol LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where_conditions);

// Récupérer les transactions
$transactions_stmt = $pdo->prepare("
    SELECT 
        t.*,
        CASE 
            WHEN t.type = 'buy' THEN 'Achat'
            WHEN t.type = 'sell' THEN 'Vente'
            WHEN t.type = 'deposit' THEN 'Dépôt'
            WHEN t.type = 'withdrawal' THEN 'Retrait'
            WHEN t.type = 'dividend' THEN 'Dividende'
            WHEN t.type = 'fee' THEN 'Frais'
            ELSE t.type 
        END as type_fr
    FROM transactions t
    WHERE $where_sql
    ORDER BY t.created_at DESC
    LIMIT 200
");

$transactions_stmt->execute($params);
$transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques des transactions filtrées
// 🔎 Construction dynamique du filtre
$where_sql = "1"; // par défaut : aucune condition
$params = [];

// Filtrer par utilisateur si un ID est passé dans l’URL
if (!empty($_GET['user_id'])) {
    $where_sql .= " AND t.user_id = :user_id";
    $params[':user_id'] = $_GET['user_id'];
}

// Filtrer par type de transaction (achat, vente, etc.)
if (!empty($_GET['type'])) {
    $where_sql .= " AND t.type = :type";
    $params[':type'] = $_GET['type'];
}

// Filtrer par statut (completed, pending, etc.)
if (!empty($_GET['status'])) {
    $where_sql .= " AND t.status = :status";
    $params[':status'] = $_GET['status'];
}

// ✅ Requête corrigée
$sql = "
    SELECT 
        t.*,
        u.full_name,
        u.email,
        CASE 
            WHEN t.type = 'buy' THEN 'Achat'
            WHEN t.type = 'sell' THEN 'Vente'
            WHEN t.type = 'deposit' THEN 'Dépôt'
            WHEN t.type = 'withdrawal' THEN 'Retrait'
            WHEN t.type = 'dividend' THEN 'Dividende'
            WHEN t.type = 'fee' THEN 'Frais'
            ELSE t.type 
        END AS type_fr
    FROM transactions AS t
    JOIN users AS u ON t.user_id = u.id
    WHERE $where_sql
    ORDER BY t.created_at DESC
    LIMIT 200
";

$transactions_stmt = $pdo->prepare($sql);
$transactions_stmt->execute($params);
$transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);


// Récupérer les types et statuts uniques pour les filtres
$types = $pdo->query("SELECT DISTINCT type FROM transactions WHERE user_id = $user_id ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$statuses = $pdo->query("SELECT DISTINCT status FROM transactions WHERE user_id = $user_id ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transactions - <?php echo htmlspecialchars($user['full_name']); ?> | FLUX TRADING</title>
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
    max-width: 1600px;
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
    gap: 1rem;
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

.user-details h1 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.user-meta {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--text-secondary);
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
    min-width: 250px;
}

.search-input {
    width: 100%;
    padding: 10px 12px 10px 40px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--card-border);
    border-radius: 6px;
    color: var(--text);
    font-size: 0.9rem;
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
    font-size: 0.8rem;
    font-weight: 500;
}

.filter-select {
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--card-border);
    border-radius: 6px;
    color: var(--text);
    font-size: 0.8rem;
    min-width: 100px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
}

.date-input {
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--card-border);
    border-radius: 6px;
    color: var(--text);
    font-size: 0.8rem;
}

.date-input:focus {
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
    min-width: 1000px;
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

.status-pending {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-cancelled {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
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

.type-dividend {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.type-fee {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
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
        <a href="user_trading_details.php?id=<?php echo $user_id; ?>"><?php echo htmlspecialchars($user['full_name']); ?></a>
        <span>/</span>
        <span>Transactions</span>
    </div>

    <!-- En-tête de page -->
    <div class="page-header">
        <div class="user-profile">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <h1>Transactions de <?php echo htmlspecialchars($user['full_name']); ?></h1>
                <div class="user-meta">
                    <span>Solde: <?php echo number_format($user['balance'], 2, ',', ' '); ?> <?php echo $user['currency']; ?></span>
                    <span>•</span>
                    <span>Email: <?php echo htmlspecialchars($user['email']); ?></span>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 1rem;">
            <a href="user_trading_details.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                <i class="fas fa-chart-line"></i> Profil Trading
            </a>
            <a href="manage_users.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <!-- Statistiques filtrées -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($filtered_stats['total'] ?? 0, 0, ',', ' '); ?></div>
            <div class="stat-label">Transactions</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value amount-negative">-<?php echo number_format($filtered_stats['total_buy'] ?? 0, 2, ',', ' '); ?></div>
            <div class="stat-label">Total Achats</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value amount-positive">+<?php echo number_format($filtered_stats['total_sell'] ?? 0, 2, ',', ' '); ?></div>
            <div class="stat-label">Total Ventes</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value amount-negative">-<?php echo number_format($filtered_stats['total_fees'] ?? 0, 2, ',', ' '); ?></div>
            <div class="stat-label">Frais Total</div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filters">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Rechercher par action ou description..." 
                   value="<?php echo htmlspecialchars($search); ?>" 
                   onkeypress="if(event.keyCode==13) applyFilters()">
        </div>
        
        <div class="filter-group">
            <span class="filter-label">Type:</span>
            <select class="filter-select" id="typeFilter" onchange="applyFilters()">
                <option value="all">Tous les types</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?php echo $type; ?>" <?php echo $type_filter === $type ? 'selected' : ''; ?>>
                        <?php echo ucfirst($type); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <span class="filter-label">Statut:</span>
            <select class="filter-select" id="statusFilter" onchange="applyFilters()">
                <option value="all">Tous les statuts</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                        <?php echo ucfirst($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <span class="filter-label">De:</span>
            <input type="date" class="date-input" id="dateFrom" value="<?php echo htmlspecialchars($date_from); ?>" onchange="applyFilters()">
        </div>
        
        <div class="filter-group">
            <span class="filter-label">À:</span>
            <input type="date" class="date-input" id="dateTo" value="<?php echo htmlspecialchars($date_to); ?>" onchange="applyFilters()">
        </div>
        
        <a href="user_transactions.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">
            <i class="fas fa-redo"></i> Réinitialiser
        </a>
        
        <button class="btn btn-primary" onclick="exportTransactions()">
            <i class="fas fa-download"></i> Exporter
        </button>
    </div>

    <!-- Tableau des transactions -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Historique des Transactions (<?php echo count($transactions); ?>)</h2>
        </div>
        
        <?php if (!empty($transactions)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Action</th>
                        <th>Quantité</th>
                        <th>Prix</th>
                        <th>Montant</th>
                        <th>Frais</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td>#<?php echo $transaction['id']; ?></td>
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
                        <td>
                            <?php if ($transaction['quantity'] > 0): ?>
                                <?php echo number_format($transaction['quantity'], 0, ',', ' '); ?>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($transaction['price'] > 0): ?>
                                <?php echo number_format($transaction['price'], 2, ',', ' '); ?> <?php echo $user['currency']; ?>
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
                            <?php if ($transaction['fees'] > 0): ?>
                                <span class="amount-negative">-<?php echo number_format($transaction['fees'], 2, ',', ' '); ?> <?php echo $user['currency']; ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $transaction['status']; ?>">
                                <?php echo ucfirst($transaction['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div><?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo date('H:i', strtotime($transaction['created_at'])); ?></div>
                        </td>
                        <td>
                            <a href="transaction_details.php?id=<?php echo $transaction['id']; ?>" class="btn btn-primary btn-sm">
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
            <i class="fas fa-exchange-alt"></i>
            <p>Aucune transaction trouvée</p>
            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Aucune transaction ne correspond à vos critères de recherche</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function applyFilters() {
    const search = document.querySelector('.search-input').value;
    const type = document.getElementById('typeFilter').value;
    const status = document.getElementById('statusFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    const url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('type', type);
    url.searchParams.set('status', status);
    url.searchParams.set('date_from', dateFrom);
    url.searchParams.set('date_to', dateTo);
    
    window.location.href = url.toString();
}

function exportTransactions() {
    const search = document.querySelector('.search-input').value;
    const type = document.getElementById('typeFilter').value;
    const status = document.getElementById('statusFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    const url = new URL(window.location);
    url.searchParams.set('export', '1');
    url.searchParams.set('search', search);
    url.searchParams.set('type', type);
    url.searchParams.set('status', status);
    url.searchParams.set('date_from', dateFrom);
    url.searchParams.set('date_to', dateTo);
    
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