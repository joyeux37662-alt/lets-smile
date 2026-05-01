<div class="modal" id="<?= e($modalId) ?>" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <section class="modal-panel appointment-modal-panel" role="dialog" aria-modal="true" aria-labelledby="<?= e($modalId) ?>-title">
        <header class="modal-header">
            <div>
                <h2 id="<?= e($modalId) ?>-title"><?= e($formTitle) ?></h2>
                <p>Planification du rendez-vous au cabinet</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer">×</button>
        </header>

        <form class="patient-form" method="post" action="<?= e($formAction) ?>">
            <?= csrf_field() ?>
            <?php if (!empty($returnTo)): ?>
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <?php endif; ?>
            <?php if (!empty($formAppointment['id'])): ?>
                <input type="hidden" name="id" value="<?= e($formAppointment['id']) ?>">
            <?php endif; ?>

            <div class="form-grid">
                <label>
                    <span>Patient</span>
                    <select name="patient_id" required>
                        <option value="">Sélectionner un patient</option>
                        <?php foreach (($options['patients'] ?? []) as $patient): ?>
                            <option value="<?= e($patient['id']) ?>" <?= (int) $formAppointment['patient_id'] === (int) $patient['id'] ? 'selected' : '' ?>>
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
                            <option value="<?= e($practitioner['id']) ?>" <?= (int) $formAppointment['practitioner_id'] === (int) $practitioner['id'] ? 'selected' : '' ?>>
                                <?= e($practitioner['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Type de soin</span>
                    <select name="appointment_type_id">
                        <option value="">Non renseigné</option>
                        <?php foreach (($options['types'] ?? []) as $type): ?>
                            <option value="<?= e($type['id']) ?>" <?= (int) $formAppointment['appointment_type_id'] === (int) $type['id'] ? 'selected' : '' ?>>
                                <?= e($type['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Salle</span>
                    <select name="room_id">
                        <option value="">Non renseignée</option>
                        <?php foreach (($options['rooms'] ?? []) as $room): ?>
                            <option value="<?= e($room['id']) ?>" <?= (int) $formAppointment['room_id'] === (int) $room['id'] ? 'selected' : '' ?>>
                                <?= e($room['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Date</span>
                    <input type="date" name="date" value="<?= e($formAppointment['date']) ?>" required>
                </label>
                <label>
                    <span>Statut</span>
                    <select name="status">
                        <option value="confirmed" <?= $formAppointment['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmé</option>
                        <option value="pending" <?= $formAppointment['status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="completed" <?= $formAppointment['status'] === 'completed' ? 'selected' : '' ?>>Terminé</option>
                        <option value="cancelled" <?= $formAppointment['status'] === 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                        <option value="missed" <?= $formAppointment['status'] === 'missed' ? 'selected' : '' ?>>Non honoré</option>
                    </select>
                </label>
                <label>
                    <span>Heure de début</span>
                    <input type="time" name="start_time" value="<?= e($formAppointment['start_time']) ?>" required>
                </label>
                <label>
                    <span>Heure de fin</span>
                    <input type="time" name="end_time" value="<?= e($formAppointment['end_time']) ?>">
                </label>
                <label class="span-2">
                    <span>Notes</span>
                    <textarea name="notes" rows="4"><?= e($formAppointment['notes']) ?></textarea>
                </label>
            </div>

            <footer class="modal-actions">
                <button class="button button-light" type="button" data-modal-close>Annuler</button>
                <button class="button button-primary" type="submit">Enregistrer</button>
            </footer>
        </form>
    </section>
</div>
