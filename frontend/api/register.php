<?php
// api/register.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:8000');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Inclure les fichiers de configuration
require_once('../config/database.php');
require_once('../config/user.php');

try {
    // Récupérer les données JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON invalide');
    }

    // Validation des champs requis
    if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis']);
        exit;
    }

    $name = trim($data['name']);
    $email = trim($data['email']);
    $password = $data['password'];

    // Validation basique
    if (empty($name) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tous les champs doivent être remplis']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email invalide']);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères']);
        exit;
    }

    // Connexion à la base de données
    $database = new Database();
    $db = $database->connect();
    
    if (!$db) {
        throw new Exception('Impossible de se connecter à la base de données');
    }

    $user = new User($db);

    // Vérifier si l'email existe déjà
    $user->email = $email;
    if ($user->emailExists()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
        exit;
    }

    // Créer l'utilisateur
    $user->name = $name;
    $user->email = $email;
    $user->password = $password;

    if ($user->register()) {
        // Récupérer les données de l'utilisateur créé
        $userData = $user->login();
        
        if ($userData) {
            // Définir les variables de session
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['user_name'] = $userData['name'];
            $_SESSION['user_email'] = $userData['email'];
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Inscription réussie',
                'user' => [
                    'id' => $userData['id'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'wallet' => $userData['wallet'],
                    'trades' => $userData['trades'],
                    'profit' => $userData['profit'],
                    'joinDate' => $userData['join_date'],
                    'lastLogin' => $userData['last_login'] ?? 'Aujourd\'hui'
                ]
            ]);
        } else {
            throw new Exception('Erreur lors de la connexion après inscription');
        }
    } else {
        throw new Exception('Erreur lors de l\'inscription');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
    error_log("Erreur register: " . $e->getMessage());
}
?>