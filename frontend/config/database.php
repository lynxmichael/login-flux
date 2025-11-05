<?php
require_once __DIR__ . '/env.php'; // Charge les variables d’environnement

class Database {
    private $type;
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    private $conn;

    public function __construct() {
        // Chargement des valeurs depuis .env
        $this->type = getenv('DB_TYPE') ?: 'mysql';
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->port = getenv('DB_PORT') ?: ($this->type === 'pgsql' ? 5432 : 3306);
        $this->db_name = getenv('DB_NAME') ?: 'trading_db';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
        $this->charset = getenv('DB_CHARSET') ?: 'utf8mb4';
    }

    public function getConnection() {
        if ($this->conn) return $this->conn; // Reuse existing connection

        try {
            if ($this->type === 'pgsql') {
                $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name}";
            } else {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset={$this->charset}";
            }

            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // ✅ Test de la connexion
            $this->conn->query($this->type === 'pgsql' ? 'SELECT 1' : 'SELECT 1');
            return $this->conn;

        } catch (PDOException $e) {
            error_log("❌ Erreur PDO : " . $e->getMessage());
            throw new Exception("Erreur : Impossible d'établir la connexion à la base de données.");
        } catch (Exception $e) {
            error_log("⚠️ Erreur système : " . $e->getMessage());
            throw new Exception("Erreur interne lors de la connexion à la base.");
        }
    }
}
?>
