<?php
class Account {
    public static function getByUser(int $userId): ?array {
        $stmt = getDB()->prepare('SELECT * FROM accounts WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function addToBalance(int $accountId, float $amount): void {
        getDB()->prepare('UPDATE accounts SET solde = solde + ? WHERE id = ?')->execute([$amount, $accountId]);
    }

    public static function generateAccountNumber(): string {
        $year = date('Y');
        $stmt = getDB()->query("SELECT MAX(CAST(SUBSTRING(numero_compte_principal, -5) AS UNSIGNED)) as max_num FROM accounts");
        $result = $stmt->fetch();
        $next = ($result['max_num'] ?? 0) + 1;
        return sprintf('BOFA-%s-%05d', $year, $next);
    }

    public static function create(int $userId): int {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO accounts (user_id, numero_compte_principal, solde, devise) VALUES (?, ?, 0.00, "USD")');
        $stmt->execute([$userId, self::generateAccountNumber()]);
        return (int)$db->lastInsertId();
    }
}

class SubAccount {
    public static function create(int $accountId, float $ledger): int {
        $db = getDB();
        $num = self::generateNumber($accountId);
        $stmt = $db->prepare('INSERT INTO sub_accounts (account_id, numero_sous_compte, ledger) VALUES (?, ?, ?)');
        $stmt->execute([$accountId, $num, $ledger]);
        return (int)$db->lastInsertId();
    }

    public static function generateNumber(int $accountId): string {
        $account = getDB()->prepare('SELECT numero_compte_principal FROM accounts WHERE id = ?');
        $account->execute([$accountId]);
        $acct = $account->fetch();
        $stmt = getDB()->prepare('SELECT COUNT(*) as cnt FROM sub_accounts WHERE account_id = ?');
        $stmt->execute([$accountId]);
        $count = (int)$stmt->fetch()['cnt'] + 1;
        return sprintf('SUB-%s-%02d', str_replace('BOFA-', '', $acct['numero_compte_principal']), $count);
    }
}

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
