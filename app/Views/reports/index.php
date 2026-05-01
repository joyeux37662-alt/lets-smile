<?php
$series = $dailySeries ?? [];
$filters = $filters ?? [];
$period = $period ?? ['label' => '', 'previous_label' => ''];
$maxValue = static function (array $rows, string $key): float {
    $values = array_map(static fn (array $row): float => (float) ($row[$key] ?? 0), $rows);

    return max(1, $values === [] ? 1 : max($values));
};
$linePoints = static function (array $rows, string $key, int $width = 680, int $height = 240): string {
    if ($rows === []) {
        return '';
    }

    $max = max(1, max(array_map(static fn (array $row): float => (float) ($row[$key] ?? 0), $rows)));
    $count = count($rows);
    $points = [];

    foreach ($rows as $index => $row) {
        $x = $count === 1 ? $width / 2 : ($index / ($count - 1)) * $width;
        $y = $height - (((float) ($row[$key] ?? 0) / $max) * ($height - 18)) - 9;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }

    return implode(' ', $points);
};
$sparkline = static function (array $rows, string $key) use ($linePoints): string {
    $points = $linePoints($rows, $key, 118, 38);

    return $points !== '' ? $points : '0,30 118,30';
};
$donutStyle = static function (array $rows): string {
    if ($rows === []) {
        return 'background: conic-gradient(#e5eaf2 0 100%);';
    }

    $cursor = 0;
    $parts = [];

    foreach ($rows as $row) {
        $percent = max(0, (float) ($row['percent'] ?? 0));
        $next = min(100, $cursor + $percent);
        $parts[] = ($row['color'] ?? '#94A3B8') . ' ' . $cursor . '% ' . $next . '%';
        $cursor = $next;
    }

    if ($cursor < 100) {
        $parts[] = '#e5eaf2 ' . $cursor . '% 100%';
    }

    return 'background: conic-gradient(' . implode(', ', $parts) . ');';
};
$trendClass = static fn (float|int $trend): string => (float) $trend >= 0 ? 'positive' : 'negative';
$trendText = static fn (float|int $trend): string => ((float) $trend >= 0 ? '+' : '') . number_format((float) $trend, 1, ',', ' ') . '%';
$axisLabels = static function (array $rows): array {
    $labels = [];
    $count = count($rows);

    foreach ($rows as $index => $row) {
        if ($index === 0 || $index === $count - 1 || $index % 5 === 0) {
            $labels[] = format_date($row['date']);
        }
    }

    return array_slice($labels, 0, 7);
};
$formatPerformance = static function (array $row, string $field): string {
    $value = (float) ($row[$field] ?? 0);

    return match ($row['format'] ?? 'number') {
        'money' => format_money($value),
        'percent' => number_format($value, 1, ',', ' ') . '%',
        default => number_format($value, 0, ',', ' '),
    };
};
$nameInitials = static function (string $name): string {
    $parts = array_values(array_filter(explode(' ', str_replace('.', '', $name))));
    $first = strtoupper(substr($parts[0] ?? 'R', 0, 1));
    $second = strtoupper(substr($parts[1] ?? ($parts[0] ?? 'P'), 0, 1));

    return $first . $second;
};
$revenuePoints = $linePoints($series, 'revenue', 760, 260);
$revenueFill = $revenuePoints !== '' ? '0,260 ' . $revenuePoints . ' 760,260' : '';
$indicatorRevenue = $linePoints($series, 'revenue', 520, 170);
$indicatorAppointments = $linePoints($series, 'appointments', 520, 170);
$indicatorPatients = $linePoints($series, 'new_patients', 520, 170);
$maxPerformance = max(1, max(array_map(static fn (array $row): float => max((float) ($row['current'] ?? 0), (float) ($row['previous'] ?? 0)), $performance ?: [['current' => 0, 'previous' => 0]])));
$queryBase = http_build_query(array_filter($filters, static fn ($value) => $value !== '' && $value !== 0 && $value !== 'all'));
?>

<section class="report-stat-grid" aria-label="Résumé des rapports">
    <article class="report-stat-card report-stat-blue">
        <span class="report-stat-icon report-icon-invoice" aria-hidden="true"></span>
        <div>
            <p>Chiffre d'affaires</p>
            <strong><?= e(format_money($summary['revenue']['value'] ?? 0)) ?></strong>
            <small class="<?= e($trendClass($summary['revenue']['trend'] ?? 0)) ?>"><?= e($trendText($summary['revenue']['trend'] ?? 0)) ?> par rapport à <?= e($period['previous_label']) ?></small>
        </div>
        <svg viewBox="0 0 118 38" aria-hidden="true"><polyline points="<?= e($sparkline($series, 'revenue')) ?>"></polyline></svg>
    </article>
    <article class="report-stat-card report-stat-green">
        <span class="report-stat-icon report-icon-calendar" aria-hidden="true"></span>
        <div>
            <p>Rendez-vous honorés</p>
            <strong><?= e(number_format((float) ($summary['appointments']['value'] ?? 0), 0, ',', ' ')) ?></strong>
            <small class="<?= e($trendClass($summary['appointments']['trend'] ?? 0)) ?>"><?= e($trendText($summary['appointments']['trend'] ?? 0)) ?> par rapport à <?= e($period['previous_label']) ?></small>
        </div>
        <svg viewBox="0 0 118 38" aria-hidden="true"><polyline points="<?= e($sparkline($series, 'appointments')) ?>"></polyline></svg>
    </article>
    <article class="report-stat-card report-stat-orange">
        <span class="report-stat-icon report-icon-clock" aria-hidden="true"></span>
        <div>
            <p>Nouveaux patients</p>
            <strong><?= e(number_format((float) ($summary['new_patients']['value'] ?? 0), 0, ',', ' ')) ?></strong>
            <small class="<?= e($trendClass($summary['new_patients']['trend'] ?? 0)) ?>"><?= e($trendText($summary['new_patients']['trend'] ?? 0)) ?> par rapport à <?= e($period['previous_label']) ?></small>
        </div>
        <svg viewBox="0 0 118 38" aria-hidden="true"><polyline points="<?= e($sparkline($series, 'new_patients')) ?>"></polyline></svg>
    </article>
    <article class="report-stat-card report-stat-purple">
        <span class="report-stat-icon report-icon-rate" aria-hidden="true"></span>
        <div>
            <p>Taux d'occupation</p>
            <strong><?= e(number_format((float) ($summary['occupancy']['value'] ?? 0), 1, ',', ' ')) ?>%</strong>
            <small class="<?= e($trendClass($summary['occupancy']['trend'] ?? 0)) ?>"><?= e($trendText($summary['occupancy']['trend'] ?? 0)) ?> par rapport à <?= e($period['previous_label']) ?></small>
        </div>
        <svg viewBox="0 0 118 38" aria-hidden="true"><polyline points="<?= e($sparkline($series, 'appointments')) ?>"></polyline></svg>
    </article>
</section>

<section class="reports-shell">
    <aside class="report-sidebar">
        <section class="report-filter-card">
            <h2>Filtres des rapports</h2>
            <form method="get" action="<?= e(app_url('/reports')) ?>">
                <label>
                    <span>Période</span>
                    <select name="period">
                        <option value="this_month" <?= ($filters['period'] ?? '') === 'this_month' ? 'selected' : '' ?>>Ce mois</option>
                        <option value="last_month" <?= ($filters['period'] ?? '') === 'last_month' ? 'selected' : '' ?>>Mois précédent</option>
                        <option value="last_30_days" <?= ($filters['period'] ?? '') === 'last_30_days' ? 'selected' : '' ?>>30 derniers jours</option>
                        <option value="this_year" <?= ($filters['period'] ?? '') === 'this_year' ? 'selected' : '' ?>>Cette année</option>
                    </select>
                </label>
                <label>
                    <span>Catégorie de rapport</span>
                    <select name="category_id">
                        <option value="0">Toutes les catégories</option>
                        <?php foreach (($options['categories'] ?? []) as $category): ?>
                            <option value="<?= e($category['id']) ?>" <?= (int) ($filters['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Praticien</span>
                    <select name="practitioner_id">
                        <option value="0">Tous les praticiens</option>
                        <?php foreach (($options['practitioners'] ?? []) as $practitioner): ?>
                            <option value="<?= e($practitioner['id']) ?>" <?= (int) ($filters['practitioner_id'] ?? 0) === (int) $practitioner['id'] ? 'selected' : '' ?>>
                                <?= e($practitioner['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Source</span>
                    <select name="source">
                        <option value="all">Toutes les sources</option>
                        <option value="billing" <?= ($filters['source'] ?? '') === 'billing' ? 'selected' : '' ?>>Facturation</option>
                        <option value="appointments" <?= ($filters['source'] ?? '') === 'appointments' ? 'selected' : '' ?>>Rendez-vous</option>
                    </select>
                </label>
                <button class="button button-primary button-full" type="submit">Appliquer les filtres</button>
                <a class="button button-light button-full" href="<?= e(app_url('/reports')) ?>">Réinitialiser les filtres</a>
            </form>
        </section>

        <section class="report-categories-card">
            <h2>Catégories de rapports</h2>
            <nav aria-label="Catégories de rapports">
                <a class="active" href="<?= e(app_url('/reports?' . $queryBase)) ?>"><span></span> Vue d'ensemble</a>
                <a href="#"><span></span> Activité</a>
                <a href="#"><span></span> Financier</a>
                <a href="#"><span></span> Patients</a>
                <a href="#"><span></span> Rendez-vous</a>
                <a href="#"><span></span> Traitements</a>
                <a href="#"><span></span> Facturation</a>
                <a href="#"><span></span> Stock</a>
            </nav>
        </section>
    </aside>

    <section class="report-main-grid">
        <article class="report-panel report-panel-wide">
            <header class="report-panel-header">
                <h2>Évolution du chiffre d'affaires</h2>
                <span><?= e($period['label']) ?></span>
            </header>
            <div class="report-line-chart">
                <svg viewBox="0 0 760 260" role="img" aria-label="Évolution du chiffre d'affaires">
                    <g class="grid">
                        <line x1="0" y1="52" x2="760" y2="52"></line>
                        <line x1="0" y1="104" x2="760" y2="104"></line>
                        <line x1="0" y1="156" x2="760" y2="156"></line>
                        <line x1="0" y1="208" x2="760" y2="208"></line>
                    </g>
                    <?php if ($revenueFill !== ''): ?>
                        <polygon points="<?= e($revenueFill) ?>"></polygon>
                        <polyline points="<?= e($revenuePoints) ?>"></polyline>
                    <?php endif; ?>
                </svg>
                <footer>
                    <?php foreach ($axisLabels($series) as $label): ?>
                        <span><?= e($label) ?></span>
                    <?php endforeach; ?>
                </footer>
            </div>
        </article>

        <article class="report-panel">
            <header class="report-panel-header">
                <h2>Répartition du chiffre d'affaires</h2>
            </header>
            <section class="report-donut-layout">
                <div class="report-donut" style="<?= e($donutStyle($revenueBreakdown ?? [])) ?>">
                    <span><?= e(format_money($summary['revenue']['value'] ?? 0)) ?></span>
                    <small>Total</small>
                </div>
                <ul class="report-legend">
                    <?php foreach (($revenueBreakdown ?? []) as $row): ?>
                        <li>
                            <span style="background: <?= e($row['color']) ?>"></span>
                            <strong><?= e($row['label']) ?></strong>
                            <small><?= e((string) $row['percent']) ?>% (<?= e(format_money($row['total'])) ?>)</small>
                        </li>
                    <?php endforeach; ?>
                    <?php if (($revenueBreakdown ?? []) === []): ?>
                        <li><strong>Aucune donnée</strong><small>La répartition apparaîtra après facturation.</small></li>
                    <?php endif; ?>
                </ul>
            </section>
        </article>

        <article class="report-panel">
            <header class="report-panel-header">
                <h2>Activité par praticien</h2>
            </header>
            <div class="report-practitioner-list">
                <?php foreach (($practitionerActivity ?? []) as $row): ?>
                    <article>
                        <span class="patient-avatar"><?= e($nameInitials($row['full_name'])) ?></span>
                        <div>
                            <strong><?= e($row['full_name']) ?></strong>
                            <small><?= e((string) $row['appointments']) ?> rendez-vous</small>
                        </div>
                        <p>
                            <?= e(format_money($row['revenue'])) ?>
                            <small>CA généré</small>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
            <a class="report-panel-link" href="#">Voir le rapport complet</a>
        </article>

        <article class="report-panel">
            <header class="report-panel-header">
                <h2>Répartition des rendez-vous</h2>
            </header>
            <section class="report-donut-layout compact">
                <div class="report-donut small" style="<?= e($donutStyle($appointmentBreakdown ?? [])) ?>">
                    <span><?= e(number_format(array_sum(array_column($appointmentBreakdown ?? [], 'total')), 0, ',', ' ')) ?></span>
                    <small>Total</small>
                </div>
                <ul class="report-legend">
                    <?php foreach (($appointmentBreakdown ?? []) as $row): ?>
                        <li>
                            <span style="background: <?= e($row['color']) ?>"></span>
                            <strong><?= e($row['label']) ?></strong>
                            <small><?= e((string) $row['total']) ?> (<?= e((string) $row['percent']) ?>%)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <a class="report-panel-link" href="#">Voir le rapport complet</a>
        </article>

        <article class="report-panel">
            <header class="report-panel-header">
                <h2>Top traitements</h2>
            </header>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Traitement</th>
                        <th>Nombre</th>
                        <th>CA généré</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($topTreatments ?? []) as $row): ?>
                        <tr>
                            <td><?= e($row['description']) ?></td>
                            <td><?= e(number_format((float) $row['quantity'], 0, ',', ' ')) ?></td>
                            <td><?= e(format_money($row['revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($topTreatments ?? []) === []): ?>
                        <tr><td colspan="3">Aucun traitement facturé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <a class="report-panel-link" href="#">Voir le rapport complet</a>
        </article>

        <article class="report-panel report-panel-wide">
            <header class="report-panel-header">
                <h2>Évolution des indicateurs clés</h2>
                <span>Par jour</span>
            </header>
            <div class="report-indicator-chart">
                <svg viewBox="0 0 520 170" role="img" aria-label="Indicateurs clés">
                    <g class="grid">
                        <line x1="0" y1="42" x2="520" y2="42"></line>
                        <line x1="0" y1="84" x2="520" y2="84"></line>
                        <line x1="0" y1="126" x2="520" y2="126"></line>
                    </g>
                    <polyline class="line-blue" points="<?= e($indicatorRevenue) ?>"></polyline>
                    <polyline class="line-green" points="<?= e($indicatorAppointments) ?>"></polyline>
                    <polyline class="line-orange" points="<?= e($indicatorPatients) ?>"></polyline>
                </svg>
                <footer>
                    <span><i class="blue"></i> CA</span>
                    <span><i class="green"></i> Rendez-vous</span>
                    <span><i class="orange"></i> Nouveaux patients</span>
                </footer>
            </div>
        </article>

        <article class="report-panel report-performance-panel">
            <header class="report-panel-header">
                <h2>Performance mensuelle</h2>
                <span><?= e($period['label']) ?></span>
            </header>
            <div class="report-bars">
                <?php foreach (($performance ?? []) as $row): ?>
                    <?php
                        $previousHeight = max(8, ((float) ($row['previous'] ?? 0) / $maxPerformance) * 128);
                        $currentHeight = max(8, ((float) ($row['current'] ?? 0) / $maxPerformance) * 128);
                    ?>
                    <article>
                        <div>
                            <span class="bar previous" style="height: <?= e((string) round($previousHeight)) ?>px"></span>
                            <span class="bar current" style="height: <?= e((string) round($currentHeight)) ?>px"></span>
                        </div>
                        <strong><?= e($formatPerformance($row, 'current')) ?></strong>
                        <small><?= e($row['label']) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </article>
    </section>
</section>
