<?php
/**
 * Fichier de configuration central — BofaDueDiligence
 * Application de conformité AML/EDD bancaire
 *
 * @version 2.0
 */

define('BOFA_APP', true);

// ---------------------------------------------------------------------------
// Constantes de base de données
// Les valeurs ci-dessous sont des valeurs par défaut pour l'environnement
// de développement (MAMP). En production, définir les variables d'environnement
// suivantes sur le serveur pour surcharger ces valeurs :
//   BOFA_DB_HOST, BOFA_DB_PORT, BOFA_DB_NAME, BOFA_DB_USER, BOFA_DB_PASS
// Ne jamais committer des identifiants de production dans le dépôt.
// ---------------------------------------------------------------------------
define('DB_HOST',    getenv('BOFA_DB_HOST') ?: 'localhost');
define('DB_PORT',    getenv('BOFA_DB_PORT') ?: '8889');
define('DB_NAME',    getenv('BOFA_DB_NAME') ?: 'bofa_due_diligence');
define('DB_USER',    getenv('BOFA_DB_USER') ?: 'root');
define('DB_PASS',    getenv('BOFA_DB_PASS') ?: 'root');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------------
// Constantes applicatives
// ---------------------------------------------------------------------------
define('BOFA_VERSION',    '2.0');
define('BOFA_URL',        'http://localhost:8888/bofa/public');
define('BOFA_ROOT',       dirname(__FILE__));
define('BOFA_UPLOAD_DIR', BOFA_ROOT . '/uploads');
define('BOFA_UPLOAD_MAX', 10485760); // 10 Mo

// ---------------------------------------------------------------------------
// Configuration des sessions sécurisées
// ---------------------------------------------------------------------------
ini_set('session.cookie_httponly', '1');
// Activer cookie_secure en production HTTPS via variable d'environnement
ini_set('session.cookie_secure',   getenv('BOFA_ENV') === 'production' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime',  '1800');

define('BOFA_SESSION_TIMEOUT', 1800); // 30 minutes en secondes

// Démarrer la session si elle n'est pas encore active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------------
// Connexion PDO — instance singleton
// ---------------------------------------------------------------------------

/**
 * Retourne l'instance PDO singleton vers la base de données.
 * Utilise les constantes DB_* définies ci-dessus.
 *
 * @return PDO
 * @throws RuntimeException si la connexion échoue
 */
function bofa_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Journaliser l'erreur sans exposer les identifiants
            error_log('[BofaDueDiligence] Échec de connexion PDO : ' . $e->getMessage());
            throw new RuntimeException('Connexion à la base de données impossible. Veuillez réessayer.');
        }
    }

    return $pdo;
}

// ---------------------------------------------------------------------------
// Journal d'audit
// ---------------------------------------------------------------------------

/**
 * Insère une entrée dans la table audit_log pour traçabilité réglementaire.
 *
 * @param int         $userId   Identifiant de l'utilisateur auteur de l'action
 * @param string      $action   Libellé de l'action effectuée (ex. 'UPDATE', 'DELETE')
 * @param string      $table    Nom de la table concernée
 * @param int         $recordId Identifiant de l'enregistrement modifié
 * @param mixed|null  $oldVal   Valeur avant modification (encodée JSON)
 * @param mixed|null  $newVal   Valeur après modification (encodée JSON)
 */
function bofa_audit(
    int    $userId,
    string $action,
    string $table,
    int    $recordId,
    mixed  $oldVal = null,
    mixed  $newVal = null
): void {
    try {
        $db  = bofa_db();
        $sql = "INSERT INTO audit_log
                    (user_id, action, table_name, record_id, old_value, new_value, ip_address, user_agent, created_at)
                VALUES
                    (:user_id, :action, :table_name, :record_id, :old_value, :new_value, :ip, :ua, NOW())";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id'    => $userId,
            ':action'     => $action,
            ':table_name' => $table,
            ':record_id'  => $recordId,
            ':old_value'  => $oldVal !== null ? json_encode($oldVal, JSON_UNESCAPED_UNICODE) : null,
            ':new_value'  => $newVal !== null ? json_encode($newVal, JSON_UNESCAPED_UNICODE) : null,
            ':ip'         => $_SERVER['REMOTE_ADDR']     ?? '0.0.0.0',
            ':ua'         => $_SERVER['HTTP_USER_AGENT'] ?? 'inconnu',
        ]);
    } catch (PDOException $e) {
        error_log('[BofaDueDiligence] Erreur audit_log : ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Calcul du score de risque AML/EDD
// ---------------------------------------------------------------------------

/**
 * Calcule le score de risque normalisé sur 100 pour un dossier AML/EDD.
 *
 * Formule : Score brut = montant × coeff_pays × coeff_actif
 * Normalisation : score = min(100, valeur_brute / 250 000 × 100)
 * (valeur maximale théorique : 10 000 000 × 5 × 5 = 250 000 000)
 *
 * @param float  $montant   Montant de la transaction ou du dossier
 * @param string $codePays  Code ISO 3166-1 alpha-2 du pays (ex. 'FR', 'IR')
 * @param string $typeActif Type d'actif (ex. 'crypto', 'cash', 'immobilier')
 * @return float Score de risque entre 0.00 et 100.00
 */
function bofa_calculer_score(float $montant, string $codePays, string $typeActif): float
{
    try {
        $db = bofa_db();

        // Récupérer le coefficient du pays
        $stmtPays = $db->prepare(
            "SELECT coefficient FROM risk_countries WHERE code_iso = :code LIMIT 1"
        );
        $stmtPays->execute([':code' => strtoupper(trim($codePays))]);
        $coeffPays = (float) ($stmtPays->fetchColumn() ?: 1.0);

        // Récupérer le coefficient du type d'actif
        $stmtActif = $db->prepare(
            "SELECT coefficient FROM risk_asset_types WHERE code = :code LIMIT 1"
        );
        $stmtActif->execute([':code' => strtolower(trim($typeActif))]);
        $coeffActif = (float) ($stmtActif->fetchColumn() ?: 1.0);

        // Calcul du score brut et normalisation
        $scoreBrut = $montant * $coeffPays * $coeffActif;
        $scoreNorm = ($scoreBrut / 250000000.0) * 100.0;

        return round(min(100.0, max(0.0, $scoreNorm)), 2);

    } catch (PDOException $e) {
        error_log('[BofaDueDiligence] Erreur calcul score : ' . $e->getMessage());
        return 0.0;
    }
}

// ---------------------------------------------------------------------------
// Protection CSRF
// ---------------------------------------------------------------------------

/**
 * Génère ou retourne le jeton CSRF stocké en session.
 * Crée un nouveau jeton si aucun n'existe.
 *
 * @return string Jeton CSRF hexadécimal (64 caractères)
 */
function bofa_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valide le jeton CSRF soumis par le formulaire.
 * Régénère un nouveau jeton après validation réussie.
 *
 * @param string $token Jeton soumis via le formulaire
 * @return bool true si le jeton est valide, false sinon
 */
function bofa_csrf_validate(string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!hash_equals($sessionToken, $token)) {
        return false;
    }

    // Régénérer le jeton après utilisation (rotation)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return true;
}

// ---------------------------------------------------------------------------
// Contrôle d'authentification et d'autorisation
// ---------------------------------------------------------------------------

/**
 * Vérifie que l'utilisateur est connecté et possède le rôle requis.
 * Redirige vers la page de connexion en cas d'échec.
 * Expire la session après BOFA_SESSION_TIMEOUT secondes d'inactivité.
 *
 * @param array $roles Liste des rôles autorisés (vide = tout utilisateur connecté)
 */
function bofa_auth_check(array $roles = []): void
{
    // Vérifier l'expiration de la session
    if (isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > BOFA_SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            bofa_redirect(BOFA_URL . '/login.php?timeout=1');
        }
    }
    $_SESSION['last_activity'] = time();

    // Vérifier la présence d'un utilisateur connecté
    if (empty($_SESSION['user_id'])) {
        bofa_redirect(BOFA_URL . '/login.php');
    }

    // Vérifier le rôle si une liste est fournie
    if (!empty($roles) && !in_array($_SESSION['user_role'] ?? '', $roles, true)) {
        http_response_code(403);
        bofa_redirect(BOFA_URL . '/403.php');
    }
}

// ---------------------------------------------------------------------------
// Assainissement des entrées
// ---------------------------------------------------------------------------

/**
 * Assainit une chaîne en appliquant htmlspecialchars et trim.
 * Utilisé pour afficher des données issues de l'utilisateur en toute sécurité.
 *
 * @param string $input Chaîne brute à assainir
 * @return string Chaîne assainie
 */
function bofa_sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Messages flash
// ---------------------------------------------------------------------------

/**
 * Stocke un message flash en session pour affichage unique.
 *
 * @param string $msg  Contenu du message
 * @param string $type Catégorie visuelle : 'success', 'error', 'warning', 'info'
 */
function bofa_flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash_messages'][] = [
        'message' => $msg,
        'type'    => $type,
    ];
}

/**
 * Récupère et efface tous les messages flash en session.
 *
 * @return array Liste des messages flash [['message' => ..., 'type' => ...], ...]
 */
function bofa_get_flash(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

// ---------------------------------------------------------------------------
// Génération de l'identifiant unique de dossier AML
// ---------------------------------------------------------------------------

/**
 * Génère un identifiant de dossier au format AML-EDD-YYYY-XXXXX.
 * L'incrément est basé sur le numéro de séquence le plus élevé
 * existant dans la table cases pour l'année courante.
 *
 * Exemple : AML-EDD-2025-00001, AML-EDD-2025-00042
 *
 * @return string Identifiant unique formaté
 */
function bofa_generate_case_id(): string
{
    $annee = date('Y');

    try {
        $db   = bofa_db();
        $stmt = $db->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(case_number, '-', -1) AS UNSIGNED)) AS max_seq
             FROM cases
             WHERE case_number LIKE :pattern"
        );
        $stmt->execute([':pattern' => "AML-EDD-{$annee}-%"]);
        $row    = $stmt->fetch();
        $suivant = (int) ($row['max_seq'] ?? 0) + 1;

    } catch (PDOException $e) {
        error_log('[BofaDueDiligence] Erreur génération case_id : ' . $e->getMessage());
        $suivant = 1;
    }

    return sprintf('AML-EDD-%s-%05d', $annee, $suivant);
}

// ---------------------------------------------------------------------------
// Redirection HTTP
// ---------------------------------------------------------------------------

/**
 * Redirige le navigateur vers l'URL indiquée et termine l'exécution.
 *
 * @param string $url URL cible (absolue ou relative)
 */
function bofa_redirect(string $url): void
{
    header('Location: ' . $url, true, 302);
    exit();
}

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

/**
 * Calcule les données de pagination nécessaires à l'affichage d'une liste.
 *
 * @param int $total       Nombre total d'enregistrements
 * @param int $perPage     Nombre d'enregistrements par page
 * @param int $currentPage Page courante (commence à 1)
 * @return array Tableau contenant :
 *   - totalPages  : nombre total de pages
 *   - offset      : décalage SQL (OFFSET)
 *   - currentPage : page courante validée
 *   - perPage     : enregistrements par page
 *   - total       : total d'enregistrements
 *   - hasPrev     : bool — page précédente disponible
 *   - hasNext     : bool — page suivante disponible
 *   - prevPage    : numéro de la page précédente
 *   - nextPage    : numéro de la page suivante
 */
function bofa_paginate(int $total, int $perPage, int $currentPage): array
{
    // Valeurs plancher pour éviter les états incohérents
    $perPage     = max(1, $perPage);
    $totalPages  = max(1, (int) ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset      = ($currentPage - 1) * $perPage;

    return [
        'total'       => $total,
        'perPage'     => $perPage,
        'totalPages'  => $totalPages,
        'currentPage' => $currentPage,
        'offset'      => $offset,
        'hasPrev'     => $currentPage > 1,
        'hasNext'     => $currentPage < $totalPages,
        'prevPage'    => max(1, $currentPage - 1),
        'nextPage'    => min($totalPages, $currentPage + 1),
    ];
}
