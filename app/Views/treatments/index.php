<?php
use App\Core\Auth;

$rows = $treatments['data'] ?? [];
$total = (int) ($treatments['total'] ?? 0);
$page = (int) ($treatments['page'] ?? 1);
$pages = (int) ($treatments['pages'] ?? 1);
$perPage = (int) ($treatments['per_page'] ?? 8);
$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);
$selected = $selectedTreatment;
$currentStatus = $status ?? 'all';
$queryBase = array_filter(['q' => $search ?? '', 'status' => $currentStatus !== 'all' ? $currentStatus : ''], static fn ($value) => $value !== '');
$pageUrl = static function (int $targetPage) use ($queryBase): string {
    return app_url('/treatments?' . http_build_query($queryBase + ['page' => $targetPage]));
};
$treatmentUrl = static function (int $treatmentId) use ($queryBase, $page): string {
    return app_url('/treatments?' . http_build_query($queryBase + ['page' => $page, 'treatment' => $treatmentId]));
};
$tabUrl = static function (string $targetStatus) use ($search): string {
    return app_url('/treatments?' . http_build_query(array_filter(['q' => $search ?? '', 'status' => $targetStatus !== 'all' ? $targetStatus : ''])));
};
$statusLabels = [
    'pending' => 'En attente',
    'in_progress' => 'En cours',
    'completed' => 'Terminé',
    'suspended' => 'Suspendu',
    'cancelled' => 'Annulé',
];
$statusTabs = [
    'all' => 'Tous',
    'in_progress' => 'En cours',
    'pending' => 'En attente',
    'completed' => 'Terminés',
    'suspended' => 'Suspendus',
];
$percent = static function (int $value, int $total): string {
    return $total <= 0 ? '0%' : number_format(($value / $total) * 100, 1, ',', ' ') . '%';
};
$defaultTreatment = [
    'id' => '',
    'patient_id' => '',
    'practitioner_id' => Auth::user()['id'] ?? '',
    'category_id' => '',
    'title' => '',
    'status' => 'in_progress',
    'progress' => 0,
    'total_amount' => '',
    'started_at' => date('Y-m-d'),
    'completed_at' => '',
    'steps' => [
        ['title' => '', 'description' => '', 'planned_date' => '', 'completed_at' => '', 'status' => 'pending', 'amount' => ''],
    ],
];
?>

<section class="treatment-stat-grid" aria-label="Résumé des traitements">
    <article class="treatment-stat-card treatment-stat-blue">
        <span class="treatment-stat-icon treat-plan" aria-hidden="true"></span>
        <div>
            <p>Plans de traitement</p>
            <strong><?= e((string) ($stats['total'] ?? 0)) ?></strong>
            <small>Voir tous</small>
        </div>
    </article>
    <article class="treatment-stat-card treatment-stat-green">
        <span class="treatment-stat-icon treat-check" aria-hidden="true"></span>
        <div>
            <p>En cours</p>
            <strong><?= e((string) ($stats['in_progress'] ?? 0)) ?></strong>
            <small><?= e($percent((int) ($stats['in_progress'] ?? 0), max(1, (int) ($stats['total'] ?? 0)))) ?></small>
        </div>
    </article>
    <article class="treatment-stat-card treatment-stat-orange">
        <span class="treatment-stat-icon treat-clock" aria-hidden="true"></span>
        <div>
            <p>En attente</p>
            <strong><?= e((string) ($stats['pending'] ?? 0)) ?></strong>
            <small><?= e($percent((int) ($stats['pending'] ?? 0), max(1, (int) ($stats['total'] ?? 0)))) ?></small>
        </div>
    </article>
    <article class="treatment-stat-card treatment-stat-red">
        <span class="treatment-stat-icon treat-finish" aria-hidden="true"></span>
        <div>
            <p>Terminés</p>
            <strong><?= e((string) ($stats['completed'] ?? 0)) ?></strong>
            <small><?= e($percent((int) ($stats['completed'] ?? 0), max(1, (int) ($stats['total'] ?? 0)))) ?></small>
        </div>
    </article>
</section>

<section class="treatments-workspace">
    <article class="treatments-table-card">
        <header class="treatments-toolbar">
            <nav class="treatment-tabs" aria-label="Statut des traitements">
                <?php foreach ($statusTabs as $key => $label): ?>
                    <a class="<?= $currentStatus === $key ? 'active' : '' ?>" href="<?= e($tabUrl($key)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <button class="button button-light button-filter" type="button"><span aria-hidden="true"></span> Filtres</button>
            <button class="square-action" type="button" aria-label="Options"></button>
        </header>

        <div class="treatments-table-wrap">
            <table class="treatments-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Plan de traitement</th>
                        <th>Statut</th>
                        <th>Avancement</th>
                        <th>Montant total</th>
                        <th>Reste à payer</th>
                        <th>Dernière mise à jour</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td class="treatments-empty" colspan="8">
                                Aucun plan de traitement.
                                <button class="link-button" type="button" data-modal-open="treatment-create">Créer un plan</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $treatment): ?>
                        <?php $isSelected = $selected && (int) $selected['id'] === (int) $treatment['id']; ?>
                        <tr class="<?= $isSelected ? 'is-selected' : '' ?>" data-row-href="<?= e($treatmentUrl((int) $treatment['id'])) ?>">
                            <td>
                                <div class="treatment-patient-cell">
                                    <span class="patient-avatar"><?= e(initials($treatment)) ?></span>
                                    <span>
                                        <a href="<?= e($treatmentUrl((int) $treatment['id'])) ?>"><?= e(full_name($treatment)) ?></a>
                                        <small><?= e($treatment['patient_age'] !== null ? $treatment['patient_age'] . ' ans' : $treatment['patient_reference']) ?></small>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <strong><?= e($treatment['title']) ?></strong>
                                <small><?= e($treatment['reference']) ?></small>
                            </td>
                            <td><span class="treatment-status treatment-status-<?= e($treatment['status']) ?>"><?= e($statusLabels[$treatment['status']] ?? $treatment['status']) ?></span></td>
                            <td>
                                <div class="treatment-progress">
                                    <span><i style="width: <?= e((string) (int) $treatment['progress']) ?>%;"></i></span>
                                    <small><?= e((string) (int) $treatment['progress']) ?>%</small>
                                </div>
                            </td>
                            <td><?= e(format_money($treatment['total_amount'])) ?></td>
                            <td class="money-blue"><?= e(format_money($treatment['remaining_amount'])) ?></td>
                            <td><?= e(format_date($treatment['updated_at'] ?: $treatment['created_at'])) ?></td>
                            <td><a class="row-more" href="<?= e($treatmentUrl((int) $treatment['id'])) ?>" aria-label="Détails">⋮</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="treatments-pagination">
            <p>Affichage de <?= e((string) $from) ?> à <?= e((string) $to) ?> sur <?= e(number_format($total, 0, ',', ' ')) ?> traitements</p>
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

    <aside class="treatment-detail-card">
        <?php if ($selected): ?>
            <div class="detail-close" aria-hidden="true">×</div>
            <section class="treatment-detail-profile">
                <span class="detail-avatar"><?= e(initials($selected)) ?></span>
                <div>
                    <h2><?= e(full_name($selected)) ?></h2>
                    <p><?= e($selected['patient_age'] !== null ? $selected['patient_age'] . ' ans' : 'Âge non renseigné') ?> · <?= e(format_date($selected['birth_date'] ?? null)) ?> · ID #<?= e($selected['patient_reference']) ?></p>
                    <span class="treatment-status treatment-status-<?= e($selected['status']) ?>"><?= e($statusLabels[$selected['status']] ?? $selected['status']) ?></span>
                </div>
            </section>

            <section class="treatment-detail-actions" aria-label="Actions traitement">
                <a href="<?= e(app_url('/patients?patient=' . (int) $selected['patient_id'])) ?>" class="treatment-action"><span class="treatment-action-icon patient"></span>Voir le patient</a>
                <button class="treatment-action" type="button" data-modal-open="treatment-edit"><span class="treatment-action-icon plan"></span>Plan de traitement</button>
                <a class="treatment-action" href="<?= e(app_url('/treatments/quote-pdf?treatment=' . (int) $selected['id'])) ?>"><span class="treatment-action-icon quote"></span>Devis PDF</a>
                <button class="treatment-action is-disabled" type="button" disabled><span class="treatment-action-icon invoice"></span>Facture</button>
                <button class="treatment-action" type="button"><span class="treatment-action-icon folder"></span>Documents</button>
            </section>

            <section class="treatment-detail-section">
                <h3>Détails du traitement</h3>
                <dl class="treatment-detail-list">
                    <div><dt>Plan de traitement</dt><dd><?= e($selected['title']) ?></dd></div>
                    <div><dt>Référence</dt><dd><?= e($selected['reference']) ?></dd></div>
                    <div><dt>Catégorie</dt><dd><?= e($selected['category_name'] ?: '-') ?></dd></div>
                    <div><dt>Date de début</dt><dd><?= e(format_date($selected['started_at'])) ?></dd></div>
                    <div><dt>Praticien</dt><dd><?= e($selected['practitioner_name'] ?: '-') ?></dd></div>
                    <div><dt>Avancement</dt><dd><div class="treatment-progress wide"><span><i style="width: <?= e((string) (int) $selected['progress']) ?>%;"></i></span><small><?= e((string) (int) $selected['progress']) ?>%</small></div></dd></div>
                    <div><dt>Montant total</dt><dd><?= e(format_money($selected['total_amount'])) ?></dd></div>
                    <div><dt>Reste à payer</dt><dd class="money-blue"><?= e(format_money($selected['remaining_amount'])) ?></dd></div>
                    <div><dt>Devis / Factures</dt><dd><?= e((string) ($selected['quotes_count'] ?? 0)) ?> devis · <?= e((string) ($selected['invoices_count'] ?? 0)) ?> facture</dd></div>
                </dl>
            </section>

            <section class="treatment-steps-section">
                <h3>Étapes du traitement</h3>
                <ol class="treatment-steps">
                    <?php foreach (($selected['steps'] ?? []) as $index => $step): ?>
                        <li class="<?= e($step['status']) ?>">
                            <span><?= e($step['status'] === 'completed' ? '✓' : (string) ($index + 1)) ?></span>
                            <div>
                                <strong><?= e($step['title']) ?></strong>
                                <p><?= e($step['planned_date'] ? 'Date prévue : ' . format_date($step['planned_date']) : ($step['description'] ?: 'À venir')) ?></p>
                            </div>
                            <em><?= e($step['status'] === 'completed' ? 'Terminé' : ($step['status'] === 'cancelled' ? 'Annulé' : 'À venir')) ?></em>
                        </li>
                    <?php endforeach; ?>
                    <?php if (($selected['steps'] ?? []) === []): ?>
                        <li class="pending"><span>1</span><div><strong>Aucune étape</strong><p>Ajoutez les étapes du plan.</p></div><em>À venir</em></li>
                    <?php endif; ?>
                </ol>
            </section>

            <section class="treatment-status-actions">
                <button class="button button-light" type="button" data-modal-open="treatment-edit"><span class="button-pencil" aria-hidden="true"></span> Modifier le plan</button>
                <?php if ($selected['status'] !== 'suspended'): ?>
                    <form method="post" action="<?= e(app_url('/treatments/status')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
                        <input type="hidden" name="status" value="suspended">
                        <button class="button button-light" type="submit">Suspendre</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(app_url('/treatments/status')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
                        <input type="hidden" name="status" value="in_progress">
                        <button class="button button-light" type="submit">Reprendre</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= e(app_url('/treatments/status')) ?>" data-confirm="Terminer ce plan de traitement ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
                    <input type="hidden" name="status" value="completed">
                    <button class="button button-primary" type="submit">Terminer le plan</button>
                </form>
            </section>
        <?php else: ?>
            <section class="detail-empty">
                <span class="patient-avatar large">TR</span>
                <h2>Aucun traitement sélectionné</h2>
                <p>Créez un plan pour suivre les étapes et les montants.</p>
                <button class="button button-primary" type="button" data-modal-open="treatment-create">Nouveau traitement</button>
            </section>
        <?php endif; ?>
    </aside>
</section>

<?php
$formTreatment = $defaultTreatment;
$formAction = app_url('/treatments/store');
$formTitle = 'Nouveau traitement';
$modalId = 'treatment-create';
require app_path('Views/treatments/treatment-form.php');

if ($selected) {
    $formTreatment = array_merge($defaultTreatment, $selected);
    $formTreatment['steps'] = $selected['steps'] ?: $defaultTreatment['steps'];
    $formAction = app_url('/treatments/update');
    $formTitle = 'Modifier le traitement';
    $modalId = 'treatment-edit';
    require app_path('Views/treatments/treatment-form.php');
}
?>
