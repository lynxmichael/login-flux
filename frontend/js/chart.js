// Configuration améliorée du graphique
const CHART_CONFIG = {
  DEFAULT_TIMEFRAME: '1h',
  UPDATE_INTERVAL: 5000, // 5 secondes comme demandé
  HEARTBEAT_INTERVAL: 1000, // Battement de coeur chaque seconde
  ZOOM_SENSITIVITY: 0.1,
  RIGHT_MARGIN: 30, // Marge en pixels à droite pour le point actuel
  AUTO_ZOOM_FACTOR: 0.9 // Facteur de zoom automatique
};

// Données temporelles améliorées avec plus de variations
const TIMEFRAME_DATA = {
  '1h': {
    duration: 60, // 60 minutes
    data: generateRealisticData(60, 700, 800, 15), // Plus de variations
    labels: generateTimeLabels(60, 'minutes'),
    dynamic: true
  },
  '4h': {
    duration: 240, // 240 minutes
    data: generateRealisticData(240, 680, 820, 25),
    labels: generateTimeLabels(240, 'minutes'),
    dynamic: true
  },
  '1d': {
    duration: 1440, // 1440 minutes
    data: generateRealisticData(1440, 650, 850, 50),
    labels: generateTimeLabels(24, 'hours'),
    dynamic: true
  },
  '1w': {
    duration: 10080, // 10080 minutes
    data: generateRealisticData(7, 600, 900, 100),
    labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
    dynamic: false
  },
  '1m': {
    duration: 43200, // 43200 minutes
    data: generateRealisticData(30, 550, 950, 150),
    labels: generateTimeLabels(30, 'days'),
    dynamic: false
  },
  'all': {
    duration: 525600, // 525600 minutes (1 an)
    data: generateRealisticData(12, 500, 1000, 200),
    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
    dynamic: false
  }
};

// Fonctions utilitaires pour générer des données réalistes
function generateRealisticData(count, minPrice, maxPrice, volatility) {
  const data = [];
  let currentPrice = (minPrice + maxPrice) / 2;
  
  for (let i = 0; i < count; i++) {
    // Variation plus réaliste avec tendance
    const change = (Math.random() - 0.4) * volatility;
    currentPrice += change;
    
    // Maintenir dans les limites
    currentPrice = Math.max(minPrice, Math.min(maxPrice, currentPrice));
    
    // Ajouter un peu de bruit
    const noise = (Math.random() - 0.5) * (volatility * 0.1);
    data.push(Math.round((currentPrice + noise) * 100) / 100);
  }
  
  return data;
}

function generateTimeLabels(count, unit) {
  const labels = [];
  const now = new Date();
  
  for (let i = count - 1; i >= 0; i--) {
    const date = new Date(now);
    
    if (unit === 'minutes') {
      date.setMinutes(date.getMinutes() - i);
      labels.push(date.getHours().toString().padStart(2, '0') + ':' + 
                 date.getMinutes().toString().padStart(2, '0'));
    } else if (unit === 'hours') {
      date.setHours(date.getHours() - i);
      labels.push(date.getHours().toString().padStart(2, '0') + 'h');
    } else if (unit === 'days') {
      date.setDate(date.getDate() - i);
      labels.push('J' + (i + 1));
    }
  }
  
  return labels;
}

// Plugin watermark amélioré
const watermarkPlugin = {
  id: 'watermark',
  beforeDraw: (chart) => {
    const ctx = chart.ctx;
    const chartArea = chart.chartArea;
    
    const watermark = new Image();
    watermark.src = 'IMAGES/FLUX.png';
    
    if (watermark.complete) {
      drawWatermark();
    } else {
      watermark.onload = drawWatermark;
    }
    
    function drawWatermark() {
      const imgWidth = watermark.width * 0.15;
      const imgHeight = watermark.height * 0.15;
      const chartWidth = chartArea.right - chartArea.left;
      const chartHeight = chartArea.bottom - chartArea.top;
      
      const x = chartArea.left + chartWidth / 2 - imgWidth / 2;
      const y = chartArea.top + chartHeight / 2 - imgHeight / 2;
      
      ctx.save();
      ctx.globalAlpha = 0.3;
      ctx.filter = 'blur(6px)';
      ctx.drawImage(watermark, x, y, imgWidth, imgHeight);
      ctx.restore();
    }
  }
};

// Plugin pour les lignes de prix extrêmes
const extremeLinesPlugin = {
  id: 'extremeLines',
  afterDatasetsDraw: (chart) => {
    const ctx = chart.ctx;
    const yAxis = chart.scales.y;
    const data = chart.data.datasets[0].data;
    
    if (!data || data.length === 0) return;
    
    const maxPrice = Math.max(...data);
    const minPrice = Math.min(...data);
    const maxY = yAxis.getPixelForValue(maxPrice);
    const minY = yAxis.getPixelForValue(minPrice);
    
    // Ligne du prix maximum (vert)
    ctx.save();
    ctx.strokeStyle = '#009933';
    ctx.lineWidth = 1;
    ctx.setLineDash([3, 2]);
    ctx.beginPath();
    ctx.moveTo(chart.chartArea.left, maxY);
    ctx.lineTo(chart.chartArea.right, maxY);
    ctx.stroke();
    
    // Annotation du prix max
    ctx.fillStyle = '#009933';
    ctx.font = '10px Arial';
    ctx.fillText(`Max: ${maxPrice.toFixed(2)} FCFA`, chart.chartArea.left + 5, maxY - 5);
    
    // Ligne du prix minimum (rouge)
    ctx.strokeStyle = '#cc0000';
    ctx.beginPath();
    ctx.moveTo(chart.chartArea.left, minY);
    ctx.lineTo(chart.chartArea.right, minY);
    ctx.stroke();
    
    // Annotation du prix min
    ctx.fillStyle = '#cc0000';
    ctx.font = '10px Arial';
    ctx.fillText(`Min: ${minPrice.toFixed(2)} FCFA`, chart.chartArea.left + 5, minY - 5);
    
    ctx.restore();
  }
};

// Plugin pour l'effet de battement de coeur
const heartbeatPlugin = {
  id: 'heartbeat',
  afterDatasetsDraw: (chart, args, options) => {
    if (!chart.heartbeat) return;
    
    const ctx = chart.ctx;
    const dataset = chart.data.datasets[0];
    const data = dataset.data;
    
    if (data.length === 0) return;
    
    const currentPrice = data[data.length - 1];
    const xAxis = chart.scales.x;
    const yAxis = chart.scales.y;
    
    const x = xAxis.getPixelForValue(data.length - 1);
    const y = yAxis.getPixelForValue(currentPrice);
    
    // Point principal (battement de coeur)
    ctx.save();
    
    // Effet de pulsation
    const pulseSize = 3 + (Math.sin(Date.now() / 200) * 2);
    
    // Cercle externe (onde)
    ctx.beginPath();
    ctx.arc(x, y, pulseSize + 6, 0, 2 * Math.PI);
    ctx.fillStyle = 'rgba(255, 102, 0, 0.15)';
    ctx.fill();
    
    // Cercle moyen
    ctx.beginPath();
    ctx.arc(x, y, pulseSize + 3, 0, 2 * Math.PI);
    ctx.fillStyle = 'rgba(255, 102, 0, 0.3)';
    ctx.fill();
    
    // Point central
    ctx.beginPath();
    ctx.arc(x, y, pulseSize, 0, 2 * Math.PI);
    ctx.fillStyle = '#ff6600';
    ctx.fill();
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 1;
    ctx.stroke();
    
    ctx.restore();
  }
};

// Gestion du graphique amélioré
class ChartManager {
  constructor() {
    this.chart = null;
    this.isPlaying = true;
    this.updateInterval = null;
    this.heartbeatInterval = null;
    this.currentTimeframe = CHART_CONFIG.DEFAULT_TIMEFRAME;
    this.init();
  }

  init() {
    this.registerPlugins();
    this.initChart();
    this.bindEvents();
    this.startAutoUpdate();
    this.startHeartbeat();
  }

  registerPlugins() {
    Chart.register(watermarkPlugin);
    Chart.register(extremeLinesPlugin);
    Chart.register(heartbeatPlugin);
  }

  initChart() {
    const ctx = document.getElementById('chart').getContext('2d');
    
    this.chart = new Chart(ctx, {
      type: 'line',
      data: this.getChartData(),
      options: this.getChartOptions(),
      plugins: [watermarkPlugin, extremeLinesPlugin, heartbeatPlugin]
    });

    // Activer l'effet de battement
    this.chart.heartbeat = true;
  }

  getChartData() {
    const timeframe = TIMEFRAME_DATA[this.currentTimeframe];
    
    // Créer un dégradé pour l'arrière-plan
    const gradient = this.createGradient();
    
    return {
      labels: timeframe.labels,
      datasets: [{
        label: "Cours SEMC",
        data: timeframe.data,
        borderColor: "#ff6600", // Couleur unique orange
        backgroundColor: gradient,
        fill: true,
        tension: 0.1, // Légère courbure pour un diagramme linéaire
        pointBackgroundColor: "transparent", // Points invisibles
        pointBorderColor: "transparent",
        pointRadius: 0, // Pas de points visibles sur la ligne
        pointHoverRadius: 0,
        borderWidth: 1.5, // Épaisseur réduite
        segment: {
          borderColor: "#ff6600" // Couleur uniforme même si ça monte/descend
        }
      }]
    };
  }

  createGradient() {
    const ctx = this.chart?.ctx || document.getElementById('chart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(255, 102, 0, 0.6)');
    gradient.addColorStop(0.5, 'rgba(255, 102, 0, 0.4)');
    gradient.addColorStop(1, 'rgba(255, 102, 0, 0.02)');
    return gradient;
  }

  getChartOptions() {
    return {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 800,
        easing: 'easeOutQuart'
      },
      layout: {
        padding: {
          right: CHART_CONFIG.RIGHT_MARGIN // MARGIN AJOUTÉE ICI
        }
      },
      plugins: { 
        legend: { 
          display: false 
        },
        tooltip: {
          mode: 'index',
          intersect: false,
          backgroundColor: 'rgba(0,0,0,0.7)',
          titleFont: { 
            size: 10 // Épaisseur réduite
          },
          bodyFont: { 
            size: 10 // Épaisseur réduite
          },
          padding: 8,
          callbacks: {
            label: function(context) {
              return `Prix: ${context.parsed.y} FCFA`;
            }
          }
        }
      },
      scales: {
        x: { 
          display: true,
          grid: { 
            display: true,
            color: 'rgba(0,0,0,0.03)',
            lineWidth: 0.5
          },
          ticks: { 
            font: { 
              size: 9, // Épaisseur réduite
              weight: 'normal'
            },
            maxRotation: 45
          },
          // Configuration pour le zoom automatique et la marge
          afterBuildTicks: (axis) => {
            // Ajuster automatiquement les limites pour créer une marge à droite
            const dataLength = this.chart?.data?.labels?.length || 0;
            if (dataLength > 0) {
              axis.max = dataLength - 1 + 0.5; // Réduit à 0.5 pour éviter les problèmes
            }
          }
        },
        y: { 
          display: true,
          grid: { 
            color: 'rgba(0,0,0,0.03)',
            lineWidth: 0.5
          },
          ticks: {
            font: { 
              size: 9, // Épaisseur réduite
              weight: 'normal'
            },
            callback: (value) => value + ' FCFA'
          },
          // Zoom automatique vertical
          afterDataLimits: (scale) => {
            // Adapter automatiquement l'échelle Y pour éviter le débordement
            const data = this.chart?.data?.datasets[0]?.data || [];
            if (data.length > 0) {
              const minValue = Math.min(...data);
              const maxValue = Math.max(...data);
              const range = maxValue - minValue;
              const margin = range * 0.1; // 10% de marge
              scale.min = minValue - margin;
              scale.max = maxValue + margin;
            }
          }
        }
      },
      interaction: {
        intersect: false,
        mode: 'nearest'
      },
      elements: {
        line: {
          borderWidth: 1.5, // Épaisseur réduite
          tension: 0.1
        }
      }
    };
  }

  updateTimeframe(timeframe) {
    this.currentTimeframe = timeframe;
    
    // Transition en douceur
    this.chart.data.labels = TIMEFRAME_DATA[timeframe].labels;
    this.chart.data.datasets[0].data = TIMEFRAME_DATA[timeframe].data;
    this.chart.data.datasets[0].backgroundColor = this.createGradient();
    
    this.chart.update('none');
    
    // Gérer les mises à jour automatiques
    this.stopAutoUpdate();
    if (TIMEFRAME_DATA[timeframe].dynamic) {
      this.startAutoUpdate();
    }
  }

  startAutoUpdate() {
    if (this.updateInterval) return;
    
    this.updateInterval = setInterval(() => {
      if (this.isPlaying && TIMEFRAME_DATA[this.currentTimeframe].dynamic) {
        this.simulateLiveData();
      }
    }, CHART_CONFIG.UPDATE_INTERVAL);
  }

  stopAutoUpdate() {
    if (this.updateInterval) {
      clearInterval(this.updateInterval);
      this.updateInterval = null;
    }
  }

  startHeartbeat() {
    this.heartbeatInterval = setInterval(() => {
      if (this.chart) {
        this.chart.update('none');
      }
    }, CHART_CONFIG.HEARTBEAT_INTERVAL);
  }

  simulateLiveData() {
    const data = this.chart.data.datasets[0].data;
    const labels = this.chart.data.labels;
    
    if (data.length === 0) return;
    
    const lastValue = data[data.length - 1];
    
    // Variation plus importante (entre -8 et +8 FCFA)
    const change = (Math.random() - 0.5) * 16;
    const newValue = Math.max(500, Math.min(1200, lastValue + change));
    
    // Ajouter nouvelle donnée
    data.push(newValue);
    
    // Générer le nouveau label en fonction du timeframe
    const newLabel = this.generateNextLabel(labels[labels.length - 1]);
    labels.push(newLabel);
    
    // Garder seulement les données de la période sélectionnée
    const timeframeDuration = TIMEFRAME_DATA[this.currentTimeframe].duration;
    const maxPoints = Math.min(timeframeDuration, 200); // Limiter à 200 points max
    
    if (data.length > maxPoints) {
      data.shift();
      labels.shift();
    }
    
    // Appliquer le zoom automatique
    this.applyAutoZoom();
    
    this.chart.update('none');
  }

  applyAutoZoom() {
    // Zoom automatique horizontal avec marge
    const dataLength = this.chart.data.labels.length;
    if (dataLength > 0) {
      // Calculer la limite maximale avec marge réduite
      const maxLimit = dataLength - 1 + 0.5;
      
      // Mettre à jour les limites de l'axe X
      this.chart.options.scales.x.afterBuildTicks = (axis) => {
        axis.max = maxLimit;
      };
    }
    
    // Zoom automatique vertical
    const data = this.chart.data.datasets[0].data;
    if (data.length > 0) {
      const minValue = Math.min(...data);
      const maxValue = Math.max(...data);
      const range = maxValue - minValue;
      const margin = range * 0.1; // 10% de marge
      
      // Mettre à jour les limites de l'axe Y
      this.chart.options.scales.y.afterDataLimits = (scale) => {
        scale.min = minValue - margin;
        scale.max = maxValue + margin;
      };
    }
  }

  generateNextLabel(lastLabel) {
    if (this.currentTimeframe === '1h' || this.currentTimeframe === '4h') {
      // Format HH:MM
      const [hours, minutes] = lastLabel.split(':').map(Number);
      let newHours = hours;
      let newMinutes = minutes + 1;
      
      if (newMinutes >= 60) {
        newMinutes = 0;
        newHours = (newHours + 1) % 24;
      }
      
      return `${newHours.toString().padStart(2, '0')}:${newMinutes.toString().padStart(2, '0')}`;
    } else if (this.currentTimeframe === '1d') {
      // Format HHh
      const hour = parseInt(lastLabel);
      return `${((hour + 1) % 24).toString().padStart(2, '0')}h`;
    } else {
      return lastLabel;
    }
  }

  togglePlayPause() {
    this.isPlaying = !this.isPlaying;
    const button = document.getElementById('play-pause-btn');
    if (button) {
      button.innerHTML = this.isPlaying ? '⏸️ Pause' : '▶️ Lecture';
    }
    
    if (this.isPlaying && TIMEFRAME_DATA[this.currentTimeframe].dynamic) {
      this.startAutoUpdate();
    } else {
      this.stopAutoUpdate();
    }
  }

  bindEvents() {
    // Événements pour les boutons de timeframe
    document.querySelectorAll('.timeframe-btn[data-timeframe]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const timeframe = e.target.getAttribute('data-timeframe');
        this.updateTimeframe(timeframe);
        
        // Mettre à jour l'état actif des boutons
        document.querySelectorAll('.timeframe-btn[data-timeframe]').forEach(b => {
          b.classList.remove('active');
        });
        e.target.classList.add('active');
        
        // Mettre à jour le titre du graphique
        const chartTitle = document.getElementById('chart-title');
        if (chartTitle) {
          chartTitle.textContent = `${timeframe.toUpperCase()} - SEMC`;
        }
        
        // Appliquer le zoom automatique après changement de timeframe
        setTimeout(() => {
          this.applyAutoZoom();
          this.chart.update('none');
        }, 100);
      });
    });

    // Événement pour le bouton play/pause
    const playPauseBtn = document.getElementById('play-pause-btn');
    if (playPauseBtn) {
      playPauseBtn.addEventListener('click', () => {
        this.togglePlayPause();
      });
    }
  }

  destroy() {
    this.stopAutoUpdate();
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
    }
    if (this.chart) {
      this.chart.destroy();
    }
  }
}

// Instance globale
const chartManager = new ChartManager();