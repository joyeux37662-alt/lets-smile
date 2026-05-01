<section class="login-card" aria-labelledby="login-title">
    <div class="login-brand">
        <span class="login-logo" aria-hidden="true">
            <svg viewBox="0 0 48 48" role="img">
                <path d="M14.4 6.5c3.2 0 5.1 1.5 7.1 2.6 1.5.9 3.5.9 5 0 2-1.1 3.9-2.6 7.1-2.6 6.3 0 9.7 5.1 8.5 12.7-1 6.5-4.2 15.4-7.5 20.1-1.8 2.6-5.8 2.1-6.5-.9l-1.7-7.2c-.5-2.1-3.3-2.1-3.8 0l-1.7 7.2c-.7 3-4.7 3.5-6.5.9-3.3-4.7-6.5-13.6-7.5-20.1C4.7 11.6 8.1 6.5 14.4 6.5Z" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        <div>
            <strong>Let’s <span>Smile</span></strong>
            <small>Gestion du cabinet dentaire</small>
        </div>
    </div>

    <div class="login-steps" aria-hidden="true">
        <span class="active"></span>
        <span></span>
        <span></span>
    </div>

    <div class="login-heading">
        <span class="login-building" aria-hidden="true"></span>
        <h1 id="login-title">Connexion au cabinet</h1>
        <p>Saisissez l’identifiant de votre cabinet puis vos accès utilisateur.</p>
    </div>

    <?php if ($message = flash('error')): ?>
        <div class="alert alert-error"><?= e($message) ?></div>
    <?php endif; ?>

    <form class="login-form" method="post" action="<?= e(app_url('/login')) ?>">
        <?= csrf_field() ?>

        <label for="cabinet">Identifiant cabinet</label>
        <input id="cabinet" name="cabinet" type="text" autocomplete="organization" value="<?= e(old('cabinet', 'lets-smile')) ?>" placeholder="ex : lets-smile" required>

        <label for="email">Email utilisateur</label>
        <input id="email" name="email" type="email" autocomplete="email" value="<?= e(old('email', 'admin@letssmile.mg')) ?>" placeholder="admin@letssmile.mg" required>

        <label for="password">Mot de passe</label>
        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="admin123" required>

        <button class="button button-primary button-full" type="submit">
            Continuer
            <span aria-hidden="true">›</span>
        </button>
    </form>
</section>

<p class="auth-footer">Logiciel de gestion clinique sécurisé · Madagascar · MGA / Ar</p>
