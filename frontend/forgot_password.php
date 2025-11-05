<?php
session_start();
require_once 'config/database.php';

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: app.php');
    exit;
}

$success = $error = "";
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = "Token de réinitialisation invalide.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token)) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            // Vérifier le token et son expiration
            $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user) {
                // Hash du nouveau mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Mettre à jour le mot de passe et effacer le token
                $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
                $stmt->execute([$hashed_password, $token]);
                
                $success = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
                $token = ''; // Invalider le token après utilisation
            } else {
                $error = "Le lien de réinitialisation est invalide ou a expiré.";
            }
        } catch (PDOException $e) {
            error_log("Erreur base de données: " . $e->getMessage());
            $error = "Une erreur technique est survenue. Veuillez réessayer.";
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
    
    <meta name="description" content="FLUX.IO | Nouveau mot de passe">
    <meta property="og:description" content="FLUX.IO | Créez votre nouveau mot de passe">
    
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <title>Nouveau mot de passe | FLUX.IO</title>
    
    <style>
        /* Reprendre les mêmes styles que forgot_password.php */
        :root {
            --primary-color: #f7a64f;
            --secondary-color: #bb4444;
            --success-color: #51CF66;
            --error-color: #FF8787;
            --background-dark: #1A1B1E;
            --surface-dark: #2C2E33;
            --text-primary: #FFFFFF;
            --text-secondary: #C1C2C5;
        }

        * {
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
        }

        .form-input {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(247, 166, 79, 0.2);
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
        }

        .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(247, 166, 79, 0.3);
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-secondary);
        }

        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .error-message {
            background: rgba(187, 68, 68, 0.1);
            color: var(--error-color);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 1px solid rgba(187, 68, 68, 0.3);
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
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="logo">FLUX.IO</div>
            <p class="auth-subtitle">Créez votre nouveau mot de passe</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <?php echo $success; ?>
                <p style="margin-top: 1rem;"><a href="login.php" style="color: var(--primary-color);">Se connecter</a></p>
            </div>
        <?php elseif (empty($token)): ?>
            <div class="error-message">
                Lien de réinitialisation invalide.
                <p style="margin-top: 1rem;"><a href="forgot_password.php" style="color: var(--primary-color);">Demander un nouveau lien</a></p>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password" class="form-label">Nouveau mot de passe</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Minimum 6 caractères" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Retapez votre mot de passe" required>
                </div>

                <button type="submit" class="auth-btn">Réinitialiser le mot de passe</button>
            </form>
        <?php endif; ?>

        <div class="form-footer">
            <p><a href="login.php">← Retour à la connexion</a></p>
        </div>
    </div>
</body>
</html>