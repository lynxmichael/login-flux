<?php
// ✅ Activer les erreurs (en développement uniquement)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Inclure les fichiers nécessaires
require_once '../config/database.php';
require_once '../models/Wallet.php';

// ✅ Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ Base de données
    try {
        $database = new Database();
        $pdo = $database->getConnection();
    } catch (Exception $e) {
        die("Erreur : Impossible d'établir la connexion à la base de données.");
    }

    // ✅ Récupération des champs
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // ✅ Validation basique
    if (empty($email)) $errors[] = "Veuillez entrer votre email.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Adresse email invalide.";
    
    if (empty($password)) $errors[] = "Veuillez entrer votre mot de passe.";

    // ✅ Si aucune erreur, on passe à la connexion
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, full_name, email, password FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // ✅ Vérification de l'utilisateur + mot de passe
            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = "Email ou mot de passe incorrect.";
            } else {
                // ✅ Gestion automatique du Wallet
                $wallet = new Wallet();
                $wallet_status = $wallet->getBalance($user['id']);

                if (!$wallet_status['success']) {
                    // ✅ Créer un wallet si inexistant
                    $wallet_creation = $wallet->createWallet($user['id']);
                    if (!$wallet_creation['success']) {
                        throw new Exception("Wallet non créé : " . $wallet_creation['message']);
                    }
                    $wallet_balance = $wallet_creation['data']['balance'];
                } else {
                    $wallet_balance = $wallet_status['data']['balance'];
                }

                // ✅ Stockage dans SESSION
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['wallet_balance'] = $wallet_balance;

                // ✅ Message de succès
                $_SESSION['success'] = "Connexion réussie ! Solde wallet : {$wallet_balance} FCFA";

                header('Location: ../app.php'); 
                exit;
            }
        } catch (Exception $e) {
            $errors[] = "Erreur interne : " . $e->getMessage();
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
    
    <meta name="description" content="FLUX.IO | Connexion à votre compte">
    <meta property="og:description" content="FLUX.IO | Connectez-vous pour accéder au marché boursier BRVM">
    
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <title>Connexion | FLUX.IO</title>
    
    <style>
        /* Mêmes styles que register.php avec quelques ajustements */
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
            max-width: 450px;
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
            <p class="auth-subtitle">Connectez-vous à votre compte</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-message show">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="">
            <div class="form-group">
                <label for="email" class="form-label">Adresse e-mail</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="votre@email.com" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Mot de passe</label>
                <div style="position: relative;">
                    <input type="password" id="password" name="password" class="form-input" placeholder="Votre mot de passe" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">👁️</button>
                </div>
            </div>

            <button type="submit" class="auth-btn" id="loginBtn">
                <span id="btnText">Se connecter</span>
                <span id="btnLoading" class="loading" style="display: none;"></span>
            </button>
        </form>

        <div class="form-footer">
            <p>Pas encore de compte ? <a href="register.php">Créer un compte</a></p>
            <p><a href="forgot_password.php">Mot de passe oublié ?</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
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

            // Validation du formulaire
            loginForm.addEventListener('submit', function(e) {
                // Afficher l'indicateur de chargement
                btnText.textContent = 'Connexion...';
                btnLoading.style.display = 'inline-block';
                loginBtn.disabled = true;
            });
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