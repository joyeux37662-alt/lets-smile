<?php
$rows = $payments['data'] ?? [];
$total = (int) ($payments['total'] ?? 0);
$page = (int) ($payments['page'] ?? 1);
$pages = (int) ($payments['pages'] ?? 1);
$perPage = (int) ($payments['per_page'] ?? 8);
$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);
$selected = $selectedPayment;
$currentStatus = $status ?? 'all';
$queryBase = array_filter(['q' => $search ?? '', 'status' => $currentStatus !== 'all' ? $currentStatus : ''], static fn ($value) => $value !== '');
$pageUrl = static function (int $targetPage) use ($queryBase): string {
    return app_url('/payments?' . http_build_query($queryBase + ['page' => $targetPage]));
};
$paymentUrl = static function (int $paymentId) use ($queryBase, $page): string {
    return app_url('/payments?' . http_build_query($queryBase + ['page' => $page, 'payment' => $paymentId]));
};
$tabUrl = static function (string $targetStatus) use ($search): string {
    return app_url('/payments?' . http_build_query(array_filter(['q' => $search ?? '', 'status' => $targetStatus !== 'all' ? $targetStatus : ''])));
};
$statusLabels = [
    'received' => 'Reçu',
    'pending' => 'En attente',
    'failed' => 'Échoué',
    'refunded' => 'Remboursé',
    'cancelled' => 'Annulé',
];
$statusTabs = [
    'all' => 'Tous',
    'received' => 'Reçus',
    'pending' => 'En attente',
    'failed' => 'Échoués',
    'refunded' => 'Remboursés',
];
$defaultPayment = [
    'id' => '',
    'invoice_id' => '',
    'patient_id' => '',
    'method_id' => '',
    'amount' => '',
    'status' => 'received',
    'paid_at' => date('Y-m-d\TH:i'),
    'notes' => '',
];
$methodClass = static function (?string $name): string {
    $name = strtolower((string) $name);

    if (str_contains($name, 'carte')) {
        return 'card';
    }

    if (str_contains($name, 'esp') || str_contains($name, 'cash')) {
        return 'cash';
    }

    if (str_contains($name, 'virement')) {
        return 'bank';
    }

    if (str_contains($name, 'ch')) {
        return 'check';
    }

    return 'mobile';
};
$displayPaymentAmount = static function (array $payment): float {
    $amount = (float) $payment['amount'];

    return $payment['status'] === 'refunded' ? -abs($amount) : $amount;
};
?>

<section class="payment-stat-grid" aria-label="Résumé des paiements">
    <article class="payment-stat-card payment-stat-blue">
        <span class="payment-stat-icon pay-card" aria-hidden="true"></span>
        <div>
            <p>Encaissements (mois)</p>
            <strong><?= e(format_money($stats['received_amount'] ?? 0)) ?></strong>
            <small>Paiements reçus ce mois</small>
        </div>
    </article>
    <article class="payment-stat-card payment-stat-green">
        <span class="payment-stat-icon pay-cash" aria-hidden="true"></span>
        <div>
            <p>Paiements reçus</p>
            <strong><?= e((string) ($stats['received_count'] ?? 0)) ?></strong>
            <small>Transactions validées</small>
        </div>
    </article>
    <article class="payment-stat-card payment-stat-orange">
        <span class="payment-stat-icon pay-clock" aria-hidden="true"></span>
        <div>
            <p>En attente</p>
            <strong><?= e(format_money($stats['pending_amount'] ?? 0)) ?></strong>
            <small><?= e((string) ($stats['pending_count'] ?? 0)) ?> paiements</small>
        </div>
    </article>
    <article class="payment-stat-card payment-stat-red">
        <span class="payment-stat-icon pay-x" aria-hidden="true"></span>
        <div>
            <p>Échoués / Annulés</p>
            <strong><?= e(format_money($stats['failed_amount'] ?? 0)) ?></strong>
            <small><?= e((string) ($stats['failed_count'] ?? 0)) ?> paiements</small>
        </div>
    </article>
</section>

<section class="payment-workspace">
    <article class="payment-table-card">
        <header class="payment-toolbar">
            <nav class="payment-tabs" aria-label="Statut des paiements">
                <?php foreach ($statusTabs as $key => $label): ?>
                    <a class="<?= $currentStatus === $key ? 'active' : '' ?>" href="<?= e($tabUrl($key)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <button class="button button-light button-filter" type="button"><span aria-hidden="true"></span> Filtres</button>
            <button class="button button-light button-export" type="button"><span aria-hidden="true"></span> Exporter</button>
        </header>

        <div class="payment-table-wrap">
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Facture</th>
                        <th>Montant</th>
                        <th>Méthode de paiement</th>
                        <th>Statut</th>
                        <th>Référence</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td class="payment-empty" colspan="8">
                                Aucun paiement.
                                <button class="link-button" type="button" data-modal-open="payment-create">Créer un paiement</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $payment): ?>
                        <?php $isSelected = $selected && (int) $selected['id'] === (int) $payment['id']; ?>
                        <tr class="<?= $isSelected ? 'is-selected' : '' ?> payment-row-<?= e($payment['status']) ?>" data-row-href="<?= e($paymentUrl((int) $payment['id'])) ?>">
                            <td>
                                <strong><?= e(format_date($payment['paid_at'])) ?></strong>
                                <small><?= e(format_time($payment['paid_at'])) ?></small>
                            </td>
                            <td>
                                <div class="payment-patient-cell">
                                    <span class="patient-avatar"><?= e(initials($payment)) ?></span>
                                    <span>
                                        <strong><?= e(full_name($payment)) ?></strong>
                                        <small><?= e($payment['patient_age'] !== null ? $payment['patient_age'] . ' ans' : $payment['patient_reference']) ?></small>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if ($payment['invoice_id']): ?>
                                    <a href="<?= e(app_url('/billing?invoice=' . (int) $payment['invoice_id'])) ?>"><?= e($payment['invoice_number']) ?></a>
                                <?php else: ?>
                                    <span>-</span>
                                <?php endif; ?>
                            </td>
                            <td class="<?= $payment['status'] === 'refunded' ? 'payment-negative' : '' ?>"><?= e(format_money($displayPaymentAmount($payment))) ?></td>
                            <td>
                                <span class="payment-method-pill payment-method-<?= e($methodClass($payment['method_name'])) ?>">
                                    <span aria-hidden="true"></span><?= e($payment['method_name']) ?>
                                </span>
                            </td>
                            <td><span class="payment-status payment-status-<?= e($payment['status']) ?>"><?= e($statusLabels[$payment['status']] ?? $payment['status']) ?></span></td>
                            <td><?= e($payment['reference']) ?></td>
                            <td>
                                <a class="table-eye" href="<?= e($paymentUrl((int) $payment['id'])) ?>" aria-label="Voir"></a>
                                <a class="row-more" href="<?= e($paymentUrl((int) $payment['id'])) ?>" aria-label="Détails">⋮</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="payment-pagination">
            <p>Affichage de <?= e((string) $from) ?> à <?= e((string) $to) ?> sur <?= e(number_format($total, 0, ',', ' ')) ?> paiements</p>
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

    <aside class="payment-detail-card">
        <?php if ($selected): ?>
            <div class="detail-close" aria-hidden="true">×</div>
            <section class="payment-detail-head">
                <span class="payment-status payment-status-<?= e($selected['status']) ?>"><?= e($statusLabels[$selected['status']] ?? $selected['status']) ?></span>
                <h2 class="<?= $selected['status'] === 'refunded' ? 'payment-negative' : '' ?>"><?= e(format_money($displayPaymentAmount($selected))) ?></h2>
                <p>Paiement <?= e($selected['status'] === 'received' ? 'reçu' : strtolower($statusLabels[$selected['status']] ?? $selected['status'])) ?> le <?= e(format_date($selected['paid_at'])) ?> à <?= e(format_time($selected['paid_at'])) ?></p>
            </section>

            <section class="payment-detail-section">
                <dl class="payment-detail-list">
                    <div>
                        <dt>Patient</dt>
                        <dd>
                            <?= e(full_name($selected)) ?>
                            <a href="<?= e(app_url('/patients?patient=' . (int) $selected['patient_id'])) ?>">Voir le profil</a>
                        </dd>
                    </div>
                    <div>
                        <dt>Facture</dt>
                        <dd>
                            <?php if ($selected['invoice_id']): ?>
                                <?= e($selected['invoice_number']) ?>
                                <a href="<?= e(app_url('/billing?invoice=' . (int) $selected['invoice_id'])) ?>">Voir la facture</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div><dt>Méthode</dt><dd><?= e($selected['method_name']) ?></dd></div>
                    <div><dt>Référence</dt><dd><?= e($selected['reference']) ?></dd></div>
                    <div><dt>Notes</dt><dd><?= e($selected['notes'] ?: 'Aucune note') ?></dd></div>
                    <div><dt>Ajouté par</dt><dd><?= e($selected['created_by_name'] ?: '-') ?></dd></div>
                </dl>
            </section>

            <section class="payment-split-section">
                <h3>Répartition du paiement</h3>
                <div><span>Montant payé</span><strong><?= e(format_money($selected['status'] === 'received' ? $selected['amount'] : 0)) ?></strong></div>
                <div><span>Reste à payer</span><strong><?= e($selected['invoice_id'] ? format_money($selected['invoice_remaining']) : '-') ?></strong></div>
                <div class="payment-total"><span>Total facture</span><strong><?= e($selected['invoice_id'] ? format_money($selected['invoice_total']) : '-') ?></strong></div>
            </section>

            <section class="payment-detail-actions">
                <button class="button button-primary" type="button" data-modal-open="payment-edit">Modifier</button>
                <a class="button button-light" href="<?= e(app_url('/payments/receipt?id=' . (int) $selected['id'])) ?>">Imprimer le reçu</a>
                <form method="post" action="<?= e(app_url('/payments/status')) ?>" data-confirm="Marquer ce paiement comme remboursé ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
                    <input type="hidden" name="status" value="refunded">
                    <button class="button button-light" type="submit">Rembourser</button>
                </form>
                <form method="post" action="<?= e(app_url('/payments/status')) ?>" data-confirm="Annuler ce paiement ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
                    <input type="hidden" name="status" value="cancelled">
                    <button class="button button-danger" type="submit">Annuler le paiement</button>
                </form>
            </section>
        <?php else: ?>
            <section class="detail-empty">
                <span class="patient-avatar large">PA</span>
                <h2>Aucun paiement sélectionné</h2>
                <p>Créez un paiement pour afficher son détail.</p>
                <button class="button button-primary" type="button" data-modal-open="payment-create">Nouveau paiement</button>
            </section>
        <?php endif; ?>
    </aside>
</section>

<?php
$formPayment = $defaultPayment;
$formAction = app_url('/payments/store');
$formTitle = 'Nouveau paiement';
$modalId = 'payment-create';
require app_path('Views/payments/payment-form.php');

if ($selected) {
    $formPayment = array_merge($defaultPayment, $selected);
    $formPayment['paid_at'] = str_replace(' ', 'T', substr((string) $formPayment['paid_at'], 0, 16));
    $formAction = app_url('/payments/update');
    $formTitle = 'Modifier le paiement';
    $modalId = 'payment-edit';
    require app_path('Views/payments/payment-form.php');
}
?>
