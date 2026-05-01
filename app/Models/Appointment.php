<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;
use PDO;

final class Appointment
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function stats(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                SUM(DATE(starts_at) = CURRENT_DATE()) AS today,
                SUM(status = 'confirmed' AND starts_at >= CURRENT_DATE()) AS confirmed,
                SUM(status = 'pending' AND starts_at >= CURRENT_DATE()) AS pending,
                SUM(status = 'cancelled' AND starts_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')) AS cancelled
            FROM appointments
            WHERE cabinet_id = :cabinet_id
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $stats = $statement->fetch() ?: [];

        $upcoming = ((int) ($stats['confirmed'] ?? 0)) + ((int) ($stats['pending'] ?? 0)) + ((int) ($stats['cancelled'] ?? 0));

        return [
            'today' => (int) ($stats['today'] ?? 0),
            'confirmed' => (int) ($stats['confirmed'] ?? 0),
            'pending' => (int) ($stats['pending'] ?? 0),
            'cancelled' => (int) ($stats['cancelled'] ?? 0),
            'upcoming_total' => max(1, $upcoming),
        ];
    }

    public function paginate(int $cabinetId, string $search, string $status, int $page = 1, int $perPage = 8): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $filter = $this->filterSql($search, $status);

        $count = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            LEFT JOIN appointment_types at ON at.id = a.appointment_type_id
            LEFT JOIN users u ON u.id = a.practitioner_id
            LEFT JOIN rooms r ON r.id = a.room_id
            WHERE a.cabinet_id = :cabinet_id
              {$filter['where']}
        ");
        $count->execute($filter['params'] + ['cabinet_id' => $cabinetId]);
        $total = (int) $count->fetchColumn();

        $statement = $this->db->prepare("
            SELECT
                a.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                at.name AS type_name,
                at.color AS type_color,
                u.full_name AS practitioner_name,
                r.name AS room_name
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            LEFT JOIN appointment_types at ON at.id = a.appointment_type_id
            LEFT JOIN users u ON u.id = a.practitioner_id
            LEFT JOIN rooms r ON r.id = a.room_id
            WHERE a.cabinet_id = :cabinet_id
              {$filter['where']}
            ORDER BY
                a.starts_at >= NOW() DESC,
                CASE WHEN a.starts_at >= NOW() THEN a.starts_at END ASC,
                a.starts_at DESC,
                a.id DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($filter['params'] + ['cabinet_id' => $cabinetId] as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }

        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'data' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function find(int $cabinetId, int $id): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                a.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                at.name AS type_name,
                at.color AS type_color,
                u.full_name AS practitioner_name,
                r.name AS room_name
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            LEFT JOIN appointment_types at ON at.id = a.appointment_type_id
            LEFT JOIN users u ON u.id = a.practitioner_id
            LEFT JOIN rooms r ON r.id = a.room_id
            WHERE a.cabinet_id = :cabinet_id
              AND a.id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        $appointment = $statement->fetch();

        return $appointment ?: null;
    }

    public function weekAppointments(int $cabinetId, DateTimeImmutable $weekStart, array $filters = []): array
    {
        $weekEnd = $weekStart->modify('+5 days');
        $where = '';
        $params = [
            'cabinet_id' => $cabinetId,
            'start' => $weekStart->format('Y-m-d 00:00:00'),
            'end' => $weekEnd->format('Y-m-d 00:00:00'),
        ];

        if (!empty($filters['practitioner_id'])) {
            $where .= ' AND a.practitioner_id = :practitioner_id';
            $params['practitioner_id'] = (int) $filters['practitioner_id'];
        }

        if (!empty($filters['room_id'])) {
            $where .= ' AND a.room_id = :room_id';
            $params['room_id'] = (int) $filters['room_id'];
        }

        if (empty($filters['show_cancelled'])) {
            $where .= " AND a.status != 'cancelled'";
        }

        $statement = $this->db->prepare("
            SELECT
                a.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                at.name AS type_name,
                at.color AS type_color,
                u.full_name AS practitioner_name,
                r.name AS room_name
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            LEFT JOIN appointment_types at ON at.id = a.appointment_type_id
            LEFT JOIN users u ON u.id = a.practitioner_id
            LEFT JOIN rooms r ON r.id = a.room_id
            WHERE a.cabinet_id = :cabinet_id
              AND a.starts_at >= :start
              AND a.starts_at < :end
              {$where}
            ORDER BY a.starts_at ASC, a.id ASC
        ");
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function firstWeekId(int $cabinetId, DateTimeImmutable $weekStart, array $filters = []): ?int
    {
        $appointments = $this->weekAppointments($cabinetId, $weekStart, $filters);

        return isset($appointments[0]['id']) ? (int) $appointments[0]['id'] : null;
    }

    public function firstId(int $cabinetId, string $search, string $status): ?int
    {
        $page = $this->paginate($cabinetId, $search, $status, 1, 1);

        return isset($page['data'][0]['id']) ? (int) $page['data'][0]['id'] : null;
    }

    public function create(int $cabinetId, array $data, ?int $userId): int
    {
        $statement = $this->db->prepare("
            INSERT INTO appointments (
                cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id,
                starts_at, ends_at, status, notes, created_by
            ) VALUES (
                :cabinet_id, :patient_id, :practitioner_id, :room_id, :appointment_type_id,
                :starts_at, :ends_at, :status, :notes, :created_by
            )
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'patient_id' => $data['patient_id'],
            'practitioner_id' => $data['practitioner_id'],
            'room_id' => $data['room_id'] ?: null,
            'appointment_type_id' => $data['appointment_type_id'] ?: null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?: null,
            'created_by' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $cabinetId, int $id, array $data): void
    {
        $statement = $this->db->prepare("
            UPDATE appointments
            SET patient_id = :patient_id,
                practitioner_id = :practitioner_id,
                room_id = :room_id,
                appointment_type_id = :appointment_type_id,
                starts_at = :starts_at,
                ends_at = :ends_at,
                status = :status,
                notes = :notes,
                cancellation_reason = NULL
            WHERE cabinet_id = :cabinet_id
              AND id = :id
        ");
        $statement->execute([
            'patient_id' => $data['patient_id'],
            'practitioner_id' => $data['practitioner_id'],
            'room_id' => $data['room_id'] ?: null,
            'appointment_type_id' => $data['appointment_type_id'] ?: null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?: null,
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);
    }

    public function cancel(int $cabinetId, int $id, string $reason): void
    {
        $statement = $this->db->prepare("
            UPDATE appointments
            SET status = 'cancelled',
                cancellation_reason = :reason
            WHERE cabinet_id = :cabinet_id
              AND id = :id
        ");
        $statement->execute([
            'reason' => $reason ?: 'Annulation manuelle',
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);
    }

    public function formOptions(int $cabinetId): array
    {
        return [
            'patients' => $this->allPatients($cabinetId),
            'practitioners' => $this->allPractitioners($cabinetId),
            'rooms' => $this->allRooms($cabinetId),
            'types' => $this->allTypes($cabinetId),
        ];
    }

    private function allPatients(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, reference, first_name, last_name
            FROM patients
            WHERE cabinet_id = :cabinet_id
              AND status = 'active'
            ORDER BY last_name ASC, first_name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function allPractitioners(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT users.id, users.full_name
            FROM users
            INNER JOIN user_cabinets ON user_cabinets.user_id = users.id
            WHERE user_cabinets.cabinet_id = :cabinet_id
              AND users.status = 'active'
            ORDER BY users.full_name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function allRooms(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, name
            FROM rooms
            WHERE cabinet_id = :cabinet_id
              AND is_active = 1
            ORDER BY name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function allTypes(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, name, color, duration_minutes
            FROM appointment_types
            WHERE cabinet_id = :cabinet_id
              AND is_active = 1
            ORDER BY name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    public function normalizeDateTime(string $date, string $startTime, string $endTime): ?array
    {
        if ($date === '' || $startTime === '') {
            return null;
        }

        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $startTime);
        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . ($endTime ?: $startTime));

        if (!$start || !$end) {
            return null;
        }

        if ($end <= $start) {
            $end = $start->modify('+1 hour');
        }

        return [
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $end->format('Y-m-d H:i:s'),
        ];
    }

    private function filterSql(string $search, string $status): array
    {
        $where = '';
        $params = [];

        if ($search !== '') {
            $where .= "
                AND (
                    p.reference LIKE :search
                    OR p.first_name LIKE :search
                    OR p.last_name LIKE :search
                    OR CONCAT(p.last_name, ' ', p.first_name) LIKE :search
                    OR p.phone LIKE :search
                    OR at.name LIKE :search
                    OR u.full_name LIKE :search
                    OR r.name LIKE :search
                )
            ";
            $params['search'] = '%' . $search . '%';
        }

        if ($status === 'past') {
            $where .= " AND a.starts_at < NOW() AND a.status != 'cancelled'";
        } elseif (in_array($status, ['confirmed', 'pending', 'cancelled', 'completed', 'missed'], true)) {
            $where .= " AND a.status = :status";
            $params['status'] = $status;
        }

        return ['where' => $where, 'params' => $params];
    }
}
