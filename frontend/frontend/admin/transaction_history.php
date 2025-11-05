
<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

// Statistiques des transactions
$total_transactions = $pdo->query("SELECT COUNT(*) AS total FROM transactions")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_volume = $pdo->query("SELECT SUM(ABS(amount)) AS total FROM transactions WHERE status = 'completed'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$pending_transactions = $pdo->query("SELECT COUNT(*) AS total FROM transactions WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$today_transactions = $pdo->query("SELECT COUNT(*) AS total FROM transactions WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Récupérer les transactions avec filtres
$type_filter = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if ($type_filter !== 'all') {
    $where_conditions[] = "t.type = ?";
    $params[] = $type_filter;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR t.stock_symbol LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

$transactions = $pdo->prepare("
    SELECT 
        t.*,
        u.full_name,
        u.email,
        w.balance as user_balance
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN wallets w ON t.user_id = w.user_id
    $where_sql
    ORDER BY t.created_at DESC
    LIMIT 100
");

$transactions->execute($params);
$transactions = $transactions->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les types de transactions uniques pour le filtre
$transaction_types = $pdo->query("SELECT DISTINCT type FROM transactions ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$status_types = $pdo->query("SELECT DISTINCT status FROM transactions ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Historique des Transactions | FLUX TRADING</title>
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
.icon-volume { background: linear-gradient(135deg, #10b981, #34d399); }
.icon-pending { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.icon-today { background: linear-gradient(135deg, #ec4899, #f472b6); }

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

.status-failed {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
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
            <a href="transaction_history.php" class="nav-link active">Transactions</a>
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
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="stat-content">
                <h3>Total Transactions</h3>
                <div class="number"><?php echo number_format($total_transactions, 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-volume">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="stat-content">
                <h3>Volume Total</h3>
                <div class="number"><?php echo number_format($total_volume, 2, ',', ' '); ?> FCFA</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3>En Attente</h3>
                <div class="number"><?php echo number_format($pending_transactions, 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-today">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <h3>Aujourd'hui</h3>
                <div class="number"><?php echo number_format($today_transactions, 0, ',', ' '); ?></div>
            </div>
        </div>
    </div>

    <!-- Filtres et recherche -->
    <div class="filters">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Rechercher par utilisateur ou symbole..." 
                   value="<?php echo htmlspecialchars($search); ?>" 
                   onkeypress="if(event.keyCode==13) applyFilters()">
        </div>
        
        <div class="filter-group">
            <span class="filter-label">Type:</span>
            <select class="filter-select" id="typeFilter" onchange="applyFilters()">
                <option value="all">Tous les types</option>
                <?php foreach ($transaction_types as $type): ?>
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
                <?php foreach ($status_types as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                        <?php echo ucfirst($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <a href="transaction_history.php" class="filter-btn">
            <i class="fas fa-redo"></i> Réinitialiser
        </a>
    </div>

    <!-- Tableau des transactions -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-history"></i> Historique des Transactions (<?php echo count($transactions); ?>)</h2>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Utilisateur</th>
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
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($transaction['full_name'] ?? 'U', 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($transaction['full_name'] ?? 'N/A'); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($transaction['email'] ?? ''); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="type-badge type-<?php echo $transaction['type']; ?>">
                                <?php echo strtoupper($transaction['type']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($transaction['stock_symbol'] && $transaction['stock_symbol'] !== '0'): ?>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($transaction['stock_symbol']); ?></div>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($transaction['quantity'] > 0): ?>
                                <div><?php echo number_format($transaction['quantity'], 0, ',', ' '); ?></div>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($transaction['price'] > 0): ?>
                                <div><?php echo number_format($transaction['price'], 2, ',', ' '); ?> FCFA</div>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($transaction['amount'] != 0): ?>
                                <div class="<?php echo $transaction['amount'] > 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo number_format($transaction['amount'], 2, ',', ' '); ?> FCFA
                                </div>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($transaction['fees'] > 0): ?>
                                <div class="amount-negative">-<?php echo number_format($transaction['fees'], 2, ',', ' '); ?> FCFA</div>
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
                            <div style="display: flex; gap: 5px;">
                                <a href="transaction_details.php?id=<?php echo $transaction['id']; ?>" class="btn btn-primary btn-sm" title="Détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($transaction['status'] === 'pending'): ?>
                                    <button class="btn btn-primary btn-sm" title="Valider" onclick="validateTransaction(<?php echo $transaction['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-primary btn-sm" title="Annuler" onclick="cancelTransaction(<?php echo $transaction['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
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
function applyFilters() {
    const search = document.querySelector('.search-input').value;
    const type = document.getElementById('typeFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('type', type);
    url.searchParams.set('status', status);
    
    window.location.href = url.toString();
}

function validateTransaction(transactionId) {
    if (confirm('Êtes-vous sûr de vouloir valider cette transaction ?')) {
        window.location.href = `validate_transaction.php?id=${transactionId}&action=validate`;
    }
}

function cancelTransaction(transactionId) {
    if (confirm('Êtes-vous sûr de vouloir annuler cette transaction ?')) {
        window.location.href = `validate_transaction.php?id=${transactionId}&action=cancel`;
    }
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