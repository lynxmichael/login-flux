class SecureWalletManager {
    constructor() {
        this.baseUrl = '../php/wallet.php';
        this.balance = 0;
        this.transactions = [];
        this.userStocks = [];
        this.init();
    }

    init() {
        this.loadWalletData();
        this.bindEvents();
    }

    bindEvents() {
        // Événements pour l'interface
        document.getElementById('btnRefreshWallet')?.addEventListener('click', () => this.loadWalletData());
        document.getElementById('btnViewTransactions')?.addEventListener('click', () => this.showTransactions());
    }

    async makeSecureRequest(data) {
        try {
            const formData = new FormData();
            for (const key in data) {
                formData.append(key, data[key]);
            }

            // Ajouter le token d'authentification
            const userData = localStorage.getItem('user_data');
            if (userData) {
                const user = JSON.parse(userData);
                formData.append('token', user.session_token || '');
            }

            const response = await fetch(this.baseUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status}`);
            }

            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Request failed');
            }

            return result;

        } catch (error) {
            console.error('Secure request failed:', error);
            this.showError(error.message);
            throw error;
        }
    }

    async loadWalletData() {
        try {
            const [balanceResult, transactionsResult] = await Promise.all([
                this.makeSecureRequest({ action: 'get_balance' }),
                this.makeSecureRequest({ action: 'get_transactions', limit: 5 })
            ]);

            this.balance = balanceResult.balance;
            this.transactions = transactionsResult.transactions || [];

            this.updateDisplay();
            
        } catch (error) {
            console.error('Failed to load wallet data:', error);
        }
    }

    async executeBuy(stockSymbol, quantity, price) {
        try {
            const result = await this.makeSecureRequest({
                action: 'buy_stock',
                stock_symbol: stockSymbol,
                quantity: quantity,
                price: price
            });

            if (result.success) {
                this.balance = result.new_balance;
                this.updateDisplay();
                this.showSuccess(`Achat réussi! Nouveau solde: ${result.new_balance} FCFA`);
                return true;
            }
            
        } catch (error) {
            console.error('Buy order failed:', error);
            this.showError(`Échec de l'achat: ${error.message}`);
        }
        return false;
    }

    async executeSell(stockSymbol, quantity, price) {
        try {
            const result = await this.makeSecureRequest({
                action: 'sell_stock',
                stock_symbol: stockSymbol,
                quantity: quantity,
                price: price
            });

            if (result.success) {
                this.balance = result.new_balance;
                this.updateDisplay();
                this.showSuccess(`Vente réussie! Nouveau solde: ${result.new_balance} FCFA`);
                return true;
            }
            
        } catch (error) {
            console.error('Sell order failed:', error);
            this.showError(`Échec de la vente: ${error.message}`);
        }
        return false;
    }

    updateDisplay() {
        // Mettre à jour l'affichage du solde
        const balanceElement = document.getElementById('walletBalance');
        if (balanceElement) {
            balanceElement.textContent = `${this.balance.toLocaleString()} FCFA`;
        }

        // Mettre à jour l'historique des transactions
        this.updateTransactionsDisplay();
    }

    updateTransactionsDisplay() {
        const container = document.getElementById('transactionsList');
        if (!container) return;

        if (this.transactions.length === 0) {
            container.innerHTML = '<div class="no-data">Aucune transaction</div>';
            return;
        }

        const html = this.transactions.map(transaction => `
            <div class="transaction-item ${transaction.type}">
                <div class="transaction-header">
                    <span class="transaction-type">${this.getTypeLabel(transaction.type)}</span>
                    <span class="transaction-amount ${transaction.amount >= 0 ? 'positive' : 'negative'}">
                        ${transaction.amount >= 0 ? '+' : ''}${transaction.amount} FCFA
                    </span>
                </div>
                <div class="transaction-details">
                    <span>${transaction.stock_symbol} • ${transaction.quantity} actions</span>
                    <span>${new Date(transaction.created_at).toLocaleDateString('fr-FR')}</span>
                </div>
                <div class="transaction-description">${transaction.description}</div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    getTypeLabel(type) {
        const labels = {
            'buy': '🟢 Achat',
            'sell': '🔴 Vente',
            'deposit': '💰 Dépôt',
            'withdrawal': '💸 Retrait',
            'dividend': '📈 Dividende',
            'fee': '💳 Frais'
        };
        return labels[type] || type;
    }

    showTransactions() {
        const modal = document.getElementById('transactionsModal');
        if (modal) {
            this.updateTransactionsDisplay();
            modal.style.display = 'flex';
        }
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;

        document.body.appendChild(notification);

        // Animation d'entrée
        setTimeout(() => notification.classList.add('show'), 100);

        // Fermeture automatique
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 5000);

        // Fermeture manuelle
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        });
    }
}

// Initialisation sécurisée
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier que l'utilisateur est connecté
    const userData = localStorage.getItem('user_data');
    if (!userData) {
        console.warn('Utilisateur non connecté');
        return;
    }

    try {
        window.secureWallet = new SecureWalletManager();
        console.log('💰 SecureWalletManager initialisé');
    } catch (error) {
        console.error('❌ Erreur initialisation SecureWalletManager:', error);
    }
});