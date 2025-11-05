<?php
session_start();
require_once '../config/database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $database = new Database();
    $pdo = $database->getConnection();

    $stmt = $pdo->prepare("SELECT * FROM administrators WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_role'] = $admin['role'];

        header('Location: admin_dashboard.php');
        exit;
    } else {
        $errors[] = "Email ou mot de passe incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion Admin | FLUX.IO</title>
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
    max-width: 400px;
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

.links {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.5rem;
    font-size: 0.875rem;
}

.link {
    color: var(--text-secondary);
    text-decoration: none;
    transition: color 0.3s ease;
}

.link:hover {
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
    width: 80px;
    height: 80px;
    top: 20%;
    left: 15%;
    animation: float 8s ease-in-out infinite;
}

.shape-2 {
    width: 50px;
    height: 50px;
    top: 70%;
    right: 15%;
    animation: float 6s ease-in-out infinite reverse;
}

.shape-3 {
    width: 60px;
    height: 60px;
    bottom: 30%;
    left: 25%;
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

.password-container {
    position: relative;
}

.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.9rem;
}

@media (max-width: 480px) {
    .form-container {
        padding: 2rem 1.5rem;
    }
    
    .container {
        max-width: 100%;
    }
    
    .links {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
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
        <div class="subtitle">Espace Administrateur</div>
    </div>

    <div class="form-container">
        <h2 style="margin-bottom: 1.5rem; font-weight: 600; text-align: center;">Connexion</h2>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <?= implode('<br>', $errors); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Adresse e-mail</label>
                <input type="email" name="email" class="form-input" placeholder="admin@exemple.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <div class="password-container">
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required id="password">
                    <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn">
                Se connecter
            </button>
        </form>

        <div class="links">
            <a href="forgot_password.php" class="link">Mot de passe oublié ?</a>
            <a href="create_admin.php" class="link">Créer un compte</a>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('.toggle-password');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleButton.textContent = '🔒';
    } else {
        passwordInput.type = 'password';
        toggleButton.textContent = '👁️';
    }
}
</script>
</body>
</html>