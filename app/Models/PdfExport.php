<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class PdfExport
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function cabinet(int $cabinetId): array
    {
        $statement = $this->db->prepare("SELECT * FROM cabinets WHERE id = :id LIMIT 1");
        $statement->execute(['id' => $cabinetId]);
        $cabinet = $statement->fetch() ?: [];

        $settings = $this->db->prepare("
            SELECT setting_key, setting_value
            FROM settings
            WHERE cabinet_id = :cabinet_id
              AND setting_key IN ('website', 'tax_identifier')
        ");
        $settings->execute(['cabinet_id' => $cabinetId]);

        foreach ($settings->fetchAll() as $row) {
            $cabinet[(string) $row['setting_key']] = $row['setting_value'];
        }

        return $cabinet;
    }

    public function invoice(int $cabinetId, int $invoiceId): ?array
    {
        return (new Invoice())->find($cabinetId, $invoiceId);
    }

    public function payment(int $cabinetId, int $paymentId): ?array
    {
        return (new Payment())->find($cabinetId, $paymentId);
    }

    public function quote(int $cabinetId, int $quoteId): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                q.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.phone,
                p.email,
                p.birth_date,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                tp.reference AS treatment_reference,
                tp.title AS treatment_title
            FROM quotes q
            INNER JOIN patients p ON p.id = q.patient_id
            LEFT JOIN treatment_plans tp ON tp.id = q.treatment_plan_id
            WHERE q.cabinet_id = :cabinet_id
              AND q.id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $quoteId,
        ]);
        $quote = $statement->fetch();

        if (!$quote) {
            return null;
        }

        $quote['items'] = $this->quoteItems($quoteId);

        return $quote;
    }

    public function quoteFromTreatment(int $cabinetId, int $treatmentId): ?array
    {
        $storedQuote = $this->firstQuoteForTreatment($cabinetId, $treatmentId);

        if ($storedQuote !== null) {
            return $storedQuote;
        }

        $statement = $this->db->prepare("
            SELECT
                tp.*,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.phone,
                p.email,
                p.birth_date,
                TIMESTAMPDIFF(YEAR, p.birth_date, CURRENT_DATE()) AS patient_age,
                tp.reference AS treatment_reference,
                tp.title AS treatment_title
            FROM treatment_plans tp
            INNER JOIN patients p ON p.id = tp.patient_id
            WHERE tp.cabinet_id = :cabinet_id
              AND tp.id = :id
              AND tp.status != 'cancelled'
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $treatmentId,
        ]);
        $treatment = $statement->fetch();

        if (!$treatment) {
            return null;
        }

        $steps = $this->treatmentSteps($treatmentId);
        $items = [];

        foreach ($steps as $step) {
            if ((float) $step['amount'] <= 0) {
                continue;
            }

            $items[] = [
                'description' => $step['title'],
                'quantity' => 1,
                'unit_price' => (float) $step['amount'],
                'total' => (float) $step['amount'],
            ];
        }

        if ($items === []) {
            $items[] = [
                'description' => $treatment['title'],
                'quantity' => 1,
                'unit_price' => (float) $treatment['total_amount'],
                'total' => (float) $treatment['total_amount'],
            ];
        }

        return $treatment + [
            'id' => 0,
            'number' => 'D-' . preg_replace('/^T-/', '', (string) $treatment['reference']),
            'issue_date' => date('Y-m-d'),
            'valid_until' => date('Y-m-d', strtotime('+30 days')),
            'status' => 'draft',
            'total_amount' => array_sum(array_column($items, 'total')),
            'items' => $items,
        ];
    }

    public function patientRecord(int $cabinetId, int $patientId): ?array
    {
        $patient = (new Patient())->find($cabinetId, $patientId);

        if (!$patient) {
            return null;
        }

        return [
            'patient' => $patient,
            'appointments' => $this->patientAppointments($cabinetId, $patientId),
            'treatments' => $this->patientTreatments($cabinetId, $patientId),
            'invoices' => $this->patientInvoices($cabinetId, $patientId),
            'payments' => $this->patientPayments($cabinetId, $patientId),
            'documents' => $this->patientDocuments($cabinetId, $patientId),
        ];
    }

    private function firstQuoteForTreatment(int $cabinetId, int $treatmentId): ?array
    {
        $statement = $this->db->prepare("
            SELECT id
            FROM quotes
            WHERE cabinet_id = :cabinet_id
              AND treatment_plan_id = :treatment_id
              AND status != 'cancelled'
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'treatment_id' => $treatmentId,
        ]);
        $quoteId = $statement->fetchColumn();

        return $quoteId ? $this->quote($cabinetId, (int) $quoteId) : null;
    }

    private function quoteItems(int $quoteId): array
    {
        $statement = $this->db->prepare("
            SELECT description, quantity, unit_price, total
            FROM quote_items
            WHERE quote_id = :quote_id
            ORDER BY id ASC
        ");
        $statement->execute(['quote_id' => $quoteId]);

        return $statement->fetchAll();
    }

    private function treatmentSteps(int $treatmentId): array
    {
        $statement = $this->db->prepare("
            SELECT title, amount
            FROM treatment_steps
            WHERE treatment_plan_id = :treatment_id
            ORDER BY sort_order ASC, id ASC
        ");
        $statement->execute(['treatment_id' => $treatmentId]);

        return $statement->fetchAll();
    }

    private function patientAppointments(int $cabinetId, int $patientId): array
    {
        $statement = $this->db->prepare("
            SELECT
                a.starts_at,
                at.name AS type_name,
                u.full_name AS practitioner_name,
                a.status
            FROM appointments a
            LEFT JOIN appointment_types at ON at.id = a.appointment_type_id
            LEFT JOIN users u ON u.id = a.practitioner_id
            WHERE a.cabinet_id = :cabinet_id
              AND a.patient_id = :patient_id
            ORDER BY a.starts_at DESC
            LIMIT 8
        ");
        $statement->execute(['cabinet_id' => $cabinetId, 'patient_id' => $patientId]);

        return array_map(static fn (array $row): array => [
            format_date($row['starts_at']) . ' ' . format_time($row['starts_at']),
            (string) ($row['type_name'] ?: 'Rendez-vous'),
            (string) ($row['practitioner_name'] ?: '-'),
            self::appointmentStatus((string) $row['status']),
        ], $statement->fetchAll());
    }

    private function patientTreatments(int $cabinetId, int $patientId): array
    {
        $statement = $this->db->prepare("
            SELECT reference, title, progress, total_amount
            FROM treatment_plans
            WHERE cabinet_id = :cabinet_id
              AND patient_id = :patient_id
              AND status != 'cancelled'
            ORDER BY updated_at DESC, id DESC
            LIMIT 8
        ");
        $statement->execute(['cabinet_id' => $cabinetId, 'patient_id' => $patientId]);

        return array_map(static fn (array $row): array => [
            (string) $row['reference'],
            (string) $row['title'],
            (int) $row['progress'] . '%',
            format_money($row['total_amount']),
        ], $statement->fetchAll());
    }

    private function patientInvoices(int $cabinetId, int $patientId): array
    {
        $statement = $this->db->prepare("
            SELECT number, issue_date, status, total_amount
            FROM invoices
            WHERE cabinet_id = :cabinet_id
              AND patient_id = :patient_id
            ORDER BY issue_date DESC, id DESC
            LIMIT 8
        ");
        $statement->execute(['cabinet_id' => $cabinetId, 'patient_id' => $patientId]);

        return array_map(static fn (array $row): array => [
            (string) $row['number'],
            format_date($row['issue_date']),
            self::invoiceStatus((string) $row['status']),
            format_money($row['total_amount']),
        ], $statement->fetchAll());
    }

    private function patientPayments(int $cabinetId, int $patientId): array
    {
        $statement = $this->db->prepare("
            SELECT pay.reference, pay.paid_at, pm.name AS method_name, pay.amount
            FROM payments pay
            INNER JOIN payment_methods pm ON pm.id = pay.method_id
            WHERE pay.cabinet_id = :cabinet_id
              AND pay.patient_id = :patient_id
            ORDER BY pay.paid_at DESC, pay.id DESC
            LIMIT 8
        ");
        $statement->execute(['cabinet_id' => $cabinetId, 'patient_id' => $patientId]);

        return array_map(static fn (array $row): array => [
            (string) $row['reference'],
            format_date($row['paid_at']),
            (string) $row['method_name'],
            format_money($row['amount']),
        ], $statement->fetchAll());
    }

    private function patientDocuments(int $cabinetId, int $patientId): array
    {
        $statement = $this->db->prepare("
            SELECT d.title, dc.name AS category_name, d.created_at
            FROM documents d
            LEFT JOIN document_categories dc ON dc.id = d.category_id
            WHERE d.cabinet_id = :cabinet_id
              AND d.patient_id = :patient_id
            ORDER BY d.created_at DESC, d.id DESC
            LIMIT 8
        ");
        $statement->execute(['cabinet_id' => $cabinetId, 'patient_id' => $patientId]);

        return array_map(static fn (array $row): array => [
            (string) $row['title'],
            (string) ($row['category_name'] ?: '-'),
            format_date($row['created_at']),
        ], $statement->fetchAll());
    }

    private static function appointmentStatus(string $status): string
    {
        return [
            'pending' => 'En attente',
            'confirmed' => 'Confirme',
            'completed' => 'Honore',
            'cancelled' => 'Annule',
            'no_show' => 'Non honore',
        ][$status] ?? $status;
    }

    private static function invoiceStatus(string $status): string
    {
        return [
            'draft' => 'Brouillon',
            'pending' => 'En attente',
            'paid' => 'Payee',
            'partial' => 'Partielle',
            'overdue' => 'Impayee',
            'cancelled' => 'Annulee',
            'credit_note' => 'Avoir',
        ][$status] ?? $status;
    }
}
