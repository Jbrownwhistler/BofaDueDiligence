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
