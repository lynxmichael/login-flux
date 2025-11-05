<?php
session_start();
require_once 'config/database.php';
require_once 'php/auth_check.php';
require_once 'models/Wallet.php'; // AJOUTER CETTE LIGNE

// Connexion à la base
$database = new Database();
$pdo = $database->getConnection();

// Récupérer les infos complètes de l'utilisateur connecté depuis la base
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, full_name, email, phone, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// NOUVELLE MÉTHODE: Utiliser la classe Wallet pour récupérer le solde
$walletModel = new Wallet();
$walletResult = $walletModel->getBalance($user_id);

// Si le wallet n'existe pas, le créer
if (!$walletResult['success']) {
    $walletModel->ensureWalletExists($user_id);
    $walletResult = $walletModel->getBalance($user_id);
}

$wallet_balance = $walletResult['success'] ? $walletResult['balance'] : 0;
$currency = $walletResult['success'] ? $walletResult['currency'] : 'FCFA';

// Préparer les données pour JavaScript
$user_data = [
    'id' => $user['id'],
    'name' => $user['full_name'],
    'email' => $user['email'],
    'wallet_balance' => $wallet_balance,
    'currency' => $currency,
    'created_at' => $user['created_at']
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FluxIO - Plateforme de Trading</title>

    <link rel="stylesheet" href="css/ultimestyle.css">
</head>

<body>
<script>
// ✅ Injection des données PHP dans JavaScript
window.PHP_USER_DATA = <?php echo json_encode($user_data); ?>;
console.log('✅ Données utilisateur injectées:', window.PHP_USER_DATA);
</script>
<!-- Modal pour les ordres d'achat/vente -->
<div id="orderModal" class="order-modal">
    <div class="order-modal-content">
        <div class="order-modal-header">
            <h3 id="modal-title">Passer un ordre</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="order-modal-body active">
            <div class="modal-scroll-container">
                <form id="orderForm" method="POST" action="php/process_order.php">
                    <!-- Champ caché pour le type d'ordre -->
                    <input type="hidden" id="orderType" name="orderType" value="">
                    <input type="hidden" id="operationDate" name="operationDate" value="<?php echo date('Y-m-d H:i:s'); ?>">
                    
                    <!-- Section Informations de l'ordre -->
                    <div class="form-section">
                        <h4>Informations de l'ordre</h4>
                        
                        <div class="order-form-group">
                            <label for="orderTypeDisplay">Type d'ordre</label>
                            <input type="text" id="orderTypeDisplay" readonly class="readonly-field" style="font-weight: bold; font-size: 16px;">
                        </div>
                        
                       <div class="order-form-group">
    <label for="stockName">Sélectionner l'action</label>
    <select id="stockName" name="stockName" class="form-select" required>
        <option value="SEMC" data-price="720">SEMC - 720 FCFA</option>
        <option value="ABJC" data-price="1600">ABJC - 1 600 FCFA</option>
        <option value="BICC" data-price="6600">BICC - 6 600 FCFA</option>
        <option value="BNBC" data-price="6100">BNBC - 6 100 FCFA</option>
        <option value="BOAB" data-price="6110">BOAB - 6 110 FCFA</option>
        <option value="BOABF" data-price="5395">BOABF - 5 395 FCFA</option>
        <option value="BOAC" data-price="4310">BOAC - 4 310 FCFA</option>
        <option value="BOAN" data-price="2550">BOAN - 2 550 FCFA</option>
        <option value="DAS" data-price="2420">DAS - 2 420 FCFA</option>
        <option value="CABC" data-price="1095">CABC - 1 095 FCFA</option>
        <option value="ETIT" data-price="3250">ETIT - 3 250 FCFA</option>
        <option value="FTSC" data-price="4100">FTSC - 4 100 FCFA</option>
        <option value="NEIC" data-price="2800">NEIC - 2 800 FCFA</option>
    </select>
</div>
                        
                        <div class="order-form-group">
                            <label for="quantity">Quantité</label>
                            <input type="number" id="quantity" name="quantity" min="1" required placeholder="Ex: 10">
                        </div>
                        
                        <div class="order-form-group">
                            <label for="price">Prix unitaire (FCFA)</label>
                            <input type="number" id="price" name="price" step="0.01" required placeholder="Ex: 720">
                        </div>
                        
                        <div class="order-form-group">
                            <label for="operationDateDisplay">Date d'opération</label>
                            <input type="text" id="operationDateDisplay" value="<?php echo date('d/m/Y H:i:s'); ?>" readonly class="readonly-field">
                        </div>
                    </div>

                    <!-- Section Calculs -->
                    <div class="form-section calculation-section" style="display: none;">
                        <h4>Détails financiers</h4>
                        
                        <div class="calc-details">
                            <div class="calc-row">
                                <span>Sous-total:</span>
                                <span id="orderSubtotal">0 FCFA</span>
                            </div>
                            <div class="calc-row">
                                <span>Frais de transaction (0.1%):</span>
                                <span id="orderFees">0 FCFA</span>
                            </div>
                            <div class="calc-row total">
                                <span id="orderTotalLabel">Montant total:</span>
                                <span id="orderTotalWithFees">0 FCFA</span>
                            </div>
                            <div class="calc-row balance-check">
                                <span id="orderBalanceCheck"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Section Portfolio (pour les ventes) -->
                    <div class="form-section portfolio-section" id="portfolioSection" style="display: none;">
                        <h4>Votre portfolio - SEMC</h4>
                        <div class="portfolio-info">
                            <div class="portfolio-item">
                                <span>Actions détenues:</span>
                                <span id="ownedQuantity" class="portfolio-value">0</span>
                            </div>
                            <div class="portfolio-item">
                                <span>Prix moyen d'achat:</span>
                                <span id="averagePrice" class="portfolio-value">0 FCFA</span>
                            </div>
                            <div class="portfolio-item">
                                <span>Plus-value latente:</span>
                                <span id="unrealizedGain" class="portfolio-value positive">+0 FCFA</span>
                            </div>
                            <div class="portfolio-item">
                                <span>Performance:</span>
                                <span id="performance" class="portfolio-value positive">+0%</span>
                            </div>
                        </div>
                    </div>
                      
                    <!-- Modal de confirmation -->
<div id="confirmationModal" class="order-modal">
  <div class="order-modal-content">
    <div class="order-modal-header">
      <h3 id="confirmationModalTitle">Confirmation</h3>
      <button class="close-modal">&times;</button>
    </div>
    <div class="order-modal-body">
      <p id="confirmationModalMessage"></p>
      <div class="order-modal-buttons">
        <button id="confirmationModalCancel" class="order-modal-btn order-btn-cancel">Annuler</button>
        <button id="confirmationModalConfirm" class="order-modal-btn order-btn-confirm-buy">Confirmer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal d'alerte -->
<div id="alertModal" class="order-modal">
  <div class="order-modal-content">
    <div class="order-modal-header">
      <h3 id="alertModalTitle">Information</h3>
      <button class="close-modal">&times;</button>
    </div>
    <div class="order-modal-body">
      <p id="alertModalMessage"></p>
      <div class="order-modal-buttons">
        <button id="alertModalOk" class="order-modal-btn order-btn-confirm-buy">OK</button>
      </div>
    </div>
  </div>
</div>


                    <!-- Section Actions disponibles (pour les achats) -->
                    <div class="form-section actions-section" id="actionsSection" style="display: none;">
                        <h4>Actions disponibles</h4>
                        <div class="available-stocks">
                            <div class="stock-option" data-symbol="SEMC" data-price="720">
                                <span class="stock-symbol">SEMC</span>
                                <span class="stock-price">720 FCFA</span>
                                <span class="stock-change positive">+1.2%</span>
                            </div>
                            <div class="stock-option" data-symbol="ABJC" data-price="1600">
                                <span class="stock-symbol">ABJC</span>
                                <span class="stock-price">1 600 FCFA</span>
                                <span class="stock-change positive">+0.06%</span>
                            </div>
                            <div class="stock-option" data-symbol="BICC" data-price="6600">
                                <span class="stock-symbol">BICC</span>
                                <span class="stock-price">6 600 FCFA</span>
                                <span class="stock-change positive">+1.54%</span>
                            </div>
                            <div class="stock-option" data-symbol="BNBC" data-price="6100">
                                <span class="stock-symbol">BNBC</span>
                                <span class="stock-price">6 100 FCFA</span>
                                <span class="stock-change negative">-0.23%</span>
                            </div>
                        </div>
                    </div>

                    <div class="order-modal-buttons">
                        <button type="button" class="order-modal-btn order-btn-cancel">Annuler</button>
                        <button type="submit" id="confirmOrder" class="order-modal-btn order-btn-confirm-buy">Confirmer l'ordre</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
 <header>
    <nav>
        <h2>SEMC - <span id="current-price">720</span> FCFA</h2>
        <div class="header-actions">
            <!-- Wallet amélioré -->
            <div class="wallet-container wallet-premium">
                <div class="wallet-particles" id="walletParticles">
                    <!-- Les particules seront générées en JavaScript -->
                </div>
                
                
                <div class="wallet-info">
                    <div class="wallet-balance" id="walletBalance">
                        <?php echo number_format($user_data['wallet_balance'] ?? 0, 0, ',', ' '); ?> FCFA
                    </div>
                    <div class="wallet-label">
                        <span>Portefeuille •</span>
                        <span class="wallet-change positive" id="walletChange">+2.3%</span>
                    
                    </div>
                </div>
            </div>

            <!-- Bouton Tableau de Bord -->
            <a href="dashboard.php" class="header-dashboard-btn">
                <span class="dashboard-btn-icon">📊</span>
                Tableau de Bord
            </a>
        </div>
    </nav>
</header>
    <div class="principalcontainer">
        <section class="left-section">
            <h3>Vendeurs</h3>
            <table class="order-table" id="sellers-table">
                <tr><th>Prix</th><th>Volume</th><th>Total</th></tr>
                <tr class="sell"><td>746</td><td>2,936</td><td>2,936</td></tr>
                <tr class="sell"><td>745</td><td>1,287</td><td>4,223</td></tr>
                <tr class="sell"><td>744</td><td>1,181</td><td>5,404</td></tr>
                <tr class="sell"><td>743</td><td>1,016</td><td>6,420</td></tr>
                <tr class="sell"><td>742</td><td>2,936</td><td>2,936</td></tr>
                <tr class="sell"><td>741</td><td>1,287</td><td>4,223</td></tr>
                <tr class="sell"><td>740</td><td>1,287</td><td>4,223</td></tr>
            </table>

            <h3>Acheteurs</h3>
            <table class="order-table" id="buyers-table">
                <tr><th>Prix</th><th>Volume</th><th>Total</th></tr>
                <tr class="buy"><td>728</td><td>1,874</td><td>1,874</td></tr>
                <tr class="buy"><td>727</td><td>2,108</td><td>3,982</td></tr>
                <tr class="buy"><td>726</td><td>1,455</td><td>5,437</td></tr>
                <tr class="buy"><td>725</td><td>1,987</td><td>7,424</td></tr>
                <tr class="buy"><td>724</td><td>1,874</td><td>1,874</td></tr>
                <tr class="buy"><td>723</td><td>2,108</td><td>3,982</td></tr>
                <tr class="buy"><td>722</td><td>2,108</td><td>3,982</td></tr>
            </table>
            
            <div class="price-indicator">
                Écart: <span id="price-gap">18</span> FCFA
            </div>
        </section>

        <section class="center-section">
            <div class="card" style="border-radius: 0 0 6px 6px; margin-top: 0; padding-top: 10px; padding-bottom: 10px;">
                <h3 id="chart-title">1H - SEMC</h3>
                
                <div class="chart-controls">
                    <div>
                        <button class="timeframe-btn active" data-timeframe="1h">1H</button>
                        <button class="timeframe-btn" data-timeframe="4h">4H</button>
                        <button class="timeframe-btn" data-timeframe="1d">1J</button>
                        <button class="timeframe-btn" data-timeframe="1w">1S</button>
                        <button class="timeframe-btn" data-timeframe="1m">1M</button>
                        <button class="timeframe-btn" data-timeframe="all">All</button>
                    </div>
                    <div>
                        <button id="play-pause-btn" class="timeframe-btn">⏸️ Pause</button>
                    </div>
                </div>
                
                <div class="chart-container">
                    <canvas id="chart"></canvas>
                </div>
                <div class="order-btn">
                    <button class="btn-buy" id="btn-buy">Acheter</button>
                    <button class="btn-sell" id="btn-sell">Vendre</button>
                </div>
                
                <!-- Slider publicitaire -->
                <div class="slider">
                    <div class="slide active">
                        <img src="IMAGE/pub (2).jpg" alt="Publicité 1">
                    </div>
                    <div class="slide">
                        <img src="IMAGE/pub.jpg" alt="Publicité 2">
                    </div>
                    <div class="slide">
                        <img src="IMAGE/pub 4.png" alt="Publicité 3">
                    </div>
                    <div class="slide">
                        <img src="IMAGE/pub 5.png" alt="Publicité 4">
                    </div>
                </div>
                <div class="slider-controls">
                    <div class="slider-dot active" data-slide="0"></div>
                    <div class="slider-dot" data-slide="1"></div>
                    <div class="slider-dot" data-slide="2"></div>
                    <div class="slider-dot" data-slide="3"></div>
                </div>
            </div>
        </section>

        <section class="right-section">
            <div class="search-container">
                <input type="text" id="stockSearch" class="search-input" placeholder="Rechercher une action...">
            </div>
            
            <h3>Actions populaires</h3>
            <div class="scrollable-table-container">
                <table class="stock-table" id="stocksTable">
                    <tr><th>Symbole</th><th>Prix</th><th>Variation</th></tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">ABJC</span></a></td>
                        <td>1 600</td>
                        <td><span class="variation-btn positive">+0.06%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">BICC</span></a></td>
                        <td>6 600</td>
                        <td><span class="variation-btn positive">+1.54%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">BNBC</span></a></td>
                        <td>6 100</td>
                        <td><span class="variation-btn negative">-0.23%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">BOAB</span></a></td>
                        <td>6 110</td>
                        <td><span class="variation-btn positive">+0.12%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">BOABF</span></a></td>
                        <td>5 395</td>
                        <td><span class="variation-btn positive">+0.87%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">BOAC</span></a></td>
                        <td>4 310</td>
                        <td><span class="variation-btn negative">-0.79%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">BOAN</span></a></td>
                        <td>2 550</td>
                        <td><span class="variation-btn positive">+0.20%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">DAS</span></a></td>
                        <td>2 420</td>
                        <td><span class="variation-btn negative">-0.79%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">CABC</span></a></td>
                        <td>1 095</td>
                        <td><span class="variation-btn negative">-5.73%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">ETIT</span></a></td>
                        <td>3 250</td>
                        <td><span class="variation-btn positive">+1.25%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">FTSC</span></a></td>
                        <td>4 100</td>
                        <td><span class="variation-btn negative">-0.50%</span></td>
                    </tr>
                    <tr>
                        <td><a href="#" class="stock-link"><span class="stock-symbol">NEIC</span></a></td>
                        <td>2 800</td>
                        <td><span class="variation-btn positive">+0.90%</span></td>
                    </tr>
                </table>
            </div>

            <h3>Historique des dernières transactions</h3>
            <table class="order-table" id="transaction-history">
                <tr><th>Prix</th><th>Heure</th><th>Qté</th></tr>
                <tr><td>728</td><td>12:51:41</td><td>4</td></tr>
                <tr><td>727</td><td>12:52:46</td><td>6</td></tr>
                <tr><td>725</td><td>12:53:10</td><td>11</td></tr>
                <tr><td>726</td><td>12:54:59</td><td>54</td></tr>
                <tr><td>728</td><td>12:56:32</td><td>2</td></tr>
                 <tr class="back-button-row">
    <td colspan="3" class="back-button-cell">
      <button class="back-button">← Retour à l'accueil</button>
    </td>
  </tr>
            </table>
        </section>
    </div>
    <footer>
 <script>
// ============================================
// SOLUTION CONSOLIDÉE - GESTION DES MODALS
// ============================================

class UnifiedOrderManager {
    constructor() {
        console.log('🚀 Initialisation UnifiedOrderManager');
        
        this.currentUser = null;
        this.walletBalance = 0;
        this.currentStock = 'SEMC';
        this.currentPrice = 720;
        this.currentOrderType = 'buy';
        this.stockData = {}; // Stocke les données des actions
        this.priceUpdateInterval = null;
        this.setupStockSelector();
        
        this.init();
    }

    init() {
        console.log('📋 Phase d\'initialisation...');
        
        // 1. Synchroniser les données utilisateur
        this.syncUserData();
        
        // 2. Attendre que le DOM soit complètement prêt
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupAll());
        } else {
            this.setupAll();
        }
    }

    setupAll() {
        console.log('⚙️ Configuration complète...');
        
        // Configuration dans l'ordre
        this.initializeStockData();
        this.updateCurrentPrice();
        this.bindTradeButtons();
        this.bindModalControls();
        this.bindFormEvents();
        this.updateWalletDisplay();
        this.setupStockLinks();
        this.setupStockSearch();
        this.startPriceUpdates();
        this.addBackButton(); // Ajouter le bouton retour
        
        console.log('✅ Configuration terminée');
    }

    // ==========================================
    // BOUTON RETOUR VERS APP.PHP
    // ==========================================
    
    addBackButton() {
        console.log('🔙 Ajout du bouton retour dans le tableau des transactions...');
        
        const transactionTable = document.getElementById('transaction-history');
        if (!transactionTable) {
            console.error('❌ Tableau historique des transactions non trouvé');
            return;
        }
        
        // Créer une nouvelle ligne pour le bouton retour
        const backRow = document.createElement('tr');
        backRow.className = 'back-button-row';
        
        // Créer une cellule qui s'étend sur toutes les colonnes
        const backCell = document.createElement('td');
        backCell.colSpan = 3; // 3 colonnes : Prix, Heure, Qté
        backCell.className = 'back-button-cell';
        
        // Créer le bouton retour
        const backButton = document.createElement('button');
        backButton.id = 'backToApp';
        backButton.className = 'back-button';
        backButton.innerHTML = '← Retour à l\'accueil';
        backButton.title = 'Retour à la page principale';
        
        // Ajouter l'événement de clic
        backButton.addEventListener('click', () => {
            this.goBackToApp();
        });
        
        // Assembler les éléments
        backCell.appendChild(backButton);
        backRow.appendChild(backCell);
        
        // Ajouter la ligne à la fin du tableau
        transactionTable.appendChild(backRow);
        
        console.log('✅ Bouton retour ajouté dans le tableau');
    }

    goBackToApp() {
        console.log('🔙 Retour vers app.php...');
        
        // Afficher une confirmation si des transactions sont en cours
        if (this.hasPendingTransactions()) {
            const confirmLeave = confirm('⚠️ Vous avez des transactions en cours.\n\nÊtes-vous sûr de vouloir quitter ?');
            if (!confirmLeave) {
                return;
            }
        }
        
        // Redirection vers app.php
        window.location.href = '../app.php';
    }

    hasPendingTransactions() {
        // Vérifier s'il y a des transactions non confirmées
        // Pour l'instant, retourne false - à adapter selon votre logique métier
        return false;
    }

    // ==========================================
    // INITIALISATION DES DONNÉES DES ACTIONS
    // ==========================================
    
    initializeStockData() {
        console.log('📊 Initialisation des données des actions...');
        
        this.stockData = {
            'SEMC': { price: 720, previousPrice: 720, variation: 0 },
            'ABJC': { price: 1600, previousPrice: 1600, variation: 0.06 },
            'BICC': { price: 6600, previousPrice: 6500, variation: 1.54 },
            'BNBC': { price: 6100, previousPrice: 6114, variation: -0.23 },
            'BOAB': { price: 6110, previousPrice: 6103, variation: 0.12 },
            'BOABF': { price: 5395, previousPrice: 5350, variation: 0.87 },
            'BOAC': { price: 4310, previousPrice: 4344, variation: -0.79 },
            'BOAN': { price: 2550, previousPrice: 2545, variation: 0.20 },
            'DAS': { price: 2420, previousPrice: 2440, variation: -0.79 },
            'CABC': { price: 1095, previousPrice: 1160, variation: -5.73 },
            'ETIT': { price: 3250, previousPrice: 3210, variation: 1.25 },
            'FTSC': { price: 4100, previousPrice: 4120, variation: -0.50 },
            'NEIC': { price: 2800, previousPrice: 2775, variation: 0.90 }
        };
        
        console.log('✅ Données des actions initialisées:', this.stockData);
    }

    // ==========================================
    // MISE À JOUR DYNAMIQUE DES PRIX
    // ==========================================
    
    startPriceUpdates() {
        console.log('🔄 Démarrage des mises à jour de prix...');
        
        // Mettre à jour les prix toutes les 5 secondes
        this.priceUpdateInterval = setInterval(() => {
            this.updateAllStockPrices();
        }, 5000);
        
        // Première mise à jour immédiate
        this.updateAllStockPrices();
    }

    updateAllStockPrices() {
        Object.keys(this.stockData).forEach(symbol => {
            this.updateStockPrice(symbol);
        });
        
        // Mettre à jour l'affichage du tableau
        this.updateStocksTable();
        
        // Si l'action actuelle est affichée, mettre à jour son prix
        if (this.stockData[this.currentStock]) {
            this.currentPrice = this.stockData[this.currentStock].price;
            this.updateCurrentPriceDisplay();
        }
    }

    updateStockPrice(symbol) {
        const stock = this.stockData[symbol];
        if (!stock) return;
        
        // Sauvegarder l'ancien prix
        stock.previousPrice = stock.price;
        
        // Générer une variation aléatoire réaliste (-2% à +2%)
        const randomChange = (Math.random() * 4 - 2) / 100; // -2% à +2%
        const priceChange = stock.price * randomChange;
        
        // Appliquer le changement (arrondi à 5 FCFA près)
        stock.price = Math.round((stock.price + priceChange) / 5) * 5;
        
        // S'assurer que le prix ne tombe pas en dessous de 100 FCFA
        stock.price = Math.max(stock.price, 100);
        
        // Calculer la variation en pourcentage
        const actualChange = stock.price - stock.previousPrice;
        stock.variation = (actualChange / stock.previousPrice) * 100;
        
        console.log(`📈 ${symbol}: ${stock.previousPrice} → ${stock.price} (${stock.variation.toFixed(2)}%)`);
    }

    updateStocksTable() {
        const table = document.getElementById('stocksTable');
        if (!table) return;
        
        const rows = table.querySelectorAll('tr');
        
        // Parcourir toutes les lignes (en sautant l'en-tête)
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const symbolElement = row.querySelector('.stock-symbol');
            const priceCell = row.cells[1];
            const variationCell = row.cells[2];
            
            if (symbolElement && priceCell && variationCell) {
                const symbol = symbolElement.textContent.trim();
                const stock = this.stockData[symbol];
                
                if (stock) {
                    // Mettre à jour le prix
                    priceCell.textContent = this.formatNumber(stock.price);
                    
                    // Mettre à jour la variation
                    const variationElement = variationCell.querySelector('.variation-btn');
                    if (variationElement) {
                        const variationText = `${stock.variation >= 0 ? '+' : ''}${stock.variation.toFixed(2)}%`;
                        variationElement.textContent = variationText;
                        
                        // Mettre à jour les classes CSS
                        variationElement.className = 'variation-btn ' + 
                            (stock.variation >= 0 ? 'positive' : 'negative');
                        
                        // Ajouter une animation pour les changements
                        this.animatePriceChange(variationElement, stock.variation);
                    }
                }
            }
        }
    }

    animatePriceChange(element, variation) {
        // Ajouter une classe d'animation
        element.classList.add('price-update');
        
        // Supprimer la classe après l'animation
        setTimeout(() => {
            element.classList.remove('price-update');
        }, 1000);
    }

    updateCurrentPriceDisplay() {
        const priceElement = document.getElementById('current-price');
        if (priceElement) {
            priceElement.textContent = this.currentPrice;
            
            // Animation du changement de prix
            priceElement.classList.add('price-update');
            setTimeout(() => {
                priceElement.classList.remove('price-update');
            }, 1000);
        }
        
        // Mettre à jour les tableaux d'ordres si l'action actuelle a changé
        this.updateOrderTables(this.currentPrice);
    }

    // ==========================================
    // SYNCHRONISATION DES DONNÉES UTILISATEUR
    // ==========================================
    
   syncUserData() {
    console.log('🔄 Synchronisation des données utilisateur...');
    
    // Priorité 1: Données PHP injectées
    if (window.PHP_USER_DATA && window.PHP_USER_DATA.id) {
        this.currentUser = window.PHP_USER_DATA;
        this.walletBalance = parseFloat(window.PHP_USER_DATA.wallet_balance) || 0;
        console.log('✅ Données PHP trouvées:', this.currentUser);
        console.log('💰 Solde wallet:', this.walletBalance, 'FCFA');
        
        // Sauvegarder dans localStorage pour persistance
        localStorage.setItem('user_data', JSON.stringify(this.currentUser));
        return true;
    }
    
    // Priorité 2: localStorage
    const storedData = localStorage.getItem('user_data');
    if (storedData) {
        try {
            this.currentUser = JSON.parse(storedData);
            this.walletBalance = parseFloat(this.currentUser.wallet_balance) || 0;
            console.log('✅ Données localStorage trouvées:', this.currentUser);
            console.log('💰 Solde wallet:', this.walletBalance, 'FCFA');
            return true;
        } catch (e) {
            console.error('❌ Erreur parsing localStorage:', e);
        }
    }
    
    // Priorité 3: Appel API pour récupérer les données
    fetch('php/wallet.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ action: 'get_user_data' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.user) {
            this.currentUser = data.user;
            this.walletBalance = parseFloat(data.user.wallet_balance) || 0;
            console.log('✅ Données API trouvées:', this.currentUser);
            console.log('💰 Solde wallet:', this.walletBalance, 'FCFA');
            
            // Sauvegarder dans localStorage
            localStorage.setItem('user_data', JSON.stringify(this.currentUser));
            
            // Mettre à jour l'affichage
            this.updateWalletDisplay();
        }
    })
    .catch(error => {
        console.error('❌ Erreur récupération données utilisateur:', error);
    });
    
    console.warn('⚠️ Aucune donnée utilisateur complète trouvée');
    return false;
}
    isUserLoggedIn() {
        // Vérification multi-sources
        const checks = {
            hasCurrentUser: !!this.currentUser,
            hasUserId: !!(this.currentUser && this.currentUser.id),
            hasLocalStorage: !!localStorage.getItem('user_data'),
            hasPhpData: !!(window.PHP_USER_DATA && window.PHP_USER_DATA.id),
            hasWalletInDom: !!document.getElementById('walletBalance')
        };
        
        console.log('🔍 Vérification connexion:', checks);
        
        // Si au moins 2 vérifications passent, on considère l'utilisateur connecté
        const passedChecks = Object.values(checks).filter(Boolean).length;
        return passedChecks >= 2;
    }

    // ==========================================
    // GESTION DES BOUTONS ACHAT/VENTE
    // ==========================================
    
    bindTradeButtons() {
        console.log('🔗 Configuration des boutons de trading...');
        
        // Nettoyer et recréer les boutons pour éviter les doublons
        this.setupButton('btn-buy', 'buy', '🟢 ACHAT');
        this.setupButton('btn-sell', 'sell', '🔴 VENTE');
    }

    setupButton(buttonId, orderType, label) {
        const oldBtn = document.getElementById(buttonId);
        
        if (!oldBtn) {
            console.error(`❌ Bouton ${buttonId} non trouvé`);
            return;
        }

        // Cloner pour supprimer tous les anciens événements
        const newBtn = oldBtn.cloneNode(true);
        oldBtn.parentNode.replaceChild(newBtn, oldBtn);
        
        // Attacher UN SEUL gestionnaire
        newBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log(`${label} cliqué`);
            this.handleTradeClick(orderType);
        });
        
        console.log(`✅ Bouton ${buttonId} configuré`);
    }

    handleTradeClick(orderType) {
        console.log(`🎯 Tentative d'ouverture modal ${orderType}`);
        
        // Vérifier la connexion
        if (!this.isUserLoggedIn()) {
            console.warn('⚠️ Utilisateur non connecté');
            this.showLoginPrompt();
            return;
        }
        
        console.log('✅ Utilisateur connecté, ouverture du modal...');
        this.openOrderModal(orderType);
    }

    showLoginPrompt() {
        const message = '🔒 Veuillez vous connecter pour passer des ordres.\n\n' +
                       'Utilisez les boutons "Se connecter" ou "S\'inscrire" en haut de la page.';
        
        alert(message);
        
        // Essayer d'ouvrir le modal de connexion si disponible
        const authModal = document.getElementById('authModal');
        if (authModal) {
            authModal.style.display = 'flex';
        }
    }

    // ==========================================
    // GESTION DU MODAL D'ORDRE
    // ==========================================
    // Dans la classe UnifiedOrderManager, modifiez la méthode configureOrderModal :

configureOrderModal(orderType) {
    const elements = {
        title: document.getElementById('modal-title'),
        confirmBtn: document.getElementById('confirmOrder'),
        typeDisplay: document.getElementById('orderTypeDisplay'),
        typeHidden: document.getElementById('orderType'),
        stockInput: document.getElementById('stockName'),
        priceInput: document.getElementById('price'),
        operationDateDisplay: document.getElementById('operationDateDisplay'),
        portfolioSection: document.getElementById('portfolioSection'),
        actionsSection: document.getElementById('actionsSection')
    };

    // Configuration selon le type
    if (orderType === 'buy') {
        if (elements.title) elements.title.textContent = '🟢 Acheter des Actions';
        if (elements.confirmBtn) {
            elements.confirmBtn.textContent = 'Confirmer l\'achat';
            elements.confirmBtn.className = 'order-modal-btn order-btn-confirm-buy';
        }
        if (elements.typeDisplay) {
            elements.typeDisplay.value = 'ACHAT';
            elements.typeDisplay.style.color = 'var(--success-color)';
        }
        if (elements.typeHidden) elements.typeHidden.value = 'buy';
        
        // Afficher les actions disponibles, masquer le portfolio
        if (elements.portfolioSection) elements.portfolioSection.style.display = 'none';
        if (elements.actionsSection) elements.actionsSection.style.display = 'block';
        
    } else {
        if (elements.title) elements.title.textContent = '🔴 Vendre des Actions';
        if (elements.confirmBtn) {
            elements.confirmBtn.textContent = 'Confirmer la vente';
            elements.confirmBtn.className = 'order-modal-btn order-btn-confirm-sell';
        }
        if (elements.typeDisplay) {
            elements.typeDisplay.value = 'VENTE';
            elements.typeDisplay.style.color = 'var(--danger-color)';
        }
        if (elements.typeHidden) elements.typeHidden.value = 'sell';
        
        // Afficher le portfolio, masquer les actions disponibles
        if (elements.portfolioSection) {
            elements.portfolioSection.style.display = 'block';
            this.loadPortfolioData(); // Charger les données du portfolio
        }
        if (elements.actionsSection) elements.actionsSection.style.display = 'none';
    }

    // Pré-remplir les champs
    if (elements.stockInput) elements.stockInput.value = this.currentStock;
    if (elements.priceInput) elements.priceInput.value = this.currentPrice;
    
    // Mettre à jour la date d'opération
    if (elements.operationDateDisplay) {
        const now = new Date();
        const dateString = now.toLocaleDateString('fr-FR') + ' ' + now.toLocaleTimeString('fr-FR');
        elements.operationDateDisplay.value = dateString;
    }

    // Mettre à jour le champ hidden operationDate
    const operationDateHidden = document.getElementById('operationDate');
    if (operationDateHidden) {
        operationDateHidden.value = new Date().toISOString().slice(0, 19).replace('T', ' ');
    }

    // Configurer les événements pour les actions disponibles
    this.setupStockOptions();
}

// Nouvelle méthode pour charger les données du portfolio
loadPortfolioData() {
    // Données de test pour le portfolio
    const portfolioData = {
        'SEMC': { quantity: 150, averagePrice: 680, currentPrice: 720 },
        'ABJC': { quantity: 50, averagePrice: 1550, currentPrice: 1600 },
        'BICC': { quantity: 25, averagePrice: 6450, currentPrice: 6600 },
        'BNBC': { quantity: 0, averagePrice: 0, currentPrice: 6100 }
    };

    const currentStock = this.currentStock;
    const stockData = portfolioData[currentStock] || { quantity: 0, averagePrice: 0, currentPrice: this.currentPrice };

    // Calculer les indicateurs
    const totalCost = stockData.quantity * stockData.averagePrice;
    const currentValue = stockData.quantity * stockData.currentPrice;
    const unrealizedGain = currentValue - totalCost;
    const performance = totalCost > 0 ? (unrealizedGain / totalCost) * 100 : 0;

    // Mettre à jour l'affichage
    document.getElementById('ownedQuantity').textContent = stockData.quantity;
    document.getElementById('averagePrice').textContent = this.formatNumber(stockData.averagePrice) + ' FCFA';
    document.getElementById('unrealizedGain').textContent = (unrealizedGain >= 0 ? '+' : '') + this.formatNumber(unrealizedGain) + ' FCFA';
    document.getElementById('performance').textContent = (performance >= 0 ? '+' : '') + performance.toFixed(2) + '%';

    // Appliquer les couleurs
    const gainElement = document.getElementById('unrealizedGain');
    const perfElement = document.getElementById('performance');
    
    if (unrealizedGain >= 0) {
        gainElement.className = 'portfolio-value positive';
        perfElement.className = 'portfolio-value positive';
    } else {
        gainElement.className = 'portfolio-value negative';
        perfElement.className = 'portfolio-value negative';
    }
}

// Méthode pour configurer les options d'actions
setupStockOptions() {
    const stockOptions = document.querySelectorAll('.stock-option');
    
    stockOptions.forEach(option => {
        option.addEventListener('click', () => {
            // Retirer la classe active de toutes les options
            stockOptions.forEach(opt => opt.classList.remove('active'));
            
            // Ajouter la classe active à l'option cliquée
            option.classList.add('active');
            
            // Mettre à jour le formulaire
            const symbol = option.getAttribute('data-symbol');
            const price = parseFloat(option.getAttribute('data-price'));
            
            document.getElementById('stockName').value = symbol;
            document.getElementById('price').value = price;
            
            // Mettre à jour les calculs
            this.updateOrderCalculations();
        });
    });
}

// Modifiez la méthode updateOrderCalculations pour inclure le sous-total
updateOrderCalculations() {
    const quantity = parseInt(document.getElementById('quantity')?.value) || 0;
    const price = parseFloat(document.getElementById('price')?.value) || 0;
    const orderType = document.getElementById('orderType')?.value || 'buy';
    
    if (quantity <= 0 || price <= 0) return;
    
    const subtotal = quantity * price;
    const fees = subtotal * 0.001; // 0.1%
    const totalWithFees = orderType === 'buy' ? subtotal + fees : subtotal - fees;
    
    console.log('💰 Calcul ordre:');
    console.log('  - Sous-total:', subtotal);
    console.log('  - Frais:', fees);
    console.log('  - Total:', totalWithFees);
    console.log('  - Solde wallet:', this.walletBalance);
    
    // Mise à jour de l'affichage
    const calcSection = document.querySelector('.calculation-section');
    if (calcSection) {
        calcSection.style.display = 'block';
        
        const orderSubtotal = document.getElementById('orderSubtotal');
        const orderFees = document.getElementById('orderFees');
        const orderTotalWithFees = document.getElementById('orderTotalWithFees');
        const balanceCheck = document.getElementById('orderBalanceCheck');
        
        if (orderSubtotal) orderSubtotal.textContent = `${this.formatNumber(subtotal)} FCFA`;
        if (orderFees) orderFees.textContent = `${this.formatNumber(fees)} FCFA`;
        if (orderTotalWithFees) orderTotalWithFees.textContent = `${this.formatNumber(totalWithFees)} FCFA`;
        
        // Vérification du solde pour les achats
        if (balanceCheck && orderType === 'buy') {
            // S'assurer que walletBalance est un nombre
            const currentBalance = parseFloat(this.walletBalance) || 0;
            
            console.log('  - Comparaison:', currentBalance, '>=', totalWithFees, '?', currentBalance >= totalWithFees);
            
            if (currentBalance >= totalWithFees) {
                balanceCheck.textContent = '✅ Solde suffisant';
                balanceCheck.style.color = 'var(--success-color)';
                balanceCheck.style.background = 'rgba(40, 167, 69, 0.1)';
                balanceCheck.style.padding = '10px';
                balanceCheck.style.borderRadius = '5px';
                
                // Activer le bouton de confirmation
                const confirmBtn = document.getElementById('confirmOrder');
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.style.opacity = '1';
                    confirmBtn.style.cursor = 'pointer';
                }
            } else {
                const deficit = totalWithFees - currentBalance;
                balanceCheck.innerHTML = `
                    ❌ Solde insuffisant<br>
                    <small>Disponible: ${this.formatNumber(currentBalance)} FCFA</small><br>
                    <small>Manquant: ${this.formatNumber(deficit)} FCFA</small>
                `;
                balanceCheck.style.color = 'var(--danger-color)';
                balanceCheck.style.background = 'rgba(220, 53, 69, 0.1)';
                balanceCheck.style.padding = '10px';
                balanceCheck.style.borderRadius = '5px';
                
                // Désactiver le bouton de confirmation
                const confirmBtn = document.getElementById('confirmOrder');
                if (confirmBtn) {
                    confirmBtn.disabled = true;
                    confirmBtn.style.opacity = '0.5';
                    confirmBtn.style.cursor = 'not-allowed';
                }
            }
        }
        
        // Vérification de la quantité pour les ventes
        if (balanceCheck && orderType === 'sell') {
            const ownedQuantity = parseInt(document.getElementById('ownedQuantity')?.textContent) || 0;
            if (quantity <= ownedQuantity) {
                balanceCheck.textContent = '✅ Quantité disponible';
                balanceCheck.style.color = 'var(--success-color)';
                balanceCheck.style.background = 'rgba(40, 167, 69, 0.1)';
                
                const confirmBtn = document.getElementById('confirmOrder');
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.style.opacity = '1';
                    confirmBtn.style.cursor = 'pointer';
                }
            } else {
                balanceCheck.textContent = `❌ Quantité insuffisante (${ownedQuantity} actions disponibles)`;
                balanceCheck.style.color = 'var(--danger-color)';
                balanceCheck.style.background = 'rgba(220, 53, 69, 0.1)';
                
                const confirmBtn = document.getElementById('confirmOrder');
                if (confirmBtn) {
                    confirmBtn.disabled = true;
                    confirmBtn.style.opacity = '0.5';
                    confirmBtn.style.cursor = 'not-allowed';
                }
            }
        }
    }
}
    // ==========================================
    // GESTION DES CONTRÔLES DU MODAL
    // ==========================================
    
    bindModalControls() {
        console.log('🔗 Configuration des contrôles du modal...');
        
        // Boutons de fermeture
        const closeBtn = document.querySelector('.close-modal');
        const cancelBtn = document.querySelector('.order-btn-cancel');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.closeOrderModal());
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.closeOrderModal());
        }
        
        // Fermer en cliquant à l'extérieur
        const modal = document.getElementById('orderModal');
        if (modal) {
            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeOrderModal();
                }
            });
        }
    }

    bindFormEvents() {
        console.log('🔗 Configuration des événements du formulaire...');
        
        const orderForm = document.getElementById('orderForm');
        if (orderForm) {
            orderForm.addEventListener('submit', (e) => this.handleOrderSubmit(e));
        }
        
        const orderType = document.getElementById('orderType');
        if (orderType) {
            orderType.addEventListener('change', (e) => {
                this.currentOrderType = e.target.value;
                this.updateOrderCalculations();
            });
        }
        
        const quantity = document.getElementById('quantity');
        const price = document.getElementById('price');
        
        if (quantity) {
            quantity.addEventListener('input', () => this.updateOrderCalculations());
        }
        
        if (price) {
            price.addEventListener('input', () => this.updateOrderCalculations());
        }
    }

    // ==========================================
    // RECHERCHE DYNAMIQUE DES ACTIONS
    // ==========================================
    
    setupStockSearch() {
        console.log('🔍 Configuration de la recherche dynamique...');
        
        const searchInput = document.getElementById('stockSearch');
        if (!searchInput) {
            console.error('❌ Champ de recherche non trouvé');
            return;
        }

        searchInput.addEventListener('input', (e) => {
            this.filterStocks(e.target.value);
        });

        // Ajouter un indicateur de recherche
        searchInput.setAttribute('placeholder', 'Rechercher une action... 🔍');
        
        console.log('✅ Recherche dynamique configurée');
    }

    filterStocks(searchTerm) {
        const table = document.getElementById('stocksTable');
        if (!table) return;

        const rows = table.querySelectorAll('tr');
        let visibleCount = 0;
        
        searchTerm = searchTerm.toLowerCase().trim();
        
        // Parcourir toutes les lignes (en sautant l'en-tête)
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const symbolCell = row.querySelector('.stock-symbol');
            const priceCell = row.cells[1];
            const variationCell = row.cells[2];
            
            if (symbolCell && priceCell && variationCell) {
                const symbol = symbolCell.textContent.toLowerCase();
                const price = priceCell.textContent.toLowerCase();
                const variation = variationCell.textContent.toLowerCase();
                
                // Vérifier si le terme de recherche correspond au symbole, prix ou variation
                const matches = symbol.includes(searchTerm) || 
                               price.includes(searchTerm) || 
                               variation.includes(searchTerm);
                
                if (matches || searchTerm === '') {
                    row.style.display = '';
                    visibleCount++;
                    
                    // Ajouter un effet de surbrillance
                    if (searchTerm !== '' && symbol.includes(searchTerm)) {
                        this.highlightText(symbolCell, searchTerm);
                    } else {
                        this.removeHighlight(symbolCell);
                    }
                } else {
                    row.style.display = 'none';
                }
            }
        }
        
        // Afficher un message si aucun résultat
        this.showSearchResults(visibleCount, searchTerm);
    }

    highlightText(element, searchTerm) {
        const text = element.textContent;
        const regex = new RegExp(`(${searchTerm})`, 'gi');
        const highlightedText = text.replace(regex, '<mark class="search-highlight">$1</mark>');
        element.innerHTML = highlightedText;
    }

    removeHighlight(element) {
        const text = element.textContent;
        element.innerHTML = text;
    }

    showSearchResults(visibleCount, searchTerm) {
        // Supprimer l'ancien message de résultats
        const oldMessage = document.getElementById('search-results-message');
        if (oldMessage) {
            oldMessage.remove();
        }
        
        if (searchTerm !== '' && visibleCount === 0) {
            const tableContainer = document.querySelector('.scrollable-table-container');
            if (tableContainer) {
                const message = document.createElement('div');
                message.id = 'search-results-message';
                message.className = 'search-results-message';
                message.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: var(--text-secondary);">
                        <div style="font-size: 48px; margin-bottom: 10px;">🔍</div>
                        <strong>Aucun résultat trouvé</strong><br>
                        <span style="font-size: 0.9em;">Aucune action ne correspond à "${searchTerm}"</span>
                    </div>
                `;
                
                // Insérer après le tableau
                const table = document.getElementById('stocksTable');
                if (table) {
                    table.parentNode.insertBefore(message, table.nextSibling);
                }
            }
        }
    }

    // ==========================================
    // CALCULS ET AFFICHAGE
    // ==========================================
    
    updateCurrentPrice() {
        const priceElement = document.getElementById('current-price');
        if (priceElement) {
            const priceText = priceElement.textContent.trim();
            this.currentPrice = parseInt(priceText) || 720;
            console.log('💰 Prix actuel:', this.currentPrice);
        }
    }

    updateOrderCalculations() {
        const quantity = parseInt(document.getElementById('quantity')?.value) || 0;
        const price = parseFloat(document.getElementById('price')?.value) || 0;
        const orderType = document.getElementById('orderType')?.value || 'buy';
        
        if (quantity <= 0 || price <= 0) return;
        
        const total = quantity * price;
        const fees = total * 0.001; // 0.1%
        const totalWithFees = total + fees;
        
        // Mise à jour de l'affichage
        const calcSection = document.querySelector('.calculation-section');
        if (calcSection) {
            calcSection.style.display = 'block';
            
            const orderTotal = document.getElementById('orderTotal');
            const orderFees = document.getElementById('orderFees');
            const orderTotalWithFees = document.getElementById('orderTotalWithFees');
            const balanceCheck = document.getElementById('orderBalanceCheck');
            
            if (orderTotal) orderTotal.textContent = `${this.formatNumber(total)} FCFA`;
            if (orderFees) orderFees.textContent = `${this.formatNumber(fees)} FCFA`;
            if (orderTotalWithFees) orderTotalWithFees.textContent = `${this.formatNumber(totalWithFees)} FCFA`;
            
            // Vérification du solde
            if (balanceCheck && orderType === 'buy') {
                if (this.walletBalance >= totalWithFees) {
                    balanceCheck.textContent = '✅ Solde suffisant';
                    balanceCheck.style.color = 'var(--success-color, #28a745)';
                } else {
                    balanceCheck.textContent = `❌ Solde insuffisant (${this.formatNumber(this.walletBalance)} FCFA disponible)`;
                    balanceCheck.style.color = 'var(--danger-color, #dc3545)';
                }
            }
        }
    }

    updateWalletDisplay() {
        const walletElement = document.getElementById('walletBalance');
        if (walletElement && this.walletBalance) {
            walletElement.textContent = `${this.formatNumber(this.walletBalance)} FCFA`;
        }
    }

    formatNumber(num) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(num);
    }

    // ==========================================
    // GESTION DES LIENS D'ACTIONS POPULAIRES
    // ==========================================
    
    setupStockLinks() {
        console.log('🔗 Configuration des liens des actions populaires...');
        
        const stockLinks = document.querySelectorAll('.stock-link');
        
        stockLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleStockClick(e);
            });
        });
        
        console.log(`✅ ${stockLinks.length} liens d'actions configurés`);
    }

    handleStockClick(e) {
        const link = e.currentTarget;
        const row = link.closest('tr');
        
        if (!row) return;
        
        const symbolElement = row.querySelector('.stock-symbol');
        const priceElement = row.cells[1];
        const variationElement = row.querySelector('.variation-btn');
        
        if (symbolElement && priceElement) {
            const symbol = symbolElement.textContent.trim();
            const stock = this.stockData[symbol];
            
            if (stock) {
                const price = stock.price;
                const variation = stock.variation;
                
                console.log(`📈 Action sélectionnée: ${symbol} à ${price} FCFA (${variation.toFixed(2)}%)`);
                
                // Mettre à jour la page avec la nouvelle action
                this.updatePageWithSelectedStock(symbol, price, variation);
                
                // Stocker l'action sélectionnée pour persistance
                localStorage.setItem('selectedStock', JSON.stringify({
                    symbol: symbol,
                    price: price,
                    variation: variation
                }));

                // Réinitialiser la recherche
                const searchInput = document.getElementById('stockSearch');
                if (searchInput) {
                    searchInput.value = '';
                    this.filterStocks('');
                }
            }
        }
    }

    updatePageWithSelectedStock(symbol, price, variation) {
        // 1. Mettre à jour le header
        const headerTitle = document.querySelector('header h2');
        if (headerTitle) {
            headerTitle.innerHTML = `${symbol} - <span id="current-price">${price}</span> FCFA`;
        }
        
        // 2. Mettre à jour le titre du graphique
        const chartTitle = document.getElementById('chart-title');
        if (chartTitle) {
            chartTitle.textContent = `1H - ${symbol}`;
        }
        
        // 3. Mettre à jour le nom de l'action dans le modal
        const stockNameInput = document.getElementById('stockName');
        if (stockNameInput) {
            stockNameInput.value = symbol;
        }
        
        // 4. Mettre à jour le prix dans le modal
        const priceInput = document.getElementById('price');
        if (priceInput) {
            priceInput.value = price;
        }
        
        // 5. Mettre à jour le prix actuel dans le manager
        this.currentStock = symbol;
        this.currentPrice = price;
        this.updateCurrentPrice();
        
        // 6. Mettre à jour les tableaux d'ordres avec de nouvelles données
        this.updateOrderTables(price);
        
        // 7. Mettre à jour l'historique des transactions
        this.updateTransactionHistory(symbol);
        
        // 8. Supprimer les informations de l'entreprise
        this.removeCompanyInfo();
        
        // 9. Recalculer les écarts de prix
        this.updatePriceGap();
        
        console.log(`✅ Page mise à jour avec ${symbol}`);
    }

    updateOrderTables(basePrice) {
        const sellersTable = document.getElementById('sellers-table');
        const buyersTable = document.getElementById('buyers-table');
        
        if (sellersTable && buyersTable) {
            // Générer des données de vendeurs réalistes
            let sellersHTML = '<tr><th>Prix</th><th>Volume</th><th>Total</th></tr>';
            let totalSellers = 0;
            
            for (let i = 6; i >= 0; i--) {
                const price = Math.round(basePrice * (1.02 + (i * 0.005)));
                const volume = Math.floor(Math.random() * 3000) + 500;
                totalSellers += volume;
                sellersHTML += `<tr class="sell"><td>${price}</td><td>${volume.toLocaleString()}</td><td>${totalSellers.toLocaleString()}</td></tr>`;
            }
            
            sellersTable.innerHTML = sellersHTML;
            
            // Générer des données d'acheteurs réalistes
            let buyersHTML = '<tr><th>Prix</th><th>Volume</th><th>Total</th></tr>';
            let totalBuyers = 0;
            
            for (let i = 0; i < 7; i++) {
                const price = Math.round(basePrice * (0.98 - (i * 0.005)));
                const volume = Math.floor(Math.random() * 3000) + 500;
                totalBuyers += volume;
                buyersHTML += `<tr class="buy"><td>${price}</td><td>${volume.toLocaleString()}</td><td>${totalBuyers.toLocaleString()}</td></tr>`;
            }
            
            buyersTable.innerHTML = buyersHTML;
        }
    }

    updateTransactionHistory(symbol) {
        const historyTable = document.getElementById('transaction-history');
        if (historyTable) {
            let historyHTML = '<tr><th>Prix</th><th>Heure</th><th>Qté</th></tr>';
            
        // Générer des transactions récentes réalistes
        const basePrice = this.currentPrice;
        
        for (let i = 0; i < 5; i++) {
            const minutesAgo = 5 - i;
            const time = new Date();
            time.setMinutes(time.getMinutes() - minutesAgo);
            
            const hours = time.getHours().toString().padStart(2, '0');
            const minutes = time.getMinutes().toString().padStart(2, '0');
            const seconds = time.getSeconds().toString().padStart(2, '0');
            
            const price = basePrice + Math.floor(Math.random() * 10) - 5;
            const quantity = Math.floor(Math.random() * 50) + 1;
            
            historyHTML += `<tr><td>${price}</td><td>${hours}:${minutes}:${seconds}</td><td>${quantity}</td></tr>`;
        }
        
        historyTable.innerHTML = historyHTML;
        
        // Réajouter le bouton retour après la mise à jour de l'historique
        this.addBackButton();
    }
}

// Supprimer les informations de l'entreprise
removeCompanyInfo() {
    const infoSection = document.getElementById('company-info');
    if (infoSection) {
        infoSection.remove();
    }
}

updatePriceGap() {
    const sellers = document.querySelectorAll('#sellers-table .sell');
    const buyers = document.querySelectorAll('#buyers-table .buy');
    
    if (sellers.length > 0 && buyers.length > 0) {
        const highestSeller = parseInt(sellers[0].querySelector('td').textContent);
        const lowestBuyer = parseInt(buyers[0].querySelector('td').textContent);
        const gap = highestSeller - lowestBuyer;
        
        const gapElement = document.getElementById('price-gap');
        if (gapElement) {
            gapElement.textContent = gap;
        }
    }
}

setupStockSelector() {
    const stockSelector = document.getElementById('stockName');
    if (!stockSelector) return;

    // Mettre à jour le prix quand l'action change
    stockSelector.addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        
        // Mettre à jour le champ prix
        const priceInput = document.getElementById('price');
        if (priceInput) {
            priceInput.value = price;
        }
        
        // Mettre à jour l'action courante
        this.currentStock = e.target.value;
        this.currentPrice = parseFloat(price);
        
        // Recalculer les ordres
        this.updateOrderCalculations();
        
        // Mettre à jour le portfolio si en mode vente
        if (this.currentOrderType === 'sell') {
            this.loadPortfolioData();
        }
    });
}
// ==========================================
// SOUMISSION DU FORMULAIRE AVEC APPEL API
// ==========================================

async handleOrderSubmit(e) {
    e.preventDefault();
    console.log('📝 Soumission du formulaire...');
    
    const quantity = parseInt(document.getElementById('quantity').value);
    const price = parseFloat(document.getElementById('price').value);
    const orderType = document.getElementById('orderType').value;
    const stockName = document.getElementById('stockName').value;
    
    // Validation
    if (!quantity || quantity <= 0) {
        alert('❌ Veuillez saisir une quantité valide');
        return;
    }
    
    if (!price || price <= 0) {
        alert('❌ Veuillez saisir un prix valide');
        return;
    }
    
    const total = quantity * price;
    const fees = total * 0.001;
    const totalWithFees = orderType === 'buy' ? total + fees : total - fees;
    
    // Vérification du solde pour les achats
    if (orderType === 'buy' && this.walletBalance < totalWithFees) {
        alert(`❌ Solde insuffisant\n\nNécessaire: ${this.formatNumber(totalWithFees)} FCFA\nDisponible: ${this.formatNumber(this.walletBalance)} FCFA`);
        return;
    }
    
    // Confirmation
    const action = orderType === 'buy' ? 'achat' : 'vente';
    const confirmMsg = `Confirmer cet ordre d'${action}?\n\n` +
                      `📊 ${stockName}\n` +
                      `📦 Quantité: ${quantity} actions\n` +
                      `💰 Prix: ${this.formatNumber(price)} FCFA\n` +
                      `💵 Total: ${this.formatNumber(total)} FCFA\n` +
                      `📈 Frais: ${this.formatNumber(fees)} FCFA\n` +
                      `━━━━━━━━━━━━━━━\n` +
                      `💳 TOTAL: ${this.formatNumber(totalWithFees)} FCFA`;
    
    if (!confirm(confirmMsg)) {
        return;
    }
    
    try {
        // Appel API réel vers wallet.php
        const formData = new FormData();
        formData.append('action', orderType === 'buy' ? 'buy_stock' : 'sell_stock');
        formData.append('stock_symbol', stockName);
        formData.append('quantity', quantity);
        formData.append('price', price);
        
        const response = await fetch('php/wallet.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✅ Ordre d'${action} confirmé!\n\n${result.message}\nNouveau solde: ${this.formatNumber(result.new_balance)} FCFA`);
            
            // Mettre à jour le solde local et l'affichage
            this.walletBalance = result.new_balance;
            this.updateWalletDisplay();
            
            this.closeOrderModal();
            
            // Recharger les données utilisateur
            await this.syncUserData();
            
        } else {
            throw new Error(result.message);
        }
        
    } catch (error) {
        console.error('❌ Erreur soumission:', error);
        alert('❌ Erreur lors de la transaction: ' + error.message);
    }
}


showNotification(message, type = 'info', duration = 3000) {
    // Supprimer les notifications existantes
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notif => notif.remove());

    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️',
        warning: '⚠️'
    };

    notification.innerHTML = `
        <div class="notification-content">
            <div class="notification-icon">${icons[type] || icons.info}</div>
            <div class="notification-message">${message}</div>
            <button class="notification-close" onclick="this.closest('.notification').remove()">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-suppression après la durée spécifiée
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.4s ease';
            setTimeout(() => notification.remove(), 400);
        }
    }, duration);
}

// Méthode pour rafraîchir les données du wallet
async refreshWalletData() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_wallet_data');
        
        const response = await fetch('php/wallet.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.walletBalance = result.wallet_balance;
            this.updateWalletDisplay();
            return result;
        }
    } catch (error) {
        console.error('Erreur rafraîchissement wallet:', error);
    }
}

// ==========================================
// CHARGEMENT DE L'ACTION SÉLECTIONNÉE
// ==========================================

loadSelectedStock() {
    const stockData = localStorage.getItem('selectedStock');
    
    if (stockData) {
        try {
            const stock = JSON.parse(stockData);
            this.updatePageWithSelectedStock(stock.symbol, stock.price, stock.variation);
            console.log('✅ Action sélectionnée chargée:', stock.symbol);
        } catch (e) {
            console.error('❌ Erreur chargement stock:', e);
            // Charger SEMC par défaut en cas d'erreur
            this.updatePageWithSelectedStock('SEMC', 720, 0);
        }
    } else {
        // Charger SEMC par défaut si aucune sélection
        this.updatePageWithSelectedStock('SEMC', 720, 0);
    }
}

// ==========================================
// NETTOYAGE
// ==========================================

destroy() {
    if (this.priceUpdateInterval) {
        clearInterval(this.priceUpdateInterval);
        console.log('🛑 Mises à jour de prix arrêtées');
    }
}
}

// ==========================================
// INITIALISATION GLOBALE
// ==========================================

// Nettoyer les anciennes instances
if (window.walletManager) {
    console.log('🧹 Nettoyage de l\'ancienne instance WalletManager');
    window.walletManager = null;
}

if (window.orderManager) {
    console.log('🧹 Nettoyage de l\'ancienne instance OrderManager');
    window.orderManager = null;
}

// Créer la nouvelle instance unifiée
console.log('🎬 Création de UnifiedOrderManager...');
window.unifiedOrderManager = new UnifiedOrderManager();

// ==========================================
// FONCTIONS DE DÉBOGAGE
// ==========================================

window.debugModal = function() {
    console.log('=== DEBUG MODAL ===');
    console.log('currentUser:', window.unifiedOrderManager.currentUser);
    console.log('walletBalance:', window.unifiedOrderManager.walletBalance);
    console.log('isLoggedIn:', window.unifiedOrderManager.isUserLoggedIn());
    console.log('currentPrice:', window.unifiedOrderManager.currentPrice);
    console.log('currentStock:', window.unifiedOrderManager.currentStock);
    console.log('stockData:', window.unifiedOrderManager.stockData);
    console.log('Modal element:', document.getElementById('orderModal'));
    console.log('===================');
};

window.forceOpenModal = function(type = 'buy') {
    console.log(`🔓 Ouverture forcée du modal ${type}...`);
    if (window.unifiedOrderManager) {
        window.unifiedOrderManager.openOrderModal(type);
    } else {
        alert('UnifiedOrderManager non disponible');
    }
};

// Arrêter les mises à jour quand la page se ferme
window.addEventListener('beforeunload', () => {
    if (window.unifiedOrderManager) {
        window.unifiedOrderManager.destroy();
    }
});

// ==========================================
// INITIALISATION COMPLÈTE
// ==========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM chargé - Initialisation complète');
    
    // Charger l'action sélectionnée
    if (window.unifiedOrderManager) {
        window.unifiedOrderManager.loadSelectedStock();
    }
    
    console.log('✅ Script de gestion des modals chargé avec succès');
    console.log('💡 Utilisez window.debugModal() pour déboguer');
    console.log('💡 Utilisez window.forceOpenModal("buy") ou window.forceOpenModal("sell") pour forcer l\'ouverture');
});

// ==========================================
// STYLES DYNAMIQUES POUR LA RECHERCHE ET ANIMATIONS
// ==========================================

const style = document.createElement('style');
style.textContent = `
    .stock-link {
        color: var(--text-primary);
        text-decoration: none;
        transition: all 0.3s ease;
        display: block;
        padding: 8px 5px;
        border-radius: 4px;
        border: 1px solid transparent;
    }

    .stock-link:hover {
        color: var(--primary-color);
        background-color: var(--surface-hover);
        text-decoration: none;
        border-color: var(--primary-color);
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .stock-symbol {
        font-weight: bold;
        color: var(--text-primary);
    }

    .stock-link:hover .stock-symbol {
        color: var(--primary-color);
    }

    .search-input {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        background: var(--surface-dark);
        color: var(--text-primary);
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
    }

    .search-input::placeholder {
        color: var(--text-secondary);
    }

    .search-highlight {
        background-color: #ffeb3b;
        color: #000;
        padding: 1px 2px;
        border-radius: 2px;
        font-weight: bold;
    }

    .search-results-message {
        margin-top: 10px;
        padding: 20px;
        background: var(--surface-dark);
        border-radius: 6px;
        border: 1px solid var(--border-color);
        text-align: center;
    }

    /* Bouton retour dans le tableau */
    .back-button-row {
        border-top: 2px solid var(--border-color);
    }

    .back-button-cell {
        padding: 15px 10px !important;
        text-align: center;
        background: rgba(255, 255, 255, 0.02);
    }

    .back-button {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: var(--text-secondary);
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        width: 100%;
        max-width: 200px;
    }

    .back-button:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .back-button:active {
        transform: translateY(0);
    }

    /* Animations pour les mises à jour de prix */
    .price-update {
        animation: pricePulse 0.6s ease-in-out;
    }

    @keyframes pricePulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); background-color: rgba(255, 193, 7, 0.2); }
        100% { transform: scale(1); }
    }

    .variation-btn.positive {
        background-color: rgba(40, 167, 69, 0.2);
        color: #28a745;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: bold;
    }

    .variation-btn.negative {
        background-color: rgba(220, 53, 69, 0.2);
        color: #dc3545;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: bold;
    }

    .variation-btn.price-update {
        animation: variationFlash 0.8s ease-in-out;
    }

    @keyframes variationFlash {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }

    /* Style pour le compteur de résultats */
    .search-count {
        font-size: 0.8em;
        color: var(--text-secondary);
        margin-top: 5px;
        text-align: right;
    }

    /* Amélioration de l'affichage des prix */
    #current-price {
        transition: all 0.3s ease;
        display: inline-block;
        padding: 2px 6px;
        border-radius: 3px;
    }

    #current-price.price-update {
        background-color: rgba(255, 193, 7, 0.3);
    }

    /* Responsive pour le bouton retour */
    @media (max-width: 768px) {
        .back-button {
            padding: 12px 16px;
            font-size: 16px;
        }
    }

    /* Styles pour le bouton de déconnexion */
    .logout-btn {
        background: linear-gradient(45deg, #dc3545, #c82333) !important;
        border: 1px solid #dc3545 !important;
    }

    .logout-btn:hover {
        background: linear-gradient(45deg, #c82333, #bd2130) !important;
        border-color: #bd2130 !important;
    }
`;
document.head.appendChild(style);
// Animation des particules du wallet
function createWalletParticles() {
    const particlesContainer = document.getElementById('walletParticles');
    if (!particlesContainer) return;
    
    // Nettoyer les anciennes particules
    particlesContainer.innerHTML = '';
    
    // Créer 6 particules
    for (let i = 0; i < 6; i++) {
        const particle = document.createElement('div');
        particle.className = 'wallet-particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 3 + 's';
        particle.style.animationDuration = (2 + Math.random() * 2) + 's';
        particlesContainer.appendChild(particle);
    }
}

// Animation de mise à jour du solde
function animateWalletUpdate(newBalance) {
    const walletElement = document.getElementById('walletBalance');
    if (walletElement) {
        walletElement.classList.add('update');
        setTimeout(() => {
            walletElement.textContent = newBalance;
            setTimeout(() => {
                walletElement.classList.remove('update');
            }, 600);
        }, 300);
    }
}

// Effet de pulse pour les mises à jour
function pulseWallet() {
    const walletContainer = document.querySelector('.wallet-container');
    if (walletContainer) {
        walletContainer.classList.add('wallet-update');
        setTimeout(() => {
            walletContainer.classList.remove('wallet-update');
        }, 1000);
    }
}

// Initialiser au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    createWalletParticles();
    
    // Exemple : Pulse toutes les 30 secondes
    setInterval(() => {
        pulseWallet();
    }, 30000);
});

// Fonction pour mettre à jour le solde (à appeler quand le solde change)
function updateWalletBalance(newBalance) {
    const formattedBalance = new Intl.NumberFormat('fr-FR').format(newBalance) + ' FCFA';
    animateWalletUpdate(formattedBalance);
    pulseWallet();
}

// Exemple d'utilisation :
// updateWalletBalance(1500000);

// ============================================
// CLASSE POUR LA GESTION DU SLIDER PUBLICITAIRE
// ============================================

class SliderManager {
    constructor() {
        this.slides = [];
        this.currentSlide = 0;
        this.slideInterval = null;
        this.slideDuration = 5000; // 5 secondes par défaut
        this.isPlaying = true;
        
        this.init();
    }

    init() {
        console.log('🎠 Initialisation du SliderManager...');
        
        // Attendre que le DOM soit chargé
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupSlider());
        } else {
            this.setupSlider();
        }
    }

    setupSlider() {
        console.log('⚙️ Configuration du slider...');
        
        // Récupérer tous les éléments du slider
        this.slides = document.querySelectorAll('.slide');
        this.dots = document.querySelectorAll('.slider-dot');
        
        if (this.slides.length === 0) {
            console.error('❌ Aucun slide trouvé');
            return;
        }

        console.log(`✅ ${this.slides.length} slides trouvés`);

        // Configuration initiale
        this.setupControls();
        this.setupDots();
        this.startAutoPlay();
        
        // Afficher la première slide
        this.showSlide(0);
    }

    setupControls() {
        console.log('🎮 Configuration des contrôles du slider...');
        
        // Bouton play/pause
        const playPauseBtn = document.getElementById('play-pause-btn');
        if (playPauseBtn) {
            playPauseBtn.addEventListener('click', () => this.togglePlayPause());
        }

        // Navigation par flèches clavier
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') {
                this.previousSlide();
            } else if (e.key === 'ArrowRight') {
                this.nextSlide();
            } else if (e.key === ' ') {
                e.preventDefault();
                this.togglePlayPause();
            }
        });

        // Swipe sur mobile
        this.setupTouchEvents();
    }

    setupDots() {
        console.log('🔘 Configuration des points de navigation...');
        
        this.dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                this.showSlide(index);
                this.restartAutoPlay();
            });
        });
    }

    setupTouchEvents() {
        let startX = 0;
        let endX = 0;
        const slider = document.querySelector('.slider');

        if (!slider) return;

        slider.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        }, { passive: true });

        slider.addEventListener('touchend', (e) => {
            endX = e.changedTouches[0].clientX;
            this.handleSwipe(startX, endX);
        }, { passive: true });
    }

    handleSwipe(startX, endX) {
        const swipeThreshold = 50;
        const diff = startX - endX;

        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                this.nextSlide(); // Swipe gauche
            } else {
                this.previousSlide(); // Swipe droit
            }
            this.restartAutoPlay();
        }
    }

    // ==========================================
    // FONCTIONNALITÉS PRINCIPALES DU SLIDER
    // ==========================================

    showSlide(index) {
        // Validation de l'index
        if (index < 0) {
            index = this.slides.length - 1;
        } else if (index >= this.slides.length) {
            index = 0;
        }

        // Masquer toutes les slides
        this.slides.forEach(slide => {
            slide.classList.remove('active');
            slide.style.opacity = '0';
            slide.style.transform = 'translateX(100%)';
        });

        // Masquer tous les dots
        this.dots.forEach(dot => {
            dot.classList.remove('active');
        });

        // Afficher la slide courante avec animation
        setTimeout(() => {
            this.slides[index].classList.add('active');
            this.slides[index].style.opacity = '1';
            this.slides[index].style.transform = 'translateX(0)';
            
            // Mettre à jour le dot actif
            if (this.dots[index]) {
                this.dots[index].classList.add('active');
            }

            this.currentSlide = index;
            
            console.log(`🖼️ Slide ${index + 1}/${this.slides.length} affichée`);
        }, 50);
    }

    nextSlide() {
        const nextIndex = (this.currentSlide + 1) % this.slides.length;
        
        // Animation de transition
        this.slides[this.currentSlide].style.transform = 'translateX(-100%)';
        this.slides[this.currentSlide].style.opacity = '0';
        
        setTimeout(() => {
            this.showSlide(nextIndex);
        }, 300);
    }

    previousSlide() {
        const prevIndex = this.currentSlide - 1 < 0 ? this.slides.length - 1 : this.currentSlide - 1;
        
        // Animation de transition
        this.slides[this.currentSlide].style.transform = 'translateX(100%)';
        this.slides[this.currentSlide].style.opacity = '0';
        
        setTimeout(() => {
            this.showSlide(prevIndex);
        }, 300);
    }

    // ==========================================
    // GESTION DE LA LECTURE AUTOMATIQUE
    // ==========================================

    startAutoPlay() {
        console.log('▶️ Démarrage de la lecture automatique');
        
        this.slideInterval = setInterval(() => {
            if (this.isPlaying) {
                this.nextSlide();
            }
        }, this.slideDuration);

        this.updatePlayPauseButton();
    }

    stopAutoPlay() {
        console.log('⏸️ Arrêt de la lecture automatique');
        
        if (this.slideInterval) {
            clearInterval(this.slideInterval);
            this.slideInterval = null;
        }
    }

    restartAutoPlay() {
        this.stopAutoPlay();
        if (this.isPlaying) {
            this.startAutoPlay();
        }
    }

    togglePlayPause() {
        this.isPlaying = !this.isPlaying;
        
        if (this.isPlaying) {
            this.startAutoPlay();
        } else {
            this.stopAutoPlay();
        }
        
        this.updatePlayPauseButton();
        console.log(this.isPlaying ? '▶️ Lecture reprise' : '⏸️ Lecture en pause');
    }

    updatePlayPauseButton() {
        const playPauseBtn = document.getElementById('play-pause-btn');
        if (playPauseBtn) {
            if (this.isPlaying) {
                playPauseBtn.innerHTML = '⏸️ Pause';
                playPauseBtn.title = 'Mettre en pause le slider';
            } else {
                playPauseBtn.innerHTML = '▶️ Lecture';
                playPauseBtn.title = 'Reprendre la lecture';
            }
        }
    }

    // ==========================================
    // FONCTIONS UTILITAIRES
    // ==========================================

    setSlideDuration(duration) {
        this.slideDuration = duration;
        this.restartAutoPlay();
        console.log(`⏱️ Durée des slides définie à ${duration}ms`);
    }

    goToSlide(index) {
        if (index >= 0 && index < this.slides.length) {
            this.showSlide(index);
            this.restartAutoPlay();
        }
    }

    getCurrentSlide() {
        return {
            index: this.currentSlide,
            total: this.slides.length,
            element: this.slides[this.currentSlide]
        };
    }

    // ==========================================
    // DESTRUCTION ET NETTOYAGE
    // ==========================================

    destroy() {
        this.stopAutoPlay();
        console.log('🧹 SliderManager nettoyé');
    }
}

// ============================================
// INITIALISATION DU SLIDER
// ============================================

// Créer l'instance globale
window.sliderManager = new SliderManager();

// Arrêter le slider quand la page se ferme
window.addEventListener('beforeunload', () => {
    if (window.sliderManager) {
        window.sliderManager.destroy();
    }
});

// ============================================
// CSS DYNAMIQUE POUR LES ANIMATIONS DU SLIDER
// ============================================

const sliderStyles = `
/* Styles de base pour le slider */
.slider {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
    border-radius: 8px;
    margin: 15px 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.5s ease-in-out;
    display: flex;
    align-items: center;
    justify-content: center;
}

.slide.active {
    opacity: 1;
    transform: translateX(0);
    z-index: 2;
}

.slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
}

/* Contrôles du slider */
.slider-controls {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding: 10px;
}

.slider-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    border: 2px solid rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: all 0.3s ease;
}

.slider-dot:hover {
    background: rgba(255, 255, 255, 0.5);
    transform: scale(1.2);
}

.slider-dot.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    transform: scale(1.3);
}

/* Indicateur de statut */
.slider-status {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    z-index: 3;
}

/* Animation de progression */
.slider-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: var(--primary-color);
    width: 0%;
    z-index: 3;
    transition: width linear;
}

.slider-progress.animating {
    animation: progressBar linear forwards;
}

@keyframes progressBar {
    0% { width: 0%; }
    100% { width: 100%; }
}

/* Boutons de navigation */
.slider-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: all 0.3s ease;
    opacity: 0;
}

.slider:hover .slider-nav {
    opacity: 1;
}

.slider-nav:hover {
    background: rgba(0, 0, 0, 0.8);
    transform: translateY(-50%) scale(1.1);
}

.slider-prev {
    left: 10px;
}

.slider-next {
    right: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .slider {
        height: 150px;
    }
    
    .slider-nav {
        opacity: 1;
        width: 35px;
        height: 35px;
    }
}

/* Effet de fondu */
.slide.fade {
    transform: translateX(0);
    transition: opacity 0.8s ease;
}

.slide.fade:not(.active) {
    opacity: 0;
}

/* Effet de zoom */
.slide.zoom {
    transform: scale(1.1);
    transition: all 0.8s ease;
}

.slide.zoom.active {
    transform: scale(1);
}
`;

// Injecter les styles dans la page
const styleSheet = document.createElement('style');
styleSheet.textContent = sliderStyles;
document.head.appendChild(styleSheet);

// ============================================
// FONCTIONS DE DÉBOGAGE ET CONTRÔLES AVANCÉS
// ============================================

// Fonctions de débogage accessibles depuis la console
window.sliderDebug = {
    showInfo: function() {
        if (window.sliderManager) {
            const current = window.sliderManager.getCurrentSlide();
            console.log('📊 Informations du slider:');
            console.log('   Slide actuelle:', current.index + 1);
            console.log('   Total des slides:', current.total);
            console.log('   Lecture automatique:', window.sliderManager.isPlaying ? '▶️ ON' : '⏸️ OFF');
            console.log('   Durée des slides:', window.sliderManager.slideDuration + 'ms');
        } else {
            console.error('❌ SliderManager non disponible');
        }
    },
    
    goToSlide: function(index) {
        if (window.sliderManager) {
            window.sliderManager.goToSlide(index);
        }
    },
    
    setDuration: function(duration) {
        if (window.sliderManager) {
            window.sliderManager.setSlideDuration(duration);
        }
    },
    
    togglePlay: function() {
        if (window.sliderManager) {
            window.sliderManager.togglePlayPause();
        }
    }
};

console.log('🎠 SliderManager chargé - Utilisez window.sliderDebug pour contrôler le slider');

// ============================================
// INTÉGRATION AVEC LE SYSTÈME EXISTANT
// ============================================

// S'assurer que le slider fonctionne avec le système existant
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Slider publicitaire initialisé');
    
    // Vérifier que les images du slider existent
    const slides = document.querySelectorAll('.slide img');
    slides.forEach((img, index) => {
        img.onerror = function() {
            console.error(`❌ Image du slide ${index + 1} non trouvée:`, img.src);
            // Remplacer par une image de secours
            img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjMzMzIi8+CiAgPHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iI2ZmZiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPlB1YmxpY2l0w6kgcGFyIGTDqWZhdXQ8L3RleHQ+Cjwvc3ZnPg==';
        };
        
        img.onload = function() {
            console.log(`✅ Image du slide ${index + 1} chargée:`, img.src);
        };
    });
});
</script>
    
    <!-- Dans la section des scripts, corriger l'ordre et les chemins -->
 <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="config/constants.js"></script>
    <script src="js/utils.js"></script>
    <script src="js/api.js"></script>
     <script src="js/app.js"></script>
    <script src="js/orders.js"></script>
    <script src="js/chart.js"></script>

</body>
</html>