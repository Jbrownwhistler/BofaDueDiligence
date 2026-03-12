-- =============================================================================
-- BofaDueDiligence — Script d'installation de la base de données
-- Application de conformité AML/EDD bancaire — Version 2.0
--
-- Exécution : mysql -u root -p < install.sql
-- =============================================================================

-- Supprimer et recréer la base de données proprement
DROP DATABASE IF EXISTS bofa_due_diligence;
CREATE DATABASE IF NOT EXISTS bofa_due_diligence
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bofa_due_diligence;

-- Désactiver temporairement les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =============================================================================
-- TABLE : users — Comptes utilisateurs de la plateforme
-- =============================================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nom           VARCHAR(100)    NOT NULL COMMENT 'Nom de famille',
    prenom        VARCHAR(100)    NOT NULL COMMENT 'Prénom',
    email         VARCHAR(255)    NOT NULL COMMENT 'Adresse e-mail unique',
    password_hash VARCHAR(255)    NOT NULL COMMENT 'Hachage BCRYPT coût 12',
    role          ENUM('admin','agent','client') NOT NULL DEFAULT 'client' COMMENT 'Rôle applicatif',
    statut        ENUM('actif','inactif','suspendu') NOT NULL DEFAULT 'actif',
    telephone     VARCHAR(30)     DEFAULT NULL,
    avatar        VARCHAR(255)    DEFAULT NULL COMMENT 'Chemin relatif vers l avatar',
    derniere_connexion DATETIME   DEFAULT NULL,
    tentatives_connexion TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Compteur anti-brute-force',
    bloque_jusqu   DATETIME       DEFAULT NULL COMMENT 'Blocage temporaire après tentatives excessives',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_users_email (email),
    KEY idx_users_role   (role),
    KEY idx_users_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Comptes utilisateurs — admins, agents AML, clients';

-- =============================================================================
-- TABLE : accounts — Comptes bancaires des clients
-- =============================================================================
CREATE TABLE IF NOT EXISTS accounts (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED  NOT NULL COMMENT 'Propriétaire du compte',
    numero_compte   VARCHAR(50)   NOT NULL COMMENT 'Numéro de compte unique (IBAN ou interne)',
    type_compte     ENUM('courant','epargne','investissement','professionnel') NOT NULL DEFAULT 'courant',
    devise          CHAR(3)       NOT NULL DEFAULT 'EUR' COMMENT 'Code ISO 4217',
    solde           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    statut          ENUM('actif','cloture','gele','surveille') NOT NULL DEFAULT 'actif',
    banque_origine  VARCHAR(150)  DEFAULT NULL COMMENT 'Banque émettrice si externe',
    code_swift      VARCHAR(11)   DEFAULT NULL,
    pays_origine    CHAR(2)       DEFAULT NULL COMMENT 'Code ISO 3166-1 alpha-2',
    date_ouverture  DATE          NOT NULL,
    date_cloture    DATE          DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_accounts_numero (numero_compte),
    KEY idx_accounts_user   (user_id),
    KEY idx_accounts_statut (statut),
    CONSTRAINT fk_accounts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Comptes bancaires associés aux clients';

-- =============================================================================
-- TABLE : sub_accounts — Sous-comptes et portefeuilles liés
-- =============================================================================
CREATE TABLE IF NOT EXISTS sub_accounts (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    account_id      INT UNSIGNED  NOT NULL COMMENT 'Compte parent',
    libelle         VARCHAR(150)  NOT NULL COMMENT 'Intitulé du sous-compte',
    type_actif      VARCHAR(50)   NOT NULL DEFAULT 'cash' COMMENT 'Correspond à risk_asset_types.code',
    montant         DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    devise          CHAR(3)       NOT NULL DEFAULT 'EUR',
    description     TEXT          DEFAULT NULL,
    statut          ENUM('actif','cloture','suspendu') NOT NULL DEFAULT 'actif',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_subaccounts_account (account_id),
    KEY idx_subaccounts_actif   (type_actif),
    CONSTRAINT fk_subaccounts_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sous-comptes et portefeuilles par type d actif';

-- =============================================================================
-- TABLE : cases — Dossiers AML/EDD
-- =============================================================================
CREATE TABLE IF NOT EXISTS cases (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    case_number     VARCHAR(25)   NOT NULL COMMENT 'Identifiant unique AML-EDD-YYYY-NNNNN',
    user_id         INT UNSIGNED  NOT NULL COMMENT 'Client concerné par le dossier',
    agent_id        INT UNSIGNED  DEFAULT NULL COMMENT 'Agent AML responsable',
    account_id      INT UNSIGNED  DEFAULT NULL COMMENT 'Compte bancaire lié',
    titre           VARCHAR(255)  NOT NULL COMMENT 'Intitulé court du dossier',
    description     TEXT          DEFAULT NULL COMMENT 'Description détaillée',
    type_cas        ENUM('aml','edd','kyc','sanctions','pep','fraude','autre') NOT NULL DEFAULT 'aml',
    statut          ENUM('ouvert','en_cours','en_attente','cloture','rejete','approuve') NOT NULL DEFAULT 'ouvert',
    priorite        ENUM('faible','normale','haute','critique') NOT NULL DEFAULT 'normale',
    score_risque    DECIMAL(5,2)  NOT NULL DEFAULT 0.00 COMMENT 'Score calculé 0-100',
    montant         DECIMAL(18,4) DEFAULT NULL COMMENT 'Montant en jeu',
    devise          CHAR(3)       DEFAULT 'EUR',
    pays_origine    CHAR(2)       DEFAULT NULL COMMENT 'Pays source de la transaction',
    type_actif      VARCHAR(50)   DEFAULT 'cash' COMMENT 'Type d actif concerné',
    motif_cloture   TEXT          DEFAULT NULL,
    date_echeance   DATE          DEFAULT NULL COMMENT 'Date limite réglementaire',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at       DATETIME      DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cases_number (case_number),
    KEY idx_cases_user    (user_id),
    KEY idx_cases_agent   (agent_id),
    KEY idx_cases_statut  (statut),
    KEY idx_cases_score   (score_risque),
    KEY idx_cases_type    (type_cas),
    CONSTRAINT fk_cases_user    FOREIGN KEY (user_id)    REFERENCES users    (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cases_agent   FOREIGN KEY (agent_id)   REFERENCES users    (id) ON DELETE SET NULL  ON UPDATE CASCADE,
    CONSTRAINT fk_cases_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE SET NULL  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Dossiers de conformité AML/EDD';

-- =============================================================================
-- TABLE : case_status_history — Historique des changements de statut
-- =============================================================================
CREATE TABLE IF NOT EXISTS case_status_history (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    case_id         INT UNSIGNED  NOT NULL,
    user_id         INT UNSIGNED  NOT NULL COMMENT 'Auteur du changement',
    ancien_statut   VARCHAR(30)   DEFAULT NULL,
    nouveau_statut  VARCHAR(30)   NOT NULL,
    commentaire     TEXT          DEFAULT NULL COMMENT 'Justification du changement',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_csh_case (case_id),
    KEY idx_csh_user (user_id),
    CONSTRAINT fk_csh_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_csh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Traçabilité des transitions de statut des dossiers';

-- =============================================================================
-- TABLE : documents — Pièces justificatives attachées aux dossiers
-- Les fichiers sont stockés dans BOFA_UPLOAD_DIR avec un nom UUID (nom_stockage).
-- L'accès est contrôlé par le contrôleur PHP — uploads/.htaccess bloque tout
-- accès direct. Le hash SHA-256 permet de vérifier l'intégrité à la livraison.
-- =============================================================================
CREATE TABLE IF NOT EXISTS documents (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    case_id         INT UNSIGNED  NOT NULL,
    uploaded_by     INT UNSIGNED  NOT NULL COMMENT 'Utilisateur ayant téléversé le fichier',
    nom_original    VARCHAR(255)  NOT NULL COMMENT 'Nom de fichier d origine',
    nom_stockage    VARCHAR(255)  NOT NULL COMMENT 'Nom de fichier sur le serveur (UUID)',
    type_mime       VARCHAR(100)  NOT NULL,
    taille_octets   INT UNSIGNED  NOT NULL COMMENT 'Taille en octets',
    type_document   ENUM('identite','justificatif_domicile','releve_bancaire','contrat','rapport','autre') NOT NULL DEFAULT 'autre',
    description     VARCHAR(500)  DEFAULT NULL,
    hash_sha256     CHAR(64)      DEFAULT NULL COMMENT 'Empreinte d intégrité du fichier',
    valide          TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Validé par un agent : 0=non, 1=oui',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_docs_case     (case_id),
    KEY idx_docs_uploader (uploaded_by),
    CONSTRAINT fk_docs_case     FOREIGN KEY (case_id)     REFERENCES cases (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_docs_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Documents justificatifs des dossiers AML/EDD';

-- =============================================================================
-- TABLE : checklist_items — Éléments de la liste de contrôle KYC/EDD
-- =============================================================================
CREATE TABLE IF NOT EXISTS checklist_items (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    case_id         INT UNSIGNED  NOT NULL,
    libelle         VARCHAR(300)  NOT NULL COMMENT 'Description de la tâche de vérification',
    statut          ENUM('en_attente','en_cours','valide','rejete') NOT NULL DEFAULT 'en_attente',
    obligatoire     TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'Tâche obligatoire ou optionnelle',
    assigne_a       INT UNSIGNED  DEFAULT NULL COMMENT 'Agent responsable de la tâche',
    commentaire     TEXT          DEFAULT NULL,
    date_limite     DATE          DEFAULT NULL,
    complete_at     DATETIME      DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_checklist_case    (case_id),
    KEY idx_checklist_assigne (assigne_a),
    CONSTRAINT fk_checklist_case    FOREIGN KEY (case_id)    REFERENCES cases (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_checklist_assigne FOREIGN KEY (assigne_a)  REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Liste de contrôle KYC/EDD par dossier';

-- =============================================================================
-- TABLE : messages — Messagerie interne entre agents et clients
-- =============================================================================
CREATE TABLE IF NOT EXISTS messages (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    case_id         INT UNSIGNED  NOT NULL,
    expediteur_id   INT UNSIGNED  NOT NULL,
    destinataire_id INT UNSIGNED  DEFAULT NULL COMMENT 'NULL = message visible par toute l équipe',
    contenu         TEXT          NOT NULL,
    lu              TINYINT(1)    NOT NULL DEFAULT 0,
    lu_at           DATETIME      DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_msg_case         (case_id),
    KEY idx_msg_expediteur   (expediteur_id),
    KEY idx_msg_destinataire (destinataire_id),
    CONSTRAINT fk_msg_case          FOREIGN KEY (case_id)         REFERENCES cases (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_msg_expediteur    FOREIGN KEY (expediteur_id)   REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_msg_destinataire  FOREIGN KEY (destinataire_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Messagerie interne liée aux dossiers';

-- =============================================================================
-- TABLE : risk_countries — Coefficients de risque par pays
-- =============================================================================
CREATE TABLE IF NOT EXISTS risk_countries (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    code_iso        CHAR(2)       NOT NULL COMMENT 'Code ISO 3166-1 alpha-2',
    nom             VARCHAR(100)  NOT NULL COMMENT 'Nom complet du pays',
    coefficient     DECIMAL(4,2)  NOT NULL DEFAULT 1.00 COMMENT 'Multiplicateur de risque (1.0 = neutre)',
    categorie_risque ENUM('faible','moyen','eleve','tres_eleve') NOT NULL DEFAULT 'faible',
    liste_noire     TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Pays sous sanctions internationales',
    source          VARCHAR(100)  DEFAULT 'FATF/GAFI' COMMENT 'Source réglementaire',
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_risk_countries_iso (code_iso),
    KEY idx_risk_countries_cat (categorie_risque)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Table de référence des coefficients de risque par pays';

-- =============================================================================
-- TABLE : risk_asset_types — Coefficients de risque par type d'actif
-- =============================================================================
CREATE TABLE IF NOT EXISTS risk_asset_types (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    code            VARCHAR(30)   NOT NULL COMMENT 'Identifiant technique du type d actif',
    libelle         VARCHAR(100)  NOT NULL COMMENT 'Libellé affiché',
    coefficient     DECIMAL(4,2)  NOT NULL DEFAULT 1.00 COMMENT 'Multiplicateur de risque',
    description     TEXT          DEFAULT NULL,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_risk_assets_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Types d actifs avec leur coefficient de risque AML';

-- =============================================================================
-- TABLE : audit_log — Journal d'audit réglementaire
-- =============================================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED    DEFAULT NULL COMMENT 'Utilisateur auteur (NULL si action système)',
    action          VARCHAR(50)     NOT NULL COMMENT 'Type d action : CREATE, UPDATE, DELETE, LOGIN, etc.',
    table_name      VARCHAR(60)     NOT NULL COMMENT 'Table concernée',
    record_id       INT UNSIGNED    DEFAULT NULL,
    old_value       JSON            DEFAULT NULL COMMENT 'État avant modification',
    new_value       JSON            DEFAULT NULL COMMENT 'État après modification',
    ip_address      VARCHAR(45)     DEFAULT NULL COMMENT 'Adresse IP (IPv4 ou IPv6)',
    user_agent      VARCHAR(500)    DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user   (user_id),
    KEY idx_audit_table  (table_name),
    KEY idx_audit_action (action),
    KEY idx_audit_date   (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Journal d audit immuable — conformité réglementaire';

-- =============================================================================
-- TABLE : notifications — Alertes et notifications utilisateurs
-- =============================================================================
CREATE TABLE IF NOT EXISTS notifications (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED  NOT NULL COMMENT 'Destinataire de la notification',
    case_id         INT UNSIGNED  DEFAULT NULL COMMENT 'Dossier lié si applicable',
    titre           VARCHAR(200)  NOT NULL,
    message         TEXT          NOT NULL,
    type            ENUM('info','alerte','erreur','succes','aml_alerte') NOT NULL DEFAULT 'info',
    lu              TINYINT(1)    NOT NULL DEFAULT 0,
    lu_at           DATETIME      DEFAULT NULL,
    lien            VARCHAR(500)  DEFAULT NULL COMMENT 'URL d action liée',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user   (user_id),
    KEY idx_notif_case   (case_id),
    KEY idx_notif_lu     (lu),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)  REFERENCES users  (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_notif_case FOREIGN KEY (case_id)  REFERENCES cases  (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Notifications et alertes utilisateurs';

-- =============================================================================
-- TABLE : tags — Étiquettes de classification des dossiers
-- =============================================================================
CREATE TABLE IF NOT EXISTS tags (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    libelle         VARCHAR(80)   NOT NULL COMMENT 'Nom de l étiquette',
    couleur         CHAR(7)       NOT NULL DEFAULT '#6B7280' COMMENT 'Code couleur hexadécimal',
    description     VARCHAR(255)  DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tags_libelle (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Étiquettes de classification pour les dossiers AML';

-- =============================================================================
-- TABLE : case_tags — Association dossiers ↔ étiquettes (N:M)
-- =============================================================================
CREATE TABLE IF NOT EXISTS case_tags (
    case_id         INT UNSIGNED  NOT NULL,
    tag_id          INT UNSIGNED  NOT NULL,
    assigned_by     INT UNSIGNED  DEFAULT NULL COMMENT 'Agent ayant attribué l étiquette',
    assigned_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (case_id, tag_id),
    KEY idx_casetags_tag  (tag_id),
    CONSTRAINT fk_casetags_case FOREIGN KEY (case_id)     REFERENCES cases (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_casetags_tag  FOREIGN KEY (tag_id)      REFERENCES tags  (id) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_casetags_user FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Table de liaison dossiers et étiquettes';

-- =============================================================================
-- TABLE : user_sessions — Sessions utilisateurs actives
-- =============================================================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id              VARCHAR(128)  NOT NULL COMMENT 'Identifiant de session PHP',
    user_id         INT UNSIGNED  NOT NULL,
    ip_address      VARCHAR(45)   DEFAULT NULL,
    user_agent      VARCHAR(500)  DEFAULT NULL,
    payload         TEXT          DEFAULT NULL COMMENT 'Données sérialisées de session',
    derniere_activite DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_usess_user (user_id),
    CONSTRAINT fk_usess_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sessions utilisateurs actives pour gestion multi-appareils';

-- =============================================================================
-- TABLE : business_rules — Règles métier AML configurables
-- =============================================================================
CREATE TABLE IF NOT EXISTS business_rules (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    code            VARCHAR(60)   NOT NULL COMMENT 'Identifiant technique de la règle',
    libelle         VARCHAR(200)  NOT NULL COMMENT 'Description lisible',
    type_regle      ENUM('seuil','scoring','blocage','alerte','workflow') NOT NULL DEFAULT 'alerte',
    valeur_seuil    DECIMAL(18,4) DEFAULT NULL COMMENT 'Valeur numérique du seuil si applicable',
    condition_json  JSON          DEFAULT NULL COMMENT 'Conditions structurées en JSON',
    action_json     JSON          DEFAULT NULL COMMENT 'Actions déclenchées en JSON',
    active          TINYINT(1)    NOT NULL DEFAULT 1,
    priorite        SMALLINT      NOT NULL DEFAULT 100 COMMENT 'Ordre d évaluation (plus petit = prioritaire)',
    created_by      INT UNSIGNED  DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_rules_code (code),
    KEY idx_rules_active   (active),
    KEY idx_rules_priorite (priorite),
    CONSTRAINT fk_rules_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Règles métier AML/EDD configurables par les administrateurs';

-- =============================================================================
-- TABLE : system_config — Configuration système de l'application
-- =============================================================================
CREATE TABLE IF NOT EXISTS system_config (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    cle             VARCHAR(100)  NOT NULL COMMENT 'Clé de configuration unique',
    valeur          TEXT          DEFAULT NULL COMMENT 'Valeur de configuration',
    description     VARCHAR(500)  DEFAULT NULL COMMENT 'Description de la clé',
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_sysconfig_cle (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Paramètres de configuration système';

-- Réactiver les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- DONNÉES DE DÉMONSTRATION
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Utilisateurs — 1 admin, 2 agents, 3 clients
-- Mots de passe BCRYPT coût 12 :
--   admin@bofa.fr        → Admin@2025!
--   agent1@bofa.fr       → Agent@2025!
--   agent2@bofa.fr       → Agent@2025!
--   client1@example.com  → Client@2025!
--   client2@example.com  → Client@2025!
--   client3@example.com  → Client@2025!
--
-- ⚠️  SÉCURITÉ PRODUCTION : Ces comptes de démonstration utilisent des mots
-- de passe connus. Avant tout déploiement en production :
--   1. Supprimer ou désactiver ces comptes de démonstration, OU
--   2. Forcer la réinitialisation du mot de passe à la première connexion
--      (colonne force_password_change à ajouter selon votre workflow).
-- Ne jamais laisser ces hachages connus dans un environnement exposé.
-- -----------------------------------------------------------------------------
INSERT INTO users (id, nom, prenom, email, password_hash, role, statut, telephone, created_at) VALUES
(1, 'Administrateur', 'Système',     'admin@bofa.fr',        '$2y$12$OkVOE4XVlRivVZBPYajWv.wqAqBdqCkxg9Kg8xLqFhK0b2VpCGvBW', 'admin',  'actif', '+33100000000', '2025-01-01 08:00:00'),
(2, 'Dubois',         'Marie',       'agent1@bofa.fr',       '$2y$12$nXHq3uI0eSzRAGnKSCL/mO1WQ4RzaJZNzQJkJnf0GlCuH8qDFPDkS', 'agent',  'actif', '+33611111111', '2025-01-02 09:00:00'),
(3, 'Martin',         'Pierre',      'agent2@bofa.fr',       '$2y$12$nXHq3uI0eSzRAGnKSCL/mO1WQ4RzaJZNzQJkJnf0GlCuH8qDFPDkS', 'agent',  'actif', '+33622222222', '2025-01-03 09:00:00'),
(4, 'Nguyen',         'Thanh',       'client1@example.com',  '$2y$12$HsT/XpQOxGJANuL4mRXNS.p9vmHqBf0KCsKdHCjKxI.5GzKLO2lIi', 'client', 'actif', '+33633333333', '2025-02-01 10:00:00'),
(5, 'Al-Rashid',      'Youssef',     'client2@example.com',  '$2y$12$HsT/XpQOxGJANuL4mRXNS.p9vmHqBf0KCsKdHCjKxI.5GzKLO2lIi', 'client', 'actif', '+33644444444', '2025-02-15 11:00:00'),
(6, 'Ivanova',        'Natasha',     'client3@example.com',  '$2y$12$HsT/XpQOxGJANuL4mRXNS.p9vmHqBf0KCsKdHCjKxI.5GzKLO2lIi', 'client', 'actif', '+33655555555', '2025-03-01 12:00:00');

-- -----------------------------------------------------------------------------
-- Pays à risque — Coefficients basés sur les listes FATF/GAFI et UE
-- -----------------------------------------------------------------------------
INSERT INTO risk_countries (code_iso, nom, coefficient, categorie_risque, liste_noire, source) VALUES
-- Pays à très haut risque — liste noire FATF
('IR', 'Iran',                       5.00, 'tres_eleve', 1, 'FATF'),
('KP', 'Corée du Nord',              5.00, 'tres_eleve', 1, 'FATF/ONU'),
('SY', 'Syrie',                      4.50, 'tres_eleve', 1, 'UE/OFAC'),
('AF', 'Afghanistan',                4.00, 'tres_eleve', 1, 'FATF'),
-- Pays à haut risque
('RU', 'Russie',                     3.50, 'eleve',      0, 'UE/OFAC'),
('MM', 'Myanmar (Birmanie)',          3.50, 'eleve',      0, 'FATF'),
('YE', 'Yémen',                      3.00, 'eleve',      0, 'ONU'),
('IQ', 'Irak',                       3.00, 'eleve',      0, 'FATF'),
('LY', 'Libye',                      3.00, 'eleve',      0, 'ONU'),
('VE', 'Venezuela',                  2.80, 'eleve',      0, 'OFAC'),
-- Pays à risque moyen-élevé
('CN', 'Chine',                      2.50, 'moyen',      0, 'FATF'),
('BY', 'Biélorussie',                2.50, 'moyen',      0, 'UE'),
('CU', 'Cuba',                       2.00, 'moyen',      0, 'OFAC'),
('PK', 'Pakistan',                   2.00, 'moyen',      0, 'FATF'),
('NG', 'Nigeria',                    1.80, 'moyen',      0, 'FATF'),
('UA', 'Ukraine',                    1.80, 'moyen',      0, 'ACPR'),
('TR', 'Turquie',                    1.50, 'moyen',      0, 'FATF'),
('MA', 'Maroc',                      1.30, 'moyen',      0, 'GAFI-MENA'),
-- Pays à faible risque
('US', 'États-Unis',                 1.20, 'faible',     0, 'FATF'),
('GB', 'Royaume-Uni',                1.10, 'faible',     0, 'FCA'),
('DE', 'Allemagne',                  1.00, 'faible',     0, 'BaFin'),
('FR', 'France',                     1.00, 'faible',     0, 'ACPR'),
('CH', 'Suisse',                     1.10, 'faible',     0, 'FINMA'),
('LU', 'Luxembourg',                 1.10, 'faible',     0, 'CSSF'),
('SG', 'Singapour',                  1.10, 'faible',     0, 'MAS');

-- -----------------------------------------------------------------------------
-- Types d'actifs avec coefficients de risque AML
-- -----------------------------------------------------------------------------
INSERT INTO risk_asset_types (code, libelle, coefficient, description) VALUES
('crypto',       'Crypto-actifs',                4.00, 'Bitcoin, Ethereum et autres actifs numériques décentralisés'),
('cash',         'Espèces',                      3.50, 'Transactions en numéraire, difficiles à tracer'),
('immobilier',   'Immobilier',                   2.50, 'Transactions immobilières résidentielles et commerciales'),
('art',          'Art et objets de valeur',      2.50, 'Œuvres d art, antiquités, véhicules de collection'),
('assurance_vie','Assurance-vie',                2.00, 'Contrats d assurance-vie à fort rendement'),
('forex',        'Change de devises',            2.00, 'Opérations de change et transferts internationaux'),
('metaux',       'Métaux précieux',              1.80, 'Or, argent, platine physiques'),
('obligations',  'Obligations',                  1.50, 'Obligations souveraines et d entreprises'),
('actions',      'Actions et parts',             1.50, 'Actions cotées et parts de sociétés'),
('fonds',        'Fonds d investissement',       1.30, 'OPCVM, FCP, SICAV'),
('depot',        'Dépôts bancaires',             1.10, 'Dépôts à terme et comptes rémunérés'),
('virement',     'Virements bancaires',          1.20, 'Virements SEPA et internationaux (SWIFT)');

-- -----------------------------------------------------------------------------
-- Étiquettes de classification
-- -----------------------------------------------------------------------------
INSERT INTO tags (id, libelle, couleur, description) VALUES
(1, 'Prioritaire',   '#E31837', 'Dossier nécessitant un traitement urgent'),
(2, 'PEP',           '#FF6B35', 'Personne Politiquement Exposée — surveillance renforcée'),
(3, 'Crypto',        '#8B5CF6', 'Opérations impliquant des crypto-actifs'),
(4, 'Sanctions',     '#DC2626', 'Lien avec des entités ou pays sous sanctions'),
(5, 'Diaspora',      '#059669', 'Transferts de fonds liés à la diaspora'),
(6, 'Risque Élevé',  '#F59E0B', 'Score de risque supérieur à 75/100'),
(7, 'EDD Requis',    '#3B82F6', 'Enquête approfondie Due Diligence requise'),
(8, 'FATCA',         '#6B7280', 'Obligation de déclaration FATCA (US persons)');

-- -----------------------------------------------------------------------------
-- Comptes bancaires de démonstration
-- -----------------------------------------------------------------------------
INSERT INTO accounts (id, user_id, numero_compte, type_compte, devise, solde, statut, pays_origine, date_ouverture) VALUES
(1, 4, 'FR7630001007941234567890185', 'courant',       'EUR',  25000.0000, 'actif',     'FR', '2020-03-15'),
(2, 4, 'FR7630001007949876543210274', 'investissement','EUR', 150000.0000, 'surveille', 'FR', '2021-06-01'),
(3, 5, 'FR7610096000509182736455012', 'courant',       'EUR',   8500.0000, 'actif',     'RU', '2022-01-10'),
(4, 5, 'AE070331234567890123456',     'professionnel', 'AED', 500000.0000, 'gele',      'AE', '2023-02-20'),
(5, 6, 'RU0204452560040702810277',    'courant',       'RUB',  75000.0000, 'surveille', 'RU', '2021-11-05'),
(6, 6, 'FR7617569000703456789012365', 'epargne',       'EUR',  45000.0000, 'actif',     'FR', '2022-09-30');

-- -----------------------------------------------------------------------------
-- Sous-comptes de démonstration
-- -----------------------------------------------------------------------------
INSERT INTO sub_accounts (account_id, libelle, type_actif, montant, devise, statut) VALUES
(2, 'Portefeuille crypto BTC/ETH', 'crypto',       80000.0000, 'EUR', 'actif'),
(2, 'Obligations souveraines',     'obligations',  70000.0000, 'EUR', 'actif'),
(4, 'Fonds Dubai Investments',     'fonds',       500000.0000, 'AED', 'suspendu'),
(5, 'Espèces déclarées',           'cash',         75000.0000, 'RUB', 'actif'),
(6, 'Livret A',                    'depot',        22950.0000, 'EUR', 'actif');

-- -----------------------------------------------------------------------------
-- Dossiers AML/EDD de démonstration
-- -----------------------------------------------------------------------------
INSERT INTO cases (id, case_number, user_id, agent_id, account_id, titre, description, type_cas, statut, priorite, score_risque, montant, devise, pays_origine, type_actif, date_echeance, created_at) VALUES
(1, 'AML-EDD-2025-00001', 4, 2, 2,
 'Flux crypto suspects — compte investissement',
 'Client ayant effectué 12 virements vers des exchanges de crypto-actifs non régulés pour un total de 80 000 EUR sur 3 mois. Origine des fonds non justifiée.',
 'aml', 'en_cours', 'haute', 72.50, 80000.0000, 'EUR', 'FR', 'crypto', '2025-07-15', '2025-04-10 09:00:00'),

(2, 'AML-EDD-2025-00002', 5, 2, 4,
 'Compte professionnel Abu Dhabi — gel préventif',
 'Compte en AED présentant des mouvements importants avec une entité signalée par l OFAC. Gel préventif appliqué en attente d investigation.',
 'sanctions', 'en_attente', 'critique', 91.20, 500000.0000, 'AED', 'AE', 'fonds', '2025-06-30', '2025-03-20 14:30:00'),

(3, 'AML-EDD-2025-00003', 6, 3, 5,
 'Transferts espèces — origine russe',
 'Multiples dépôts en espèces de montants inférieurs au seuil réglementaire (technique de fractionnement). Total reconstituable : 75 000 RUB.',
 'edd', 'ouvert', 'haute', 68.40, 75000.0000, 'RUB', 'RU', 'cash', '2025-08-01', '2025-05-01 08:00:00'),

(4, 'AML-EDD-2025-00004', 5, 3, 3,
 'PEP — Vérification KYC renforcée',
 'Client identifié comme Personne Politiquement Exposée suite à mise à jour base de données. Revue KYC complète requise.',
 'pep', 'en_cours', 'haute', 55.80, 8500.0000, 'EUR', 'RU', 'depot', '2025-09-15', '2025-05-10 10:00:00'),

(5, 'AML-EDD-2025-00005', 4, 2, 1,
 'Analyse transactions immobilières Paris',
 'Acquisition immobilière à Paris pour 950 000 EUR financée en partie par des fonds d origine étrangère non vérifiée. Rapport de l agent immobilier transmis.',
 'kyc', 'ouvert', 'normale', 38.00, 950000.0000, 'EUR', 'FR', 'immobilier', '2025-10-01', '2025-05-15 11:00:00');

-- -----------------------------------------------------------------------------
-- Historique des statuts
-- -----------------------------------------------------------------------------
INSERT INTO case_status_history (case_id, user_id, ancien_statut, nouveau_statut, commentaire, created_at) VALUES
(1, 2, 'ouvert',     'en_cours',   'Prise en charge du dossier — analyse des flux en cours',           '2025-04-11 09:00:00'),
(2, 1, 'ouvert',     'en_attente', 'Gel préventif du compte en attente de validation OFAC',             '2025-03-20 16:00:00'),
(2, 2, 'en_attente', 'en_attente', 'En attente de retour juridique — escalade niveau 2',                '2025-04-01 10:00:00'),
(4, 3, 'ouvert',     'en_cours',   'Demande de documents KYC envoyée au client le 10/05/2025',          '2025-05-10 10:30:00');

-- -----------------------------------------------------------------------------
-- Éléments de checklist de démonstration
-- -----------------------------------------------------------------------------
INSERT INTO checklist_items (case_id, libelle, statut, obligatoire, assigne_a, commentaire, date_limite) VALUES
-- Dossier 1 — Crypto
(1, 'Vérification identité (CNI + passeport)',           'valide',     1, 2, 'Documents reçus et conformes', '2025-04-20'),
(1, 'Justificatif de domicile < 3 mois',                'valide',     1, 2, 'Facture EDF reçue',            '2025-04-20'),
(1, 'Origine des fonds crypto documentée',              'en_cours',   1, 2, NULL,                           '2025-07-10'),
(1, 'Rapport analyse blockchain (Chainalysis)',          'en_attente', 1, 2, NULL,                           '2025-07-10'),
(1, 'Entretien téléphonique client',                    'en_cours',   1, 2, 'RDV planifié le 20/06',        '2025-06-20'),
-- Dossier 2 — Sanctions
(2, 'Vérification liste OFAC',                          'valide',     1, 2, 'Match confirmé — entité SDN',  '2025-04-01'),
(2, 'Notification service conformité groupe',           'valide',     1, 2, 'Escalade transmise',           '2025-03-25'),
(2, 'Demande gel compte auprès back-office',            'valide',     1, 1, 'Compte gelé le 21/03/2025',    '2025-03-22'),
(2, 'Rapport TRACFIN — déclaration de soupçon',         'en_attente', 1, 2, NULL,                           '2025-06-30'),
-- Dossier 4 — PEP
(4, 'Identification source PEP (base Dow Jones)',       'valide',     1, 3, 'Confirmé en base Refinitiv',   '2025-05-15'),
(4, 'Questionnaire patrimoine étendu',                  'en_cours',   1, 3, NULL,                           '2025-09-01'),
(4, 'Vérification mandat politique actuel/passé',       'en_attente', 1, 3, NULL,                           '2025-09-01');

-- -----------------------------------------------------------------------------
-- Messages internes de démonstration
-- -----------------------------------------------------------------------------
INSERT INTO messages (case_id, expediteur_id, destinataire_id, contenu, lu, created_at) VALUES
(1, 2, NULL,
 'Analyse des flux crypto terminée. 12 transactions identifiées vers 3 exchanges non conformes MiCA. Demande de documentation complémentaire envoyée au client.',
 0, '2025-04-15 10:00:00'),
(1, 4, 2,
 'Bonjour, je vous transmets les relevés de mes wallets crypto. Les fonds proviennent de la vente de mes parts dans une startup technologique en 2024.',
 1, '2025-04-16 14:30:00'),
(2, 1, 2,
 'URGENT : Validation juridique reçue. Maintenir le gel du compte. Préparer la déclaration TRACFIN avant le 30 juin.',
 1, '2025-04-20 09:00:00'),
(4, 3, 4,
 'Monsieur Al-Rashid, dans le cadre de notre procédure de vérification, nous vous demandons de fournir : 1) Justificatif de mandat politique, 2) Déclaration de patrimoine, 3) Source des fonds. Délai : 30 jours.',
 0, '2025-05-11 09:00:00');

-- -----------------------------------------------------------------------------
-- Notifications de démonstration
-- -----------------------------------------------------------------------------
INSERT INTO notifications (user_id, case_id, titre, message, type, lu, created_at) VALUES
(2, 1, 'Réponse client reçue',
 'Le client Nguyen Thanh a répondu à votre demande de documents pour le dossier AML-EDD-2025-00001.',
 'info', 1, '2025-04-16 14:31:00'),
(2, 2, 'ALERTE SANCTIONS — Action requise',
 'Le dossier AML-EDD-2025-00002 nécessite une déclaration TRACFIN avant le 30 juin 2025.',
 'aml_alerte', 0, '2025-04-20 09:01:00'),
(1, 2, 'Dossier sanctions escaladé',
 'Le dossier AML-EDD-2025-00002 (Youssef Al-Rashid) a été escaladé au niveau 2 par l agent Dubois Marie.',
 'alerte', 0, '2025-04-20 09:05:00'),
(3, 4, 'Nouveau dossier PEP assigné',
 'Le dossier AML-EDD-2025-00004 (PEP Youssef Al-Rashid) vous a été assigné. Échéance : 15 septembre 2025.',
 'info', 0, '2025-05-10 10:01:00'),
(4, 1, 'Votre dossier est en cours d examen',
 'Votre dossier AML-EDD-2025-00001 est actuellement examiné par notre équipe conformité. Nous vous contacterons sous 5 jours ouvrés.',
 'info', 1, '2025-04-11 09:05:00');

-- -----------------------------------------------------------------------------
-- Étiquettes appliquées aux dossiers
-- -----------------------------------------------------------------------------
INSERT INTO case_tags (case_id, tag_id, assigned_by, assigned_at) VALUES
(1, 3, 2, '2025-04-10 09:30:00'), -- Dossier 1 → Crypto
(1, 7, 2, '2025-04-10 09:30:00'), -- Dossier 1 → EDD Requis
(1, 6, 2, '2025-04-11 10:00:00'), -- Dossier 1 → Risque Élevé
(2, 4, 1, '2025-03-20 15:00:00'), -- Dossier 2 → Sanctions
(2, 1, 1, '2025-03-20 15:00:00'), -- Dossier 2 → Prioritaire
(2, 6, 2, '2025-03-21 08:00:00'), -- Dossier 2 → Risque Élevé
(3, 7, 3, '2025-05-01 08:30:00'), -- Dossier 3 → EDD Requis
(4, 2, 3, '2025-05-10 10:15:00'), -- Dossier 4 → PEP
(4, 7, 3, '2025-05-10 10:15:00'), -- Dossier 4 → EDD Requis
(5, 7, 2, '2025-05-15 11:30:00'); -- Dossier 5 → EDD Requis

-- -----------------------------------------------------------------------------
-- Règles métier AML configurables
-- -----------------------------------------------------------------------------
INSERT INTO business_rules (code, libelle, type_regle, valeur_seuil, condition_json, action_json, active, priorite, created_by) VALUES
('SEUIL_CASH_10K',
 'Alerte déclaration espèces ≥ 10 000 EUR',
 'seuil', 10000.00,
 '{"type_actif": "cash", "operateur": ">=", "montant": 10000}',
 '{"notifier_agent": true, "creer_checklist": true, "priorite": "haute"}',
 1, 10, 1),

('SCORE_CRITIQUE_85',
 'Blocage automatique si score risque > 85',
 'blocage', 85.00,
 '{"score_risque": {"operateur": ">", "valeur": 85}}',
 '{"geler_compte": true, "notifier_compliance": true, "escalade_niveau": 2}',
 1, 5, 1),

('PEP_EDD_FORCE',
 'Enquête EDD obligatoire pour tout PEP identifié',
 'workflow', NULL,
 '{"type_cas": "pep"}',
 '{"creer_checklist_edd": true, "assigner_senior": true, "notifier_direction": false}',
 1, 20, 1),

('PAYS_LISTE_NOIRE',
 'Alerte critique pour transactions pays liste noire FATF',
 'alerte', NULL,
 '{"pays_liste_noire": true}',
 '{"priorite": "critique", "notifier_agent": true, "notifier_compliance": true, "tag": "Sanctions"}',
 1, 1, 1),

('CRYPTO_MONITORING',
 'Surveillance renforcée des flux crypto > 5 000 EUR',
 'scoring', 5000.00,
 '{"type_actif": "crypto", "operateur": ">", "montant": 5000}',
 '{"bonus_score": 20, "notifier_agent": true, "tag": "Crypto"}',
 1, 15, 1),

('FRACTIONNEMENT_DETECTION',
 'Détection de fractionnement : 5 transactions < 3 000 EUR en 30 jours',
 'alerte', 3000.00,
 '{"nb_transactions": {"operateur": ">=", "valeur": 5}, "montant_unitaire": {"operateur": "<", "valeur": 3000}, "periode_jours": 30}',
 '{"creer_dossier_aml": true, "priorite": "haute", "notifier_agent": true}',
 1, 8, 1);

-- -----------------------------------------------------------------------------
-- Configuration système
-- -----------------------------------------------------------------------------
INSERT INTO system_config (cle, valeur, description) VALUES
('app_nom',               'BofaDueDiligence',                          'Nom de l application'),
('app_version',           '2.0',                                        'Version courante'),
('app_environnement',     'developpement',                              'Environnement : developpement, recette, production'),
('session_timeout',       '1800',                                       'Durée d inactivité avant expiration de session (secondes)'),
('upload_max_size',       '10485760',                                   'Taille maximale des uploads en octets (10 Mo)'),
('upload_types_autorises','pdf,jpg,jpeg,png,doc,docx,xls,xlsx',        'Extensions de fichiers autorisées'),
('score_alerte_seuil',    '60',                                         'Score de risque à partir duquel une alerte est générée'),
('score_critique_seuil',  '85',                                         'Score de risque à partir duquel un blocage est déclenché'),
('tracfin_delai_jours',   '30',                                         'Délai réglementaire de déclaration TRACFIN en jours'),
('pagination_par_page',   '20',                                         'Nombre d enregistrements par page par défaut'),
('maintenance_mode',      '0',                                          'Mode maintenance : 1=actif, 0=inactif'),
('email_compliance',      'compliance@bofa.fr',                         'Adresse e-mail de l équipe conformité'),
('email_direction',       'direction@bofa.fr',                          'Adresse e-mail de la direction'),
('log_niveau',            'WARNING',                                    'Niveau de journalisation : DEBUG, INFO, WARNING, ERROR'),
('bcrypt_cost',           '12',                                         'Coût BCRYPT pour le hachage des mots de passe');

-- -----------------------------------------------------------------------------
-- Entrées d'audit initiales (installation)
-- -----------------------------------------------------------------------------
INSERT INTO audit_log (user_id, action, table_name, record_id, old_value, new_value, ip_address, created_at) VALUES
(1, 'INSTALL', 'system',  0, NULL, '{"version":"2.0","message":"Installation initiale de la base de données"}', '127.0.0.1', NOW()),
(1, 'CREATE',  'users',   1, NULL, '{"email":"admin@bofa.fr","role":"admin"}',                                  '127.0.0.1', NOW()),
(1, 'CREATE',  'cases',   1, NULL, '{"case_number":"AML-EDD-2025-00001","type":"aml"}',                        '127.0.0.1', NOW()),
(1, 'CREATE',  'cases',   2, NULL, '{"case_number":"AML-EDD-2025-00002","type":"sanctions"}',                  '127.0.0.1', NOW()),
(1, 'CREATE',  'cases',   3, NULL, '{"case_number":"AML-EDD-2025-00003","type":"edd"}',                        '127.0.0.1', NOW()),
(1, 'CREATE',  'cases',   4, NULL, '{"case_number":"AML-EDD-2025-00004","type":"pep"}',                        '127.0.0.1', NOW()),
(1, 'CREATE',  'cases',   5, NULL, '{"case_number":"AML-EDD-2025-00005","type":"kyc"}',                        '127.0.0.1', NOW());
