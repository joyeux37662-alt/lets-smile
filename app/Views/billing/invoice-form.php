<div class="modal" id="<?= e($modalId) ?>" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <section class="modal-panel invoice-modal-panel" role="dialog" aria-modal="true" aria-labelledby="<?= e($modalId) ?>-title">
        <header class="modal-header">
            <div>
                <h2 id="<?= e($modalId) ?>-title"><?= e($formTitle) ?></h2>
                <p>Patient, traitement, lignes et règlement</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer">×</button>
        </header>

        <form class="patient-form invoice-form" method="post" action="<?= e($formAction) ?>">
            <?= csrf_field() ?>
            <?php if (!empty($formInvoice['id'])): ?>
                <input type="hidden" name="id" value="<?= e($formInvoice['id']) ?>">
            <?php endif; ?>

            <div class="form-grid">
                <label>
                    <span>Patient</span>
                    <select name="patient_id" required>
                        <option value="">Sélectionner un patient</option>
                        <?php foreach (($options['patients'] ?? []) as $patient): ?>
                            <option value="<?= e($patient['id']) ?>" <?= (int) $formInvoice['patient_id'] === (int) $patient['id'] ? 'selected' : '' ?>>
                                <?= e(full_name($patient)) ?> · <?= e($patient['reference']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Plan de traitement</span>
                    <select name="treatment_plan_id">
                        <option value="">Aucun</option>
                        <?php foreach (($options['treatments'] ?? []) as $treatment): ?>
                            <option value="<?= e($treatment['id']) ?>" <?= (int) $formInvoice['treatment_plan_id'] === (int) $treatment['id'] ? 'selected' : '' ?>>
                                <?= e($treatment['reference']) ?> · <?= e($treatment['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Date de facture</span>
                    <input type="date" name="issue_date" value="<?= e($formInvoice['issue_date']) ?>" required>
                </label>
                <label>
                    <span>Échéance</span>
                    <input type="date" name="due_date" value="<?= e($formInvoice['due_date']) ?>">
                </label>
                <label>
                    <span>Statut</span>
                    <select name="status">
                        <option value="draft" <?= $formInvoice['status'] === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                        <option value="pending" <?= $formInvoice['status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="partial" <?= $formInvoice['status'] === 'partial' ? 'selected' : '' ?>>Partielle</option>
                        <option value="paid" <?= $formInvoice['status'] === 'paid' ? 'selected' : '' ?>>Payée</option>
                        <option value="overdue" <?= $formInvoice['status'] === 'overdue' ? 'selected' : '' ?>>Impayée</option>
                        <option value="credit_note" <?= $formInvoice['status'] === 'credit_note' ? 'selected' : '' ?>>Avoir</option>
                    </select>
                </label>
                <label>
                    <span>Montant payé</span>
                    <input type="number" name="paid_amount" min="0" step="100" value="<?= e($formInvoice['paid_amount']) ?>">
                </label>
                <label class="span-2">
                    <span>Notes</span>
                    <textarea name="notes" rows="3"><?= e($formInvoice['notes']) ?></textarea>
                </label>
            </div>

            <section class="invoice-form-items">
                <header>
                    <h3>Lignes de facture</h3>
                    <button class="button button-light" type="button" data-invoice-item-add>Ajouter une ligne</button>
                </header>
                <div class="invoice-item-rows" data-invoice-item-rows>
                    <?php foreach (($formInvoice['items'] ?? []) as $item): ?>
                        <div class="invoice-item-row">
                            <input name="items[description][]" value="<?= e($item['description'] ?? '') ?>" placeholder="Description">
                            <input name="items[quantity][]" type="number" min="0.01" step="0.01" value="<?= e($item['quantity'] ?? 1) ?>" placeholder="Qté">
                            <input name="items[unit_price][]" type="number" min="0" step="100" value="<?= e($item['unit_price'] ?? '') ?>" placeholder="Prix unitaire">
                            <button type="button" data-invoice-item-remove aria-label="Supprimer">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <footer class="modal-actions">
                <button class="button button-light" type="button" data-modal-close>Annuler</button>
                <button class="button button-primary" type="submit">Enregistrer</button>
            </footer>
        </form>
    </section>
</div>
