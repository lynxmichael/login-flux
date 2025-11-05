<?php

class Auth {
    private $secret_key = 'fluxio_secret_key_2025';

    public function generateToken($user_id) {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'user_id' => $user_id,
            'iat' => time(),
            'exp' => time() + (7 * 24 * 60 * 60) // 7 days
        ]));
        
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $this->secret_key, true));
        return "$header.$payload.$signature";
    }

    public function verifyToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        list($header, $payload, $signature) = $parts;
        $expected_signature = base64_encode(hash_hmac('sha256', "$header.$payload", $this->secret_key, true));

        if ($signature !== $expected_signature) return false;

        $decoded = json_decode(base64_decode($payload), true);
        if ($decoded['exp'] < time()) return false;

        return $decoded;
    }

    public function getTokenFromHeader() {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $matches = [];
            preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches);
            return $matches[1] ?? null;
        }
        return null;
    }
}
?>