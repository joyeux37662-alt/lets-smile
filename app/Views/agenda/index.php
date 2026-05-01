<?php
use App\Core\Auth;

$workStartHour = 8;
$workEndHour = 18;
$workMinutes = ($workEndHour - $workStartHour) * 60;
$weekDays = [];
$daysShort = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.'];
$months = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$rangeStart = $weekStart;
$rangeEnd = $weekStart->modify('+4 days');
$today = new DateTimeImmutable('today');
$selected = $selectedAppointment;
$filters = $filters ?? ['practitioner_id' => 0, 'room_id' => 0, 'show_cancelled' => false];
$baseFilters = array_filter([
    'practitioner_id' => !empty($filters['practitioner_id']) ? $filters['practitioner_id'] : null,
    'room_id' => !empty($filters['room_id']) ? $filters['room_id'] : null,
    'show_cancelled' => !empty($filters['show_cancelled']) ? '1' : null,
], static fn ($value) => $value !== null && $value !== '');
$agendaUrl = static function (DateTimeImmutable $date, array $extra = []) use ($baseFilters): string {
    return app_url('/agenda?' . http_build_query($baseFilters + ['date' => $date->format('Y-m-d')] + $extra));
};
$appointmentUrl = static function (int $appointmentId) use ($weekStart, $baseFilters): string {
    return app_url('/agenda?' . http_build_query($baseFilters + ['date' => $weekStart->format('Y-m-d'), 'appointment' => $appointmentId]));
};
$rangeLabel = static function (?string $start, ?string $end): string {
    if (!$start || !$end) {
        return '-';
    }

    return format_time($start) . ' – ' . format_time($end);
};
$statusLabels = [
    'pending' => 'En attente',
    'confirmed' => 'Confirmé',
    'completed' => 'Terminé',
    'cancelled' => 'Annulé',
    'missed' => 'Non honoré',
];
$typeColors = [];
foreach (($options['types'] ?? []) as $type) {
    $typeColors[$type['name']] = $type['color'] ?: '#2563EB';
}

for ($i = 0; $i < 5; $i++) {
    $date = $weekStart->modify('+' . $i . ' days');
    $weekDays[$date->format('Y-m-d')] = [
        'date' => $date,
        'label' => $daysShort[$i],
        'appointments' => [],
    ];
}

foreach ($appointments as $appointment) {
    $key = (new DateTimeImmutable($appointment['starts_at']))->format('Y-m-d');

    if (isset($weekDays[$key])) {
        $weekDays[$key]['appointments'][] = $appointment;
    }
}

$eventStyle = static function (array $appointment) use ($workStartHour, $workMinutes): string {
    $start = new DateTimeImmutable($appointment['starts_at']);
    $end = new DateTimeImmutable($appointment['ends_at']);
    $startMinutes = ((int) $start->format('H') * 60 + (int) $start->format('i')) - ($workStartHour * 60);
    $endMinutes = ((int) $end->format('H') * 60 + (int) $end->format('i')) - ($workStartHour * 60);
    $top = max(0, min(100, ($startMinutes / $workMinutes) * 100));
    $height = max(6, min(100 - $top, (($endMinutes - $startMinutes) / $workMinutes) * 100));
    $color = is_string($appointment['type_color'] ?? null) && preg_match('/^#[0-9A-Fa-f]{6}$/', $appointment['type_color'])
        ? $appointment['type_color']
        : '#2563EB';

    return '--event-top:' . $top . '%; --event-height:' . $height . '%; --event-color:' . $color . ';';
};

$formAppointment = [
    'id' => '',
    'patient_id' => '',
    'practitioner_id' => Auth::user()['id'] ?? '',
    'room_id' => !empty($filters['room_id']) ? $filters['room_id'] : '',
    'appointment_type_id' => '',
    'date' => $today->format('Y-m-d'),
    'start_time' => '09:00',
    'end_time' => '10:00',
    'status' => 'confirmed',
    'notes' => '',
];

$monthStart = $weekStart->modify('first day of this month');
$calendarStart = $monthStart->modify('monday this week');
?>

<section class="agenda-shell">
    <article class="agenda-board">
        <header class="agenda-toolbar">
            <a class="button button-light" href="<?= e($agendaUrl($today)) ?>">Aujourd’hui</a>
            <div class="agenda-range-nav">
                <a href="<?= e($agendaUrl($weekStart->modify('-7 days'))) ?>" aria-label="Semaine précédente">‹</a>
                <a href="<?= e($agendaUrl($weekStart->modify('+7 days'))) ?>" aria-label="Semaine suivante">›</a>
            </div>
            <strong><?= e($rangeStart->format('d')) ?> – <?= e($rangeEnd->format('d')) ?> <?= e($months[(int) $rangeEnd->format('n')]) ?> <?= e($rangeEnd->format('Y')) ?></strong>
            <nav class="agenda-view-switch" aria-label="Vue agenda">
                <a href="#">Jour</a>
                <a class="active" href="#">Semaine</a>
                <a href="#">Mois</a>
            </nav>
            <button class="square-action agenda-settings" type="button" aria-label="Paramètres"></button>
        </header>

        <div class="agenda-week" style="--day-count: <?= e((string) count($weekDays)) ?>;">
            <div class="agenda-week-head">
                <span></span>
                <?php foreach ($weekDays as $day): ?>
                    <?php $isToday = $day['date']->format('Y-m-d') === $today->format('Y-m-d'); ?>
                    <div class="<?= $isToday ? 'is-today' : '' ?>">
                        <span><?= e($day['label']) ?></span>
                        <strong><?= e($day['date']->format('d')) ?></strong>
                        <small><?= e($months[(int) $day['date']->format('n')]) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="agenda-week-grid">
                <div class="agenda-time-axis">
                    <?php for ($hour = $workStartHour; $hour <= $workEndHour; $hour++): ?>
                        <span style="top: <?= e((string) ((($hour - $workStartHour) / ($workEndHour - $workStartHour)) * 100)) ?>%;"><?= e(str_pad((string) $hour, 2, '0', STR_PAD_LEFT)) ?>:00</span>
                    <?php endfor; ?>
                </div>

                <?php foreach ($weekDays as $dayKey => $day): ?>
                    <div class="agenda-day-column">
                        <?php if ($day['date']->format('Y-m-d') === $today->format('Y-m-d')): ?>
                            <?php
                            $now = new DateTimeImmutable('now');
                            $minutesNow = ((int) $now->format('H') * 60 + (int) $now->format('i')) - ($workStartHour * 60);
                            $nowTop = max(0, min(100, ($minutesNow / $workMinutes) * 100));
                            ?>
                            <span class="agenda-now-line" style="top: <?= e((string) $nowTop) ?>%;"></span>
                        <?php endif; ?>

                        <?php foreach ($day['appointments'] as $appointment): ?>
                            <?php $isSelected = $selected && (int) $selected['id'] === (int) $appointment['id']; ?>
                            <a class="agenda-event <?= e($appointment['status']) ?> <?= $isSelected ? 'is-selected' : '' ?>" href="<?= e($appointmentUrl((int) $appointment['id'])) ?>" style="<?= e($eventStyle($appointment)) ?>">
                                <span><?= e($rangeLabel($appointment['starts_at'], $appointment['ends_at'])) ?></span>
                                <strong><?= e(full_name($appointment)) ?></strong>
                                <small><?= e($appointment['type_name'] ?: 'Rendez-vous') ?></small>
                                <em><?= e($appointment['room_name'] ?: 'Salle non renseignée') ?></em>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <footer class="agenda-legend">
                <?php foreach ($typeColors as $name => $color): ?>
                    <?php $safeColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#2563EB'; ?>
                    <span style="--legend-color: <?= e($safeColor) ?>;"><?= e($name) ?></span>
                <?php endforeach; ?>
            </footer>
        </div>
    </article>

    <aside class="agenda-side">
        <section class="agenda-detail-card">
            <?php if ($selected): ?>
                <div class="detail-close" aria-hidden="true">×</div>
                <h2>Détails du rendez-vous</h2>
                <div class="agenda-detail-line" style="--event-color: <?= e(preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($selected['type_color'] ?? '')) ? $selected['type_color'] : '#2563EB') ?>;">
                    <strong><?= e($rangeLabel($selected['starts_at'], $selected['ends_at'])) ?></strong>
                    <small><?= e($selected['type_name'] ?: 'Rendez-vous') ?></small>
                </div>
                <dl class="agenda-detail-list">
                    <div><dt>Patient</dt><dd><?= e(full_name($selected)) ?></dd></div>
                    <div><dt>Type de soin</dt><dd><?= e($selected['type_name'] ?: '-') ?></dd></div>
                    <div><dt>Salle</dt><dd><?= e($selected['room_name'] ?: '-') ?></dd></div>
                    <div><dt>Praticien</dt><dd><?= e($selected['practitioner_name'] ?: '-') ?></dd></div>
                    <div><dt>Téléphone</dt><dd><?= e($selected['phone'] ?: '-') ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($selected['email'] ?: '-') ?></dd></div>
                    <div><dt>Statut</dt><dd><span class="rdv-status rdv-status-<?= e($selected['status']) ?>"><?= e($statusLabels[$selected['status']] ?? $selected['status']) ?></span></dd></div>
                    <div><dt>Notes</dt><dd><?= e($selected['notes'] ?: 'Aucune note') ?></dd></div>
                </dl>
                <div class="agenda-detail-actions">
                    <a class="button button-light" href="<?= e(app_url('/patients?patient=' . (int) $selected['patient_id'])) ?>">Voir le patient</a>
                    <button class="button button-light" type="button" data-modal-open="appointment-edit">Modifier</button>
                </div>
            <?php else: ?>
                <div class="detail-empty agenda-empty-detail">
                    <span class="patient-avatar large">AG</span>
                    <h2>Aucun rendez-vous</h2>
                    <p>Cette semaine est libre avec les filtres actuels.</p>
                    <button class="button button-primary" type="button" data-modal-open="appointment-create">Nouveau rendez-vous</button>
                </div>
            <?php endif; ?>
        </section>

        <section class="agenda-month-card">
            <header>
                <strong><?= e(ucfirst($months[(int) $weekStart->format('n')])) ?> <?= e($weekStart->format('Y')) ?></strong>
                <span>
                    <a href="<?= e($agendaUrl($weekStart->modify('-1 month'))) ?>">‹</a>
                    <a href="<?= e($agendaUrl($weekStart->modify('+1 month'))) ?>">›</a>
                </span>
            </header>
            <div class="agenda-mini-month">
                <?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $dayName): ?>
                    <b><?= e($dayName) ?></b>
                <?php endforeach; ?>
                <?php for ($i = 0; $i < 42; $i++): ?>
                    <?php
                    $date = $calendarStart->modify('+' . $i . ' days');
                    $inMonth = $date->format('m') === $weekStart->format('m');
                    $inWeek = $date >= $weekStart && $date <= $weekStart->modify('+4 days');
                    $isToday = $date->format('Y-m-d') === $today->format('Y-m-d');
                    ?>
                    <a class="<?= !$inMonth ? 'muted-day' : '' ?> <?= $inWeek ? 'in-week' : '' ?> <?= $isToday ? 'today' : '' ?>" href="<?= e($agendaUrl($date)) ?>"><?= e($date->format('j')) ?></a>
                <?php endfor; ?>
            </div>
        </section>

        <section class="agenda-filter-card">
            <h2>Filtres d’affichage</h2>
            <form method="get" action="<?= e(app_url('/agenda')) ?>">
                <input type="hidden" name="date" value="<?= e($weekStart->format('Y-m-d')) ?>">
                <label>
                    <span>Praticien</span>
                    <select name="practitioner_id">
                        <option value="">Tous les praticiens</option>
                        <?php foreach (($options['practitioners'] ?? []) as $practitioner): ?>
                            <option value="<?= e($practitioner['id']) ?>" <?= (int) ($filters['practitioner_id'] ?? 0) === (int) $practitioner['id'] ? 'selected' : '' ?>><?= e($practitioner['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Salle</span>
                    <select name="room_id">
                        <option value="">Toutes les salles</option>
                        <?php foreach (($options['rooms'] ?? []) as $room): ?>
                            <option value="<?= e($room['id']) ?>" <?= (int) ($filters['room_id'] ?? 0) === (int) $room['id'] ? 'selected' : '' ?>><?= e($room['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="agenda-check">
                    <input type="checkbox" name="show_cancelled" value="1" <?= !empty($filters['show_cancelled']) ? 'checked' : '' ?>>
                    Afficher les annulés
                </label>
                <label class="agenda-check">
                    <input type="checkbox" checked disabled>
                    Afficher les disponibilités
                </label>
                <button class="button button-primary button-full" type="submit">Appliquer les filtres</button>
            </form>
        </section>
    </aside>
</section>

<?php
$formAction = app_url('/appointments/store');
$formTitle = 'Nouveau rendez-vous';
$modalId = 'appointment-create';
$returnTo = '/agenda?' . http_build_query($baseFilters + ['date' => $weekStart->format('Y-m-d')]);
require app_path('Views/appointments/appointment-form.php');

if ($selected) {
    $formAppointment = array_merge($formAppointment, $selected, [
        'date' => (new DateTimeImmutable($selected['starts_at']))->format('Y-m-d'),
        'start_time' => (new DateTimeImmutable($selected['starts_at']))->format('H:i'),
        'end_time' => (new DateTimeImmutable($selected['ends_at']))->format('H:i'),
    ]);
    $formAction = app_url('/appointments/update');
    $formTitle = 'Modifier le rendez-vous';
    $modalId = 'appointment-edit';
    $returnTo = '/agenda?' . http_build_query($baseFilters + ['date' => $weekStart->format('Y-m-d')]);
    require app_path('Views/appointments/appointment-form.php');
}
?>
