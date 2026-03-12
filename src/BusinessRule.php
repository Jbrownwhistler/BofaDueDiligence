<?php
defined('BOFA_APP') || die('Accès direct interdit.');

/**
 * Gestion des règles métier AML/EDD configurables.
 * Création, évaluation et application des règles sur les dossiers.
 */
class BusinessRule
{
    public function __construct() {}

    // -------------------------------------------------------------------------
    // Récupération
    // -------------------------------------------------------------------------

    /**
     * Retourne toutes les règles métier.
     */
    public function getAll(): array
    {
        $stmt = bofa_db()->query(
            "SELECT * FROM business_rules ORDER BY priorite ASC, id ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Retourne uniquement les règles actives, triées par priorité.
     */
    public function getActive(): array
    {
        $stmt = bofa_db()->query(
            "SELECT * FROM business_rules WHERE active = 1 ORDER BY priorite ASC, id ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Récupère une règle par son identifiant.
     */
    public function getById(int $id): array|null
    {
        $stmt = bofa_db()->prepare(
            "SELECT * FROM business_rules WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Crée une nouvelle règle métier.
     *
     * Données attendues :
     *   - nom_regle / libelle  : libellé descriptif de la règle
     *   - description          : description détaillée (optionnel)
     *   - condition_json       : JSON de la condition { field, operator, value }
     *   - action_json          : JSON de l'action { set_score, set_status, escalate, … }
     *   - priorite             : ordre d'évaluation (plus petit = plus prioritaire)
     *   - actif / active       : 1/0
     *   - type_regle           : seuil | scoring | blocage | alerte | workflow
     *
     * @throws InvalidArgumentException si condition_json ou action_json sont invalides
     */
    public function create(array $data): int
    {
        // Accepte 'nom_regle' (spec) ou 'libelle' (colonne BDD)
        $libelle   = bofa_sanitize($data['nom_regle'] ?? $data['libelle'] ?? '');
        if (!$libelle) {
            throw new InvalidArgumentException('Le champ nom_regle / libelle est obligatoire.');
        }

        // Validation du JSON de condition
        $conditionJson = $data['condition_json'] ?? null;
        if (is_array($conditionJson)) {
            $conditionJson = json_encode($conditionJson, JSON_UNESCAPED_UNICODE);
        }
        if ($conditionJson !== null && json_decode($conditionJson) === null) {
            throw new InvalidArgumentException('condition_json invalide (JSON malformé).');
        }

        // Validation du JSON d'action
        $actionJson = $data['action_json'] ?? null;
        if (is_array($actionJson)) {
            $actionJson = json_encode($actionJson, JSON_UNESCAPED_UNICODE);
        }
        if ($actionJson !== null && json_decode($actionJson) === null) {
            throw new InvalidArgumentException('action_json invalide (JSON malformé).');
        }

        $typeRegle  = in_array($data['type_regle'] ?? '', ['seuil','scoring','blocage','alerte','workflow'], true)
                      ? $data['type_regle'] : 'alerte';
        $priorite   = (int) ($data['priorite'] ?? 100);
        // Accepte 'actif' (spec) ou 'active' (colonne BDD)
        $active     = isset($data['actif']) ? (int) (bool) $data['actif']
                     : (int) (bool) ($data['active'] ?? 1);
        $seuil      = isset($data['valeur_seuil']) ? (float) $data['valeur_seuil'] : null;
        $code       = bofa_sanitize($data['code'] ?? 'RULE_' . uniqid());
        $desc       = bofa_sanitize($data['description'] ?? '');

        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO business_rules
                (code, libelle, type_regle, valeur_seuil, condition_json, action_json,
                 active, priorite, created_at)
             VALUES
                (:code, :lib, :type, :seuil, :cond, :action, :active, :prio, NOW())"
        );
        $stmt->execute([
            ':code'   => $code,
            ':lib'    => $libelle,
            ':type'   => $typeRegle,
            ':seuil'  => $seuil,
            ':cond'   => $conditionJson,
            ':action' => $actionJson,
            ':active' => $active,
            ':prio'   => $priorite,
        ]);

        $newId = (int) $db->lastInsertId();
        bofa_audit(0, 'CREATE', 'business_rules', $newId, null, ['code' => $code, 'libelle' => $libelle]);
        return $newId;
    }

    /**
     * Met à jour une règle métier existante.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['libelle', 'type_regle', 'valeur_seuil', 'condition_json', 'action_json', 'active', 'priorite'];
        $sets    = [];
        $params  = [':id' => $id];
        $old     = $this->getById($id);

        // Alias : nom_regle → libelle, actif → active
        if (isset($data['nom_regle']) && !isset($data['libelle'])) {
            $data['libelle'] = $data['nom_regle'];
        }
        if (isset($data['actif']) && !isset($data['active'])) {
            $data['active'] = $data['actif'];
        }

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;

            $val = match ($field) {
                'libelle'        => bofa_sanitize((string) $data[$field]),
                'type_regle'     => in_array($data[$field], ['seuil','scoring','blocage','alerte','workflow'], true)
                                    ? $data[$field] : null,
                'valeur_seuil'   => isset($data[$field]) ? (float) $data[$field] : null,
                'active'         => (int) (bool) $data[$field],
                'priorite'       => (int) $data[$field],
                'condition_json' => $this->_encodeJson($data[$field]),
                'action_json'    => $this->_encodeJson($data[$field]),
                default          => bofa_sanitize((string) $data[$field]),
            };

            if ($val === null && !in_array($field, ['valeur_seuil'], true)) continue;

            $sets[]             = "{$field} = :{$field}";
            $params[":{$field}"] = $val;
        }

        if (empty($sets)) return false;

        $stmt = bofa_db()->prepare("UPDATE business_rules SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);

        bofa_audit(0, 'UPDATE', 'business_rules', $id, $old, $data);
        return $stmt->rowCount() >= 0;
    }

    /**
     * Supprime une règle métier.
     */
    public function delete(int $id): bool
    {
        $stmt = bofa_db()->prepare("DELETE FROM business_rules WHERE id = :id");
        $stmt->execute([':id' => $id]);
        bofa_audit(0, 'DELETE', 'business_rules', $id);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Évaluation
    // -------------------------------------------------------------------------

    /**
     * Évalue toutes les règles actives sur un dossier et applique les actions résultantes.
     *
     * Structure de condition_json :
     *   { "field": "montant", "operator": ">", "value": 50000 }
     *   Opérateurs : >, >=, <, <=, ==, !=, contains, in
     *
     * Structure de action_json :
     *   {
     *     "set_score": 95,           — force le score à une valeur donnée
     *     "add_score": 10,           — ajoute des points au score
     *     "set_status": "en_attente",— force un statut
     *     "set_priorite": "critique",— force la priorité
     *     "escalate": true           — signale une escalade
     *   }
     *
     * @param array $caseData Données du dossier à évaluer
     * @return array Données du dossier après application des règles
     */
    public function evaluate(array $caseData): array
    {
        $rules = $this->getActive();

        foreach ($rules as $rule) {
            $condition = $rule['condition_json']
                ? (json_decode($rule['condition_json'], true) ?? [])
                : [];
            $action    = $rule['action_json']
                ? (json_decode($rule['action_json'], true) ?? [])
                : [];

            if (empty($action)) continue;

            // Évaluation de la condition (vide = toujours vraie)
            if (!empty($condition) && !$this->_evaluateCondition($condition, $caseData)) {
                continue;
            }

            // Application des actions
            $caseData = $this->_applyAction($action, $caseData, $rule);
        }

        return $caseData;
    }

    // -------------------------------------------------------------------------
    // Méthodes privées
    // -------------------------------------------------------------------------

    /**
     * Évalue une condition JSON sur les données du dossier.
     * Supporte les conditions simples et les tableaux de conditions (opérateur AND implicite).
     */
    private function _evaluateCondition(array $condition, array $caseData): bool
    {
        // Condition composite : tableau de conditions (AND implicite)
        if (isset($condition[0]) && is_array($condition[0])) {
            foreach ($condition as $cond) {
                if (!$this->_evaluateCondition($cond, $caseData)) {
                    return false;
                }
            }
            return true;
        }

        $field    = $condition['field']    ?? '';
        $operator = $condition['operator'] ?? '==';
        $expected = $condition['value']    ?? null;

        if (!array_key_exists($field, $caseData)) {
            return false;
        }
        $actual = $caseData[$field];

        return match ($operator) {
            '>'        => (float) $actual >  (float) $expected,
            '>='       => (float) $actual >= (float) $expected,
            '<'        => (float) $actual <  (float) $expected,
            '<='       => (float) $actual <= (float) $expected,
            '=='       => $actual == $expected,
            '!='       => $actual != $expected,
            'contains' => is_string($actual) && str_contains(strtolower($actual), strtolower((string) $expected)),
            'in'       => is_array($expected) && in_array($actual, $expected),
            default    => false,
        };
    }

    /**
     * Applique les actions définies dans action_json sur les données du dossier.
     */
    private function _applyAction(array $action, array $caseData, array $rule): array
    {
        // Forcer le score à une valeur fixe
        if (isset($action['set_score'])) {
            $caseData['score_risque'] = (float) min(100, max(0, $action['set_score']));
        }

        // Ajouter des points au score
        if (isset($action['add_score'])) {
            $caseData['score_risque'] = (float) min(100, max(0,
                ($caseData['score_risque'] ?? 0) + (float) $action['add_score']
            ));
        }

        // Forcer un statut
        $validStatuts = ['ouvert', 'en_cours', 'en_attente', 'cloture', 'rejete', 'approuve'];
        if (!empty($action['set_status']) && in_array($action['set_status'], $validStatuts, true)) {
            $caseData['statut'] = $action['set_status'];
        }

        // Forcer une priorité
        $validPriorites = ['faible', 'normale', 'haute', 'critique'];
        if (!empty($action['set_priorite']) && in_array($action['set_priorite'], $validPriorites, true)) {
            $caseData['priorite'] = $action['set_priorite'];
        }

        // Marquage d'escalade dans les données du dossier
        if (!empty($action['escalate'])) {
            $caseData['_escalade'] = true;
            if (($caseData['priorite'] ?? 'normale') !== 'critique') {
                $caseData['priorite'] = 'haute';
            }
        }

        // Journalisation de l'application de la règle
        $caseData['_applied_rules'][] = [
            'id'      => $rule['id'],
            'code'    => $rule['code'],
            'libelle' => $rule['libelle'],
        ];

        return $caseData;
    }

    /**
     * Encode une valeur en JSON pour stockage.
     * Accepte un tableau (encode) ou une chaîne JSON (valide et retourne).
     */
    private function _encodeJson(mixed $value): string|null
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_string($value)) {
            // Valider que la chaîne est un JSON valide
            if ($value !== '' && json_decode($value) === null && $value !== 'null') {
                return null; // JSON invalide ignoré
            }
            return $value;
        }
        return null;
    }
}
