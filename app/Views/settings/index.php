<?php
$settings = $settings ?? [];
$cabinet = $cabinet ?? [];
$users = $users ?? [];
$roles = $roles ?? [];
$permissionGroups = $permissionGroups ?? [];
$rolePermissions = $rolePermissions ?? [];
$userStats = $userStats ?? [
    'total_users' => count($users),
    'active_users' => 0,
    'blocked_users' => 0,
    'roles' => count($roles),
    'permissions' => 0,
];
$auditLogs = $auditLogs ?? [];
$canManageUsers = (bool) ($canManageUsers ?? false);
$currentUserId = (int) ($currentUserId ?? 0);

$value = static function (string $key, string $default = '') use ($settings): string {
    return (string) ($settings[$key] ?? $default);
};
$checked = static fn (string $key): string => (($settings[$key] ?? '0') === '1') ? 'checked' : '';
$logoUrl = !empty($cabinet['logo_path']) ? app_url((string) $cabinet['logo_path']) : '';
$signatureUrl = $value('signature_path') !== '' ? app_url($value('signature_path')) : '';
$backupAt = $value('last_backup_at') !== '' ? format_date($value('last_backup_at')) . ' a ' . format_time($value('last_backup_at')) : 'Aucune sauvegarde';
$tabs = ['General', 'Cabinet', 'Utilisateurs', 'Rendez-vous', 'Facturation', 'Communications', 'Securite', 'Sauvegarde'];
$advanced = [
    'Champs personnalises',
    'Modeles de documents',
    'Categories de traitements',
    'Motifs d annulation des rendez-vous',
    'Integrations et API',
];
$statusLabels = [
    'active' => 'Actif',
    'inactive' => 'Inactif',
    'blocked' => 'Bloque',
];
$auditLabels = [
    'user.created' => 'Utilisateur cree',
    'user.updated' => 'Utilisateur modifie',
    'user.status_updated' => 'Statut modifie',
    'role.permissions_updated' => 'Permissions modifiees',
    'account.password_updated' => 'Mot de passe modifie',
];
?>

<nav class="settings-tabs" aria-label="Sections des parametres">
    <?php foreach ($tabs as $index => $tab): ?>
        <a class="<?= $index === 0 ? 'active' : '' ?>" href="<?= $tab === 'Utilisateurs' ? '#users' : '#' ?>"><?= e($tab) ?></a>
    <?php endforeach; ?>
</nav>

<section class="settings-grid">
    <article class="settings-card settings-card-wide">
        <header class="settings-card-header">
            <div>
                <span class="settings-card-icon settings-icon-building" aria-hidden="true"></span>
                <h2>Informations du cabinet</h2>
            </div>
            <button class="button button-light settings-edit-button" type="button" data-modal-open="settings-cabinet-edit">Modifier</button>
        </header>
        <dl class="settings-info-list">
            <div><dt>Nom du cabinet</dt><dd><?= e($cabinet['name'] ?? '-') ?></dd></div>
            <div><dt>Adresse</dt><dd><?= e(trim((string) (($cabinet['address'] ?? '') . ' ' . ($cabinet['city'] ?? ''))) ?: '-') ?></dd></div>
            <div><dt>Telephone</dt><dd><?= e($cabinet['phone'] ?? '-') ?></dd></div>
            <div><dt>Email</dt><dd><?= e($cabinet['email'] ?? '-') ?></dd></div>
            <div><dt>NIF / STAT</dt><dd><?= e($value('tax_identifier', '-')) ?></dd></div>
            <div><dt>Site web</dt><dd><?= e($value('website', '-')) ?></dd></div>
        </dl>
    </article>

    <article class="settings-card">
        <header class="settings-card-header">
            <div>
                <span class="settings-card-icon settings-icon-building" aria-hidden="true"></span>
                <h2>Logo et identite visuelle</h2>
            </div>
        </header>
        <section class="settings-logo-row">
            <div class="settings-logo-preview">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= e($logoUrl) ?>" alt="Logo actuel">
                <?php else: ?>
                    <span class="settings-tooth-preview" aria-hidden="true"></span>
                <?php endif; ?>
            </div>
            <div>
                <strong>Logo actuel</strong>
                <small>Format recommande : PNG ou JPG<br>Taille max. : 2 Mo</small>
                <form class="settings-inline-actions" method="post" action="<?= e(app_url('/settings/logo')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <label class="button button-light">
                        Changer le logo
                        <input class="visually-hidden" type="file" name="logo" accept=".png,.jpg,.jpeg" onchange="this.form.submit()">
                    </label>
                </form>
                <form method="post" action="<?= e(app_url('/settings/logo/delete')) ?>" data-confirm="Supprimer le logo ?">
                    <?= csrf_field() ?>
                    <button class="button button-danger-light" type="submit">Supprimer</button>
                </form>
            </div>
        </section>
        <form class="settings-color-form" method="post" action="<?= e(app_url('/settings/identity')) ?>">
            <?= csrf_field() ?>
            <label>
                <span>Couleur principale</span>
                <input type="color" name="primary_color" value="<?= e($value('primary_color', '#2563EB')) ?>">
                <strong><?= e($value('primary_color', '#2563EB')) ?></strong>
            </label>
            <label>
                <span>Couleur secondaire</span>
                <input type="color" name="secondary_color" value="<?= e($value('secondary_color', '#10B981')) ?>">
                <strong><?= e($value('secondary_color', '#10B981')) ?></strong>
            </label>
            <button class="button button-light button-full" type="submit">Enregistrer les couleurs</button>
        </form>
    </article>

    <article class="settings-card">
        <header class="settings-card-header">
            <div>
                <span class="settings-card-icon settings-icon-gear" aria-hidden="true"></span>
                <h2>Preferences generales</h2>
            </div>
        </header>
        <form class="settings-select-list" method="post" action="<?= e(app_url('/settings/preferences')) ?>">
            <?= csrf_field() ?>
            <label><span>Fuseau horaire</span><strong>Indian/Antananarivo</strong></label>
            <label>
                <span>Langue</span>
                <select name="language">
                    <option value="fr" <?= $value('language', 'fr') === 'fr' ? 'selected' : '' ?>>Francais</option>
                    <option value="mg" <?= $value('language') === 'mg' ? 'selected' : '' ?>>Malgache</option>
                </select>
            </label>
            <label>
                <span>Format de date</span>
                <select name="date_format">
                    <option value="d/m/Y" <?= $value('date_format', 'd/m/Y') === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                    <option value="Y-m-d" <?= $value('date_format') === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                </select>
            </label>
            <label>
                <span>Format d heure</span>
                <select name="time_format">
                    <option value="H:i" <?= $value('time_format', 'H:i') === 'H:i' ? 'selected' : '' ?>>24 heures</option>
                    <option value="h:i A" <?= $value('time_format') === 'h:i A' ? 'selected' : '' ?>>12 heures</option>
                </select>
            </label>
            <label>
                <span>Devise</span>
                <select name="currency">
                    <option value="MGA" <?= $value('currency', 'MGA') === 'MGA' ? 'selected' : '' ?>>Ariary malgache (MGA / Ar)</option>
                    <option value="EUR" <?= $value('currency') === 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
                    <option value="USD" <?= $value('currency') === 'USD' ? 'selected' : '' ?>>Dollar (USD)</option>
                </select>
            </label>
            <label><span>Symbole</span><input type="text" name="currency_symbol" value="<?= e($value('currency_symbol', 'Ar')) ?>"></label>
            <label>
                <span>Nombre de decimales</span>
                <select name="decimal_places">
                    <option value="0" <?= $value('decimal_places', '0') === '0' ? 'selected' : '' ?>>0</option>
                    <option value="1" <?= $value('decimal_places') === '1' ? 'selected' : '' ?>>1</option>
                    <option value="2" <?= $value('decimal_places') === '2' ? 'selected' : '' ?>>2</option>
                </select>
            </label>
            <label>
                <span>Theme d interface</span>
                <select name="theme">
                    <option value="light" <?= $value('theme', 'light') === 'light' ? 'selected' : '' ?>>Clair</option>
                    <option value="dark" <?= $value('theme') === 'dark' ? 'selected' : '' ?>>Sombre</option>
                </select>
            </label>
            <button class="button button-light button-full" type="submit">Enregistrer</button>
        </form>
    </article>

    <article class="settings-card">
        <header class="settings-card-header">
            <div>
                <span class="settings-card-icon settings-icon-bell" aria-hidden="true"></span>
                <h2>Notifications</h2>
            </div>
        </header>
        <form class="settings-toggle-list" method="post" action="<?= e(app_url('/settings/notifications')) ?>">
            <?= csrf_field() ?>
            <label><span>Rappels de rendez-vous par email</span><input type="checkbox" name="email_reminders" <?= $checked('email_reminders') ?>><i></i></label>
            <label><span>Rappels de rendez-vous par SMS</span><input type="checkbox" name="sms_reminders" <?= $checked('sms_reminders') ?>><i></i></label>
            <label><span>Notifications de nouveaux patients</span><input type="checkbox" name="new_patient_notifications" <?= $checked('new_patient_notifications') ?>><i></i></label>
            <label><span>Alertes de stocks faibles</span><input type="checkbox" name="low_stock_alerts" <?= $checked('low_stock_alerts') ?>><i></i></label>
            <label><span>Notifications de paiements</span><input type="checkbox" name="payment_notifications" <?= $checked('payment_notifications') ?>><i></i></label>
            <label><span>Notifications systeme</span><input type="checkbox" name="system_notifications" <?= $checked('system_notifications') ?>><i></i></label>
            <button class="button button-light button-full" type="submit">Enregistrer</button>
        </form>
    </article>

    <article class="settings-card">
        <header class="settings-card-header">
            <div>
                <span class="settings-card-icon settings-icon-cloud" aria-hidden="true"></span>
                <h2>Sauvegarde et maintenance</h2>
            </div>
        </header>
        <form class="settings-select-list" method="post" action="<?= e(app_url('/settings/backup')) ?>">
            <?= csrf_field() ?>
            <label><span>Derniere sauvegarde</span><strong><?= e($backupAt) ?></strong></label>
            <label>
                <span>Frequence de sauvegarde</span>
                <select name="backup_frequency">
                    <option value="daily" <?= $value('backup_frequency', 'daily') === 'daily' ? 'selected' : '' ?>>Quotidienne</option>
                    <option value="weekly" <?= $value('backup_frequency') === 'weekly' ? 'selected' : '' ?>>Hebdomadaire</option>
                    <option value="monthly" <?= $value('backup_frequency') === 'monthly' ? 'selected' : '' ?>>Mensuelle</option>
                </select>
            </label>
            <label>
                <span>Conserver les sauvegardes pendant</span>
                <select name="backup_retention_days">
                    <option value="30" <?= $value('backup_retention_days', '30') === '30' ? 'selected' : '' ?>>30 jours</option>
                    <option value="90" <?= $value('backup_retention_days') === '90' ? 'selected' : '' ?>>90 jours</option>
                    <option value="365" <?= $value('backup_retention_days') === '365' ? 'selected' : '' ?>>365 jours</option>
                </select>
            </label>
            <button class="button button-light button-full" type="submit">Enregistrer</button>
        </form>
        <form method="post" action="<?= e(app_url('/settings/backup-now')) ?>">
            <?= csrf_field() ?>
            <button class="button button-primary button-full" type="submit">Sauvegarder maintenant</button>
        </form>
    </article>

    <article class="settings-card">
        <header class="settings-card-header">
            <div>
                <span class="settings-card-icon settings-icon-pen" aria-hidden="true"></span>
                <h2>Signature electronique</h2>
            </div>
        </header>
        <p class="settings-muted">Signature pour les documents et ordonnances</p>
        <div class="settings-signature-box">
            <?php if ($signatureUrl !== ''): ?>
                <img src="<?= e($signatureUrl) ?>" alt="Signature electronique">
            <?php else: ?>
                <span>Sophie Martin</span>
            <?php endif; ?>
        </div>
        <div class="settings-action-row">
            <form method="post" action="<?= e(app_url('/settings/signature')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <label class="button button-light">
                    Modifier la signature
                    <input class="visually-hidden" type="file" name="signature" accept=".png,.jpg,.jpeg" onchange="this.form.submit()">
                </label>
            </form>
            <form method="post" action="<?= e(app_url('/settings/signature/delete')) ?>" data-confirm="Supprimer la signature ?">
                <?= csrf_field() ?>
                <button class="button button-danger-light" type="submit">Supprimer</button>
            </form>
        </div>
    </article>

    <article class="settings-card">
        <header class="settings-card-header">
            <div>
                <span class="settings-card-icon settings-icon-number" aria-hidden="true"></span>
                <h2>Numerotation</h2>
            </div>
        </header>
        <form class="settings-numbering-form" method="post" action="<?= e(app_url('/settings/numbering')) ?>">
            <?= csrf_field() ?>
            <label><span>Prefixe des factures</span><input type="text" name="invoice_prefix" value="<?= e($value('invoice_prefix', 'F-2026-')) ?>"></label>
            <label><span>Prefixe des recus</span><input type="text" name="receipt_prefix" value="<?= e($value('receipt_prefix', 'R-2026-')) ?>"></label>
            <label><span>Prefixe des devis</span><input type="text" name="quote_prefix" value="<?= e($value('quote_prefix', 'D-2026-')) ?>"></label>
            <label><span>Prefixe des factures d avoir</span><input type="text" name="credit_note_prefix" value="<?= e($value('credit_note_prefix', 'A-2026-')) ?>"></label>
            <button class="button button-light button-full" type="submit">Enregistrer</button>
        </form>
    </article>

    <article class="settings-card">
        <header class="settings-card-header">
            <div>
                <span class="settings-card-icon settings-icon-sliders" aria-hidden="true"></span>
                <h2>Parametres avances</h2>
            </div>
        </header>
        <nav class="settings-advanced-list">
            <?php foreach ($advanced as $item): ?>
                <a href="#"><?= e($item) ?><span aria-hidden="true">&rsaquo;</span></a>
            <?php endforeach; ?>
        </nav>
    </article>
</section>

<section class="settings-users-section" id="users">
    <div class="settings-user-stats">
        <article>
            <span>Total utilisateurs</span>
            <strong><?= e((string) $userStats['total_users']) ?></strong>
            <small><?= e((string) $userStats['active_users']) ?> comptes actifs</small>
        </article>
        <article>
            <span>Roles disponibles</span>
            <strong><?= e((string) $userStats['roles']) ?></strong>
            <small>Cabinet, soins, accueil, finance</small>
        </article>
        <article>
            <span>Permissions</span>
            <strong><?= e((string) $userStats['permissions']) ?></strong>
            <small>Acces par module</small>
        </article>
        <article>
            <span>Comptes bloques</span>
            <strong><?= e((string) $userStats['blocked_users']) ?></strong>
            <small>Inactifs ou bloques</small>
        </article>
    </div>

    <div class="settings-users-layout">
        <article class="settings-users-card settings-users-main">
            <header class="settings-card-header">
                <div>
                    <span class="settings-card-icon settings-icon-users" aria-hidden="true"></span>
                    <h2>Utilisateurs du cabinet</h2>
                </div>
                <?php if ($canManageUsers): ?>
                    <div class="settings-users-actions">
                        <button class="button button-light" type="button" data-modal-open="settings-password-edit">Changer mon mot de passe</button>
                        <button class="button button-primary" type="button" data-modal-open="settings-user-create">Nouvel utilisateur</button>
                    </div>
                <?php endif; ?>
            </header>
            <div class="settings-users-table-wrap">
                <table class="settings-users-table">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Telephone</th>
                            <th>Permissions</th>
                            <th>Statut</th>
                            <th>Derniere connexion</th>
                            <?php if ($canManageUsers): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                                $userId = (int) $user['id'];
                                $status = (string) ($user['status'] ?? 'inactive');
                                $isCurrentUser = $userId === $currentUserId;
                            ?>
                            <tr>
                                <td>
                                    <span class="patient-avatar"><?= e($user['full_name'] ? strtoupper(substr((string) $user['full_name'], 0, 1)) : 'U') ?></span>
                                    <span><strong><?= e($user['full_name']) ?></strong><?php if ($isCurrentUser): ?><small>Vous</small><?php endif; ?></span>
                                </td>
                                <td><?= e($user['role_label'] ?: '-') ?></td>
                                <td><?= e($user['email']) ?></td>
                                <td><?= e($user['phone'] ?: '-') ?></td>
                                <td><?= e((string) ($user['permission_count'] ?? 0)) ?></td>
                                <td><span class="settings-status settings-status-<?= e($status) ?>"><?= e($statusLabels[$status] ?? ucfirst($status)) ?></span></td>
                                <td><?= e($user['last_login_at'] ? format_date($user['last_login_at']) . ' a ' . format_time($user['last_login_at']) : '-') ?></td>
                                <?php if ($canManageUsers): ?>
                                    <td class="settings-actions-cell">
                                        <button class="button button-light" type="button" data-modal-open="settings-user-edit-<?= e((string) $userId) ?>">Modifier</button>
                                        <?php if (!$isCurrentUser): ?>
                                            <form method="post" action="<?= e(app_url('/settings/users/status')) ?>" data-confirm="Modifier le statut de cet utilisateur ?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= e((string) $userId) ?>">
                                                <input type="hidden" name="status" value="<?= $status === 'active' ? 'blocked' : 'active' ?>">
                                                <button class="button <?= $status === 'active' ? 'button-danger-light' : 'button-light' ?>" type="submit"><?= $status === 'active' ? 'Bloquer' : 'Activer' ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="settings-users-side">
            <article class="settings-users-card" id="permissions">
                <header class="settings-card-header">
                    <div>
                        <span class="settings-card-icon settings-icon-sliders" aria-hidden="true"></span>
                        <h2>Roles et permissions</h2>
                    </div>
                </header>
                <div class="settings-role-list">
                    <?php foreach ($roles as $role): ?>
                        <?php $roleId = (int) $role['id']; ?>
                        <section class="settings-role-card">
                            <div>
                                <strong><?= e($role['label']) ?></strong>
                                <small><?= e((string) ($role['user_count'] ?? 0)) ?> utilisateur(s) - <?= e((string) ($role['permission_count'] ?? 0)) ?> permission(s)</small>
                            </div>
                            <?php if ($canManageUsers): ?>
                                <button class="button button-light" type="button" data-modal-open="settings-role-permissions-<?= e((string) $roleId) ?>">Configurer</button>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="settings-users-card">
                <header class="settings-card-header">
                    <div>
                        <span class="settings-card-icon settings-icon-bell" aria-hidden="true"></span>
                        <h2>Journal recent</h2>
                    </div>
                </header>
                <ul class="settings-audit-list">
                    <?php if ($auditLogs === []): ?>
                        <li><span>Aucune action recente</span><small>-</small></li>
                    <?php endif; ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <li>
                            <span><?= e($auditLabels[$log['action']] ?? $log['action']) ?></span>
                            <small><?= e(($log['actor_name'] ?: 'Systeme') . ' - ' . format_date($log['created_at']) . ' a ' . format_time($log['created_at'])) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        </aside>
    </div>
</section>

<?php if ($canManageUsers): ?>
    <div class="modal" id="settings-user-create" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <section class="modal-panel settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="settings-user-create-title">
            <header class="modal-header">
                <div>
                    <h2 id="settings-user-create-title">Nouvel utilisateur</h2>
                    <p>Ajoutez un membre de l'equipe et attribuez son role.</p>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Fermer">x</button>
            </header>
            <form class="patient-form" method="post" action="<?= e(app_url('/settings/users/store')) ?>">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <label><span>Nom complet</span><input type="text" name="full_name" required></label>
                    <label><span>Email</span><input type="email" name="email" required></label>
                    <label><span>Telephone</span><input type="text" name="phone"></label>
                    <label>
                        <span>Role</span>
                        <select name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= e((string) $role['id']) ?>"><?= e($role['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Statut</span>
                        <select name="status">
                            <option value="active">Actif</option>
                            <option value="inactive">Inactif</option>
                            <option value="blocked">Bloque</option>
                        </select>
                    </label>
                    <label><span>Mot de passe</span><input type="password" name="password" minlength="8" required></label>
                    <label><span>Confirmation</span><input type="password" name="password_confirmation" minlength="8" required></label>
                </div>
                <footer class="modal-actions">
                    <button class="button button-light" type="button" data-modal-close>Annuler</button>
                    <button class="button button-primary" type="submit">Creer l'utilisateur</button>
                </footer>
            </form>
        </section>
    </div>

    <div class="modal" id="settings-password-edit" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <section class="modal-panel settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="settings-password-title">
            <header class="modal-header">
                <div>
                    <h2 id="settings-password-title">Changer mon mot de passe</h2>
                    <p>Le nouveau mot de passe doit contenir au moins 8 caracteres.</p>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Fermer">x</button>
            </header>
            <form class="patient-form" method="post" action="<?= e(app_url('/settings/account/password')) ?>">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <label><span>Mot de passe actuel</span><input type="password" name="current_password" required></label>
                    <label><span>Nouveau mot de passe</span><input type="password" name="new_password" minlength="8" required></label>
                    <label><span>Confirmation</span><input type="password" name="password_confirmation" minlength="8" required></label>
                </div>
                <footer class="modal-actions">
                    <button class="button button-light" type="button" data-modal-close>Annuler</button>
                    <button class="button button-primary" type="submit">Mettre a jour</button>
                </footer>
            </form>
        </section>
    </div>

    <?php foreach ($users as $user): ?>
        <?php
            $userId = (int) $user['id'];
            $isCurrentUser = $userId === $currentUserId;
        ?>
        <div class="modal" id="settings-user-edit-<?= e((string) $userId) ?>" aria-hidden="true">
            <div class="modal-backdrop" data-modal-close></div>
            <section class="modal-panel settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="settings-user-edit-title-<?= e((string) $userId) ?>">
                <header class="modal-header">
                    <div>
                        <h2 id="settings-user-edit-title-<?= e((string) $userId) ?>">Modifier l'utilisateur</h2>
                        <p><?= e($user['full_name']) ?></p>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Fermer">x</button>
                </header>
                <form class="patient-form" method="post" action="<?= e(app_url('/settings/users/update')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= e((string) $userId) ?>">
                    <div class="form-grid">
                        <label><span>Nom complet</span><input type="text" name="full_name" value="<?= e($user['full_name']) ?>" required></label>
                        <label><span>Email</span><input type="email" name="email" value="<?= e($user['email']) ?>" required></label>
                        <label><span>Telephone</span><input type="text" name="phone" value="<?= e($user['phone'] ?? '') ?>"></label>
                        <label>
                            <span>Role</span>
                            <select name="role_id" <?= $isCurrentUser ? 'disabled' : '' ?> required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= e((string) $role['id']) ?>" <?= (int) $user['role_id'] === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isCurrentUser): ?><input type="hidden" name="role_id" value="<?= e((string) $user['role_id']) ?>"><?php endif; ?>
                        </label>
                        <label>
                            <span>Statut</span>
                            <select name="status" <?= $isCurrentUser ? 'disabled' : '' ?>>
                                <?php foreach ($statusLabels as $statusValue => $statusLabel): ?>
                                    <option value="<?= e($statusValue) ?>" <?= ($user['status'] ?? '') === $statusValue ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isCurrentUser): ?><input type="hidden" name="status" value="active"><?php endif; ?>
                        </label>
                        <label><span>Nouveau mot de passe</span><input type="password" name="password" minlength="8" placeholder="Laisser vide pour conserver"></label>
                        <label><span>Confirmation</span><input type="password" name="password_confirmation" minlength="8"></label>
                    </div>
                    <footer class="modal-actions">
                        <button class="button button-light" type="button" data-modal-close>Annuler</button>
                        <button class="button button-primary" type="submit">Enregistrer</button>
                    </footer>
                </form>
            </section>
        </div>
    <?php endforeach; ?>

    <?php foreach ($roles as $role): ?>
        <?php
            $roleId = (int) $role['id'];
            $selectedPermissions = $rolePermissions[$roleId] ?? [];
            $isSuperAdminRole = ($role['name'] ?? '') === 'super_admin';
        ?>
        <div class="modal" id="settings-role-permissions-<?= e((string) $roleId) ?>" aria-hidden="true">
            <div class="modal-backdrop" data-modal-close></div>
            <section class="modal-panel settings-modal-panel settings-permission-modal" role="dialog" aria-modal="true" aria-labelledby="settings-role-title-<?= e((string) $roleId) ?>">
                <header class="modal-header">
                    <div>
                        <h2 id="settings-role-title-<?= e((string) $roleId) ?>">Permissions - <?= e($role['label']) ?></h2>
                        <p>Controlez les acces module par module.</p>
                    </div>
                    <button class="modal-close" type="button" data-modal-close aria-label="Fermer">x</button>
                </header>
                <form class="patient-form" method="post" action="<?= e(app_url('/settings/roles/permissions')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="role_id" value="<?= e((string) $roleId) ?>">
                    <div class="settings-permission-groups">
                        <?php foreach ($permissionGroups as $module => $group): ?>
                            <fieldset class="settings-permission-group">
                                <legend><?= e($group['label'] ?? ucfirst((string) $module)) ?></legend>
                                <?php foreach ($group['permissions'] as $permission): ?>
                                    <?php
                                        $permissionName = (string) $permission['name'];
                                        $isChecked = array_key_exists($permissionName, $selectedPermissions);
                                    ?>
                                    <label>
                                        <input type="checkbox" name="permissions[]" value="<?= e($permissionName) ?>" <?= $isChecked ? 'checked' : '' ?> <?= $isSuperAdminRole ? 'disabled' : '' ?>>
                                        <span><?= e($permission['label']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                    <footer class="modal-actions">
                        <button class="button button-light" type="button" data-modal-close>Annuler</button>
                        <button class="button button-primary" type="submit">Enregistrer les permissions</button>
                    </footer>
                </form>
            </section>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal" id="settings-cabinet-edit" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <section class="modal-panel settings-modal-panel" role="dialog" aria-modal="true" aria-labelledby="settings-cabinet-title">
        <header class="modal-header">
            <div>
                <h2 id="settings-cabinet-title">Modifier le cabinet</h2>
                <p>Coordonnees, identite administrative et localisation</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer">x</button>
        </header>
        <form class="patient-form" method="post" action="<?= e(app_url('/settings/cabinet')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label><span>Nom du cabinet</span><input type="text" name="name" value="<?= e($cabinet['name'] ?? '') ?>" required></label>
                <label><span>Telephone</span><input type="text" name="phone" value="<?= e($cabinet['phone'] ?? '') ?>"></label>
                <label><span>Email</span><input type="email" name="email" value="<?= e($cabinet['email'] ?? '') ?>"></label>
                <label><span>Site web</span><input type="text" name="website" value="<?= e($value('website')) ?>"></label>
                <label class="span-2"><span>Adresse</span><input type="text" name="address" value="<?= e($cabinet['address'] ?? '') ?>"></label>
                <label><span>Ville</span><input type="text" name="city" value="<?= e($cabinet['city'] ?? 'Antananarivo') ?>"></label>
                <label><span>Pays</span><input type="text" name="country" value="<?= e($cabinet['country'] ?? 'Madagascar') ?>"></label>
                <label><span>NIF / STAT</span><input type="text" name="tax_identifier" value="<?= e($value('tax_identifier')) ?>"></label>
                <label>
                    <span>Fuseau horaire</span>
                    <select name="timezone">
                        <option value="Indian/Antananarivo" <?= ($cabinet['timezone'] ?? '') === 'Indian/Antananarivo' ? 'selected' : '' ?>>Indian/Antananarivo</option>
                    </select>
                </label>
                <label>
                    <span>Devise</span>
                    <select name="currency">
                        <option value="MGA" <?= ($cabinet['currency'] ?? 'MGA') === 'MGA' ? 'selected' : '' ?>>MGA / Ar</option>
                    </select>
                </label>
                <label>
                    <span>Locale</span>
                    <select name="locale">
                        <option value="fr" <?= ($cabinet['locale'] ?? 'fr') === 'fr' ? 'selected' : '' ?>>Francais</option>
                        <option value="mg" <?= ($cabinet['locale'] ?? '') === 'mg' ? 'selected' : '' ?>>Malgache</option>
                    </select>
                </label>
            </div>
            <footer class="modal-actions">
                <button class="button button-light" type="button" data-modal-close>Annuler</button>
                <button class="button button-primary" type="submit">Enregistrer</button>
            </footer>
        </form>
    </section>
</div>
