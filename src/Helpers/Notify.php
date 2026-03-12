<?php
class Notify {
    public static function send(int $userId, string $message, string $type = 'info', ?string $link = null): void {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO notifications (user_id, message, type, lien) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $message, $type, $link]);
    }

    public static function getUnread(int $userId): array {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? AND lu = 0 ORDER BY date DESC LIMIT 20');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function countUnread(int $userId): int {
        $db = getDB();
        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND lu = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetch()['cnt'];
    }

    public static function markRead(int $notifId, int $userId): void {
        $db = getDB();
        $stmt = $db->prepare('UPDATE notifications SET lu = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notifId, $userId]);
    }

    public static function markAllRead(int $userId): void {
        $db = getDB();
        $stmt = $db->prepare('UPDATE notifications SET lu = 1 WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
}
