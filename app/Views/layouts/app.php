<?php
use App\Core\Auth;

$user = Auth::user();
$cabinet = Auth::cabinet();
$pageTitle = ($title ?? 'Application') . ' - ' . config('app.name');
$currentPath = current_path();
$navigation = [
    ['label' => 'Tableau de bord', 'href' => '/dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
    ['label' => 'Agenda', 'href' => '/agenda', 'icon' => 'calendar', 'permission' => 'agenda.view'],
    ['label' => 'Patients', 'href' => '/patients', 'icon' => 'users', 'permission' => 'patients.view'],
    ['label' => 'Rendez-vous', 'href' => '/appointments', 'icon' => 'clock', 'permission' => 'appointments.view'],
    ['label' => 'Traitements', 'href' => '/treatments', 'icon' => 'tooth', 'permission' => 'treatments.view'],
    ['label' => 'Facturation', 'href' => '/billing', 'icon' => 'invoice', 'permission' => 'billing.view'],
    ['label' => 'Paiements', 'href' => '/payments', 'icon' => 'card', 'permission' => 'payments.view'],
    ['label' => 'Stock', 'href' => '/stock', 'icon' => 'box', 'permission' => 'stock.view'],
    ['label' => 'Documents', 'href' => '/documents', 'icon' => 'file', 'permission' => 'documents.view'],
    ['label' => 'Messages', 'href' => '/messages', 'icon' => 'mail', 'permission' => 'messages.view'],
    ['label' => 'Rapports', 'href' => '/reports', 'icon' => 'chart', 'permission' => 'reports.view'],
    ['label' => 'Paramètres', 'href' => '/settings', 'icon' => 'settings', 'permission' => 'settings.manage'],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-page">
    <aside class="sidebar">
        <a class="brand" href="<?= e(app_url('/dashboard')) ?>" aria-label="Accueil Let’s Smile">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" role="img">
                    <path d="M14.4 6.5c3.2 0 5.1 1.5 7.1 2.6 1.5.9 3.5.9 5 0 2-1.1 3.9-2.6 7.1-2.6 6.3 0 9.7 5.1 8.5 12.7-1 6.5-4.2 15.4-7.5 20.1-1.8 2.6-5.8 2.1-6.5-.9l-1.7-7.2c-.5-2.1-3.3-2.1-3.8 0l-1.7 7.2c-.7 3-4.7 3.5-6.5.9-3.3-4.7-6.5-13.6-7.5-20.1C4.7 11.6 8.1 6.5 14.4 6.5Z" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span>
                <strong>Let’s <span>Smile</span></strong>
                <small>Gestion du cabinet dentaire</small>
            </span>
        </a>

        <nav class="side-nav" aria-label="Navigation principale">
            <?php foreach ($navigation as $item): ?>
                <?php if (!Auth::can($item['permission'])) { continue; } ?>
                <?php $isActive = $item['href'] !== '#' && ($currentPath === $item['href'] || ($item['href'] === '/appointments' && $currentPath === '/rendez-vous') || ($item['href'] === '/treatments' && $currentPath === '/traitements') || ($item['href'] === '/billing' && $currentPath === '/facturation') || ($item['href'] === '/payments' && $currentPath === '/paiements') || ($item['href'] === '/reports' && $currentPath === '/rapports') || ($item['href'] === '/settings' && $currentPath === '/parametres')); ?>
                <a class="<?= $isActive ? 'active' : '' ?>" href="<?= e($item['href'] === '#' ? '#' : app_url($item['href'])) ?>">
                    <span class="nav-icon nav-icon-<?= e($item['icon']) ?>" aria-hidden="true"></span>
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="help-card">
            <strong>Besoin d’aide ?</strong>
            <p>Consultez le support interne ou contactez l’administrateur.</p>
            <a href="#">Centre d’aide</a>
        </section>

        <section class="sidebar-user">
            <span class="avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'LS', 0, 1))) ?></span>
            <span>
                <strong><?= e($user['full_name'] ?? 'Utilisateur') ?></strong>
                <small><?= e($user['role_label'] ?? 'Connecté') ?></small>
            </span>
        </section>
    </aside>

    <div class="app-shell">
        <header class="topbar">
            <div>
                <button class="icon-button menu-button" type="button" data-sidebar-toggle aria-label="Menu"></button>
                <h1><?= e($title ?? 'Let’s Smile') ?></h1>
                <p><?= e($subtitle ?? (($cabinet['name'] ?? 'Cabinet dentaire') . ' · ' . config('app.timezone'))) ?></p>
            </div>
            <div class="topbar-actions">
                <?php if (($topbarSearchVisible ?? true) !== false): ?>
                    <form class="search-form" role="search" method="get" action="<?= e(app_url($topbarSearchAction ?? $currentPath)) ?>">
                        <input type="search" name="q" value="<?= e($topbarSearchValue ?? '') ?>" placeholder="<?= e($topbarSearchPlaceholder ?? 'Rechercher (patient, RDV, facture...)') ?>">
                    </form>
                <?php endif; ?>
                <?= $topbarActionHtml ?? '' ?>
                <form method="post" action="<?= e(app_url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button class="button button-light" type="submit">Déconnexion</button>
                </form>
            </div>
        </header>

        <?php if ($message = flash('success')): ?>
            <div class="alert alert-success"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($message = flash('error')): ?>
            <div class="alert alert-error app-alert"><?= e($message) ?></div>
        <?php endif; ?>

        <main class="content">
            <?= $content ?>
        </main>
    </div>

    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
