<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si l'utilisateur n'est pas connecté → redirection login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
