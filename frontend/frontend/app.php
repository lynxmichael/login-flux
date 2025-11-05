<?php
session_start();
require_once 'config/database.php';
require_once 'php/auth_check.php';

// Connexion à la base
$database = new Database();
$pdo = $database->getConnection();

// Récupérer les infos complètes de l'utilisateur connecté depuis la base
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, full_name, email, phone, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>


<!DOCTYPE html>
<html lang="fr" class="notranslate" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="google" content="notranslate">
    
    <meta name="description" content="FLUX.IO | Visualisez vos données du marché boursier BRVM en temps réel">
    <meta property="og:description" content="FLUX.IO | Trading et visualisation des données BRVM en temps réel">
    
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <title>FLUX.IO | Marché boursier Africain en temps réel</title>
    
    <style>
        :root {
            --primary-color: #f7a64f;
            --secondary-color: #bb4444;
            --success-color: #51CF66;
            --error-color: #FF8787;
            --background-dark: #1A1B1E;
            --surface-dark: #2C2E33;
            --text-primary: #FFFFFF;
            --text-secondary: #C1C2C5;
            --grid-color: #141313;
            --dark: #1c1c1c;
            --favorite-color: #FFD700;
        }

        *,
        ::before,
        ::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
       
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--background-dark);
            color: var(--text-primary);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        /* Header */
       .header {
    background: rgb(30, 30, 30);
    margin: 0 auto;
    padding: 1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    max-width: 1800px;
    position: fixed;
    z-index: 100;
    box-shadow: 0 4px 16px #0006;
}

        .logo {
            font-size: 2rem;
            font-weight: bold;
            background: var(--primary-color);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            cursor:pointer;
        }

        .market-selector {
            background: var(--surface-dark);
            border: 1px solid var(--text-secondary);
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
        }

        /* Slider publicitaire */
        .slider-container {
            position: relative;
            width: 95%;
            max-width: 1800px;
            margin: 1rem auto;
            margin-top: 100px;
            border-radius: 0;
            overflow: hidden;
            height: 400px;
        }

        .slider {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .slide {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .slide.active {
            opacity: 1;
        }

        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .slider-controls {
            position: absolute;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 0.5rem;
        }

        .slider-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .slider-dot.active {
            background: var(--primary-color);
        }

        /* Search */
        .search-container {
            padding: 1rem 0;
            display: flex;
            justify-content: center;
            width: 80%;
            max-width: 1800px;
        }

        .search-input-wrapper {
            position: relative;
            max-width: 600px;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 0.8rem 3rem 0.8rem 1rem;
            background: var(--surface-dark);
            border: 1px solid var(--text-primary);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .search-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        /* Table */
        .table-wrapper {
            width: 100%;
            max-width: 1800px;
            margin: 1rem auto;
            overflow-x: auto;
            /* Ajout pour le défilement horizontal */
            border-radius: 8px;
        }

        .table-container {
            background: var(--background-dark);
            border-radius: 0px;
            overflow: hidden;
            width: 100%;
            /* Modification pour permettre le défilement horizontal */
            min-width: 1200px; /* Largeur minimale pour forcer le défilement */
        }

        .currency-table {
            width: 100%;
            border-collapse: collapse;

        }

        .currency-table th {
            background: #202020;
            color: var(--text-primary);
            padding: 0.5rem;
            cursor: pointer;
            transition: background 0.3s ease;

        }

        .currency-table th:hover {
            background: rgba(74, 145, 226, 0.100);
        }

        .currency-table td {
            padding: 0rem 1rem;
            border-bottom: 1px solid rgba(64,64,64,0.3);
            align-items: center;
            justify-content: space-between;
            /* Empêche le redimensionnement des colonnes */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .currency-table tr {
            height: 50px;
        }

        .currency-table tr:hover {
            background: rgba(74, 145, 226, 0.100);
        }

        .name-cell {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .icon-button {
            background: none;
            border: none;
            cursor: pointer;
            display: inline-block;
            align-items: center;
            justify-content: center;
            width: 10px;
            height: 10px;
            margin-left: 15%;
           
           
        }

        .favorite-btn {
            align-items: center;
            justify-content: center;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50px;
            background: var(--surface-dark);
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.5s ease;
            font-size: larger;
        }

        .favorite-btn:hover {
            background: var(--text-primary);
        }

        .favorite-btn.active {
            color: #FFD700;
        }

        .currency-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .currency-header img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        .ls-Noms {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--text-primary);
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .ls-Noms:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .positive {
            background: rgb(34, 136, 34);
        }

        .negative {
            background: rgb(187, 68, 68);;
        }

        /* Converter - Même largeur que le tableau */
        .converter-section {
            margin: 1rem auto;
            background: #1e1e1e;
            padding: 2rem;
            border-radius: 12px;
            overflow-x: auto;
            width: 85%;
            max-width: 1800px;
        }

        .converter-title {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--text-primary);
            font-size: 1.5rem;
        }

        .converter-form {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            max-width: 800px;
            margin: 0 auto;
            flex-wrap: wrap;
        }

        .converter-input {
            flex: 1;
            min-width: 100px;
            padding: 0.8rem;
            background: var(--surface-dark);
            border: 1px solid var(--text-secondary);
            border-radius: 4px;
            color: var(--text-primary);
            text-align: center;
        }

        .converter-select {
            flex: 1;
            min-width: 100px;
            padding: 0.8rem;
            background: var(--surface-dark);
            border: 1px solid var(--text-secondary);
            border-radius: 4px;
            color: var(--text-primary);
            cursor: pointer;
        }

        .converter-result-label {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .conversion-details {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
        }

        .conversion-details div {
            margin: 0.3rem 0;
        }

        .converter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
            min-width: 150px;
        }

        .converter-equals {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.8rem 1.5rem;
            cursor: default;
            margin: 0 1rem;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .conversion-summary {
            text-align: center;
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            font-size: 1.1rem;
            color: var(--primary-color);
            font-weight: 500;
        }

        /* Footer - Même largeur que le tableau */
        .footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin: 40px auto;
            padding-top: 50px;
            width: 85%;
            max-width: 1800px;
            border-top: 1px solid #333;
            gap: 30px;
            overflow-x: auto;
        }
        
        .footer-section {
            flex: 1;
            min-width: 250px;
        }
        
        .footer-header {
            font-size: 20px;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 20px;
        }
        
        .footer-logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 15px;
        }
        
        .footer-description {
            line-height: 1.6;
            color: #b0b0b0;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .social-link {
            color: var(--text-secondary);
            transition: color 0.3s ease;
        }

        .social-link:hover {
            color: var(--primary-color);
        }
        
        .footer-links {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .footer-link {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background-color: #1e1e1e;
            border-radius: 4px;
            text-decoration: none;
            color: #e0e0e0;
            transition: background-color 0.3s;
        }
        
        .footer-link:hover {
            background-color: #2d2d2d;
        }
        
        .footer-link img {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            border-radius: 4px;
        }

        .payment-methods {
            display: flex;
            justify-content: center;
            margin-top: 25px;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .payment-method img {
            width: 80px;
            height: 40px;
            object-fit: contain;
            width: 60%;
        }

        

        /* Status indicator */
        .status-indicator {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            z-index: 1000;
            margin-right: 235px;
        }

        .status-connected {
            background: rgba(81, 207, 102, 0.2);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .status-disconnected {
            background: rgba(255, 135, 135, 0.2);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid var(--grid-color);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 1.5rem 0;
            gap: 1rem;
        }

        .pagination-info {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .pagination-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #3a7bc8;
        }

        .pagination-btn:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
        }

        .pagination-pages {
            display: flex;
            gap: 0.5rem;
        }

        .page-btn {
            background: var(--surface-dark);
            color: var(--text-primary);
            border: 1px solid var(--text-secondary);
            width: 32px;
            height: 32px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .header, .slider-container, .search-container, 
            .table-wrapper, .converter-section, .footer {
                width: 90%;
            }
            
            .slider-container {
                height: 300px;
            }

            .status-indicator {
                margin-right: 5%;
            }
        }

        @media (max-width: 992px) {
            .header, .slider-container, .search-container, 
            .table-wrapper, .converter-section, .footer {
                width: 95%;
            }
            
            .status-indicator {
                position: relative;
                top: auto;
                right: auto;
                margin: 1rem auto;
                width: fit-content;
            }
            
            .slider-container {
                height: 250px;
                margin-top: 100px;
            }
            
            .footer {
                flex-direction: column;
                gap: 2rem;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .logo {
                font-size: 1.8rem;
            }
            
            .slider-container {
                height: 200px;
                margin: 0.5rem auto;
                margin-top: 100px;
            }
            
            .currency-table {
                font-size: 0.9rem;
            }
            
            .currency-table th, .currency-table td {
                padding: 0.8rem 0.5rem;
            }
            
            .currency-header img {
                width: 28px;
                height: 28px;
            }
            
            .converter-form {
                flex-direction: column;
                gap: 0.8rem;
            }
            
            .converter-input, .converter-select {
                width: 100%;
                max-width: 300px;
            }
            
            .footer {
                margin: 20px auto;
                padding-top: 30px;
                gap: 20px;
            }
            
            .footer-header {
                font-size: 18px;
            }
            
            .footer-links {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .header, .slider-container, .search-container, 
            .table-wrapper, .converter-section, .footer {
                width: 100%;
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .slider-container {
                height: 150px;
                border-radius: 0;
            }
            
            .search-input-wrapper {
                width: 100%;
            }
            
            .table-wrapper {
                overflow-x: auto;
            }
            
            .currency-table {
                min-width: 800px;
            }
            
            .converter-section {
                padding: 1.5rem;
            }
            
            .converter-title {
                font-size: 1.3rem;
                margin-bottom: 1.5rem;
            }
            
            .footer {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .footer-section {
                min-width: 100%;
            }
            
            .pagination {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .pagination-pages {
                order: 3;
                width: 100%;
                justify-content: center;
            }
        }
        /* Animation pour les mises à jour de prix */
.price-cell.price-update {
  animation: pricePulse 1s ease-in-out;
  background: rgba(247, 166, 79, 0.1);
}

@keyframes pricePulse {
  0% { background-color: transparent; }
  50% { background-color: rgba(247, 166, 79, 0.3); }
  100% { background-color: transparent; }
}

.positive.price-update {
  animation: positivePulse 1s ease-in-out;
}

@keyframes positivePulse {
  0% { background-color: rgba(34, 136, 34, 0.1); }
  50% { background-color: rgba(34, 136, 34, 0.3); }
  100% { background-color: rgba(34, 136, 34, 0.1); }
}

.negative.price-update {
  animation: negativePulse 1s ease-in-out;
}

@keyframes negativePulse {
  0% { background-color: rgba(187, 68, 68, 0.1); }
  50% { background-color: rgba(187, 68, 68, 0.3); }
  100% { background-color: rgba(187, 68, 68, 0.1); }
}

/* Indicateur de connexion temps réel */
.status-connected::before {
  content: "🔄 ";
}

.status-disconnected::before {
  content: "⚠️ ";
}
/* Assurer que les cellules du tableau ont assez d'espace */
.currency-table td {
    padding: 0.5rem 0.8rem;
    border-bottom: 1px solid rgba(64,64,64,0.3);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 80px; /* Largeur minimale pour chaque cellule */
}

/* Colonnes spécifiques */
.currency-table td:nth-child(3) { /* Prix */
    min-width: 120px;
    text-align: right;
}

.currency-table td:nth-child(4) { /* Volume */
    min-width: 100px;
    text-align: right;
}

.currency-table td:nth-child(5), /* 15min */
.currency-table td:nth-child(6), /* 1h */
.currency-table td:nth-child(7), /* 24h */
.currency-table td:nth-child(8), /* 7d */
.currency-table td:nth-child(9), /* 30d */
.currency-table td:nth-child(10) { /* 1y */
    min-width: 70px;
    text-align: center;
}

/* Assurer que le tableau a assez d'espace */
.table-container {
    min-width: 1400px; /* Augmenter la largeur minimale */
}

/* Animation pour toutes les mises à jour */
.price-update {
    animation: pricePulse 1s ease-in-out;
}

@keyframes pricePulse {
    0% { background-color: transparent; }
    50% { background-color: rgba(247, 166, 79, 0.2); }
    100% { background-color: transparent; }
}

.positive.price-update {
    animation: positivePulse 1s ease-in-out;
}

@keyframes positivePulse {
    0% { background-color: rgba(34, 136, 34, 0.1); }
    50% { background-color: rgba(34, 136, 34, 0.3); }
    100% { background-color: rgba(34, 136, 34, 0.1); }
}

.negative.price-update {
    animation: negativePulse 1s ease-in-out;
}

@keyframes negativePulse {
    0% { background-color: rgba(187, 68, 68, 0.1); }
    50% { background-color: rgba(187, 68, 68, 0.3); }
    100% { background-color: rgba(187, 68, 68, 0.1); }
}

/* Header simplifié */


.header-left,
.header-center,
.header-right {
    flex: 1;
    display: flex;
    align-items: center;
}

.header-center {
    justify-content: center;
}

.header-right {
    justify-content: flex-end;
}

/* Avatar déclencheur */
.user-avatar-trigger {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--primary-color), #ff8c42);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.1rem;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    position: relative;
    z-index: 101;
}

.user-avatar-trigger:hover {
    transform: scale(1.1);
    border-color: rgba(255, 255, 255, 0.3);
    box-shadow: 0 0 20px rgba(247, 166, 79, 0.4);
}

/* Modal Profil */
.profile-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.profile-modal.active {
    opacity: 1;
    visibility: visible;
}

.modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.9);
    background: var(--surface-dark);
    border-radius: 16px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    overflow: hidden;
    transition: transform 0.3s ease;
}

.profile-modal.active .modal-content {
    transform: translate(-50%, -50%) scale(1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.2);
}

.modal-header h3 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.3rem;
}

.close-modal {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.close-modal:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.modal-body {
    padding: 2rem;
}

.profile-avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--primary-color), #ff8c42);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.8rem;
    color: white;
    margin: 0 auto 2rem;
    border: 3px solid rgba(255, 255, 255, 0.2);
}

.profile-info {
    margin-bottom: 2rem;
}

.info-group {
    margin-bottom: 1.5rem;
}

.info-group label {
    display: block;
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.info-group p {
    margin: 0;
    color: var(--text-primary);
    font-size: 1rem;
    padding: 0.8rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-actions {
    text-align: center;
}

.logout-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.8rem;
    padding: 1rem 2rem;
    background: rgba(187, 68, 68, 0.2);
    color: var(--error-color);
    text-decoration: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 1px solid rgba(187, 68, 68, 0.3);
}

.logout-btn:hover {
    background: rgba(187, 68, 68, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(187, 68, 68, 0.2);
}

/* Animation d'entrée */
@keyframes modalEnter {
    from {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.8);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .header {
        padding: 1rem;
    }
    
    .header-center {
        order: 3;
        width: 100%;
        margin-top: 1rem;
        justify-content: flex-start;
    }
    
    .user-avatar-trigger {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .modal-content {
        width: 95%;
        max-width: 350px;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .user-avatar-trigger {
        width: 38px;
        height: 38px;
        font-size: 0.9rem;
    }
    
    .modal-content {
        max-width: 320px;
    }
    
    .modal-header {
        padding: 1.2rem 1.5rem;
    }
    
    .modal-body {
        padding: 1.2rem;
    }
    
    .profile-avatar-large {
        width: 70px;
        height: 70px;
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
    }
}/* Pop-up de soutien - CENTRÉE */
.support-popup {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.8);
    width: 400px;
    max-width: 90vw;
    background: var(--surface-dark);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.1);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.support-popup.show {
    transform: translate(-50%, -50%) scale(1);
    opacity: 1;
    visibility: visible;
}

.popup-content {
    padding: 2rem;
    position: relative;
    text-align: center;
}

.popup-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
    z-index: 10;
}

.popup-close:hover {
    background: rgba(255, 255, 255, 0.2);
    color: var(--text-primary);
    transform: rotate(90deg);
}

.popup-content h3 {
    margin-bottom: 1rem;
    color: var(--primary-color);
    font-size: 1.5rem;
    font-weight: 600;
}

.popup-content p {
    margin-bottom: 1.5rem;
    color: var(--text-secondary);
    line-height: 1.6;
    font-size: 1rem;
}

.popup-payment-methods {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.popup-payment-method {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
    min-height: 100px;
}

.popup-payment-method:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    border-color: var(--primary-color);
}

.popup-payment-method img {
    width: 45px;
    height: 45px;
    object-fit: contain;
    margin-bottom: 0.8rem;
    border-radius: 8px;
}

.popup-payment-method span {
    font-size: 0.85rem;
    text-align: center;
    font-weight: 500;
}

.popup-note {
    text-align: center;
    font-size: 0.95rem;
    color: var(--primary-color);
    font-weight: 500;
    margin-bottom: 0;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Overlay d'arrière-plan */
.popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.popup-overlay.show {
    opacity: 1;
    visibility: visible;
}

/* Animation d'entrée */
@keyframes popupCenter {
    0% { 
        transform: translate(-50%, -50%) scale(0.7); 
        opacity: 0; 
    }
    70% { 
        transform: translate(-50%, -50%) scale(1.05); 
        opacity: 1; 
    }
    100% { 
        transform: translate(-50%, -50%) scale(1); 
        opacity: 1; 
    }
}

.support-popup.show {
    animation: popupCenter 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

/* Responsive */
@media (max-width: 768px) {
    .support-popup {
        width: 350px;
    }
    
    .popup-content {
        padding: 1.5rem;
    }
    
    .popup-payment-methods {
        grid-template-columns: 1fr;
        gap: 0.8rem;
    }
    
    .popup-payment-method {
        min-height: 80px;
        padding: 0.8rem;
    }
    
    .popup-payment-method img {
        width: 35px;
        height: 35px;
    }
}

@media (max-width: 480px) {
    .support-popup {
        width: 320px;
    }
    
    .popup-content {
        padding: 1.2rem;
    }
    
    .popup-content h3 {
        font-size: 1.3rem;
    }
    
    .popup-content p {
        font-size: 0.9rem;
    }
}
    </style>
</head>
<body>
    

    <!-- Header -->
    <!-- Header -->
<header class="header">
    <div class="header-left">
        <div class="logo" onclick="location.reload()">FLUX.IO</div>
    </div>
    
    <div class="header-right" style="margin-left:800px;">
        <select class="market-selector" id="marketSelector" aria-label="Sélectionnez un marché">
            <option value="all">Tous les marchés</option>
            <option value="favorites">Favoris</option>
            <optgroup label="Bourses">
                <option value="JSE">JSE</option>
                <option value="EGX">EGX</option>
                <option value="NGX">NGX</option>
                <option value="MASI">MASI</option>
                <option value="NSE">NSE</option>
                <option value="BRVM">BRVM</option>
            </optgroup>
        </select>
    </div>
    
    <div class="header-right">
        <div class="user-avatar-trigger" id="userAvatarTrigger">
            <?php 
            // Générer l'initiale du nom
            $initial = '';
            if (isset($_SESSION['user_name'])) {
                $initial = strtoupper(substr(trim($_SESSION['user_name']), 0, 1));
            }
            echo $initial ?: 'U';
            ?>
        </div>
    </div>
</header>

<!-- Modal Profil -->
<div class="profile-modal" id="profileModal">
    <div class="modal-backdrop" id="modalBackdrop"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mon Profil</h3>
            <button class="close-modal" id="closeModal">&times;</button>
        </div>
        
        <div class="modal-body">
            <div class="profile-avatar-large">
                <?php echo $initial ?: 'U'; ?>
            </div>
            
            <div class="profile-info">
                <div class="info-group">
                    <label>Nom complet</label>
                    <p><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Non renseigné'); ?></p>
                </div>
                
                <div class="info-group">
                    <label>Adresse email</label>
                    <p><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Non renseigné'); ?></p>
                </div>
                
                <div class="info-group">
                    <label>Membre depuis</label>
                    <p><?php 
                    if (isset($_SESSION['user_created_at'])) {
                        echo date('d/m/Y', strtotime($_SESSION['user_created_at']));
                    } else {
                        echo date('d/m/Y');
                    }
                    ?></p>
                </div>
            </div>
            
            <div class="modal-actions">
                <a href="php/logout.php" class="logout-btn">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                        <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                    </svg>
                    Se déconnecter
                </a>
            </div>
        </div>
    </div>
</div>
    <!-- Slider publicitaire -->
    <section class="slider-container">
        <div class="slider">
            <div class="slide active">
                <img src="./IMAGE/pub (2).jpg" alt="Publicité 1">
            </div>
            <div class="slide">
                <img src="./IMAGE/pub.jpg" alt="Publicité 2">
            </div>
            <div class="slide">
                <img src="./IMAGE/pub 4.png" alt="Publicité 3">
            </div>
            <div class="slide">
                <img src="./IMAGE/pub 5.png" alt="Publicité 4">
            </div>
        </div>
        <div class="slider-controls">
            <div class="slider-dot active" data-slide="0"></div>
            <div class="slider-dot" data-slide="1"></div>
            <div class="slider-dot" data-slide="2"></div>
            <div class="slider-dot" data-slide="3"></div>
        </div>
    </section>

    <!-- Search -->
    <section class="search-container">
        <div class="search-input-wrapper">
            <input type="text" class="search-input" id="searchInput" placeholder="Rechercher une action...">
            <svg class="search-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </svg>
        </div>
    </section>

    <!-- Stocks Table -->
    <div class="table-wrapper">
        <section class="table-container">
            <table class="currency-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="rank">#</th>
                        <th class="sortable" data-sort="name">Nom</th>
                        <th class="sortable" data-sort="price">Prix</th>
                        <th class="sortable" data-sort="marketCap">Capitalisation</th>
                        <th class="sortable" data-sort="volume">24h Volume</th>
                        <th class="sortable" data-sort="fifteenMin">15min</th>
                        <th class="sortable" data-sort="hour">Heure</th>
                        <th class="sortable" data-sort="day">Jour</th>
                        <th class="sortable" data-sort="week">Semaine</th>
                        <th class="sortable" data-sort="month">Mois</th>
                        <th class="sortable" data-sort="year">Année</th>
                        <th>Liens SGI</th>
                    </tr>
                </thead>
                <tbody id="stocksTableBody">
                    <!-- Le contenu sera généré dynamiquement par JavaScript -->
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="pagination" id="paginationControls">
                <button class="pagination-btn" id="prevPage" disabled>Précédent</button>
                
                <div class="pagination-pages" id="pageNumbers">
                    <!-- Les numéros de page seront générés ici -->
                </div>
                
                <button class="pagination-btn" id="nextPage">Suivant</button>
                
                <span class="pagination-info" id="paginationInfo">Page 1 sur 5</span>
            </div>
        </section>
    </div>

    <!-- Converter -->
    <section class="converter-section">
        <h2 class="converter-title">Convertisseur d'Actions</h2>
        
        <!-- Sélecteur d'action principal -->
        <div class="converter-form" style="margin-bottom: 2rem;">
            <select class="converter-select" id="actionSelector" aria-label="Sélectionner une action">
                <option value="">Sélectionner une action...</option>
            </select>
        </div>
        
        <!-- Zone de conversion -->
        <div class="converter-form">
            <!-- Devise source -->
            <div class="converter-group">
                <select class="converter-select" id="fromCurrency" aria-label="Sélectionner la devise source" title="Sélectionner la devise source">
                    <option value="FCFA">FCFA</option>
                    <option value="EUR">Euro (€)</option>
                    <option value="USD">Dollar US ($)</option>
                    <option value="XOF">XOF</option>
                    <option value="XAF">XAF</option>
                </select>
                <input type="number" class="converter-input" id="fromAmount" placeholder="Montant à investir" min="0" step="0.01">
            </div>
            
            <!-- Signe égal -->
            <div class="converter-equals">=</div>
            
            <!-- Résultat en nombre d'actions -->
            <div class="converter-group">
                <div class="converter-result-label">Nombre d'actions</div>
                <input type="number" class="converter-input" id="toAmount" placeholder="Résultat" style="text-align: center; font-weight: bold;">
            </div>
        </div>
        
        <!-- Résumé de conversion -->
        <div id="conversionSummary" class="conversion-summary">
            <!-- Le texte de résumé sera généré ici -->
        </div>
        
        <!-- Informations détaillées -->
        <div id="conversionDetails" class="conversion-details" style="text-align: center; margin-top: 1rem; color: var(--text-secondary); font-size: 0.9rem;">
            <div>Prix de l'action: <span id="currentActionPrice">-</span> FCFA</div>
            <div>Valeur totale: <span id="totalValue">-</span> <span id="valueCurrency">FCFA</span></div>
            <div>Reste après achat: <span id="remainingAmount">-</span> <span id="remainingCurrency">FCFA</span></div>
        </div>
    </section>



    <!-- Pop-up de soutien -->
<div class="support-popup" id="supportPopup">
    <div class="popup-content">
        <button class="popup-close" id="popupClose">&times;</button>
        <h3>Soutenez notre travail ❤️</h3>
        <p>Votre soutien financier est essentiel pour nous permettre de mettre à jour nos données.</p>
        <p>Nous sommes convaincus que l'afrique mérite une plateforme boursière unifiées adapté à ses besoins: simples, mobile et accessible à tous!
        </p>        <p>Agir maintenant c'est promouvoir l'innovation et le développement économique de l'Afrique.
        </p>       <p>L'équipe FLUX.IO</p>
        <div class="popup-payment-methods">
            <a href="https://www.orange-money.com" target="_blank" rel="noopener" class="popup-payment-method">
                <img src="IMAGES/orange.jpg" alt="Orange Money">
                <span>Orange Money</span>
            </a>
            <a href="https://www.mtn.com/momoney" target="_blank" rel="noopener" class="popup-payment-method">
                <img src="IMAGE/momo.png" alt="MTN MoMo">
                <span>MTN MoMo</span>
            </a>
            <a href="https://www.wave.com" target="_blank" rel="noopener" class="popup-payment-method">
                <img src="IMAGES/wave.png" alt="Wave">
                <span>Wave</span>
            </a>
            <a href="https://www.moov-africa.ci/money" target="_blank" rel="noopener" class="popup-payment-method">
                <img src="IMAGES/moov.png" alt="Moov Money">
                <span>Moov Money</span>
            </a>
        </div>
        <p class="popup-note">Merci de votre soutien !</p>
    </div>
</div>

    <!-- Footer -->
    <footer class="footer">
        <!-- Section gauche -->
        <div class="footer-section">
            <div class="logo">FLUX.IO</div>
            <div class="footer-description">
                FLUX.IO - Plateforme boursière africaine de référence pour suivre les marchés financiers 
                de la BRVM et d'autres places boursières africaines en temps réel.
            </div>
            
            <!-- Ajout des réseaux sociaux -->
            <div class="social-links" style="margin-top: 20px;">
                <a href="https://www.facebook.com" class="social-link" aria-label="Facebook" style="margin-right: 15px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                </a>
                <a href="https://www.twitter.com" class="social-link" aria-label="Twitter" style="margin-right: 15px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                    </svg>
                </a>
                <a href="https://www.linkedin.com" class="social-link" aria-label="LinkedIn" style="margin-right: 15px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                    </svg>
                </a>
                <a href="https://www.instagram.com" class="social-link" aria-label="Instagram">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                </a>
            </div>
        </div>
        
        <!-- Section milieu -->
        <div class="footer-section">
            <div class="footer-header">Liens SGI</div>
            <div class="footer-links">
                <a href="https://www.bni.ci" class="footer-link">
                    <img src="./IMAGE/bni.png" alt="BNI">
                    <span>BNI</span>
                </a>
                <a href="https://www.sgci.ci" class="footer-link">
                    <img src="./IMAGE/sg.jpg" alt="SG">
                    <span>Société Générale</span>
                </a>
                <a href="https://www.ecobank.com" class="footer-link">
                    <img src="./IMAGE/ecobank.jpg" alt="Ecobank">
                    <span>Ecobank</span>
                </a>
                <a href="https://www.boa.com" class="footer-link">
                    <img src="./IMAGE/boa.jpg" alt="BOA">
                    <span>Bank of Africa</span>
                </a>
                <a href="https://www.nsiabanque.ci" class="footer-link">
                    <img src="./IMAGE/nsia.png" alt="NSIA">
                    <span>NSIA Bank</span>
                </a>
                <a href="https://www.coris.bank" class="footer-link">
                    <img src="./IMAGE/coris bq.jpg" alt="Coris">
                    <span>Coris Bank</span>
                </a>
            </div>
        </div>
        
        <!-- Section droite -->
        <div class="footer-section">
            <div class="footer-header">Soutenir notre travail</div>
            <div class="footer-description">
                Votre soutien nous aide à maintenir et améliorer cette plateforme.
                Faites un don via l'un de ces services:
            </div>
            <div class="payment-methods">
                <a href="https://www.orange-money.com" target="_blank" rel="noopener" class="payment-method">
                    <img src="IMAGES/orange.jpg" alt="Orange Money">
                </a>
                <a href="https://www.mtn.com/momoney" target="_blank" rel="noopener" class="payment-method">
                    <img src="IMAGE/momo.png" alt="MTN MoMo">
                </a>
                <a href="https://www.wave.com" target="_blank"rel="noopener" class="payment-method">
                    <img src="IMAGES/wave.png" alt="Wave">
                </a>
                <a href="https://www.moov-africa.ci/money" target="_blank" rel="noopener"class="payment-method">
                    <img src="IMAGES/moov.png" alt="Moov Money">
                </a>
            </div>
        </div>
    </footer>

    <script>
// Configuration WebSocket
const WS_URL = 'wss://ws.postman-echo.com/raw';

// Application state
const AppState = {
    stocks: [],
    favorites: JSON.parse(localStorage.getItem('favorites') || '[]'),
    currentView: 'overview',
    sortColumn: 'rank',
    sortDirection: 'asc',
    isConverting: false,
    searchQuery: '',
    selectedMarket: 'all',
    websocket: null,
    isConnected: false,
    currentPage: 1,
    itemsPerPage: 10,
    totalPages: 1
};

// Service WebSocket avec valeurs nulles figées
class WebSocketService {
    constructor() {
        this.socket = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 3;
        this.reconnectInterval = 3000;
        this.simulationIntervals = {};
        console.log('🔧 WebSocketService initialisé');
    }

    connect() {
        try {
            console.log('🔄 Connexion WebSocket vers service public...');
            
            if (this.socket) {
                this.socket.close();
            }

            this.socket = new WebSocket(WS_URL);
            
            this.socket.onopen = () => {
                console.log('✅ Connecté au service WebSocket public');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.updateConnectionStatus(true);
                
                this.startDataSimulation();
            };

            this.socket.onmessage = (event) => {
                console.log('📨 Message reçu:', event.data);
            };

            this.socket.onclose = (event) => {
                console.log('❌ Déconnexion WebSocket');
                this.isConnected = false;
                this.updateConnectionStatus(false);
                this.stopAllSimulations();
                this.attemptReconnect();
            };

            this.socket.onerror = (error) => {
                console.error('❌ Erreur WebSocket:', error);
                this.isConnected = false;
                this.stopAllSimulations();
            };

        } catch (error) {
            console.error('❌ Erreur connexion WebSocket:', error);
            this.attemptReconnect();
        }
    }

    startDataSimulation() {
        console.log('🎮 Démarrage simulation données...');
        
        this.stopAllSimulations();
        
        // Intervalles différents pour chaque période
        const intervals = {
            price: 30000,        // 5 secondes
            marketCap: 30000,    // 5 secondes
            change15min: 30000,  // 5 secondes
            change1h: 30000,     // 5 secondes
            change24h: 30000,    // 5 secondes
            change7d: 30000,     // 6 secondes
            change30d: 30000,    // 7 secondes
            change1y: 35000,     // 9 secondes
            volume: 40000       // 10 secondes
        };
        
        // Démarrer les simulations
        Object.keys(intervals).forEach(period => {
            this.simulationIntervals[period] = setInterval(() => {
                this.simulatePeriodUpdate(period);
            }, intervals[period]);
            
            setTimeout(() => {
                this.simulatePeriodUpdate(period);
            }, 100);
        });
    }

    stopAllSimulations() {
        Object.values(this.simulationIntervals).forEach(interval => {
            if (interval) clearInterval(interval);
        });
        this.simulationIntervals = {};
    }

    simulatePeriodUpdate(period) {
        const allStocks = AppState.stocks;
        
        allStocks.forEach(stock => {
            if (period === 'price') {
                const priceChange = this.generatePriceChange(stock.price);
                const newPrice = Math.max(100, Math.floor(stock.price + priceChange));
                stock.price = newPrice;
                
                // Mettre à jour automatiquement la capitalisation quand le prix change
                stock.marketCap = this.calculateMarketCap(stock.symbol, newPrice);
            } else if (period === 'marketCap') {
                // Mettre à jour la capitalisation indépendamment
                stock.marketCap = this.generateMarketCap(stock.symbol, stock.price);
            } else if (period === 'volume') {
                stock.volume = this.generateRealisticVolume(stock.symbol);
            } else {
                // Pour les pourcentages, NE PAS mettre à jour si la valeur est nulle
                if (this.shouldUpdateValue(stock[period], period)) {
                    const newValue = this.generatePercentageChange(period, stock[period]);
                    stock[period] = newValue;
                }
            }
            
            this.updatePeriodDisplay(stock, period);
        });
        
        console.log(`🔄 Mise à jour ${period} pour ${allStocks.length} actions`);
    }

    shouldUpdateValue(currentValue, period) {
        // Ne pas mettre à jour les valeurs nulles (0.00 ou 0)
        if (currentValue === 0 || currentValue === 0.00 || currentValue === '0.00%') {
            return false;
        }
        
        // Pour les pourcentages, ne pas mettre à jour si c'est exactement 0.00
        if (typeof currentValue === 'number' && currentValue.toFixed(2) === '0.00') {
            return false;
        }
        
        return true;
    }

    generatePriceChange(currentPrice) {
        const percentageChange = (Math.random() - 0.5) * 4;
        return (currentPrice * percentageChange) / 100;
    }

    generatePercentageChange(period, currentValue) {
        const baseRanges = {
            change15min: { min: -2, max: 2, volatility: 0.3 },
            change1h: { min: -3, max: 3, volatility: 0.5 },
            change24h: { min: -8, max: 8, volatility: 1 },
            change7d: { min: -12, max: 12, volatility: 1.5 },
            change30d: { min: -20, max: 20, volatility: 2 },
            change1y: { min: -40, max: 40, volatility: 3 }
        };
        
        const range = baseRanges[period] || { min: -1, max: 1, volatility: 0.5 };
        const variation = (Math.random() - 0.5) * 2 * range.volatility;
        
        let newValue = currentValue + variation;
        newValue = Math.max(range.min, Math.min(range.max, newValue));
        
        // Éviter de créer de nouvelles valeurs nulles
        if (Math.abs(newValue) < 0.05 && currentValue !== 0) {
            newValue = currentValue > 0 ? 0.05 : -0.05;
        }
        
        return newValue;
    }

    calculateMarketCap(symbol, price) {
        // Capitalisations de base réalistes
        const baseMarketCaps = {
            'SVRC': '447.58M', 'BICC': '228.38M', 'BNBC': '67.55M', 'BOA': '44.58M',
            'BOABF': '228.38M', 'BOACI': '67.55M', 'BOAML': '557.34M', 'BOANG': '436.34M',
            'BOASN': '897.34M', 'SICOR': '447.58M', 'CB': '67.55M', 'CFAO': '228.38M',
            'CIEC': '67.55M', 'ECOC': '447.58M', 'ECOT': '228.38M', 'FLTS': '67.55M',
            'NCDC': '447.58M', 'NSIA': '228.38M', 'NEST': '67.55M', 'ONAT': '447.58M',
            'ORAG': '228.38M', 'PARL': '67.55M', 'TRAC': '447.58M', 'SAFC': '228.38M',
            'SUCR': '67.55M', 'SODE': '447.58M', 'BOLL': '228.38M', 'CRWN': '67.55M',
            'SOCG': '447.58M', 'VIVO': '228.38M', 'SIB': '67.55M', 'SICR': '447.58M',
            'AIRL': '228.38M', 'SOLI': '67.55M', 'SMB': '447.58M', 'SONA': '228.38M',
            'SOGB': '67.55M', 'SAPH': '447.58M', 'SETC': '228.38M', 'SITB': '67.55M',
            'MOVS': '447.58M', 'TOTC': '228.38M', 'TOTS': '67.55M', 'TRIT': '447.58M',
            'UNIL': '228.38M', 'UNIW': '67.55M'
        };
        
        const baseMarketCap = baseMarketCaps[symbol] || '100.00M';
        return this.applyMarketCapVariation(baseMarketCap, price);
    }

    generateMarketCap(symbol, price) {
        return this.calculateMarketCap(symbol, price);
    }

    applyMarketCapVariation(marketCapStr, currentPrice) {
        const match = marketCapStr.match(/([\d.]+)([MK])/);
        if (!match) return marketCapStr;
        
        const value = parseFloat(match[1]);
        const unit = match[2];
        
        const variation = (Math.random() - 0.5) * 0.2;
        const newValue = Math.max(1, value * (1 + variation));
        
        return `${newValue.toFixed(2)}${unit}`;
    }

    generateRealisticVolume(symbol) {
        // Volumes de base réalistes
        const baseVolumes = {
            'SVRC': '23.10M', 'BICC': '14.60M', 'BNBC': '41.43M', 'BOA': '23.10M',
            'BOABF': '14.60M', 'BOACI': '41.43M', 'BOAML': '41.43M', 'BOANG': '27.03M',
            'BOASN': '63.43M', 'SICOR': '23.10M', 'CB': '41.43M', 'CFAO': '14.60M',
            'CIEC': '41.43M', 'ECOC': '23.10M', 'ECOT': '14.60M', 'FLTS': '41.43M',
            'NCDC': '23.10M', 'NSIA': '14.60M', 'NEST': '41.43M', 'ONAT': '23.10M',
            'ORAG': '14.60M', 'PARL': '41.43M', 'TRAC': '23.10M', 'SAFC': '14.60M',
            'SUCR': '41.43M', 'SODE': '23.10M', 'BOLL': '14.60M', 'CRWN': '41.43M',
            'SOCG': '23.10M', 'VIVO': '14.60M', 'SIB': '41.43M', 'SICR': '23.10M',
            'AIRL': '14.60M', 'SOLI': '41.43M', 'SMB': '23.10M', 'SONA': '14.60M',
            'SOGB': '41.43M', 'SAPH': '23.10M', 'SETC': '14.60M', 'SITB': '41.43M',
            'MOVS': '23.10M', 'TOTC': '14.60M', 'TOTS': '41.43M', 'TRIT': '23.10M',
            'UNIL': '14.60M', 'UNIW': '41.43M'
        };
        
        const baseVolume = baseVolumes[symbol] || '20.00M';
        return this.applyVolumeVariation(baseVolume);
    }

    applyVolumeVariation(volumeStr) {
        const match = volumeStr.match(/([\d.]+)([MK])/);
        if (!match) return volumeStr;
        
        const value = parseFloat(match[1]);
        const unit = match[2];
        
        const variation = (Math.random() - 0.5) * 0.3;
        const newValue = Math.max(0.1, value * (1 + variation));
        
        return `${newValue.toFixed(2)}${unit}`;
    }

    updatePeriodDisplay(stock, period) {
        const row = document.querySelector(`tr[data-symbol="${stock.symbol}"]`);
        if (!row) return;

        const periodMap = {
            price: { 
                index: 3, 
                formatter: (value) => this.formatPrice(value) + ' FCFA', 
                isPercent: false 
            },
            marketCap: { 
                index: 4, 
                formatter: (value) => value,
                isPercent: false 
            },
            volume: { 
                index: 5, 
                formatter: (value) => value,
                isPercent: false 
            },
            change15min: { 
                index: 6, 
                formatter: (value) => this.formatChange(value), 
                isPercent: true 
            },
            change1h: { 
                index: 7, 
                formatter: (value) => this.formatChange(value), 
                isPercent: true 
            },
            change24h: { 
                index: 8, 
                formatter: (value) => this.formatChange(value), 
                isPercent: true 
            },
            change7d: { 
                index: 9, 
                formatter: (value) => this.formatChange(value), 
                isPercent: true 
            },
            change30d: { 
                index: 10, 
                formatter: (value) => this.formatChange(value), 
                isPercent: true 
            },
            change1y: { 
                index: 11, 
                formatter: (value) => this.formatChange(value), 
                isPercent: true 
            }
        };

        const periodInfo = periodMap[period];
        if (!periodInfo) return;

        const cell = row.querySelector(`td:nth-child(${periodInfo.index})`);
        if (cell) {
            const displayValue = periodInfo.formatter(stock[period]);
            
            // Ne pas animer les cellules qui n'ont pas changé (valeurs nulles)
            const shouldAnimate = this.shouldUpdateValue(stock[period], period);
            
            cell.textContent = displayValue;
            
            if (periodInfo.isPercent) {
                cell.className = `center ${this.getChangeClass(stock[period])}`;
                if (shouldAnimate) {
                    cell.classList.add('price-update');
                }
            } else {
                if (shouldAnimate) {
                    cell.classList.add('price-update');
                }
            }
            
            if (shouldAnimate) {
                setTimeout(() => {
                    cell.classList.remove('price-update');
                }, 1000);
            }
        }

        this.updateConverterIfNeeded(stock.symbol);
    }

    updateConverterIfNeeded(symbol) {
        const actionSelector = document.getElementById('actionSelector');
        if (actionSelector && actionSelector.value === symbol) {
            const event = new Event('input', { bubbles: true });
            const fromAmount = document.getElementById('fromAmount');
            if (fromAmount) fromAmount.dispatchEvent(event);
        }
    }

    formatPrice(price) {
        return new Intl.NumberFormat('fr-FR').format(Math.floor(price));
    }

    formatChange(change) {
        if (change === 0) return '0.00%';
        return change > 0 ? `+${change.toFixed(2)}%` : `${change.toFixed(2)}%`;
    }

    getChangeClass(change) {
        if (change > 0) return 'positive';
        if (change < 0) return 'negative';
        return '';
    }

updateConnectionStatus(connected) {
    console.log('🔌 Statut connexion:', connected);
    
    let statusElement = document.getElementById('connection-status');
    if (!statusElement) {
        statusElement = document.createElement('div');
        statusElement.id = 'connection-status';
        statusElement.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            z-index: 1000;
            background: rgba(0,0,0,0.9);
            color: white;
            border: 2px solid;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            backdrop-filter: blur(10px);
        `;
        document.body.appendChild(statusElement);
    }

    // Update displayed text and classes based on connection state
    statusElement.textContent = connected ? 'Connecté' : 'Déconnecté';
    // Use the CSS utility classes defined in the stylesheet for visual state
    statusElement.className = connected ? 'status-indicator status-connected' : 'status-indicator status-disconnected';
    statusElement.style.display = 'block';
}

attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = this.reconnectInterval * this.reconnectAttempts;
            console.log(`🔄 Reconnexion ${this.reconnectAttempts}/${this.maxReconnectAttempts} dans ${delay}ms`);
            
            setTimeout(() => {
                if (!this.isConnected) {
                    this.connect();
                }
            }, delay);
        } else {
            console.log('🔶 Passage en mode simulation locale');
            this.updateConnectionStatus(false);
            this.startDataSimulation();
        }
    }

    disconnect() {
        this.stopAllSimulations();
        if (this.socket) {
            this.socket.close();
            this.socket = null;
        }
    }
}

// Initialisation WebSocket
AppState.websocketService = new WebSocketService();

// Démarrer après le chargement complet
window.addEventListener('load', () => {
    setTimeout(() => {
        AppState.websocketService.connect();
    }, 1000);
});
// Fonctions de pagination
class Pagination {
    static updatePaginationControls() {
        const prevBtn = document.getElementById('prevPage');
        const nextBtn = document.getElementById('nextPage');
                const pageNumbers = document.getElementById('pageNumbers');
                const paginationInfo = document.getElementById('paginationInfo');
                
                prevBtn.disabled = AppState.currentPage === 1;
                nextBtn.disabled = AppState.currentPage === AppState.totalPages;
                
                paginationInfo.textContent = `Page ${AppState.currentPage} sur ${AppState.totalPages}`;
                
                pageNumbers.innerHTML = '';
                
                let startPage = Math.max(1, AppState.currentPage - 2);
                let endPage = Math.min(AppState.totalPages, startPage + 4);
                
                if (endPage - startPage < 4) {
                    startPage = Math.max(1, endPage - 4);
                }
                
                if (startPage > 1) {
                    const firstPageBtn = document.createElement('button');
                    firstPageBtn.className = 'page-btn';
                    firstPageBtn.textContent = '1';
                    firstPageBtn.addEventListener('click', () => UI.goToPage(1));
                    pageNumbers.appendChild(firstPageBtn);
                    
                    if (startPage > 2) {
                        const ellipsis = document.createElement('span');
                        ellipsis.textContent = '...';
                        ellipsis.style.color = 'var(--text-secondary)';
                        pageNumbers.appendChild(ellipsis);
                    }
                }
                
                for (let i = startPage; i <= endPage; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = `page-btn ${i === AppState.currentPage ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', () => UI.goToPage(i));
                    pageNumbers.appendChild(pageBtn);
                }
                
                if (endPage < AppState.totalPages) {
                    if (endPage < AppState.totalPages - 1) {
                        const ellipsis = document.createElement('span');
                        ellipsis.textContent = '...';
                        ellipsis.style.color = 'var(--text-secondary)';
                        pageNumbers.appendChild(ellipsis);
                    }
                    
                    const lastPageBtn = document.createElement('button');
                    lastPageBtn.className = 'page-btn';
                    lastPageBtn.textContent = AppState.totalPages;
                    lastPageBtn.addEventListener('click', () => UI.goToPage(AppState.totalPages));
                    pageNumbers.appendChild(lastPageBtn);
                }
            }
            
            static calculateTotalPages(filteredStocks) {
                return Math.max(1, Math.ceil(filteredStocks.length / AppState.itemsPerPage));
            }
            
            static getStocksForCurrentPage(filteredStocks) {
                const startIndex = (AppState.currentPage - 1) * AppState.itemsPerPage;
                const endIndex = startIndex + AppState.itemsPerPage;
                return filteredStocks.slice(startIndex, endIndex);
            }
        }

        // API Service
        class APIService {
            static async request(endpoint, options = {}) {
                try {
                    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
                        headers: {
                            'Content-Type': 'application/json',
                            ...options.headers
                        },
                        ...options
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    return await response.json();
                } catch (error) {
                    console.error('API request failed:', error);
                    throw error;
                }
            }

            static async getAssets() {
                return this.request('/assets');
            }

            static async getPriceHistory(symbol) {
                return this.request(`/assets/${symbol}/price`);
            }

            static async getMarketData() {
                return this.request('/market-data');
            }
        }

        // FONCTIONS:
        

        // UI Controller
        class UI {
            static init() {
                this.setupEventListeners();
                this.setupSlider();
                this.loadMockData();
            }

            static setupEventListeners() {
                document.getElementById('searchInput').addEventListener('input', (e) => {
                    AppState.searchQuery = e.target.value.toLowerCase();
                    this.filterAndRenderStocks();
                });

                document.getElementById('marketSelector').addEventListener('change', (e) => {
                    AppState.selectedMarket = e.target.value;
                    this.filterAndRenderStocks();
                });

                document.querySelectorAll('.sortable').forEach(th => {
                    th.addEventListener('click', (e) => {
                        const column = e.currentTarget.dataset.sort;
                        this.sortTable(column);
                    });
                });

                document.getElementById('actionSelector').addEventListener('change', this.updateConversion.bind(this));
                document.getElementById('fromAmount').addEventListener('input', this.updateConversion.bind(this));
                document.getElementById('fromCurrency').addEventListener('change', this.updateConversion.bind(this));
                
                // AJOUT: Écouteur pour le champ nombre d'actions
                document.getElementById('toAmount').addEventListener('input', this.updateConversionFromShares.bind(this));
                
                document.getElementById('prevPage').addEventListener('click', () => this.previousPage());
                document.getElementById('nextPage').addEventListener('click', () => this.nextPage());
            }

            static setupSlider() {
                let currentSlide = 0;
                const slides = document.querySelectorAll('.slide');
                const dots = document.querySelectorAll('.slider-dot');
                
                const showSlide = (index) => {
                    slides.forEach((slide, i) => {
                        slide.classList.toggle('active', i === index);
                    });
                    dots.forEach((dot, i) => {
                        dot.classList.toggle('active', i === index);
                    });
                };

                dots.forEach((dot, index) => {
                    dot.addEventListener('click', () => {
                        currentSlide = index;
                        showSlide(currentSlide);
                    });
                });

                setInterval(() => {
                    currentSlide = (currentSlide + 1) % slides.length;
                    showSlide(currentSlide);
                }, 5000);
            }

            static loadMockData() {
                // Mock data for demonstration
                AppState.stocks = [
                {
                    rank: 1, symbol: 'SVRC', name: 'Servair CI', price: 23403, 
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/sevair_Abidjan (2).png',
                    change15min: 0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 2, symbol: 'BICC', name: 'Bici CI', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/bicici.jpg',
                    change15min: -0.1, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 3, symbol: 'BNBC', name: 'Bernabé CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/bernabé.jpg',
                    change15min: 0.0, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 4, symbol: 'BOA', name: 'BOA BN', price: 23403,
                    marketCap: '44.58M', volume: '23.10M', logo: './IMAGE/boa.jpg',
                    change15min: 0.5, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 5, symbol: 'BOABF', name: 'BOA BF', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/boa.jpg',
                    change15min: -0.3, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 6, symbol: 'BOACI', name: 'BOA CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/boa.jpg',
                    change15min: 0.1, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 7, symbol: 'BOAML', name: 'BOA ML', price: 22500,
                    marketCap: '557.34M', volume: '41.43M', logo: './IMAGE/boa.jpg',
                    change15min: -0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 8, symbol: 'BOANG', name: 'BOA NG', price: 1740,
                    marketCap: '436.34M', volume: '27.03M', logo: './IMAGE/boa.jpg',
                    change15min: 0.4, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 9, symbol: 'BOASN', name: 'BOA SN', price: 3000,
                    marketCap: '897.34M', volume: '63.43M', logo: './IMAGE/boa.jpg',
                    change15min: -0.1, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 10, symbol: 'SICOR', name: 'Sicable CI', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/sicable.jpg',
                    change15min: 0.3, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 11, symbol: 'CB', name: 'CB Int BF', price: 2500,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/coris bq.jpg',
                    change15min: 0.0, change1h: 0, change24h: 0, change7d: 0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 12, symbol: 'CFAO', name: 'Cfao CI', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/cfao.jpg',
                    change15min: -0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 13, symbol: 'CIEC', name: 'Cie CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/cie ci.png',
                    change15min: 0.1, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 14, symbol: 'ECOC', name: 'Ecobank CI', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/ecobank.jpg',
                    change15min: -0.4, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 15, symbol: 'ECOT', name: 'Ecobank TG', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/ecobank.jpg',
                    change15min: 0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 16, symbol: 'FLTS', name: 'Filtisac CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/filtisac.jpg',
                    change15min: -0.1, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 17, symbol: 'NCDC', name: 'Nei-Ceda CI', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/nei-ceda.jpg',
                    change15min: 0.5, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 18, symbol: 'NSIA', name: 'Nsia Bk CI', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/nsia.png',
                    change15min: -0.3, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 19, symbol: 'NEST', name: 'Nestle CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/nestlé.jpg',
                    change15min: 0.0, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 20, symbol: 'ONAT', name: 'Onatel BF', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/onatel.png',
                    change15min: 0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 21, symbol: 'ORAG', name: 'Oragroup TG', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/oragroup.jpg',
                    change15min: 0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 22, symbol: 'PARL', name: 'Parl CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/parl.jpg',
                    change15min: 0.2, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 23, symbol: 'TRAC', name: 'Trac Motors CI', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/trac.jpg',
                    change15min: 0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 24, symbol: 'SAFC', name: 'Safca CI', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/saf.jpg',
                    change15min: 0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 25, symbol: 'SUCR', name: 'Sucrivoire CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/sucre.jpg',
                    change15min: 0.2, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 26, symbol: 'SODE', name: 'Sode CI', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/sodeci.png',
                    change15min: 0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 27, symbol: 'BOLL', name: 'Bolloré Ci', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/boloré.png',
                    change15min: 0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 28, symbol: 'CRWN', name: 'Crown Siem CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/crown siem.jpg',
                    change15min: 0.2, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 29, symbol: 'SOCG', name: 'Societé g CI', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/sg.jpg',
                    change15min: 0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 30, symbol: 'VIVO', name: 'Vivo Energie CI', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/vivo.jpg',
                    change15min: 0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 31, symbol: 'SIB', name: 'Sib CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/sib.png',
                    change15min: 0.2, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 32, symbol: 'SICR', name: 'Sicor CI', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/sicor.jpg',
                    change15min: 0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 33, symbol: 'AIRL', name: 'Air L. CI', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/air.jpg',
                    change15min: 0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 34, symbol: 'SOLI', name: 'Solibra CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/solibra.png',
                    change15min: 0.2, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 35, symbol: 'SMB', name: 'Smb CI', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/smb.png',
                    change15min: 0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 36, symbol: 'SONA', name: 'Sonatel SN', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/sonatel.jpg',
                    change15min: 0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 37, symbol: 'SOGB', name: 'Sogb CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/SOGB.png',
                    change15min: 0.2, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 38, symbol: 'SAPH', name: 'Saph CI', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/saph.jpg',
                    change15min: 0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 39, symbol: 'SETC', name: 'Setao ci', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/Setao.png',
                    change15min: 0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 40, symbol: 'SITB', name: 'Sitab CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/sitab.jpg',
                    change15min: 0.2, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 41, symbol: 'MOVS', name: 'Movis CI', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/movis-ci.png',
                    change15min: 0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 42, symbol: 'TOTC', name: 'Total CI', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/total.jpg',
                    change15min: 0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 43, symbol: 'TOTS', name: 'Total SN', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/total.jpg',
                    change15min: 0.2, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 44, symbol: 'TRIT', name: 'Trituraf ste', price: 23403,
                    marketCap: '447.58M', volume: '23.10M', logo: './IMAGE/brvm.png',
                    change15min: 0.2, change1h: -0.1, change24h: 0.3, change7d: -3, change30d: 6.3, change1y: -52
                },
                {
                    rank: 45, symbol: 'UNIL', name: 'Unilever CI', price: 1872,
                    marketCap: '228.38M', volume: '14.60M', logo: './IMAGE/unilever.png',
                    change15min: 0.2, change1h: -0.2, change24h: 2.1, change7d: -0.9, change30d: 21.2, change1y: -41.3
                },
                {
                    rank: 46, symbol: 'UNIW', name: 'Uniwax CI', price: 1000,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/uniwax.png',
                    change15min: 0.2, change1h: 0, change24h: 0, change7d: -0.1, change30d: 0, change1y: -0.1
                },
                {
                    rank: 47, symbol: 'ORAC', name: 'Orange CI', price: 14800,
                    marketCap: '67.55M', volume: '41.43M', logo: './IMAGE/orange.png',
                    change15min: 0.3, change1h: 0, change24h: 0, change7d: -0.2, change30d: 0, change1y: -0.2
                }
            
                    // Add more mock data as needed
                ];

                this.renderStocksTable();
                this.populateConverterOptions();
            }

      static getFilteredStocks() {
    let filtered = [...AppState.stocks];
    
    if (AppState.searchQuery) {
        filtered = filtered.filter(stock => 
            stock.name.toLowerCase().includes(AppState.searchQuery) ||
            stock.symbol.toLowerCase().includes(AppState.searchQuery)
        );
    }
    
    // Gestion des différents marchés
    if (AppState.selectedMarket === 'favorites') {
        filtered = filtered.filter(stock => AppState.favorites.includes(stock.symbol));
    } else if (AppState.selectedMarket === 'BRVM') {
        // BRVM affiche toutes les données (comme "Tous les marchés")
        // Pas de filtrage supplémentaire
    } else if (AppState.selectedMarket !== 'all') {
        // Pour les autres marchés (JSE, EGX, NGX, MASI, NSE)
        // On retourne un tableau vide pour afficher le message
        filtered = [];
    }
    
    // Tri
    filtered.sort((a, b) => {
        let aVal = a[AppState.sortColumn];
        let bVal = b[AppState.sortColumn];
        
        if (typeof aVal === 'string') {
            aVal = aVal.toLowerCase();
            bVal = bVal.toLowerCase();
        }
        
        if (AppState.sortDirection === 'asc') {
            return aVal > bVal ? 1 : -1;
        } else {
            return aVal < bVal ? 1 : -1;
        }
    });
    
    return filtered;
}

            static filterAndRenderStocks() {
                AppState.currentPage = 1;
                this.renderStocksTable();
            }

            static sortTable(column) {
                if (AppState.sortColumn === column) {
                    AppState.sortDirection = AppState.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    AppState.sortColumn = column;
                    AppState.sortDirection = 'asc';
                }
                this.renderStocksTable();
            }

            static toggleFavorite(symbol) {
                const index = AppState.favorites.indexOf(symbol);
                if (index > -1) {
                    AppState.favorites.splice(index, 1);
                } else {
                    AppState.favorites.push(symbol);
                }
                localStorage.setItem('favorites', JSON.stringify(AppState.favorites));
                this.renderStocksTable();
            }

            static updateConversion() {
                // Protection contre les boucles infinies
                if (AppState.isConverting) return;
                AppState.isConverting = true;
                
                try {
                    const actionSymbol = document.getElementById('actionSelector').value;
                    const fromAmount = parseFloat(document.getElementById('fromAmount').value) || 0;
                    const fromCurrency = document.getElementById('fromCurrency').value;
                    const conversionSummary = document.getElementById('conversionSummary');
                    
                    if (actionSymbol && fromAmount > 0) {
                        const action = AppState.stocks.find(s => s.symbol === actionSymbol);
                        if (action && action.price > 0) {
                            document.getElementById('currentActionPrice').textContent = this.formatPrice(action.price);
                            
                            const exchangeRates = {
                                'FCFA': 1,
                                'EUR': 655,
                                'USD': 600,
                                'XOF': 1,
                                'XAF': 1
                            };
                            
                            const amountInFCFA = fromAmount * (exchangeRates[fromCurrency] || 1);
                            const numberOfShares = Math.floor(amountInFCFA / action.price);
                            const totalValue = numberOfShares * action.price;
                            const remainingAmount = amountInFCFA - totalValue;
                            
                            document.getElementById('toAmount').value = numberOfShares;
                            document.getElementById('totalValue').textContent = this.formatPrice(totalValue);
                            document.getElementById('valueCurrency').textContent = 'FCFA';
                            document.getElementById('remainingAmount').textContent = this.formatPrice(remainingAmount);
                            document.getElementById('remainingCurrency').textContent = 'FCFA';
                            
                            conversionSummary.textContent = 
                                `Pour ${this.formatPrice(fromAmount)} ${fromCurrency} vous aurez ${numberOfShares.toLocaleString('fr-FR')} actions de ${action.name}`;
                            
                            const toAmountInput = document.getElementById('toAmount');
                            if (numberOfShares > 0) {
                                toAmountInput.style.color = 'var(--success-color)';
                                toAmountInput.style.borderColor = 'var(--success-color)';
                                conversionSummary.style.color = 'var(--success-color)';
                            } else {
                                toAmountInput.style.color = 'var(--error-color)';
                                toAmountInput.style.borderColor = 'var(--error-color)';
                                conversionSummary.style.color = 'var(--error-color)';
                            }
                        }
                    } else {
                        document.getElementById('toAmount').value = '';
                        document.getElementById('currentActionPrice').textContent = '-';
                        document.getElementById('totalValue').textContent = '-';
                        document.getElementById('remainingAmount').textContent = '-';
                        conversionSummary.textContent = '';
                        
                        const toAmountInput = document.getElementById('toAmount');
                        toAmountInput.style.color = 'var(--primary-color)';
                        toAmountInput.style.borderColor = 'var(--primary-color)';
                    }
                } finally {
                    // Réactiver la conversion après un court délai
                    setTimeout(() => {
                        AppState.isConverting = false;
                    }, 100);
                }
            }

            static updateConversionFromShares() {
                // Protection contre les boucles infinies
                if (AppState.isConverting) return;
                AppState.isConverting = true;
                
                try {
                    const actionSymbol = document.getElementById('actionSelector').value;
                    const toAmount = parseFloat(document.getElementById('toAmount').value) || 0;
                    const fromCurrency = document.getElementById('fromCurrency').value;
                    const conversionSummary = document.getElementById('conversionSummary');
                    
                    if (actionSymbol && toAmount > 0) {
                        const action = AppState.stocks.find(s => s.symbol === actionSymbol);
                        if (action && action.price > 0) {
                            document.getElementById('currentActionPrice').textContent = this.formatPrice(action.price);
                            
                            const exchangeRates = {
                                'FCFA': 1,
                                'EUR': 655,
                                'USD': 600,
                                'XOF': 1,
                                'XAF': 1
                            };
                            
                            // Calcul du montant nécessaire pour acheter le nombre d'actions
                            const amountInFCFA = toAmount * action.price;
                            const fromAmount = amountInFCFA / (exchangeRates[fromCurrency] || 1);
                            
                            document.getElementById('fromAmount').value = fromAmount.toFixed(2);
                            document.getElementById('totalValue').textContent = this.formatPrice(amountInFCFA);
                            document.getElementById('valueCurrency').textContent = 'FCFA';
                            document.getElementById('remainingAmount').textContent = '0';
                            document.getElementById('remainingCurrency').textContent = 'FCFA';
                            
                            conversionSummary.textContent = 
                                `Pour ${toAmount.toLocaleString('fr-FR')} actions de ${action.name}, vous avez besoin de ${this.formatPrice(fromAmount)} ${fromCurrency}`;
                            
                            const toAmountInput = document.getElementById('toAmount');
                            toAmountInput.style.color = 'var(--success-color)';
                            toAmountInput.style.borderColor = 'var(--success-color)';
                            conversionSummary.style.color = 'var(--success-color)';
                        }
                    } else {
                        document.getElementById('fromAmount').value = '';
                        document.getElementById('currentActionPrice').textContent = '-';
                        document.getElementById('totalValue').textContent = '-';
                        document.getElementById('remainingAmount').textContent = '-';
                        conversionSummary.textContent = '';
                        
                        const toAmountInput = document.getElementById('toAmount');
                        toAmountInput.style.color = 'var(--primary-color)';
                        toAmountInput.style.borderColor = 'var(--primary-color)';
                    }
                } finally {
                    // Réactiver la conversion après un court délai
                    setTimeout(() => {
                        AppState.isConverting = false;
                    }, 100);
                }
            }

            static formatPrice(price) {
                return new Intl.NumberFormat('fr-FR', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                }).format(price);
            }

            static formatChange(change) {
                return change > 0 ? `+${change.toFixed(1)}` : change.toFixed(1);
            }

            static getChangeClass(change) {
                if (change > 0) return 'positive';
                if (change < 0) return 'negative';
                return '';
            }

            static goToPage(page) {
                AppState.currentPage = page;
                this.renderStocksTable();
            }
            
            static previousPage() {
                if (AppState.currentPage > 1) {
                    AppState.currentPage--;
                    this.renderStocksTable();
                }
            }
            
            static nextPage() {
                if (AppState.currentPage < AppState.totalPages) {
                    AppState.currentPage++;
                    this.renderStocksTable();
                }
            }

            static populateConverterOptions() {
                const actionSelector = document.getElementById('actionSelector');
                const options = AppState.stocks.map(stock => 
                    `<option value="${stock.symbol}">${stock.name} (${stock.symbol}) - ${this.formatPrice(stock.price)} FCFA</option>`
                ).join('');
                
                actionSelector.innerHTML = '<option value="">Sélectionner une action...</option>' + options;
            }

            static redirectToStockPage(symbol) {
                const stock = AppState.stocks.find(s => s.symbol === symbol);
                
                if (stock) {
                    // Stocker les données dans le localStorage pour les récupérer dans ultimepage.html
                    localStorage.setItem('selectedStock', JSON.stringify(stock));

                    // Rediriger vers ultimepage.php
                    window.location.href = 'ultimepage.php';
                }
            }
        }

        // Modifier la fonction renderStocksTable pour inclure les données complètes
       UI.renderStocksTable = function() {
    const tbody = document.getElementById('stocksTableBody');
    const filteredStocks = this.getFilteredStocks();
    
    // Liste des marchés non disponibles
    const unavailableMarkets = ['JSE', 'EGX', 'NGX', 'MASI', 'NSE'];
    
    // Vérifier si on doit afficher le message d'avertissement
    if (unavailableMarkets.includes(AppState.selectedMarket) && filteredStocks.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="center" style="padding: 2rem; color: var(--text-secondary);">
                    <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">⚠️</div>
                    <div>Données en cours de mise à jour</div>
                    <div style="font-size: 0.9rem; margin-top: 0.5rem;">
                        Les données pour le marché ${AppState.selectedMarket} seront disponibles prochainement
                    </div>
                </td>
            </tr>
        `;
        
        // Masquer la pagination
        document.getElementById('paginationControls').style.display = 'none';
        return;
    }
    
    // Réafficher la pagination si elle était cachée
    document.getElementById('paginationControls').style.display = 'flex';
    
    AppState.totalPages = Pagination.calculateTotalPages(filteredStocks);
    
    if (AppState.currentPage > AppState.totalPages) {
        AppState.currentPage = AppState.totalPages;
    }
    
    const stocksToShow = Pagination.getStocksForCurrentPage(filteredStocks);
    
    tbody.innerHTML = stocksToShow.map(stock => `
        <tr data-symbol="${stock.symbol}">
            <td class="right">${stock.rank}</td>
            <td>
                <div class="name-cell">
                    <button class="favorite-btn ${AppState.favorites.includes(stock.symbol) ? 'active' : ''}" 
                            onclick="UI.toggleFavorite('${stock.symbol}')">
                        ★
                    </button>
                    <div class="currency-header">
                        <a class="ls-Noms" href="javascript:void(0)" onclick="UI.redirectToStockPage('${stock.symbol}')">
                            <img src="${stock.logo}" alt="${stock.name}" title="${stock.name}">
                            <span>${stock.name}</span>
                        </a>
                    </div>
                </div>
            </td>
            <td class="right">${this.formatPrice(stock.price)}&nbsp;FCFA</td>
            <td class="right">${stock.marketCap}&nbsp;FCFA</td>
            <td class="right volume">${stock.volume}&nbsp;FCFA</td>
            <td class="center ${this.getChangeClass(stock.change15min)}">${this.formatChange(stock.change15min)}%</td>
            <td class="center ${this.getChangeClass(stock.change1h)}">${this.formatChange(stock.change1h)}%</td>
            <td class="center ${this.getChangeClass(stock.change24h)}">${this.formatChange(stock.change24h)}%</td>
            <td class="center ${this.getChangeClass(stock.change7d)}">${this.formatChange(stock.change7d)}%</td>
            <td class="center ${this.getChangeClass(stock.change30d)}">${this.formatChange(stock.change30d)}%</td>
            <td class="center ${this.getChangeClass(stock.change1y)}">${this.formatChange(stock.change1y)}%</td>
            <td>
                <a href="#" title="BNI Finance" class="icon-button" target="_blank" rel="noopener">
                    <img src="./IMAGE/bni.png" width="24" height="24">
                </a>
                <a href="#" title="Mac Africa" class="icon-button" target="_blank" rel="noopener">
                    <img src="./IMAGE/mac (2).png" width="24" height="24">
                </a>
                <a href="#" title="Bici Bourse" class="icon-button" target="_blank" rel="noopener">
                    <img src="./IMAGE/bici.png" width="24" height="24">
                </a>
            </td>
        </tr>
    `).join('');
    
    Pagination.updatePaginationControls();
};
        // Application initialization
        document.addEventListener('DOMContentLoaded', () => {
            UI.init();
        });


        // Gestion de la modal de profil
document.addEventListener('DOMContentLoaded', function() {
    const userAvatarTrigger = document.getElementById('userAvatarTrigger');
    const profileModal = document.getElementById('profileModal');
    const modalBackdrop = document.getElementById('modalBackdrop');
    const closeModal = document.getElementById('closeModal');
    
    let hoverTimer;
    let isModalOpen = false;
    
    // Ouvrir la modal au survol (avec délai pour éviter les ouvertures accidentelles)
    userAvatarTrigger.addEventListener('mouseenter', function() {
        hoverTimer = setTimeout(() => {
            if (!isModalOpen) {
                openModal();
            }
        }, 300); // 300ms de délai
    });
    
    // Annuler l'ouverture si la souris quitte avant le délai
    userAvatarTrigger.addEventListener('mouseleave', function() {
        clearTimeout(hoverTimer);
    });
    
    // Ouvrir la modal au clic (pour mobile)
    userAvatarTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        openModal();
    });
    
    // Fermer la modal
    function closeModalFunc() {
        profileModal.classList.remove('active');
        isModalOpen = false;
        
        // Réactiver le scroll de la page
        document.body.style.overflow = 'auto';
    }
    
    function openModal() {
        profileModal.classList.add('active');
        isModalOpen = true;
        
        // Désactiver le scroll de la page
        document.body.style.overflow = 'hidden';
    }
    
    // Fermer en cliquant sur le backdrop
    modalBackdrop.addEventListener('click', closeModalFunc);
    
    // Fermer en cliquant sur la croix
    closeModal.addEventListener('click', closeModalFunc);
    
    // Fermer en appuyant sur Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isModalOpen) {
            closeModalFunc();
        }
    });
    
    // Empêcher la fermeture quand on clique dans le contenu de la modal
    profileModal.querySelector('.modal-content').addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    // Animation d'entrée au chargement si des données sont présentes
    if (document.querySelector('.profile-info p').textContent.trim() !== 'Non renseigné') {
        userAvatarTrigger.style.animation = 'pulse 2s infinite';
        
        // Ajouter le style pour l'animation de pulse
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { box-shadow: 0 0 0 0 rgba(247, 166, 79, 0.7); }
                70% { box-shadow: 0 0 0 10px rgba(247, 166, 79, 0); }
                100% { box-shadow: 0 0 0 0 rgba(247, 166, 79, 0); }
            }
        `;
        document.head.appendChild(style);
        
        // Arrêter l'animation après 6 secondes
        setTimeout(() => {
            userAvatarTrigger.style.animation = 'none';
        }, 6000);
    }
});

// Gestion de la pop-up de soutien CENTRÉE
class SupportPopup {
    constructor() {
        this.popup = document.getElementById('supportPopup');
        this.closeBtn = document.getElementById('popupClose');
        this.isVisible = false;
        this.timer = null;
        
        // Créer l'overlay
        this.createOverlay();
        this.init();
    }
    
    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'popup-overlay';
        document.body.appendChild(this.overlay);
        
        // Fermer la pop-up en cliquant sur l'overlay
        this.overlay.addEventListener('click', () => {
            this.hide();
            this.startTimer();
        });
    }
    
    init() {
        // Écouteur pour le bouton de fermeture
        this.closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.hide();
            this.startTimer();
        });
        
        // Empêcher la fermeture quand on clique dans le contenu
        this.popup.addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        // Démarrer le timer après le chargement de la page
        setTimeout(() => {
            this.startTimer();
        }, 10000); // Première apparition après 10 secondes
    }
    
    startTimer() {
        // Effacer le timer existant
        if (this.timer) {
            clearTimeout(this.timer);
        }
        
        // Démarrer un nouveau timer
        this.timer = setTimeout(() => {
            this.show();
        }, 30000); // 30 secondes
    }
    
    show() {
        if (!this.isVisible) {
            this.popup.classList.add('show');
            this.overlay.classList.add('show');
            
            // Désactiver le scroll de la page
            document.body.style.overflow = 'hidden';
            
            this.isVisible = true;
        }
    }
    
    hide() {
        if (this.isVisible) {
            this.popup.classList.remove('show');
            this.overlay.classList.remove('show');
            
            // Réactiver le scroll de la page
            document.body.style.overflow = 'auto';
            
            this.isVisible = false;
        }
    }
}

// Initialiser la pop-up de soutien après le chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    // Attendre que l'UI soit initialisée
    setTimeout(() => {
        new SupportPopup();
    }, 2000);
});
    </script>

  
</body>
</html>