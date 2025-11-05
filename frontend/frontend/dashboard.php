<?php
session_start();
require_once 'config/database.php';
require_once 'php/auth_check.php';
require_once 'models/Wallet.php'; // ← Ajout de l'import du modèle Wallet

// Connexion à la base
$database = new Database();
$pdo = $database->getConnection();

// Récupérer les infos de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, full_name, email, phone, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Récupérer le solde du wallet via le modèle Wallet
$walletModel = new Wallet();
$walletResult = $walletModel->getBalance($user_id);

if ($walletResult['success']) {
    $wallet_balance = $walletResult['balance'];
    $wallet_currency = $walletResult['currency'];
} else {
    // En cas d'erreur, initialiser avec des valeurs par défaut
    $wallet_balance = 0;
    $wallet_currency = 'FCFA';
}

// Récupérer les transactions récentes
$transactions_stmt = $pdo->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$transactions_stmt->execute([$user_id]);
$transactions = $transactions_stmt->fetchAll();

// Récupérer les actifs de l'utilisateur
$assets_stmt = $pdo->prepare("
    SELECT stock_symbol, SUM(quantity) as total_quantity, AVG(price) as avg_price
    FROM transactions 
    WHERE user_id = ? AND type = 'buy'
    GROUP BY stock_symbol
    HAVING SUM(quantity) > 0
");
$assets_stmt->execute([$user_id]);
$assets = $assets_stmt->fetchAll();

// Calculer la valeur totale du portefeuille
$total_portfolio_value = $wallet_balance; // ← Utilisation de $wallet_balance
foreach ($assets as $asset) {
    $current_price = 720; // À remplacer par le prix réel
    $total_portfolio_value += $asset['total_quantity'] * $current_price;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - FluxIO</title>
    <link rel="stylesheet" href="css/ultimestyle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dashboard-page">
    <div class="dashboard-container">
        <!-- Header du dashboard -->
        <header class="dashboard-header">
            <div class="dashboard-header-top">
                <div class="dashboard-title">
                    <h1>Tableau de Bord Financier</h1>
                    <span class="user-welcome">Bonjour, <?php echo htmlspecialchars($user['full_name']); ?></span>
                </div>
                <div class="dashboard-actions">
                    <a href="ultimepage.php" class="dashboard-action-btn back-to-trading">
                        <span>←</span>
                        Retour au Trading
                    </a>
                    <button class="dashboard-action-btn refresh-btn" id="refreshDashboard">
                        <span>🔄</span>
                        Actualiser
                    </button>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="dashboard-quick-actions">
                <div class="quick-actions-header">
                    <h3>Actions Rapides</h3>
                </div>
                <div class="quick-actions-grid">
                    <a href="#" class="quick-action-btn deposit-action" onclick="openDepositModal()">
                        <span class="action-icon">💳</span>
                        <span class="action-label">Déposer</span>
                    </a>
                    <a href="#" class="quick-action-btn withdraw-action" onclick="openWithdrawModal()">
                        <span class="action-icon">🏧</span>
                        <span class="action-label">Retirer</span>
                    </a>
                    <a href="#" class="quick-action-btn transfer-action" onclick="openTransferModal()">
                        <span class="action-icon">🔄</span>
                        <span class="action-label">Transférer</span>
                    </a>
                    <a href="ultimepage.php" class="quick-action-btn trade-action">
                        <span class="action-icon">📊</span>
                        <span class="action-label">Trader</span>
                    </a>
                    <a href="#history" class="quick-action-btn history-action">
                        <span class="action-icon">📋</span>
                        <span class="action-label">Historique</span>
                    </a>
                    <a href="#" class="quick-action-btn report-action" onclick="generateReport()">
                        <span class="action-icon">📄</span>
                        <span class="action-label">Rapport</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- KPI Principaux -->
        <div class="kpi-grid">
            <div class="kpi-card balance-card">
                <div class="kpi-icon">💰</div>
                <div class="kpi-content">
                    <div class="kpi-label">Solde Total</div>
                    <div class="kpi-value" id="kpiBalance">
                        <?php echo number_format($total_portfolio_value, 0, ',', ' '); ?> FCFA
                    </div>
                    <div class="kpi-change positive" id="kpiBalanceChange">
                        <span>+2.3%</span> ce mois
                    </div>
                </div>
            </div>


            <div class="kpi-card profit-card">
                <div class="kpi-icon">📈</div>
                <div class="kpi-content">
                    <div class="kpi-label">Gains du Jour</div>
                    <div class="kpi-value" id="kpiDailyProfit">+12,450 FCFA</div>
                    <div class="kpi-change positive">
                        <span>+1.8%</span> aujourd'hui
                    </div>
                </div>
            </div>

            <div class="kpi-card trades-card">
                <div class="kpi-icon">⚡</div>
                <div class="kpi-content">
                    <div class="kpi-label">Transactions</div>
                    <div class="kpi-value" id="kpiTradesCount"><?php echo count($transactions); ?></div>
                    <div class="kpi-change neutral">
                        <span><?php echo count($assets); ?></span> actifs
                    </div>
                </div>
            </div>

            <div class="kpi-card performance-card">
                <div class="kpi-icon">🏆</div>
                <div class="kpi-content">
                    <div class="kpi-label">Performance</div>
                    <div class="kpi-value" id="kpiPerformance">78%</div>
                    <div class="kpi-change positive">
                        <span>+12%</span> vs mois dernier
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique de performance -->
        <div class="chart-section">
            <div class="section-header">
                <h2>Performance du Portefeuille</h2>
                <div class="chart-controls">
                    <button class="chart-period active" data-period="1d">24H</button>
                    <button class="chart-period" data-period="1w">1S</button>
                    <button class="chart-period" data-period="1m">1M</button>
                    <button class="chart-period" data-period="3m">3M</button>
                </div>
            </div>
            <div class="chart-container-large">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Contenu principal -->
        <div class="dashboard-content">
            <!-- Transactions récentes -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h3>Transactions Récentes</h3>
                    <button class="view-all-btn" onclick="viewAllTransactions()">Voir tout</button>
                </div>
                <div class="transactions-widget">
                    <?php if (empty($transactions)): ?>
                        <div class="loading-data">Aucune transaction récente</div>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <div class="transaction-type <?php echo $transaction['type']; ?>">
                                        <?php 
                                        $typeLabels = [
                                            'buy' => '🟢 Achat',
                                            'sell' => '🔴 Vente', 
                                            'deposit' => '💳 Dépôt',
                                            'withdraw' => '🏧 Retrait',
                                            'transfer' => '🔄 Transfert'
                                        ];
                                        echo $typeLabels[$transaction['type']] ?? $transaction['type'];
                                        ?> 
                                        <?php echo $transaction['stock_symbol'] ?? ''; ?>
                                    </div>
                                    <div class="transaction-details">
                                        <?php echo $transaction['quantity'] ?? ''; ?>
                                        <?php echo isset($transaction['quantity']) ? 'actions' : ''; ?> 
                                        • 
                                        <?php echo date('H:i', strtotime($transaction['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="transaction-amount <?php echo ($transaction['type'] === 'buy' || $transaction['type'] === 'withdraw' || $transaction['type'] === 'transfer') ? 'negative' : 'positive'; ?>">
                                    <?php echo ($transaction['type'] === 'buy' || $transaction['type'] === 'withdraw' || $transaction['type'] === 'transfer') ? '-' : '+'; ?>
                                    <?php echo number_format($transaction['amount'] ?? ($transaction['quantity'] * $transaction['price']), 0, ',', ' '); ?> FCFA
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actifs en portefeuille -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h3>Mes Actifs</h3>
                    <span class="assets-total"><?php echo count($assets); ?> actifs</span>
                </div>
                <div class="assets-widget">
                    <?php if (empty($assets)): ?>
                        <div class="loading-data">Aucun actif en portefeuille</div>
                    <?php else: ?>
                        <?php foreach ($assets as $asset): ?>
                            <div class="asset-item">
                                <div class="asset-info">
                                    <div class="asset-symbol"><?php echo $asset['stock_symbol']; ?></div>
                                    <div class="asset-quantity"><?php echo $asset['total_quantity']; ?> actions</div>
                                </div>
                                <div class="asset-value">
                                    <?php 
                                    $current_price = 720; // À remplacer par le prix actuel
                                    $total_value = $asset['total_quantity'] * $current_price;
                                    echo number_format($total_value, 0, ',', ' '); ?> FCFA
                                    <div class="asset-change positive">
                                        +<?php echo number_format((($current_price - $asset['avg_price']) / $asset['avg_price']) * 100, 2); ?>%
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h3>Notifications</h3>
                    <div class="notifications-actions">
                        <span class="notifications-badge">3</span>
                        <button class="mark-read-btn" onclick="markAllNotificationsAsRead()">Tout lire</button>
                    </div>
                </div>
                <div class="notifications-widget">
                    <div class="notification-item unread">
                        <div class="notification-content">
                            Votre ordre d'achat SEMC a été exécuté avec succès
                        </div>
                        <div class="notification-time">14:30</div>
                    </div>
                    <div class="notification-item unread">
                        <div class="notification-content">
                            Nouvelle analyse disponible pour ABJC
                        </div>
                        <div class="notification-time">13:15</div>
                    </div>
                    <div class="notification-item">
                        <div class="notification-content">
                            Mise à jour de sécurité disponible
                        </div>
                        <div class="notification-time">12:45</div>
                    </div>
                    <div class="notification-item">
                        <div class="notification-content">
                            Votre dépôt de 50,000 FCFA a été confirmé
                        </div>
                        <div class="notification-time">11:20</div>
                    </div>
                </div>
            </div>

            <!-- Tendances marché -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h3>Tendances Marché</h3>
                    <span class="market-status">📈 Hausier</span>
                </div>
                <div class="market-trends">
                    <div class="trend-item">
                        <span class="trend-symbol">SEMC</span>
                        <span class="trend-price">720 FCFA</span>
                        <span class="trend-change positive">+1.2%</span>
                    </div>
                    <div class="trend-item">
                        <span class="trend-symbol">ABJC</span>
                        <span class="trend-price">1,600 FCFA</span>
                        <span class="trend-change positive">+0.8%</span>
                    </div>
                    <div class="trend-item">
                        <span class="trend-symbol">BICC</span>
                        <span class="trend-price">6,600 FCFA</span>
                        <span class="trend-change negative">-0.3%</span>
                    </div>
                    <div class="trend-item">
                        <span class="trend-symbol">BNBC</span>
                        <span class="trend-price">6,100 FCFA</span>
                        <span class="trend-change positive">+0.5%</span>
                    </div>
                    <div class="trend-item">
                        <span class="trend-symbol">BOAB</span>
                        <span class="trend-price">6,110 FCFA</span>
                        <span class="trend-change positive">+0.2%</span>
                    </div>
                </div>
            </div>

            <!-- Historique complet -->
            <div class="dashboard-widget" id="history">
                <div class="widget-header">
                    <h3>Historique Complet</h3>
                    <button class="view-all-btn" onclick="exportHistory()">Exporter</button>
                </div>
                <div class="transactions-widget">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Action</th>
                                <th>Quantité</th>
                                <th>Prix</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
                                    <td>
                                        <span class="transaction-type <?php echo $transaction['type']; ?>">
                                            <?php echo strtoupper($transaction['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $transaction['stock_symbol'] ?? '-'; ?></td>
                                    <td><?php echo $transaction['quantity'] ?? '-'; ?></td>
                                    <td><?php echo isset($transaction['price']) ? number_format($transaction['price'], 0, ',', ' ') . ' FCFA' : '-'; ?></td>
                                    <td class="<?php echo ($transaction['type'] === 'buy' || $transaction['type'] === 'withdraw' || $transaction['type'] === 'transfer') ? 'negative' : 'positive'; ?>">
                                        <?php echo ($transaction['type'] === 'buy' || $transaction['type'] === 'withdraw' || $transaction['type'] === 'transfer') ? '-' : '+'; ?>
                                        <?php echo number_format($transaction['amount'] ?? ($transaction['quantity'] * $transaction['price']), 0, ',', ' '); ?> FCFA
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pied de dashboard -->
        <footer class="dashboard-footer">
            <div class="footer-info">
                <span class="last-update">Dernière mise à jour: <?php echo date('H:i:s'); ?></span>
            </div>
            <div class="footer-actions">
                <button class="footer-action-btn" onclick="exportData()">
                    <span>📥</span>
                    Exporter Données
                </button>
                <button class="footer-action-btn" onclick="openHelpCenter()">
                    <span>❓</span>
                    Centre d'Aide
                </button>
            </div>
        </footer>
    </div>

    <!-- Modal pour dépôt -->
    <div id="depositModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Déposer des Fonds</h3>
            <form id="depositForm">
                <div class="form-group">
                    <label for="depositAmount">Montant (FCFA)</label>
                    <input type="number" id="depositAmount" min="1000" required>
                </div>
                <div class="form-group">
                    <label for="depositMethod">Méthode de paiement</label>
                    <select id="depositMethod" required onchange="toggleOperatorField(this.value, 'deposit')">
                        <option value="mobile_money">Mobile Money</option>
                        <option value="bank_transfer">Virement Bancaire</option>
                        <option value="card">Carte Bancaire</option>
                    </select>
                </div>
                
                <!-- Champ pour sélectionner l'opérateur (visible seulement pour Mobile Money) -->
                <div class="form-group" id="depositOperatorField" style="display: none;">
                    <label for="depositOperator">Opérateur</label>
                    <select id="depositOperator">
                        <option value="orange">Orange Money</option>
                        <option value="mtn">MTN Mobile Money</option>
                        <option value="wave">Wave</option>
                        <option value="moov">Moov Money</option>
                    </select>
                </div>

                <!-- Champ pour le numéro de téléphone -->
                <div class="form-group" id="depositPhoneField" style="display: none;">
                    <label for="depositPhone">Numéro de téléphone</label>
                    <input type="tel" id="depositPhone" placeholder="Ex: 07 12 34 56 78">
                </div>

                <div class="modal-buttons">
                    <button type="button" onclick="closeModal('depositModal')">Annuler</button>
                    <button type="submit">Confirmer</button>
                </div>
            </form>
        </div>
    </div>

   <!-- Modal pour retrait (mis à jour avec wallet_balance) -->
        <div id="withdrawModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h3>Retirer des Fonds</h3>
                <form id="withdrawForm">
                    <div class="form-group">
                        <label for="withdrawAmount">Montant (FCFA)</label>
                        <input type="number" id="withdrawAmount" min="1000" max="<?php echo $wallet_balance; ?>" required>
                        <small>Solde disponible: <?php echo number_format($wallet_balance, 0, ',', ' '); ?> FCFA</small>
                    </div>
                    <div class="form-group">
                        <label for="withdrawMethod">Méthode de retrait</label>
                        <select id="withdrawMethod" required onchange="toggleOperatorField(this.value, 'withdraw')">
                            <option value="mobile_money">Mobile Money</option>
                            <option value="bank_transfer">Virement Bancaire</option>
                        </select>
                    </div>
                    
                    <!-- Champ pour sélectionner l'opérateur -->
                    <div class="form-group" id="withdrawOperatorField" style="display: none;">
                        <label for="withdrawOperator">Opérateur</label>
                        <select id="withdrawOperator">
                            <option value="orange">Orange Money</option>
                            <option value="mtn">MTN Mobile Money</option>
                            <option value="wave">Wave</option>
                            <option value="moov">Moov Money</option>
                        </select>
                    </div>

                    <!-- Champ pour le numéro de téléphone -->
                    <div class="form-group" id="withdrawPhoneField" style="display: none;">
                        <label for="withdrawPhone">Numéro de téléphone</label>
                        <input type="tel" id="withdrawPhone" placeholder="Ex: 07 12 34 56 78">
                    </div>

                    <div class="modal-buttons">
                        <button type="button" onclick="closeModal('withdrawModal')">Annuler</button>
                        <button type="submit">Confirmer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal pour transfert (mis à jour avec wallet_balance) -->
        <div id="transferModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h3>Transférer des Fonds</h3>
                <form id="transferForm">
                    <div class="form-group">
                        <label for="transferAmount">Montant (FCFA)</label>
                        <input type="number" id="transferAmount" min="1000" max="<?php echo $wallet_balance; ?>" required>
                        <small>Solde disponible: <?php echo number_format($wallet_balance, 0, ',', ' '); ?> FCFA</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="transferOperator">Opérateur du bénéficiaire</label>
                        <select id="transferOperator" required>
                            <option value="orange">Orange Money</option>
                            <option value="mtn">MTN Mobile Money</option>
                            <option value="wave">Wave</option>
                            <option value="moov">Moov Money</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="transferPhone">Numéro du bénéficiaire</label>
                        <input type="tel" id="transferPhone" placeholder="Ex: 07 12 34 56 78" required>
                    </div>

                    <div class="form-group">
                        <label for="transferNote">Note (optionnel)</label>
                        <input type="text" id="transferNote" placeholder="Motif du transfert">
                    </div>

                    <div class="modal-buttons">
                        <button type="button" onclick="closeModal('transferModal')">Annuler</button>
                        <button type="submit">Confirmer</button>
                    </div>
                </form>
            </div>
        </div>
<!-- Container pour les notifications -->
<div class="notification-container" id="notificationContainer"></div>
  <script>
    // ==================== VALIDATION DES NUMÉROS DE TÉLÉPHONE PAR OPÉRATEUR ====================

    // Définition des opérateurs et de leurs préfixes
    const operatorsConfig = {
        'orange': {
            name: 'Orange Money',
            prefixes: ['07'],
            pattern: /^07[0-9]{8}$/,
            example: '07 12 34 56 78'
        },
        'mtn': {
            name: 'MTN Mobile Money', 
            prefixes: ['05'],
            pattern: /^05[0-9]{8}$/,
            example: '05 12 34 56 78'
        },
        'moov': {
            name: 'Moov Money',
            prefixes: ['01'],
            pattern: /^01[0-9]{8}$/,
            example: '01 12 34 56 78'
        },
        'wave': {
            name: 'Wave',
            prefixes: ['01', '05', '07'],
            pattern: /^(01|05|07)[0-9]{8}$/,
            example: '01/05/07 12 34 56 78'
        }
    };

    // Fonction pour valider un numéro selon l'opérateur
    function validatePhoneNumber(phone, operator) {
        if (!phone || !operator) return false;
        
        // Nettoyer le numéro (supprimer les espaces)
        const cleanPhone = phone.replace(/\s/g, '');
        
        // Vérifier la longueur (10 chiffres)
        if (cleanPhone.length !== 10) {
            return {
                valid: false,
                message: 'Le numéro doit contenir 10 chiffres'
            };
        }
        
        // Vérifier que ce sont bien des chiffres
        if (!/^\d+$/.test(cleanPhone)) {
            return {
                valid: false,
                message: 'Le numéro ne doit contenir que des chiffres'
            };
        }
        
        // Vérifier le préfixe selon l'opérateur
        const operatorConfig = operatorsConfig[operator];
        if (!operatorConfig) {
            return {
                valid: false,
                message: 'Opérateur non reconnu'
            };
        }
        
        const isValidPrefix = operatorConfig.prefixes.some(prefix => 
            cleanPhone.startsWith(prefix)
        );
        
        if (!isValidPrefix) {
            const prefixes = operatorConfig.prefixes.join(' ou ');
            return {
                valid: false,
                message: `Numéro ${operatorConfig.name} doit commencer par ${prefixes}`
            };
        }
        
        // Vérifier le pattern complet
        if (!operatorConfig.pattern.test(cleanPhone)) {
            return {
                valid: false,
                message: `Format de numéro invalide pour ${operatorConfig.name}`
            };
        }
        
        return {
            valid: true,
            message: `Numéro ${operatorConfig.name} valide`,
            formatted: formatPhoneNumber(cleanPhone)
        };
    }

    // Fonction pour formater le numéro avec des espaces
    function formatPhoneNumber(phone) {
        const cleanPhone = phone.replace(/\s/g, '');
        if (cleanPhone.length !== 10) return phone;
        
        return cleanPhone.replace(/(\d{2})(?=\d)/g, '$1 ');
    }

    // Fonction pour obtenir l'exemple de numéro selon l'opérateur
    function getPhoneExample(operator) {
        const config = operatorsConfig[operator];
        return config ? config.example : '01 23 45 67 89';
    }

    // Mettre à jour le placeholder selon l'opérateur sélectionné
    function updatePhonePlaceholder(operator, phoneFieldId) {
        const phoneField = document.getElementById(phoneFieldId);
        if (phoneField) {
            const example = getPhoneExample(operator);
            phoneField.placeholder = `Ex: ${example}`;
        }
    }

    // ==================== GESTION DES MODALS ====================

    // Initialisation du graphique
    const ctx = document.getElementById('performanceChart').getContext('2d');
    const performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            datasets: [{
                label: 'Valeur du portefeuille',
                data: [1200000, 1215000, 1220000, 1230000, 1245000, 1250000, 1254500],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Valeur: ' + context.parsed.y.toLocaleString() + ' FCFA';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) {
                            return (value / 1000) + 'k FCFA';
                        }
                    }
                }
            }
        }
    });

    // Gestion des périodes du graphique
    document.querySelectorAll('.chart-period').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.chart-period').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            console.log('Période sélectionnée:', this.dataset.period);
        });
    });

    // Fonction pour afficher/masquer les champs opérateur et téléphone
    function toggleOperatorField(method, type) {
        const operatorField = document.getElementById(`${type}OperatorField`);
        const phoneField = document.getElementById(`${type}PhoneField`);
        
        if (method === 'mobile_money') {
            if (operatorField) operatorField.style.display = 'block';
            if (phoneField) {
                phoneField.style.display = 'block';
                // Mettre à jour le placeholder selon l'opérateur par défaut
                const defaultOperator = document.getElementById(`${type}Operator`).value;
                updatePhonePlaceholder(defaultOperator, `${type}Phone`);
            }
        } else {
            if (operatorField) operatorField.style.display = 'none';
            if (phoneField) phoneField.style.display = 'none';
        }
    }

    // Fonctions d'ouverture des modals
    function openDepositModal() {
        const modal = document.getElementById('depositModal');
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        
        // Réinitialiser et configurer les champs
        document.getElementById('depositForm').reset();
        toggleOperatorField('mobile_money', 'deposit');
        
        // Focus sur le premier champ
        setTimeout(() => {
            document.getElementById('depositAmount').focus();
        }, 100);
    }

    function openWithdrawModal() {
        const modal = document.getElementById('withdrawModal');
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        
        document.getElementById('withdrawForm').reset();
        toggleOperatorField('mobile_money', 'withdraw');
        
        setTimeout(() => {
            document.getElementById('withdrawAmount').focus();
        }, 100);
    }

    function openTransferModal() {
        const modal = document.getElementById('transferModal');
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        
        document.getElementById('transferForm').reset();
        
        // Mettre à jour le placeholder pour le transfert
        const defaultOperator = document.getElementById('transferOperator').value;
        updatePhonePlaceholder(defaultOperator, 'transferPhone');
        
        setTimeout(() => {
            document.getElementById('transferAmount').focus();
        }, 100);
    }

    // Fonctions de fermeture des modals
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }

    function closeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        });
    }

    // Fermer les modals en cliquant à l'extérieur
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });

    // Fermer les modals avec la touche Échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    // ==================== GESTION DES FORMULAIRES ====================

    // Fonction utilitaire pour obtenir le nom complet de l'opérateur
    function getOperatorDisplayName(operatorCode) {
        const operator = operatorsConfig[operatorCode];
        return operator ? operator.name : operatorCode;
    }

    // Fonction pour formater les nombres
    function numberFormat(number) {
        return new Intl.NumberFormat('fr-FR').format(number);
    }

    // Messages de chargement dynamiques par type
    const loadingMessages = {
        deposit: (amount, operator) => `Dépôt de ${numberFormat(amount)} FCFA en cours via ${getOperatorDisplayName(operator)}...`,
        withdraw: (amount, operator) => `Retrait de ${numberFormat(amount)} FCFA en cours vers ${getOperatorDisplayName(operator)}...`,
        transfer: (amount, operator) => `Transfert de ${numberFormat(amount)} FCFA en cours vers ${getOperatorDisplayName(operator)}...`
    };

    const successMessages = {
        deposit: (amount, operator) => `Dépôt de ${numberFormat(amount)} FCFA réussi via ${getOperatorDisplayName(operator)}!`,
        withdraw: (amount, operator) => `Retrait de ${numberFormat(amount)} FCFA réussi vers ${getOperatorDisplayName(operator)}!`,
        transfer: (amount, operator) => `Transfert de ${numberFormat(amount)} FCFA réussi vers ${getOperatorDisplayName(operator)}!`
    };

    // Fonction pour mettre à jour l'affichage du solde
    function updateBalanceDisplay(newBalance) {
        // Mettre à jour le KPI principal
        const balanceElement = document.getElementById('kpiBalance');
        if (balanceElement) {
            balanceElement.textContent = numberFormat(newBalance) + ' FCFA';
        }
        
        // Mettre à jour les max des modals
        const withdrawMax = document.getElementById('withdrawAmount');
        const transferMax = document.getElementById('transferAmount');
        const withdrawBalanceDisplay = document.querySelector('#withdrawModal small');
        const transferBalanceDisplay = document.querySelector('#transferModal small');
        
        if (withdrawMax) {
            withdrawMax.max = newBalance;
            if (withdrawBalanceDisplay) {
                withdrawBalanceDisplay.textContent = `Solde disponible: ${numberFormat(newBalance)} FCFA`;
            }
        }
        
        if (transferMax) {
            transferMax.max = newBalance;
            if (transferBalanceDisplay) {
                transferBalanceDisplay.textContent = `Solde disponible: ${numberFormat(newBalance)} FCFA`;
            }
        }
    }

    // Gestionnaire de formulaires amélioré
    async function handleFormSubmit(formData, url, loadingMessage, successMessage) {
        let loadingNotification = null;
        
        try {
            // Afficher le message de chargement
            loadingNotification = showLoading(loadingMessage, 'Veuillez patienter');
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            });
            
            // Vérifier si la réponse est valide
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            
            // Nettoyer le texte pour éviter les problèmes de parsing
            const cleanText = text.trim();
            
            let result;
            try {
                result = JSON.parse(cleanText);
            } catch (parseError) {
                console.error('Parse error:', parseError, 'Response text:', cleanText);
                throw new Error('Réponse serveur invalide');
            }
            
            // Fermer la notification de chargement
            if (loadingNotification) {
                loadingNotification.remove();
            }
            
            if (result.success) {
                // Message amélioré avec le nouveau solde
                let finalMessage = result.message || successMessage;
                if (result.new_balance) {
                    finalMessage += `<br><strong>Nouveau solde: ${numberFormat(result.new_balance)} FCFA</strong>`;
                }
                
                showSuccess(finalMessage, 'Opération réussie');
                
                // Mettre à jour l'affichage du solde sans recharger toute la page
                if (result.new_balance) {
                    updateBalanceDisplay(result.new_balance);
                }
                
                // Fermer le modal après un délai
                setTimeout(() => {
                    closeAllModals();
                }, 2000);
                
                return true;
            } else {
                showError(result.error, 'Erreur');
                return false;
            }
            
        } catch (error) {
            // Fermer la notification de chargement en cas d'erreur
            if (loadingNotification) {
                loadingNotification.remove();
            }
            
            console.error('Request failed:', error);
            
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                showError('Erreur de connexion. Vérifiez votre connexion internet.', 'Erreur réseau');
            } else if (error.message.includes('JSON')) {
                showError('Erreur de traitement des données. Veuillez réessayer.', 'Erreur serveur');
            } else {
                showError(error.message, 'Erreur');
            }
            
            return false;
        }
    }

    // Initialisation des gestionnaires de formulaires
    function initializeFormHandlers() {
        // Formulaire de dépôt
        const depositForm = document.getElementById('depositForm');
        if (depositForm) {
            depositForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Validation avant envoi
                if (!validateForm(this)) {
                    return;
                }
                
                const amount = document.getElementById('depositAmount').value;
                const method = document.getElementById('depositMethod').value;
                const operator = document.getElementById('depositOperator').value;
                const phone = document.getElementById('depositPhone').value;
                
                const formData = `amount=${amount}&method=${method}&operator=${operator}&phone=${phone.replace(/\s/g, '')}`;
                const loadingMessage = loadingMessages.deposit(amount, operator);
                const successMessage = successMessages.deposit(amount, operator);
                
                await handleFormSubmit(formData, 'php/deposit_process.php', loadingMessage, successMessage);
            });

            // Gestion du changement de méthode pour le dépôt
            const depositMethod = document.getElementById('depositMethod');
            if (depositMethod) {
                depositMethod.addEventListener('change', function() {
                    toggleOperatorField(this.value, 'deposit');
                });
            }

            // Gestion du changement d'opérateur pour le dépôt
            const depositOperator = document.getElementById('depositOperator');
            if (depositOperator) {
                depositOperator.addEventListener('change', function() {
                    updatePhonePlaceholder(this.value, 'depositPhone');
                    // Re-valider le numéro si déjà saisi
                    const phoneField = document.getElementById('depositPhone');
                    if (phoneField.value) {
                        validatePhoneField(phoneField, this.value);
                    }
                });
            }
        }

        // Formulaire de retrait
        const withdrawForm = document.getElementById('withdrawForm');
        if (withdrawForm) {
            withdrawForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Validation avant envoi
                if (!validateForm(this)) {
                    return;
                }
                
                const amount = document.getElementById('withdrawAmount').value;
                const method = document.getElementById('withdrawMethod').value;
                const operator = document.getElementById('withdrawOperator').value;
                const phone = document.getElementById('withdrawPhone').value;
                
                const formData = `amount=${amount}&method=${method}&operator=${operator}&phone=${phone.replace(/\s/g, '')}`;
                const loadingMessage = loadingMessages.withdraw(amount, operator);
                const successMessage = successMessages.withdraw(amount, operator);
                
                await handleFormSubmit(formData, 'php/withdraw_process.php', loadingMessage, successMessage);
            });

            // Gestion du changement de méthode pour le retrait
            const withdrawMethod = document.getElementById('withdrawMethod');
            if (withdrawMethod) {
                withdrawMethod.addEventListener('change', function() {
                    toggleOperatorField(this.value, 'withdraw');
                });
            }

            // Gestion du changement d'opérateur pour le retrait
            const withdrawOperator = document.getElementById('withdrawOperator');
            if (withdrawOperator) {
                withdrawOperator.addEventListener('change', function() {
                    updatePhonePlaceholder(this.value, 'withdrawPhone');
                    // Re-valider le numéro si déjà saisi
                    const phoneField = document.getElementById('withdrawPhone');
                    if (phoneField.value) {
                        validatePhoneField(phoneField, this.value);
                    }
                });
            }
        }

        // Formulaire de transfert
        const transferForm = document.getElementById('transferForm');
        if (transferForm) {
            transferForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Validation avant envoi
                if (!validateForm(this)) {
                    return;
                }
                
                const amount = document.getElementById('transferAmount').value;
                const operator = document.getElementById('transferOperator').value;
                const phone = document.getElementById('transferPhone').value;
                const note = document.getElementById('transferNote').value;
                
                const formData = `amount=${amount}&operator=${operator}&phone=${phone.replace(/\s/g, '')}&note=${encodeURIComponent(note)}`;
                const loadingMessage = loadingMessages.transfer(amount, operator);
                const successMessage = successMessages.transfer(amount, operator);
                
                await handleFormSubmit(formData, 'php/transfer_process.php', loadingMessage, successMessage);
            });

            // Gestion du changement d'opérateur pour le transfert
            const transferOperator = document.getElementById('transferOperator');
            if (transferOperator) {
                transferOperator.addEventListener('change', function() {
                    updatePhonePlaceholder(this.value, 'transferPhone');
                    // Re-valider le numéro si déjà saisi
                    const phoneField = document.getElementById('transferPhone');
                    if (phoneField.value) {
                        validatePhoneField(phoneField, this.value);
                    }
                });
            }
        }
    }

    // ==================== VALIDATION AVANCÉE DES FORMULAIRES ====================

    // Fonction pour valider un champ téléphone
    function validatePhoneField(phoneField, operator) {
        const validation = validatePhoneNumber(phoneField.value, operator);
        
        if (validation.valid) {
            phoneField.style.borderColor = '#10b981';
            phoneField.title = validation.message;
            phoneField.setCustomValidity('');
            // Formater le numéro
            phoneField.value = validation.formatted;
        } else {
            phoneField.style.borderColor = '#ef4444';
            phoneField.title = validation.message;
            phoneField.setCustomValidity(validation.message);
        }
        
        return validation.valid;
    }

    // Fonction de validation complète du formulaire
    function validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required], select[required]');
        
        inputs.forEach(input => {
            // Validation spéciale pour les téléphones
            if (input.type === 'tel' && input.value) {
                let operator;
                if (form.id === 'depositForm') {
                    operator = document.getElementById('depositOperator').value;
                } else if (form.id === 'withdrawForm') {
                    operator = document.getElementById('withdrawOperator').value;
                } else if (form.id === 'transferForm') {
                    operator = document.getElementById('transferOperator').value;
                }
                
                if (!validatePhoneField(input, operator)) {
                    isValid = false;
                    showError(input.title, 'Erreur de validation');
                }
            }
            
            // Validation standard pour les autres champs
            if (!input.checkValidity()) {
                input.style.borderColor = '#ef4444';
                isValid = false;
                
                if (input.validity.valueMissing) {
                    showError(`Le champ "${input.previousElementSibling?.textContent || 'ce champ'}" est requis`, 'Champ requis');
                } else if (input.validity.rangeOverflow) {
                    showError(`Le montant dépasse le solde disponible`, 'Montant invalide');
                } else if (input.validity.rangeUnderflow) {
                    showError(`Le montant minimum est de ${input.min} FCFA`, 'Montant invalide');
                }
            } else {
                input.style.borderColor = '#10b981';
            }
        });
        
        return isValid;
    }

    function initializeFormValidations() {
        // Validation des montants
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', function() {
                const max = parseFloat(this.max);
                const value = parseFloat(this.value);
                
                if (value > max) {
                    this.setCustomValidity(`Le montant ne peut pas dépasser ${numberFormat(max)} FCFA`);
                    this.style.borderColor = '#ef4444';
                } else if (value < parseFloat(this.min)) {
                    this.setCustomValidity(`Le montant minimum est de ${this.min} FCFA`);
                    this.style.borderColor = '#ef4444';
                } else {
                    this.setCustomValidity('');
                    this.style.borderColor = '#10b981';
                }
            });
        });

        // Validation et formatage des numéros de téléphone
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('input', function(e) {
                // Formatage automatique
                let value = e.target.value.replace(/\D/g, '');
                
                // Limiter à 10 chiffres
                if (value.length > 10) {
                    value = value.substring(0, 10);
                }
                
                // Formater avec des espaces
                if (value.length > 0) {
                    value = value.match(/.{1,2}/g).join(' ');
                }
                e.target.value = value;

                // Validation en temps réel (seulement si on a un opérateur)
                let operator;
                const form = this.closest('form');
                
                if (form.id === 'depositForm') {
                    operator = document.getElementById('depositOperator').value;
                } else if (form.id === 'withdrawForm') {
                    operator = document.getElementById('withdrawOperator').value;
                } else if (form.id === 'transferForm') {
                    operator = document.getElementById('transferOperator').value;
                }
                
                if (operator && value.replace(/\s/g, '').length === 10) {
                    validatePhoneField(this, operator);
                }
            });
            
            // Validation à la perte de focus
            input.addEventListener('blur', function() {
                let operator;
                const form = this.closest('form');
                
                if (form.id === 'depositForm') {
                    operator = document.getElementById('depositOperator').value;
                } else if (form.id === 'withdrawForm') {
                    operator = document.getElementById('withdrawOperator').value;
                } else if (form.id === 'transferForm') {
                    operator = document.getElementById('transferOperator').value;
                }
                
                if (operator && this.value) {
                    validatePhoneField(this, operator);
                }
            });
        });

        // Validation des champs requis
        document.querySelectorAll('input[required], select[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value) {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = '#10b981';
                }
            });
        });
    }

    // ==================== SYSTÈME DE NOTIFICATIONS ====================

    function showNotification(type, title, message, duration = 5000) {
        const container = document.getElementById('notificationContainer');
        if (!container) return null;
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️',
            loading: '⏳'
        };
        
        notification.innerHTML = `
            <button class="notification-close" onclick="this.parentElement.remove()">×</button>
            <div class="notification-header">
                <span class="notification-icon">${icons[type]}</span>
                <span class="notification-title">${title}</span>
            </div>
            <div class="notification-message">${message}</div>
            ${duration > 0 ? '<div class="notification-progress"></div>' : ''}
        `;
        
        container.appendChild(notification);
        
        // Animation d'entrée
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Animation de progression
        if (duration > 0) {
            const progress = notification.querySelector('.notification-progress');
            if (progress) {
                setTimeout(() => {
                    progress.style.transform = 'scaleX(0)';
                    progress.style.transition = `transform ${duration}ms linear`;
                }, 100);
                
                // Suppression automatique
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notification.parentElement) {
                            notification.remove();
                        }
                    }, 300);
                }, duration);
            }
        }
        
        return notification;
    }

    // Fonctions utilitaires améliorées
    function showSuccess(message, title = 'Succès') {
        return showNotification('success', title, message);
    }

    function showError(message, title = 'Erreur') {
        return showNotification('error', title, message);
    }

    function showWarning(message, title = 'Attention') {
        return showNotification('warning', title, message);
    }

    function showInfo(message, title = 'Information') {
        return showNotification('info', title, message);
    }

    function showLoading(message, title = 'Traitement en cours') {
        return showNotification('info', title, message, 0);
    }

    // ==================== FONCTIONS UTILITAIRES ====================

    function viewAllTransactions() {
        const historySection = document.getElementById('history');
        if (historySection) {
            historySection.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function markAllNotificationsAsRead() {
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.classList.remove('unread');
        });
        const badge = document.querySelector('.notifications-badge');
        if (badge) {
            badge.textContent = '0';
        }
        showSuccess('Toutes les notifications ont été marquées comme lues');
    }

    function generateReport() {
        showInfo('Génération du rapport en cours...', 'Rapport');
        setTimeout(() => {
            showSuccess('Rapport généré avec succès!', 'Rapport');
        }, 3000);
    }

    function exportData() {
        showInfo('Export des données en cours...', 'Export');
        setTimeout(() => {
            showSuccess('Données exportées avec succès!', 'Export');
        }, 2000);
    }

    function exportHistory() {
        showInfo('Export de l\'historique en cours...', 'Export Historique');
        setTimeout(() => {
            showSuccess('Historique exporté avec succès!', 'Export Historique');
        }, 2000);
    }

    function openHelpCenter() {
        window.open('help.php', '_blank');
    }

    // Actualisation du dashboard
    const refreshBtn = document.getElementById('refreshDashboard');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.style.transform = 'rotate(360deg)';
            this.style.transition = 'transform 0.5s ease';
            
            showInfo('Actualisation des données en cours...', 'Actualisation');
            
            setTimeout(() => {
                this.style.transform = 'rotate(0deg)';
                location.reload();
            }, 1000);
        });
    }

    // ==================== INITIALISATION ====================

    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser les gestionnaires de formulaires
        initializeFormHandlers();
        initializeFormValidations();

        // Initialiser les placeholders des numéros
        updatePhonePlaceholder('orange', 'depositPhone');
        updatePhonePlaceholder('orange', 'withdrawPhone');
        updatePhonePlaceholder('orange', 'transferPhone');

        // Animation des cartes KPI
        const kpiCards = document.querySelectorAll('.kpi-card');
        kpiCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Mise à jour automatique de l'heure
        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR');
            const lastUpdateElement = document.querySelector('.last-update');
            if (lastUpdateElement) {
                lastUpdateElement.textContent = `Dernière mise à jour: ${timeString}`;
            }
        }

        // Mettre à jour l'heure toutes les minutes
        setInterval(updateLastUpdateTime, 60000);
        updateLastUpdateTime();

        // Animation des boutons d'action rapide
        document.querySelectorAll('.quick-action-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });

    // Gestion des erreurs globales
    window.addEventListener('error', function(e) {
        console.error('Erreur globale:', e.error);
        showError('Une erreur inattendue est survenue', 'Erreur système');
    });

    window.addEventListener('unhandledrejection', function(e) {
        console.error('Promesse rejetée non gérée:', e.reason);
        showError('Erreur de traitement des données', 'Erreur système');
    });

    // Fonction de confirmation pour les actions critiques
    function confirmAction(message, callback) {
        if (confirm(message)) {
            if (typeof callback === 'function') {
                callback();
            }
            return true;
        }
        return false;
    }
</script>
<style>
        /* Styles supplémentaires pour les modals et l'historique */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-content h3 {
            margin: 0 0 20px 0;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group small {
            color: #666;
            font-size: 12px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .modal-buttons button[type="button"] {
            background: #f8f9fa;
            color: #333;
        }

        .modal-buttons button[type="submit"] {
            background: #667eea;
            color: white;
        }

        .modal-buttons button:hover {
            transform: translateY(-1px);
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }

        .history-table th,
        .history-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .history-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .history-table tr:hover {
            background: #f8f9fa;
        }

        /* Styles pour les champs opérateur et téléphone */
        .form-group select,
        .form-group input[type="tel"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            margin-top: 5px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
    </style>
</body>
</html>