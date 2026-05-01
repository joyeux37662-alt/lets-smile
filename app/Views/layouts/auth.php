<?php $pageTitle = ($title ?? 'Connexion') . ' - ' . config('app.name'); ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <?= $content ?>
    </main>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
