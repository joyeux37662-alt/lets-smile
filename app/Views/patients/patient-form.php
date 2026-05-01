<div class="modal" id="<?= e($modalId) ?>" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="<?= e($modalId) ?>-title">
        <header class="modal-header">
            <div>
                <h2 id="<?= e($modalId) ?>-title"><?= e($formTitle) ?></h2>
                <p>Dossier administratif et médical du patient</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer">×</button>
        </header>

        <form class="patient-form" method="post" action="<?= e($formAction) ?>">
            <?= csrf_field() ?>
            <?php if (!empty($formPatient['id'])): ?>
                <input type="hidden" name="id" value="<?= e($formPatient['id']) ?>">
            <?php endif; ?>

            <div class="form-grid">
                <label>
                    <span>Nom</span>
                    <input name="last_name" value="<?= e($formPatient['last_name']) ?>" required>
                </label>
                <label>
                    <span>Prénom</span>
                    <input name="first_name" value="<?= e($formPatient['first_name']) ?>" required>
                </label>
                <label>
                    <span>Genre</span>
                    <select name="gender">
                        <option value="">Non renseigné</option>
                        <option value="female" <?= $formPatient['gender'] === 'female' ? 'selected' : '' ?>>Femme</option>
                        <option value="male" <?= $formPatient['gender'] === 'male' ? 'selected' : '' ?>>Homme</option>
                        <option value="other" <?= $formPatient['gender'] === 'other' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </label>
                <label>
                    <span>Date de naissance</span>
                    <input type="date" name="birth_date" value="<?= e($formPatient['birth_date']) ?>">
                </label>
                <label>
                    <span>Téléphone</span>
                    <input name="phone" value="<?= e($formPatient['phone']) ?>" placeholder="+261 34 00 000 00">
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="<?= e($formPatient['email']) ?>" placeholder="patient@email.com">
                </label>
                <label class="span-2">
                    <span>Adresse</span>
                    <input name="address" value="<?= e($formPatient['address']) ?>">
                </label>
                <label>
                    <span>Profession</span>
                    <input name="profession" value="<?= e($formPatient['profession']) ?>">
                </label>
                <label>
                    <span>Statut</span>
                    <select name="status">
                        <option value="active" <?= $formPatient['status'] === 'active' ? 'selected' : '' ?>>Actif</option>
                        <option value="inactive" <?= $formPatient['status'] === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </label>
                <label>
                    <span>Groupe sanguin</span>
                    <input name="blood_group" value="<?= e($formPatient['blood_group']) ?>" placeholder="A+">
                </label>
                <label>
                    <span>Allergies</span>
                    <input name="allergies" value="<?= e($formPatient['allergies']) ?>" placeholder="Aucune connue">
                </label>
                <label class="span-2">
                    <span>Antécédents médicaux</span>
                    <textarea name="medical_history" rows="3"><?= e($formPatient['medical_history']) ?></textarea>
                </label>
                <label class="span-2">
                    <span>Médicaments actuels</span>
                    <textarea name="current_medications" rows="3"><?= e($formPatient['current_medications']) ?></textarea>
                </label>
                <label class="span-2">
                    <span>Commentaires</span>
                    <textarea name="medical_notes" rows="3"><?= e($formPatient['medical_notes']) ?></textarea>
                </label>
            </div>

            <footer class="modal-actions">
                <button class="button button-light" type="button" data-modal-close>Annuler</button>
                <button class="button button-primary" type="submit">Enregistrer</button>
            </footer>
        </form>
    </section>
</div>
