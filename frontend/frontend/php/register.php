<?php
// Activer les erreurs en mode développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../models/Wallet.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Connexion à la base
        $database = new Database();
        $pdo = $database->getConnection();
    } catch (Exception $e) {
        die("Erreur interne : connexion à la base non initialisée.");
    }

    // Sécurisation & récupération des champs
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name        = trim($_POST['full_name'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');

    // ✅ VALIDATION
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    if (empty($full_name)) {
        $errors[] = "Le nom complet est requis.";
    }

    // ✅ Vérifier si l'email existe déjà
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Cette adresse email est déjà utilisée.";
        }
    }

    // ✅ Insertion + création wallet dans une seule transaction
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1️⃣ INSERT utilisateur
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $hashed_password, $full_name, $phone]);

            $user_id = $pdo->lastInsertId();

            // 2️⃣ CRÉER WALLET ICI DIRECTEMENT (sans beginTransaction dans Wallet.php)
            $stmtWallet = $pdo->prepare("INSERT INTO wallets (user_id, balance, currency) VALUES (?, 0, 'FCFA')");
            $stmtWallet->execute([$user_id]);

            $pdo->commit();

            // ✅ Définir la session
            $_SESSION['user_id']    = $user_id;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name']  = $full_name;
            $_SESSION['wallet_balance'] = 0;

            $_SESSION['success'] = "Compte créé avec succès ! Wallet initialisé.";

            header('Location: login.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erreur lors de la création du compte : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr" class="notranslate" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="google" content="notranslate">
    
    <meta name="description" content="FLUX.IO | Créer votre compte">
    <meta property="og:description" content="FLUX.IO | Inscription pour accéder au marché boursier BRVM">
    
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <title>Inscription | FLUX.IO</title>
    
    <style>
        :root {
            --primary-color: #f7a64f;
            --secondary-color: #bb4444;
            --success-color: #51CF66;
            --error-color: #FF8787;
            --background-dark: #1A1B1E;
            --surface-dark: #2C2E33;
            --text-primary: #FFFFFF;
            --text-secondary: #C1C2C5;
            --grid-color: #141313;
            --dark: #1c1c1c;
        }

        *,
        ::before,
        ::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--background-dark);
            color: var(--text-primary);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
            background: linear-gradient(135deg, #1A1B1E 0%, #2C2E33 100%);
        }

        .auth-container {
            background: var(--surface-dark);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(45deg, var(--primary-color), #ff8c42);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .auth-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(247, 166, 79, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .password-strength {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: var(--error-color); width: 33%; }
        .strength-medium { background: #ffa726; width: 66%; }
        .strength-strong { background: var(--success-color); width: 100%; }

        .auth-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, var(--primary-color), #ff8c42);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }

        .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(247, 166, 79, 0.3);
        }

        .auth-btn:active {
            transform: translateY(0);
        }

        .auth-btn:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .form-footer a:hover {
            color: #ff8c42;
            text-decoration: underline;
        }

        .error-message {
            background: rgba(187, 68, 68, 0.1);
            color: var(--error-color);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 1px solid rgba(187, 68, 68, 0.3);
            display: none;
        }

        .error-message.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .success-message {
            background: rgba(81, 207, 102, 0.1);
            color: var(--success-color);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 1px solid rgba(81, 207, 102, 0.3);
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
        }

        .password-toggle:hover {
            color: var(--text-primary);
        }

        /* Responsive */
        @media (max-width: 576px) {
            .auth-container {
                padding: 2rem 1.5rem;
            }
            
            .logo {
                font-size: 2rem;
            }
        }

        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="logo">FLUX.IO</div>
            <p class="auth-subtitle">Créez votre compte</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-message show">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="">
            <div class="form-group">
                <label for="full_name" class="form-label">Nom complet</label>
                <input type="text" id="full_name" name="full_name" class="form-input" placeholder="Votre nom complet" 
                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Adresse e-mail</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="votre@email.com"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Téléphone (optionnel)</label>
                <input type="tel" id="phone" name="phone" class="form-input" placeholder="Votre numéro de téléphone"
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Mot de passe</label>
                <div style="position: relative;">
                    <input type="password" id="password" name="password" class="form-input" placeholder="Minimum 6 caractères" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">👁️</button>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="passwordStrength"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                <div style="position: relative;">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Retapez votre mot de passe" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">👁️</button>
                </div>
                <div id="passwordMatch" style="margin-top: 0.5rem; font-size: 0.8rem; display: none;"></div>
            </div>

            <button type="submit" class="auth-btn" id="registerBtn">
                <span id="btnText">Créer mon compte</span>
                <span id="btnLoading" class="loading" style="display: none;"></span>
            </button>
        </form>

        <div class="form-footer">
            <p>Déjà un compte ? <a href="login.php">Se connecter</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.getElementById('registerForm');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordStrength = document.getElementById('passwordStrength');
            const passwordMatch = document.getElementById('passwordMatch');
            const registerBtn = document.getElementById('registerBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');

            // Effet de ripple sur les boutons
            document.querySelectorAll('.auth-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Vérification de la force du mot de passe
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 6) strength++;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                if (password.match(/\d/)) strength++;
                if (password.match(/[^a-zA-Z\d]/)) strength++;
                
                passwordStrength.className = 'password-strength-bar';
                if (password.length > 0) {
                    if (strength <= 1) {
                        passwordStrength.classList.add('strength-weak');
                    } else if (strength <= 3) {
                        passwordStrength.classList.add('strength-medium');
                    } else {
                        passwordStrength.classList.add('strength-strong');
                    }
                }
                
                checkPasswordMatch();
            });

            // Vérification de la correspondance des mots de passe
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);

            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword.length > 0) {
                    passwordMatch.style.display = 'block';
                    if (password === confirmPassword) {
                        passwordMatch.textContent = '✓ Les mots de passe correspondent';
                        passwordMatch.style.color = 'var(--success-color)';
                    } else {
                        passwordMatch.textContent = '✗ Les mots de passe ne correspondent pas';
                        passwordMatch.style.color = 'var(--error-color)';
                    }
                } else {
                    passwordMatch.style.display = 'none';
                }
            }

            // Validation du formulaire avant soumission
            registerForm.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (password.length < 6) {
                    e.preventDefault();
                    showError('Le mot de passe doit contenir au moins 6 caractères');
                    return;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    showError('Les mots de passe ne correspondent pas');
                    return;
                }
                
                // Afficher l'indicateur de chargement
                btnText.textContent = 'Création du compte...';
                btnLoading.style.display = 'inline-block';
                registerBtn.disabled = true;
            });

            function showError(message) {
                let errorDiv = document.querySelector('.error-message');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message show';
                    registerForm.parentNode.insertBefore(errorDiv, registerForm);
                }
                errorDiv.innerHTML = message;
            }
        });

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.textContent = '🙈';
            } else {
                input.type = 'password';
                toggle.textContent = '👁️';
            }
        }
    </script>
</body>
</html>