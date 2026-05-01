<div class="modal" id="message-create" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <section class="modal-panel message-modal-panel" role="dialog" aria-modal="true" aria-labelledby="message-create-title">
        <header class="modal-header">
            <div>
                <h2 id="message-create-title">Nouveau message</h2>
                <p>Patient, équipe, sujet et contenu du message</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer">×</button>
        </header>

        <form class="patient-form message-form" method="post" action="<?= e(app_url('/messages/store')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label class="span-2">
                    <span>Destinataire</span>
                    <select name="patient_id">
                        <option value="">Équipe interne / cabinet</option>
                        <?php foreach (($options['patients'] ?? []) as $patient): ?>
                            <option value="<?= e($patient['id']) ?>">
                                <?= e(full_name($patient)) ?> · <?= e($patient['reference']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="span-2">
                    <span>Sujet</span>
                    <input type="text" name="subject" value="<?= e($formMessage['subject'] ?? '') ?>" placeholder="Ex : Disponibilités pour détartrage">
                </label>
                <label class="span-2">
                    <span>Message</span>
                    <textarea name="body" rows="6" required placeholder="Écrire votre message..."><?= e($formMessage['body'] ?? '') ?></textarea>
                </label>
            </div>

            <footer class="modal-actions">
                <button class="button button-light" type="button" data-modal-close>Annuler</button>
                <button class="button button-primary" type="submit">Envoyer</button>
            </footer>
        </form>
    </section>
</div>
