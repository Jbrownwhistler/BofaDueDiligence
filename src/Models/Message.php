<?php
class Message {
    public static function getByCaseId(int $caseId): array {
        $stmt = getDB()->prepare(
            'SELECT m.*, CONCAT(u.prenom, " ", u.nom) as sender_name, u.role as sender_role
             FROM messages m
             JOIN users u ON m.expediteur_id = u.id
             WHERE m.case_id = ?
             ORDER BY m.date ASC'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    public static function create(int $caseId, int $senderId, int $recipientId, string $message, ?string $attachment = null): int {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO messages (case_id, expediteur_id, destinataire_id, message, piece_jointe) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$caseId, $senderId, $recipientId, $message, $attachment]);
        return (int)$db->lastInsertId();
    }

    public static function markAsRead(int $caseId, int $userId): void {
        getDB()->prepare('UPDATE messages SET lu = 1 WHERE case_id = ? AND destinataire_id = ?')
               ->execute([$caseId, $userId]);
    }

    public static function countUnread(int $userId): int {
        $stmt = getDB()->prepare('SELECT COUNT(*) as cnt FROM messages WHERE destinataire_id = ? AND lu = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetch()['cnt'];
    }
}
