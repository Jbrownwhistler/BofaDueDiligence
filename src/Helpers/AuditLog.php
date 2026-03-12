<?php
class AuditLog {
    public static function log(
        string $action,
        ?string $table = null,
        ?int $recordId = null,
        ?string $oldValue = null,
        ?string $newValue = null
    ): void {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO audit_log (utilisateur_id, action, table_concernee, enregistrement_id, ancienne_valeur, nouvelle_valeur, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            Auth::check() ? Auth::id() : null,
            $action,
            $table,
            $recordId,
            $oldValue,
            $newValue,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);
    }
}
