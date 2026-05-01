<div class="modal" id="<?= e($modalId) ?>" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <section class="modal-panel payment-modal-panel" role="dialog" aria-modal="true" aria-labelledby="<?= e($modalId) ?>-title">
        <header class="modal-header">
            <div>
                <h2 id="<?= e($modalId) ?>-title"><?= e($formTitle) ?></h2>
                <p>Facture, patient, montant et moyen de paiement</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer">×</button>
        </header>

        <form class="patient-form payment-form" method="post" action="<?= e($formAction) ?>">
            <?= csrf_field() ?>
            <?php if (!empty($formPayment['id'])): ?>
                <input type="hidden" name="id" value="<?= e($formPayment['id']) ?>">
            <?php endif; ?>

            <div class="form-grid">
                <label class="span-2">
                    <span>Facture</span>
                    <select name="invoice_id" data-payment-invoice-select>
                        <option value="">Paiement sans facture</option>
                        <?php foreach (($options['invoices'] ?? []) as $invoice): ?>
                            <option
                                value="<?= e($invoice['id']) ?>"
                                data-patient="<?= e($invoice['patient_id']) ?>"
                                data-remaining="<?= e($invoice['remaining_amount']) ?>"
                                <?= (int) $formPayment['invoice_id'] === (int) $invoice['id'] ? 'selected' : '' ?>
                            >
                                <?= e($invoice['number']) ?> · <?= e(full_name($invoice)) ?> · reste <?= e(format_money($invoice['remaining_amount'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Patient</span>
                    <select name="patient_id" required data-payment-patient-select>
                        <option value="">Sélectionner un patient</option>
                        <?php foreach (($options['patients'] ?? []) as $patient): ?>
                            <option value="<?= e($patient['id']) ?>" <?= (int) $formPayment['patient_id'] === (int) $patient['id'] ? 'selected' : '' ?>>
                                <?= e(full_name($patient)) ?> · <?= e($patient['reference']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Méthode de paiement</span>
                    <select name="method_id" required>
                        <option value="">Sélectionner</option>
                        <?php foreach (($options['methods'] ?? []) as $method): ?>
                            <option value="<?= e($method['id']) ?>" <?= (int) $formPayment['method_id'] === (int) $method['id'] ? 'selected' : '' ?>>
                                <?= e($method['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Montant</span>
                    <input type="number" name="amount" min="0" step="100" value="<?= e($formPayment['amount']) ?>" required data-payment-amount>
                </label>
                <label>
                    <span>Date et heure</span>
                    <input type="datetime-local" name="paid_at" value="<?= e($formPayment['paid_at']) ?>">
                </label>
                <label>
                    <span>Statut</span>
                    <select name="status">
                        <option value="received" <?= $formPayment['status'] === 'received' ? 'selected' : '' ?>>Reçu</option>
                        <option value="pending" <?= $formPayment['status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="failed" <?= $formPayment['status'] === 'failed' ? 'selected' : '' ?>>Échoué</option>
                        <option value="refunded" <?= $formPayment['status'] === 'refunded' ? 'selected' : '' ?>>Remboursé</option>
                        <option value="cancelled" <?= $formPayment['status'] === 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </label>
                <label class="span-2">
                    <span>Notes</span>
                    <textarea name="notes" rows="3"><?= e($formPayment['notes']) ?></textarea>
                </label>
            </div>

            <footer class="modal-actions">
                <button class="button button-light" type="button" data-modal-close>Annuler</button>
                <button class="button button-primary" type="submit">Enregistrer</button>
            </footer>
        </form>
    </section>
</div>
