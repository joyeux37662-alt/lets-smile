<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Payment
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function stats(int $cabinetId): array
    {
        $periodStart = date('Y-m-01 00:00:00');
        $periodEnd = date('Y-m-t 23:59:59');

        $statement = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN status = 'received' AND paid_at BETWEEN :period_start AND :period_end THEN amount ELSE 0 END), 0) AS received_amount,
                SUM(status = 'received' AND paid_at BETWEEN :period_start_count AND :period_end_count) AS received_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_amount,
                SUM(status = 'pending') AS pending_count,
                COALESCE(SUM(CASE WHEN status IN ('failed', 'cancelled') THEN amount ELSE 0 END), 0) AS failed_amount,
                SUM(status IN ('failed', 'cancelled')) AS failed_count,
                COALESCE(SUM(CASE WHEN status = 'refunded' THEN ABS(amount) ELSE 0 END), 0) AS refunded_amount,
                SUM(status = 'refunded') AS refunded_count
            FROM payments
            WHERE cabinet_id = :cabinet_id
        ");
        $statement->execute([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_start_count' => $periodStart,
            'period_end_count' => $periodEnd,
            'cabinet_id' => $cabinetId,
        ]);
        $stats = $statement->fetch() ?: [];

        return [
            'received_amount' => (float) ($stats['received_amount'] ?? 0),
            'received_count' => (int) ($stats['received_count'] ?? 0),
            'pending_amount' => (float) ($stats['pending_amount'] ?? 0),
            'pending_count' => (int) ($stats['pending_count'] ?? 0),
            'failed_amount' => (float) ($stats['failed_amount'] ?? 0),
            'failed_count' => (int) ($stats['failed_count'] ?? 0),
            'refunded_amount' => (float) ($stats['refunded_amount'] ?? 0),
            'refunded_count' => (int) ($stats['refunded_count'] ?? 0),
        ];
    }

    public function paginate(int $cabinetId, string $search, string $status, int $page = 1, int $perPage = 8): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $filter = $this->filterSql($search, $status);

        $count = $this->db->prepare("
            SELECT COUNT(*)
            FROM payments pay
            INNER JOIN patients p ON p.id = pay.patient_id
            INNER JOIN payment_methods pm ON pm.id = pay.method_id
            LEFT JOIN invoices i ON i.id = pay.invoice_id
            WHERE pay.cabinet_id = :cabinet_id
              {$filter['where']}
        ");
        $count->execute($filter['params'] + ['cabinet_id' => $cabinetId]);
        $total = (int) $count->fetchColumn();

        $statement = $this->db->prepare("
            SELECT
                pay.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                pm.name AS method_name,
                i.number AS invoice_number,
                i.total_amount AS invoice_total,
                i.paid_amount AS invoice_paid,
                i.status AS invoice_status,
                GREATEST(COALESCE(i.total_amount, 0) - COALESCE(i.paid_amount, 0), 0) AS invoice_remaining
            FROM payments pay
            INNER JOIN patients p ON p.id = pay.patient_id
            INNER JOIN payment_methods pm ON pm.id = pay.method_id
            LEFT JOIN invoices i ON i.id = pay.invoice_id
            WHERE pay.cabinet_id = :cabinet_id
              {$filter['where']}
            ORDER BY pay.paid_at DESC, pay.id DESC
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
                pay.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.birth_date,
                p.phone,
                p.email,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                pm.name AS method_name,
                i.number AS invoice_number,
                i.total_amount AS invoice_total,
                i.paid_amount AS invoice_paid,
                i.status AS invoice_status,
                GREATEST(COALESCE(i.total_amount, 0) - COALESCE(i.paid_amount, 0), 0) AS invoice_remaining,
                u.full_name AS created_by_name
            FROM payments pay
            INNER JOIN patients p ON p.id = pay.patient_id
            INNER JOIN payment_methods pm ON pm.id = pay.method_id
            LEFT JOIN invoices i ON i.id = pay.invoice_id
            LEFT JOIN users u ON u.id = pay.created_by
            WHERE pay.cabinet_id = :cabinet_id
              AND pay.id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        $payment = $statement->fetch();

        return $payment ?: null;
    }

    public function firstId(int $cabinetId, string $search, string $status): ?int
    {
        $page = $this->paginate($cabinetId, $search, $status, 1, 1);

        return isset($page['data'][0]['id']) ? (int) $page['data'][0]['id'] : null;
    }

    public function create(int $cabinetId, array $data, ?int $userId): int
    {
        $this->db->beginTransaction();

        try {
            $data = $this->normalizeData($cabinetId, $data);

            $statement = $this->db->prepare("
                INSERT INTO payments (
                    cabinet_id, invoice_id, patient_id, method_id, reference,
                    amount, status, paid_at, notes, created_by
                ) VALUES (
                    :cabinet_id, :invoice_id, :patient_id, :method_id, :reference,
                    :amount, :status, :paid_at, :notes, :created_by
                )
            ");
            $statement->execute([
                'cabinet_id' => $cabinetId,
                'invoice_id' => $data['invoice_id'] ?: null,
                'patient_id' => $data['patient_id'],
                'method_id' => $data['method_id'],
                'reference' => $this->nextReference($cabinetId),
                'amount' => $data['amount'],
                'status' => $data['status'],
                'paid_at' => $data['paid_at'],
                'notes' => $data['notes'] ?: null,
                'created_by' => $userId,
            ]);

            $paymentId = (int) $this->db->lastInsertId();
            $this->syncInvoice($cabinetId, $data['invoice_id'] ?: null);
            $this->db->commit();

            return $paymentId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function update(int $cabinetId, int $id, array $data): void
    {
        $this->db->beginTransaction();

        try {
            $oldInvoiceId = $this->invoiceIdForPayment($cabinetId, $id);
            $data = $this->normalizeData($cabinetId, $data);

            $statement = $this->db->prepare("
                UPDATE payments
                SET invoice_id = :invoice_id,
                    patient_id = :patient_id,
                    method_id = :method_id,
                    amount = :amount,
                    status = :status,
                    paid_at = :paid_at,
                    notes = :notes
                WHERE cabinet_id = :cabinet_id
                  AND id = :id
            ");
            $statement->execute([
                'invoice_id' => $data['invoice_id'] ?: null,
                'patient_id' => $data['patient_id'],
                'method_id' => $data['method_id'],
                'amount' => $data['amount'],
                'status' => $data['status'],
                'paid_at' => $data['paid_at'],
                'notes' => $data['notes'] ?: null,
                'cabinet_id' => $cabinetId,
                'id' => $id,
            ]);

            $this->syncInvoice($cabinetId, $oldInvoiceId);
            $this->syncInvoice($cabinetId, $data['invoice_id'] ?: null);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function setStatus(int $cabinetId, int $id, string $status): void
    {
        if (!in_array($status, ['received', 'pending', 'failed', 'refunded', 'cancelled'], true)) {
            return;
        }

        $this->db->beginTransaction();

        try {
            $invoiceId = $this->invoiceIdForPayment($cabinetId, $id);
            $statement = $this->db->prepare("
                UPDATE payments
                SET status = :status
                WHERE cabinet_id = :cabinet_id
                  AND id = :id
            ");
            $statement->execute([
                'status' => $status,
                'cabinet_id' => $cabinetId,
                'id' => $id,
            ]);
            $this->syncInvoice($cabinetId, $invoiceId);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function formOptions(int $cabinetId): array
    {
        return [
            'patients' => $this->patients($cabinetId),
            'invoices' => $this->invoices($cabinetId),
            'methods' => $this->methods($cabinetId),
        ];
    }

    private function normalizeData(int $cabinetId, array $data): array
    {
        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        $patientId = (int) ($data['patient_id'] ?? 0);

        if ($invoiceId > 0) {
            $invoice = $this->invoice($cabinetId, $invoiceId);

            if ($invoice !== null) {
                $patientId = (int) $invoice['patient_id'];
            }
        }

        return [
            'invoice_id' => $invoiceId,
            'patient_id' => $patientId,
            'method_id' => (int) ($data['method_id'] ?? 0),
            'amount' => max(0, (float) ($data['amount'] ?? 0)),
            'status' => in_array($data['status'] ?? '', ['received', 'pending', 'failed', 'refunded', 'cancelled'], true) ? $data['status'] : 'received',
            'paid_at' => $this->normalizePaidAt((string) ($data['paid_at'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];
    }

    private function normalizePaidAt(string $paidAt): string
    {
        $paidAt = trim($paidAt);

        if ($paidAt === '') {
            return date('Y-m-d H:i:s');
        }

        $paidAt = str_replace('T', ' ', $paidAt);

        if (strlen($paidAt) === 16) {
            $paidAt .= ':00';
        }

        return $paidAt;
    }

    private function invoiceIdForPayment(int $cabinetId, int $id): ?int
    {
        $statement = $this->db->prepare("
            SELECT invoice_id
            FROM payments
            WHERE cabinet_id = :cabinet_id
              AND id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        $invoiceId = $statement->fetchColumn();

        return $invoiceId ? (int) $invoiceId : null;
    }

    private function syncInvoice(int $cabinetId, ?int $invoiceId): void
    {
        if ($invoiceId === null || $invoiceId <= 0) {
            return;
        }

        $invoice = $this->invoice($cabinetId, $invoiceId);

        if ($invoice === null || in_array($invoice['status'], ['cancelled', 'credit_note', 'draft'], true)) {
            return;
        }

        $statement = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE cabinet_id = :cabinet_id
              AND invoice_id = :invoice_id
              AND status = 'received'
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'invoice_id' => $invoiceId,
        ]);

        $paid = min((float) $invoice['total_amount'], max(0, (float) $statement->fetchColumn()));
        $status = $this->invoiceStatusFromPayment($invoice, $paid);

        $update = $this->db->prepare("
            UPDATE invoices
            SET paid_amount = :paid_amount,
                status = :status
            WHERE cabinet_id = :cabinet_id
              AND id = :id
        ");
        $update->execute([
            'paid_amount' => $paid,
            'status' => $status,
            'cabinet_id' => $cabinetId,
            'id' => $invoiceId,
        ]);
    }

    private function invoiceStatusFromPayment(array $invoice, float $paid): string
    {
        $total = (float) $invoice['total_amount'];

        if ($paid >= $total && $total > 0) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        if (!empty($invoice['due_date']) && strtotime((string) $invoice['due_date']) < strtotime(date('Y-m-d'))) {
            return 'overdue';
        }

        return 'pending';
    }

    private function invoice(int $cabinetId, int $invoiceId): ?array
    {
        $statement = $this->db->prepare("
            SELECT *
            FROM invoices
            WHERE cabinet_id = :cabinet_id
              AND id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $invoiceId,
        ]);

        $invoice = $statement->fetch();

        return $invoice ?: null;
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

    private function invoices(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                i.id,
                i.patient_id,
                i.number,
                i.status,
                i.total_amount,
                i.paid_amount,
                GREATEST(i.total_amount - i.paid_amount, 0) AS remaining_amount,
                p.first_name,
                p.last_name
            FROM invoices i
            INNER JOIN patients p ON p.id = i.patient_id
            WHERE i.cabinet_id = :cabinet_id
              AND i.status NOT IN ('cancelled', 'credit_note')
            ORDER BY
                FIELD(i.status, 'overdue', 'partial', 'pending', 'paid', 'draft'),
                i.issue_date DESC,
                i.id DESC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function methods(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, name
            FROM payment_methods
            WHERE cabinet_id = :cabinet_id
              AND is_active = 1
            ORDER BY id ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function nextReference(int $cabinetId): string
    {
        $statement = $this->db->prepare("
            SELECT reference
            FROM payments
            WHERE cabinet_id = :cabinet_id
              AND reference LIKE 'PAY-%'
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $reference = (string) ($statement->fetchColumn() ?: '');
        preg_match('/(\d+)$/', $reference, $matches);
        $next = ((int) ($matches[1] ?? 0)) + 1;

        return 'PAY-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function filterSql(string $search, string $status): array
    {
        $where = '';
        $params = [];

        if ($search !== '') {
            $where .= "
                AND (
                    pay.reference LIKE :search
                    OR i.number LIKE :search
                    OR p.reference LIKE :search
                    OR p.first_name LIKE :search
                    OR p.last_name LIKE :search
                    OR CONCAT(p.last_name, ' ', p.first_name) LIKE :search
                    OR pm.name LIKE :search
                )
            ";
            $params['search'] = '%' . $search . '%';
        }

        if (in_array($status, ['received', 'pending', 'failed', 'refunded', 'cancelled'], true)) {
            $where .= ' AND pay.status = :status';
            $params['status'] = $status;
        }

        return ['where' => $where, 'params' => $params];
    }
}
