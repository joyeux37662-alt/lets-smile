<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Treatment
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
                SUM(status = 'in_progress') AS in_progress,
                SUM(status = 'pending') AS pending,
                SUM(status = 'completed') AS completed
            FROM treatment_plans
            WHERE cabinet_id = :cabinet_id
              AND status != 'cancelled'
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $stats = $statement->fetch() ?: [];

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'in_progress' => (int) ($stats['in_progress'] ?? 0),
            'pending' => (int) ($stats['pending'] ?? 0),
            'completed' => (int) ($stats['completed'] ?? 0),
        ];
    }

    public function paginate(int $cabinetId, string $search, string $status, int $page = 1, int $perPage = 8): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $filter = $this->filterSql($search, $status);

        $count = $this->db->prepare("
            SELECT COUNT(*)
            FROM treatment_plans tp
            INNER JOIN patients p ON p.id = tp.patient_id
            LEFT JOIN treatment_categories tc ON tc.id = tp.category_id
            LEFT JOIN users u ON u.id = tp.practitioner_id
            WHERE tp.cabinet_id = :cabinet_id
              AND tp.status != 'cancelled'
              {$filter['where']}
        ");
        $count->execute($filter['params'] + ['cabinet_id' => $cabinetId]);
        $total = (int) $count->fetchColumn();

        $statement = $this->db->prepare("
            SELECT
                tp.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                tc.name AS category_name,
                tc.color AS category_color,
                u.full_name AS practitioner_name,
                COALESCE((
                    SELECT SUM(i.paid_amount)
                    FROM invoices i
                    WHERE i.treatment_plan_id = tp.id
                      AND i.status != 'cancelled'
                ), 0) AS paid_amount,
                GREATEST(tp.total_amount - COALESCE((
                    SELECT SUM(i.paid_amount)
                    FROM invoices i
                    WHERE i.treatment_plan_id = tp.id
                      AND i.status != 'cancelled'
                ), 0), 0) AS remaining_amount,
                (
                    SELECT COUNT(*)
                    FROM treatment_steps ts
                    WHERE ts.treatment_plan_id = tp.id
                ) AS steps_count,
                (
                    SELECT COUNT(*)
                    FROM treatment_steps ts
                    WHERE ts.treatment_plan_id = tp.id
                      AND ts.status = 'completed'
                ) AS completed_steps
            FROM treatment_plans tp
            INNER JOIN patients p ON p.id = tp.patient_id
            LEFT JOIN treatment_categories tc ON tc.id = tp.category_id
            LEFT JOIN users u ON u.id = tp.practitioner_id
            WHERE tp.cabinet_id = :cabinet_id
              AND tp.status != 'cancelled'
              {$filter['where']}
            ORDER BY
                FIELD(tp.status, 'in_progress', 'pending', 'suspended', 'completed'),
                tp.updated_at DESC,
                tp.created_at DESC,
                tp.id DESC
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
                tp.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                p.address,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                tc.name AS category_name,
                tc.color AS category_color,
                u.full_name AS practitioner_name,
                COALESCE((
                    SELECT SUM(i.paid_amount)
                    FROM invoices i
                    WHERE i.treatment_plan_id = tp.id
                      AND i.status != 'cancelled'
                ), 0) AS paid_amount,
                GREATEST(tp.total_amount - COALESCE((
                    SELECT SUM(i.paid_amount)
                    FROM invoices i
                    WHERE i.treatment_plan_id = tp.id
                      AND i.status != 'cancelled'
                ), 0), 0) AS remaining_amount
            FROM treatment_plans tp
            INNER JOIN patients p ON p.id = tp.patient_id
            LEFT JOIN treatment_categories tc ON tc.id = tp.category_id
            LEFT JOIN users u ON u.id = tp.practitioner_id
            WHERE tp.cabinet_id = :cabinet_id
              AND tp.id = :id
              AND tp.status != 'cancelled'
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        $treatment = $statement->fetch();

        if (!$treatment) {
            return null;
        }

        $treatment['steps'] = $this->steps($id);
        $treatment['invoices_count'] = $this->invoicesCount($id);
        $treatment['quotes_count'] = $this->quotesCount($id);

        return $treatment;
    }

    public function firstId(int $cabinetId, string $search, string $status): ?int
    {
        $page = $this->paginate($cabinetId, $search, $status, 1, 1);

        return isset($page['data'][0]['id']) ? (int) $page['data'][0]['id'] : null;
    }

    public function create(int $cabinetId, array $data): int
    {
        $this->db->beginTransaction();

        try {
            $steps = $this->sanitizeSteps($data['steps'] ?? []);
            $totalAmount = $this->totalFromSteps($steps, (float) $data['total_amount']);
            $progress = $this->progressFromSteps($steps, (int) $data['progress']);

            $statement = $this->db->prepare("
                INSERT INTO treatment_plans (
                    cabinet_id, patient_id, practitioner_id, category_id, reference, title,
                    status, progress, total_amount, started_at, completed_at
                ) VALUES (
                    :cabinet_id, :patient_id, :practitioner_id, :category_id, :reference, :title,
                    :status, :progress, :total_amount, :started_at, :completed_at
                )
            ");
            $statement->execute([
                'cabinet_id' => $cabinetId,
                'patient_id' => $data['patient_id'],
                'practitioner_id' => $data['practitioner_id'],
                'category_id' => $data['category_id'] ?: null,
                'reference' => $this->nextReference($cabinetId),
                'title' => $data['title'],
                'status' => $data['status'],
                'progress' => $progress,
                'total_amount' => $totalAmount,
                'started_at' => $data['started_at'] ?: null,
                'completed_at' => $data['status'] === 'completed' ? date('Y-m-d') : null,
            ]);

            $treatmentId = (int) $this->db->lastInsertId();
            $this->replaceSteps($treatmentId, $steps);
            $this->db->commit();

            return $treatmentId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function update(int $cabinetId, int $id, array $data): void
    {
        $this->db->beginTransaction();

        try {
            $steps = $this->sanitizeSteps($data['steps'] ?? []);
            $totalAmount = $this->totalFromSteps($steps, (float) $data['total_amount']);
            $progress = $this->progressFromSteps($steps, (int) $data['progress']);

            $statement = $this->db->prepare("
                UPDATE treatment_plans
                SET patient_id = :patient_id,
                    practitioner_id = :practitioner_id,
                    category_id = :category_id,
                    title = :title,
                    status = :status,
                    progress = :progress,
                    total_amount = :total_amount,
                    started_at = :started_at,
                    completed_at = :completed_at
                WHERE cabinet_id = :cabinet_id
                  AND id = :id
            ");
            $statement->execute([
                'patient_id' => $data['patient_id'],
                'practitioner_id' => $data['practitioner_id'],
                'category_id' => $data['category_id'] ?: null,
                'title' => $data['title'],
                'status' => $data['status'],
                'progress' => $progress,
                'total_amount' => $totalAmount,
                'started_at' => $data['started_at'] ?: null,
                'completed_at' => $data['status'] === 'completed' ? ($data['completed_at'] ?: date('Y-m-d')) : null,
                'cabinet_id' => $cabinetId,
                'id' => $id,
            ]);

            $this->replaceSteps($id, $steps);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function setStatus(int $cabinetId, int $id, string $status): void
    {
        if (!in_array($status, ['pending', 'in_progress', 'completed', 'suspended', 'cancelled'], true)) {
            return;
        }

        $statement = $this->db->prepare("
            UPDATE treatment_plans
            SET status = :status,
                progress = :progress,
                completed_at = :completed_at
            WHERE cabinet_id = :cabinet_id
              AND id = :id
        ");
        $statement->execute([
            'status' => $status,
            'progress' => $status === 'completed' ? 100 : $this->currentProgress($cabinetId, $id),
            'completed_at' => $status === 'completed' ? date('Y-m-d') : null,
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        if ($status === 'completed') {
            $this->db->prepare("
                UPDATE treatment_steps
                SET status = 'completed',
                    completed_at = COALESCE(completed_at, NOW())
                WHERE treatment_plan_id = :id
                  AND status != 'cancelled'
            ")->execute(['id' => $id]);
        }
    }

    private function currentProgress(int $cabinetId, int $id): int
    {
        $statement = $this->db->prepare("
            SELECT progress
            FROM treatment_plans
            WHERE cabinet_id = :cabinet_id
              AND id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        return (int) ($statement->fetchColumn() ?: 0);
    }

    public function formOptions(int $cabinetId): array
    {
        return [
            'patients' => $this->patients($cabinetId),
            'practitioners' => $this->practitioners($cabinetId),
            'categories' => $this->categories($cabinetId),
        ];
    }

    private function steps(int $treatmentId): array
    {
        $statement = $this->db->prepare("
            SELECT *
            FROM treatment_steps
            WHERE treatment_plan_id = :id
            ORDER BY sort_order ASC, id ASC
        ");
        $statement->execute(['id' => $treatmentId]);

        return $statement->fetchAll();
    }

    private function replaceSteps(int $treatmentId, array $steps): void
    {
        $this->db->prepare('DELETE FROM treatment_steps WHERE treatment_plan_id = :id')->execute(['id' => $treatmentId]);

        $statement = $this->db->prepare("
            INSERT INTO treatment_steps (
                treatment_plan_id, title, description, planned_date, completed_at, status, amount, sort_order
            ) VALUES (
                :treatment_plan_id, :title, :description, :planned_date, :completed_at, :status, :amount, :sort_order
            )
        ");

        foreach ($steps as $index => $step) {
            $statement->execute([
                'treatment_plan_id' => $treatmentId,
                'title' => $step['title'],
                'description' => $step['description'] ?: null,
                'planned_date' => $step['planned_date'] ?: null,
                'completed_at' => $step['status'] === 'completed' ? ($step['completed_at'] ?: date('Y-m-d H:i:s')) : null,
                'status' => $step['status'],
                'amount' => $step['amount'],
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function sanitizeSteps(array $rawSteps): array
    {
        $steps = [];
        $titles = $rawSteps['title'] ?? [];

        foreach ($titles as $index => $title) {
            $title = trim((string) $title);

            if ($title === '') {
                continue;
            }

            $status = trim((string) ($rawSteps['status'][$index] ?? 'pending'));
            $status = in_array($status, ['pending', 'completed', 'cancelled'], true) ? $status : 'pending';

            $steps[] = [
                'title' => $title,
                'description' => trim((string) ($rawSteps['description'][$index] ?? '')),
                'planned_date' => trim((string) ($rawSteps['planned_date'][$index] ?? '')),
                'completed_at' => trim((string) ($rawSteps['completed_at'][$index] ?? '')),
                'status' => $status,
                'amount' => max(0, (float) str_replace(',', '.', (string) ($rawSteps['amount'][$index] ?? 0))),
            ];
        }

        return $steps;
    }

    private function totalFromSteps(array $steps, float $fallback): float
    {
        $total = array_sum(array_column($steps, 'amount'));

        return $total > 0 ? (float) $total : max(0, $fallback);
    }

    private function progressFromSteps(array $steps, int $fallback): int
    {
        $billableSteps = array_filter($steps, static fn (array $step): bool => $step['status'] !== 'cancelled');

        if ($billableSteps === []) {
            return max(0, min(100, $fallback));
        }

        $completed = count(array_filter($billableSteps, static fn (array $step): bool => $step['status'] === 'completed'));

        return (int) round(($completed / count($billableSteps)) * 100);
    }

    private function patients(int $cabinetId): array
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

    private function practitioners(int $cabinetId): array
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

    private function categories(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, name, color
            FROM treatment_categories
            WHERE cabinet_id = :cabinet_id
            ORDER BY name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function invoicesCount(int $treatmentId): int
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM invoices WHERE treatment_plan_id = :id AND status != 'cancelled'");
        $statement->execute(['id' => $treatmentId]);

        return (int) $statement->fetchColumn();
    }

    private function quotesCount(int $treatmentId): int
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM quotes WHERE treatment_plan_id = :id AND status != 'cancelled'");
        $statement->execute(['id' => $treatmentId]);

        return (int) $statement->fetchColumn();
    }

    private function nextReference(int $cabinetId): string
    {
        $statement = $this->db->prepare("
            SELECT reference
            FROM treatment_plans
            WHERE cabinet_id = :cabinet_id
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $reference = (string) ($statement->fetchColumn() ?: '');
        preg_match('/(\d+)$/', $reference, $matches);
        $next = ((int) ($matches[1] ?? 0)) + 1;

        return 'T-' . date('Y') . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function filterSql(string $search, string $status): array
    {
        $where = '';
        $params = [];

        if ($search !== '') {
            $where .= "
                AND (
                    tp.reference LIKE :search
                    OR tp.title LIKE :search
                    OR p.reference LIKE :search
                    OR p.first_name LIKE :search
                    OR p.last_name LIKE :search
                    OR CONCAT(p.last_name, ' ', p.first_name) LIKE :search
                    OR tc.name LIKE :search
                    OR u.full_name LIKE :search
                )
            ";
            $params['search'] = '%' . $search . '%';
        }

        if (in_array($status, ['pending', 'in_progress', 'completed', 'suspended'], true)) {
            $where .= ' AND tp.status = :status';
            $params['status'] = $status;
        }

        return ['where' => $where, 'params' => $params];
    }
}
