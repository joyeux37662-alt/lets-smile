<div class="modal" id="<?= e($modalId) ?>" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <section class="modal-panel stock-modal-panel" role="dialog" aria-modal="true" aria-labelledby="<?= e($modalId) ?>-title">
        <header class="modal-header">
            <div>
                <h2 id="<?= e($modalId) ?>-title"><?= e($formTitle) ?></h2>
                <p>Produit, catégorie, fournisseur et seuil de stock</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer">×</button>
        </header>

        <form class="patient-form stock-form" method="post" action="<?= e($formAction) ?>">
            <?= csrf_field() ?>
            <?php if (!empty($formProduct['id'])): ?>
                <input type="hidden" name="id" value="<?= e($formProduct['id']) ?>">
            <?php endif; ?>

            <div class="form-grid">
                <label class="span-2">
                    <span>Nom du produit</span>
                    <input name="name" value="<?= e($formProduct['name']) ?>" required>
                </label>
                <label>
                    <span>Catégorie</span>
                    <select name="category_id">
                        <option value="">Aucune</option>
                        <?php foreach (($options['categories'] ?? []) as $category): ?>
                            <option value="<?= e($category['id']) ?>" <?= (int) $formProduct['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Fournisseur</span>
                    <select name="supplier_id">
                        <option value="">Aucun</option>
                        <?php foreach (($options['suppliers'] ?? []) as $supplier): ?>
                            <option value="<?= e($supplier['id']) ?>" <?= (int) $formProduct['supplier_id'] === (int) $supplier['id'] ? 'selected' : '' ?>>
                                <?= e($supplier['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Référence</span>
                    <input name="reference" value="<?= e($formProduct['reference']) ?>" placeholder="Ex : GNT-NIT-100">
                </label>
                <label>
                    <span>Code barre</span>
                    <input name="barcode" value="<?= e($formProduct['barcode']) ?>">
                </label>
                <label>
                    <span>Stock actuel</span>
                    <input type="number" name="quantity" min="0" step="0.01" value="<?= e($formProduct['quantity']) ?>">
                </label>
                <label>
                    <span>Stock minimum</span>
                    <input type="number" name="minimum_quantity" min="0" step="0.01" value="<?= e($formProduct['minimum_quantity']) ?>">
                </label>
                <label>
                    <span>Unité</span>
                    <input name="unit" value="<?= e($formProduct['unit']) ?>" placeholder="boîtes, flacons, pièces...">
                </label>
                <label>
                    <span>Prix unitaire</span>
                    <input type="number" name="unit_price" min="0" step="100" value="<?= e($formProduct['unit_price']) ?>">
                </label>
            </div>

            <footer class="modal-actions">
                <button class="button button-light" type="button" data-modal-close>Annuler</button>
                <button class="button button-primary" type="submit">Enregistrer</button>
            </footer>
        </form>
    </section>
</div>
