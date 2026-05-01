<?php
$rows = $invoices['data'] ?? [];
$total = (int) ($invoices['total'] ?? 0);
$page = (int) ($invoices['page'] ?? 1);
$pages = (int) ($invoices['pages'] ?? 1);
$perPage = (int) ($invoices['per_page'] ?? 8);
$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);
$selected = $selectedInvoice;
$currentStatus = $status ?? 'all';
$queryBase = array_filter(['q' => $search ?? '', 'status' => $currentStatus !== 'all' ? $currentStatus : ''], static fn ($value) => $value !== '');
$pageUrl = static function (int $targetPage) use ($queryBase): string {
    return app_url('/billing?' . http_build_query($queryBase + ['page' => $targetPage]));
};
$invoiceUrl = static function (int $invoiceId) use ($queryBase, $page): string {
    return app_url('/billing?' . http_build_query($queryBase + ['page' => $page, 'invoice' => $invoiceId]));
};
$tabUrl = static function (string $targetStatus) use ($search): string {
    return app_url('/billing?' . http_build_query(array_filter(['q' => $search ?? '', 'status' => $targetStatus !== 'all' ? $targetStatus : ''])));
};
$statusLabels = [
    'draft' => 'Brouillon',
    'pending' => 'En attente',
    'paid' => 'Payée',
    'partial' => 'Partielle',
    'overdue' => 'Impayée',
    'cancelled' => 'Annulée',
    'credit_note' => 'Avoir',
];
$statusTabs = [
    'all' => 'Toutes',
    'paid' => 'Payées',
    'pending' => 'En attente',
    'overdue' => 'Impayées',
    'credit_note' => 'Avoirs',
];
$defaultInvoice = [
    'id' => '',
    'patient_id' => '',
    'treatment_plan_id' => '',
    'issue_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'status' => 'pending',
    'paid_amount' => 0,
    'notes' => '',
    'items' => [
        ['description' => '', 'quantity' => 1, 'unit_price' => '', 'total' => 0],
    ],
];
$remaining = static fn (array $invoice): float => max(0, (float) $invoice['total_amount'] - (float) $invoice['paid_amount']);
?>

<section class="billing-stat-grid" aria-label="Résumé de la facturation">
    <article class="billing-stat-card billing-stat-blue">
        <span class="billing-stat-icon bill-doc" aria-hidden="true"></span>
        <div>
            <p>Chiffre d’affaires</p>
            <strong><?= e(format_money($stats['turnover'] ?? 0)) ?></strong>
            <small>Total facturé</small>
        </div>
    </article>
    <article class="billing-stat-card billing-stat-green">
        <span class="billing-stat-icon bill-wallet" aria-hidden="true"></span>
        <div>
            <p>Encaissements</p>
            <strong><?= e(format_money($stats['collected'] ?? 0)) ?></strong>
            <small>Montant déjà payé</small>
        </div>
    </article>
    <article class="billing-stat-card billing-stat-orange">
        <span class="billing-stat-icon bill-clock" aria-hidden="true"></span>
        <div>
            <p>En attente</p>
            <strong><?= e(format_money($stats['pending_amount'] ?? 0)) ?></strong>
            <small><?= e((string) ($stats['pending_count'] ?? 0)) ?> factures</small>
        </div>
    </article>
    <article class="billing-stat-card billing-stat-red">
        <span class="billing-stat-icon bill-x" aria-hidden="true"></span>
        <div>
            <p>Impayées</p>
            <strong><?= e(format_money($stats['overdue_amount'] ?? 0)) ?></strong>
            <small><?= e((string) ($stats['overdue_count'] ?? 0)) ?> factures</small>
        </div>
    </article>
</section>

<section class="billing-workspace">
    <article class="billing-table-card">
        <header class="billing-toolbar">
            <nav class="billing-tabs" aria-label="Statut des factures">
                <?php foreach ($statusTabs as $key => $label): ?>
                    <a class="<?= $currentStatus === $key ? 'active' : '' ?>" href="<?= e($tabUrl($key)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <button class="button button-light button-filter" type="button"><span aria-hidden="true"></span> Filtres</button>
            <button class="button button-light button-export" type="button"><span aria-hidden="true"></span> Exporter</button>
        </header>

        <div class="billing-table-wrap">
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Échéance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td class="billing-empty" colspan="7">
                                Aucune facture.
                                <button class="link-button" type="button" data-modal-open="invoice-create">Créer une facture</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $invoice): ?>
                        <?php $isSelected = $selected && (int) $selected['id'] === (int) $invoice['id']; ?>
                        <tr class="<?= $isSelected ? 'is-selected' : '' ?> invoice-row-<?= e($invoice['status']) ?>" data-row-href="<?= e($invoiceUrl((int) $invoice['id'])) ?>">
                            <td><a href="<?= e($invoiceUrl((int) $invoice['id'])) ?>"><?= e($invoice['number']) ?></a></td>
                            <td>
                                <div class="billing-patient-cell">
                                    <span class="patient-avatar"><?= e(initials($invoice)) ?></span>
                                    <span>
                                        <strong><?= e(full_name($invoice)) ?></strong>
                                        <small><?= e($invoice['patient_reference']) ?></small>
                                    </span>
                                </div>
                            </td>
                            <td><?= e(format_date($invoice['issue_date'])) ?></td>
                            <td><?= e(format_money($invoice['total_amount'])) ?></td>
                            <td><span class="invoice-status invoice-status-<?= e($invoice['status']) ?>"><?= e($statusLabels[$invoice['status']] ?? $invoice['status']) ?></span></td>
                            <td><?= e(format_date($invoice['due_date'])) ?></td>
                            <td>
                                <a class="table-eye" href="<?= e($invoiceUrl((int) $invoice['id'])) ?>" aria-label="Voir"></a>
                                <a class="row-more" href="<?= e($invoiceUrl((int) $invoice['id'])) ?>" aria-label="Détails">⋮</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="billing-pagination">
            <p>Affichage de <?= e((string) $from) ?> à <?= e((string) $to) ?> sur <?= e(number_format($total, 0, ',', ' ')) ?> factures</p>
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

    <aside class="billing-detail-card">
        <?php if ($selected): ?>
            <div class="detail-close" aria-hidden="true">×</div>
            <section class="billing-detail-head">
                <div>
                    <h2><?= e($selected['number']) ?></h2>
                    <span class="invoice-status invoice-status-<?= e($selected['status']) ?>"><?= e($statusLabels[$selected['status']] ?? $selected['status']) ?></span>
                </div>
                <section class="billing-patient-mini">
                    <span class="detail-avatar"><?= e(initials($selected)) ?></span>
                    <div>
                        <strong><?= e(full_name($selected)) ?></strong>
                        <p><?= e($selected['patient_age'] !== null ? $selected['patient_age'] . ' ans' : 'Âge non renseigné') ?> · <?= e($selected['phone'] ?: '-') ?></p>
                        <a href="<?= e(app_url('/patients?patient=' . (int) $selected['patient_id'])) ?>">Voir le patient</a>
                    </div>
                </section>
            </section>

            <section class="billing-detail-section">
                <dl class="billing-detail-list">
                    <div><dt>Date de facture</dt><dd><?= e(format_date($selected['issue_date'])) ?></dd></div>
                    <div><dt>Échéance</dt><dd><?= e(format_date($selected['due_date'])) ?></dd></div>
                    <div><dt>Traitement</dt><dd><?= e($selected['treatment_title'] ?: '-') ?></dd></div>
                    <div><dt>Montant total</dt><dd><?= e(format_money($selected['total_amount'])) ?></dd></div>
                    <div><dt>Déjà payé</dt><dd><?= e(format_money($selected['paid_amount'])) ?></dd></div>
                    <div><dt>Reste à payer</dt><dd class="money-red"><?= e(format_money($remaining($selected))) ?></dd></div>
                </dl>
            </section>

            <section class="billing-items-section">
                <h3>Détail des actes</h3>
                <ul>
                    <?php foreach (($selected['items'] ?? []) as $item): ?>
                        <li>
                            <span><?= e($item['description']) ?><small><?= e(number_format((float) $item['quantity'], 2, ',', ' ')) ?> × <?= e(format_money($item['unit_price'])) ?></small></span>
                            <strong><?= e(format_money($item['total'])) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="billing-total-line">
                    <strong>Total</strong>
                    <strong><?= e(format_money($selected['total_amount'])) ?></strong>
                </div>
            </section>

            <section class="billing-detail-actions">
                <button class="button button-primary" type="button" data-modal-open="invoice-edit">Modifier</button>
                <form method="post" action="<?= e(app_url('/billing/status')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
                    <input type="hidden" name="status" value="paid">
                    <button class="button button-light" type="submit">Marquer payée</button>
                </form>
                <a class="button button-light" href="<?= e(app_url('/billing/pdf?id=' . (int) $selected['id'])) ?>">Télécharger</a>
                <form method="post" action="<?= e(app_url('/billing/status')) ?>" data-confirm="Annuler cette facture ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
                    <input type="hidden" name="status" value="cancelled">
                    <button class="button button-danger" type="submit">Annuler</button>
                </form>
            </section>
        <?php else: ?>
            <section class="detail-empty">
                <span class="patient-avatar large">FA</span>
                <h2>Aucune facture sélectionnée</h2>
                <p>Créez une facture pour afficher son détail.</p>
                <button class="button button-primary" type="button" data-modal-open="invoice-create">Nouvelle facture</button>
            </section>
        <?php endif; ?>
    </aside>
</section>

<?php
$formInvoice = $defaultInvoice;
$formAction = app_url('/billing/store');
$formTitle = 'Nouvelle facture';
$modalId = 'invoice-create';
require app_path('Views/billing/invoice-form.php');

if ($selected) {
    $formInvoice = array_merge($defaultInvoice, $selected);
    $formInvoice['items'] = $selected['items'] ?: $defaultInvoice['items'];
    $formAction = app_url('/billing/update');
    $formTitle = 'Modifier la facture';
    $modalId = 'invoice-edit';
    require app_path('Views/billing/invoice-form.php');
}
?>
