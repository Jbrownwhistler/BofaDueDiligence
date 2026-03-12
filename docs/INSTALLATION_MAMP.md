# Guide d'installation — MAMP

Ce guide décrit les étapes nécessaires pour installer et exécuter la plateforme **BofaDueDiligence** dans un environnement de développement local avec [MAMP](https://www.mamp.info/).

---

## Table des matières

1. [Prérequis](#prérequis)
2. [Installation de MAMP](#installation-de-mamp)
3. [Configuration de MAMP](#configuration-de-mamp)
4. [Mise en place du projet](#mise-en-place-du-projet)
5. [Création de la base de données](#création-de-la-base-de-données)
6. [Configuration de l'application](#configuration-de-lapplication)
7. [Permissions des répertoires](#permissions-des-répertoires)
8. [Lancement et vérification](#lancement-et-vérification)
9. [Comptes par défaut](#comptes-par-défaut)
10. [Dépannage](#dépannage)

---

## Prérequis

| Composant | Version minimale |
|-----------|-----------------|
| MAMP (ou MAMP PRO) | 6.x |
| PHP | 7.4+ |
| MySQL | 8.0+ |
| Apache | avec `mod_rewrite` activé |

> **Note :** MAMP est disponible pour macOS et Windows. Les chemins indiqués ci-dessous sont donnés pour macOS. Adaptez-les si vous utilisez Windows (par exemple `C:\MAMP\htdocs\`).

---

## Installation de MAMP

1. Téléchargez MAMP depuis le site officiel : <https://www.mamp.info/en/downloads/>
2. Lancez l'installateur et suivez les instructions.
3. Une fois installé, ouvrez l'application **MAMP**.

---

## Configuration de MAMP

### Vérifier la version de PHP

1. Ouvrez MAMP → **Préférences** (ou **Settings**) → **PHP**.
2. Sélectionnez une version **≥ 7.4**.

### Vérifier les ports

Par défaut, MAMP utilise les ports suivants :

| Service | Port par défaut |
|---------|----------------|
| Apache  | **8888**       |
| MySQL   | **8889**       |

> L'application est préconfigurée pour le port MySQL **8889**. Si vous modifiez ce port, vous devrez aussi mettre à jour le fichier `config.php`.

### Activer `mod_rewrite` (Apache)

`mod_rewrite` est généralement activé par défaut dans MAMP. Pour le vérifier :

1. Ouvrez le fichier de configuration Apache de MAMP :
   - **macOS :** `/Applications/MAMP/conf/apache/httpd.conf`
   - **Windows :** `C:\MAMP\conf\apache\httpd.conf`
2. Recherchez la ligne suivante et assurez-vous qu'elle n'est **pas** commentée (pas de `#` devant) :
   ```apache
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
3. Recherchez le bloc `<Directory>` correspondant à `htdocs` et vérifiez que `AllowOverride` est défini à `All` :
   ```apache
   <Directory "/Applications/MAMP/htdocs">
       AllowOverride All
   </Directory>
   ```
4. Redémarrez les serveurs MAMP après toute modification.

---

## Mise en place du projet

### Cloner le dépôt

Clonez le projet dans le répertoire `htdocs` de MAMP :

```bash
cd /Applications/MAMP/htdocs   # macOS
# ou
cd C:\MAMP\htdocs               # Windows

git clone https://github.com/Jbrownwhistler/BofaDueDiligence.git
```

Cela crée le dossier `/Applications/MAMP/htdocs/BofaDueDiligence/`.

### Structure attendue

```
BofaDueDiligence/
├── config.php          # Configuration centrale
├── install.sql         # Schéma de base de données
├── .htaccess           # Règles Apache et en-têtes de sécurité
├── src/                # Classes PHP (logique métier)
├── public/             # Racine web (point d'entrée)
│   ├── index.php
│   ├── login.php
│   └── assets/         # CSS, JS
├── agent/              # Pages agent (officier conformité)
├── client/             # Pages client (utilisateur bancaire)
├── templates/          # En-têtes, pieds de page, composants
├── uploads/            # Répertoire des fichiers téléversés
└── logs/               # Répertoire des journaux applicatifs
```

---

## Création de la base de données

### Option A — Via phpMyAdmin (interface graphique)

1. Démarrez les serveurs MAMP (bouton **Start**).
2. Ouvrez phpMyAdmin : <http://localhost:8888/phpMyAdmin/> (ou via le menu MAMP → **Open WebStart page** → **Tools** → **phpMyAdmin**).
3. Cliquez sur l'onglet **SQL**.
4. Copiez-collez le contenu du fichier `install.sql` dans la zone de texte.
5. Cliquez sur **Exécuter** (ou **Go**).

> Le script crée automatiquement la base de données `bofa_due_diligence`, toutes les tables, et insère les données initiales (comptes utilisateurs, pays à risque, etc.).

### Option B — Via le terminal

```bash
# Accédez au binaire MySQL de MAMP
# macOS :
/Applications/MAMP/Library/bin/mysql -u root -p < /Applications/MAMP/htdocs/BofaDueDiligence/install.sql

# Windows :
C:\MAMP\bin\mysql\bin\mysql.exe -u root -p < C:\MAMP\htdocs\BofaDueDiligence\install.sql
```

Lorsque le mot de passe est demandé, entrez : `root` (mot de passe par défaut de MAMP).

---

## Configuration de l'application

Le fichier `config.php` contient la configuration par défaut pour MAMP. Voici les paramètres principaux :

```php
// Connexion à la base de données (valeurs par défaut MAMP)
'host'     => 'localhost',
'port'     => '8889',
'dbname'   => 'bofa_due_diligence',
'user'     => 'root',
'password' => 'root',
'charset'  => 'utf8mb4',
```

### Environnement de développement

Aucune modification n'est nécessaire pour un environnement MAMP standard. Les valeurs par défaut conviennent.

### Environnement de production

Pour la production, définissez les variables d'environnement suivantes :

| Variable | Description |
|----------|-------------|
| `BOFA_DB_HOST` | Hôte de la base de données |
| `BOFA_DB_PORT` | Port MySQL |
| `BOFA_DB_NAME` | Nom de la base de données |
| `BOFA_DB_USER` | Utilisateur MySQL |
| `BOFA_DB_PASS` | Mot de passe MySQL |
| `BOFA_ENV` | Définir à `production` |

---

## Permissions des répertoires

Assurez-vous que les répertoires `uploads/` et `logs/` sont accessibles en écriture par le serveur web :

```bash
cd /Applications/MAMP/htdocs/BofaDueDiligence

# Créer les répertoires s'ils n'existent pas
mkdir -p uploads logs

# Définir les permissions (macOS/Linux)
chmod 755 uploads logs
```

> **Windows :** Faites un clic droit sur chaque dossier → **Propriétés** → **Sécurité** et assurez-vous que l'utilisateur du serveur a les droits d'écriture.

---

## Lancement et vérification

1. **Démarrez les serveurs MAMP** (Apache et MySQL).
2. Ouvrez votre navigateur et accédez à :
   ```
   http://localhost:8888/BofaDueDiligence/public/
   ```
3. Vous devriez voir la page de connexion de l'application.

> **Astuce :** Si vous utilisez MAMP PRO, vous pouvez configurer un Virtual Host (par exemple `bofa.local`) pointant vers le dossier `public/` du projet pour une URL plus propre.

---

## Comptes par défaut

Après l'import du fichier `install.sql`, les comptes suivants sont disponibles :

| Rôle | Description |
|------|-------------|
| **admin** | Administrateur système — accès complet |
| **agent** | Officier de conformité AML — gestion des dossiers |
| **client** | Client bancaire — consultation et soumission de documents |

> Les identifiants par défaut se trouvent dans le fichier `install.sql`. Il est fortement recommandé de modifier les mots de passe après la première connexion.

---

## Dépannage

### Erreur 404 — Page introuvable

- Vérifiez que `mod_rewrite` est bien activé (voir section [Configuration de MAMP](#configuration-de-mamp)).
- Vérifiez que `AllowOverride All` est défini dans la configuration Apache.
- Redémarrez les serveurs MAMP après modification.

### Erreur de connexion à la base de données

- Vérifiez que MySQL est bien démarré dans MAMP (indicateur vert).
- Vérifiez que le port MySQL dans `config.php` correspond au port configuré dans MAMP (par défaut : **8889**).
- Vérifiez que la base de données `bofa_due_diligence` existe dans phpMyAdmin.

### Page blanche

- Activez l'affichage des erreurs PHP dans MAMP :
  - Ouvrez le fichier `php.ini` de MAMP :
    - **macOS :** `/Applications/MAMP/bin/php/phpX.X.X/conf/php.ini`
    - **Windows :** `C:\MAMP\bin\php\phpX.X.X\conf\php.ini`
  - Recherchez et modifiez :
    ```ini
    display_errors = On
    error_reporting = E_ALL
    ```
  - Redémarrez les serveurs MAMP.

### Problèmes de téléversement de fichiers

- Vérifiez les permissions du dossier `uploads/` (voir section [Permissions des répertoires](#permissions-des-répertoires)).
- Vérifiez la taille maximale de téléversement dans `php.ini` :
  ```ini
  upload_max_filesize = 10M
  post_max_size = 12M
  ```

### Erreur CSRF ou session expirée

- Videz les cookies de votre navigateur pour le domaine `localhost`.
- Vérifiez que la durée de session est suffisante (par défaut : 30 minutes dans `config.php`).

---

## Ressources utiles

- [Documentation MAMP](https://documentation.mamp.info/)
- [PHP — Documentation officielle](https://www.php.net/docs.php)
- [MySQL 8.0 — Manuel de référence](https://dev.mysql.com/doc/refman/8.0/en/)
