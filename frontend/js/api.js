// Service API pour communiquer avec le backend
class ApiService {
  constructor() {
    this.baseUrl = CONFIG.API_BASE_URL;
    this.token = localStorage.getItem('authToken');
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const config = {
      headers: {
        'Content-Type': 'application/json',
        ...(this.token && { 'Authorization': `Bearer ${this.token}` }),
        ...options.headers,
      },
      ...options,
    };

    if (config.body && typeof config.body === 'object') {
      config.body = JSON.stringify(config.body);
    }

    try {
      const response = await fetch(url, config);
      const data = await response.json();
      
      if (!response.ok) {
        throw new Error(data.message || 'Erreur API');
      }
      
      return data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  // Authentification
  async login(credentials) {
    return this.request(CONFIG.API_ENDPOINTS.AUTH.LOGIN, {
      method: 'POST',
      body: credentials
    });
  }

  async register(userData) {
    return this.request(CONFIG.API_ENDPOINTS.AUTH.REGISTER, {
      method: 'POST',
      body: userData
    });
  }

  async verifyToken() {
    return this.request(CONFIG.API_ENDPOINTS.AUTH.VERIFY);
  }

  async getProfile() {
    return this.request(CONFIG.API_ENDPOINTS.AUTH.PROFILE);
  }

  // Stocks
  async getStocks() {
    return this.request(CONFIG.API_ENDPOINTS.STOCKS.LIST);
  }

  async getStock(symbol) {
    return this.request(`${CONFIG.API_ENDPOINTS.STOCKS.DETAIL}/${symbol}`);
  }

  async searchStocks(query) {
    return this.request(`${CONFIG.API_ENDPOINTS.STOCKS.SEARCH}?q=${encodeURIComponent(query)}`);
  }

  // Ordres
  async createOrder(orderData) {
    return this.request(CONFIG.API_ENDPOINTS.ORDERS.CREATE, {
      method: 'POST',
      body: orderData
    });
  }

  async getUserOrders() {
    return this.request(CONFIG.API_ENDPOINTS.ORDERS.USER_ORDERS);
  }

  async executeOrder(orderId) {
    return this.request(`${CONFIG.API_ENDPOINTS.ORDERS.EXECUTE}/${orderId}`, {
      method: 'POST'
    });
  }

  // Portefeuille
  async getPortfolio() {
    return this.request(CONFIG.API_ENDPOINTS.PORTFOLIO.GET);
  }

  async updatePortfolio(portfolioData) {
    return this.request(CONFIG.API_ENDPOINTS.PORTFOLIO.UPDATE, {
      method: 'PUT',
      body: portfolioData
    });
  }

  setAuthToken(token) {
    this.token = token;
    if (token) {
      localStorage.setItem('authToken', token);
    } else {
      localStorage.removeItem('authToken');
    }
  }

  // Méthodes utilitaires pour les démos (à retirer en production)
  async getDemoStocks() {
    // Simulation de données de démo
    return {
      stocks: [
        { symbol: 'ABJC', price: 1600, change: 0.06 },
        { symbol: 'BICC', price: 6600, change: 1.54 },
        { symbol: 'BNBC', price: 6100, change: -0.23 },
        { symbol: 'BOAB', price: 6110, change: 0.12 },
        { symbol: 'BOABF', price: 5395, change: 0.87 },
        { symbol: 'BOAC', price: 4310, change: -0.79 },
        { symbol: 'BOAN', price: 2550, change: 0.20 },
        { symbol: 'DAS', price: 2420, change: -0.79 },
        { symbol: 'CABC', price: 1095, change: -5.73 },
        { symbol: 'ETIT', price: 3250, change: 1.25 },
        { symbol: 'FTSC', price: 4100, change: -0.50 },
        { symbol: 'NEIC', price: 2800, change: 0.90 }
      ]
    };
  }

  async getDemoMarketData() {
    // Simulation de données de marché
    return {
      currentPrice: 720,
      dailyChange: 1.41,
      dailyHigh: 761,
      dailyLow: 721,
      dailyVolume: 2331,
      sellers: [
        { price: 746, volume: 2936, total: 2936 },
        { price: 745, volume: 1287, total: 4223 },
        { price: 744, volume: 1181, total: 5404 },
        { price: 743, volume: 1016, total: 6420 }
      ],
      buyers: [
        { price: 728, volume: 1874, total: 1874 },
        { price: 727, volume: 2108, total: 3982 },
        { price: 726, volume: 1455, total: 5437 },
        { price: 725, volume: 1987, total: 7424 }
      ]
    };
  }
}

// Instance globale
const apiService = new ApiService();