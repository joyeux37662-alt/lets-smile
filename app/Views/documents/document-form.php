<div class="modal" id="document-upload" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <section class="modal-panel document-modal-panel" role="dialog" aria-modal="true" aria-labelledby="document-upload-title">
        <header class="modal-header">
            <div>
                <h2 id="document-upload-title">Ajouter un document</h2>
                <p>Fichier, catégorie et rattachement au dossier patient</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer">×</button>
        </header>

        <form class="patient-form document-form" method="post" action="<?= e(app_url('/documents/store')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="document-upload-box">
                <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt" required>
                <strong>Déposer ou sélectionner un fichier</strong>
                <span>PDF, image, document Office ou texte - 10 Mo maximum</span>
            </div>

            <div class="form-grid">
                <label class="span-2">
                    <span>Titre du document</span>
                    <input name="title" placeholder="Ex : Devis implant 2026-001">
                </label>
                <label>
                    <span>Catégorie</span>
                    <select name="category_id">
                        <option value="">Aucune</option>
                        <?php foreach (($options['categories'] ?? []) as $category): ?>
                            <option value="<?= e($category['id']) ?>"><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Patient</span>
                    <select name="patient_id">
                        <option value="">Aucun patient</option>
                        <?php foreach (($options['patients'] ?? []) as $patient): ?>
                            <option value="<?= e($patient['id']) ?>"><?= e(full_name($patient)) ?> · <?= e($patient['reference']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Facture liée</span>
                    <select name="invoice_id">
                        <option value="">Aucune facture</option>
                        <?php foreach (($options['invoices'] ?? []) as $invoice): ?>
                            <option value="<?= e($invoice['id']) ?>"><?= e($invoice['number']) ?> · <?= e(full_name($invoice)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Traitement lié</span>
                    <select name="treatment_plan_id">
                        <option value="">Aucun traitement</option>
                        <?php foreach (($options['treatments'] ?? []) as $treatment): ?>
                            <option value="<?= e($treatment['id']) ?>"><?= e($treatment['reference']) ?> · <?= e($treatment['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="span-2">
                    <span>Description</span>
                    <textarea name="description" rows="3"></textarea>
                </label>
            </div>

            <footer class="modal-actions">
                <button class="button button-light" type="button" data-modal-close>Annuler</button>
                <button class="button button-primary" type="submit">Enregistrer</button>
            </footer>
        </form>
    </section>
</div>
