import React, { useState, useEffect, useRef } from 'react';
import { TrendingUp, TrendingDown, User, LogIn, UserPlus, X, Menu, Search, Play, Pause } from 'lucide-react';

// Configuration
const CONFIG = {
  UPDATE_INTERVAL: 2000,
  DEFAULT_TIMEFRAME: '1h'
};

// Données des graphiques
const TIMEFRAME_DATA = {
  '1h': {
    data: [710, 740, 761, 720, 730, 728, 725],
    labels: ["09:00", "10:00", "11:00", "12:00", "13:00", "14:00", "15:00"],
    dynamic: true
  },
  '4h': {
    data: [700, 710, 750, 740, 730, 735, 728, 725, 720, 722, 725, 728],
    labels: ["06:00", "08:00", "10:00", "12:00", "14:00", "16:00", "18:00", "20:00", "22:00", "00:00", "02:00", "04:00"],
    dynamic: false
  },
  '1d': {
    data: [710, 715, 720, 725, 730, 735, 740, 745, 750, 755, 760, 761, 758, 755, 752, 748, 745, 742, 738, 735, 732, 728, 725, 722],
    labels: ["00:00", "01:00", "02:00", "03:00", "04:00", "05:00", "06:00", "07:00", "08:00", "09:00", "10:00", "11:00", "12:00", "13:00", "14:00", "15:00", "16:00", "17:00", "18:00", "19:00", "20:00", "21:00", "22:00", "23:00"],
    dynamic: false
  },
  '1w': {
    data: [710, 720, 715, 730, 725, 740, 735],
    labels: ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"],
    dynamic: false
  },
  '1m': {
    data: [700, 710, 720, 730, 740, 750, 760, 761, 758, 755, 750, 745, 740, 735, 730, 728, 725, 722, 720, 718, 715, 712, 710, 708, 705, 702, 700, 705],
    labels: ["J1", "J2", "J3", "J4", "J5", "J6", "J7", "J8", "J9", "J10", "J11", "J12", "J13", "J14", "J15", "J16", "J17", "J18", "J19", "J20", "J21", "J22", "J23", "J24", "J25", "J26", "J27", "J28"],
    dynamic: false
  }
};

// Actions populaires
const STOCKS = [
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
];

// Composant principal
export default function FluxIOApp() {
  const [currentPrice, setCurrentPrice] = useState(720);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [currentUser, setCurrentUser] = useState(null);
  const [showAuthModal, setShowAuthModal] = useState(false);
  const [showProfileModal, setShowProfileModal] = useState(false);
  const [showOrderModal, setShowOrderModal] = useState(false);
  const [authTab, setAuthTab] = useState('login');
  const [orderType, setOrderType] = useState('buy');
  const [timeframe, setTimeframe] = useState('1h');
  const [isPlaying, setIsPlaying] = useState(true);
  const [chartData, setChartData] = useState(TIMEFRAME_DATA['1h'].data);
  const [searchQuery, setSearchQuery] = useState('');
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [currentSlide, setCurrentSlide] = useState(0);

  const canvasRef = useRef(null);

  // Données du carnet d'ordres
  const sellers = [
    { price: 746, volume: 2936, total: 2936 },
    { price: 745, volume: 1287, total: 4223 },
    { price: 744, volume: 1181, total: 5404 },
    { price: 743, volume: 1016, total: 6420 },
    { price: 742, volume: 2936, total: 2936 },
    { price: 741, volume: 1287, total: 4223 },
    { price: 740, volume: 1287, total: 4223 }
  ];

  const buyers = [
    { price: 728, volume: 1874, total: 1874 },
    { price: 727, volume: 2108, total: 3982 },
    { price: 726, volume: 1455, total: 5437 },
    { price: 725, volume: 1987, total: 7424 },
    { price: 724, volume: 1874, total: 1874 },
    { price: 723, volume: 2108, total: 3982 },
    { price: 722, volume: 2108, total: 3982 }
  ];

  // Slider publicitaire
  const slides = ['pub (2).jpg', 'pub.jpg', 'pub 4.png', 'pub 5.png'];

  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentSlide(prev => (prev + 1) % slides.length);
    }, 4000);
    return () => clearInterval(interval);
  }, []);

  // Initialiser le graphique
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const drawChart = () => {
      const width = canvas.width;
      const height = canvas.height;
      const padding = 40;
      const chartWidth = width - padding * 2;
      const chartHeight = height - padding * 2;

      ctx.clearRect(0, 0, width, height);

      const maxVal = Math.max(...chartData);
      const minVal = Math.min(...chartData);
      const range = maxVal - minVal;

      // Grille
      ctx.strokeStyle = '#e5e7eb';
      ctx.lineWidth = 1;
      for (let i = 0; i <= 5; i++) {
        const y = padding + (chartHeight / 5) * i;
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(width - padding, y);
        ctx.stroke();
      }

      // Ligne du graphique
      ctx.strokeStyle = '#ff6600';
      ctx.lineWidth = 2;
      ctx.beginPath();

      chartData.forEach((value, index) => {
        const x = padding + (chartWidth / (chartData.length - 1)) * index;
        const y = padding + chartHeight - ((value - minVal) / range) * chartHeight;
        
        if (index === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
      });
      ctx.stroke();

      // Points
      ctx.fillStyle = '#ff6600';
      chartData.forEach((value, index) => {
        const x = padding + (chartWidth / (chartData.length - 1)) * index;
        const y = padding + chartHeight - ((value - minVal) / range) * chartHeight;
        ctx.beginPath();
        ctx.arc(x, y, 3, 0, Math.PI * 2);
        ctx.fill();
      });

      // Labels Y
      ctx.fillStyle = '#6b7280';
      ctx.font = '12px sans-serif';
      for (let i = 0; i <= 5; i++) {
        const value = maxVal - (range / 5) * i;
        const y = padding + (chartHeight / 5) * i;
        ctx.fillText(Math.round(value), 5, y + 4);
      }
    };

    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
    drawChart();

    const handleResize = () => {
      canvas.width = canvas.offsetWidth;
      canvas.height = canvas.offsetHeight;
      drawChart();
    };

    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, [chartData]);

  // Mise à jour automatique du graphique
  useEffect(() => {
    if (!isPlaying || !TIMEFRAME_DATA[timeframe].dynamic) return;

    const interval = setInterval(() => {
      setChartData(prev => {
        const newData = [...prev];
        const lastValue = newData[newData.length - 1];
        const change = (Math.random() - 0.5) * 10;
        const newValue = Math.max(600, Math.min(800, lastValue + change));
        newData.push(newValue);
        newData.shift();
        setCurrentPrice(Math.round(newValue));
        return newData;
      });
    }, CONFIG.UPDATE_INTERVAL);

    return () => clearInterval(interval);
  }, [isPlaying, timeframe]);

  // Gestionnaires
  const handleLogin = (e) => {
    e.preventDefault();
    const email = e.target.email.value;
    const password = e.target.password.value;

    if (email === 'demo@fluxio.com' && password === 'demo123') {
      setCurrentUser({
        name: 'Utilisateur Demo',
        email: 'demo@fluxio.com',
        wallet: 1250000,
        joinDate: 'Jan 2025',
        trades: 42,
        profit: 12.5
      });
      setIsAuthenticated(true);
      setShowAuthModal(false);
      alert('✅ Connexion réussie');
    } else {
      alert('❌ Identifiants incorrects');
    }
  };

  const handleRegister = (e) => {
    e.preventDefault();
    const password = e.target.password.value;
    const confirmPassword = e.target.confirmPassword.value;

    if (password !== confirmPassword) {
      alert('❌ Les mots de passe ne correspondent pas');
      return;
    }

    alert('✅ Inscription réussie ! Vous pouvez maintenant vous connecter.');
    setAuthTab('login');
  };

  const handleLogout = () => {
    if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
      setIsAuthenticated(false);
      setCurrentUser(null);
      setShowProfileModal(false);
      alert('✅ Déconnexion réussie');
    }
  };

  const handleOrderSubmit = (e) => {
    e.preventDefault();
    if (!isAuthenticated) {
      setShowOrderModal(false);
      setShowAuthModal(true);
      return;
    }

    const quantity = e.target.quantity.value;
    const price = e.target.price.value;
    alert(`✅ Ordre d'${orderType === 'buy' ? 'achat' : 'vente'} confirmé pour ${quantity} actions SEMC au prix de ${price} FCFA`);
    setShowOrderModal(false);
    e.target.reset();
  };

  const openOrderModal = (type) => {
    if (!isAuthenticated) {
      setShowAuthModal(true);
      return;
    }
    setOrderType(type);
    setShowOrderModal(true);
  };

  const changeTimeframe = (tf) => {
    setTimeframe(tf);
    setChartData(TIMEFRAME_DATA[tf].data);
  };

  const filteredStocks = STOCKS.filter(stock =>
    stock.symbol.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const priceGap = sellers[0]?.price - buyers[0]?.price || 0;

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      {/* Header */}
      <header className="bg-white shadow-sm sticky top-0 z-40">
        <nav className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <div className="flex items-center gap-4">
              <button
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                className="lg:hidden p-2 rounded-md hover:bg-gray-100"
              >
                <Menu className="w-6 h-6" />
              </button>
              <h1 className="text-lg sm:text-xl font-bold text-gray-900">
                SEMC - <span className="text-orange-600">{currentPrice}</span> FCFA
              </h1>
            </div>
            
            <div className="flex items-center gap-2 sm:gap-3">
              {!isAuthenticated ? (
                <>
                  <button
                    onClick={() => { setAuthTab('register'); setShowAuthModal(true); }}
                    className="hidden sm:flex items-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors text-sm"
                  >
                    <UserPlus className="w-4 h-4" />
                    S'inscrire
                  </button>
                  <button
                    onClick={() => { setAuthTab('login'); setShowAuthModal(true); }}
                    className="flex items-center gap-2 px-3 sm:px-4 py-2 border border-orange-600 text-orange-600 rounded-md hover:bg-orange-50 transition-colors text-sm"
                  >
                    <LogIn className="w-4 h-4" />
                    <span className="hidden sm:inline">Connexion</span>
                  </button>
                </>
              ) : (
                <button
                  onClick={() => setShowProfileModal(true)}
                  className="flex items-center gap-2 px-3 sm:px-4 py-2 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors text-sm"
                >
                  <User className="w-4 h-4" />
                  <span className="hidden sm:inline">{currentUser.name}</span>
                </button>
              )}
            </div>
          </div>
        </nav>
      </header>

      {/* Contenu principal */}
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
          {/* Section gauche - Carnet d'ordres */}
          <div className={`lg:col-span-3 space-y-4 ${mobileMenuOpen ? 'block' : 'hidden lg:block'}`}>
            <div className="bg-white rounded-lg shadow p-4">
              <h3 className="font-bold text-gray-900 mb-3">Vendeurs</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-xs sm:text-sm">
                  <thead>
                    <tr className="border-b">
                      <th className="text-left py-2">Prix</th>
                      <th className="text-left py-2">Volume</th>
                      <th className="text-left py-2">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sellers.map((seller, idx) => (
                      <tr key={idx} className="border-b text-red-600">
                        <td className="py-1.5">{seller.price}</td>
                        <td className="py-1.5">{seller.volume.toLocaleString()}</td>
                        <td className="py-1.5">{seller.total.toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-4">
              <h3 className="font-bold text-gray-900 mb-3">Acheteurs</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-xs sm:text-sm">
                  <thead>
                    <tr className="border-b">
                      <th className="text-left py-2">Prix</th>
                      <th className="text-left py-2">Volume</th>
                      <th className="text-left py-2">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {buyers.map((buyer, idx) => (
                      <tr key={idx} className="border-b text-green-600">
                        <td className="py-1.5">{buyer.price}</td>
                        <td className="py-1.5">{buyer.volume.toLocaleString()}</td>
                        <td className="py-1.5">{buyer.total.toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="bg-gray-100 rounded-lg p-3 text-center text-sm">
              Écart: <span className="font-bold">{priceGap}</span> FCFA
            </div>
          </div>

          {/* Section centrale - Graphique */}
          <div className="lg:col-span-6 space-y-4">
            <div className="bg-white rounded-lg shadow p-4">
              <h3 className="font-bold text-gray-900 mb-3 text-sm sm:text-base">
                {timeframe.toUpperCase()} - SEMC
              </h3>

              <div className="flex flex-wrap gap-2 mb-4">
                {Object.keys(TIMEFRAME_DATA).map(tf => (
                  <button
                    key={tf}
                    onClick={() => changeTimeframe(tf)}
                    className={`px-3 py-1.5 rounded text-xs sm:text-sm transition-colors ${
                      timeframe === tf
                        ? 'bg-orange-600 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    {tf.toUpperCase()}
                  </button>
                ))}
                <button
                  onClick={() => setIsPlaying(!isPlaying)}
                  className="ml-auto px-3 py-1.5 bg-gray-100 rounded hover:bg-gray-200 transition-colors text-xs sm:text-sm flex items-center gap-1"
                >
                  {isPlaying ? <Pause className="w-4 h-4" /> : <Play className="w-4 h-4" />}
                  {isPlaying ? 'Pause' : 'Lecture'}
                </button>
              </div>

              <div className="relative h-64 sm:h-80 md:h-96">
                <canvas ref={canvasRef} className="w-full h-full" />
              </div>

              <div className="flex gap-2 sm:gap-4 mt-4">
                <button
                  onClick={() => openOrderModal('buy')}
                  className="flex-1 py-2.5 sm:py-3 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors font-medium text-sm sm:text-base"
                >
                  Acheter
                </button>
                <button
                  onClick={() => openOrderModal('sell')}
                  className="flex-1 py-2.5 sm:py-3 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors font-medium text-sm sm:text-base"
                >
                  Vendre
                </button>
              </div>
            </div>

            {/* Slider publicitaire */}
            <div className="bg-white rounded-lg shadow p-4">
              <div className="relative h-32 sm:h-40 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg overflow-hidden">
                <div className="absolute inset-0 flex items-center justify-center text-white text-lg sm:text-xl font-bold">
                  Publicité {currentSlide + 1}
                </div>
              </div>
              <div className="flex justify-center gap-2 mt-3">
                {slides.map((_, idx) => (
                  <button
                    key={idx}
                    onClick={() => setCurrentSlide(idx)}
                    className={`w-2 h-2 rounded-full transition-colors ${
                      currentSlide === idx ? 'bg-orange-600' : 'bg-gray-300'
                    }`}
                  />
                ))}
              </div>
            </div>
          </div>

          {/* Section droite - Actions populaires */}
          <div className="lg:col-span-3 space-y-4">
            <div className="bg-white rounded-lg shadow p-4">
              <div className="relative mb-3">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                  type="text"
                  placeholder="Rechercher..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                />
              </div>

              <h3 className="font-bold text-gray-900 mb-3">Actions populaires</h3>
              <div className="max-h-96 overflow-y-auto">
                <table className="w-full text-xs sm:text-sm">
                  <thead className="sticky top-0 bg-white">
                    <tr className="border-b">
                      <th className="text-left py-2">Symbole</th>
                      <th className="text-right py-2">Prix</th>
                      <th className="text-right py-2">Var.</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredStocks.map((stock) => (
                      <tr key={stock.symbol} className="border-b hover:bg-gray-50">
                        <td className="py-2 font-medium">{stock.symbol}</td>
                        <td className="text-right py-2">{stock.price.toLocaleString()}</td>
                        <td className="text-right py-2">
                          <span
                            className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs ${
                              stock.change >= 0
                                ? 'bg-green-100 text-green-700'
                                : 'bg-red-100 text-red-700'
                            }`}
                          >
                            {stock.change >= 0 ? (
                              <TrendingUp className="w-3 h-3" />
                            ) : (
                              <TrendingDown className="w-3 h-3" />
                            )}
                            {stock.change > 0 ? '+' : ''}{stock.change}%
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow p-4">
              <h3 className="font-bold text-gray-900 mb-3 text-sm sm:text-base">Dernières transactions</h3>
              <table className="w-full text-xs sm:text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2">Prix</th>
                    <th className="text-left py-2">Heure</th>
                    <th className="text-right py-2">Qté</th>
                  </tr>
                </thead>
                <tbody>
                  <tr className="border-b"><td className="py-1.5">728</td><td className="py-1.5">12:51:41</td><td className="text-right py-1.5">4</td></tr>
                  <tr className="border-b"><td className="py-1.5">727</td><td className="py-1.5">12:52:46</td><td className="text-right py-1.5">6</td></tr>
                  <tr className="border-b"><td className="py-1.5">725</td><td className="py-1.5">12:53:10</td><td className="text-right py-1.5">11</td></tr>
                  <tr className="border-b"><td className="py-1.5">726</td><td className="py-1.5">12:54:59</td><td className="text-right py-1.5">54</td></tr>
                  <tr className="border-b"><td className="py-1.5">728</td><td className="py-1.5">12:56:32</td><td className="text-right py-1.5">2</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="bg-white border-t mt-auto py-4">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-600">
          FluxIO © 2025 - Interface de démonstration
        </div>
      </footer>

      {/* Modal d'authentification */}
      {showAuthModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg max-w-md w-full p-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-xl font-bold">
                {authTab === 'login' ? 'Connexion' : 'Inscription'} à FluxIO
              </h3>
              <button onClick={() => setShowAuthModal(false)} className="p-1 hover:bg-gray-100 rounded">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="flex gap-2 mb-4">
              <button
                onClick={() => setAuthTab('login')}
                className={`flex-1 py-2 rounded ${authTab === 'login' ? 'bg-orange-600 text-white' : 'bg-gray-100'}`}
              >
                Connexion
              </button>
              <button
                onClick={() => setAuthTab('register')}
                className={`flex-1 py-2 rounded ${authTab === 'register' ? 'bg-orange-600 text-white' : 'bg-gray-100'}`}
              >
                Inscription
              </button>
            </div>

            {authTab === 'login' ? (
              <form onSubmit={handleLogin} className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Email</label>
                  <input
                    type="email"
                    name="email"
                    required
                    defaultValue="demo@fluxio.com"
                    className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Mot de passe</label>
                  <input
                    type="password"
                    name="password"
                    required
                    defaultValue="demo123"
                    className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                  />
                </div>
                <div className="text-xs text-gray-500">
                  Demo: demo@fluxio.com / demo123
                </div>
                <button
                  type="submit"
                  className="w-full py-2.5 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors font-medium"
                >
                  Se connecter
                </button>
              </form>
            ) : (
              <form onSubmit={handleRegister} className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Nom complet</label>
                  <input
                    type="text"
                    name="name"
                    required
                    className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Email</label>
                  <input
                    type="email"
                    name="email"
                    required
                    className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Mot de passe</label>
                  <input
                    type="password"
                    name="password"
                    required
                    className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Confirmer le mot de passe</label>
                  <input
                    type="password"
                    name="confirmPassword"
                    required
                    className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                  />
                </div>
                <button
                  type="submit"
                  className="w-full py-2.5 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors font-medium"
                >
                  S'inscrire
                </button>
              </form>
            )}
          </div>
        </div>
      )}

      {/* Modal de profil */}
      {showProfileModal && currentUser && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg max-w-md w-full p-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-xl font-bold">Mon Profil</h3>
              <button onClick={() => setShowProfileModal(false)} className="p-1 hover:bg-gray-100 rounded">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="text-center mb-6">
              <div className="w-20 h-20 bg-orange-600 text-white rounded-full flex items-center justify-center text-3xl font-bold mx-auto mb-3">
                {currentUser.name.charAt(0)}
              </div>
              <h4 className="font-bold text-lg">{currentUser.name}</h4>
              <p className="text-gray-600 text-sm">{currentUser.email}</p>
            </div>

            <div className="space-y-3 mb-6">
              <div className="flex justify-between p-3 bg-gray-50 rounded">
                <span className="text-gray-600">Membre depuis</span>
                <span className="font-medium">{currentUser.joinDate}</span>
              </div>
              <div className="flex justify-between p-3 bg-gray-50 rounded">
                <span className="text-gray-600">Portefeuille</span>
                <span className="font-medium">{currentUser.wallet.toLocaleString()} FCFA</span>
              </div>
              <div className="flex justify-between p-3 bg-gray-50 rounded">
                <span className="text-gray-600">Transactions</span>
                <span className="font-medium">{currentUser.trades}</span>
              </div>
              <div className="flex justify-between p-3 bg-gray-50 rounded">
                <span className="text-gray-600">Performance</span>
                <span className="font-medium text-green-600">+{currentUser.profit}%</span>
              </div>
            </div>

            <button
              onClick={handleLogout}
              className="w-full py-2.5 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors font-medium"
            >
              Déconnexion
            </button>
          </div>
        </div>
      )}

      {/* Modal d'ordre */}
      {showOrderModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg max-w-md w-full p-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-xl font-bold">
                Ordre d'{orderType === 'buy' ? 'achat' : 'vente'}
              </h3>
              <button onClick={() => setShowOrderModal(false)} className="p-1 hover:bg-gray-100 rounded">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleOrderSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1">Type d'ordre</label>
                <select
                  value={orderType}
                  onChange={(e) => setOrderType(e.target.value)}
                  className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                >
                  <option value="buy">Achat</option>
                  <option value="sell">Vente</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Nom de l'action</label>
                <input
                  type="text"
                  value="SEMC"
                  readOnly
                  className="w-full px-3 py-2 border rounded-md bg-gray-50"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Quantité</label>
                <input
                  type="number"
                  name="quantity"
                  min="1"
                  required
                  className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Prix (FCFA)</label>
                <input
                  type="number"
                  name="price"
                  defaultValue={currentPrice}
                  step="0.01"
                  required
                  className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Date de validité</label>
                <input
                  type="date"
                  name="validityDate"
                  required
                  defaultValue={new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]}
                  className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                />
              </div>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => setShowOrderModal(false)}
                  className="flex-1 py-2.5 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  className={`flex-1 py-2.5 text-white rounded-md transition-colors font-medium ${
                    orderType === 'buy'
                      ? 'bg-green-600 hover:bg-green-700'
                      : 'bg-red-600 hover:bg-red-700'
                  }`}
                >
                  Confirmer
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}