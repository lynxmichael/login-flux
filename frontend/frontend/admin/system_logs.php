<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

// Vérifier si la table system_logs existe, sinon la créer
$pdo->exec("
    CREATE TABLE IF NOT EXISTS system_logs (
        id INT(11) NOT NULL AUTO_INCREMENT,
        level ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
        module VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        user_id INT(11) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_level (level),
        KEY idx_module (module),
        KEY idx_created_at (created_at),
        KEY idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Statistiques des logs
$total_logs = $pdo->query("SELECT COUNT(*) AS total FROM system_logs")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$error_logs = $pdo->query("SELECT COUNT(*) AS total FROM system_logs WHERE level = 'error'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$warning_logs = $pdo->query("SELECT COUNT(*) AS total FROM system_logs WHERE level = 'warning'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$today_logs = $pdo->query("SELECT COUNT(*) AS total FROM system_logs WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Récupérer les logs avec filtres
$level_filter = $_GET['level'] ?? 'all';
$module_filter = $_GET['module'] ?? 'all';
$search = $_GET['search'] ?? '';
$date_filter = $_GET['date'] ?? '';

$where_conditions = [];
$params = [];

if ($level_filter !== 'all') {
    $where_conditions[] = "l.level = ?";
    $params[] = $level_filter;
}

if ($module_filter !== 'all') {
    $where_conditions[] = "l.module = ?";
    $params[] = $module_filter;
}

if ($search) {
    $where_conditions[] = "(l.message LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($date_filter) {
    $where_conditions[] = "DATE(l.created_at) = ?";
    $params[] = $date_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

// Récupérer les logs
$logs = $pdo->prepare("
    SELECT 
        l.*,
        u.full_name,
        u.email
    FROM system_logs l
    LEFT JOIN users u ON l.user_id = u.id
    $where_sql
    ORDER BY l.created_at DESC
    LIMIT 500
");

$logs->execute($params);
$logs = $logs->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les modules et niveaux uniques pour les filtres
$modules = $pdo->query("SELECT DISTINCT module FROM system_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$levels = $pdo->query("SELECT DISTINCT level FROM system_logs ORDER BY level")->fetchAll(PDO::FETCH_COLUMN);

// Action de purge des logs
if (isset($_POST['action']) && $_POST['action'] === 'purge_logs') {
    $days = intval($_POST['days'] ?? 30);
    $stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    $deleted_count = $stmt->rowCount();
    
    // Logger l'action
    $log_stmt = $pdo->prepare("INSERT INTO system_logs (level, module, message, user_id, ip_address) VALUES (?, ?, ?, ?, ?)");
    $log_stmt->execute(['info', 'system', "Purge des logs: $deleted_count entrées supprimées (plus de $days jours)", $_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
    
    header('Location: system_logs.php?purged=' . $deleted_count);
    exit;
}

// Action d'export des logs
if (isset($_GET['export'])) {
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Niveau', 'Module', 'Message', 'Utilisateur', 'IP', 'Date']);
    
    $export_logs = $pdo->query("
        SELECT l.*, u.full_name 
        FROM system_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        ORDER BY l.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($export_logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['level'],
            $log['module'],
            $log['message'],
            $log['full_name'] ?? 'Système',
            $log['ip_address'],
            $log['created_at']
        ]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logs Système | FLUX TRADING</title>
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
    --critical: #dc2626;
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
.icon-error { background: linear-gradient(135deg, #ef4444, #dc2626); }
.icon-warning { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.icon-today { background: linear-gradient(135deg, #10b981, #34d399); }

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

.actions-bar {
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

.date-input {
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--card-border);
    border-radius: 6px;
    color: var(--text);
    font-size: 0.875rem;
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

.btn-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
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
    max-height: 600px;
    overflow-y: auto;
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
    position: sticky;
    top: 0;
    z-index: 10;
}

td {
    font-size: 0.9rem;
}

.log-level {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.level-info {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.level-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.level-error {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.level-critical {
    background: rgba(220, 38, 38, 0.1);
    color: #dc2626;
}

.log-message {
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.log-message:hover {
    white-space: normal;
    overflow: visible;
}

.user-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    font-size: 0.8rem;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 2rem;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.modal-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
}

.close-modal {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close-modal:hover {
    color: var(--text);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
}

.form-input {
    width: 100%;
    padding: 10px 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--card-border);
    border-radius: 6px;
    color: var(--text);
    font-size: 0.95rem;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
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

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid var(--success);
    color: var(--success);
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
    
    .actions-bar {
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
            <a href="trading_orders.php" class="nav-link">Ordres</a>
            <a href="system_logs.php" class="nav-link active">Logs</a>
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
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-content">
                <h3>Total Logs</h3>
                <div class="number"><?php echo number_format($total_logs, 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-error">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <h3>Erreurs</h3>
                <div class="number"><?php echo number_format($error_logs, 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-warning">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-content">
                <h3>Avertissements</h3>
                <div class="number"><?php echo number_format($warning_logs, 0, ',', ' '); ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-today">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <h3>Aujourd'hui</h3>
                <div class="number"><?php echo number_format($today_logs, 0, ',', ' '); ?></div>
            </div>
        </div>
    </div>

    <!-- Barre d'actions -->
    <div class="actions-bar">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Rechercher dans les logs..." 
                   value="<?php echo htmlspecialchars($search); ?>" 
                   onkeypress="if(event.keyCode==13) applyFilters()">
        </div>
        
        <div class="filter-group">
            <span class="filter-label">Niveau:</span>
            <select class="filter-select" id="levelFilter" onchange="applyFilters()">
                <option value="all">Tous les niveaux</option>
                <?php foreach ($levels as $level): ?>
                    <option value="<?php echo $level; ?>" <?php echo $level_filter === $level ? 'selected' : ''; ?>>
                        <?php echo ucfirst($level); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <span class="filter-label">Module:</span>
            <select class="filter-select" id="moduleFilter" onchange="applyFilters()">
                <option value="all">Tous les modules</option>
                <?php foreach ($modules as $module): ?>
                    <option value="<?php echo $module; ?>" <?php echo $module_filter === $module ? 'selected' : ''; ?>>
                        <?php echo ucfirst($module); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <span class="filter-label">Date:</span>
            <input type="date" class="date-input" id="dateFilter" value="<?php echo htmlspecialchars($date_filter); ?>" onchange="applyFilters()">
        </div>
        
        <button class="btn btn-secondary" onclick="showPurgeModal()">
            <i class="fas fa-trash"></i> Purger
        </button>
        
        <a href="?export=1" class="btn btn-secondary">
            <i class="fas fa-download"></i> Exporter
        </a>
        
        <a href="system_logs.php" class="btn btn-secondary">
            <i class="fas fa-redo"></i> Actualiser
        </a>
    </div>

    <!-- Message de succès pour la purge -->
    <?php if (isset($_GET['purged'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <?php echo intval($_GET['purged']); ?> entrées de log ont été supprimées avec succès.
        </div>
    <?php endif; ?>

    <!-- Tableau des logs -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-terminal"></i> Logs Système (<?php echo count($logs); ?>)</h2>
            <div>
                <span style="color: var(--text-secondary); font-size: 0.875rem;">
                    Dernière mise à jour: <?php echo date('H:i:s'); ?>
                </span>
            </div>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Niveau</th>
                        <th>Module</th>
                        <th>Message</th>
                        <th>Utilisateur</th>
                        <th>IP</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>#<?php echo $log['id']; ?></td>
                        <td>
                            <span class="log-level level-<?php echo $log['level']; ?>">
                                <?php echo strtoupper($log['level']); ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 500;"><?php echo htmlspecialchars($log['module']); ?></span>
                        </td>
                        <td>
                            <div class="log-message" title="<?php echo htmlspecialchars($log['message']); ?>">
                                <?php echo htmlspecialchars($log['message']); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($log['user_id'] && $log['full_name']): ?>
                                <div class="user-badge">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($log['full_name']); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">Système</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-size: 0.8rem;"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <div><?php echo date('d/m/Y', strtotime($log['created_at'])); ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <button class="btn btn-primary btn-sm" title="Détails" onclick="showLogDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" title="Supprimer" onclick="deleteLog(<?php echo $log['id']; ?>)">
                                    <i class="fas fa-trash"></i>
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

<!-- Modal de purge -->
<div id="purgeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Purger les logs</h3>
            <button class="close-modal" onclick="closePurgeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="purge_logs">
            <div class="form-group">
                <label class="form-label">Supprimer les logs plus anciens que:</label>
                <select class="form-input" name="days" required>
                    <option value="7">7 jours</option>
                    <option value="30" selected>30 jours</option>
                    <option value="90">90 jours</option>
                    <option value="365">1 an</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closePurgeModal()">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer la purge</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de détails du log -->
<div id="logDetailsModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Détails du Log</h3>
            <button class="close-modal" onclick="closeLogDetailsModal()">&times;</button>
        </div>
        <div id="logDetailsContent">
            <!-- Les détails seront injectés ici par JavaScript -->
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const search = document.querySelector('.search-input').value;
    const level = document.getElementById('levelFilter').value;
    const module = document.getElementById('moduleFilter').value;
    const date = document.getElementById('dateFilter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('level', level);
    url.searchParams.set('module', module);
    url.searchParams.set('date', date);
    
    window.location.href = url.toString();
}

function showPurgeModal() {
    document.getElementById('purgeModal').style.display = 'flex';
}

function closePurgeModal() {
    document.getElementById('purgeModal').style.display = 'none';
}

function showLogDetails(log) {
    const content = document.getElementById('logDetailsContent');
    content.innerHTML = `
        <div style="display: grid; gap: 1rem;">
            <div>
                <strong>ID:</strong> #${log.id}
            </div>
            <div>
                <strong>Niveau:</strong> <span class="log-level level-${log.level}">${log.level.toUpperCase()}</span>
            </div>
            <div>
                <strong>Module:</strong> ${log.module}
            </div>
            <div>
                <strong>Message:</strong>
                <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 6px; margin-top: 0.5rem; font-family: monospace; white-space: pre-wrap;">${log.message}</div>
            </div>
            <div>
                <strong>Utilisateur:</strong> ${log.full_name || 'Système'}
            </div>
            <div>
                <strong>Adresse IP:</strong> ${log.ip_address || 'N/A'}
            </div>
            <div>
                <strong>User Agent:</strong>
                <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 6px; margin-top: 0.5rem; font-size: 0.8rem; font-family: monospace; word-break: break-all;">${log.user_agent || 'N/A'}</div>
            </div>
            <div>
                <strong>Date:</strong> ${new Date(log.created_at).toLocaleString()}
            </div>
        </div>
    `;
    document.getElementById('logDetailsModal').style.display = 'flex';
}

function closeLogDetailsModal() {
    document.getElementById('logDetailsModal').style.display = 'none';
}

function deleteLog(logId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce log ?')) {
        window.location.href = `delete_log.php?id=${logId}`;
    }
}

// Auto-apply filters when typing stops
let searchTimeout;
document.querySelector('.search-input').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
});

// Fermer les modals en cliquant à l'extérieur
window.onclick = function(event) {
    const purgeModal = document.getElementById('purgeModal');
    const logDetailsModal = document.getElementById('logDetailsModal');
    
    if (event.target === purgeModal) {
        closePurgeModal();
    }
    if (event.target === logDetailsModal) {
        closeLogDetailsModal();
    }
}

// Actualisation automatique toutes les 30 secondes
setTimeout(() => {
    window.location.reload();
}, 30000);
</script>
</body>
</html>