
<?php
require_once(__DIR__ . '/php/user.php');
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
    <!-- Modal d'authentification -->
    <div id="authModal" class="auth-modal">
        <div class="auth-modal-content">
            <div class="auth-modal-header">
                <h3>Connexion à FluxIO</h3>
                <p>Accédez à votre espace de trading</p>
            </div>
            
            <div class="auth-tabs">
                <div class="auth-tab active" data-tab="login">Connexion</div>
                <div class="auth-tab" data-tab="register">Inscription</div>
            </div>
            
            <!-- Formulaire de connexion -->
            <div id="loginTab" class="auth-tab-content active">
                <form id="loginForm">
                    <div class="auth-form-group">
                        <label for="loginEmail">Adresse email</label>
                        <input type="email" id="loginEmail" required placeholder="votre@email.com">
                    </div>
                    <div class="auth-form-group">
                        <label for="loginPassword">Mot de passe</label>
                        <input type="password" id="loginPassword" required placeholder="Votre mot de passe">
                    </div>
                    <div id="loginError" class="auth-error">
                        Identifiants incorrects. Veuillez réessayer.
                    </div>
                    <div class="auth-modal-buttons">
                        <button type="button" class="auth-modal-btn auth-btn-secondary" id="authCancel">Annuler</button>
                        <button type="submit" class="auth-modal-btn auth-btn-primary">Se connecter</button>
                    </div>
                </form>
            </div>
            
            <!-- Formulaire d'inscription -->
            <div id="registerTab" class="auth-tab-content">
                <form id="registerForm">
                    <div class="auth-form-group">
                        <label for="registerName">Nom complet</label>
                        <input type="text" id="registerName" required placeholder="Votre nom complet">
                    </div>
                    <div class="auth-form-group">
                        <label for="registerEmail">Adresse email</label>
                        <input type="email" id="registerEmail" required placeholder="votre@email.com">
                    </div>
                    <div class="auth-form-group">
                        <label for="registerPassword">Mot de passe</label>
                        <input type="password" id="registerPassword" required placeholder="Créez un mot de passe">
                    </div>
                    <div class="auth-form-group">
                        <label for="registerConfirmPassword">Confirmer le mot de passe</label>
                        <input type="password" id="registerConfirmPassword" required placeholder="Confirmez votre mot de passe">
                    </div>
                    <div id="registerError" class="auth-error">
                        Erreur lors de l'inscription. Veuillez réessayer.
                    </div>
                    <div id="registerSuccess" class="auth-success">
                        Inscription réussie ! Vous pouvez maintenant vous connecter.
                    </div>
                    <div class="auth-modal-buttons">
                        <button type="button" class="auth-modal-btn auth-btn-secondary" id="registerCancel">Annuler</button>
                        <button type="submit" class="auth-modal-btn auth-btn-primary">S'inscrire</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal profil utilisateur -->
    <div id="profileModal" class="profile-modal">
        <div class="profile-modal-content">
            <div class="profile-header">
                <div class="profile-avatar" id="profileAvatar">U</div>
                <h3 id="profileName">Utilisateur</h3>
                <p id="profileEmail">utilisateur@email.com</p>
            </div>
            
            <div class="profile-info">
                <div class="profile-info-item">
                    <span class="profile-info-label">Membre depuis</span>
                    <span class="profile-info-value" id="profileSince">2025</span>
                </div>
                <div class="profile-info-item">
                    <span class="profile-info-label">Portefeuille</span>
                    <span class="profile-info-value" id="profileWallet">0 FCFA</span>
                </div>
                <div class="profile-info-item">
                    <span class="profile-info-label">Dernière connexion</span>
                    <span class="profile-info-value" id="profileLastLogin">Aujourd'hui</span>
                </div>
            </div>
            
            <div class="profile-stats">
                <div class="profile-stat">
                    <div class="profile-stat-value" id="profileTrades">0</div>
                    <div class="profile-stat-label">Transactions</div>
                </div>
                <div class="profile-stat">
                    <div class="profile-stat-value" id="profileProfit">0%</div>
                    <div class="profile-stat-label">Performance</div>
                </div>
            </div>
            
            <div class="auth-modal-buttons">
                <button type="button" class="auth-modal-btn auth-btn-secondary" id="profileClose">Fermer</button>
                <button type="button" class="auth-modal-btn auth-btn-primary" onclick="deconnexion()">Déconnexion</button>
            </div>
        </div>
    </div>

    <!-- Modal pour les ordres d'achat/vente -->
    <div id="orderModal" class="order-modal">
        <div class="order-modal-content">
            <div class="order-modal-header">
                <h3 id="modal-title">Passer un ordre</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="order-modal-body active">
                <form id="orderForm">
                    <div class="order-form-group">
                        <label for="recipient">Destinataire</label>
                        <input type="text" id="recipient" required>
                    </div>
                    <div class="order-form-group">
                        <label for="orderType">Type d'ordre</label>
                        <select id="orderType" required>
                            <option value="buy">Achat</option>
                            <option value="sell">Vente</option>
                        </select>
                    </div>
                    <div class="order-form-group">
                        <label for="stockName">Nom de l'action</label>
                        <input type="text" id="stockName" value="SEMC" readonly>
                    </div>
                    <div class="order-form-group">
                        <label for="quantity">Quantité</label>
                        <input type="number" id="quantity" min="1" required>
                    </div>
                    <div class="order-form-group">
                        <label for="price">Prix (FCFA)</label>
                        <input type="number" id="price" step="0.01" required>
                    </div>
                    <div class="order-form-group">
                        <label for="validityDate">Date de validité</label>
                        <input type="date" id="validityDate" required>
                    </div>
                    <div class="order-modal-buttons">
                        <button type="button" class="order-modal-btn order-btn-cancel">Annuler</button>
                        <button type="submit" id="confirmOrder" class="order-modal-btn order-btn-confirm-buy">Confirmer l'ordre</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <header>
        <nav>
            <h2>SEMC - <span id="current-price">720</span> FCFA</h2>
            <div class="auth-buttons">
                <button class="auth-btn btn-register" id="btnRegister">S'inscrire</button>
                <button class="auth-btn btn-login" id="btnLogin">Connexion</button>
                <button class="auth-btn btn-profile" id="btnProfile" style="display: none;">
                    <span>Mon Profil</span>
                </button>
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
            </table>
        </section>
    </div>
    <footer>
        FluxIO © 2025 - Interface de démonstration
    </footer>

    <!-- Dans la section des scripts, corriger l'ordre et les chemins -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="config/constants.js"></script>
    <script src="js/utils.js"></script>
    <script src="js/api.js"></script>
    <script src="js/auth.js"></script>
    <script src="js/orders.js"></script>
    <script src="js/chart.js"></script>
    <script src="js/app.js"></script>
    <script src="js/websocket-service.js"></script>

    <script>
    // Remplacer tout le script existant par ceci :
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM chargé - Initialisation des composants');
        
        // Initialiser AuthManager en premier
        if (typeof AuthManager !== 'undefined') {
            window.authManager = new AuthManager();
        }
        
        // Initialiser OrderManager après AuthManager
        setTimeout(() => {
            if (typeof OrderManager !== 'undefined') {
                window.orderManager = new OrderManager();
            }
        }, 100);
        
        // Initialiser ChartManager
        if (typeof ChartManager !== 'undefined') {
            window.chartManager = new ChartManager();
        }
        
        // Initialiser TradingApp
        if (typeof TradingApp !== 'undefined') {
            window.tradingApp = new TradingApp();
        }
        
        // Attacher les événements manuels
        attachEventListeners();
        initStockSearch();
    });

    function attachEventListeners() {
        console.log('Attachement des événements manuels');
        
        // Boutons d'authentification avec fallback robuste
        document.getElementById('btnLogin')?.addEventListener('click', function() {
            if (window.authManager && typeof window.authManager.openAuthModal === 'function') {
                window.authManager.openAuthModal();
            } else {
                document.getElementById('authModal').style.display = 'flex';
            }
        });
        
        document.getElementById('btnRegister')?.addEventListener('click', function() {
            if (window.authManager && typeof window.authManager.openRegisterModal === 'function') {
                window.authManager.openRegisterModal();
            } else {
                document.getElementById('authModal').style.display = 'flex';
                document.querySelectorAll('.auth-tab').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.auth-tab-content').forEach(content => content.classList.remove('active'));
                document.querySelector('.auth-tab[data-tab="register"]')?.classList.add('active');
                document.getElementById('registerTab')?.classList.add('active');
            }
        });
        
        document.getElementById('btnProfile')?.addEventListener('click', function() {
            if (window.authManager && typeof window.authManager.openProfileModal === 'function') {
                window.authManager.openProfileModal();
            }
        });
        
        // Boutons d'achat/vente avec fallback
        document.getElementById('btn-buy')?.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.orderManager && typeof window.orderManager.openOrderModal === 'function') {
                window.orderManager.openOrderModal('buy');
            } else {
                document.getElementById('orderModal').style.display = 'flex';
            }
        });
        
        document.getElementById('btn-sell')?.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.orderManager && typeof window.orderManager.openOrderModal === 'function') {
                window.orderManager.openOrderModal('sell');
            } else {
                document.getElementById('orderModal').style.display = 'flex';
            }
        });
        
        // Fermeture des modals
        document.querySelectorAll('.close-modal, .order-btn-cancel, #authCancel, #registerCancel, #profileClose').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.auth-modal, .profile-modal, .order-modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            });
        });
    }

    function initStockSearch() {
        const searchInput = document.getElementById('stockSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const query = e.target.value.toUpperCase();
                const rows = document.querySelectorAll('#stocksTable tr');
                
                for (let i = 1; i < rows.length; i++) {
                    const symbol = rows[i].querySelector('.stock-symbol')?.textContent.toUpperCase();
                    if (symbol && symbol.includes(query)) {
                        rows[i].style.display = '';
                    } else {
                        rows[i].style.display = 'none';
                    }
                }
            });
        }
    }

    function deconnexion() {
        if (window.authManager && typeof window.authManager.logout === 'function') {
            window.authManager.logout();
        } else {
            if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
                localStorage.removeItem('authToken');
                window.location.reload();
            }
        }
    }

    // Fonction pour charger les données de l'entreprise sélectionnée
    function loadSelectedStock() {
        const stockData = localStorage.getItem('selectedStock');
        
        if (stockData) {
            const stock = JSON.parse(stockData);
            updatePageWithStockData(stock);
            
            // Nettoyer le localStorage après utilisation
            localStorage.removeItem('selectedStock');
        } else {
            // Si aucune donnée n'est trouvée, utiliser SEMC par défaut
            const defaultStock = AppState.stocks.find(s => s.symbol === 'SEMC') || AppState.stocks[0];
            if (defaultStock) {
                updatePageWithStockData(defaultStock);
            }
        }
    }

    // Fonction pour mettre à jour la page avec les données de l'entreprise
    function updatePageWithStockData(stock) {
        // Mettre à jour le header
        const headerTitle = document.querySelector('header h2');
        if (headerTitle) {
            headerTitle.innerHTML = `${stock.symbol} - <span id="current-price">${stock.price}</span> FCFA`;
        }
        
        // Mettre à jour le titre du graphique
        const chartTitle = document.getElementById('chart-title');
        if (chartTitle) {
            chartTitle.textContent = `1H - ${stock.symbol}`;
        }
        
        // Mettre à jour les données de marché (simulation)
        updateMarketData(stock);
        
        // Mettre à jour les informations de l'entreprise
        updateCompanyInfo(stock);
        
        // Stocker le symbole actuel pour les ordres
        if (window.orderManager) {
            window.orderManager.currentStock = stock.symbol;
        }
    }

    // Fonction pour mettre à jour les données de marché
    function updateMarketData(stock) {
        // Générer des données de marché réalistes basées sur le prix de l'action
        const basePrice = stock.price;
        
        // Mettre à jour le prix actuel
        const priceElement = document.getElementById('current-price');
        if (priceElement) {
            priceElement.textContent = stock.price;
        }
        
        // Mettre à jour les tableaux d'ordres (vendeurs et acheteurs)
        updateOrderTables(basePrice);
    }

    // Fonction pour mettre à jour les tableaux d'ordres
    function updateOrderTables(basePrice) {
        const sellersTable = document.getElementById('sellers-table');
        const buyersTable = document.getElementById('buyers-table');
        
        if (sellersTable && buyersTable) {
            // Générer des données de vendeurs (prix > prix actuel)
            let sellersHTML = '<tr><th>Prix</th><th>Volume</th><th>Total</th></tr>';
            let totalSellers = 0;
            
            for (let i = 0; i < 7; i++) {
                const price = Math.round(basePrice * (1 + 0.02 + Math.random() * 0.03));
                const volume = Math.floor(Math.random() * 3000) + 500;
                totalSellers += volume;
                sellersHTML += `<tr class="sell"><td>${price}</td><td>${volume.toLocaleString()}</td><td>${totalSellers.toLocaleString()}</td></tr>`;
            }
            
            sellersTable.innerHTML = sellersHTML;
            
            // Générer des données d'acheteurs (prix < prix actuel)
            let buyersHTML = '<tr><th>Prix</th><th>Volume</th><th>Total</th></tr>';
            let totalBuyers = 0;
            
            for (let i = 0; i < 7; i++) {
                const price = Math.round(basePrice * (1 - 0.02 - Math.random() * 0.03));
                const volume = Math.floor(Math.random() * 3000) + 500;
                totalBuyers += volume;
                buyersHTML += `<tr class="buy"><td>${price}</td><td>${volume.toLocaleString()}</td><td>${totalBuyers.toLocaleString()}</td></tr>`;
            }
            
            buyersTable.innerHTML = buyersHTML;
            
            // Mettre à jour l'écart de prix
            updatePriceGap();
        }
    }

    // Fonction pour mettre à jour l'écart de prix
    function updatePriceGap() {
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

    // Fonction pour mettre à jour les informations de l'entreprise
    function updateCompanyInfo(stock) {
        const companyData = COMPANY_DATA[stock.symbol];
        
        if (companyData) {
            // Créer ou mettre à jour une section d'informations sur l'entreprise
            let infoSection = document.getElementById('company-info');
            
            if (!infoSection) {
                infoSection = document.createElement('div');
                infoSection.id = 'company-info';
                infoSection.className = 'company-info';
                infoSection.style.cssText = `
                    background: var(--surface-dark);
                    padding: 20px;
                    margin: 20px 0;
                    border-radius: 8px;
                    border-left: 4px solid var(--primary-color);
                `;
                
                // Insérer après le header
                const header = document.querySelector('header');
                if (header) {
                    header.parentNode.insertBefore(infoSection, header.nextSibling);
                }
            }
            
            infoSection.innerHTML = `
                <h3>Informations sur ${companyData.name}</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                    <div>
                        <strong>Nom complet:</strong><br>
                        ${companyData.fullName}
                    </div>
                    <div>
                        <strong>Secteur:</strong><br>
                        ${companyData.sector}
                    </div>
                    <div>
                        <strong>Marché:</strong><br>
                        ${companyData.market}
                    </div>
                    <div>
                        <strong>Employés:</strong><br>
                        ${companyData.employees.toLocaleString()}
                    </div>
                    <div>
                        <strong>Fondation:</strong><br>
                        ${companyData.founded}
                    </div>
                    <div>
                        <strong>Site web:</strong><br>
                        <a href="${companyData.website}" target="_blank" style="color: var(--primary-color);">${companyData.website}</a>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <strong>Description:</strong><br>
                    ${companyData.description}
                </div>
            `;
        }
    }

    // Modifier l'initialisation pour charger l'action sélectionnée
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM chargé - Initialisation des composants');
        
        // Charger les données de l'entreprise sélectionnée en premier
        loadSelectedStock();
        
        // Initialiser AuthManager en premier
        if (typeof AuthManager !== 'undefined') {
            window.authManager = new AuthManager();
        }
        
        // Initialiser OrderManager après AuthManager
        setTimeout(() => {
            if (typeof OrderManager !== 'undefined') {
                window.orderManager = new OrderManager();
            }
        }, 100);
        
        // Initialiser ChartManager
        if (typeof ChartManager !== 'undefined') {
            window.chartManager = new ChartManager();
        }
        
        // Initialiser TradingApp
        if (typeof TradingApp !== 'undefined') {
            window.tradingApp = new TradingApp();
        }
        
        // Attacher les événements manuels
        attachEventListeners();
        initStockSearch();
    });

    
    </script>
</body>
</html>