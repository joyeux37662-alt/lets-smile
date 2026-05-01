<?php
$total = (int) ($stats['total'] ?? 0);
$active = (int) ($stats['active'] ?? 0);
$inactive = (int) ($stats['inactive'] ?? 0);
$newThisMonth = (int) ($stats['new_this_month'] ?? 0);
$activeRate = $total > 0 ? round(($active / $total) * 100, 1) : 0;
$inactiveRate = $total > 0 ? round(($inactive / $total) * 100, 1) : 0;
$rows = $patients['data'] ?? [];
$page = (int) ($patients['page'] ?? 1);
$pages = (int) ($patients['pages'] ?? 1);
$perPage = (int) ($patients['per_page'] ?? 8);
$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);
$selected = $selectedPatient;
$queryBase = array_filter(['q' => $search ?? ''], static fn ($value) => $value !== '');
$pageUrl = static function (int $targetPage) use ($queryBase): string {
    return app_url('/patients?' . http_build_query($queryBase + ['page' => $targetPage]));
};
$patientUrl = static function (int $patientId) use ($queryBase, $page): string {
    return app_url('/patients?' . http_build_query($queryBase + ['page' => $page, 'patient' => $patientId]));
};
$statusLabel = static fn (?string $status): string => $status === 'inactive' ? 'Inactif' : 'Actif';
$safePatient = [
    'id' => '',
    'first_name' => '',
    'last_name' => '',
    'gender' => '',
    'birth_date' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'profession' => '',
    'status' => 'active',
    'blood_group' => '',
    'allergies' => '',
    'medical_history' => '',
    'current_medications' => '',
    'medical_notes' => '',
];
?>

<section class="patient-stat-grid" aria-label="Résumé des patients">
    <article class="patient-stat-card patient-stat-blue">
        <span class="patient-stat-icon icon-people" aria-hidden="true"></span>
        <div>
            <p>Total patients</p>
            <strong><?= e(number_format($total, 0, ',', ' ')) ?></strong>
            <small>+ <?= e((string) $newThisMonth) ?> ce mois</small>
        </div>
    </article>
    <article class="patient-stat-card patient-stat-green">
        <span class="patient-stat-icon icon-calendar-check" aria-hidden="true"></span>
        <div>
            <p>Nouveaux patients</p>
            <strong><?= e((string) $newThisMonth) ?></strong>
            <small>Ce mois</small>
        </div>
    </article>
    <article class="patient-stat-card patient-stat-pink">
        <span class="patient-stat-icon icon-heart" aria-hidden="true"></span>
        <div>
            <p>Patients actifs</p>
            <strong><?= e(number_format($active, 0, ',', ' ')) ?></strong>
            <small><?= e((string) $activeRate) ?>% du total</small>
        </div>
    </article>
    <article class="patient-stat-card patient-stat-orange">
        <span class="patient-stat-icon icon-clock-round" aria-hidden="true"></span>
        <div>
            <p>Inactifs</p>
            <strong><?= e(number_format($inactive, 0, ',', ' ')) ?></strong>
            <small><?= e((string) $inactiveRate) ?>% du total</small>
        </div>
    </article>
</section>

<section class="patients-workspace">
    <article class="patients-table-card">
        <header class="patients-toolbar">
            <form class="patient-inline-search" method="get" action="<?= e(app_url('/patients')) ?>">
                <input type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Rechercher un patient...">
                <button type="submit" aria-label="Rechercher"></button>
            </form>
            <button class="button button-light button-filter" type="button"><span aria-hidden="true"></span> Filtres</button>
            <a class="button button-light button-export" href="<?= e(app_url('/patients')) ?>"><span aria-hidden="true"></span> Exporter</a>
            <button class="square-action" type="button" aria-label="Options"></button>
        </header>

        <div class="patients-table-wrap">
            <table class="patients-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Âge</th>
                        <th>Téléphone</th>
                        <th>Dernier rendez-vous</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td class="patients-empty" colspan="6">
                                Aucun patient pour le moment.
                                <button class="link-button" type="button" data-modal-open="patient-create">Ajouter le premier patient</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $patient): ?>
                        <?php $isSelected = $selected && (int) $selected['id'] === (int) $patient['id']; ?>
                        <tr class="<?= $isSelected ? 'is-selected' : '' ?>" data-row-href="<?= e($patientUrl((int) $patient['id'])) ?>">
                            <td>
                                <div class="patient-cell">
                                    <span class="patient-avatar"><?= e(initials($patient)) ?></span>
                                    <span>
                                        <a href="<?= e($patientUrl((int) $patient['id'])) ?>"><?= e(full_name($patient)) ?></a>
                                        <small><?= e($patient['email'] ?: $patient['reference']) ?></small>
                                    </span>
                                </div>
                            </td>
                            <td><?= e($patient['age'] !== null ? $patient['age'] . ' ans' : '-') ?></td>
                            <td><?= e($patient['phone'] ?: '-') ?></td>
                            <td><?= e(format_date($patient['last_appointment_at'] ?? null)) ?></td>
                            <td><span class="status-pill status-<?= e($patient['status']) ?>"><?= e($statusLabel($patient['status'])) ?></span></td>
                            <td><a class="row-more" href="<?= e($patientUrl((int) $patient['id'])) ?>" aria-label="Voir la fiche">⋮</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="patients-pagination">
            <p>Affichage de <?= e((string) $from) ?> à <?= e((string) $to) ?> sur <?= e(number_format($total, 0, ',', ' ')) ?> patients</p>
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

    <aside class="patient-detail-card">
        <?php if ($selected): ?>
            <div class="detail-close" aria-hidden="true">×</div>
            <section class="detail-profile">
                <span class="detail-avatar"><?= e(initials($selected)) ?></span>
                <div>
                    <h2><?= e(full_name($selected)) ?></h2>
                    <p><?= e($selected['age'] !== null ? $selected['age'] . ' ans' : 'Âge non renseigné') ?> · <?= e(format_date($selected['birth_date'] ?? null)) ?> · ID #<?= e($selected['reference']) ?></p>
                    <span class="status-pill status-<?= e($selected['status']) ?>"><?= e($statusLabel($selected['status'])) ?></span>
                </div>
            </section>

            <section class="detail-actions" aria-label="Actions patient">
                <a href="#" class="detail-action"><span class="detail-action-icon appointment"></span>Prendre RDV</a>
                <button class="detail-action" type="button" data-modal-open="patient-edit"><span class="detail-action-icon document"></span>Modifier</button>
                <a href="#" class="detail-action"><span class="detail-action-icon tooth"></span>Nouveau traitement</a>
                <a href="<?= e(app_url('/patients/pdf?id=' . (int) $selected['id'])) ?>" class="detail-action"><span class="detail-action-icon more"></span>Dossier PDF</a>
            </section>

            <nav class="detail-tabs" aria-label="Fiche patient">
                <a class="active" href="#">Informations</a>
                <a href="#">Historique</a>
                <a href="#">Traitements</a>
                <a href="#">Documents</a>
            </nav>

            <section class="detail-section">
                <header>
                    <h3>Informations personnelles</h3>
                    <button type="button" data-modal-open="patient-edit">Modifier</button>
                </header>
                <dl class="detail-list">
                    <div><dt>Téléphone</dt><dd><?= e($selected['phone'] ?: '-') ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($selected['email'] ?: '-') ?></dd></div>
                    <div><dt>Adresse</dt><dd><?= e($selected['address'] ?: '-') ?></dd></div>
                    <div><dt>Profession</dt><dd><?= e($selected['profession'] ?: '-') ?></dd></div>
                </dl>
            </section>

            <section class="detail-section">
                <header>
                    <h3>Informations médicales</h3>
                    <button type="button" data-modal-open="patient-edit">Modifier</button>
                </header>
                <dl class="detail-list">
                    <div><dt>Groupe sanguin</dt><dd><?= e($selected['blood_group'] ?: '-') ?></dd></div>
                    <div><dt>Allergies</dt><dd><?= e($selected['allergies'] ?: 'Aucune connue') ?></dd></div>
                    <div><dt>Antécédents</dt><dd><?= e($selected['medical_history'] ?: '-') ?></dd></div>
                    <div><dt>Commentaires</dt><dd><?= e($selected['medical_notes'] ?: '-') ?></dd></div>
                </dl>
            </section>

            <section class="next-appointment">
                <h3>Prochain rendez-vous</h3>
                <?php if (!empty($selected['next_appointment'])): ?>
                    <?php $appointment = $selected['next_appointment']; ?>
                    <div class="appointment-card-mini">
                        <span class="mini-calendar" aria-hidden="true"></span>
                        <div>
                            <strong><?= e(format_date($appointment['starts_at'])) ?></strong>
                            <p><?= e(format_time($appointment['starts_at'])) ?> · <?= e($appointment['type_name'] ?? 'Rendez-vous') ?></p>
                        </div>
                        <a href="#">Voir l’agenda</a>
                    </div>
                <?php else: ?>
                    <div class="appointment-card-mini empty-mini">
                        <span class="mini-calendar" aria-hidden="true"></span>
                        <div>
                            <strong>Aucun rendez-vous prévu</strong>
                            <p>Créez un RDV depuis l’agenda.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <form class="archive-form" method="post" action="<?= e(app_url('/patients/archive')) ?>" data-confirm="Archiver ce patient ?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
                <button class="button button-danger button-full" type="submit">Archiver le patient</button>
            </form>
        <?php else: ?>
            <section class="detail-empty">
                <span class="patient-avatar large">LS</span>
                <h2>Aucun patient sélectionné</h2>
                <p>Ajoutez un patient pour afficher sa fiche complète.</p>
                <button class="button button-primary" type="button" data-modal-open="patient-create">Nouveau patient</button>
            </section>
        <?php endif; ?>
    </aside>
</section>

<?php
$formPatient = $safePatient;
$formAction = app_url('/patients/store');
$formTitle = 'Nouveau patient';
$modalId = 'patient-create';
require app_path('Views/patients/patient-form.php');

if ($selected) {
    $formPatient = array_merge($safePatient, $selected);
    $formAction = app_url('/patients/update');
    $formTitle = 'Modifier le patient';
    $modalId = 'patient-edit';
    require app_path('Views/patients/patient-form.php');
}
?>
