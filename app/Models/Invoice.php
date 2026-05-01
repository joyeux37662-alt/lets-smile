<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Invoice
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
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total_amount ELSE 0 END), 0) AS turnover,
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN paid_amount ELSE 0 END), 0) AS collected,
                COALESCE(SUM(CASE WHEN status IN ('pending', 'partial', 'overdue') THEN total_amount - paid_amount ELSE 0 END), 0) AS pending_amount,
                SUM(status IN ('pending', 'partial', 'overdue')) AS pending_count,
                COALESCE(SUM(CASE WHEN status = 'overdue' THEN total_amount - paid_amount ELSE 0 END), 0) AS overdue_amount,
                SUM(status = 'overdue') AS overdue_count
            FROM invoices
            WHERE cabinet_id = :cabinet_id
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $stats = $statement->fetch() ?: [];

        return [
            'turnover' => (float) ($stats['turnover'] ?? 0),
            'collected' => (float) ($stats['collected'] ?? 0),
            'pending_amount' => (float) ($stats['pending_amount'] ?? 0),
            'pending_count' => (int) ($stats['pending_count'] ?? 0),
            'overdue_amount' => (float) ($stats['overdue_amount'] ?? 0),
            'overdue_count' => (int) ($stats['overdue_count'] ?? 0),
        ];
    }

    public function paginate(int $cabinetId, string $search, string $status, int $page = 1, int $perPage = 8): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $filter = $this->filterSql($search, $status);

        $count = $this->db->prepare("
            SELECT COUNT(*)
            FROM invoices i
            INNER JOIN patients p ON p.id = i.patient_id
            LEFT JOIN treatment_plans tp ON tp.id = i.treatment_plan_id
            WHERE i.cabinet_id = :cabinet_id
              {$filter['where']}
        ");
        $count->execute($filter['params'] + ['cabinet_id' => $cabinetId]);
        $total = (int) $count->fetchColumn();

        $statement = $this->db->prepare("
            SELECT
                i.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                tp.reference AS treatment_reference,
                tp.title AS treatment_title
            FROM invoices i
            INNER JOIN patients p ON p.id = i.patient_id
            LEFT JOIN treatment_plans tp ON tp.id = i.treatment_plan_id
            WHERE i.cabinet_id = :cabinet_id
              {$filter['where']}
            ORDER BY i.issue_date DESC, i.id DESC
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
                i.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                tp.reference AS treatment_reference,
                tp.title AS treatment_title
            FROM invoices i
            INNER JOIN patients p ON p.id = i.patient_id
            LEFT JOIN treatment_plans tp ON tp.id = i.treatment_plan_id
            WHERE i.cabinet_id = :cabinet_id
              AND i.id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        $invoice = $statement->fetch();

        if (!$invoice) {
            return null;
        }

        $invoice['items'] = $this->items($id);

        return $invoice;
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
            $items = $this->sanitizeItems($data['items'] ?? []);
            $total = $this->totalFromItems($items);
            $paid = min($total, (float) $data['paid_amount']);
            $status = $this->normalizeStatus($data['status'], $total, $paid, $data['due_date']);

            $statement = $this->db->prepare("
                INSERT INTO invoices (
                    cabinet_id, patient_id, treatment_plan_id, quote_id, number, issue_date,
                    due_date, status, total_amount, paid_amount, currency, notes
                ) VALUES (
                    :cabinet_id, :patient_id, :treatment_plan_id, NULL, :number, :issue_date,
                    :due_date, :status, :total_amount, :paid_amount, 'MGA', :notes
                )
            ");
            $statement->execute([
                'cabinet_id' => $cabinetId,
                'patient_id' => $data['patient_id'],
                'treatment_plan_id' => $data['treatment_plan_id'] ?: null,
                'number' => $this->nextNumber($cabinetId),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?: null,
                'status' => $status,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'notes' => $data['notes'] ?: null,
            ]);

            $invoiceId = (int) $this->db->lastInsertId();
            $this->replaceItems($invoiceId, $items);
            $this->db->commit();

            return $invoiceId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function update(int $cabinetId, int $id, array $data): void
    {
        $this->db->beginTransaction();

        try {
            $items = $this->sanitizeItems($data['items'] ?? []);
            $total = $this->totalFromItems($items);
            $paid = min($total, (float) $data['paid_amount']);
            $status = $this->normalizeStatus($data['status'], $total, $paid, $data['due_date']);

            $statement = $this->db->prepare("
                UPDATE invoices
                SET patient_id = :patient_id,
                    treatment_plan_id = :treatment_plan_id,
                    issue_date = :issue_date,
                    due_date = :due_date,
                    status = :status,
                    total_amount = :total_amount,
                    paid_amount = :paid_amount,
                    notes = :notes
                WHERE cabinet_id = :cabinet_id
                  AND id = :id
            ");
            $statement->execute([
                'patient_id' => $data['patient_id'],
                'treatment_plan_id' => $data['treatment_plan_id'] ?: null,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?: null,
                'status' => $status,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'notes' => $data['notes'] ?: null,
                'cabinet_id' => $cabinetId,
                'id' => $id,
            ]);

            $this->replaceItems($id, $items);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function setStatus(int $cabinetId, int $id, string $status): void
    {
        if (!in_array($status, ['draft', 'pending', 'paid', 'partial', 'overdue', 'cancelled', 'credit_note'], true)) {
            return;
        }

        $statement = $this->db->prepare("
            UPDATE invoices
            SET status = :status,
                paid_amount = CASE WHEN :paid_status = 'paid' THEN total_amount ELSE paid_amount END
            WHERE cabinet_id = :cabinet_id
              AND id = :id
        ");
        $statement->execute([
            'status' => $status,
            'paid_status' => $status,
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);
    }

    public function formOptions(int $cabinetId): array
    {
        return [
            'patients' => $this->patients($cabinetId),
            'treatments' => $this->treatments($cabinetId),
        ];
    }

    private function items(int $invoiceId): array
    {
        $statement = $this->db->prepare("
            SELECT *
            FROM invoice_items
            WHERE invoice_id = :id
            ORDER BY id ASC
        ");
        $statement->execute(['id' => $invoiceId]);

        return $statement->fetchAll();
    }

    private function replaceItems(int $invoiceId, array $items): void
    {
        $this->db->prepare('DELETE FROM invoice_items WHERE invoice_id = :id')->execute(['id' => $invoiceId]);

        $statement = $this->db->prepare("
            INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total)
            VALUES (:invoice_id, :description, :quantity, :unit_price, :total)
        ");

        foreach ($items as $item) {
            $statement->execute([
                'invoice_id' => $invoiceId,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $item['total'],
            ]);
        }
    }

    private function sanitizeItems(array $rawItems): array
    {
        $items = [];
        $descriptions = $rawItems['description'] ?? [];

        foreach ($descriptions as $index => $description) {
            $description = trim((string) $description);

            if ($description === '') {
                continue;
            }

            $quantity = max(0.01, (float) str_replace(',', '.', (string) ($rawItems['quantity'][$index] ?? 1)));
            $unitPrice = max(0, (float) str_replace(',', '.', (string) ($rawItems['unit_price'][$index] ?? 0)));

            $items[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $quantity * $unitPrice,
            ];
        }

        return $items ?: [[
            'description' => 'Acte dentaire',
            'quantity' => 1,
            'unit_price' => 0,
            'total' => 0,
        ]];
    }

    private function totalFromItems(array $items): float
    {
        return (float) array_sum(array_column($items, 'total'));
    }

    private function normalizeStatus(string $status, float $total, float $paid, string $dueDate): string
    {
        if ($status === 'cancelled' || $status === 'draft' || $status === 'credit_note') {
            return $status;
        }

        if ($paid >= $total && $total > 0) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        if ($dueDate !== '' && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
            return 'overdue';
        }

        return in_array($status, ['pending', 'overdue'], true) ? $status : 'pending';
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

    private function treatments(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                tp.id,
                tp.reference,
                tp.title,
                tp.patient_id,
                tp.total_amount,
                p.first_name,
                p.last_name
            FROM treatment_plans tp
            INNER JOIN patients p ON p.id = tp.patient_id
            WHERE tp.cabinet_id = :cabinet_id
              AND tp.status != 'cancelled'
            ORDER BY tp.created_at DESC, tp.id DESC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function nextNumber(int $cabinetId): string
    {
        $statement = $this->db->prepare("
            SELECT number
            FROM invoices
            WHERE cabinet_id = :cabinet_id
              AND number LIKE :prefix
            ORDER BY id DESC
            LIMIT 1
        ");
        $prefix = 'F-' . date('Y') . '-';
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'prefix' => $prefix . '%',
        ]);
        $number = (string) ($statement->fetchColumn() ?: '');
        preg_match('/(\d+)$/', $number, $matches);
        $next = ((int) ($matches[1] ?? 0)) + 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function filterSql(string $search, string $status): array
    {
        $where = '';
        $params = [];

        if ($search !== '') {
            $where .= "
                AND (
                    i.number LIKE :search
                    OR p.reference LIKE :search
                    OR p.first_name LIKE :search
                    OR p.last_name LIKE :search
                    OR CONCAT(p.last_name, ' ', p.first_name) LIKE :search
                    OR tp.reference LIKE :search
                    OR tp.title LIKE :search
                )
            ";
            $params['search'] = '%' . $search . '%';
        }

        if ($status === 'unpaid') {
            $where .= " AND i.status IN ('pending', 'partial', 'overdue')";
        } elseif (in_array($status, ['draft', 'pending', 'paid', 'partial', 'overdue', 'cancelled', 'credit_note'], true)) {
            $where .= ' AND i.status = :status';
            $params['status'] = $status;
        }

        return ['where' => $where, 'params' => $params];
    }
}
