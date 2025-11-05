<?php
// api/login.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

require_once('../config/database.php');
require_once('../config/user.php');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Champs manquants']));
}

try {
    $db = new Database();
    $conn = $db->connect();
    $user = new User($conn);

    $user->email = htmlspecialchars($data['email']);
    $user->password = $data['password'];

    $userData = $user->login();

    if ($userData) {
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['user_name'] = $userData['name'];
        $_SESSION['user_email'] = $userData['email'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => [
                'id' => $userData['id'],
                'name' => $userData['name'],
                'email' => $userData['email'],
                'wallet' => $userData['wallet'],
                'trades' => $userData['trades'],
                'profit' => $userData['profit'],
                'joinDate' => $userData['join_date'],
                'lastLogin' => $userData['last_login']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>