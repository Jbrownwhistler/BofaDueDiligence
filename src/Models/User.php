<?php
class User {
    public static function findById(int $id): ?array {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function getAll(array $filters = []): array {
        $sql = 'SELECT * FROM users WHERE 1=1';
        $params = [];

        if (!empty($filters['role'])) {
            $sql .= ' AND role = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['statut'])) {
            $sql .= ' AND statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$s, $s, $s]);
        }

        $sql .= ' ORDER BY date_creation DESC';
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO users (email, password, nom, prenom, role, statut) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['nom'],
            $data['prenom'],
            $data['role'] ?? 'client',
            $data['statut'] ?? 'actif',
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void {
        $fields = [];
        $params = [];
        foreach (['nom', 'prenom', 'email', 'role', 'statut'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        $params[] = $id;
        getDB()->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    }

    public static function updatePassword(int $id, string $password): void {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        getDB()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $id]);
    }

    public static function toggleStatus(int $id): void {
        getDB()->prepare(
            "UPDATE users SET statut = IF(statut='actif','inactif','actif') WHERE id = ?"
        )->execute([$id]);
    }

    public static function getAgents(): array {
        $stmt = getDB()->prepare("SELECT id, CONCAT(prenom, ' ', nom) as full_name FROM users WHERE role = 'agent' AND statut = 'actif'");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getClients(): array {
        $stmt = getDB()->prepare("SELECT id, CONCAT(prenom, ' ', nom) as full_name, email FROM users WHERE role = 'client' AND statut = 'actif'");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countByRole(): array {
        $stmt = getDB()->query('SELECT role, COUNT(*) as cnt FROM users GROUP BY role');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['role']] = (int)$row['cnt'];
        }
        return $result;
    }
}
