// Gestion des ordres
class OrderManager {
    constructor() {
        this.currentOrderType = 'buy';
        this.walletBalance = 0;
        this.init();
    }

    async init() {
        console.log('Initialisation OrderManager');
        
        // Charger le solde immédiatement
        await this.loadWalletBalance();
        console.log('💰 Solde initial chargé:', this.walletBalance);
        
        this.bindEvents();
        this.initOrderModal();
    }

    async loadWalletBalance() {
        try {
            console.log('🔄 Chargement du solde depuis l\'API...');
            
            const response = await fetch('wallet.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_balance'
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Solde chargé:', result.balance, result.currency);
                this.walletBalance = result.balance;
                
                // Mettre à jour les données globales
                if (window.PHP_USER_DATA) {
                    window.PHP_USER_DATA.wallet_balance = result.balance;
                }
                
                // Mettre à jour localStorage
                const userData = localStorage.getItem('user_data');
                if (userData) {
                    const parsedData = JSON.parse(userData);
                    parsedData.wallet_balance = result.balance;
                    localStorage.setItem('user_data', JSON.stringify(parsedData));
                }
                
                return result.balance;
            } else {
                console.error('❌ Erreur chargement solde:', result.message);
                return 0;
            }
        } catch (error) {
            console.error('❌ Erreur API solde:', error);
            return 0;
        }
    }

    bindEvents() {
        console.log('🔗 Attachement des événements aux boutons (OrderManager)');
        
        // Bouton Acheter
        const buyBtn = document.getElementById('btn-buy');
        if (buyBtn) {
            buyBtn.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('🟢 Bouton Acheter cliqué - Solde actuel:', this.walletBalance);
                this.openOrderModal('buy');
            });
            console.log('✅ Bouton Acheter configuré');
        } else {
            console.error('❌ Bouton Acheter non trouvé');
        }

        // Bouton Vendre
        const sellBtn = document.getElementById('btn-sell');
        if (sellBtn) {
            sellBtn.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('🔴 Bouton Vendre cliqué - Solde actuel:', this.walletBalance);
                this.openOrderModal('sell');
            });
            console.log('✅ Bouton Vendre configuré');
        } else {
            console.error('❌ Bouton Vendre non trouvé');
        }
    }

    initOrderModal() {
        console.log('Initialisation du modal des ordres');
        
        const modal = document.getElementById('orderModal');
        const closeBtn = document.querySelector('.close-modal');
        const cancelBtn = document.querySelector('.order-btn-cancel');

        if (!modal) {
            console.error('Modal des ordres non trouvé');
            return;
        }

        // Fermer le modal
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.closeOrderModal());
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.closeOrderModal());
        }

        // Formulaire de soumission
        const orderForm = document.getElementById('orderForm');
        if (orderForm) {
            orderForm.addEventListener('submit', (e) => this.handleOrderSubmit(e));
        }

        // Fermer en cliquant à l'extérieur
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeOrderModal();
            }
        });

        // Écouteurs pour les calculs en temps réel
        this.bindCalculationEvents();
    }

    bindCalculationEvents() {
        const quantityInput = document.getElementById('quantity');
        const priceInput = document.getElementById('price');
        const orderTypeInput = document.getElementById('orderType');

        if (quantityInput) {
            quantityInput.addEventListener('input', () => this.updateOrderCalculations());
        }
        
        if (priceInput) {
            priceInput.addEventListener('input', () => this.updateOrderCalculations());
        }
        
        if (orderTypeInput) {
            orderTypeInput.addEventListener('change', () => this.updateOrderCalculations());
        }
    }

    updateOrderCalculations() {
        try {
            const quantityInput = document.getElementById('quantity');
            const priceInput = document.getElementById('price');
            const orderTypeInput = document.getElementById('orderType');
            
            if (!quantityInput || !priceInput || !orderTypeInput) {
                console.error('❌ Éléments de calcul non trouvés');
                return;
            }

            const quantity = parseInt(quantityInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const orderType = orderTypeInput.value || 'buy';

            if (quantity <= 0 || price <= 0) {
                this.hideCalculationSection();
                return;
            }

            const subtotal = quantity * price;
            const fees = subtotal * 0.001; // 0.1%
            const totalWithFees = orderType === 'buy' ? subtotal + fees : subtotal - fees;

            this.updateCalculationDisplay(subtotal, fees, totalWithFees, orderType);
            this.showCalculationSection();

        } catch (error) {
            console.error('❌ Erreur dans updateOrderCalculations:', error);
        }
    }

    updateCalculationDisplay(subtotal, fees, totalWithFees, orderType) {
        try {
            const orderSubtotal = document.getElementById('orderSubtotal');
            const orderFees = document.getElementById('orderFees');
            const orderTotalWithFees = document.getElementById('orderTotalWithFees');
            const balanceCheck = document.getElementById('orderBalanceCheck');

            if (orderSubtotal) orderSubtotal.textContent = this.formatNumber(subtotal) + ' FCFA';
            if (orderFees) orderFees.textContent = this.formatNumber(fees) + ' FCFA';
            if (orderTotalWithFees) orderTotalWithFees.textContent = this.formatNumber(totalWithFees) + ' FCFA';

            // Vérification du solde
            if (balanceCheck) {
                const walletBalance = this.getCurrentWalletBalance();
                console.log('💰 Vérification solde:', { walletBalance, totalWithFees, orderType });
                
                if (orderType === 'buy') {
                    if (walletBalance >= totalWithFees) {
                        balanceCheck.textContent = `✅ Solde suffisant (${this.formatNumber(walletBalance)} FCFA disponible)`;
                        balanceCheck.style.color = 'var(--success-color)';
                        balanceCheck.style.background = 'rgba(40, 167, 69, 0.1)';
                    } else {
                        balanceCheck.textContent = `❌ Solde insuffisant (${this.formatNumber(walletBalance)} FCFA disponible)`;
                        balanceCheck.style.color = 'var(--danger-color)';
                        balanceCheck.style.background = 'rgba(220, 53, 69, 0.1)';
                    }
                } else {
                    balanceCheck.textContent = '✅ Ordre de vente';
                    balanceCheck.style.color = 'var(--success-color)';
                    balanceCheck.style.background = 'rgba(40, 167, 69, 0.1)';
                }
            }
        } catch (error) {
            console.error('❌ Erreur dans updateCalculationDisplay:', error);
        }
    }

    getCurrentWalletBalance() {
        try {
            // Si nous avons déjà un solde, l'utiliser
            if (this.walletBalance && this.walletBalance > 0) {
                return this.walletBalance;
            }
            
            // Essayer de récupérer le solde depuis WalletManager
            if (window.walletManager && window.walletManager.walletBalance) {
                this.walletBalance = window.walletManager.walletBalance;
                return this.walletBalance;
            }
            
            // Essayer depuis les données PHP
            if (window.PHP_USER_DATA && window.PHP_USER_DATA.wallet_balance) {
                this.walletBalance = window.PHP_USER_DATA.wallet_balance;
                return this.walletBalance;
            }
            
            // Essayer depuis localStorage
            const userData = localStorage.getItem('user_data');
            if (userData) {
                const parsedData = JSON.parse(userData);
                this.walletBalance = parsedData.wallet_balance || 0;
                return this.walletBalance;
            }
            
            console.warn('⚠️ Aucun solde trouvé localement, utilisation de 0');
            return 0;
            
        } catch (error) {
            console.error('❌ Erreur récupération solde:', error);
            return 0;
        }
    }

    showCalculationSection() {
        const calcSection = document.querySelector('.calculation-section');
        if (calcSection) {
            calcSection.style.display = 'block';
        }
    }

    hideCalculationSection() {
        const calcSection = document.querySelector('.calculation-section');
        if (calcSection) {
            calcSection.style.display = 'none';
        }
    }

    formatNumber(num) {
        return new Intl.NumberFormat('fr-FR').format(num);
    }

    openOrderModal(type) {
        console.log(`🎯 OrderManager.openOrderModal('${type}') appelé - Solde: ${this.walletBalance}`);
        
        // Vérifier si l'utilisateur est connecté
        if (!this.isUserLoggedIn()) {
            console.error('❌ Utilisateur non connecté');
            alert('Veuillez vous connecter pour effectuer cette action');
            return;
        }

        console.log('✅ Utilisateur connecté, ouverture du modal...');
        
        const modal = document.getElementById('orderModal');
        if (modal) {
            this.currentOrderType = type;
            this.updateOrderModal(type);
            modal.style.display = 'flex';
            console.log('✅ Modal ouvert');
            
            // Mettre à jour les calculs initiaux
            setTimeout(() => this.updateOrderCalculations(), 100);
        } else {
            console.error('❌ Modal orderModal non trouvé');
        }
    }

    isUserLoggedIn() {
        // Vérification multi-sources
        const checks = {
            hasWalletManager: !!(window.walletManager && window.walletManager.currentUser),
            hasPhpData: !!(window.PHP_USER_DATA && window.PHP_USER_DATA.id),
            hasLocalStorage: !!localStorage.getItem('user_data')
        };
        
        console.log('🔍 Vérification connexion (OrderManager):', checks);
        
        // Si au moins une vérification passe
        return checks.hasWalletManager || checks.hasPhpData || checks.hasLocalStorage;
    }

    updateOrderModal(type) {
        try {
            const modalTitle = document.getElementById('modal-title');
            const orderTypeDisplay = document.getElementById('orderTypeDisplay');
            const orderTypeHidden = document.getElementById('orderType');
            const priceInput = document.getElementById('price');
            const confirmBtn = document.getElementById('confirmOrder');
            const stockInput = document.getElementById('stockName');
            const operationDateDisplay = document.getElementById('operationDateDisplay');
            
            if (!modalTitle || !orderTypeDisplay || !orderTypeHidden || !priceInput || !confirmBtn) {
                console.error('❌ Éléments du modal non trouvés');
                return;
            }

            // Titre et type
            if (type === 'buy') {
                modalTitle.textContent = '🟢 Acheter des Actions';
                confirmBtn.textContent = 'Confirmer l\'achat';
                confirmBtn.className = 'order-modal-btn order-btn-confirm-buy';
                orderTypeDisplay.value = 'ACHAT';
                orderTypeDisplay.style.color = 'var(--success-color)';
            } else {
                modalTitle.textContent = '🔴 Vendre des Actions';
                confirmBtn.textContent = 'Confirmer la vente';
                confirmBtn.className = 'order-modal-btn order-btn-confirm-sell';
                orderTypeDisplay.value = 'VENTE';
                orderTypeDisplay.style.color = 'var(--danger-color)';
            }
            
            orderTypeHidden.value = type;
            
            // Stock
            if (stockInput) {
                stockInput.value = 'SEMC';
            }
            
            // Prix actuel
            const currentPriceElement = document.getElementById('current-price');
            if (currentPriceElement) {
                priceInput.value = currentPriceElement.textContent.replace(/[^\d]/g, '');
            }
            
            // Date d'opération
            if (operationDateDisplay) {
                const now = new Date();
                const dateString = now.toLocaleDateString('fr-FR') + ' ' + now.toLocaleTimeString('fr-FR');
                operationDateDisplay.value = dateString;
            }

            // Mettre à jour le champ hidden operationDate
            const operationDateHidden = document.getElementById('operationDate');
            if (operationDateHidden) {
                operationDateHidden.value = new Date().toISOString().slice(0, 19).replace('T', ' ');
            }

        } catch (error) {
            console.error('❌ Erreur dans updateOrderModal:', error);
        }
    }

    closeOrderModal() {
        const modal = document.getElementById('orderModal');
        if (modal) {
            modal.style.display = 'none';
            console.log('✅ Modal des ordres fermé');
        }
        
        const orderForm = document.getElementById('orderForm');
        if (orderForm) {
            orderForm.reset();
        }
        
        this.hideCalculationSection();
    }

    async handleOrderSubmit(e) {
        e.preventDefault();
        console.log('📝 Soumission du formulaire d\'ordre');
        
        try {
            // Vérifier la connexion
            if (!this.isUserLoggedIn()) {
                alert('Veuillez vous connecter pour passer un ordre');
                return;
            }

            // Récupérer les éléments avec vérification
            const quantityElement = document.getElementById('quantity');
            const priceElement = document.getElementById('price');
            const orderTypeElement = document.getElementById('orderType');
            const stockNameElement = document.getElementById('stockName');

            if (!quantityElement || !priceElement || !orderTypeElement || !stockNameElement) {
                throw new Error('Éléments du formulaire non trouvés');
            }

            const quantity = parseInt(quantityElement.value);
            const price = parseFloat(priceElement.value);
            const orderType = orderTypeElement.value;
            const stockName = stockNameElement.value;

            // Validation
            if (!quantity || quantity <= 0) {
                alert('Veuillez saisir une quantité valide');
                return;
            }

            if (!price || price <= 0) {
                alert('Veuillez saisir un prix valide');
                return;
            }

            // Calculer le total
            const total = quantity * price;
            const fees = total * 0.001;
            const totalWithFees = orderType === 'buy' ? total + fees : total - fees;

            // Vérification du solde pour les achats
            if (orderType === 'buy') {
                const walletBalance = this.getCurrentWalletBalance();
                console.log('💰 Vérification finale solde:', { walletBalance, totalWithFees });
                
                if (walletBalance < totalWithFees) {
                    alert(`❌ Solde insuffisant\n\nNécessaire: ${this.formatNumber(totalWithFees)} FCFA\nDisponible: ${this.formatNumber(walletBalance)} FCFA`);
                    return;
                }
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

            console.log('📤 Envoi de l\'ordre:', { orderType, stockName, quantity, price });

            // Appel API
            const formData = new FormData();
            formData.append('action', orderType === 'buy' ? 'buy_stock' : 'sell_stock');
            formData.append('stock_symbol', stockName);
            formData.append('quantity', quantity);
            formData.append('price', price);

            const response = await fetch('wallet.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert(`✅ Ordre d'${action} confirmé!\n\n${result.message}\nNouveau solde: ${this.formatNumber(result.new_balance)} FCFA`);
                
                // Mettre à jour le solde local
                this.updateLocalBalance(result.new_balance);
                
                this.closeOrderModal();
                
                // Rafraîchir les données
                this.refreshWalletData();
                
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('❌ Erreur lors de la soumission:', error);
            alert('❌ Erreur: ' + (error.message || 'Impossible de passer l\'ordre'));
        }
    }

    updateLocalBalance(newBalance) {
        // Mettre à jour le solde local
        this.walletBalance = newBalance;
        
        // Mettre à jour WalletManager si disponible
        if (window.walletManager) {
            window.walletManager.walletBalance = newBalance;
            window.walletManager.updateWalletDisplay();
        }
        
        // Mettre à jour les données locales
        const userData = localStorage.getItem('user_data');
        if (userData) {
            const parsedData = JSON.parse(userData);
            parsedData.wallet_balance = newBalance;
            localStorage.setItem('user_data', JSON.stringify(parsedData));
        }
        
        // Mettre à jour les données PHP
        if (window.PHP_USER_DATA) {
            window.PHP_USER_DATA.wallet_balance = newBalance;
        }
        
        console.log('💰 Solde mis à jour:', newBalance);
    }

    async refreshWalletData() {
        try {
            // Recharger le solde depuis l'API
            await this.loadWalletBalance();
            console.log('💰 Solde rafraîchi:', this.walletBalance);
            
            // Mettre à jour les calculs si le modal est ouvert
            this.updateOrderCalculations();
            
        } catch (error) {
            console.error('❌ Erreur rafraîchissement:', error);
        }
    }
}

// INITIALISATION
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 DOM chargé - Initialisation OrderManager...');
    
    // Initialiser OrderManager
    window.orderManager = new OrderManager();
    console.log('✅ OrderManager initialisé');
});