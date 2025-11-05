
<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$success = $error = "";

// 1️⃣ Vérifier si le token existe dans l’URL
if (!isset($_GET['token'])) {
    die("Lien invalide");
}

$token = $_GET['token'];

// 2️⃣ Vérifier si le token est encore valide (pas expiré)
$stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("Lien expiré ou invalide");
}

// 3️⃣ Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($password) || empty($confirm_password)) {
        $error = "Veuillez remplir tous les champs.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        // 4️⃣ Hachage du mot de passe
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // 5️⃣ Mise à jour dans la BD + suppression du token
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['id']]);

        $success = "Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.";
        $_SESSION['success'] = $success;

        header("Location: login.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation du mot de passe</title>
</head>
<body>
    <h2>🔐 Réinitialiser votre mot de passe</h2>

    <?php if ($error): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Nouveau mot de passe :</label><br>
        <input type="password" name="password" required><br><br>

        <label>Confirmer le mot de passe :</label><br>
        <input type="password" name="confirm_password" required><br><br>

        <button type="submit">✅ Réinitialiser</button>
    </form>
</body>
</html>
