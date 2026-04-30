-- Schema de la base de données FFTT Player Manager

CREATE DATABASE IF NOT EXISTS fftt_manager;
USE fftt_manager;

-- Table des clubs
CREATE TABLE IF NOT EXISTS clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    nom VARCHAR(255),
    ville VARCHAR(255),
    last_sync DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des joueurs
CREATE TABLE IF NOT EXISTS players (
    licence VARCHAR(20) PRIMARY KEY,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    numclub VARCHAR(20),
    sexe CHAR(1),
    cat VARCHAR(10),
    points_officiel FLOAT DEFAULT 0, -- Points officiels de début de phase
    points_mensuel FLOAT DEFAULT 0,  -- Points mensuels actuels
    points_ph1 FLOAT DEFAULT 0,      -- Points au début de la Phase 1
    points_ph2 FLOAT DEFAULT 0,      -- Points au début de la Phase 2
    points_mois FLOAT DEFAULT 0,     -- Points au début du mois en cours
    last_update DATETIME,
    INDEX (numclub)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique des points
CREATE TABLE IF NOT EXISTS points_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    licence VARCHAR(20),
    date_history DATE NOT NULL,
    points FLOAT NOT NULL,
    type VARCHAR(20) DEFAULT 'snapshot', -- 'mensuel', 'biannuel', 'snapshot'
    INDEX (licence),
    INDEX (date_history),
    UNIQUE KEY unique_daily (licence, date_history)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des matchs (optionnel pour les calculs de progression détaillés)
CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    licence VARCHAR(20),
    date_match DATE,
    adversaire_nom VARCHAR(255),
    adversaire_licence VARCHAR(20),
    victoire_defaite CHAR(1), -- 'V' ou 'D'
    points_resultat FLOAT,
    epreuve VARCHAR(255),
    coefficient FLOAT DEFAULT 1.0,
    idpartie VARCHAR(20) UNIQUE DEFAULT NULL,
    INDEX (licence),
    INDEX (date_match)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
