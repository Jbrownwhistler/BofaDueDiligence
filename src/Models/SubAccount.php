<?php
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
