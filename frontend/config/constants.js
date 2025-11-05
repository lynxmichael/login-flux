// Configuration globale
const CONFIG = {
    API_BASE_URL: 'https://api.fluxio.demo',
    STOCK_SYMBOL: 'SEMC',
    UPDATE_INTERVAL: 2000,
    
    API_ENDPOINTS: {
        AUTH: {
            LOGIN: '/auth/login',
            REGISTER: '/auth/register',
            VERIFY: '/auth/verify',
            PROFILE: '/auth/profile'
        },
        STOCKS: {
            LIST: '/stocks',
            DETAIL: '/stocks',
            SEARCH: '/stocks/search'
        },
        ORDERS: {
            CREATE: '/orders',
            USER_ORDERS: '/orders/user',
            EXECUTE: '/orders/execute'
        },
        PORTFOLIO: {
            GET: '/portfolio',
            UPDATE: '/portfolio'
        }
    }
};

// Exposer globalement
window.CONFIG = CONFIG;