<div class="modal" id="<?= e($modalId) ?>" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <section class="modal-panel treatment-modal-panel" role="dialog" aria-modal="true" aria-labelledby="<?= e($modalId) ?>-title">
        <header class="modal-header">
            <div>
                <h2 id="<?= e($modalId) ?>-title"><?= e($formTitle) ?></h2>
                <p>Plan, étapes et montants du traitement</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer">×</button>
        </header>

        <form class="patient-form treatment-form" method="post" action="<?= e($formAction) ?>">
            <?= csrf_field() ?>
            <?php if (!empty($formTreatment['id'])): ?>
                <input type="hidden" name="id" value="<?= e($formTreatment['id']) ?>">
            <?php endif; ?>

            <div class="form-grid">
                <label>
                    <span>Patient</span>
                    <select name="patient_id" required>
                        <option value="">Sélectionner un patient</option>
                        <?php foreach (($options['patients'] ?? []) as $patient): ?>
                            <option value="<?= e($patient['id']) ?>" <?= (int) $formTreatment['patient_id'] === (int) $patient['id'] ? 'selected' : '' ?>>
                                <?= e(full_name($patient)) ?> · <?= e($patient['reference']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Praticien</span>
                    <select name="practitioner_id" required>
                        <option value="">Sélectionner un praticien</option>
                        <?php foreach (($options['practitioners'] ?? []) as $practitioner): ?>
                            <option value="<?= e($practitioner['id']) ?>" <?= (int) $formTreatment['practitioner_id'] === (int) $practitioner['id'] ? 'selected' : '' ?>>
                                <?= e($practitioner['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="span-2">
                    <span>Titre du traitement</span>
                    <input name="title" value="<?= e($formTreatment['title']) ?>" placeholder="Implant + Couronne" required>
                </label>
                <label>
                    <span>Catégorie</span>
                    <select name="category_id">
                        <option value="">Non renseignée</option>
                        <?php foreach (($options['categories'] ?? []) as $category): ?>
                            <option value="<?= e($category['id']) ?>" <?= (int) $formTreatment['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Statut</span>
                    <select name="status">
                        <option value="pending" <?= $formTreatment['status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="in_progress" <?= $formTreatment['status'] === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                        <option value="suspended" <?= $formTreatment['status'] === 'suspended' ? 'selected' : '' ?>>Suspendu</option>
                        <option value="completed" <?= $formTreatment['status'] === 'completed' ? 'selected' : '' ?>>Terminé</option>
                    </select>
                </label>
                <label>
                    <span>Date de début</span>
                    <input type="date" name="started_at" value="<?= e($formTreatment['started_at']) ?>">
                </label>
                <label>
                    <span>Montant total</span>
                    <input type="number" name="total_amount" min="0" step="100" value="<?= e($formTreatment['total_amount']) ?>">
                </label>
                <label>
                    <span>Avancement manuel</span>
                    <input type="number" name="progress" min="0" max="100" value="<?= e($formTreatment['progress']) ?>">
                </label>
                <label>
                    <span>Date de fin</span>
                    <input type="date" name="completed_at" value="<?= e($formTreatment['completed_at'] ?? '') ?>">
                </label>
            </div>

            <section class="treatment-form-steps">
                <header>
                    <h3>Étapes du traitement</h3>
                    <button class="button button-light" type="button" data-step-add>Ajouter une étape</button>
                </header>
                <div class="treatment-step-rows" data-step-rows>
                    <?php foreach (($formTreatment['steps'] ?? []) as $step): ?>
                        <div class="treatment-step-row">
                            <input name="steps[title][]" value="<?= e($step['title'] ?? '') ?>" placeholder="Étape">
                            <input name="steps[planned_date][]" type="date" value="<?= e($step['planned_date'] ?? '') ?>">
                            <select name="steps[status][]">
                                <option value="pending" <?= ($step['status'] ?? '') === 'pending' ? 'selected' : '' ?>>À venir</option>
                                <option value="completed" <?= ($step['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Terminée</option>
                                <option value="cancelled" <?= ($step['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                            </select>
                            <input name="steps[amount][]" type="number" min="0" step="100" value="<?= e($step['amount'] ?? '') ?>" placeholder="Montant">
                            <input name="steps[description][]" value="<?= e($step['description'] ?? '') ?>" placeholder="Description">
                            <input name="steps[completed_at][]" type="hidden" value="<?= e($step['completed_at'] ?? '') ?>">
                            <button type="button" data-step-remove aria-label="Supprimer">×</button>
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
