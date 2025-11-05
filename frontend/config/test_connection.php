<?php
require_once __DIR__ . '/Database.php';

echo "🔍 Test de connexion à la base de données...\n";

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Test selon le type de base
    $dbType = getenv('DB_TYPE');
    $testQuery = ($dbType === 'pgsql') ? "SELECT version();" : "SELECT VERSION()";

    $stmt = $conn->query($testQuery);
    $result = $stmt->fetch();

    echo "✅ Connexion réussie !\n";
    echo "📌 Type de base : " . strtoupper($dbType) . "\n";
    echo "📦 Base de données : " . getenv('DB_NAME') . "\n";
    echo "🖥️ Version du serveur : " . ($result ? implode(" ", $result) : "Non disponible") . "\n";

} catch (Exception $e) {
    echo "❌ Échec de connexion !\n";
    echo "📍 Erreur : " . $e->getMessage() . "\n";
}
