<?php
use App\Core\Auth;

$rows = $appointments['data'] ?? [];
$total = (int) ($appointments['total'] ?? 0);
$page = (int) ($appointments['page'] ?? 1);
$pages = (int) ($appointments['pages'] ?? 1);
$perPage = (int) ($appointments['per_page'] ?? 8);
$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);
$selected = $selectedAppointment;
$currentStatus = $status ?? 'all';
$queryBase = array_filter(['q' => $search ?? '', 'status' => $currentStatus !== 'all' ? $currentStatus : ''], static fn ($value) => $value !== '');
$pageUrl = static function (int $targetPage) use ($queryBase): string {
    return app_url('/appointments?' . http_build_query($queryBase + ['page' => $targetPage]));
};
$appointmentUrl = static function (int $appointmentId) use ($queryBase, $page): string {
    return app_url('/appointments?' . http_build_query($queryBase + ['page' => $page, 'appointment' => $appointmentId]));
};
$tabUrl = static function (string $targetStatus) use ($search): string {
    return app_url('/appointments?' . http_build_query(array_filter(['q' => $search ?? '', 'status' => $targetStatus !== 'all' ? $targetStatus : ''])));
};
$statusLabels = [
    'pending' => 'En attente',
    'confirmed' => 'Confirmé',
    'completed' => 'Terminé',
    'cancelled' => 'Annulé',
    'missed' => 'Non honoré',
];
$formatDay = static function (?string $date): string {
    if (!$date) {
        return '-';
    }

    $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $months = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $value = new DateTimeImmutable($date);

    return ucfirst($days[(int) $value->format('w')]) . ' ' . $value->format('j') . ' ' . $months[(int) $value->format('n')] . ' ' . $value->format('Y');
};
$rangeLabel = static function (?string $start, ?string $end): string {
    if (!$start || !$end) {
        return '-';
    }

    return format_time($start) . ' – ' . format_time($end);
};
$percent = static function (int $value, int $total): string {
    return $total <= 0 ? '0%' : number_format(($value / $total) * 100, 1, ',', ' ') . '%';
};
$defaultAppointment = [
    'id' => '',
    'patient_id' => '',
    'practitioner_id' => Auth::user()['id'] ?? '',
    'room_id' => '',
    'appointment_type_id' => '',
    'date' => date('Y-m-d'),
    'start_time' => '09:00',
    'end_time' => '10:00',
    'status' => 'confirmed',
    'notes' => '',
];
?>

<section class="appointment-stat-grid" aria-label="Résumé des rendez-vous">
    <article class="appointment-stat-card appointment-stat-blue">
        <span class="appointment-stat-icon rdv-calendar" aria-hidden="true"></span>
        <div>
            <p>Rendez-vous aujourd’hui</p>
            <strong><?= e((string) ($stats['today'] ?? 0)) ?></strong>
            <small>Voir l’agenda</small>
        </div>
    </article>
    <article class="appointment-stat-card appointment-stat-green">
        <span class="appointment-stat-icon rdv-check" aria-hidden="true"></span>
        <div>
            <p>Confirmés</p>
            <strong><?= e((string) ($stats['confirmed'] ?? 0)) ?></strong>
            <small><?= e($percent((int) ($stats['confirmed'] ?? 0), (int) ($stats['upcoming_total'] ?? 1))) ?></small>
        </div>
    </article>
    <article class="appointment-stat-card appointment-stat-orange">
        <span class="appointment-stat-icon rdv-clock" aria-hidden="true"></span>
        <div>
            <p>En attente</p>
            <strong><?= e((string) ($stats['pending'] ?? 0)) ?></strong>
            <small><?= e($percent((int) ($stats['pending'] ?? 0), (int) ($stats['upcoming_total'] ?? 1))) ?></small>
        </div>
    </article>
    <article class="appointment-stat-card appointment-stat-red">
        <span class="appointment-stat-icon rdv-x" aria-hidden="true"></span>
        <div>
            <p>Annulés</p>
            <strong><?= e((string) ($stats['cancelled'] ?? 0)) ?></strong>
            <small><?= e($percent((int) ($stats['cancelled'] ?? 0), (int) ($stats['upcoming_total'] ?? 1))) ?></small>
        </div>
    </article>
</section>

<section class="appointments-workspace">
    <article class="appointments-table-card">
        <header class="appointments-toolbar">
            <nav class="appointment-tabs" aria-label="Statut des rendez-vous">
                <?php foreach (['all' => 'Tous', 'confirmed' => 'Confirmés', 'pending' => 'En attente', 'cancelled' => 'Annulés', 'past' => 'Passés'] as $key => $label): ?>
                    <a class="<?= $currentStatus === $key ? 'active' : '' ?>" href="<?= e($tabUrl($key)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <button class="button button-light button-filter" type="button"><span aria-hidden="true"></span> Filtres</button>
            <div class="appointment-date-window">
                <a href="#" aria-label="Semaine précédente">‹</a>
                <strong><?= e(date('d')) ?> – <?= e(date('d M Y', strtotime('+4 days'))) ?></strong>
                <a href="#" aria-label="Semaine suivante">›</a>
            </div>
            <button class="square-action" type="button" aria-label="Options"></button>
        </header>

        <div class="appointments-table-wrap">
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" aria-label="Tout sélectionner"></th>
                        <th>Date / Heure ↑</th>
                        <th>Patient</th>
                        <th>Type de soin</th>
                        <th>Praticien</th>
                        <th>Statut</th>
                        <th>Salle</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td class="appointments-empty" colspan="8">
                                Aucun rendez-vous trouvé.
                                <button class="link-button" type="button" data-modal-open="appointment-create">Créer un rendez-vous</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $appointment): ?>
                        <?php $isSelected = $selected && (int) $selected['id'] === (int) $appointment['id']; ?>
                        <tr class="<?= $isSelected ? 'is-selected' : '' ?>" data-row-href="<?= e($appointmentUrl((int) $appointment['id'])) ?>">
                            <td><input type="checkbox" aria-label="Sélectionner"></td>
                            <td>
                                <span class="appointment-date"><?= e(format_date($appointment['starts_at'])) ?></span>
                                <small><?= e($rangeLabel($appointment['starts_at'], $appointment['ends_at'])) ?></small>
                            </td>
                            <td>
                                <div class="appointment-patient-cell">
                                    <span class="patient-avatar"><?= e(initials($appointment)) ?></span>
                                    <span>
                                        <a href="<?= e($appointmentUrl((int) $appointment['id'])) ?>"><?= e(full_name($appointment)) ?></a>
                                        <small><?= e($appointment['patient_age'] !== null ? $appointment['patient_age'] . ' ans' : $appointment['patient_reference']) ?></small>
                                    </span>
                                </div>
                            </td>
                            <td><?= e($appointment['type_name'] ?: '-') ?></td>
                            <td>
                                <div class="practitioner-cell">
                                    <span class="mini-avatar"><?= e(strtoupper(substr((string) ($appointment['practitioner_name'] ?? 'D'), 0, 1))) ?></span>
                                    <?= e($appointment['practitioner_name'] ?: '-') ?>
                                </div>
                            </td>
                            <td><span class="rdv-status rdv-status-<?= e($appointment['status']) ?>"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></span></td>
                            <td><?= e($appointment['room_name'] ?: '-') ?></td>
                            <td>
                                <a class="table-eye" href="<?= e($appointmentUrl((int) $appointment['id'])) ?>" aria-label="Voir"></a>
                                <a class="row-more" href="<?= e($appointmentUrl((int) $appointment['id'])) ?>" aria-label="Détails">⋮</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="appointments-pagination">
            <p>Affichage de <?= e((string) $from) ?> à <?= e((string) $to) ?> sur <?= e(number_format($total, 0, ',', ' ')) ?> rendez-vous</p>
            <nav aria-label="Pagination">
                <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e($pageUrl(max(1, $page - 1))) ?>">‹</a>
                <?php for ($i = 1; $i <= min($pages, 3); $i++): ?>
                    <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= e($pageUrl($i)) ?>"><?= e((string) $i) ?></a>
                <?php endfor; ?>
                <?php if ($pages > 4): ?>
                    <span>...</span>
                    <a href="<?= e($pageUrl($pages)) ?>"><?= e((string) $pages) ?></a>
                <?php elseif ($pages === 4): ?>
                    <a class="<?= $page === 4 ? 'active' : '' ?>" href="<?= e($pageUrl(4)) ?>">4</a>
                <?php endif; ?>
                <a class="<?= $page >= $pages ? 'disabled' : '' ?>" href="<?= e($pageUrl(min($pages, $page + 1))) ?>">›</a>
            </nav>
        </footer>
    </article>

    <aside class="appointment-detail-card">
        <?php if ($selected): ?>
            <div class="detail-close" aria-hidden="true">×</div>
            <section class="appointment-detail-profile">
                <span class="detail-avatar"><?= e(initials($selected)) ?></span>
                <div>
                    <h2><?= e(full_name($selected)) ?></h2>
                    <p><?= e($selected['patient_age'] !== null ? $selected['patient_age'] . ' ans' : 'Âge non renseigné') ?> · <?= e(format_date($selected['birth_date'] ?? null)) ?> · ID #<?= e($selected['patient_reference']) ?></p>
                </div>
            </section>

            <section class="appointment-detail-actions" aria-label="Actions rendez-vous">
                <a href="<?= e(app_url('/patients?patient=' . (int) $selected['patient_id'])) ?>" class="appointment-action"><span class="appointment-action-icon calendar"></span>Voir le patient</a>
                <a href="<?= e($selected['phone'] ? 'tel:' . $selected['phone'] : '#') ?>" class="appointment-action"><span class="appointment-action-icon phone"></span>Téléphoner</a>
                <a href="<?= e($selected['phone'] ? 'sms:' . $selected['phone'] : '#') ?>" class="appointment-action"><span class="appointment-action-icon message"></span>Envoyer un SMS</a>
            </section>

            <section class="appointment-detail-section">
                <dl class="appointment-detail-list">
                    <div><dt>Date / Heure</dt><dd><?= e($formatDay($selected['starts_at'])) ?><br><?= e($rangeLabel($selected['starts_at'], $selected['ends_at'])) ?></dd></div>
                    <div><dt>Type de soin</dt><dd><?= e($selected['type_name'] ?: '-') ?></dd></div>
                    <div><dt>Praticien</dt><dd><?= e($selected['practitioner_name'] ?: '-') ?></dd></div>
                    <div><dt>Salle</dt><dd><?= e($selected['room_name'] ?: '-') ?></dd></div>
                    <div><dt>Statut</dt><dd><span class="rdv-status rdv-status-<?= e($selected['status']) ?>"><?= e($statusLabels[$selected['status']] ?? $selected['status']) ?></span></dd></div>
                    <div><dt>Rappel</dt><dd>24h avant le rendez-vous</dd></div>
                    <div><dt>Notes</dt><dd><?= e($selected['notes'] ?: 'Aucune note') ?></dd></div>
                </dl>
            </section>

            <section class="appointment-detail-buttons">
                <button class="button button-light" type="button" data-modal-open="appointment-edit"><span class="button-pencil" aria-hidden="true"></span> Modifier</button>
                <form method="post" action="<?= e(app_url('/appointments/cancel')) ?>" data-confirm="Annuler ce rendez-vous ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
                    <input type="hidden" name="reason" value="Annulation depuis la fiche rendez-vous">
                    <button class="button button-danger" type="submit"><span class="button-trash" aria-hidden="true"></span> Annuler</button>
                </form>
                <button class="button button-light button-full" type="button" data-modal-open="appointment-create"><span class="button-copy" aria-hidden="true"></span> Dupliquer le rendez-vous</button>
            </section>
        <?php else: ?>
            <section class="detail-empty">
                <span class="patient-avatar large">RDV</span>
                <h2>Aucun rendez-vous sélectionné</h2>
                <p>Créez un rendez-vous pour afficher sa fiche complète.</p>
                <button class="button button-primary" type="button" data-modal-open="appointment-create">Nouveau rendez-vous</button>
            </section>
        <?php endif; ?>
    </aside>
</section>

<?php
$formAppointment = $defaultAppointment;
$formAction = app_url('/appointments/store');
$formTitle = 'Nouveau rendez-vous';
$modalId = 'appointment-create';
require app_path('Views/appointments/appointment-form.php');

if ($selected) {
    $formAppointment = array_merge($defaultAppointment, $selected, [
        'date' => (new DateTimeImmutable($selected['starts_at']))->format('Y-m-d'),
        'start_time' => (new DateTimeImmutable($selected['starts_at']))->format('H:i'),
        'end_time' => (new DateTimeImmutable($selected['ends_at']))->format('H:i'),
    ]);
    $formAction = app_url('/appointments/update');
    $formTitle = 'Modifier le rendez-vous';
    $modalId = 'appointment-edit';
    require app_path('Views/appointments/appointment-form.php');
}
?>
