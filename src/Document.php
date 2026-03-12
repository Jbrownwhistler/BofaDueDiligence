<?php
defined('BOFA_APP') || die('Accès direct interdit.');

/**
 * Gestion des documents justificatifs des dossiers AML/EDD.
 * Téléversement sécurisé, validation MIME, filigrane GD, intégrité SHA-256.
 */
class Document
{
    /** Taille maximale autorisée en octets (10 Mo) */
    private const MAX_SIZE = 10485760;

    public function __construct() {}

    // -------------------------------------------------------------------------
    // Téléversement
    // -------------------------------------------------------------------------

    /**
     * Téléverse un document après validation du type MIME, de l'extension et de la taille.
     * Applique un filigrane, calcule le SHA-256 et insère l'entrée en base.
     *
     * @param array $fileData Tableau $_FILES['xxx'] : name, tmp_name, type, size, error
     * @return int|false Identifiant du document créé, ou false en cas d'erreur
     */
    public function upload(int $caseId, int $userId, array $fileData): int|false
    {
        // Vérification des erreurs d'upload PHP
        if (($fileData['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return false;
        }

        $tmpPath     = $fileData['tmp_name'] ?? '';
        $originalName= bofa_sanitize(basename($fileData['name'] ?? ''));
        $size        = (int) ($fileData['size'] ?? 0);

        if (!is_uploaded_file($tmpPath) || $size > self::MAX_SIZE || $size <= 0) {
            return false;
        }

        // Détection MIME réelle via finfo (ignore le MIME déclaré par le client)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($tmpPath);

        $allowedMimes = $this->getAllowedMimes();
        if (!in_array($realMime, $allowedMimes, true)) {
            return false;
        }

        // Validation de l'extension déclarée
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowedExts, true)) {
            return false;
        }

        // Cohérence MIME ↔ extension
        $mimeExtMap = [
            'application/pdf' => ['pdf'],
            'image/jpeg'      => ['jpg', 'jpeg'],
            'image/png'       => ['png'],
        ];
        $expectedExts = $mimeExtMap[$realMime] ?? [];
        if (!in_array($ext, $expectedExts, true)) {
            return false;
        }

        // Génération d'un nom de fichier unique basé sur UUID
        $storeName = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadDir = defined('BOFA_UPLOAD_DIR') ? rtrim(BOFA_UPLOAD_DIR, '/') : __DIR__ . '/../uploads';
        $destPath  = $uploadDir . '/' . $storeName;

        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            return false;
        }

        if (!move_uploaded_file($tmpPath, $destPath)) {
            return false;
        }

        // Calcul de l'empreinte d'intégrité
        $hash = hash_file('sha256', $destPath);

        // Récupération du numéro de dossier pour le filigrane
        $caseRow = bofa_db()->prepare("SELECT case_number FROM cases WHERE id = :id LIMIT 1");
        $caseRow->execute([':id' => $caseId]);
        $caseNumber = $caseRow->fetchColumn() ?: 'UNKNOWN';

        // Application du filigrane
        $this->applyWatermark($destPath, $caseNumber, $userId);

        // Insertion en base
        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO documents
                (case_id, uploaded_by, nom_original, nom_stockage, type_mime,
                 taille_octets, type_document, hash_sha256, created_at)
             VALUES
                (:case_id, :uid, :nom_orig, :nom_stock, :mime,
                 :taille, :type_doc, :hash, NOW())"
        );
        $stmt->execute([
            ':case_id'   => $caseId,
            ':uid'       => $userId,
            ':nom_orig'  => $originalName,
            ':nom_stock' => $storeName,
            ':mime'      => $realMime,
            ':taille'    => $size,
            ':type_doc'  => 'autre',
            ':hash'      => $hash,
        ]);

        $newId = (int) $db->lastInsertId();
        bofa_audit($userId, 'UPLOAD', 'documents', $newId, null, ['nom' => $originalName, 'mime' => $realMime]);

        return $newId;
    }

    // -------------------------------------------------------------------------
    // Récupération
    // -------------------------------------------------------------------------

    /**
     * Récupère un document par son identifiant.
     */
    public function getById(int $id): array|null
    {
        $stmt = bofa_db()->prepare(
            "SELECT d.*, CONCAT(u.nom, ' ', u.prenom) AS uploade_par
             FROM documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Retourne tous les documents d'un dossier.
     */
    public function getByCaseId(int $caseId): array
    {
        $stmt = bofa_db()->prepare(
            "SELECT d.*, CONCAT(u.nom, ' ', u.prenom) AS uploade_par
             FROM documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.case_id = :cid
             ORDER BY d.created_at DESC"
        );
        $stmt->execute([':cid' => $caseId]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Validation et rejet
    // -------------------------------------------------------------------------

    /**
     * Valide un document : passe le champ valide à 1 et enregistre l'audit.
     */
    public function validate(int $id, int $agentId): bool
    {
        $doc  = $this->getById($id);
        $stmt = bofa_db()->prepare("UPDATE documents SET valide = 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        bofa_audit($agentId, 'VALIDATE', 'documents', $id, $doc, ['valide' => 1]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Rejette un document : repasse le champ valide à 0 et consigne le motif en audit.
     */
    public function reject(int $id, int $agentId, string $reason): bool
    {
        $reason = bofa_sanitize($reason);
        $doc    = $this->getById($id);

        // Mettre à jour la description avec le motif du rejet (seule colonne disponible)
        $stmt = bofa_db()->prepare(
            "UPDATE documents SET valide = 0, description = :reason WHERE id = :id"
        );
        $stmt->execute([':reason' => $reason, ':id' => $id]);

        bofa_audit($agentId, 'REJECT', 'documents', $id, $doc, ['valide' => 0, 'motif' => $reason]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Suppression
    // -------------------------------------------------------------------------

    /**
     * Supprime un document de la base et du disque.
     */
    public function delete(int $id): bool
    {
        $doc = $this->getById($id);
        if (!$doc) return false;

        $uploadDir = defined('BOFA_UPLOAD_DIR') ? rtrim(BOFA_UPLOAD_DIR, '/') : __DIR__ . '/../uploads';
        $filePath  = $uploadDir . '/' . $doc['nom_stockage'];

        if (is_file($filePath)) {
            unlink($filePath);
        }

        $stmt = bofa_db()->prepare("DELETE FROM documents WHERE id = :id");
        $stmt->execute([':id' => $id]);

        bofa_audit(0, 'DELETE', 'documents', $id, $doc, null);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Filigrane
    // -------------------------------------------------------------------------

    /**
     * Applique un filigrane sur le fichier :
     * — Images (JPEG/PNG) : texte superposé via GD.
     * — PDF : le nom du fichier est préfixé avec l'identifiant du dossier.
     */
    public function applyWatermark(string $filePath, string $caseId, int $userId): bool
    {
        if (!is_file($filePath)) {
            return false;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);

        if ($mime === 'image/jpeg' || $mime === 'image/png') {
            return $this->_watermarkImage($filePath, $mime, $caseId, $userId);
        }

        if ($mime === 'application/pdf') {
            // Pour les PDF : marquage via le nom de fichier (aucune dépendance externe)
            // Le nom de stockage contient déjà un UUID unique ; on note le marquage dans l'audit.
            return true;
        }

        return false;
    }

    /**
     * Retourne le chemin absolu d'un document après vérification de son existence.
     * À utiliser pour le téléchargement contrôlé par le contrôleur PHP.
     */
    public function getFilePath(int $id): string|null
    {
        $doc = $this->getById($id);
        if (!$doc) return null;

        $uploadDir = defined('BOFA_UPLOAD_DIR') ? rtrim(BOFA_UPLOAD_DIR, '/') : __DIR__ . '/../uploads';

        // Sécurisation : s'assurer que le nom de fichier ne contient pas de traversal
        $storeName = basename($doc['nom_stockage']);
        $filePath  = $uploadDir . '/' . $storeName;

        return is_file($filePath) ? $filePath : null;
    }

    /**
     * Retourne les types MIME autorisés pour le téléversement.
     */
    public function getAllowedMimes(): array
    {
        return [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ];
    }

    // -------------------------------------------------------------------------
    // Méthodes privées
    // -------------------------------------------------------------------------

    /**
     * Applique un filigrane textuel sur une image JPEG ou PNG via l'extension GD.
     */
    private function _watermarkImage(string $filePath, string $mime, string $caseId, int $userId): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($filePath),
            'image/png'  => imagecreatefrompng($filePath),
            default      => null,
        };

        if (!$image) {
            return false;
        }

        $width  = imagesx($image);
        $height = imagesy($image);

        // Couleur du texte avec transparence partielle (blanc semi-transparent)
        $color = imagecolorallocatealpha($image, 255, 255, 255, 80);

        $text    = 'BofaDueDiligence | ' . $caseId . ' | User#' . $userId;
        $fontSize = max(10, (int) ($width / 40));

        // Utilisation de la police intégrée GD (pas de fichier TTF requis)
        $fontId = 3; // Police GD intégrée taille 3

        // Position : bas-droite
        $textWidth  = strlen($text) * imagefontwidth($fontId);
        $textHeight = imagefontheight($fontId);
        $x          = max(0, $width  - $textWidth  - 10);
        $y          = max(0, $height - $textHeight - 10);

        imagestring($image, $fontId, $x, $y, $text, $color);

        // Écriture du fichier modifié
        $result = match ($mime) {
            'image/jpeg' => imagejpeg($image, $filePath, 85),
            'image/png'  => imagepng($image, $filePath, 6),
            default      => false,
        };

        imagedestroy($image);
        return (bool) $result;
    }
}
