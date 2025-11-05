<?php
require_once '../config/env.php';

class Auth {
    public function validateToken() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? $_POST['token'] ?? '';
        
        if (empty($token)) {
            return false;
        }
        
        // Récupérer le token depuis la session ou la base de données
        session_start();
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
            return [
                'user_id' => $_SESSION['user_id'],
                'user_email' => $_SESSION['user_email']
            ];
        }
        
        return false;
    }
    
    public function generateToken($user_id, $email) {
        $payload = [
            'user_id' => $user_id,
            'email' => $email,
            'exp' => time() + (int)Env::get('JWT_EXPIRE', 86400)
        ];
        
        // Implémentation simplifiée - dans un vrai projet, utiliser une lib JWT
        return base64_encode(json_encode($payload));
    }
}
?>