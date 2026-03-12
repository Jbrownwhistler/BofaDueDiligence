<?php
defined('BOFA_APP') || die('Accès direct interdit.');

/**
 * Gestion des notifications et alertes utilisateurs.
 * Envoi unitaire ou en masse, marquage de lecture, rappels.
 */
class Notification
{
    /**
     * Types de notifications valides selon le schéma de la table.
     * Le type 'rappel' n'existant pas dans l'ENUM, les rappels utilisent 'alerte'
     * avec le préfixe 'rappel:' dans le champ lien pour les distinguer.
     */
    private const VALID_TYPES = ['info', 'alerte', 'erreur', 'succes', 'aml_alerte'];

    public function __construct() {}

    // -------------------------------------------------------------------------
    // Envoi
    // -------------------------------------------------------------------------

    /**
     * Insère une notification pour un utilisateur.
     *
     * @param string $type  Type parmi : info, alerte, erreur, succes, aml_alerte
     * @param int    $caseId Identifiant du dossier lié (0 = aucun)
     * @return int Identifiant de la notification créée (0 en cas d'erreur)
     */
    public function send(int $userId, string $message, string $type, int $caseId = 0): int
    {
        $type    = in_array($type, self::VALID_TYPES, true) ? $type : 'info';
        $message = bofa_sanitize($message);

        // Titre généré automatiquement depuis le type
        $titres = [
            'info'      => 'Information',
            'alerte'    => 'Alerte',
            'erreur'    => 'Erreur',
            'succes'    => 'Succès',
            'aml_alerte'=> 'Alerte AML',
        ];
        $titre = $titres[$type] ?? 'Notification';

        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, case_id, titre, message, type, created_at)
             VALUES (:uid, :cid, :titre, :msg, :type, NOW())"
        );
        $ok = $stmt->execute([
            ':uid'   => $userId,
            ':cid'   => $caseId > 0 ? $caseId : null,
            ':titre' => $titre,
            ':msg'   => $message,
            ':type'  => $type,
        ]);

        return $ok ? (int) $db->lastInsertId() : 0;
    }

    /**
     * Envoie la même notification à plusieurs utilisateurs.
     */
    public function sendBulk(array $userIds, string $message, string $type, int $caseId = 0): bool
    {
        if (empty($userIds)) return false;

        $type    = in_array($type, self::VALID_TYPES, true) ? $type : 'info';
        $message = bofa_sanitize($message);

        $titres = [
            'info'      => 'Information',
            'alerte'    => 'Alerte',
            'erreur'    => 'Erreur',
            'succes'    => 'Succès',
            'aml_alerte'=> 'Alerte AML',
        ];
        $titre = $titres[$type] ?? 'Notification';

        $db   = bofa_db();
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, case_id, titre, message, type, created_at)
             VALUES (:uid, :cid, :titre, :msg, :type, NOW())"
        );

        $allOk = true;
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0) continue;
            $ok = $stmt->execute([
                ':uid'   => $uid,
                ':cid'   => $caseId > 0 ? $caseId : null,
                ':titre' => $titre,
                ':msg'   => $message,
                ':type'  => $type,
            ]);
            if (!$ok) $allOk = false;
        }

        return $allOk;
    }

    // -------------------------------------------------------------------------
    // Récupération
    // -------------------------------------------------------------------------

    /**
     * Retourne les notifications d'un utilisateur, avec option "non lues uniquement".
     */
    public function getForUser(int $userId, bool $unreadOnly = false, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $where = 'user_id = :uid';
        if ($unreadOnly) {
            $where .= ' AND lu = 0';
        }

        $stmt = bofa_db()->prepare(
            "SELECT * FROM notifications
             WHERE {$where}
             ORDER BY created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':uid',   $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Marquage
    // -------------------------------------------------------------------------

    /**
     * Marque une notification comme lue.
     */
    public function markRead(int $id): bool
    {
        $stmt = bofa_db()->prepare(
            "UPDATE notifications SET lu = 1, lu_at = NOW() WHERE id = :id AND lu = 0"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues.
     */
    public function markAllRead(int $userId): bool
    {
        $stmt = bofa_db()->prepare(
            "UPDATE notifications SET lu = 1, lu_at = NOW() WHERE user_id = :uid AND lu = 0"
        );
        $stmt->execute([':uid' => $userId]);
        return true;
    }

    /**
     * Retourne le nombre de notifications non lues d'un utilisateur.
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = bofa_db()->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND lu = 0"
        );
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Suppression
    // -------------------------------------------------------------------------

    /**
     * Supprime une notification par son identifiant.
     */
    public function delete(int $id): bool
    {
        $stmt = bofa_db()->prepare("DELETE FROM notifications WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Helpers métier
    // -------------------------------------------------------------------------

    /**
     * Envoie une notification de changement de statut au client concerné.
     */
    public function sendStatusChange(int $caseId, string $newStatus, int $clientId): void
    {
        $labels = [
            'ouvert'     => 'ouvert',
            'en_cours'   => 'en cours de traitement',
            'en_attente' => 'en attente de validation',
            'cloture'    => 'clôturé',
            'rejete'     => 'rejeté',
            'approuve'   => 'approuvé',
        ];
        $label   = $labels[$newStatus] ?? $newStatus;
        $message = "Votre dossier AML a été mis à jour. Nouveau statut : {$label}.";

        $type = match ($newStatus) {
            'approuve'   => 'succes',
            'rejete'     => 'erreur',
            'en_attente' => 'alerte',
            default      => 'info',
        };

        $this->send($clientId, $message, $type, $caseId);
    }

    /**
     * Traite les rappels en attente.
     * Les rappels sont des notifications de type 'alerte' dont le lien commence par 'rappel:'.
     * Retourne le nombre de rappels traités (marqués comme lus / envoyés).
     */
    public function processReminders(): int
    {
        $stmt = bofa_db()->prepare(
            "SELECT * FROM notifications
             WHERE type = 'alerte'
               AND lu = 0
               AND lien LIKE 'rappel:%'
               AND created_at <= NOW()
             ORDER BY created_at ASC
             LIMIT 100"
        );
        $stmt->execute();
        $reminders = $stmt->fetchAll();

        $count = 0;
        foreach ($reminders as $reminder) {
            // Marquer le rappel comme traité
            $this->markRead((int) $reminder['id']);
            $count++;
        }

        return $count;
    }
}
