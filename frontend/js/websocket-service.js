class WebSocketService {
  constructor() {
    this.socket = null;
    this.isConnected = false;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 5;
    this.reconnectInterval = 5000;
    
    console.log('🔧 WebSocketService initialisé');
  }

  connect() {
    console.log('🔄 Tentative de connexion WebSocket...');
    
    try {
      // Fermer toute connexion existante
      if (this.socket) {
        this.socket.close();
      }

      // Utiliser wss:// si en production, ws:// en développement
      this.socket = new WebSocket('ws://localhost:3000');
      
      this.socket.onopen = () => {
        console.log('✅ CONNEXION RÉUSSIE au serveur WebSocket');
        this.isConnected = true;
        this.reconnectAttempts = 0;
        this.onConnectionStatusChange(true);
        
        // Demander les données initiales
        this.requestInitialData();
      };

      this.socket.onmessage = (event) => {
        console.log('📨 Message WebSocket reçu:', event.data);
        
        try {
          const data = JSON.parse(event.data);
          console.log('📊 Données parsées:', data);
          this.handleMessage(data);
        } catch (error) {
          console.error('❌ Erreur parsing JSON:', error);
        }
      };

      this.socket.onclose = (event) => {
        console.log('❌ Déconnecté du serveur WebSocket');
        this.isConnected = false;
        this.onConnectionStatusChange(false);
        
        if (event.code !== 1000) {
          this.attemptReconnect();
        }
      };

      this.socket.onerror = (error) => {
        console.error('💥 Erreur WebSocket:', error);
      };

    } catch (error) {
      console.error('💥 Erreur création WebSocket:', error);
      this.attemptReconnect();
    }
  }

  requestInitialData() {
    if (this.isConnected) {
      this.send({
        type: 'subscribe',
        channels: ['prices', 'orders', 'transactions']
      });
    }
  }

  send(message) {
    if (this.isConnected && this.socket) {
      try {
        this.socket.send(JSON.stringify(message));
        console.log('📤 Message envoyé:', message);
        return true;
      } catch (error) {
        console.error('❌ Erreur envoi message:', error);
        return false;
      }
    } else {
      console.warn('⚠️ Impossible d\'envoyer - Non connecté');
      return false;
    }
  }

  handleMessage(data) {
    console.log('🔄 Traitement message:', data);
    
    // Gestion flexible des différents formats
    const eventType = data.event || data.type;
    
    switch (eventType) {
      case 'price-update':
      case 'price_update':
      case 'asset-update':
        console.log('💰 Mise à jour prix détectée');
        this.onPriceUpdate(data.data || data.payload || data);
        break;
      case 'order-update':
      case 'order_update':
        console.log('📊 Mise à jour ordres détectée');
        this.onOrderUpdate(data.data || data.payload || data);
        break;
      case 'transaction-update':
      case 'transaction_update':
        console.log('💸 Mise à jour transactions détectée');
        this.onTransactionUpdate(data.data || data.payload || data);
        break;
      case 'market-update':
      case 'market_update':
        console.log('🌐 Mise à jour marché détectée');
        this.onMarketUpdate(data.data || data.payload || data);
        break;
      case 'connection':
      case 'connect':
        console.log('🔗 Message de connexion:', data.message);
        break;
      case 'error':
        console.error('🚨 Erreur serveur:', data.message);
        break;
      default:
        console.log('❓ Message non reconnu, traitement générique:', data);
        // Traitement générique pour les données de prix
        if (data.price !== undefined || data.symbol) {
          this.onPriceUpdate(data);
        }
    }
  }

  onPriceUpdate(priceData) {
    console.log('🎯 Mise à jour prix reçue:', priceData);
    
    if (!priceData) {
      console.warn('⚠️ Données prix manquantes');
      return;
    }
    
    // Mettre à jour le prix principal
    const priceElement = document.getElementById('current-price');
    if (priceElement) {
      const newPrice = priceData.price || priceData.currentPrice || priceData;
      priceElement.textContent = newPrice;
      console.log('✅ Prix mis à jour:', newPrice);
    }
    
    // Mettre à jour les graphiques si disponibles
    if (window.chartManager && typeof window.chartManager.updatePrice === 'function') {
      window.chartManager.updatePrice(priceData);
    }
    
    // Animer le changement de prix
    this.animatePriceChange(priceElement);
  }

  onOrderUpdate(orderData) {
    console.log('🎯 Mise à jour ordres reçue:', orderData);
    
    // Mettre à jour les tableaux d'ordres
    this.updateOrderTables(orderData);
  }

  onTransactionUpdate(transactionData) {
    console.log('🎯 Mise à jour transactions reçue:', transactionData);
    
    // Mettre à jour l'historique des transactions
    this.updateTransactionHistory(transactionData);
  }

  onMarketUpdate(marketData) {
    console.log('🎯 Mise à jour marché reçue:', marketData);
    
    // Mettre à jour toutes les données de marché
    if (marketData.prices) {
      this.onPriceUpdate(marketData.prices);
    }
    if (marketData.orders) {
      this.onOrderUpdate(marketData.orders);
    }
    if (marketData.transactions) {
      this.onTransactionUpdate(marketData.transactions);
    }
  }

  onConnectionStatusChange(connected) {
    console.log('🔌 Statut connexion:', connected);
    
    // Mettre à jour l'interface utilisateur
    const statusElement = document.getElementById('connection-status') || document.createElement('div');
    if (!document.getElementById('connection-status')) {
      statusElement.id = 'connection-status';
      statusElement.style.cssText = `
        position: fixed;
        top: 10px;
        right: 10px;
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 12px;
        z-index: 1000;
        background: ${connected ? '#4CAF50' : '#f44336'};
        color: white;
      `;
      document.body.appendChild(statusElement);
    }
    
    statusElement.textContent = connected ? '🟢 Connecté' : '🔴 Déconnecté';
    statusElement.style.background = connected ? '#4CAF50' : '#f44336';
  }

  updateOrderTables(orderData) {
    console.log('🔄 Mise à jour tableaux d\'ordres');
    
    // Mettre à jour les vendeurs
    if (orderData.sellers && Array.isArray(orderData.sellers)) {
      this.updateSellersTable(orderData.sellers);
    }
    
    // Mettre à jour les acheteurs
    if (orderData.buyers && Array.isArray(orderData.buyers)) {
      this.updateBuyersTable(orderData.buyers);
    }
    
    // Recalculer l'écart de prix
    this.updatePriceGap();
  }

  updateSellersTable(sellers) {
    const table = document.getElementById('sellers-table');
    if (!table) return;
    
    let html = '<tr><th>Prix</th><th>Volume</th><th>Total</th></tr>';
    let runningTotal = 0;
    
    sellers.forEach(seller => {
      runningTotal += seller.volume || seller.quantity || 0;
      html += `
        <tr class="sell">
          <td>${seller.price}</td>
          <td>${(seller.volume || seller.quantity || 0).toLocaleString()}</td>
          <td>${runningTotal.toLocaleString()}</td>
        </tr>
      `;
    });
    
    table.innerHTML = html;
    console.log('✅ Table vendeurs mis à jour');
  }

  updateBuyersTable(buyers) {
    const table = document.getElementById('buyers-table');
    if (!table) return;
    
    let html = '<tr><th>Prix</th><th>Volume</th><th>Total</th></tr>';
    let runningTotal = 0;
    
    buyers.forEach(buyer => {
      runningTotal += buyer.volume || buyer.quantity || 0;
      html += `
        <tr class="buy">
          <td>${buyer.price}</td>
          <td>${(buyer.volume || buyer.quantity || 0).toLocaleString()}</td>
          <td>${runningTotal.toLocaleString()}</td>
        </tr>
      `;
    });
    
    table.innerHTML = html;
    console.log('✅ Table acheteurs mis à jour');
  }

  updateTransactionHistory(transactions) {
    const table = document.getElementById('transaction-history');
    if (!table) return;
    
    let html = '<tr><th>Prix</th><th>Heure</th><th>Qté</th></tr>';
    
    // Prendre les 5 dernières transactions
    const recentTransactions = transactions.slice(0, 5);
    
    recentTransactions.forEach(transaction => {
      const time = transaction.time || new Date().toLocaleTimeString();
      html += `
        <tr>
          <td>${transaction.price}</td>
          <td>${time}</td>
          <td>${transaction.quantity || transaction.volume || 0}</td>
        </tr>
      `;
    });
    
    table.innerHTML = html;
    console.log('✅ Historique transactions mis à jour');
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

  animatePriceChange(priceElement) {
    if (priceElement) {
      priceElement.style.transition = 'all 0.3s ease';
      priceElement.style.color = '#4CAF50';
      priceElement.style.transform = 'scale(1.1)';
      
      setTimeout(() => {
        priceElement.style.color = '';
        priceElement.style.transform = 'scale(1)';
      }, 300);
    }
  }

  attemptReconnect() {
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      const delay = this.reconnectInterval * Math.pow(1.5, this.reconnectAttempts - 1);
      
      console.log(`🔄 Tentative de reconnexion ${this.reconnectAttempts}/${this.maxReconnectAttempts} dans ${delay}ms`);
      
      setTimeout(() => {
        this.connect();
      }, delay);
    } else {
      console.error('❌ Échec de reconnexion après plusieurs tentatives');
    }
  }

  disconnect() {
    console.log('🔌 Déconnexion manuelle WebSocket');
    this.reconnectAttempts = this.maxReconnectAttempts;
    if (this.socket) {
      this.socket.close(1000, 'Déconnexion utilisateur');
    }
  }
}