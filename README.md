# BofaDueDiligence — Plateforme AML/KYC

Plateforme de conformité Anti-Money Laundering (AML) inspirée de Bank of America. Application web PHP/MySQL pour la gestion des dossiers de due diligence, le suivi des fonds suspects et la vérification de conformité.

## Fonctionnalités

### Client
- Tableau de bord avec vue d'ensemble des dossiers et fonds en attente
- Consultation détaillée des dossiers (statut, sous-comptes, historique)
- Téléversement de documents justificatifs
- Suivi de la checklist de conformité
- Messagerie avec l'agent de conformité
- Historique des transferts
- Gestion du profil

### Agent de Conformité
- Tableau de bord avec statistiques et graphique de répartition (Chart.js)
- Liste des dossiers avec filtres (statut, risque, recherche)
- Analyse détaillée des dossiers en 5 onglets
- Gel / Dégel des fonds
- Validation / Rejet des dossiers (double validation)
- Validation / Rejet des documents
- Gestion de la checklist
- Journal d'audit par dossier
- Export CSV des dossiers

### Super Admin
- Tableau de bord global avec KPIs
- Gestion des utilisateurs (CRUD, activation/désactivation, reset mot de passe)
- Attribution des agents aux dossiers
- Configuration des pays à risque (niveaux 1-5)
- Configuration des types d'actifs à risque
- Paramètres globaux (seuils de risque, devise par défaut)
- Journal d'audit complet avec pagination
- Vue de tous les dossiers
- Export CSV

### Transversal
- Calcul automatique du score de risque (montant + pays + actif)
- Notifications en temps réel (polling AJAX)
- Recherche globale
- Identifiants de dossier uniques (AML-YYYYMMDD-XXXX)
- Journalisation complète des actions (audit log)
- Protection CSRF sur tous les formulaires
- Mots de passe hashés (bcrypt)
- Requêtes préparées (PDO)

## Prérequis

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache avec `mod_rewrite` activé
- Extensions PHP : `pdo_mysql`, `mbstring`, `json`

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/Jbrownwhistler/BofaDueDiligence.git
cd BofaDueDiligence
```

### 2. Créer la base de données

```bash
mysql -u root -p < install.sql
```

Cela crée la base `bofa_due_diligence` avec toutes les tables et les données de démonstration.

### 3. Configurer la connexion

Éditer `src/config.php` si nécessaire :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bofa_due_diligence');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', '/BofaDueDiligence/public/');
```

### 4. Configurer Apache

Pointer le `DocumentRoot` vers le dossier `public/` ou utiliser un alias :

```apache
Alias /BofaDueDiligence/public /chemin/vers/BofaDueDiligence/public
<Directory /chemin/vers/BofaDueDiligence/public>
    AllowOverride All
    Require all granted
</Directory>
```

S'assurer que `mod_rewrite` est activé :

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 5. Permissions

```bash
chmod 755 uploads/
```

### 6. Accéder à l'application

Ouvrir `http://localhost/BofaDueDiligence/public/` dans le navigateur.

## Comptes de démonstration

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Super Admin | admin@bofa.com | password |
| Agent | agent@bofa.com | password |
| Agent 2 | agent2@bofa.com | password |
| Client | client@bofa.com | password |
| Client 2 | client2@bofa.com | password |

## Architecture

```
BofaDueDiligence/
├── public/                  # Document root
│   ├── index.php            # Front controller
│   ├── .htaccess            # URL rewriting
│   └── assets/
│       ├── css/style.css    # Thème BofA
│       └── js/app.js        # JS (notifications, sidebar, etc.)
├── src/
│   ├── config.php           # Configuration DB + constantes
│   ├── autoload.php         # Chargement automatique des classes
│   ├── routes.php           # Définition des routes
│   ├── Helpers/
│   │   ├── Session.php      # Gestion des sessions
│   │   ├── CSRF.php         # Protection CSRF
│   │   ├── Auth.php         # Authentification et autorisation
│   │   ├── AuditLog.php     # Journalisation
│   │   ├── RiskCalculator.php # Calcul du score de risque
│   │   └── Notify.php       # Système de notifications
│   ├── Models/
│   │   ├── User.php         # Modèle utilisateur
│   │   ├── CaseModel.php    # Modèle dossier AML
│   │   └── Account.php      # Comptes, sous-comptes, documents, messages, checklist
│   └── Controllers/
│       ├── AuthController.php   # Login / Logout
│       ├── ClientController.php # Espace client
│       ├── AgentController.php  # Espace agent
│       ├── AdminController.php  # Espace admin
│       └── ApiController.php    # API (notifications, recherche, export)
├── templates/
│   ├── layouts/             # Header, sidebar, footer
│   ├── auth/                # Page de connexion
│   ├── client/              # Templates client
│   ├── agent/               # Templates agent
│   ├── admin/               # Templates admin
│   └── errors/              # Pages d'erreur
├── uploads/                 # Documents téléversés
└── install.sql              # Script d'installation DB
```

## Stack technique

- **Backend** : PHP 8+ (vanilla, sans framework)
- **Base de données** : MySQL / MariaDB
- **Frontend** : Bootstrap 5.3, FontAwesome 6, Chart.js 4
- **Design** : Thème Bank of America (Navy #012169, Rouge #E31837)
- **Sécurité** : PDO prepared statements, bcrypt, CSRF tokens, sessions sécurisées

## Licence

Usage éducatif uniquement. Ce projet n'est pas affilié à Bank of America.
