<?php
// config/User.php
require_once('database.php');
class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $name;
    public $email;
    public $password;
    public $wallet;
    public $trades;
    public $profit;
    public $join_date;
    public $last_login;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Inscription utilisateur
    public function register() {
        $sql = "INSERT INTO " . $this->table . " 
                (name, email, password, wallet, trades, profit, join_date) 
                VALUES (:name, :email, :password, 1000000, 0, 0, NOW())";
        
        try {
            $stmt = $this->conn->prepare($sql);
            
            // Nettoyer et valider les données
            $this->name = htmlspecialchars(strip_tags($this->name));
            $this->email = htmlspecialchars(strip_tags($this->email));
            
            // Hasher le mot de passe
            $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
            
            // Liaison des paramètres
            $stmt->bindParam(':name', $this->name);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $hashed_password);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erreur register: " . $e->getMessage());
            return false;
        }
    }

    // Connexion utilisateur
    public function login() {
        $sql = "SELECT * FROM " . $this->table . " WHERE email = :email";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':email', $this->email);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                
                // Vérifier le mot de passe
                if (password_verify($this->password, $user['password'])) {
                    // Mettre à jour la dernière connexion
                    $this->updateLastLogin($user['id']);
                    return $user;
                }
            }
            return false;
            
        } catch (PDOException $e) {
            error_log("Erreur login: " . $e->getMessage());
            return false;
        }
    }

    // Récupérer un utilisateur par ID
    public function getUserById($id) {
        $sql = "SELECT id, name, email, wallet, trades, profit, join_date, last_login 
                FROM " . $this->table . " 
                WHERE id = :id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Erreur getUserById: " . $e->getMessage());
            return false;
        }
    }

    // Vérifier si l'email existe
    public function emailExists() {
        $sql = "SELECT id FROM " . $this->table . " WHERE email = :email";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':email', $this->email);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Erreur emailExists: " . $e->getMessage());
            return false;
        }
    }

    // Mettre à jour la dernière connexion
    public function updateLastLogin($id) {
        $sql = "UPDATE " . $this->table . " SET last_login = NOW() WHERE id = :id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erreur updateLastLogin: " . $e->getMessage());
            return false;
        }
    }

    // Mettre à jour le profil
    public function updateProfile($id, $name, $wallet = null) {
        $sql = "UPDATE " . $this->table . " SET name = :name";
        $params = [':name' => $name];
        
        if ($wallet !== null) {
            $sql .= ", wallet = :wallet";
            $params[':wallet'] = $wallet;
        }
        
        $sql .= " WHERE id = :id";
        $params[':id'] = $id;
        
        try {
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Erreur updateProfile: " . $e->getMessage());
            return false;
        }
    }

    // Récupérer tous les utilisateurs (pour admin)
    public function getAllUsers() {
        $sql = "SELECT id, name, email, wallet, trades, profit, join_date, last_login 
                FROM " . $this->table . " 
                ORDER BY join_date DESC";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erreur getAllUsers: " . $e->getMessage());
            return false;
        }
    }

    // Mettre à jour le portefeuille
    public function updateWallet($id, $amount) {
        $sql = "UPDATE " . $this->table . " SET wallet = :amount WHERE id = :id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erreur updateWallet: " . $e->getMessage());
            return false;
        }
    }
}
?>