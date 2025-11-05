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
        this.updateCurrentPrice();
        this.bindTradeButtons();
        this.bindModalControls();
        this.bindFormEvents();
        this.updateWalletDisplay();
        
        console.log('✅ Configuration terminée');
    }

    // ==========================================
    // SYNCHRONISATION DES DONNÉES UTILISATEUR
    // ==========================================
    
    syncUserData() {
        console.log('🔄 Synchronisation des données utilisateur...');
        
        // Priorité 1: Données PHP injectées
        if (window.PHP_USER_DATA && window.PHP_USER_DATA.id) {
            this.currentUser = window.PHP_USER_DATA;
            this.walletBalance = window.PHP_USER_DATA.wallet_balance || 0;
            console.log('✅ Données PHP trouvées:', this.currentUser);
            return true;
        }
        
        // Priorité 2: localStorage
        const storedData = localStorage.getItem('user_data');
        if (storedData) {
            try {
                this.currentUser = JSON.parse(storedData);
                this.walletBalance = this.currentUser.wallet_balance || 0;
                console.log('✅ Données localStorage trouvées:', this.currentUser);
                return true;
            } catch (e) {
                console.error('❌ Erreur parsing localStorage:', e);
            }
        }
        
        // Priorité 3: Session PHP (depuis la page)
        const walletElement = document.getElementById('walletBalance');
        if (walletElement) {
            const balanceText = walletElement.textContent.replace(/[^\d]/g, '');
            this.walletBalance = parseInt(balanceText) || 0;
            console.log('✅ Balance trouvée dans le DOM:', this.walletBalance);
        }
        
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
    
    openOrderModal(orderType) {
        console.log(`📂 Ouverture du modal ${orderType}...`);
        
        const modal = document.getElementById('orderModal');
        if (!modal) {
            console.error('❌ Modal orderModal introuvable dans le DOM');
            alert('Erreur: Modal non trouvé. Rechargez la page.');
            return;
        }

        // Mettre à jour l'état
        this.currentOrderType = orderType;
        this.updateCurrentPrice();
        
        // Réinitialiser et configurer le formulaire
        this.resetOrderForm();
        this.configureOrderModal(orderType);
        this.updateOrderCalculations();
        
        // Afficher le modal
        modal.style.display = 'flex';
        
        console.log(`✅ Modal ${orderType} ouvert avec succès`);
        
        // Focus sur le champ quantité
        setTimeout(() => {
            const qtyInput = document.getElementById('quantity');
            if (qtyInput) qtyInput.focus();
        }, 100);
    }

    configureOrderModal(orderType) {
        const elements = {
            title: document.getElementById('modal-title'),
            confirmBtn: document.getElementById('confirmOrder'),
            typeSelect: document.getElementById('orderType'),
            stockInput: document.getElementById('stockName'),
            priceInput: document.getElementById('price'),
            validityDate: document.getElementById('validityDate')
        };

        // Vérifier que tous les éléments existent
        const missingElements = Object.entries(elements)
            .filter(([key, el]) => !el)
            .map(([key]) => key);
        
        if (missingElements.length > 0) {
            console.error('❌ Éléments manquants:', missingElements);
        }

        // Configuration selon le type
        if (orderType === 'buy') {
            if (elements.title) elements.title.textContent = '🟢 Acheter des Actions';
            if (elements.confirmBtn) {
                elements.confirmBtn.textContent = 'Confirmer l\'achat';
                elements.confirmBtn.className = 'order-modal-btn order-btn-confirm-buy';
            }
            if (elements.typeSelect) elements.typeSelect.value = 'buy';
        } else {
            if (elements.title) elements.title.textContent = '🔴 Vendre des Actions';
            if (elements.confirmBtn) {
                elements.confirmBtn.textContent = 'Confirmer la vente';
                elements.confirmBtn.className = 'order-modal-btn order-btn-confirm-sell';
            }
            if (elements.typeSelect) elements.typeSelect.value = 'sell';
        }

        // Pré-remplir les champs
        if (elements.stockInput) elements.stockInput.value = this.currentStock;
        if (elements.priceInput) elements.priceInput.value = this.currentPrice;
        
        // Date de validité (7 jours)
        if (elements.validityDate) {
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            elements.validityDate.value = nextWeek.toISOString().split('T')[0];
        }

        // Masquer le champ destinataire
        const recipientGroup = document.querySelector('.order-form-group:first-child');
        if (recipientGroup) {
            recipientGroup.style.display = 'none';
        }
    }

    resetOrderForm() {
        const form = document.getElementById('orderForm');
        if (form) {
            form.reset();
        }
    }

    closeOrderModal() {
        const modal = document.getElementById('orderModal');
        if (modal) {
            modal.style.display = 'none';
            this.resetOrderForm();
            console.log('✅ Modal fermé');
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
            maximumFractionDigits: 2
        }).format(num);
    }

    // ==========================================
    // SOUMISSION DU FORMULAIRE
    // ==========================================
    
    async handleOrderSubmit(e) {
        e.preventDefault();
        console.log('📝 Soumission du formulaire...');
        
        const quantity = parseInt(document.getElementById('quantity').value);
        const price = parseFloat(document.getElementById('price').value);
        const orderType = document.getElementById('orderType').value;
        const validityDate = document.getElementById('validityDate').value;
        
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
        const totalWithFees = total + fees;
        
        // Vérification du solde pour les achats
        if (orderType === 'buy' && this.walletBalance < totalWithFees) {
            alert(`❌ Solde insuffisant\n\nNécessaire: ${this.formatNumber(totalWithFees)} FCFA\nDisponible: ${this.formatNumber(this.walletBalance)} FCFA`);
            return;
        }
        
        // Confirmation
        const action = orderType === 'buy' ? 'achat' : 'vente';
        const confirmMsg = `Confirmer cet ordre d'${action}?\n\n` +
                          `📊 ${this.currentStock}\n` +
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
            // Simuler l'envoi (remplacer par votre API)
            await this.submitOrder({
                type: orderType,
                stock: this.currentStock,
                quantity,
                price,
                validityDate,
                total: totalWithFees
            });
            
            alert(`✅ Ordre d'${action} confirmé!\n\nVotre ordre a été enregistré avec succès.`);
            this.closeOrderModal();
            
            // Recharger les données
            this.syncUserData();
            this.updateWalletDisplay();
            
        } catch (error) {
            console.error('❌ Erreur soumission:', error);
            alert('❌ Erreur: ' + error.message);
        }
    }

    async submitOrder(orderData) {
        console.log('📤 Envoi de l\'ordre:', orderData);
        
        // Simulation (remplacer par votre appel API réel)
        return new Promise((resolve) => {
            setTimeout(() => {
                console.log('✅ Ordre confirmé');
                resolve({ success: true, orderId: 'ORDER_' + Date.now() });
            }, 1000);
        });
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

console.log('✅ Script de gestion des modals chargé avec succès');
console.log('💡 Utilisez window.debugModal() pour déboguer');
console.log('💡 Utilisez window.forceOpenModal("buy") ou window.forceOpenModal("sell") pour forcer l\'ouverture');