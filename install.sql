-- ============================================================
-- BofaDueDiligence - Installation SQL
-- Base de données MySQL/MariaDB
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS bofa_due_diligence CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bofa_due_diligence;

-- ============================================================
-- TABLE: users
-- ============================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `role` ENUM('client','agent','admin') NOT NULL DEFAULT 'client',
    `statut` ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
    `dernier_login` DATETIME DEFAULT NULL,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: accounts
-- ============================================================
DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `numero_compte_principal` VARCHAR(20) NOT NULL UNIQUE,
    `solde` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `devise` VARCHAR(3) NOT NULL DEFAULT 'USD',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: sub_accounts
-- ============================================================
DROP TABLE IF EXISTS `sub_accounts`;
CREATE TABLE `sub_accounts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT UNSIGNED NOT NULL,
    `numero_sous_compte` VARCHAR(30) NOT NULL UNIQUE,
    `ledger` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: cases
-- ============================================================
DROP TABLE IF EXISTS `cases`;
CREATE TABLE `cases` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `case_id_unique` VARCHAR(20) NOT NULL UNIQUE,
    `sub_account_id` INT UNSIGNED NOT NULL,
    `montant` DECIMAL(15,2) NOT NULL,
    `devise` VARCHAR(3) NOT NULL DEFAULT 'USD',
    `emetteur_nom` VARCHAR(200) NOT NULL,
    `emetteur_banque` VARCHAR(200) DEFAULT NULL,
    `beneficiaire_nom` VARCHAR(200) NOT NULL,
    `beneficiaire_banque` VARCHAR(200) DEFAULT NULL,
    `pays_origine` VARCHAR(100) NOT NULL,
    `pays_destination` VARCHAR(100) NOT NULL,
    `type_actif` VARCHAR(100) NOT NULL DEFAULT 'Virement',
    `score_risque` DECIMAL(5,2) DEFAULT 0.00,
    `statut` ENUM('en_analyse','documents_demandes','en_attente_validation','valide','pret_pour_transfert','rejete','gele') NOT NULL DEFAULT 'en_analyse',
    `statut_fonds` ENUM('bloque','gele','disponible','transfere') NOT NULL DEFAULT 'bloque',
    `agent_assigne_id` INT UNSIGNED DEFAULT NULL,
    `superviseur_requis` TINYINT(1) NOT NULL DEFAULT 0,
    `date_limite` DATE DEFAULT NULL,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_maj` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`sub_account_id`) REFERENCES `sub_accounts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`agent_assigne_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: case_status_history
-- ============================================================
DROP TABLE IF EXISTS `case_status_history`;
CREATE TABLE `case_status_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `case_id` INT UNSIGNED NOT NULL,
    `ancien_statut` VARCHAR(50) DEFAULT NULL,
    `nouveau_statut` VARCHAR(50) NOT NULL,
    `commentaire` TEXT DEFAULT NULL,
    `utilisateur_id` INT UNSIGNED DEFAULT NULL,
    `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: documents
-- ============================================================
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `case_id` INT UNSIGNED NOT NULL,
    `nom_fichier` VARCHAR(255) NOT NULL,
    `chemin_fichier` VARCHAR(500) NOT NULL,
    `type_document` VARCHAR(100) DEFAULT 'Autre',
    `statut_validation` ENUM('en_attente','valide','rejete') NOT NULL DEFAULT 'en_attente',
    `motif_rejet` TEXT DEFAULT NULL,
    `date_upload` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: checklist_items
-- ============================================================
DROP TABLE IF EXISTS `checklist_items`;
CREATE TABLE `checklist_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `case_id` INT UNSIGNED NOT NULL,
    `libelle` VARCHAR(500) NOT NULL,
    `type_exigence` ENUM('case','document') NOT NULL DEFAULT 'case',
    `est_coche` TINYINT(1) NOT NULL DEFAULT 0,
    `document_id` INT UNSIGNED DEFAULT NULL,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: messages
-- ============================================================
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `case_id` INT UNSIGNED NOT NULL,
    `expediteur_id` INT UNSIGNED NOT NULL,
    `destinataire_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `piece_jointe` VARCHAR(500) DEFAULT NULL,
    `lu` TINYINT(1) NOT NULL DEFAULT 0,
    `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`expediteur_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`destinataire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: risk_countries
-- ============================================================
DROP TABLE IF EXISTS `risk_countries`;
CREATE TABLE `risk_countries` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nom_pays` VARCHAR(100) NOT NULL,
    `code_pays` VARCHAR(3) NOT NULL UNIQUE,
    `coefficient_risque` DECIMAL(4,2) NOT NULL DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: risk_asset_types
-- ============================================================
DROP TABLE IF EXISTS `risk_asset_types`;
CREATE TABLE `risk_asset_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nom_type` VARCHAR(100) NOT NULL UNIQUE,
    `coefficient_risque` DECIMAL(4,2) NOT NULL DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: audit_log
-- ============================================================
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(255) NOT NULL,
    `table_concernee` VARCHAR(100) DEFAULT NULL,
    `enregistrement_id` INT UNSIGNED DEFAULT NULL,
    `ancienne_valeur` TEXT DEFAULT NULL,
    `nouvelle_valeur` TEXT DEFAULT NULL,
    `ip` VARCHAR(45) DEFAULT NULL,
    `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: notifications
-- ============================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `message` VARCHAR(500) NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'info',
    `lien` VARCHAR(255) DEFAULT NULL,
    `lu` TINYINT(1) NOT NULL DEFAULT 0,
    `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: settings (for thresholds and config)
-- ============================================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cle` VARCHAR(100) NOT NULL UNIQUE,
    `valeur` VARCHAR(500) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Settings
INSERT INTO `settings` (`cle`, `valeur`, `description`) VALUES
('seuil_double_validation', '7.5', 'Score de risque minimum pour exiger une double validation'),
('delai_escalade_jours', '5', 'Nombre de jours sans action avant escalade automatique'),
('devise_par_defaut', 'USD', 'Devise par défaut pour les nouveaux comptes');

-- Users (passwords: admin123, agent123, client123)
INSERT INTO `users` (`email`, `password`, `nom`, `prenom`, `role`, `statut`) VALUES
('admin@bofa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Super', 'admin', 'actif'),
('agent@bofa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dupont', 'Marie', 'agent', 'actif'),
('agent2@bofa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Martin', 'Pierre', 'agent', 'actif'),
('client@bofa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Durand', 'Jean', 'client', 'actif'),
('client2@bofa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bernard', 'Sophie', 'client', 'actif');

-- Accounts
INSERT INTO `accounts` (`user_id`, `numero_compte_principal`, `solde`, `devise`) VALUES
(4, 'BOFA-2026-00001', 125000.00, 'USD'),
(5, 'BOFA-2026-00002', 87500.00, 'USD');

-- Sub-accounts
INSERT INTO `sub_accounts` (`account_id`, `numero_sous_compte`, `ledger`) VALUES
(1, 'SUB-2026-00001-01', 50000.00),
(1, 'SUB-2026-00001-02', 150000.00),
(1, 'SUB-2026-00001-03', 25000.00),
(2, 'SUB-2026-00002-01', 200000.00),
(2, 'SUB-2026-00002-02', 75000.00);

-- Cases
INSERT INTO `cases` (`case_id_unique`, `sub_account_id`, `montant`, `devise`, `emetteur_nom`, `emetteur_banque`, `beneficiaire_nom`, `beneficiaire_banque`, `pays_origine`, `pays_destination`, `type_actif`, `score_risque`, `statut`, `statut_fonds`, `agent_assigne_id`, `date_limite`) VALUES
('AML-EDD-2026-00001', 1, 50000.00, 'USD', 'Global Trade Corp', 'HSBC London', 'Jean Durand', 'Bank of America', 'Royaume-Uni', 'États-Unis', 'Virement', 3.50, 'en_analyse', 'bloque', 2, DATE_ADD(CURDATE(), INTERVAL 10 DAY)),
('AML-EDD-2026-00002', 2, 150000.00, 'USD', 'Offshore Holdings Ltd', 'Cayman National Bank', 'Jean Durand', 'Bank of America', 'Îles Caïmans', 'États-Unis', 'Virement', 12.00, 'documents_demandes', 'bloque', 2, DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
('AML-EDD-2026-00003', 3, 25000.00, 'USD', 'Tech Solutions SA', 'BNP Paribas', 'Jean Durand', 'Bank of America', 'France', 'États-Unis', 'Chèque', 2.10, 'pret_pour_transfert', 'disponible', 2, DATE_ADD(CURDATE(), INTERVAL 15 DAY)),
('AML-EDD-2026-00004', 4, 200000.00, 'USD', 'Investment Fund LLC', 'Deutsche Bank', 'Sophie Bernard', 'Bank of America', 'Russie', 'États-Unis', 'Virement', 15.00, 'gele', 'gele', 3, DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
('AML-EDD-2026-00005', 5, 75000.00, 'USD', 'Crypto Exchange SA', 'Binance', 'Sophie Bernard', 'Bank of America', 'Singapour', 'États-Unis', 'Cryptomonnaie', 8.75, 'en_attente_validation', 'bloque', 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY));

-- Case status history
INSERT INTO `case_status_history` (`case_id`, `ancien_statut`, `nouveau_statut`, `commentaire`, `utilisateur_id`) VALUES
(1, NULL, 'en_analyse', 'Dossier créé automatiquement', 1),
(2, 'en_analyse', 'documents_demandes', 'Documents supplémentaires requis pour vérification offshore', 2),
(3, 'en_analyse', 'valide', 'Transaction à faible risque validée', 2),
(3, 'valide', 'pret_pour_transfert', 'Fonds disponibles pour transfert', 2),
(4, 'en_analyse', 'gele', 'Gel préventif - pays à haut risque', 3),
(5, 'en_analyse', 'en_attente_validation', 'En attente de validation superviseur - score élevé', 3);

-- Checklist items
INSERT INTO `checklist_items` (`case_id`, `libelle`, `type_exigence`, `est_coche`) VALUES
(2, 'Fournir une preuve d''origine des fonds', 'document', 0),
(2, 'Fournir un relevé bancaire des 3 derniers mois', 'document', 0),
(2, 'Confirmer l''identité du bénéficiaire', 'case', 0),
(2, 'Accepter les conditions de conformité AML', 'case', 0),
(3, 'Accepter les conditions de transfert', 'case', 1),
(3, 'Confirmer l''identité', 'case', 1);

-- Risk countries
INSERT INTO `risk_countries` (`nom_pays`, `code_pays`, `coefficient_risque`) VALUES
('États-Unis', 'US', 1.00),
('France', 'FR', 1.00),
('Royaume-Uni', 'GB', 1.20),
('Allemagne', 'DE', 1.10),
('Singapour', 'SG', 1.50),
('Îles Caïmans', 'KY', 3.00),
('Russie', 'RU', 3.50),
('Iran', 'IR', 5.00),
('Corée du Nord', 'KP', 5.00),
('Afghanistan', 'AF', 4.50),
('Syrie', 'SY', 4.80),
('Venezuela', 'VE', 3.20),
('Myanmar', 'MM', 3.80),
('Libye', 'LY', 4.00),
('Somalie', 'SO', 4.20);

-- Risk asset types
INSERT INTO `risk_asset_types` (`nom_type`, `coefficient_risque`) VALUES
('Virement', 1.00),
('Chèque', 1.20),
('Espèces', 2.50),
('Cryptomonnaie', 2.00),
('Titres', 1.50),
('Métaux précieux', 2.80),
('Immobilier', 1.80);

-- Notifications
INSERT INTO `notifications` (`user_id`, `message`, `type`, `lien`) VALUES
(4, 'Votre dossier AML-EDD-2026-00002 nécessite des documents supplémentaires.', 'warning', '/client/case/2'),
(4, 'Votre dossier AML-EDD-2026-00003 est prêt pour le transfert.', 'success', '/client/case/3'),
(5, 'Votre dossier AML-EDD-2026-00004 a été gelé pour vérification approfondie.', 'danger', '/client/case/4'),
(2, 'Nouveau dossier AML-EDD-2026-00001 assigné.', 'info', '/agent/case/1'),
(3, 'Dossier AML-EDD-2026-00005 en attente de votre validation superviseur.', 'warning', '/agent/case/5');

-- Messages
INSERT INTO `messages` (`case_id`, `expediteur_id`, `destinataire_id`, `message`) VALUES
(2, 2, 4, 'Bonjour M. Durand, nous avons besoin de documents supplémentaires pour votre dossier. Merci de fournir une preuve d''origine des fonds et vos relevés bancaires des 3 derniers mois.'),
(2, 4, 2, 'Bonjour, je vais préparer ces documents. Puis-je vous envoyer les relevés au format PDF ?'),
(2, 2, 4, 'Oui, le format PDF est parfait. Merci de les téléverser dans votre espace documents.'),
(4, 3, 5, 'Mme Bernard, votre dossier a été gelé temporairement en raison de l''origine des fonds (Russie). Veuillez fournir des justificatifs complémentaires.');

-- Audit log entries
INSERT INTO `audit_log` (`utilisateur_id`, `action`, `table_concernee`, `enregistrement_id`, `ip`) VALUES
(1, 'Création du dossier AML-EDD-2026-00001', 'cases', 1, '192.168.1.1'),
(2, 'Changement de statut: en_analyse → documents_demandes', 'cases', 2, '192.168.1.2'),
(2, 'Validation du dossier AML-EDD-2026-00003', 'cases', 3, '192.168.1.2'),
(3, 'Gel des fonds du dossier AML-EDD-2026-00004', 'cases', 4, '192.168.1.3'),
(3, 'Soumission à validation superviseur AML-EDD-2026-00005', 'cases', 5, '192.168.1.3');

SET FOREIGN_KEY_CHECKS = 1;
