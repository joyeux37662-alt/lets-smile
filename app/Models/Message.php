<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class Message
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function stats(int $cabinetId, int $userId): array
    {
        $statement = $this->db->prepare("
            SELECT
                (SELECT COUNT(*) FROM message_threads WHERE cabinet_id = :cabinet_inbox AND status = 'open') AS inbox_count,
                (
                    SELECT COUNT(DISTINCT mt.id)
                    FROM message_threads mt
                    INNER JOIN messages m ON m.thread_id = mt.id
                    WHERE mt.cabinet_id = :cabinet_unread
                      AND mt.status = 'open'
                      AND m.sender_patient_id IS NOT NULL
                      AND m.read_at IS NULL
                ) AS unread_threads,
                (
                    SELECT COUNT(*)
                    FROM message_threads
                    WHERE cabinet_id = :cabinet_patients
                      AND status != 'archived'
                      AND patient_id IS NOT NULL
                ) AS patient_threads,
                (
                    SELECT COUNT(DISTINCT mt.id)
                    FROM message_threads mt
                    INNER JOIN messages m ON m.thread_id = mt.id
                    WHERE mt.cabinet_id = :cabinet_patient_unread
                      AND mt.status != 'archived'
                      AND mt.patient_id IS NOT NULL
                      AND m.sender_patient_id IS NOT NULL
                      AND m.read_at IS NULL
                ) AS patient_unread_threads,
                (
                    SELECT COUNT(*)
                    FROM message_threads
                    WHERE cabinet_id = :cabinet_team
                      AND status != 'archived'
                      AND patient_id IS NULL
                ) AS team_threads,
                (
                    SELECT COUNT(DISTINCT mt.id)
                    FROM message_threads mt
                    INNER JOIN messages m ON m.thread_id = mt.id
                    WHERE mt.cabinet_id = :cabinet_team_unread
                      AND mt.status != 'archived'
                      AND mt.patient_id IS NULL
                      AND m.sender_user_id IS NOT NULL
                      AND m.sender_user_id != :user_id
                      AND m.read_at IS NULL
                ) AS team_unread_threads,
                (
                    SELECT COUNT(*)
                    FROM message_threads
                    WHERE cabinet_id = :cabinet_archived
                      AND status = 'archived'
                ) AS archived_threads
        ");
        $statement->execute([
            'cabinet_inbox' => $cabinetId,
            'cabinet_unread' => $cabinetId,
            'cabinet_patients' => $cabinetId,
            'cabinet_patient_unread' => $cabinetId,
            'cabinet_team' => $cabinetId,
            'cabinet_team_unread' => $cabinetId,
            'cabinet_archived' => $cabinetId,
            'user_id' => $userId,
        ]);
        $stats = $statement->fetch() ?: [];

        return [
            'inbox_count' => (int) ($stats['inbox_count'] ?? 0),
            'unread_threads' => (int) ($stats['unread_threads'] ?? 0),
            'patient_threads' => (int) ($stats['patient_threads'] ?? 0),
            'patient_unread_threads' => (int) ($stats['patient_unread_threads'] ?? 0),
            'team_threads' => (int) ($stats['team_threads'] ?? 0),
            'team_unread_threads' => (int) ($stats['team_unread_threads'] ?? 0),
            'archived_threads' => (int) ($stats['archived_threads'] ?? 0),
        ];
    }

    public function threads(int $cabinetId, string $search, string $scope, int $userId = 0, int $limit = 8): array
    {
        $filter = $this->filterSql($search, $scope, $userId);
        $statement = $this->db->prepare("
            SELECT
                mt.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                lm.body AS last_body,
                lm.sender_user_id AS last_sender_user_id,
                lm.sender_patient_id AS last_sender_patient_id,
                lm.created_at AS last_message_at,
                COALESCE(unread.unread_count, 0) AS unread_count
            FROM message_threads mt
            LEFT JOIN patients p ON p.id = mt.patient_id
            LEFT JOIN (
                SELECT m.*
                FROM messages m
                INNER JOIN (
                    SELECT thread_id, MAX(id) AS last_id
                    FROM messages
                    GROUP BY thread_id
                ) latest ON latest.last_id = m.id
            ) lm ON lm.thread_id = mt.id
            LEFT JOIN (
                SELECT thread_id, COUNT(*) AS unread_count
                FROM messages
                WHERE read_at IS NULL
                  AND (
                    sender_patient_id IS NOT NULL
                    OR (sender_user_id IS NOT NULL AND sender_user_id != :thread_user_id)
                  )
                GROUP BY thread_id
            ) unread ON unread.thread_id = mt.id
            WHERE mt.cabinet_id = :cabinet_id
              {$filter['where']}
            ORDER BY COALESCE(lm.created_at, mt.updated_at, mt.created_at) DESC, mt.id DESC
            LIMIT :limit
        ");

        foreach ($filter['params'] + ['cabinet_id' => $cabinetId, 'thread_user_id' => $userId] as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function firstId(int $cabinetId, string $search, string $scope, int $userId = 0): ?int
    {
        $threads = $this->threads($cabinetId, $search, $scope, $userId, 1);

        return isset($threads[0]['id']) ? (int) $threads[0]['id'] : null;
    }

    public function find(int $cabinetId, int $id): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                mt.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                p.address,
                p.status AS patient_status,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age
            FROM message_threads mt
            LEFT JOIN patients p ON p.id = mt.patient_id
            WHERE mt.cabinet_id = :cabinet_id
              AND mt.id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        $thread = $statement->fetch();

        if (!$thread) {
            return null;
        }

        $patientId = (int) ($thread['patient_id'] ?? 0);
        $thread['messages'] = $this->messages($id);
        $thread['last_appointment'] = $patientId > 0 ? $this->appointment($cabinetId, $patientId, 'last') : null;
        $thread['next_appointment'] = $patientId > 0 ? $this->appointment($cabinetId, $patientId, 'next') : null;
        $thread['current_treatment'] = $patientId > 0 ? $this->currentTreatment($cabinetId, $patientId) : null;

        return $thread;
    }

    public function createThread(int $cabinetId, array $data, int $userId): int
    {
        $patientId = (int) ($data['patient_id'] ?? 0);
        $body = trim((string) ($data['body'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));

        if ($body === '') {
            throw new RuntimeException('Le message est obligatoire.');
        }

        if ($patientId > 0 && !$this->patientExists($cabinetId, $patientId)) {
            throw new RuntimeException('Patient introuvable.');
        }

        if ($subject === '') {
            $subject = $patientId > 0 ? 'Conversation patient' : 'Conversation équipe';
        }

        $this->db->beginTransaction();

        try {
            $thread = $this->db->prepare("
                INSERT INTO message_threads (cabinet_id, patient_id, subject, status, updated_at)
                VALUES (:cabinet_id, :patient_id, :subject, 'open', NOW())
            ");
            $thread->execute([
                'cabinet_id' => $cabinetId,
                'patient_id' => $patientId > 0 ? $patientId : null,
                'subject' => $subject,
            ]);

            $threadId = (int) $this->db->lastInsertId();
            $this->addParticipant($threadId, $userId, null);

            if ($patientId > 0) {
                $this->addParticipant($threadId, null, $patientId);
            }

            $message = $this->db->prepare("
                INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at)
                VALUES (:thread_id, :sender_user_id, NULL, :body, NOW())
            ");
            $message->execute([
                'thread_id' => $threadId,
                'sender_user_id' => $userId,
                'body' => $body,
            ]);

            $this->db->commit();

            return $threadId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function reply(int $cabinetId, int $threadId, string $body, int $userId): void
    {
        $body = trim($body);

        if ($body === '' || !$this->threadExists($cabinetId, $threadId)) {
            return;
        }

        $this->db->beginTransaction();

        try {
            $message = $this->db->prepare("
                INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at)
                VALUES (:thread_id, :sender_user_id, NULL, :body, NOW())
            ");
            $message->execute([
                'thread_id' => $threadId,
                'sender_user_id' => $userId,
                'body' => $body,
            ]);

            $thread = $this->db->prepare("
                UPDATE message_threads
                SET status = 'open',
                    updated_at = NOW()
                WHERE cabinet_id = :cabinet_id
                  AND id = :id
            ");
            $thread->execute([
                'cabinet_id' => $cabinetId,
                'id' => $threadId,
            ]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function setStatus(int $cabinetId, int $threadId, string $status): void
    {
        if (!in_array($status, ['open', 'closed', 'archived'], true)) {
            return;
        }

        $statement = $this->db->prepare("
            UPDATE message_threads
            SET status = :status,
                updated_at = NOW()
            WHERE cabinet_id = :cabinet_id
              AND id = :id
        ");
        $statement->execute([
            'status' => $status,
            'cabinet_id' => $cabinetId,
            'id' => $threadId,
        ]);
    }

    public function markRead(int $cabinetId, int $threadId, int $userId): void
    {
        if (!$this->threadExists($cabinetId, $threadId)) {
            return;
        }

        $statement = $this->db->prepare("
            UPDATE messages
            SET read_at = NOW()
            WHERE thread_id = :thread_id
              AND (
                sender_patient_id IS NOT NULL
                OR (sender_user_id IS NOT NULL AND sender_user_id != :user_id)
              )
              AND read_at IS NULL
        ");
        $statement->execute([
            'thread_id' => $threadId,
            'user_id' => $userId,
        ]);
    }

    public function formOptions(int $cabinetId): array
    {
        return [
            'patients' => $this->patients($cabinetId),
            'users' => $this->users($cabinetId),
        ];
    }

    private function messages(int $threadId): array
    {
        $statement = $this->db->prepare("
            SELECT
                m.*,
                u.full_name AS sender_user_name,
                p.first_name AS sender_patient_first_name,
                p.last_name AS sender_patient_last_name
            FROM messages m
            LEFT JOIN users u ON u.id = m.sender_user_id
            LEFT JOIN patients p ON p.id = m.sender_patient_id
            WHERE m.thread_id = :thread_id
            ORDER BY m.created_at ASC, m.id ASC
        ");
        $statement->execute(['thread_id' => $threadId]);

        return $statement->fetchAll();
    }

    private function addParticipant(int $threadId, ?int $userId, ?int $patientId): void
    {
        $statement = $this->db->prepare("
            INSERT INTO message_participants (thread_id, user_id, patient_id, last_read_at)
            VALUES (:thread_id, :user_id, :patient_id, NOW())
        ");
        $statement->execute([
            'thread_id' => $threadId,
            'user_id' => $userId,
            'patient_id' => $patientId,
        ]);
    }

    private function patientExists(int $cabinetId, int $patientId): bool
    {
        $statement = $this->db->prepare("
            SELECT 1
            FROM patients
            WHERE cabinet_id = :cabinet_id
              AND id = :id
              AND status != 'archived'
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $patientId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    private function threadExists(int $cabinetId, int $threadId): bool
    {
        $statement = $this->db->prepare("
            SELECT 1
            FROM message_threads
            WHERE cabinet_id = :cabinet_id
              AND id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $threadId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    private function appointment(int $cabinetId, int $patientId, string $direction): ?array
    {
        $operator = $direction === 'next' ? '>=' : '<=';
        $order = $direction === 'next' ? 'ASC' : 'DESC';

        $statement = $this->db->prepare("
            SELECT
                a.starts_at,
                a.ends_at,
                a.status,
                at.name AS type_name,
                u.full_name AS practitioner_name
            FROM appointments a
            LEFT JOIN appointment_types at ON at.id = a.appointment_type_id
            LEFT JOIN users u ON u.id = a.practitioner_id
            WHERE a.cabinet_id = :cabinet_id
              AND a.patient_id = :patient_id
              AND a.starts_at {$operator} NOW()
              AND a.status IN ('pending', 'confirmed', 'completed')
            ORDER BY a.starts_at {$order}
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'patient_id' => $patientId,
        ]);

        $appointment = $statement->fetch();

        return $appointment ?: null;
    }

    private function currentTreatment(int $cabinetId, int $patientId): ?array
    {
        $statement = $this->db->prepare("
            SELECT title, reference, status, progress, total_amount
            FROM treatment_plans
            WHERE cabinet_id = :cabinet_id
              AND patient_id = :patient_id
              AND status IN ('in_progress', 'pending', 'suspended')
            ORDER BY FIELD(status, 'in_progress', 'pending', 'suspended'), updated_at DESC, id DESC
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'patient_id' => $patientId,
        ]);

        $treatment = $statement->fetch();

        return $treatment ?: null;
    }

    private function patients(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, reference, first_name, last_name
            FROM patients
            WHERE cabinet_id = :cabinet_id
              AND status != 'archived'
            ORDER BY last_name ASC, first_name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function users(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT u.id, u.full_name, r.label AS role_label
            FROM users u
            INNER JOIN user_cabinets uc ON uc.user_id = u.id
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE uc.cabinet_id = :cabinet_id
              AND u.status = 'active'
            ORDER BY u.full_name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function filterSql(string $search, string $scope, int $userId): array
    {
        $where = '';
        $params = [];

        if ($scope === 'archived') {
            $where .= " AND mt.status = 'archived'";
        } else {
            $where .= " AND mt.status != 'archived'";
        }

        if ($scope === 'unread') {
            $where .= "
                AND EXISTS (
                    SELECT 1
                    FROM messages unread_messages
                    WHERE unread_messages.thread_id = mt.id
                      AND unread_messages.read_at IS NULL
                      AND (
                        unread_messages.sender_patient_id IS NOT NULL
                        OR (unread_messages.sender_user_id IS NOT NULL AND unread_messages.sender_user_id != :unread_user_id)
                      )
                )
            ";
            $params['unread_user_id'] = $userId;
        }

        if ($scope === 'patients') {
            $where .= ' AND mt.patient_id IS NOT NULL';
        }

        if ($scope === 'team') {
            $where .= ' AND mt.patient_id IS NULL';
        }

        if ($search !== '') {
            $where .= "
                AND (
                    mt.subject LIKE :search
                    OR p.reference LIKE :search
                    OR p.first_name LIKE :search
                    OR p.last_name LIKE :search
                    OR CONCAT(p.last_name, ' ', p.first_name) LIKE :search
                    OR p.phone LIKE :search
                    OR p.email LIKE :search
                    OR lm.body LIKE :search
                )
            ";
            $params['search'] = '%' . $search . '%';
        }

        return ['where' => $where, 'params' => $params];
    }
}
