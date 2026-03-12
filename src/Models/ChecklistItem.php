<?php
class ChecklistItem {
    public static function getByCaseId(int $caseId): array {
        $stmt = getDB()->prepare(
            'SELECT ci.*, d.nom_fichier as doc_name
             FROM checklist_items ci
             LEFT JOIN documents d ON ci.document_id = d.id
             WHERE ci.case_id = ?
             ORDER BY ci.date_creation ASC'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    public static function create(int $caseId, string $label, string $type = 'case'): int {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO checklist_items (case_id, libelle, type_exigence) VALUES (?, ?, ?)');
        $stmt->execute([$caseId, $label, $type]);
        return (int)$db->lastInsertId();
    }

    public static function toggle(int $id): void {
        getDB()->prepare('UPDATE checklist_items SET est_coche = NOT est_coche WHERE id = ?')->execute([$id]);
    }

    public static function linkDocument(int $id, int $docId): void {
        getDB()->prepare('UPDATE checklist_items SET document_id = ?, est_coche = 1 WHERE id = ?')->execute([$docId, $id]);
    }

    public static function allCompleted(int $caseId): bool {
        $stmt = getDB()->prepare('SELECT COUNT(*) as total, SUM(est_coche) as done FROM checklist_items WHERE case_id = ?');
        $stmt->execute([$caseId]);
        $r = $stmt->fetch();
        return $r['total'] > 0 && $r['total'] == $r['done'];
    }
}
