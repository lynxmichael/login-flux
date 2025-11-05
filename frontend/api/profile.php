<?php
// api/profile.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Non authentifié']));
}

require_once('../config/database.php');
require_once('../config/user.php');

try {
    $db = new Database();
    $conn = $db->connect();
    $user = new User($conn);

    $userData = $user->getUserById($_SESSION['user_id']);

    if ($userData) {
        echo json_encode([
            'success' => true,
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
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>