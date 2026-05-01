<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Patient
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
                COUNT(*) AS total,
                SUM(created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')) AS new_this_month,
                SUM(status = 'active') AS active,
                SUM(status != 'active') AS inactive
            FROM patients
            WHERE cabinet_id = :cabinet_id
              AND status != 'archived'
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $stats = $statement->fetch() ?: [];

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'new_this_month' => (int) ($stats['new_this_month'] ?? 0),
            'active' => (int) ($stats['active'] ?? 0),
            'inactive' => (int) ($stats['inactive'] ?? 0),
        ];
    }

    public function paginate(int $cabinetId, string $search, int $page = 1, int $perPage = 8): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $filter = $this->searchSql($search);

        $count = $this->db->prepare("
            SELECT COUNT(*)
            FROM patients p
            WHERE p.cabinet_id = :cabinet_id
              AND p.status != 'archived'
              {$filter['where']}
        ");
        $count->execute($filter['params'] + ['cabinet_id' => $cabinetId]);
        $total = (int) $count->fetchColumn();

        $statement = $this->db->prepare("
            SELECT
                p.*,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS age,
                (
                    SELECT MAX(a.starts_at)
                    FROM appointments a
                    WHERE a.patient_id = p.id
                      AND a.status IN ('confirmed', 'completed')
                      AND a.starts_at <= NOW()
                ) AS last_appointment_at,
                (
                    SELECT MIN(a.starts_at)
                    FROM appointments a
                    WHERE a.patient_id = p.id
                      AND a.status IN ('pending', 'confirmed')
                      AND a.starts_at >= NOW()
                ) AS next_appointment_at
            FROM patients p
            WHERE p.cabinet_id = :cabinet_id
              AND p.status != 'archived'
              {$filter['where']}
            ORDER BY p.status = 'active' DESC, p.created_at DESC, p.id DESC
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
                p.*,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS age,
                mr.blood_group,
                mr.allergies,
                mr.medical_history,
                mr.current_medications,
                mr.notes AS medical_notes
            FROM patients p
            LEFT JOIN patient_medical_records mr ON mr.patient_id = p.id
            WHERE p.cabinet_id = :cabinet_id
              AND p.id = :id
              AND p.status != 'archived'
            ORDER BY mr.updated_at DESC, mr.id DESC
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        $patient = $statement->fetch();

        if (!$patient) {
            return null;
        }

        $patient['next_appointment'] = $this->nextAppointment($cabinetId, $id);

        return $patient;
    }

    public function firstId(int $cabinetId): ?int
    {
        $statement = $this->db->prepare("
            SELECT id
            FROM patients
            WHERE cabinet_id = :cabinet_id
              AND status != 'archived'
            ORDER BY status = 'active' DESC, created_at DESC, id DESC
            LIMIT 1
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $id = $statement->fetchColumn();

        return $id ? (int) $id : null;
    }

    public function create(int $cabinetId, array $data, ?int $userId): int
    {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare("
                INSERT INTO patients (
                    cabinet_id, reference, first_name, last_name, gender, birth_date,
                    phone, email, address, profession, status
                ) VALUES (
                    :cabinet_id, :reference, :first_name, :last_name, :gender, :birth_date,
                    :phone, :email, :address, :profession, :status
                )
            ");
            $statement->execute([
                'cabinet_id' => $cabinetId,
                'reference' => $this->nextReference($cabinetId),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?: null,
                'birth_date' => $data['birth_date'] ?: null,
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'address' => $data['address'] ?: null,
                'profession' => $data['profession'] ?: null,
                'status' => $data['status'] ?: 'active',
            ]);

            $patientId = (int) $this->db->lastInsertId();

            $medical = $this->db->prepare("
                INSERT INTO patient_medical_records (
                    patient_id, blood_group, allergies, medical_history, current_medications, notes, updated_by
                ) VALUES (
                    :patient_id, :blood_group, :allergies, :medical_history, :current_medications, :notes, :updated_by
                )
            ");
            $medical->execute([
                'patient_id' => $patientId,
                'blood_group' => $data['blood_group'] ?: null,
                'allergies' => $data['allergies'] ?: null,
                'medical_history' => $data['medical_history'] ?: null,
                'current_medications' => $data['current_medications'] ?: null,
                'notes' => $data['medical_notes'] ?: null,
                'updated_by' => $userId,
            ]);

            $this->db->commit();

            return $patientId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function update(int $cabinetId, int $id, array $data, ?int $userId): void
    {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare("
                UPDATE patients
                SET first_name = :first_name,
                    last_name = :last_name,
                    gender = :gender,
                    birth_date = :birth_date,
                    phone = :phone,
                    email = :email,
                    address = :address,
                    profession = :profession,
                    status = :status
                WHERE cabinet_id = :cabinet_id
                  AND id = :id
            ");
            $statement->execute([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?: null,
                'birth_date' => $data['birth_date'] ?: null,
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'address' => $data['address'] ?: null,
                'profession' => $data['profession'] ?: null,
                'status' => $data['status'] ?: 'active',
                'cabinet_id' => $cabinetId,
                'id' => $id,
            ]);

            $this->saveMedicalRecord($id, $data, $userId);

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function saveMedicalRecord(int $patientId, array $data, ?int $userId): void
    {
        $existing = $this->db->prepare("
            SELECT id
            FROM patient_medical_records
            WHERE patient_id = :patient_id
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");
        $existing->execute(['patient_id' => $patientId]);
        $recordId = $existing->fetchColumn();

        $payload = [
            'blood_group' => $data['blood_group'] ?: null,
            'allergies' => $data['allergies'] ?: null,
            'medical_history' => $data['medical_history'] ?: null,
            'current_medications' => $data['current_medications'] ?: null,
            'notes' => $data['medical_notes'] ?: null,
            'updated_by' => $userId,
        ];

        if ($recordId) {
            $statement = $this->db->prepare("
                UPDATE patient_medical_records
                SET blood_group = :blood_group,
                    allergies = :allergies,
                    medical_history = :medical_history,
                    current_medications = :current_medications,
                    notes = :notes,
                    updated_by = :updated_by
                WHERE id = :id
            ");
            $statement->execute($payload + ['id' => (int) $recordId]);
            return;
        }

        $statement = $this->db->prepare("
            INSERT INTO patient_medical_records (
                patient_id, blood_group, allergies, medical_history, current_medications, notes, updated_by
            ) VALUES (
                :patient_id, :blood_group, :allergies, :medical_history, :current_medications, :notes, :updated_by
            )
        ");
        $statement->execute($payload + ['patient_id' => $patientId]);
    }

    public function archive(int $cabinetId, int $id): void
    {
        $statement = $this->db->prepare("
            UPDATE patients
            SET status = 'archived'
            WHERE cabinet_id = :cabinet_id
              AND id = :id
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);
    }

    private function nextAppointment(int $cabinetId, int $patientId): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                a.*,
                appointment_types.name AS type_name,
                users.full_name AS practitioner_name,
                rooms.name AS room_name
            FROM appointments a
            LEFT JOIN appointment_types ON appointment_types.id = a.appointment_type_id
            LEFT JOIN users ON users.id = a.practitioner_id
            LEFT JOIN rooms ON rooms.id = a.room_id
            WHERE a.cabinet_id = :cabinet_id
              AND a.patient_id = :patient_id
              AND a.status IN ('pending', 'confirmed')
              AND a.starts_at >= NOW()
            ORDER BY a.starts_at ASC
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'patient_id' => $patientId,
        ]);

        $appointment = $statement->fetch();

        return $appointment ?: null;
    }

    private function nextReference(int $cabinetId): string
    {
        $statement = $this->db->prepare("
            SELECT reference
            FROM patients
            WHERE cabinet_id = :cabinet_id
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $reference = (string) ($statement->fetchColumn() ?: '');
        preg_match('/(\d+)$/', $reference, $matches);
        $next = ((int) ($matches[1] ?? 0)) + 1;

        return 'PAT-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function searchSql(string $search): array
    {
        if ($search === '') {
            return ['where' => '', 'params' => []];
        }

        return [
            'where' => "
                AND (
                    p.reference LIKE :search
                    OR p.first_name LIKE :search
                    OR p.last_name LIKE :search
                    OR CONCAT(p.last_name, ' ', p.first_name) LIKE :search
                    OR p.phone LIKE :search
                    OR p.email LIKE :search
                )
            ",
            'params' => ['search' => '%' . $search . '%'],
        ];
    }
}
