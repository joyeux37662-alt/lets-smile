<div class="modal" id="stock-movement" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <section class="modal-panel stock-movement-modal-panel" role="dialog" aria-modal="true" aria-labelledby="stock-movement-title">
        <header class="modal-header">
            <div>
                <h2 id="stock-movement-title">Ajouter un mouvement</h2>
                <p><?= e($selected['name']) ?> · stock actuel <?= e($formatQuantity($selected['quantity'], $selected['unit'])) ?></p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer">×</button>
        </header>

        <form class="patient-form stock-form" method="post" action="<?= e(app_url('/stock/movement')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= e($selected['id']) ?>">

            <div class="form-grid">
                <label>
                    <span>Type de mouvement</span>
                    <select name="type">
                        <option value="in">Entrée de stock</option>
                        <option value="out">Sortie de stock</option>
                        <option value="adjustment">Ajustement</option>
                    </select>
                </label>
                <label>
                    <span>Quantité</span>
                    <input type="number" name="quantity" min="0.01" step="0.01" required>
                </label>
                <label class="span-2">
                    <span>Motif</span>
                    <input name="reason" placeholder="Réapprovisionnement, utilisation clinique, correction...">
                </label>
            </div>

            <footer class="modal-actions">
                <button class="button button-light" type="button" data-modal-close>Annuler</button>
                <button class="button button-primary" type="submit">Enregistrer</button>
            </footer>
        </form>
    </section>
</div>
