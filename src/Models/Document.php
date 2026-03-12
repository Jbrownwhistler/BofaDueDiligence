<?php
class Document {
    public static function getByCaseId(int $caseId): array {
        $stmt = getDB()->prepare('SELECT * FROM documents WHERE case_id = ? ORDER BY date_upload DESC');
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array {
        $stmt = getDB()->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $caseId, string $filename, string $path, string $type = 'Autre'): int {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO documents (case_id, nom_fichier, chemin_fichier, type_document) VALUES (?, ?, ?, ?)');
        $stmt->execute([$caseId, $filename, $path, $type]);
        return (int)$db->lastInsertId();
    }

    public static function updateStatus(int $id, string $status, ?string $motif = null): void {
        getDB()->prepare('UPDATE documents SET statut_validation = ?, motif_rejet = ? WHERE id = ?')
               ->execute([$status, $motif, $id]);
    }
}
