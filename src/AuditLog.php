<?php
defined('BOFA_APP') || die('Accès direct interdit.');

/**
 * Journal d'audit réglementaire AML/EDD.
 * Enregistrement des actions, consultation avec filtres, export CSV.
 */
class AuditLog
{
    public function __construct() {}

    // -------------------------------------------------------------------------
    // Enregistrement
    // -------------------------------------------------------------------------

    /**
     * Insère une entrée dans le journal d'audit.
     *
     * @param int    $userId    Identifiant de l'auteur (0 = action système)
     * @param string $action    Type d'action : CREATE, UPDATE, DELETE, LOGIN, etc.
     * @param string $table     Table concernée par l'action
     * @param int    $recordId  Identifiant de l'enregistrement concerné
     * @param mixed  $oldVal    Valeur avant modification (sera encodée en JSON)
     * @param mixed  $newVal    Valeur après modification (sera encodée en JSON)
     * @param string $ip        Adresse IP (vide = lecture depuis $_SERVER)
     * @param string $userAgent Agent HTTP (vide = lecture depuis $_SERVER)
     */
    public function log(
        int    $userId,
        string $action,
        string $table,
        int    $recordId,
               $oldVal    = null,
               $newVal    = null,
        string $ip        = '',
        string $userAgent = ''
    ): bool {
        $action    = bofa_sanitize(strtoupper($action));
        $table     = bofa_sanitize($table);
        $ip        = $ip ?: ($_SERVER['REMOTE_ADDR']     ?? '0.0.0.0');
        $userAgent = $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? 'inconnu');

        // Validation de l'adresse IP
        $ip        = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
        $userAgent = substr(bofa_sanitize($userAgent), 0, 500);

        $oldJson = $oldVal !== null ? json_encode($oldVal, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newVal !== null ? json_encode($newVal, JSON_UNESCAPED_UNICODE) : null;

        $stmt = bofa_db()->prepare(
            "INSERT INTO audit_log
                (user_id, action, table_name, record_id, old_value, new_value,
                 ip_address, user_agent, created_at)
             VALUES
                (:uid, :action, :table, :rid, :old, :new, :ip, :ua, NOW())"
        );
        return $stmt->execute([
            ':uid'    => $userId > 0 ? $userId : null,
            ':action' => $action,
            ':table'  => $table,
            ':rid'    => $recordId > 0 ? $recordId : null,
            ':old'    => $oldJson,
            ':new'    => $newJson,
            ':ip'     => $ip,
            ':ua'     => $userAgent,
        ]);
    }

    // -------------------------------------------------------------------------
    // Consultation
    // -------------------------------------------------------------------------

    /**
     * Retourne les entrées du journal avec filtres et pagination.
     * Filtres : user_id, action, table_concernee, date_debut, date_fin, ip.
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $db     = bofa_db();
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]          = 'al.user_id = :user_id';
            $params[':user_id']= (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[]           = 'al.action = :action';
            $params[':action'] = strtoupper(bofa_sanitize($filters['action']));
        }
        // Accepte 'table_concernee' (spec) ou 'table_name' (colonne réelle)
        $tableFilter = $filters['table_concernee'] ?? $filters['table_name'] ?? '';
        if (!empty($tableFilter)) {
            $where[]          = 'al.table_name = :table_name';
            $params[':table_name'] = bofa_sanitize($tableFilter);
        }
        if (!empty($filters['date_debut'])) {
            $where[]               = 'al.created_at >= :date_debut';
            $params[':date_debut'] = bofa_sanitize($filters['date_debut']) . ' 00:00:00';
        }
        if (!empty($filters['date_fin'])) {
            $where[]             = 'al.created_at <= :date_fin';
            $params[':date_fin'] = bofa_sanitize($filters['date_fin']) . ' 23:59:59';
        }
        if (!empty($filters['ip'])) {
            $where[]       = 'al.ip_address = :ip';
            $params[':ip'] = bofa_sanitize($filters['ip']);
        }

        $whereStr  = implode(' AND ', $where);
        $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log al WHERE {$whereStr}");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $pagination = bofa_paginate($total, $perPage, $page);

        $stmt = $db->prepare(
            "SELECT al.*, CONCAT(u.nom, ' ', u.prenom) AS auteur
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE {$whereStr}
             ORDER BY al.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit',  $pagination['perPage'],  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'pagination' => $pagination];
    }

    /**
     * Retourne toutes les entrées d'audit pour un dossier donné.
     */
    public function getForCase(int $caseId): array
    {
        $stmt = bofa_db()->prepare(
            "SELECT al.*, CONCAT(u.nom, ' ', u.prenom) AS auteur
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE (al.table_name = 'cases' AND al.record_id = :cid)
                OR (al.table_name = 'documents' AND al.record_id IN (
                      SELECT id FROM documents WHERE case_id = :cid2))
                OR (al.table_name = 'case_status_history' AND al.record_id IN (
                      SELECT id FROM case_status_history WHERE case_id = :cid3))
             ORDER BY al.created_at ASC"
        );
        $stmt->execute([':cid' => $caseId, ':cid2' => $caseId, ':cid3' => $caseId]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Export
    // -------------------------------------------------------------------------

    /**
     * Génère et retourne une chaîne CSV du journal d'audit pour les filtres donnés.
     * Les valeurs contenant des guillemets, virgules ou sauts de ligne sont encadrées.
     */
    public function exportCsv(array $filters = []): string
    {
        // Récupérer jusqu'à 50 000 entrées pour l'export
        $result = $this->getAll($filters, 1, 50000);
        $rows   = $result['data'] ?? [];

        $columns = [
            'id', 'user_id', 'auteur', 'action', 'table_name',
            'record_id', 'old_value', 'new_value',
            'ip_address', 'user_agent', 'created_at',
        ];

        // BOM UTF-8 pour compatibilité Excel
        $csv  = "\xEF\xBB\xBF";
        $csv .= implode(';', array_map([$this, '_csvEscape'], $columns)) . "\r\n";

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = $this->_csvEscape((string) ($row[$col] ?? ''));
            }
            $csv .= implode(';', $line) . "\r\n";
        }

        return $csv;
    }

    // -------------------------------------------------------------------------
    // Statistiques
    // -------------------------------------------------------------------------

    /**
     * Retourne des statistiques agrégées : actions par type et par table.
     */
    public function getStats(): array
    {
        $db = bofa_db();

        // Répartition par type d'action
        $stmtActions = $db->query(
            "SELECT action, COUNT(*) AS total
             FROM audit_log
             GROUP BY action
             ORDER BY total DESC"
        );
        $byAction = $stmtActions->fetchAll();

        // Répartition par table concernée
        $stmtTables = $db->query(
            "SELECT table_name, COUNT(*) AS total
             FROM audit_log
             GROUP BY table_name
             ORDER BY total DESC"
        );
        $byTable = $stmtTables->fetchAll();

        // Total global
        $total = (int) $db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();

        return [
            'total'     => $total,
            'by_action' => $byAction,
            'by_table'  => $byTable,
        ];
    }

    // -------------------------------------------------------------------------
    // Méthode privée utilitaire
    // -------------------------------------------------------------------------

    /**
     * Échappe une valeur pour un champ CSV (séparateur point-virgule).
     */
    private function _csvEscape(string $value): string
    {
        // Supprimer les retours à la ligne internes puis encadrer si nécessaire
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        if (str_contains($value, '"') || str_contains($value, ';') || str_contains($value, ' ')) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
