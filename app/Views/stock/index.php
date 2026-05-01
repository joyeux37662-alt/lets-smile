<?php
$rows = $products['data'] ?? [];
$total = (int) ($products['total'] ?? 0);
$page = (int) ($products['page'] ?? 1);
$pages = (int) ($products['pages'] ?? 1);
$perPage = (int) ($products['per_page'] ?? 8);
$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);
$selected = $selectedProduct;
$currentStatus = $status ?? 'all';
$currentCategoryId = (int) ($categoryId ?? 0);
$queryBase = array_filter([
    'q' => $search ?? '',
    'status' => $currentStatus !== 'all' ? $currentStatus : '',
    'category_id' => $currentCategoryId > 0 ? $currentCategoryId : '',
], static fn ($value) => $value !== '' && $value !== 0);
$pageUrl = static function (int $targetPage) use ($queryBase): string {
    return app_url('/stock?' . http_build_query($queryBase + ['page' => $targetPage]));
};
$productUrl = static function (int $productId) use ($queryBase, $page): string {
    return app_url('/stock?' . http_build_query($queryBase + ['page' => $page, 'product' => $productId]));
};
$statusLabels = [
    'in' => 'En stock',
    'low' => 'Stock faible',
    'out' => 'Rupture',
];
$movementLabels = [
    'in' => 'Entrée de stock',
    'out' => 'Sortie de stock',
    'adjustment' => 'Ajustement',
];
$movementSigns = [
    'in' => '+',
    'out' => '-',
    'adjustment' => '=',
];
$defaultProduct = [
    'id' => '',
    'category_id' => '',
    'supplier_id' => '',
    'name' => '',
    'reference' => '',
    'barcode' => '',
    'quantity' => 0,
    'minimum_quantity' => 0,
    'unit' => 'boîtes',
    'unit_price' => 0,
];
$formatQuantity = static function (float|int|string|null $quantity, ?string $unit = null): string {
    $value = (float) ($quantity ?? 0);
    $formatted = rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');

    return trim($formatted . ' ' . (string) ($unit ?? ''));
};
$productInitial = static function (array $product): string {
    return strtoupper(substr((string) ($product['name'] ?? 'P'), 0, 1));
};
?>

<section class="stock-stat-grid" aria-label="Résumé du stock">
    <article class="stock-stat-card stock-stat-blue">
        <span class="stock-stat-icon stock-box" aria-hidden="true"></span>
        <div>
            <p>Valeur du stock</p>
            <strong><?= e(format_money($stats['stock_value'] ?? 0)) ?></strong>
            <small>Valeur actuelle</small>
        </div>
    </article>
    <article class="stock-stat-card stock-stat-green">
        <span class="stock-stat-icon stock-cart" aria-hidden="true"></span>
        <div>
            <p>Produits en stock</p>
            <strong><?= e((string) ($stats['products_count'] ?? 0)) ?></strong>
            <small>Répartis par catégories</small>
        </div>
    </article>
    <article class="stock-stat-card stock-stat-orange">
        <span class="stock-stat-icon stock-alert" aria-hidden="true"></span>
        <div>
            <p>Stock faible</p>
            <strong><?= e((string) ($stats['low_stock_count'] ?? 0)) ?></strong>
            <small>Produits à réapprovisionner</small>
        </div>
    </article>
    <article class="stock-stat-card stock-stat-red">
        <span class="stock-stat-icon stock-empty" aria-hidden="true"></span>
        <div>
            <p>Rupture de stock</p>
            <strong><?= e((string) ($stats['out_of_stock_count'] ?? 0)) ?></strong>
            <small>Produits indisponibles</small>
        </div>
    </article>
</section>

<section class="stock-workspace">
    <article class="stock-table-card">
        <header class="stock-toolbar">
            <form class="stock-filter-form" method="get" action="<?= e(app_url('/stock')) ?>">
                <input type="hidden" name="q" value="<?= e($search ?? '') ?>">
                <select name="category_id" onchange="this.form.submit()">
                    <option value="">Toutes catégories</option>
                    <?php foreach (($options['categories'] ?? []) as $category): ?>
                        <option value="<?= e($category['id']) ?>" <?= $currentCategoryId === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Tous statuts</option>
                    <option value="in" <?= $currentStatus === 'in' ? 'selected' : '' ?>>En stock</option>
                    <option value="low" <?= $currentStatus === 'low' ? 'selected' : '' ?>>Stock faible</option>
                    <option value="out" <?= $currentStatus === 'out' ? 'selected' : '' ?>>Rupture</option>
                </select>
            </form>
            <button class="button button-light button-filter" type="button"><span aria-hidden="true"></span> Filtres</button>
            <button class="button button-light button-export" type="button"><span aria-hidden="true"></span> Exporter</button>
        </header>

        <div class="stock-table-wrap">
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Catégorie</th>
                        <th>Référence</th>
                        <th>Stock</th>
                        <th>Stock min.</th>
                        <th>Prix unitaire</th>
                        <th>Valeur</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td class="stock-empty-cell" colspan="9">
                                Aucun produit.
                                <button class="link-button" type="button" data-modal-open="product-create">Ajouter un produit</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $product): ?>
                        <?php $isSelected = $selected && (int) $selected['id'] === (int) $product['id']; ?>
                        <tr class="<?= $isSelected ? 'is-selected' : '' ?> stock-row-<?= e($product['stock_status']) ?>" data-row-href="<?= e($productUrl((int) $product['id'])) ?>">
                            <td>
                                <div class="stock-product-cell">
                                    <span class="product-thumb"><?= e($productInitial($product)) ?></span>
                                    <span>
                                        <strong><?= e($product['name']) ?></strong>
                                        <small><?= e($product['unit'] ?: 'unité') ?></small>
                                    </span>
                                </div>
                            </td>
                            <td><?= e($product['category_name'] ?: '-') ?></td>
                            <td><?= e($product['reference'] ?: '-') ?></td>
                            <td class="<?= $product['stock_status'] === 'out' ? 'stock-red-text' : ($product['stock_status'] === 'low' ? 'stock-orange-text' : 'stock-green-text') ?>">
                                <?= e($formatQuantity($product['quantity'], $product['unit'])) ?>
                            </td>
                            <td><?= e($formatQuantity($product['minimum_quantity'], $product['unit'])) ?></td>
                            <td><?= e(format_money($product['unit_price'])) ?></td>
                            <td><?= e(format_money($product['stock_value'])) ?></td>
                            <td><span class="stock-status stock-status-<?= e($product['stock_status']) ?>"><?= e($statusLabels[$product['stock_status']] ?? $product['stock_status']) ?></span></td>
                            <td>
                                <a class="table-eye" href="<?= e($productUrl((int) $product['id'])) ?>" aria-label="Voir"></a>
                                <a class="row-more" href="<?= e($productUrl((int) $product['id'])) ?>" aria-label="Détails">⋮</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="stock-pagination">
            <p>Affichage de <?= e((string) $from) ?> à <?= e((string) $to) ?> sur <?= e(number_format($total, 0, ',', ' ')) ?> produits</p>
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

    <aside class="stock-detail-card">
        <?php if ($selected): ?>
            <div class="detail-close" aria-hidden="true">×</div>
            <section class="stock-detail-head">
                <span class="product-thumb large"><?= e($productInitial($selected)) ?></span>
                <div>
                    <h2><?= e($selected['name']) ?></h2>
                    <span class="stock-status stock-status-<?= e($selected['stock_status']) ?>"><?= e($statusLabels[$selected['stock_status']] ?? $selected['stock_status']) ?></span>
                </div>
            </section>

            <section class="stock-detail-section">
                <dl class="stock-detail-list">
                    <div><dt>Catégorie</dt><dd><?= e($selected['category_name'] ?: '-') ?></dd></div>
                    <div><dt>Référence</dt><dd><?= e($selected['reference'] ?: '-') ?></dd></div>
                    <div><dt>Code barre</dt><dd><?= e($selected['barcode'] ?: '-') ?></dd></div>
                    <div><dt>Fournisseur</dt><dd><?= e($selected['supplier_name'] ?: '-') ?></dd></div>
                    <div><dt>Prix unitaire</dt><dd><?= e(format_money($selected['unit_price'])) ?></dd></div>
                    <div><dt>Stock actuel</dt><dd><?= e($formatQuantity($selected['quantity'], $selected['unit'])) ?></dd></div>
                    <div><dt>Stock minimum</dt><dd><?= e($formatQuantity($selected['minimum_quantity'], $selected['unit'])) ?></dd></div>
                    <div><dt>Valeur du stock</dt><dd><?= e(format_money($selected['stock_value'])) ?></dd></div>
                </dl>
            </section>

            <section class="stock-tabs">
                <a class="active" href="#">Mouvements</a>
                <a href="#">Fournisseur</a>
                <a href="#">Détails</a>
            </section>

            <section class="stock-movement-section">
                <ul>
                    <?php if (($selected['movements'] ?? []) === []): ?>
                        <li class="stock-movement-empty">Aucun mouvement enregistré.</li>
                    <?php endif; ?>
                    <?php foreach (($selected['movements'] ?? []) as $movement): ?>
                        <li>
                            <span>
                                <strong><?= e(format_date($movement['created_at'])) ?></strong>
                                <?= e($movementLabels[$movement['type']] ?? $movement['type']) ?>
                            </span>
                            <span class="stock-movement-qty stock-movement-<?= e($movement['type']) ?>">
                                <?= e($movementSigns[$movement['type']] ?? '') ?><?= e($formatQuantity($movement['quantity'], $selected['unit'])) ?>
                            </span>
                            <small><?= e($movement['user_name'] ?: '-') ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button class="button button-light button-full" type="button" data-modal-open="stock-movement">Voir l’historique complet</button>
            </section>

            <section class="stock-detail-actions">
                <button class="button button-primary" type="button" data-modal-open="product-edit">Modifier le produit</button>
                <button class="button button-light" type="button" data-modal-open="stock-movement">Ajouter un mouvement</button>
            </section>
        <?php else: ?>
            <section class="detail-empty">
                <span class="patient-avatar large">ST</span>
                <h2>Aucun produit sélectionné</h2>
                <p>Ajoutez un produit pour afficher son détail.</p>
                <button class="button button-primary" type="button" data-modal-open="product-create">Ajouter un produit</button>
            </section>
        <?php endif; ?>
    </aside>
</section>

<?php
$formProduct = $defaultProduct;
$formAction = app_url('/stock/store');
$formTitle = 'Ajouter un produit';
$modalId = 'product-create';
require app_path('Views/stock/product-form.php');

if ($selected) {
    $formProduct = array_merge($defaultProduct, $selected);
    $formAction = app_url('/stock/update');
    $formTitle = 'Modifier le produit';
    $modalId = 'product-edit';
    require app_path('Views/stock/product-form.php');

    require app_path('Views/stock/movement-form.php');
}
?>
