// Utilitaires généraux
class Utils {
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    static formatCurrency(amount) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount) + ' FCFA';
    }

    static showAlert(message, type = 'info') {
        // Créer une alerte simple
        const alertDiv = document.createElement('div');
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            z-index: 10000;
            transition: opacity 0.3s;
        `;
        
        switch(type) {
            case 'success':
                alertDiv.style.backgroundColor = '#0099336c';
                break;
            case 'error':
                alertDiv.style.backgroundColor = '#cc00007c';
                break;
            case 'warning':
                alertDiv.style.backgroundColor = '#ff6600';
                break;
            default:
                alertDiv.style.backgroundColor = '#0056b3';
        }
        
        alertDiv.textContent = message;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            setTimeout(() => {
                document.body.removeChild(alertDiv);
            }, 300);
        }, 3000);
    }
}

// Exposer globalement
window.Utils = Utils;