<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;

final class Report
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function summary(int $cabinetId, string $start, string $end, string $previousStart, string $previousEnd, array $filters): array
    {
        $current = $this->metrics($cabinetId, $start, $end, $filters);
        $previous = $this->metrics($cabinetId, $previousStart, $previousEnd, $filters);

        return [
            'revenue' => $this->metricWithTrend($current['revenue'], $previous['revenue']),
            'appointments' => $this->metricWithTrend($current['appointments'], $previous['appointments']),
            'new_patients' => $this->metricWithTrend($current['new_patients'], $previous['new_patients']),
            'occupancy' => $this->metricWithTrend($current['occupancy'], $previous['occupancy']),
        ];
    }

    public function dailySeries(int $cabinetId, string $start, string $end, array $filters): array
    {
        $series = [];

        foreach ($this->dateRange($start, $end) as $date) {
            $series[$date] = [
                'date' => $date,
                'revenue' => 0.0,
                'appointments' => 0,
                'new_patients' => 0,
            ];
        }

        foreach ($this->revenueByDay($cabinetId, $start, $end, $filters) as $row) {
            if (isset($series[$row['day']])) {
                $series[$row['day']]['revenue'] = (float) $row['total'];
            }
        }

        foreach ($this->appointmentsByDay($cabinetId, $start, $end, $filters) as $row) {
            if (isset($series[$row['day']])) {
                $series[$row['day']]['appointments'] = (int) $row['total'];
            }
        }

        foreach ($this->patientsByDay($cabinetId, $start, $end) as $row) {
            if (isset($series[$row['day']])) {
                $series[$row['day']]['new_patients'] = (int) $row['total'];
            }
        }

        return array_values($series);
    }

    public function revenueBreakdown(int $cabinetId, string $start, string $end, array $filters): array
    {
        $where = $this->invoiceFilterSql($filters);
        $statement = $this->db->prepare("
            SELECT
                COALESCE(tc.name, 'Autres') AS label,
                COALESCE(tc.color, '#94A3B8') AS color,
                COALESCE(SUM(i.total_amount), 0) AS total
            FROM invoices i
            LEFT JOIN treatment_plans tp ON tp.id = i.treatment_plan_id
            LEFT JOIN treatment_categories tc ON tc.id = tp.category_id
            WHERE i.cabinet_id = :cabinet_id
              AND i.issue_date BETWEEN :start_date AND :end_date
              AND i.status NOT IN ('draft', 'cancelled')
              {$where['sql']}
            GROUP BY COALESCE(tc.name, 'Autres'), COALESCE(tc.color, '#94A3B8')
            HAVING total != 0
            ORDER BY total DESC
        ");
        $statement->execute($where['params'] + [
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $rows = $statement->fetchAll();
        $total = (float) array_sum(array_map(static fn (array $row): float => max(0, (float) $row['total']), $rows));

        return array_map(static function (array $row) use ($total): array {
            $amount = max(0, (float) $row['total']);

            return [
                'label' => (string) $row['label'],
                'color' => (string) $row['color'],
                'total' => $amount,
                'percent' => $total > 0 ? round(($amount / $total) * 100) : 0,
            ];
        }, $rows);
    }

    public function appointmentBreakdown(int $cabinetId, string $start, string $end, array $filters): array
    {
        $where = $this->appointmentFilterSql($filters);
        $statement = $this->db->prepare("
            SELECT status, COUNT(*) AS total
            FROM appointments
            WHERE cabinet_id = :cabinet_id
              AND DATE(starts_at) BETWEEN :start_date AND :end_date
              {$where['sql']}
            GROUP BY status
        ");
        $statement->execute($where['params'] + [
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $counts = [
            'completed' => 0,
            'confirmed' => 0,
            'pending' => 0,
            'cancelled' => 0,
            'missed' => 0,
        ];

        foreach ($statement->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $rows = [
            ['label' => 'Honorés', 'key' => 'honored', 'color' => '#10B981', 'total' => $counts['completed'] + $counts['confirmed']],
            ['label' => 'Annulés', 'key' => 'cancelled', 'color' => '#F59E0B', 'total' => $counts['cancelled']],
            ['label' => 'À confirmer', 'key' => 'pending', 'color' => '#2563EB', 'total' => $counts['pending']],
            ['label' => 'Non honorés', 'key' => 'missed', 'color' => '#EC4899', 'total' => $counts['missed']],
        ];
        $total = array_sum(array_column($rows, 'total'));

        foreach ($rows as &$row) {
            $row['percent'] = $total > 0 ? round(((int) $row['total'] / $total) * 100) : 0;
        }
        unset($row);

        return $rows;
    }

    public function practitionerActivity(int $cabinetId, string $start, string $end, array $filters): array
    {
        $users = $this->practitioners($cabinetId);
        $activity = [];

        foreach ($users as $user) {
            $activity[(int) $user['id']] = [
                'id' => (int) $user['id'],
                'full_name' => (string) $user['full_name'],
                'appointments' => 0,
                'revenue' => 0.0,
            ];
        }

        $appointmentWhere = $this->appointmentFilterSql($filters, 'a');
        $appointments = $this->db->prepare("
            SELECT a.practitioner_id, COUNT(*) AS total
            FROM appointments a
            WHERE a.cabinet_id = :cabinet_id
              AND DATE(a.starts_at) BETWEEN :start_date AND :end_date
              AND a.status NOT IN ('cancelled', 'missed')
              {$appointmentWhere['sql']}
            GROUP BY a.practitioner_id
        ");
        $appointments->execute($appointmentWhere['params'] + [
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        foreach ($appointments->fetchAll() as $row) {
            $id = (int) $row['practitioner_id'];
            if (isset($activity[$id])) {
                $activity[$id]['appointments'] = (int) $row['total'];
            }
        }

        $invoiceWhere = $this->invoiceFilterSql($filters, 'i', 'tp');
        $revenues = $this->db->prepare("
            SELECT tp.practitioner_id, COALESCE(SUM(i.total_amount), 0) AS total
            FROM invoices i
            INNER JOIN treatment_plans tp ON tp.id = i.treatment_plan_id
            WHERE i.cabinet_id = :cabinet_id
              AND i.issue_date BETWEEN :start_date AND :end_date
              AND i.status NOT IN ('draft', 'cancelled')
              {$invoiceWhere['sql']}
            GROUP BY tp.practitioner_id
        ");
        $revenues->execute($invoiceWhere['params'] + [
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        foreach ($revenues->fetchAll() as $row) {
            $id = (int) $row['practitioner_id'];
            if (isset($activity[$id])) {
                $activity[$id]['revenue'] = (float) $row['total'];
            }
        }

        $rows = array_values($activity);
        usort($rows, static fn (array $a, array $b): int => ($b['revenue'] <=> $a['revenue']) ?: ($b['appointments'] <=> $a['appointments']));

        return array_slice($rows, 0, 4);
    }

    public function topTreatments(int $cabinetId, string $start, string $end, array $filters): array
    {
        $where = $this->invoiceFilterSql($filters, 'i', 'tp');
        $statement = $this->db->prepare("
            SELECT
                ii.description,
                COALESCE(SUM(ii.quantity), 0) AS quantity,
                COALESCE(SUM(ii.total), 0) AS revenue
            FROM invoice_items ii
            INNER JOIN invoices i ON i.id = ii.invoice_id
            LEFT JOIN treatment_plans tp ON tp.id = i.treatment_plan_id
            WHERE i.cabinet_id = :cabinet_id
              AND i.issue_date BETWEEN :start_date AND :end_date
              AND i.status NOT IN ('draft', 'cancelled', 'credit_note')
              {$where['sql']}
            GROUP BY ii.description
            HAVING revenue > 0
            ORDER BY revenue DESC
            LIMIT 5
        ");
        $statement->execute($where['params'] + [
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return $statement->fetchAll();
    }

    public function performance(int $cabinetId, string $start, string $end, string $previousStart, string $previousEnd, array $filters): array
    {
        $current = $this->metrics($cabinetId, $start, $end, $filters);
        $previous = $this->metrics($cabinetId, $previousStart, $previousEnd, $filters);

        return [
            ['label' => "Chiffre d'affaires", 'current' => $current['revenue'], 'previous' => $previous['revenue'], 'format' => 'money'],
            ['label' => 'Rendez-vous', 'current' => $current['appointments'], 'previous' => $previous['appointments'], 'format' => 'number'],
            ['label' => 'Nouveaux patients', 'current' => $current['new_patients'], 'previous' => $previous['new_patients'], 'format' => 'number'],
            ["label" => "Taux d'occupation", 'current' => $current['occupancy'], 'previous' => $previous['occupancy'], 'format' => 'percent'],
        ];
    }

    public function formOptions(int $cabinetId): array
    {
        return [
            'categories' => $this->categories($cabinetId),
            'practitioners' => $this->practitioners($cabinetId),
        ];
    }

    public function recordExport(int $cabinetId, ?int $userId, array $filters): void
    {
        $statement = $this->db->prepare("
            INSERT INTO reports_exports (cabinet_id, user_id, report_type, file_path, filters_json)
            VALUES (:cabinet_id, :user_id, 'overview', NULL, :filters_json)
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'user_id' => $userId,
            'filters_json' => json_encode($filters, JSON_THROW_ON_ERROR),
        ]);
    }

    private function metrics(int $cabinetId, string $start, string $end, array $filters): array
    {
        return [
            'revenue' => $this->revenue($cabinetId, $start, $end, $filters),
            'appointments' => $this->appointmentsCount($cabinetId, $start, $end, $filters),
            'new_patients' => $this->newPatients($cabinetId, $start, $end),
            'occupancy' => $this->occupancy($cabinetId, $start, $end, $filters),
        ];
    }

    private function revenue(int $cabinetId, string $start, string $end, array $filters): float
    {
        $where = $this->invoiceFilterSql($filters);
        $statement = $this->db->prepare("
            SELECT COALESCE(SUM(i.total_amount), 0)
            FROM invoices i
            LEFT JOIN treatment_plans tp ON tp.id = i.treatment_plan_id
            WHERE i.cabinet_id = :cabinet_id
              AND i.issue_date BETWEEN :start_date AND :end_date
              AND i.status NOT IN ('draft', 'cancelled')
              {$where['sql']}
        ");
        $statement->execute($where['params'] + [
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return (float) $statement->fetchColumn();
    }

    private function appointmentsCount(int $cabinetId, string $start, string $end, array $filters): int
    {
        $where = $this->appointmentFilterSql($filters);
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointments
            WHERE cabinet_id = :cabinet_id
              AND DATE(starts_at) BETWEEN :start_date AND :end_date
              AND status IN ('completed', 'confirmed')
              {$where['sql']}
        ");
        $statement->execute($where['params'] + [
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return (int) $statement->fetchColumn();
    }

    private function newPatients(int $cabinetId, string $start, string $end): int
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM patients
            WHERE cabinet_id = :cabinet_id
              AND DATE(created_at) BETWEEN :start_date AND :end_date
              AND status != 'archived'
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return (int) $statement->fetchColumn();
    }

    private function occupancy(int $cabinetId, string $start, string $end, array $filters): float
    {
        $where = $this->appointmentFilterSql($filters);
        $statement = $this->db->prepare("
            SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at)), 0)
            FROM appointments
            WHERE cabinet_id = :cabinet_id
              AND DATE(starts_at) BETWEEN :start_date AND :end_date
              AND status NOT IN ('cancelled', 'missed')
              {$where['sql']}
        ");
        $statement->execute($where['params'] + [
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);
        $minutes = (float) $statement->fetchColumn();

        $rooms = max(1, $this->activeRooms($cabinetId));
        $capacity = max(1, $this->businessDays($start, $end) * $rooms * 10 * 60);

        return round(min(100, ($minutes / $capacity) * 100), 1);
    }

    private function revenueByDay(int $cabinetId, string $start, string $end, array $filters): array
    {
        $where = $this->invoiceFilterSql($filters);
        $statement = $this->db->prepare("
            SELECT i.issue_date AS day, COALESCE(SUM(i.total_amount), 0) AS total
            FROM invoices i
            LEFT JOIN treatment_plans tp ON tp.id = i.treatment_plan_id
            WHERE i.cabinet_id = :cabinet_id
              AND i.issue_date BETWEEN :start_date AND :end_date
              AND i.status NOT IN ('draft', 'cancelled')
              {$where['sql']}
            GROUP BY i.issue_date
            ORDER BY i.issue_date ASC
        ");
        $statement->execute($where['params'] + [
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return $statement->fetchAll();
    }

    private function appointmentsByDay(int $cabinetId, string $start, string $end, array $filters): array
    {
        $where = $this->appointmentFilterSql($filters);
        $statement = $this->db->prepare("
            SELECT DATE(starts_at) AS day, COUNT(*) AS total
            FROM appointments
            WHERE cabinet_id = :cabinet_id
              AND DATE(starts_at) BETWEEN :start_date AND :end_date
              AND status NOT IN ('cancelled', 'missed')
              {$where['sql']}
            GROUP BY DATE(starts_at)
            ORDER BY DATE(starts_at) ASC
        ");
        $statement->execute($where['params'] + [
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return $statement->fetchAll();
    }

    private function patientsByDay(int $cabinetId, string $start, string $end): array
    {
        $statement = $this->db->prepare("
            SELECT DATE(created_at) AS day, COUNT(*) AS total
            FROM patients
            WHERE cabinet_id = :cabinet_id
              AND DATE(created_at) BETWEEN :start_date AND :end_date
              AND status != 'archived'
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return $statement->fetchAll();
    }

    private function categories(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, name, color
            FROM treatment_categories
            WHERE cabinet_id = :cabinet_id
            ORDER BY id ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function practitioners(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT u.id, u.full_name
            FROM users u
            INNER JOIN user_cabinets uc ON uc.user_id = u.id
            WHERE uc.cabinet_id = :cabinet_id
              AND u.status = 'active'
            ORDER BY u.full_name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function activeRooms(int $cabinetId): int
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM rooms
            WHERE cabinet_id = :cabinet_id
              AND is_active = 1
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return (int) $statement->fetchColumn();
    }

    private function metricWithTrend(float|int $current, float|int $previous): array
    {
        $trend = 0.0;

        if ((float) $previous !== 0.0) {
            $trend = (((float) $current - (float) $previous) / abs((float) $previous)) * 100;
        } elseif ((float) $current > 0.0) {
            $trend = 100.0;
        }

        return [
            'value' => $current,
            'previous' => $previous,
            'trend' => round($trend, 1),
        ];
    }

    private function invoiceFilterSql(array $filters, string $invoiceAlias = 'i', string $treatmentAlias = 'tp'): array
    {
        $sql = '';
        $params = [];

        if ((int) ($filters['category_id'] ?? 0) > 0) {
            $sql .= " AND {$treatmentAlias}.category_id = :invoice_category_id";
            $params['invoice_category_id'] = (int) $filters['category_id'];
        }

        if ((int) ($filters['practitioner_id'] ?? 0) > 0) {
            $sql .= " AND {$treatmentAlias}.practitioner_id = :invoice_practitioner_id";
            $params['invoice_practitioner_id'] = (int) $filters['practitioner_id'];
        }

        return ['sql' => $sql, 'params' => $params];
    }

    private function appointmentFilterSql(array $filters, string $alias = ''): array
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $sql = '';
        $params = [];

        if ((int) ($filters['practitioner_id'] ?? 0) > 0) {
            $sql .= " AND {$prefix}practitioner_id = :appointment_practitioner_id";
            $params['appointment_practitioner_id'] = (int) $filters['practitioner_id'];
        }

        return ['sql' => $sql, 'params' => $params];
    }

    private function businessDays(string $start, string $end): int
    {
        $days = 0;
        $period = new DatePeriod(
            new DateTimeImmutable($start),
            new DateInterval('P1D'),
            (new DateTimeImmutable($end))->modify('+1 day')
        );

        foreach ($period as $day) {
            if ((int) $day->format('N') <= 5) {
                $days++;
            }
        }

        return max(1, $days);
    }

    private function dateRange(string $start, string $end): array
    {
        $dates = [];
        $period = new DatePeriod(
            new DateTimeImmutable($start),
            new DateInterval('P1D'),
            (new DateTimeImmutable($end))->modify('+1 day')
        );

        foreach ($period as $day) {
            $dates[] = $day->format('Y-m-d');
        }

        return $dates;
    }
}
