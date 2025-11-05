<?php
session_start();
require_once '../config/database.php';

// Activer le mode erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

$errors = [];
$success = "";

// Limiter à 5 admins maximum
try {
    $database = new Database();
    $pdo = $database->getConnection();

    $count_admins = $pdo->query("SELECT COUNT(*) AS total FROM administrators")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($count_admins >= 5) {
            $errors[] = "Limite atteinte : 5 administrateurs maximum autorisés.";
        } else {
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // ✅ Validation
            if (empty($full_name)) $errors[] = "Le nom complet est requis.";
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Adresse email invalide.";
            if (strlen($password) < 6) $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
            if ($password !== $confirm_password) $errors[] = "Les mots de passe ne correspondent pas.";

            // ✅ Vérifier email existant
            $stmt = $pdo->prepare("SELECT id FROM administrators WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Cet email est déjà utilisé.";
            }

            // ✅ Insertion
            if (empty($errors)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO administrators (full_name, email, password, role) VALUES (?, ?, ?, 'admin')");
                if ($stmt->execute([$full_name, $email, $hashed])) {
                    $success = "✅ Administrateur enregistré avec succès.";
                } else {
                    $errors[] = "Erreur lors de l'inscription.";
                }
            }
        }
    }
} catch (Exception $e) {
    $errors[] = "Erreur interne : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Créer un Administrateur | FLUX.IO</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #6366f1;
    --primary-hover: #4f46e5;
    --surface: #0f0f23;
    --card: #1a1b2f;
    --card-border: #2a2b45;
    --text: #ffffff;
    --text-secondary: #94a3b8;
    --error: #ef4444;
    --success: #10b981;
    --gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: var(--surface);
    color: var(--text);
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    position: relative;
    overflow: hidden;
}

body::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
    z-index: -1;
}

.container {
    width: 100%;
    max-width: 440px;
}

.header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.logo {
    font-size: 2rem;
    font-weight: 700;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
}

.subtitle {
    color: var(--text-secondary);
    font-weight: 400;
    font-size: 0.9rem;
}

.form-container {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    padding: 2.5rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
}

.form-group {
    margin-bottom: 1.5rem;
    position: relative;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--card-border);
    border-radius: 8px;
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    background: rgba(255, 255, 255, 0.08);
}

.form-input::placeholder {
    color: var(--text-secondary);
}

.btn {
    width: 100%;
    padding: 14px 20px;
    background: var(--gradient);
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
}

.btn:active {
    transform: translateY(0);
}

.back-link {
    display: block;
    text-align: center;
    margin-top: 1.5rem;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.875rem;
    transition: color 0.3s ease;
}

.back-link:hover {
    color: var(--primary);
}

.message {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
    animation: slideDown 0.3s ease;
}

.error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid var(--error);
    color: var(--error);
}

.success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid var(--success);
    color: var(--success);
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

.floating-shapes {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: -1;
}

.shape {
    position: absolute;
    opacity: 0.1;
    background: var(--gradient);
    border-radius: 50%;
}

.shape-1 {
    width: 100px;
    height: 100px;
    top: 10%;
    left: 10%;
    animation: float 8s ease-in-out infinite;
}

.shape-2 {
    width: 60px;
    height: 60px;
    top: 60%;
    right: 10%;
    animation: float 6s ease-in-out infinite reverse;
}

.shape-3 {
    width: 80px;
    height: 80px;
    bottom: 20%;
    left: 20%;
    animation: float 10s ease-in-out infinite;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-20px) rotate(180deg);
    }
}

@media (max-width: 480px) {
    .form-container {
        padding: 2rem 1.5rem;
    }
    
    .container {
        max-width: 100%;
    }
}
</style>
</head>
<body>
<div class="floating-shapes">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>
</div>

<div class="container">
    <div class="header">
        <div class="logo">FLUX</div>
        <div class="subtitle">Panel d'administration</div>
    </div>

    <div class="form-container">
        <h2 style="margin-bottom: 1.5rem; font-weight: 600; text-align: center;">Créer un Administrateur</h2>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <?= implode('<br>', $errors); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success"><?= $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Nom complet</label>
                <input type="text" name="full_name" class="form-input" placeholder="John Doe" required>
            </div>

            <div class="form-group">
                <label class="form-label">Adresse e-mail</label>
                <input type="email" name="email" class="form-input" placeholder="john@exemple.com" required>
            </div>

            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>

            <div class="form-group">
                <label class="form-label">Confirmer le mot de passe</label>
                <input type="password" name="confirm_password" class="form-input" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn">
                Créer l'administrateur
            </button>
        </form>

        <a href="admin_login.php" class="back-link">
            ← Retour à la connexion
        </a>
    </div>
</div>
</body>
</html>