<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: transaction_history.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$transaction_id = intval($_GET['id']);

// Récupérer les détails de la transaction
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        u.full_name,
        u.email,
        u.phone,
        u.created_at as user_created_at,
        w.balance as current_wallet_balance,
        w.currency
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN wallets w ON t.user_id = w.user_id
    WHERE t.id = ?
");

$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    header('Location: transaction_history.php?error=transaction_not_found');
    exit;
}

// Récupérer les transactions similaires pour contexte
$similar_transactions = $pdo->prepare("
    SELECT 
        id,
        type,
        stock_symbol,
        quantity,
        amount,
        status,
        created_at
    FROM transactions 
    WHERE user_id = ? AND stock_symbol = ? AND id != ?
    ORDER BY created_at DESC
    LIMIT 5
");

$similar_transactions->execute([$transaction['user_id'], $transaction['stock_symbol'], $transaction_id]);
$similar_transactions = $similar_transactions->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le solde du portefeuille avant et après la transaction
$wallet_before = $pdo->prepare("
    SELECT balance 
    FROM wallet_history 
    WHERE wallet_id = ? AND created_at < ? 
    ORDER BY created_at DESC 
    LIMIT 1
");

$wallet_before->execute([$transaction['wallet_id'], $transaction['created_at']]);
$wallet_before = $wallet_before->fetch(PDO::FETCH_ASSOC);

// Actions sur la transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'validate':
            if ($transaction['status'] === 'pending') {
                $update_stmt = $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE id = ?");
                $update_stmt->execute([$transaction_id]);
                
                // Logger l'action
                $log_stmt = $pdo->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->execute(['info', 'transactions', "Transaction #$transaction_id validée par l'administrateur", $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
                
                header('Location: transaction_details.php?id=' . $transaction_id . '&success=validated');
                exit;
            }
            break;
            
        case 'cancel':
            if ($transaction['status'] === 'pending') {
                $update_stmt = $pdo->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ?");
                $update_stmt->execute([$transaction_id]);
                
                // Logger l'action
                $log_stmt = $pdo->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->execute(['info', 'transactions', "Transaction #$transaction_id annulée par l'administrateur", $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
                
                header('Location: transaction_details.php?id=' . $transaction_id . '&success=cancelled');
                exit;
            }
            break;
            
        case 'refund':
            // Implémenter le remboursement si nécessaire
            // Cette fonctionnalité dépendrait de votre logique métier
            break;
    }
}

// Recharger les données après modification
if (isset($_GET['success'])) {
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Détails Transaction #<?php echo $transaction_id; ?> | FLUX TRADING</title>
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
    justify-content: between;
    align-items: center;
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
    justify-content: between;
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
    font-size: 1.1rem;
}

.amount-negative {
    color: #ef4444;
    font-weight: 600;
    font-size: 1.1rem;
}

.amount-neutral {
    color: var(--text-secondary);
    font-weight: 600;
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

.metadata-preview {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    padding: 1rem;
    margin-top: 0.5rem;
    font-family: 'Courier New', monospace;
    font-size: 0.8rem;
    max-height: 200px;
    overflow-y: auto;
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
        <a href="transaction_history.php">Transactions</a>
        <span>/</span>
        <span>Détails #<?php echo $transaction_id; ?></span>
    </div>

    <!-- Messages de succès -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php 
            switch ($_GET['success']) {
                case 'validated':
                    echo "Transaction validée avec succès.";
                    break;
                case 'cancelled':
                    echo "Transaction annulée avec succès.";
                    break;
                default:
                    echo "Action effectuée avec succès.";
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- En-tête de page -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Détails de la Transaction</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                ID: #<?php echo $transaction_id; ?> • 
                Créée le <?php echo date('d/m/Y à H:i', strtotime($transaction['created_at'])); ?>
            </p>
        </div>
        <a href="transaction_history.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour à l'historique
        </a>
    </div>

    <div class="grid-layout">
        <!-- Colonne principale -->
        <div>
            <!-- Informations de la transaction -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> Informations de la Transaction</h2>
                    <div>
                        <span class="type-badge type-<?php echo $transaction['type']; ?>">
                            <?php echo strtoupper($transaction['type']); ?>
                        </span>
                        <span class="status-badge status-<?php echo $transaction['status']; ?>">
                            <?php echo ucfirst($transaction['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-group">
                        <span class="info-label">Montant</span>
                        <span class="<?php echo $transaction['amount'] > 0 ? 'amount-positive' : 'amount-negative'; ?>">
                            <?php echo $transaction['amount'] > 0 ? '+' : ''; ?>
                            <?php echo number_format($transaction['amount'], 2, ',', ' '); ?> 
                            <?php echo $transaction['currency']; ?>
                        </span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Frais</span>
                        <span class="amount-negative">
                            -<?php echo number_format($transaction['fees'], 2, ',', ' '); ?> 
                            <?php echo $transaction['currency']; ?>
                        </span>
                    </div>
                    
                    <?php if ($transaction['stock_symbol'] && $transaction['stock_symbol'] !== '0'): ?>
                    <div class="info-group">
                        <span class="info-label">Action</span>
                        <span class="info-value"><?php echo htmlspecialchars($transaction['stock_symbol']); ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Quantité</span>
                        <span class="info-value"><?php echo number_format($transaction['quantity'], 0, ',', ' '); ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Prix unitaire</span>
                        <span class="info-value">
                            <?php echo number_format($transaction['price'], 2, ',', ' '); ?> 
                            <?php echo $transaction['currency']; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-group">
                        <span class="info-label">Statut</span>
                        <span class="status-badge status-<?php echo $transaction['status']; ?>">
                            <?php echo ucfirst($transaction['status']); ?>
                        </span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Date de création</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($transaction['created_at'])); ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Dernière mise à jour</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($transaction['updated_at'])); ?></span>
                    </div>
                </div>
                
                <?php if ($transaction['description']): ?>
                <div style="margin-top: 1.5rem;">
                    <span class="info-label">Description</span>
                    <p style="margin-top: 0.5rem; color: var(--text);"><?php echo htmlspecialchars($transaction['description']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($transaction['metadata']): ?>
                <div style="margin-top: 1.5rem;">
                    <span class="info-label">Métadonnées</span>
                    <div class="metadata-preview">
                        <pre><?php echo htmlspecialchars(json_encode(json_decode($transaction['metadata']), JSON_PRETTY_PRINT)); ?></pre>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Transactions similaires -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Transactions Similaires</h2>
                </div>
                
                <?php if (!empty($similar_transactions)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Quantité</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($similar_transactions as $similar): ?>
                            <tr>
                                <td>#<?php echo $similar['id']; ?></td>
                                <td>
                                    <span class="type-badge type-<?php echo $similar['type']; ?>">
                                        <?php echo strtoupper($similar['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($similar['quantity'], 0, ',', ' '); ?></td>
                                <td class="<?php echo $similar['amount'] > 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo $similar['amount'] > 0 ? '+' : ''; ?>
                                    <?php echo number_format($similar['amount'], 2, ',', ' '); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $similar['status']; ?>">
                                        <?php echo ucfirst($similar['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($similar['created_at'])); ?></td>
                                <td>
                                    <a href="transaction_details.php?id=<?php echo $similar['id']; ?>" class="btn btn-primary btn-sm">
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
                    <p>Aucune transaction similaire trouvée</p>
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
                        <?php echo strtoupper(substr($transaction['full_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($transaction['full_name']); ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">ID: <?php echo $transaction['user_id']; ?></div>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-group">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($transaction['email']); ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Téléphone</span>
                        <span class="info-value"><?php echo htmlspecialchars($transaction['phone'] ?? 'N/A'); ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Inscription</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($transaction['user_created_at'])); ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Solde actuel</span>
                        <span class="amount-positive">
                            <?php echo number_format($transaction['current_wallet_balance'], 2, ',', ' '); ?> 
                            <?php echo $transaction['currency']; ?>
                        </span>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                    <a href="user_trading_details.php?id=<?php echo $transaction['user_id']; ?>" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-chart-line"></i> Profil Trading
                    </a>
                    <a href="user_transactions.php?id=<?php echo $transaction['user_id']; ?>" class="btn btn-secondary">
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
                    <?php if ($transaction['status'] === 'pending'): ?>
                    <form method="POST" style="grid-column: 1 / -1;">
                        <input type="hidden" name="action" value="validate">
                        <button type="submit" class="btn btn-success" style="width: 100%;">
                            <i class="fas fa-check"></i> Valider la Transaction
                        </button>
                    </form>
                    
                    <form method="POST" style="grid-column: 1 / -1;">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-danger" style="width: 100%;" 
                                onclick="return confirm('Êtes-vous sûr de vouloir annuler cette transaction ?')">
                            <i class="fas fa-times"></i> Annuler la Transaction
                        </button>
                    </form>
                    <?php elseif ($transaction['status'] === 'completed' && $transaction['type'] === 'deposit'): ?>
                    <form method="POST" style="grid-column: 1 / -1;">
                        <input type="hidden" name="action" value="refund">
                        <button type="submit" class="btn btn-danger" style="width: 100%;" 
                                onclick="return confirm('Êtes-vous sûr de vouloir rembourser cette transaction ?')">
                            <i class="fas fa-undo"></i> Rembourser
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="empty-state" style="padding: 1rem;">
                        <i class="fas fa-info-circle"></i>
                        <p>Aucune action disponible</p>
                    </div>
                    <?php endif; ?>
                    
                    <a href="transaction_history.php?search=<?php echo urlencode($transaction['full_name']); ?>" 
                       class="btn btn-secondary" style="grid-column: 1 / -1;">
                        <i class="fas fa-search"></i> Voir toutes les transactions de cet utilisateur
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
                        <span class="info-label">ID Transaction</span>
                        <span class="info-value">#<?php echo $transaction['id']; ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">ID Utilisateur</span>
                        <span class="info-value">#<?php echo $transaction['user_id']; ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">ID Portefeuille</span>
                        <span class="info-value">#<?php echo $transaction['wallet_id']; ?></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Type</span>
                        <span class="info-value"><?php echo $transaction['type']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour confirmer les actions critiques
function confirmAction(action, transactionId) {
    const messages = {
        'cancel': 'Êtes-vous sûr de vouloir annuler cette transaction ?',
        'refund': 'Êtes-vous sûr de vouloir rembourser cette transaction ?',
        'validate': 'Êtes-vous sûr de vouloir valider cette transaction ?'
    };
    
    return confirm(messages[action] || 'Confirmer cette action ?');
}

// Ajouter des confirmations aux formulaires
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const action = form.querySelector('input[name="action"]')?.value;
        if (action && ['cancel', 'refund'].includes(action)) {
            form.addEventListener('submit', function(e) {
                if (!confirmAction(action, <?php echo $transaction_id; ?>)) {
                    e.preventDefault();
                }
            });
        }
    });
});
</script>
</body>
</html>