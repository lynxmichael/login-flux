-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mer. 05 nov. 2025 à 09:06
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `trading_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `administrators`
--

DROP TABLE IF EXISTS `administrators`;
CREATE TABLE IF NOT EXISTS `administrators` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('superadmin','admin') COLLATE utf8mb4_general_ci DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `administrators`
--

INSERT INTO `administrators` (`id`, `full_name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'franck michael', 'franck@gmail.com', '$2y$10$VqKix4KBVPsch7pa1iTuAePrZ3X.7WxMu3IQgsefQqVYtJsuVxBxm', 'admin', '2025-10-29 13:15:07'),
(2, 'Abla Pokou', 'ablapokou6@gmail.com', '$2y$10$84MyIqCpUs6nF6nShbPOPOiqEHzMm6YCJYze54ZUglsKX5BKbA56W', 'admin', '2025-10-30 09:33:40');

-- --------------------------------------------------------

--
-- Structure de la table `manual_deposits`
--

DROP TABLE IF EXISTS `manual_deposits`;
CREATE TABLE IF NOT EXISTS `manual_deposits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Mobile Money',
  `momo_number` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `transaction_code` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `proof_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `recipient` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_type` enum('buy','sell') COLLATE utf8mb4_unicode_ci NOT NULL,
  `stock_symbol` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `fees` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `validity_date` date NOT NULL,
  `status` enum('pending','executed','cancelled','expired') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `operation_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `executed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_stock` (`stock_symbol`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `recipient`, `order_type`, `stock_symbol`, `quantity`, `price`, `fees`, `total_amount`, `validity_date`, `status`, `operation_date`, `created_at`, `executed_at`) VALUES
(1, 1, NULL, 'buy', 'SEMC', 100, 650.00, 65.00, 6565.00, '0000-00-00', 'pending', '2024-01-15 10:30:00', '2025-10-29 15:48:19', NULL),
(2, 1, NULL, 'buy', 'SEMC', 50, 740.00, 37.00, 3737.00, '0000-00-00', 'pending', '2024-01-20 14:45:00', '2025-10-29 15:48:19', NULL),
(3, 1, NULL, 'buy', 'ABJC', 50, 1550.00, 77.50, 7777.50, '0000-00-00', 'pending', '2024-01-18 11:20:00', '2025-10-29 15:48:19', NULL),
(4, 1, NULL, 'buy', 'BICC', 25, 6450.00, 161.25, 161611.25, '0000-00-00', 'pending', '2024-01-22 09:15:00', '2025-10-29 15:48:19', NULL),
(5, 3, NULL, 'buy', 'ETIT', 75, 3200.00, 240.00, 24240.00, '0000-00-00', 'pending', '2024-01-19 15:30:00', '2025-10-29 15:48:19', NULL),
(7, 5, NULL, 'buy', 'BICC', 1, 6600.00, 6.60, 6606.60, '0000-00-00', '', '2025-11-04 11:56:17', '2025-11-04 10:56:17', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `portfolio`
--

DROP TABLE IF EXISTS `portfolio`;
CREATE TABLE IF NOT EXISTS `portfolio` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `stock_symbol` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `average_price` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) NOT NULL,
  `profit_loss` decimal(15,2) DEFAULT '0.00',
  `profit_loss_percentage` decimal(5,2) DEFAULT '0.00',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_stock` (`user_id`,`stock_symbol`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `portfolio`
--

INSERT INTO `portfolio` (`id`, `user_id`, `stock_symbol`, `quantity`, `average_price`, `current_value`, `profit_loss`, `profit_loss_percentage`, `last_updated`) VALUES
(5, 1, 'SEMC', 150, 680.00, 0.00, 0.00, 0.00, '2025-10-29 15:11:26'),
(6, 1, 'ABJC', 50, 1550.00, 0.00, 0.00, 0.00, '2025-10-29 15:11:26'),
(7, 1, 'BICC', 25, 6450.00, 0.00, 0.00, 0.00, '2025-10-29 15:11:26'),
(8, 1, 'BNBC', 10, 6150.00, 0.00, 0.00, 0.00, '2025-10-29 15:11:26');

-- --------------------------------------------------------

--
-- Structure de la table `stocks`
--

DROP TABLE IF EXISTS `stocks`;
CREATE TABLE IF NOT EXISTS `stocks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `symbol` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `current_price` decimal(15,2) NOT NULL,
  `previous_price` decimal(15,2) NOT NULL,
  `variation` decimal(5,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbol` (`symbol`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `stocks`
--

INSERT INTO `stocks` (`id`, `symbol`, `name`, `current_price`, `previous_price`, `variation`, `created_at`, `updated_at`) VALUES
(1, 'SEMC', 'Société des Eaux Minérales de Côte d\'Ivoire', 720.00, 720.00, 0.00, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(2, 'ABJC', 'Abidjan Java Company', 1600.00, 1600.00, 0.06, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(3, 'BICC', 'Banque Internationale de Côte d\'Ivoire', 6600.00, 6500.00, 1.54, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(4, 'BNBC', 'Banque Nationale de Bourse de Côte d\'Ivoire', 6100.00, 6114.00, -0.23, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(5, 'BOAB', 'Bank of Africa Bénin', 6110.00, 6103.00, 0.12, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(6, 'BOABF', 'Bank of Africa Burkina Faso', 5395.00, 5350.00, 0.87, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(7, 'BOAC', 'Bank of Africa Congo', 4310.00, 4344.00, -0.79, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(8, 'BOAN', 'Bank of Africa Niger', 2550.00, 2545.00, 0.20, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(9, 'DAS', 'Diamond Stone', 2420.00, 2440.00, -0.79, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(10, 'CABC', 'Côte d\'Ivoire Bank Corporation', 1095.00, 1160.00, -5.73, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(11, 'ETIT', 'Electronic Technology and IT', 3250.00, 3210.00, 1.25, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(12, 'FTSC', 'Financial Technology and Security Company', 4100.00, 4120.00, -0.50, '2025-10-29 15:39:45', '2025-10-29 15:39:45'),
(13, 'NEIC', 'New Energy and Infrastructure Company', 2800.00, 2775.00, 0.90, '2025-10-29 15:39:45', '2025-10-29 15:39:45');

-- --------------------------------------------------------

--
-- Structure de la table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `level` enum('info','warning','error','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_level` (`level`),
  KEY `idx_module` (`module`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_created` (`user_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `system_logs`
--

INSERT INTO `system_logs` (`id`, `level`, `module`, `message`, `user_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'info', 'auth', 'Connexion réussie de l\'utilisateur FRANCK', 1, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-10-23 08:30:15'),
(2, 'warning', 'trading', 'Tentative d\'achat avec solde insuffisant - Utilisateur ID: 7', 7, '192.168.1.105', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15', '2025-10-23 09:15:22'),
(3, 'error', 'payment', 'Échec de traitement du paiement pour la transaction #45', NULL, '192.168.1.120', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-10-23 10:45:33'),
(4, 'info', 'user', 'Nouvel utilisateur inscrit: abla pokou', 7, '192.168.1.105', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15', '2025-10-23 12:10:27'),
(5, 'critical', 'database', 'Timeout de connexion à la base de données - 3 tentatives échouées', NULL, '192.168.1.1', 'System', '2025-10-23 13:20:05'),
(6, 'info', 'trading', 'Ordre d\'achat exécuté: 10 actions SEMC à 720 FCFA - User ID: 1', 1, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-10-23 14:06:34'),
(7, 'warning', 'security', 'Tentative de connexion avec mot de passe incorrect pour email: admin@flux.io', NULL, '192.168.1.200', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36', '2025-10-23 15:30:18'),
(8, 'info', 'system', 'Sauvegarde automatique de la base de données effectuée', NULL, '192.168.1.1', 'System', '2025-10-23 16:00:00'),
(9, 'error', 'api', 'Échec de connexion à l\'API de prix boursier - Timeout après 10s', NULL, '192.168.1.50', 'System', '2025-10-23 16:45:12'),
(10, 'info', 'user', 'Profil utilisateur mis à jour - User ID: 5', 5, '192.168.1.110', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', '2025-10-23 17:20:45'),
(11, 'warning', 'trading', 'Ordre de vente partiellement exécuté - 5/8 actions SEMC vendues', 1, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-10-23 18:15:30'),
(12, 'info', 'admin', 'Administrateur a validé la transaction #3', 1, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-10-23 19:05:22'),
(13, 'critical', 'system', 'Espace disque critique - Moins de 5% d\'espace libre', NULL, '192.168.1.1', 'System', '2025-10-23 20:30:15'),
(14, 'info', 'email', 'Email de confirmation envoyé à dan@gmail.com', 5, '192.168.1.110', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', '2025-10-23 21:10:08'),
(15, 'error', 'payment', 'Échec de débit pour le retrait - Solde insuffisant User ID: 8', 8, '192.168.1.115', 'Mozilla/5.0 (Android 13; Mobile) AppleWebKit/537.36', '2025-10-23 22:45:33');

-- --------------------------------------------------------

--
-- Structure de la table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `wallet_id` int NOT NULL,
  `type` enum('buy','sell','deposit','withdrawal','transfer','dividend','fee') COLLATE utf8mb4_unicode_ci NOT NULL,
  `stock_symbol` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `fees` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','completed','cancelled','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `description` text COLLATE utf8mb4_unicode_ci,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_wallet_id` (`wallet_id`),
  KEY `idx_type` (`type`),
  KEY `idx_stock_symbol` (`stock_symbol`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_created` (`user_id`,`created_at`)
) ;

--
-- Déchargement des données de la table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `wallet_id`, `type`, `stock_symbol`, `quantity`, `price`, `amount`, `fees`, `status`, `description`, `metadata`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'buy', 'SEMC', 10, 720.00, -7200.00, 10.00, 'completed', 'Achat de 10 actions SEMC', NULL, '2025-10-22 14:06:34', '2025-10-22 14:06:34'),
(2, 1, 1, 'buy', 'ABJC', 5, 1600.00, -8000.00, 15.00, 'completed', 'Achat de 5 actions ABJC', NULL, '2025-10-22 14:06:34', '2025-10-22 14:06:34'),
(3, 1, 1, 'sell', 'SEMC', 5, 750.00, 3750.00, 8.00, 'completed', 'Vente de 5 actions SEMC', NULL, '2025-10-22 14:06:34', '2025-10-22 14:06:34'),
(4, 1, 1, 'dividend', 'SEMC', 0, 0.00, 120.50, 0.00, 'completed', 'Dividendes SEMC', NULL, '2025-10-22 14:06:34', '2025-10-22 14:06:34'),
(5, 2, 2, 'buy', 'BICC', 8, 6600.00, -52800.00, 25.00, 'completed', 'Achat de 8 actions BICC', NULL, '2025-10-22 14:06:34', '2025-10-22 14:06:34'),
(6, 5, 0, 'buy', '', 0, 0.00, 6606.60, 0.00, 'completed', 'Achat de 1 actions BICC à 6,600.00 FCFA', NULL, '2025-11-04 10:56:17', '2025-11-04 10:56:17');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `wallet_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `reset_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_session_token` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `status`, `created_at`, `is_active`, `wallet_balance`, `reset_token`, `reset_expires`, `last_login`) VALUES
(1, 'FRANCK', 'franckmichael@gmail.com', '$2y$10$eea4.2GHWBJl4GKGzJdvGOdssnH2ogXki6PTgTOVhs1WTHGULu1I.', '6f698a4401f2573', 'active', '2025-10-20 16:15:05', 1, 0.00, NULL, NULL, NULL),
(3, 'koffi franck', 'koffifranck@gmail.com', '$2y$10$3F6pnuElZbSw3EAFq2Ne.uSXEYpDlyjmF497n8Xz47CH59yIPPBJS', '094a24e438d064a', 'active', '2025-10-20 16:15:05', 1, 0.00, NULL, NULL, NULL),
(5, 'dan', 'dan@gmail.com', '$2y$10$hxnVo06430qcFUe5uUNoXe2pQJs52DRivudNZ6FTv2xp6A2TtkOZy', '0152118179', 'active', '2025-10-20 16:18:08', 1, 0.00, NULL, NULL, NULL),
(7, 'abla pokou', 'ablapokou6@gmail.com', '$2y$10$Nv7PJqu3KAak93Ei9ITqcuf8ywhdYRtGAyPIdlsnwqG.Ps9wnlS92', '0546979919', 'active', '2025-10-23 12:10:27', 1, 0.00, NULL, NULL, NULL),
(8, 'maman', 'maman@gmail.com', '$2y$10$2NpdtMKVDvvriTh1gP9VTuhwynUfRgCRho2YsdCOP9JN6J4bVCORu', '0768470669', 'active', '2025-10-23 12:15:45', 1, 0.00, NULL, NULL, NULL),
(9, 'JJQJQJ', 'eudes@gmail.com', '$2y$10$5bTgF1x3NUqbDz.l1twubeH0f.Ldsm7NA3sa/sfqkqeK4FK4IIYLW', '0152118179', 'active', '2025-10-28 10:36:48', 1, 0.00, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `user_portfolio`
--

DROP TABLE IF EXISTS `user_portfolio`;
CREATE TABLE IF NOT EXISTS `user_portfolio` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `stock_symbol` varchar(10) NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `average_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_stock` (`user_id`,`stock_symbol`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

--
-- Déchargement des données de la table `user_portfolio`
--

INSERT INTO `user_portfolio` (`id`, `user_id`, `stock_symbol`, `quantity`, `average_price`, `created_at`, `updated_at`) VALUES
(1, 1, 'SEMC', 150, 680.00, '2025-10-29 15:45:15', '2025-10-29 15:45:15'),
(2, 1, 'ABJC', 50, 1550.00, '2025-10-29 15:45:15', '2025-10-29 15:45:15'),
(3, 1, 'BICC', 25, 6450.00, '2025-10-29 15:45:15', '2025-10-29 15:45:15'),
(4, 1, 'BNBC', 10, 6150.00, '2025-10-29 15:45:15', '2025-10-29 15:45:15'),
(5, 2, 'SEMC', 100, 700.00, '2025-10-29 15:45:15', '2025-10-29 15:45:15'),
(6, 2, 'BOAB', 30, 6000.00, '2025-10-29 15:45:15', '2025-10-29 15:45:15'),
(7, 3, 'ETIT', 75, 3200.00, '2025-10-29 15:45:15', '2025-10-29 15:45:15'),
(8, 4, 'FTSC', 40, 4100.00, '2025-10-29 15:45:15', '2025-10-29 15:45:15'),
(9, 5, 'BICC', 1, 6600.00, '2025-11-04 10:56:18', '2025-11-04 10:56:18');

-- --------------------------------------------------------

--
-- Structure de la table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `is_valid` tinyint(1) DEFAULT '1',
  `login_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_token` (`session_token`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_stocks`
--

DROP TABLE IF EXISTS `user_stocks`;
CREATE TABLE IF NOT EXISTS `user_stocks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `stock_symbol` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `average_buy_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_invested` decimal(15,2) NOT NULL DEFAULT '0.00',
  `current_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `unrealized_pnl` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_stock` (`user_id`,`stock_symbol`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_stock_symbol` (`stock_symbol`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_stocks`
--

INSERT INTO `user_stocks` (`id`, `user_id`, `stock_symbol`, `quantity`, `average_buy_price`, `total_invested`, `current_value`, `unrealized_pnl`, `created_at`, `updated_at`) VALUES
(7, 1, 'SEMC', 5, 720.00, 3600.00, 0.00, 0.00, '2025-10-22 14:15:29', '2025-10-22 14:15:29');

-- --------------------------------------------------------

--
-- Structure de la table `user_transactions`
--

DROP TABLE IF EXISTS `user_transactions`;
CREATE TABLE IF NOT EXISTS `user_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `transaction_type` enum('stock_purchase','stock_sale','dividend','fee','transfer','adjustment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `stock_symbol` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `price_per_share` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `fees` decimal(10,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','completed','cancelled','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `transaction_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `settlement_date` timestamp NULL DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `broker_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_stock_symbol` (`stock_symbol`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_status` (`status`),
  KEY `idx_user_stock_date` (`user_id`,`stock_symbol`,`transaction_date`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_transactions`
--

INSERT INTO `user_transactions` (`id`, `user_id`, `transaction_type`, `stock_symbol`, `quantity`, `price_per_share`, `total_amount`, `fees`, `net_amount`, `status`, `transaction_date`, `settlement_date`, `description`, `broker_reference`, `created_at`, `updated_at`) VALUES
(1, 1, 'stock_purchase', 'SEMC', 10, 720.00, 7200.00, 10.00, -7210.00, 'completed', '2025-10-22 14:06:34', '2025-10-24 14:06:34', 'Achat initial de 10 actions SEMC', 'BRK001234', '2025-10-29 16:11:19', '2025-10-29 16:11:19'),
(2, 1, 'stock_purchase', 'ABJC', 5, 1600.00, 8000.00, 15.00, -8015.00, 'completed', '2025-10-22 14:06:34', '2025-10-24 14:06:34', 'Achat de 5 actions ABJC', 'BRK001235', '2025-10-29 16:11:19', '2025-10-29 16:11:19'),
(3, 1, 'stock_sale', 'SEMC', 5, 750.00, 3750.00, 8.00, 3742.00, 'completed', '2025-10-22 14:06:34', '2025-10-24 14:06:34', 'Vente partielle de 5 actions SEMC', 'BRK001236', '2025-10-29 16:11:19', '2025-10-29 16:11:19'),
(4, 1, 'dividend', 'SEMC', 0, 0.00, 120.50, 0.00, 120.50, 'completed', '2025-10-22 14:06:34', '2025-10-22 14:06:34', 'Dividendes trimestriels SEMC', 'DIV001237', '2025-10-29 16:11:19', '2025-10-29 16:11:19'),
(5, 2, 'stock_purchase', 'BICC', 8, 6600.00, 52800.00, 25.00, -52825.00, 'completed', '2025-10-22 14:06:34', '2025-10-24 14:06:34', 'Achat de 8 actions BICC', 'BRK001238', '2025-10-29 16:11:19', '2025-10-29 16:11:19'),
(6, 1, 'fee', NULL, 0, 0.00, 5.00, 0.00, -5.00, 'completed', '2025-10-23 09:00:00', '2025-10-23 09:00:00', 'Frais de tenue de compte mensuels', 'FEE001239', '2025-10-29 16:11:19', '2025-10-29 16:11:19'),
(7, 1, 'stock_purchase', 'SEMC', 3, 740.00, 2220.00, 7.00, -2227.00, 'pending', '2025-10-23 10:30:00', NULL, 'Achat complémentaire SEMC', 'BRK001240', '2025-10-29 16:11:19', '2025-10-29 16:11:19'),
(8, 5, 'stock_purchase', 'BICC', 2, 6650.00, 13300.00, 10.00, -13310.00, 'completed', '2025-10-23 11:00:00', '2025-10-25 11:00:00', 'Premier achat BICC', 'BRK001241', '2025-10-29 16:11:19', '2025-10-29 16:11:19'),
(9, 1, 'dividend', 'ABJC', 0, 0.00, 45.00, 0.00, 45.00, 'completed', '2025-10-23 12:00:00', '2025-10-23 12:00:00', 'Dividendes ABJC', 'DIV001242', '2025-10-29 16:11:19', '2025-10-29 16:11:19'),
(10, 1, 'stock_sale', 'ABJC', 2, 1650.00, 3300.00, 8.00, 3292.00, 'completed', '2025-10-23 14:00:00', '2025-10-25 14:00:00', 'Vente partielle ABJC', 'BRK001243', '2025-10-29 16:11:19', '2025-10-29 16:11:19');

-- --------------------------------------------------------

--
-- Structure de la table `wallets`
--

DROP TABLE IF EXISTS `wallets`;
CREATE TABLE IF NOT EXISTS `wallets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT '10000.00',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FCFA',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_currency` (`currency`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `wallets`
--

INSERT INTO `wallets` (`id`, `user_id`, `balance`, `currency`, `created_at`, `updated_at`) VALUES
(10, 1, 100000.00, 'FCFA', '2025-10-22 14:10:23', '2025-10-22 14:10:23'),
(11, 5, 3393.40, 'FCFA', '2025-10-23 11:54:25', '2025-11-04 10:56:16'),
(13, 7, 0.00, 'FCFA', '2025-10-23 12:10:27', '2025-10-23 12:10:27'),
(14, 8, 0.00, 'FCFA', '2025-10-23 12:15:46', '2025-10-23 12:15:46'),
(15, 9, 0.00, 'FCFA', '2025-10-28 10:36:49', '2025-10-28 10:36:49');

-- --------------------------------------------------------

--
-- Structure de la table `wallet_history`
--

DROP TABLE IF EXISTS `wallet_history`;
CREATE TABLE IF NOT EXISTS `wallet_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `wallet_id` int NOT NULL,
  `balance` decimal(15,2) NOT NULL,
  `transaction_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_id` (`wallet_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_wallet_created` (`wallet_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `wallet_history`
--

INSERT INTO `wallet_history` (`id`, `wallet_id`, `balance`, `transaction_id`, `created_at`) VALUES
(1, 10, 100000.00, NULL, '2025-10-22 14:10:23'),
(2, 10, 99290.00, 1, '2025-10-22 14:06:34'),
(3, 10, 91290.00, 2, '2025-10-22 14:06:34'),
(4, 10, 95040.00, 3, '2025-10-22 14:06:34'),
(5, 10, 95160.50, 4, '2025-10-22 14:06:34'),
(6, 11, 10000.00, NULL, '2025-10-23 11:54:25'),
(7, 13, 0.00, NULL, '2025-10-23 12:10:27'),
(8, 14, 0.00, NULL, '2025-10-23 12:15:46');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `manual_deposits`
--
ALTER TABLE `manual_deposits`
  ADD CONSTRAINT `manual_deposits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `portfolio`
--
ALTER TABLE `portfolio`
  ADD CONSTRAINT `portfolio_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_stocks`
--
ALTER TABLE `user_stocks`
  ADD CONSTRAINT `user_stocks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `wallets`
--
ALTER TABLE `wallets`
  ADD CONSTRAINT `wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
