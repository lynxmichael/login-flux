<?php
class Env {
    private static bool $loaded = false;

    public static function load(string $path = null): void {
        if (self::$loaded) return;

        // Chemin par défaut du fichier .env
        $path = $path ?? __DIR__ . '/../../.env';

        if (!file_exists($path)) {
            throw new Exception("❌ Le fichier .env est introuvable à : $path");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorer les commentaires ou lignes invalides
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            
            // Permet KEY="value # not comment"
            $line = preg_replace('/\s+#.*/', '', $line);

            // Séparation clé=valeur (2 parties maximum)
            if (!str_contains($line, '=')) continue;
            list($key, $value) = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            // Retirer les guillemets autour de la valeur
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            // Stocker dans $_ENV et variables système
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed {
        self::load(); // s'assure que les variables sont chargées
        return $_ENV[$key] ?? getenv($key) ?? $default;
    }
}

// Charger automatiquement .env
Env::load();
?>