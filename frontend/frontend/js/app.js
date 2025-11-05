class AuthManager {
    constructor() {
        this.isLoggedIn = false;
        this.currentUser = null;
        this.init();
    }

    init() {
        this.checkAuthState();
        this.bindEvents();
    }

    async checkAuthState() {
        const sessionToken = localStorage.getItem('session_token');
        
        if (sessionToken) {
            try {
                const response = await this.makeRequest('../php/auth.php', {
                    action: 'check_session',
                    session_token: sessionToken
                });

                if (response.success) {
                    this.isLoggedIn = true;
                    this.currentUser = response.user;
                    this.setLoggedInState(true);
                    this.updateProfileModal();
                } else {
                    this.clearAuthData();
                }
            } catch (error) {
                console.error('Erreur vérification session:', error);
                this.clearAuthData();
            }
        }
    }

    bindEvents() {
        // Formulaire de connexion
        document.getElementById('loginForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });

        // Formulaire d'inscription
        document.getElementById('registerForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleRegister();
        });

        // Onglets d'authentification
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const tabName = e.target.getAttribute('data-tab');
                this.switchTab(tabName);
            });
        });

        // Boutons de fermeture
        document.getElementById('authCancel')?.addEventListener('click', () => {
            this.closeAuthModal();
        });

        document.getElementById('registerCancel')?.addEventListener('click', () => {
            this.closeAuthModal();
        });

        document.getElementById('profileClose')?.addEventListener('click', () => {
            this.closeProfileModal();
        });
    }

    async handleLogin() {
        const email = document.getElementById('loginEmail').value;
        const password = document.getElementById('loginPassword').value;
        
        // Masquer les erreurs précédentes
        this.hideErrors();

        try {
            const response = await this.makeRequest('../php/auth_check.php', {
                action: 'login',
                email: email,
                password: password
            });

            if (response.success) {
                this.isLoggedIn = true;
                this.currentUser = response.user;
                
                // Stocker les données de session
                localStorage.setItem('session_token', response.session_token);
                localStorage.setItem('user_data', JSON.stringify(response.user));
                
                this.setLoggedInState(true);
                this.updateProfileModal();
                this.closeAuthModal();
                this.showNotification('Connexion réussie !', 'success');
                
                // Recharger la page pour mettre à jour l'interface complète
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showError('login', response.message);
            }
        } catch (error) {
            console.error('Erreur connexion:', error);
            this.showError('login', 'Erreur de connexion au serveur. Vérifiez que PHP est bien configuré.');
        }
    }

    async handleRegister() {
        const name = document.getElementById('registerName').value;
        const email = document.getElementById('registerEmail').value;
        const password = document.getElementById('registerPassword').value;
        const confirmPassword = document.getElementById('registerConfirmPassword').value;

        // Masquer les erreurs précédentes
        this.hideErrors();

        // Validation côté client
        if (password !== confirmPassword) {
            this.showError('register', 'Les mots de passe ne correspondent pas.');
            return;
        }

        if (password.length < 6) {
            this.showError('register', 'Le mot de passe doit contenir au moins 6 caractères.');
            return;
        }

        try {
            const response = await this.makeRequest('../php/auth_check.php', {
                action: 'register',
                name: name,
                email: email,
                password: password
            });

            if (response.success) {
                this.showRegisterSuccess(response.message);
                
                // Se connecter automatiquement après inscription
                setTimeout(() => {
                    this.switchTab('login');
                    // Pré-remplir le formulaire de connexion
                    document.getElementById('loginEmail').value = email;
                }, 2000);
            } else {
                this.showError('register', response.message);
            }
        } catch (error) {
            console.error('Erreur inscription:', error);
            this.showError('register', 'Erreur de connexion au serveur. Vérifiez que PHP est bien configuré.');
        }
    }

    async logout() {
        try {
            const sessionToken = localStorage.getItem('session_token');
            const response = await this.makeRequest('php/auth_check.php', {
                action: 'logout',
                session_token: sessionToken
            });

            this.clearAuthData();
            this.setLoggedInState(false);
            this.closeProfileModal();
            this.showNotification('Déconnexion réussie.', 'info');
            
            // Recharger la page pour réinitialiser l'état
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } catch (error) {
            console.error('Erreur déconnexion:', error);
            // Déconnexion forcée même en cas d'erreur
            this.clearAuthData();
            window.location.reload();
        }
    }

    clearAuthData() {
        localStorage.removeItem('session_token');
        localStorage.removeItem('user_data');
        this.isLoggedIn = false;
        this.currentUser = null;
    }

    setLoggedInState(loggedIn) {
        const btnLogin = document.getElementById('btnLogin');
        const btnRegister = document.getElementById('btnRegister');
        const btnProfile = document.getElementById('btnProfile');

        if (loggedIn) {
            btnLogin.style.display = 'none';
            btnRegister.style.display = 'none';
            btnProfile.style.display = 'flex';
        } else {
            btnLogin.style.display = 'flex';
            btnRegister.style.display = 'flex';
            btnProfile.style.display = 'none';
        }
    }

    updateProfileModal() {
        if (this.currentUser) {
            document.getElementById('profileName').textContent = this.currentUser.name;
            document.getElementById('profileEmail').textContent = this.currentUser.email;
            
            // Formater la date de création
            const sinceDate = new Date(this.currentUser.created_at);
            document.getElementById('profileSince').textContent = sinceDate.getFullYear();
            
            document.getElementById('profileWallet').textContent = `${parseFloat(this.currentUser.wallet).toLocaleString('fr-FR')} FCFA`;
            
            // Dernière connexion
            const lastLogin = this.currentUser.last_login ? 
                new Date(this.currentUser.last_login).toLocaleDateString('fr-FR') : 'Aujourd\'hui';
            document.getElementById('profileLastLogin').textContent = lastLogin;
            
            document.getElementById('profileTrades').textContent = this.currentUser.trades_count || 0;
            document.getElementById('profileProfit').textContent = `${this.currentUser.performance || 0}%`;

            // Initiales pour l'avatar
            const initials = this.currentUser.name.split(' ').map(n => n[0]).join('').toUpperCase();
            document.getElementById('profileAvatar').textContent = initials;
        }
    }

    // Méthodes utilitaires
    switchTab(tabName) {
        document.querySelectorAll('.auth-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.auth-tab-content').forEach(content => content.classList.remove('active'));

        document.querySelector(`.auth-tab[data-tab="${tabName}"]`).classList.add('active');
        document.getElementById(`${tabName}Tab`).classList.add('active');
    }

    openAuthModal() {
        document.getElementById('authModal').style.display = 'flex';
        this.switchTab('login');
        this.hideErrors();
    }

    openRegisterModal() {
        document.getElementById('authModal').style.display = 'flex';
        this.switchTab('register');
        this.hideErrors();
    }

    openProfileModal() {
        this.updateProfileModal();
        document.getElementById('profileModal').style.display = 'flex';
    }

    closeAuthModal() {
        document.getElementById('authModal').style.display = 'none';
        this.clearForms();
    }

    closeProfileModal() {
        document.getElementById('profileModal').style.display = 'none';
    }

    clearForms() {
        document.getElementById('loginForm')?.reset();
        document.getElementById('registerForm')?.reset();
        this.hideErrors();
    }

    hideErrors() {
        document.getElementById('loginError').style.display = 'none';
        document.getElementById('registerError').style.display = 'none';
        document.getElementById('registerSuccess').style.display = 'none';
    }

    showError(formType, message) {
        const errorElement = document.getElementById(`${formType}Error`);
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        
        // Masquer le succès si on montre une erreur
        if (formType === 'register') {
            document.getElementById('registerSuccess').style.display = 'none';
        }
    }

    showRegisterSuccess(message) {
        const successElement = document.getElementById('registerSuccess');
        successElement.textContent = message;
        successElement.style.display = 'block';
        
        // Masquer l'erreur si on montre un succès
        document.getElementById('registerError').style.display = 'none';
    }

    showNotification(message, type = 'info') {
        // Implémentation basique de notification - vous pouvez utiliser Toast ou autre
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            z-index: 10000;
            font-weight: bold;
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        `;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 3000);
    }

    async makeRequest(url, data) {
        try {
            const formData = new FormData();
            for (const key in data) {
                formData.append(key, data[key]);
            }

            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }

            const result = await response.json();
            return result;

        } catch (error) {
            console.error('Erreur fetch:', error);
            throw error;
        }
    }
}

// Fonction globale pour la déconnexion
function deconnexion() {
    if (window.authManager) {
        window.authManager.logout();
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    window.authManager = new AuthManager();
});