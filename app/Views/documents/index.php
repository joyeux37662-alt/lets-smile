<?php
$rows = $documents['data'] ?? [];
$total = (int) ($documents['total'] ?? 0);
$page = (int) ($documents['page'] ?? 1);
$pages = (int) ($documents['pages'] ?? 1);
$perPage = (int) ($documents['per_page'] ?? 8);
$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);
$selected = $selectedDocument;
$currentCategoryId = (int) ($categoryId ?? 0);
$queryBase = array_filter([
    'q' => $search ?? '',
    'category_id' => $currentCategoryId > 0 ? $currentCategoryId : '',
], static fn ($value) => $value !== '' && $value !== 0);
$pageUrl = static function (int $targetPage) use ($queryBase): string {
    return app_url('/documents?' . http_build_query($queryBase + ['page' => $targetPage]));
};
$documentUrl = static function (int $documentId) use ($queryBase, $page): string {
    return app_url('/documents?' . http_build_query($queryBase + ['page' => $page, 'document' => $documentId]));
};
$folderUrl = static function (int $targetCategoryId) use ($search): string {
    return app_url('/documents?' . http_build_query(array_filter([
        'q' => $search ?? '',
        'category_id' => $targetCategoryId > 0 ? $targetCategoryId : '',
    ], static fn ($value) => $value !== '' && $value !== 0)));
};
$formatBytes = static function (int|float|string|null $bytes): string {
    $bytes = (float) ($bytes ?? 0);
    $units = ['o', 'Ko', 'Mo', 'Go'];
    $index = 0;

    while ($bytes >= 1024 && $index < count($units) - 1) {
        $bytes /= 1024;
        $index++;
    }

    $decimals = $index === 0 ? 0 : ($bytes >= 10 ? 1 : 2);

    return number_format($bytes, $decimals, ',', ' ') . ' ' . $units[$index];
};
$documentKind = static function (array $document): array {
    $extension = strtolower(pathinfo((string) ($document['file_name'] ?? ''), PATHINFO_EXTENSION));

    return match ($extension) {
        'pdf' => ['PDF', 'pdf'],
        'jpg', 'jpeg', 'png' => ['Image', 'image'],
        'doc', 'docx' => ['DOCX', 'doc'],
        'xls', 'xlsx' => ['XLS', 'sheet'],
        'txt' => ['TXT', 'text'],
        default => [strtoupper($extension ?: 'FILE'), 'file'],
    };
};
$documentLocation = static function (array $document): string {
    $prefix = !empty($document['patient_id']) ? 'Documents patients' : 'Documents cabinet';
    $category = $document['category_name'] ?: 'Divers';

    return $prefix . ' > ' . $category;
};
$storageLimit = 10 * 1024 * 1024 * 1024;
$usedBytes = (int) ($stats['used_bytes'] ?? 0);
$usedPercent = $storageLimit > 0 ? min(100, round(($usedBytes / $storageLimit) * 100)) : 0;
?>

<section class="document-stat-grid" aria-label="Résumé des documents">
    <article class="document-stat-card document-stat-blue">
        <span class="document-stat-icon doc-folder" aria-hidden="true"></span>
        <div>
            <p>Total des documents</p>
            <strong><?= e(number_format((int) ($stats['total_documents'] ?? 0), 0, ',', ' ')) ?></strong>
            <small>Documents enregistrés</small>
        </div>
    </article>
    <article class="document-stat-card document-stat-green">
        <span class="document-stat-icon doc-patient" aria-hidden="true"></span>
        <div>
            <p>Documents patients</p>
            <strong><?= e(number_format((int) ($stats['patient_documents'] ?? 0), 0, ',', ' ')) ?></strong>
            <small>Dossiers liés aux patients</small>
        </div>
    </article>
    <article class="document-stat-card document-stat-orange">
        <span class="document-stat-icon doc-cabinet" aria-hidden="true"></span>
        <div>
            <p>Documents cabinet</p>
            <strong><?= e(number_format((int) ($stats['cabinet_documents'] ?? 0), 0, ',', ' ')) ?></strong>
            <small>Administration et cabinet</small>
        </div>
    </article>
    <article class="document-stat-card document-stat-purple">
        <span class="document-stat-icon doc-storage" aria-hidden="true"></span>
        <div>
            <p>Espace utilisé</p>
            <strong><?= e($formatBytes($usedBytes)) ?> / 10 Go</strong>
            <span class="document-storage-bar"><span style="width: <?= e((string) $usedPercent) ?>%"></span></span>
            <small><?= e((string) $usedPercent) ?>%</small>
        </div>
    </article>
</section>

<section class="documents-workspace">
    <aside class="document-left-panel">
        <section class="document-folders-card">
            <header>
                <h2>Dossiers</h2>
                <button class="folder-add" type="button" data-modal-open="document-upload" aria-label="Ajouter">+</button>
            </header>
            <nav class="document-folders" aria-label="Dossiers documents">
                <a class="<?= $currentCategoryId === 0 ? 'active' : '' ?>" href="<?= e($folderUrl(0)) ?>">
                    <span class="folder-icon" aria-hidden="true"></span>
                    <strong>Tous les documents</strong>
                    <small><?= e((string) ($stats['total_documents'] ?? 0)) ?></small>
                </a>
                <?php foreach (($categoryCounts ?? []) as $category): ?>
                    <a class="<?= $currentCategoryId === (int) $category['id'] ? 'active' : '' ?>" href="<?= e($folderUrl((int) $category['id'])) ?>">
                        <span class="folder-icon" aria-hidden="true"></span>
                        <strong><?= e($category['name']) ?></strong>
                        <small><?= e((string) $category['total']) ?></small>
                    </a>
                <?php endforeach; ?>
            </nav>
        </section>

        <section class="document-storage-card">
            <h2>Stockage</h2>
            <div class="storage-ring" style="--value: <?= e((string) $usedPercent) ?>">
                <strong><?= e((string) $usedPercent) ?>%</strong>
                <span>utilisé</span>
            </div>
            <dl>
                <div><dt>Utilisé</dt><dd><?= e($formatBytes($usedBytes)) ?></dd></div>
                <div><dt>Disponible</dt><dd><?= e($formatBytes($storageLimit - $usedBytes)) ?></dd></div>
            </dl>
            <button class="button button-light button-full" type="button">Gérer le stockage</button>
        </section>
    </aside>

    <article class="document-table-card">
        <header class="document-toolbar">
            <form class="document-inline-search" method="get" action="<?= e(app_url('/documents')) ?>">
                <input type="hidden" name="category_id" value="<?= e($currentCategoryId > 0 ? $currentCategoryId : '') ?>">
                <input type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Rechercher dans les documents...">
                <button type="submit" aria-label="Rechercher"></button>
            </form>
            <button class="button button-light button-filter" type="button"><span aria-hidden="true"></span> Filtres</button>
            <button class="button button-light" type="button">Plus récents</button>
        </header>

        <div class="document-table-wrap">
            <table class="document-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" aria-label="Tout sélectionner"></th>
                        <th>Nom du document</th>
                        <th>Catégorie</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Taille</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td class="document-empty" colspan="7">
                                Aucun document.
                                <button class="link-button" type="button" data-modal-open="document-upload">Ajouter un document</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $document): ?>
                        <?php [$kindLabel, $kindClass] = $documentKind($document); ?>
                        <?php $isSelected = $selected && (int) $selected['id'] === (int) $document['id']; ?>
                        <tr class="<?= $isSelected ? 'is-selected' : '' ?>" data-row-href="<?= e($documentUrl((int) $document['id'])) ?>">
                            <td><input type="checkbox" aria-label="Sélectionner"></td>
                            <td>
                                <div class="document-name-cell">
                                    <span class="document-mini-icon document-mini-<?= e($kindClass) ?>"><?= e($kindLabel) ?></span>
                                    <span>
                                        <strong><?= e($document['title']) ?></strong>
                                        <small><?= e($document['file_name']) ?></small>
                                    </span>
                                </div>
                            </td>
                            <td><span class="document-category document-category-<?= e($kindClass) ?>"><?= e($document['category_name'] ?: '-') ?></span></td>
                            <td>
                                <?php if ($document['patient_id']): ?>
                                    <div class="document-patient-cell">
                                        <span class="patient-avatar"><?= e(initials($document)) ?></span>
                                        <strong><?= e(full_name($document)) ?></strong>
                                    </div>
                                <?php else: ?>
                                    <span>-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e(format_date($document['created_at'])) ?></strong>
                                <small><?= e(format_time($document['created_at'])) ?></small>
                            </td>
                            <td><?= e($formatBytes($document['file_size'])) ?></td>
                            <td>
                                <a class="table-eye" href="<?= e($documentUrl((int) $document['id'])) ?>" aria-label="Voir"></a>
                                <a class="row-more" href="<?= e($documentUrl((int) $document['id'])) ?>" aria-label="Détails">⋮</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="document-pagination">
            <p>Affichage de <?= e((string) $from) ?> à <?= e((string) $to) ?> sur <?= e(number_format($total, 0, ',', ' ')) ?> documents</p>
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

    <aside class="document-detail-card">
        <?php if ($selected): ?>
            <?php [$kindLabel, $kindClass] = $documentKind($selected); ?>
            <div class="detail-close" aria-hidden="true">×</div>
            <section class="document-detail-head">
                <span class="document-file-preview document-file-<?= e($kindClass) ?>"><?= e($kindLabel) ?></span>
                <h2><?= e($selected['title']) ?></h2>
                <p><?= e($kindLabel) ?> - <?= e($formatBytes($selected['file_size'])) ?></p>
                <a class="button button-primary button-full" href="<?= e(app_url('/documents/download?id=' . (int) $selected['id'])) ?>">Télécharger</a>
                <button class="button button-light button-full" type="button">Partager</button>
            </section>

            <section class="document-detail-section">
                <dl class="document-detail-list">
                    <div><dt>Catégorie</dt><dd><?= e($selected['category_name'] ?: '-') ?></dd></div>
                    <div>
                        <dt>Patient</dt>
                        <dd>
                            <?php if ($selected['patient_id']): ?>
                                <?= e(full_name($selected)) ?>
                                <a href="<?= e(app_url('/patients?patient=' . (int) $selected['patient_id'])) ?>">Voir le profil</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div><dt>Date d’ajout</dt><dd><?= e(format_date($selected['created_at'])) ?> à <?= e(format_time($selected['created_at'])) ?></dd></div>
                    <div><dt>Ajouté par</dt><dd><?= e($selected['uploaded_by_name'] ?: '-') ?></dd></div>
                    <div><dt>Emplacement</dt><dd><?= e($documentLocation($selected)) ?></dd></div>
                    <div><dt>Facture</dt><dd><?= e($selected['invoice_number'] ?: '-') ?></dd></div>
                    <div><dt>Traitement</dt><dd><?= e($selected['treatment_title'] ?: '-') ?></dd></div>
                    <div><dt>Description</dt><dd><?= e($selected['description'] ?: '-') ?></dd></div>
                </dl>
            </section>

            <section class="document-detail-actions">
                <form method="post" action="<?= e(app_url('/documents/delete')) ?>" data-confirm="Supprimer ce document ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($selected['id']) ?>">
                    <button class="button button-danger button-full" type="submit">Supprimer le document</button>
                </form>
            </section>
        <?php else: ?>
            <section class="detail-empty">
                <span class="patient-avatar large">DO</span>
                <h2>Aucun document sélectionné</h2>
                <p>Ajoutez un document pour afficher son détail.</p>
                <button class="button button-primary" type="button" data-modal-open="document-upload">Ajouter un document</button>
            </section>
        <?php endif; ?>
    </aside>
</section>

<?php require app_path('Views/documents/document-form.php'); ?>
