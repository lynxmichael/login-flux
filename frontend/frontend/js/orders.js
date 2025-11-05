// Gestion des ordres
class OrderManager {
  constructor() {
    this.currentOrderType = 'buy';
    this.init();
  }

  init() {
    console.log('Initialisation OrderManager');
    this.bindEvents();
    this.initOrderModal();
  }

  bindEvents() {
    console.log('🔗 Attachement des événements aux boutons (OrderManager)');
    
    // ATTENDRE que WalletManager soit initialisé
    setTimeout(() => {
      // Bouton Acheter
      const buyBtn = document.getElementById('btn-buy');
      if (buyBtn) {
        // Supprimer les anciens listeners
        const newBuyBtn = buyBtn.cloneNode(true);
        buyBtn.parentNode.replaceChild(newBuyBtn, buyBtn);
        
        newBuyBtn.addEventListener('click', (e) => {
          e.preventDefault();
          console.log('🟢 Bouton Acheter cliqué');
          this.openOrderModal('buy');
        });
        console.log('✅ Bouton Acheter configuré');
      } else {
        console.error('❌ Bouton Acheter non trouvé');
      }

      // Bouton Vendre
      const sellBtn = document.getElementById('btn-sell');
      if (sellBtn) {
        // Supprimer les anciens listeners
        const newSellBtn = sellBtn.cloneNode(true);
        sellBtn.parentNode.replaceChild(newSellBtn, sellBtn);
        
        newSellBtn.addEventListener('click', (e) => {
          e.preventDefault();
          console.log('🔴 Bouton Vendre cliqué');
          this.openOrderModal('sell');
        });
        console.log('✅ Bouton Vendre configuré');
      } else {
        console.error('❌ Bouton Vendre non trouvé');
      }
    }, 200); // Attendre 200ms que WalletManager soit prêt
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

    this.initPriceObserver();
  }

  initPriceObserver() {
    const priceInput = document.getElementById('price');
    const currentPriceElement = document.getElementById('current-price');
    
    if (priceInput && currentPriceElement) {
      // Initialiser avec le prix actuel
      priceInput.value = currentPriceElement.textContent;
      
      const observer = new MutationObserver(() => {
        priceInput.value = currentPriceElement.textContent;
      });
      
      observer.observe(currentPriceElement, {
        childList: true,
        characterData: true,
        subtree: true
      });
    }
  }

  openOrderModal(type) {
    console.log(`🎯 OrderManager.openOrderModal('${type}') appelé`);
    
    // Vérifier si l'utilisateur est connecté via WalletManager
    if (!this.isUserLoggedIn()) {
      console.error('❌ Utilisateur non connecté');
      alert('Veuillez vous connecter pour effectuer cette action');
      return;
    }

    console.log('✅ Utilisateur connecté, ouverture du modal...');
    
    // Déléguer à WalletManager qui gère la configuration complète
    if (window.walletManager && typeof window.walletManager.openOrderModal === 'function') {
      window.walletManager.openOrderModal(type);
    } else {
      // Fallback: ouverture basique
      console.warn('⚠️ WalletManager non disponible, ouverture basique');
      const modal = document.getElementById('orderModal');
      if (modal) {
        this.currentOrderType = type;
        this.updateOrderModal(type);
        modal.style.display = 'flex';
        console.log('✅ Modal ouvert (mode basique)');
      } else {
        console.error('❌ Modal orderModal non trouvé');
      }
    }
  }

  isUserLoggedIn() {
    // Vérifier via WalletManager en priorité
    if (window.walletManager && typeof window.walletManager.isUserReallyLoggedIn === 'function') {
      return window.walletManager.isUserReallyLoggedIn();
    }
    
    // Vérification de secours
    const hasLocalStorage = !!localStorage.getItem('user_data');
    const hasPhpData = !!window.PHP_USER_DATA;
    
    console.log('🔍 Vérification connexion (OrderManager):', {
      hasLocalStorage,
      hasPhpData,
      walletManagerAvailable: !!window.walletManager
    });
    
    return hasLocalStorage || hasPhpData;
  }

  updateOrderModal(type) {
    const modalTitle = document.getElementById('modal-title');
    const orderTypeSelect = document.getElementById('orderType');
    const priceInput = document.getElementById('price');
    const confirmBtn = document.getElementById('confirmOrder');
    const stockInput = document.getElementById('stockName');
    
    if (!modalTitle || !orderTypeSelect || !priceInput || !confirmBtn) {
      console.error('Éléments du modal non trouvés');
      return;
    }

    // Titre et type
    if (type === 'buy') {
      modalTitle.textContent = '🟢 Acheter des Actions';
      confirmBtn.textContent = 'Confirmer l\'achat';
      confirmBtn.className = 'order-modal-btn order-btn-confirm-buy';
    } else {
      modalTitle.textContent = '🔴 Vendre des Actions';
      confirmBtn.textContent = 'Confirmer la vente';
      confirmBtn.className = 'order-modal-btn order-btn-confirm-sell';
    }
    
    orderTypeSelect.value = type;
    
    // Stock
    if (stockInput) {
      stockInput.value = 'SEMC';
    }
    
    // Mettre à jour le prix depuis l'élément current-price
    const currentPriceElement = document.getElementById('current-price');
    if (currentPriceElement) {
      priceInput.value = currentPriceElement.textContent;
    }
    
    // Date de validité (7 jours)
    const validityDate = new Date();
    validityDate.setDate(validityDate.getDate() + 7);
    const validityDateInput = document.getElementById('validityDate');
    if (validityDateInput) {
      validityDateInput.value = validityDate.toISOString().split('T')[0];
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
  }

  async handleOrderSubmit(e) {
    e.preventDefault();
    console.log('📝 Soumission du formulaire d\'ordre');
    
    // Vérifier la connexion
    if (!this.isUserLoggedIn()) {
      alert('Veuillez vous connecter pour passer un ordre');
      return;
    }

    // Récupérer les données du formulaire
    const quantity = parseInt(document.getElementById('quantity').value);
    const price = parseFloat(document.getElementById('price').value);
    const validityDate = document.getElementById('validityDate').value;
    const orderType = document.getElementById('orderType').value;

    // Validation
    if (!quantity || quantity <= 0) {
      alert('Veuillez saisir une quantité valide');
      return;
    }

    if (!price || price <= 0) {
      alert('Veuillez saisir un prix valide');
      return;
    }

    const formData = {
      type: orderType,
      quantity: quantity,
      price: price,
      validityDate: validityDate,
      stockSymbol: 'SEMC'
    };

    try {
      console.log('📤 Envoi de l\'ordre:', formData);
      
      // Simuler l'envoi d'ordre (à remplacer par votre API)
      await this.simulateOrder(formData);
      
      const total = quantity * price;
      const action = orderType === 'buy' ? 'achat' : 'vente';
      
      alert(`✅ Ordre d'${action} confirmé!\n\n` +
            `Quantité: ${quantity} actions SEMC\n` +
            `Prix: ${price} FCFA\n` +
            `Total: ${new Intl.NumberFormat('fr-FR').format(total)} FCFA`);
      
      this.closeOrderModal();
      
      // Rafraîchir les données si WalletManager disponible
      if (window.walletManager && typeof window.walletManager.loadWalletData === 'function') {
        window.walletManager.loadWalletData();
      }
      
    } catch (error) {
      console.error('❌ Erreur lors de la soumission:', error);
      alert('❌ Erreur: ' + (error.message || 'Impossible de passer l\'ordre'));
    }
  }

  async simulateOrder(orderData) {
    // Simulation d'envoi d'ordre
    return new Promise((resolve) => {
      setTimeout(() => {
        console.log('✅ Ordre simulé avec succès:', orderData);
        resolve({ success: true, orderId: 'DEMO_' + Date.now() });
      }, 1000);
    });
  }
}

// INITIALISATION
document.addEventListener('DOMContentLoaded', function() {
  console.log('📄 DOM chargé - Initialisation OrderManager...');
  
  // Attendre un peu que WalletManager soit prêt
  setTimeout(() => {
    window.orderManager = new OrderManager();
    console.log('✅ OrderManager initialisé');
  }, 300);
});