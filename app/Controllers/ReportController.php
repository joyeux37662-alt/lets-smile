<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Report;
use DateTimeImmutable;
use Throwable;

final class ReportController extends Controller
{
    public function index(): void
    {
        $cabinetId = (int) (Auth::cabinet()['id'] ?? 0);
        $filters = $this->filtersFromRequest();
        $period = $this->periodFromFilters($filters);

        try {
            $model = new Report();
            $summary = $model->summary($cabinetId, $period['start'], $period['end'], $period['previous_start'], $period['previous_end'], $filters);
            $dailySeries = $model->dailySeries($cabinetId, $period['start'], $period['end'], $filters);
            $revenueBreakdown = $model->revenueBreakdown($cabinetId, $period['start'], $period['end'], $filters);
            $appointmentBreakdown = $model->appointmentBreakdown($cabinetId, $period['start'], $period['end'], $filters);
            $practitionerActivity = $model->practitionerActivity($cabinetId, $period['start'], $period['end'], $filters);
            $topTreatments = $model->topTreatments($cabinetId, $period['start'], $period['end'], $filters);
            $performance = $model->performance($cabinetId, $period['start'], $period['end'], $period['previous_start'], $period['previous_end'], $filters);
            $options = $model->formOptions($cabinetId);
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Rapports. Vérifiez la base MySQL.');
            $summary = [
                'revenue' => ['value' => 0, 'previous' => 0, 'trend' => 0],
                'appointments' => ['value' => 0, 'previous' => 0, 'trend' => 0],
                'new_patients' => ['value' => 0, 'previous' => 0, 'trend' => 0],
                'occupancy' => ['value' => 0, 'previous' => 0, 'trend' => 0],
            ];
            $dailySeries = [];
            $revenueBreakdown = [];
            $appointmentBreakdown = [];
            $practitionerActivity = [];
            $topTreatments = [];
            $performance = [];
            $options = ['categories' => [], 'practitioners' => []];
        }

        $this->view('reports/index', [
            'title' => 'Rapports',
            'subtitle' => "Analysez l'activité et suivez les performances de votre cabinet.",
            'topbarSearchVisible' => false,
            'topbarSearchPlaceholder' => 'Rechercher un rapport...',
            'topbarSearchAction' => '/reports',
            'topbarActionHtml' => $this->topbarActions($filters),
            'summary' => $summary,
            'dailySeries' => $dailySeries,
            'revenueBreakdown' => $revenueBreakdown,
            'appointmentBreakdown' => $appointmentBreakdown,
            'practitionerActivity' => $practitionerActivity,
            'topTreatments' => $topTreatments,
            'performance' => $performance,
            'options' => $options,
            'filters' => $filters,
            'period' => $period,
        ]);
    }

    public function export(): void
    {
        $cabinetId = (int) (Auth::cabinet()['id'] ?? 0);
        $filters = $this->filtersFromRequest();
        $period = $this->periodFromFilters($filters);
        $model = new Report();

        $summary = $model->summary($cabinetId, $period['start'], $period['end'], $period['previous_start'], $period['previous_end'], $filters);
        $activity = $model->practitionerActivity($cabinetId, $period['start'], $period['end'], $filters);
        $topTreatments = $model->topTreatments($cabinetId, $period['start'], $period['end'], $filters);
        $model->recordExport($cabinetId, Auth::user()['id'] ?? null, $filters + ['start' => $period['start'], 'end' => $period['end']]);

        $filename = 'rapport-lets-smile-' . $period['start'] . '-' . $period['end'] . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        $handle = fopen('php://output', 'w');
        if ($handle === false) {
            exit;
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Let\'s Smile - Rapport', $period['label']], ';');
        fputcsv($handle, [], ';');
        fputcsv($handle, ['Indicateur', 'Valeur', 'Comparaison précédente', 'Variation'], ';');
        fputcsv($handle, ["Chiffre d'affaires", $summary['revenue']['value'], $summary['revenue']['previous'], $summary['revenue']['trend'] . '%'], ';');
        fputcsv($handle, ['Rendez-vous honorés', $summary['appointments']['value'], $summary['appointments']['previous'], $summary['appointments']['trend'] . '%'], ';');
        fputcsv($handle, ['Nouveaux patients', $summary['new_patients']['value'], $summary['new_patients']['previous'], $summary['new_patients']['trend'] . '%'], ';');
        fputcsv($handle, ["Taux d'occupation", $summary['occupancy']['value'] . '%', $summary['occupancy']['previous'] . '%', $summary['occupancy']['trend'] . '%'], ';');
        fputcsv($handle, [], ';');
        fputcsv($handle, ['Activité par praticien'], ';');
        fputcsv($handle, ['Praticien', 'Rendez-vous', 'CA généré'], ';');
        foreach ($activity as $row) {
            fputcsv($handle, [$row['full_name'], $row['appointments'], $row['revenue']], ';');
        }
        fputcsv($handle, [], ';');
        fputcsv($handle, ['Top traitements'], ';');
        fputcsv($handle, ['Traitement', 'Nombre', 'CA généré'], ';');
        foreach ($topTreatments as $row) {
            fputcsv($handle, [$row['description'], $row['quantity'], $row['revenue']], ';');
        }

        fclose($handle);
        exit;
    }

    private function filtersFromRequest(): array
    {
        $period = trim((string) ($_GET['period'] ?? 'this_month'));
        $period = in_array($period, ['this_month', 'last_month', 'last_30_days', 'this_year'], true) ? $period : 'this_month';

        return [
            'period' => $period,
            'category_id' => max(0, (int) ($_GET['category_id'] ?? 0)),
            'practitioner_id' => max(0, (int) ($_GET['practitioner_id'] ?? 0)),
            'source' => trim((string) ($_GET['source'] ?? 'all')),
        ];
    }

    private function periodFromFilters(array $filters): array
    {
        $today = new DateTimeImmutable('today');
        $period = $filters['period'] ?? 'this_month';

        if ($period === 'last_month') {
            $start = $today->modify('first day of last month');
            $end = $today->modify('last day of last month');
            $label = $this->dateLabel($start, $end);
        } elseif ($period === 'last_30_days') {
            $start = $today->modify('-29 days');
            $end = $today;
            $label = '30 derniers jours';
        } elseif ($period === 'this_year') {
            $start = new DateTimeImmutable($today->format('Y') . '-01-01');
            $end = new DateTimeImmutable($today->format('Y') . '-12-31');
            $label = 'Année ' . $today->format('Y');
        } else {
            $start = $today->modify('first day of this month');
            $end = $today->modify('last day of this month');
            $label = $this->dateLabel($start, $end);
        }

        $days = $start->diff($end)->days + 1;
        $previousEnd = $start->modify('-1 day');
        $previousStart = $previousEnd->modify('-' . ($days - 1) . ' days');

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'previous_start' => $previousStart->format('Y-m-d'),
            'previous_end' => $previousEnd->format('Y-m-d'),
            'label' => $label,
            'previous_label' => $this->dateLabel($previousStart, $previousEnd),
        ];
    }

    private function topbarActions(array $filters): string
    {
        $periods = [
            'this_month' => 'Ce mois',
            'last_month' => 'Mois précédent',
            'last_30_days' => '30 jours',
            'this_year' => 'Cette année',
        ];

        $options = '';
        foreach ($periods as $value => $label) {
            $selected = ($filters['period'] ?? 'this_month') === $value ? ' selected' : '';
            $options .= '<option value="' . e($value) . '"' . $selected . '>' . e($label) . '</option>';
        }

        $hidden = '<input type="hidden" name="category_id" value="' . e((string) ($filters['category_id'] ?? 0)) . '">';
        $hidden .= '<input type="hidden" name="practitioner_id" value="' . e((string) ($filters['practitioner_id'] ?? 0)) . '">';
        $hidden .= '<input type="hidden" name="source" value="' . e((string) ($filters['source'] ?? 'all')) . '">';
        $query = http_build_query(array_filter($filters, static fn ($value) => $value !== '' && $value !== 0 && $value !== 'all'));

        return '
            <form class="report-top-form" method="get" action="' . e(app_url('/reports')) . '">
                ' . $hidden . '
                <select name="period" onchange="this.form.submit()">' . $options . '</select>
            </form>
            <a class="button button-light button-compare" href="' . e(app_url('/reports?' . $query)) . '">Comparer</a>
            <a class="button button-primary button-export" href="' . e(app_url('/reports/export?' . $query)) . '"><span aria-hidden="true"></span> Exporter le rapport</a>
        ';
    }

    private function dateLabel(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        $months = [
            '01' => 'janv.',
            '02' => 'févr.',
            '03' => 'mars',
            '04' => 'avr.',
            '05' => 'mai',
            '06' => 'juin',
            '07' => 'juil.',
            '08' => 'août',
            '09' => 'sept.',
            '10' => 'oct.',
            '11' => 'nov.',
            '12' => 'déc.',
        ];

        if ($start->format('Y-m') === $end->format('Y-m')) {
            return $start->format('d') . ' - ' . $end->format('d') . ' ' . $months[$end->format('m')] . ' ' . $end->format('Y');
        }

        return $start->format('d') . ' ' . $months[$start->format('m')] . ' - ' . $end->format('d') . ' ' . $months[$end->format('m')] . ' ' . $end->format('Y');
    }
}
