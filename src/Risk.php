<?php
defined('BOFA_APP') || die('Accès direct interdit.');

/**
 * Gestion du référentiel de risque AML :
 * pays, types d'actifs, calcul de score, configuration système.
 */
class Risk
{
    public function __construct() {}

    // -------------------------------------------------------------------------
    // Pays à risque
    // -------------------------------------------------------------------------

    /**
     * Retourne la liste des pays avec filtres optionnels et pagination.
     * Filtres : categorie_risque, liste_noire, search (nom ou code_iso).
     */
    public function getCountries(array $filters = [], int $page = 1): array
    {
        $db      = bofa_db();
        $perPage = 50;
        $where   = ['1=1'];
        $params  = [];

        if (!empty($filters['categorie_risque'])) {
            $allowed = ['faible', 'moyen', 'eleve', 'tres_eleve'];
            $cat     = bofa_sanitize($filters['categorie_risque']);
            if (in_array($cat, $allowed, true)) {
                $where[]       = 'categorie_risque = :cat';
                $params[':cat']= $cat;
            }
        }
        if (isset($filters['liste_noire'])) {
            $where[]         = 'liste_noire = :ln';
            $params[':ln']   = (int) (bool) $filters['liste_noire'];
        }
        if (!empty($filters['search'])) {
            $where[]            = '(nom LIKE :search OR code_iso LIKE :search)';
            $params[':search']  = '%' . bofa_sanitize($filters['search']) . '%';
        }

        $whereStr  = implode(' AND ', $where);
        $countStmt = $db->prepare("SELECT COUNT(*) FROM risk_countries WHERE {$whereStr}");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $pagination = bofa_paginate($total, $perPage, $page);

        $stmt = $db->prepare(
            "SELECT * FROM risk_countries WHERE {$whereStr}
             ORDER BY nom ASC
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
     * Récupère un pays par son code ISO 3166-1 alpha-2.
     */
    public function getCountryByCode(string $code): array|null
    {
        $code = strtoupper(bofa_sanitize($code));
        $stmt = bofa_db()->prepare(
            "SELECT * FROM risk_countries WHERE code_iso = :code LIMIT 1"
        );
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Ajoute un nouveau pays dans le référentiel de risque.
     *
     * @throws InvalidArgumentException si code_iso ou nom manquants
     */
    public function addCountry(array $data): int
    {
        $code = strtoupper(bofa_sanitize($data['code_iso'] ?? ''));
        $nom  = bofa_sanitize($data['nom'] ?? '');

        if (strlen($code) !== 2 || !$nom) {
            throw new InvalidArgumentException('code_iso (2 caractères) et nom sont obligatoires.');
        }

        $categorie = in_array($data['categorie_risque'] ?? '', ['faible','moyen','eleve','tres_eleve'], true)
                     ? $data['categorie_risque'] : 'faible';
        $coeff     = max(0.0, (float) ($data['coefficient'] ?? 1.0));
        $listeNoire= isset($data['liste_noire']) ? (int) (bool) $data['liste_noire'] : 0;
        $source    = bofa_sanitize($data['source'] ?? 'FATF/GAFI');

        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO risk_countries (code_iso, nom, coefficient, categorie_risque, liste_noire, source)
             VALUES (:code, :nom, :coeff, :cat, :ln, :source)"
        );
        $stmt->execute([
            ':code'   => $code,
            ':nom'    => $nom,
            ':coeff'  => $coeff,
            ':cat'    => $categorie,
            ':ln'     => $listeNoire,
            ':source' => $source,
        ]);

        $newId = (int) $db->lastInsertId();
        bofa_audit(0, 'CREATE', 'risk_countries', $newId, null, ['code' => $code]);
        return $newId;
    }

    /**
     * Met à jour un pays dans le référentiel de risque.
     */
    public function updateCountry(int $id, array $data): bool
    {
        $allowed = ['nom', 'coefficient', 'categorie_risque', 'liste_noire', 'source'];
        $sets    = [];
        $params  = [':id' => $id];

        $old = $this->getCountryByCode((string) $id); // pour l'audit

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;
            $val = match ($field) {
                'coefficient'     => max(0.0, (float) $data[$field]),
                'liste_noire'     => (int) (bool) $data[$field],
                'categorie_risque'=> in_array($data[$field], ['faible','moyen','eleve','tres_eleve'], true)
                                     ? $data[$field] : null,
                default           => bofa_sanitize((string) $data[$field]),
            };
            if ($val === null) continue;
            $sets[]             = "{$field} = :{$field}";
            $params[":{$field}"] = $val;
        }

        if (empty($sets)) return false;

        $stmt = bofa_db()->prepare("UPDATE risk_countries SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);

        bofa_audit(0, 'UPDATE', 'risk_countries', $id, null, $data);
        return $stmt->rowCount() >= 0;
    }

    /**
     * Supprime un pays du référentiel de risque.
     */
    public function deleteCountry(int $id): bool
    {
        $stmt = bofa_db()->prepare("DELETE FROM risk_countries WHERE id = :id");
        $stmt->execute([':id' => $id]);
        bofa_audit(0, 'DELETE', 'risk_countries', $id);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Types d'actifs
    // -------------------------------------------------------------------------

    /**
     * Retourne la liste des types d'actifs avec pagination.
     */
    public function getAssetTypes(int $page = 1): array
    {
        $db      = bofa_db();
        $perPage = 50;

        $total      = (int) $db->query("SELECT COUNT(*) FROM risk_asset_types")->fetchColumn();
        $pagination = bofa_paginate($total, $perPage, $page);

        $stmt = $db->prepare(
            "SELECT * FROM risk_asset_types
             ORDER BY code ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $pagination['perPage'],  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'pagination' => $pagination];
    }

    /**
     * Récupère un type d'actif par son code technique.
     */
    public function getAssetTypeByName(string $name): array|null
    {
        $name = strtolower(bofa_sanitize($name));
        $stmt = bofa_db()->prepare(
            "SELECT * FROM risk_asset_types WHERE code = :code LIMIT 1"
        );
        $stmt->execute([':code' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Ajoute un type d'actif dans le référentiel.
     *
     * @throws InvalidArgumentException si code ou libelle manquants
     */
    public function addAssetType(array $data): int
    {
        $code   = strtolower(bofa_sanitize($data['code']    ?? ''));
        $libelle= bofa_sanitize($data['libelle'] ?? '');

        if (!$code || !$libelle) {
            throw new InvalidArgumentException('Les champs code et libelle sont obligatoires.');
        }

        $coeff = max(0.0, (float) ($data['coefficient'] ?? 1.0));
        $desc  = bofa_sanitize($data['description'] ?? '');

        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO risk_asset_types (code, libelle, coefficient, description)
             VALUES (:code, :lib, :coeff, :desc)"
        );
        $stmt->execute([
            ':code'  => $code,
            ':lib'   => $libelle,
            ':coeff' => $coeff,
            ':desc'  => $desc ?: null,
        ]);

        $newId = (int) $db->lastInsertId();
        bofa_audit(0, 'CREATE', 'risk_asset_types', $newId, null, ['code' => $code]);
        return $newId;
    }

    /**
     * Met à jour un type d'actif.
     */
    public function updateAssetType(int $id, array $data): bool
    {
        $allowed = ['libelle', 'coefficient', 'description'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;
            $val = $field === 'coefficient'
                   ? max(0.0, (float) $data[$field])
                   : bofa_sanitize((string) $data[$field]);
            $sets[]             = "{$field} = :{$field}";
            $params[":{$field}"] = $val;
        }

        if (empty($sets)) return false;

        $stmt = bofa_db()->prepare("UPDATE risk_asset_types SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);

        bofa_audit(0, 'UPDATE', 'risk_asset_types', $id, null, $data);
        return $stmt->rowCount() >= 0;
    }

    /**
     * Supprime un type d'actif du référentiel.
     */
    public function deleteAssetType(int $id): bool
    {
        $stmt = bofa_db()->prepare("DELETE FROM risk_asset_types WHERE id = :id");
        $stmt->execute([':id' => $id]);
        bofa_audit(0, 'DELETE', 'risk_asset_types', $id);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Calcul du score de risque
    // -------------------------------------------------------------------------

    /**
     * Calcule le score de risque normalisé (0–100) via le helper centralisé.
     */
    public function calculateScore(float $montant, string $codePays, string $typeActif): float
    {
        return bofa_calculer_score($montant, $codePays, $typeActif);
    }

    // -------------------------------------------------------------------------
    // Configuration système
    // -------------------------------------------------------------------------

    /**
     * Retourne la valeur d'un paramètre de configuration système.
     */
    public function getSystemConfig(string $key): string|null
    {
        $key  = bofa_sanitize($key);
        $stmt = bofa_db()->prepare(
            "SELECT valeur FROM system_config WHERE cle = :cle LIMIT 1"
        );
        $stmt->execute([':cle' => $key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string) $val : null;
    }

    /**
     * Crée ou met à jour un paramètre de configuration système.
     */
    public function setSystemConfig(string $key, string $value): bool
    {
        $key   = bofa_sanitize($key);
        $value = bofa_sanitize($value);

        $stmt = bofa_db()->prepare(
            "INSERT INTO system_config (cle, valeur)
             VALUES (:cle, :val)
             ON DUPLICATE KEY UPDATE valeur = :val2"
        );
        bofa_audit(0, 'CONFIG_UPDATE', 'system_config', 0, null, ['cle' => $key]);
        return $stmt->execute([':cle' => $key, ':val' => $value, ':val2' => $value]);
    }

    /**
     * Retourne toute la configuration système sous forme de tableau associatif clé ↔ valeur.
     */
    public function getAllConfig(): array
    {
        $stmt = bofa_db()->query("SELECT cle, valeur, description FROM system_config ORDER BY cle");
        return $stmt->fetchAll();
    }
}
