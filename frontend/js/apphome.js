// Configuration API - CORRIGE : même port
const API_BASE_URL = 'http://localhost:3000/api';
const WS_URL = 'ws://localhost:3000'; // Même port que le service

// Application state - AJOUT : fonctions de mise à jour manquantes
const AppState = {
    stocks: [],
    favorites: JSON.parse(localStorage.getItem('favorites') || '[]'),
    currentView: 'overview',
    sortColumn: 'rank',
    sortDirection: 'asc',
    isConverting: false,
    searchQuery: '',
    selectedMarket: 'all',
    websocket: null,
    isConnected: false,
    currentPage: 1,
    itemsPerPage: 10,
    totalPages: 1
};

// AJOUT: Fonctions de mise à jour pour WebSocket
window.app = {
    updateAsset: function(asset) {
        console.log('🔄 Mise à jour actif:', asset);
        
        // Trouver la ligne du tableau correspondante
        const row = document.querySelector(`tr[data-symbol="${asset.symbol}"]`);
        if (row) {
            // Mettre à jour le prix avec animation
            const priceCell = row.querySelector('td:nth-child(3)');
            if (priceCell) {
                const oldPrice = parseFloat(priceCell.textContent.replace(/[^\d.]/g, ''));
                const newPrice = asset.price || asset.currentPrice;
                
                priceCell.textContent = UI.formatPrice(newPrice) + ' FCFA';
                priceCell.classList.add('price-update');
                setTimeout(() => priceCell.classList.remove('price-update'), 1000);
            }
            
            // Mettre à jour les pourcentages si disponibles
            if (asset.change24h !== undefined) {
                const changeCell = row.querySelector('td:nth-child(8)');
                if (changeCell) {
                    changeCell.textContent = UI.formatChange(asset.change24h);
                    changeCell.className = `center ${UI.getChangeClass(asset.change24h)} price-update`;
                    setTimeout(() => changeCell.classList.remove('price-update'), 1000);
                }
            }
        }
    },
    
    updateAllAssets: function(assets) {
        console.log('🌐 Mise à jour globale:', assets.length, 'actifs');
        if (Array.isArray(assets)) {
            assets.forEach(asset => this.updateAsset(asset));
        }
    },
    
    updateConnectionStatus: function(connected) {
        console.log('🔌 Statut connexion:', connected);
        
        // Créer ou mettre à jour l'indicateur de statut
        let statusElement = document.getElementById('connection-status');
        if (!statusElement) {
            statusElement = document.createElement('div');
            statusElement.id = 'connection-status';
            statusElement.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 14px;
                z-index: 1000;
                background: rgba(0,0,0,0.8);
                color: white;
                border: 2px solid;
            `;
            document.body.appendChild(statusElement);
        }
        
        if (connected) {
            statusElement.textContent = '🟢 Connecté en temps réel';
            statusElement.style.borderColor = '#51CF66';
            statusElement.style.color = '#51CF66';
        } else {
            statusElement.textContent = '🔴 Déconnecté';
            statusElement.style.borderColor = '#FF8787';
            statusElement.style.color = '#FF8787';
        }
    }
};

// Service WebSocket simplifié
class WebSocketService {
    constructor() {
        this.socket = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectInterval = 3000;
    }

    connect() {
        try {
            console.log('🔄 Connexion WebSocket vers:', WS_URL);
            
            if (this.socket) {
                this.socket.close();
            }

            this.socket = new WebSocket(WS_URL);
            
            this.socket.onopen = () => {
                console.log('✅ Connecté au serveur WebSocket');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                window.app.updateConnectionStatus(true);
            };

            this.socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    console.log('📨 Message reçu:', data);
                    this.handleMessage(data);
                } catch (error) {
                    console.error('❌ Erreur parsing message:', error);
                }
            };

            this.socket.onclose = (event) => {
                console.log('❌ Déconnexion WebSocket:', event.code, event.reason);
                this.isConnected = false;
                window.app.updateConnectionStatus(false);
                this.attemptReconnect();
            };

            this.socket.onerror = (error) => {
                console.error('❌ Erreur WebSocket:', error);
                this.isConnected = false;
            };

        } catch (error) {
            console.error('❌ Erreur connexion WebSocket:', error);
            this.attemptReconnect();
        }
    }

    handleMessage(data) {
        // Gestion flexible des différents formats de message
        if (data.event === 'asset-update' && data.data) {
            window.app.updateAsset(data.data);
        } else if (data.event === 'market-update' && data.data) {
            window.app.updateAllAssets(data.data);
        } else if (data.symbol && data.price !== undefined) {
            // Format direct {symbol: 'ABC', price: 123}
            window.app.updateAsset(data);
        } else if (Array.isArray(data)) {
            // Tableau d'actifs
            window.app.updateAllAssets(data);
        }
    }

    attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = this.reconnectInterval * this.reconnectAttempts;
            console.log(`🔄 Reconnexion ${this.reconnectAttempts}/${this.maxReconnectAttempts} dans ${delay}ms`);
            
            setTimeout(() => {
                if (!this.isConnected) {
                    this.connect();
                }
            }, delay);
        } else {
            console.error('❌ Échec reconnexion après', this.maxReconnectAttempts, 'tentatives');
        }
    }

    disconnect() {
        if (this.socket) {
            this.socket.close();
            this.socket = null;
        }
    }
}

// Initialisation WebSocket
AppState.websocketService = new WebSocketService();
AppState.websocketService.connect();