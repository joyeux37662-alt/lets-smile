<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Setting;
use App\Models\UserManagement;
use RuntimeException;
use Throwable;

final class SettingController extends Controller
{
    public function index(): void
    {
        $cabinetId = (int) (Auth::cabinet()['id'] ?? 0);
        $search = trim((string) ($_GET['q'] ?? ''));

        try {
            $data = (new Setting())->dashboard($cabinetId);
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Parametres. Verifiez la base MySQL.');
            $data = [
                'cabinet' => Auth::cabinet() ?? [],
                'settings' => [],
                'users' => [],
                'roles' => [],
            ];
        }

        $userManagement = [
            'users' => $data['users'],
            'roles' => $data['roles'],
            'permissions' => [],
            'permissionGroups' => [],
            'rolePermissions' => [],
            'userStats' => [
                'total_users' => count($data['users']),
                'active_users' => count(array_filter($data['users'], static fn (array $user): bool => ($user['status'] ?? '') === 'active')),
                'blocked_users' => count(array_filter($data['users'], static fn (array $user): bool => in_array($user['status'] ?? '', ['blocked', 'inactive'], true))),
                'roles' => count($data['roles']),
                'permissions' => 0,
            ],
            'auditLogs' => [],
        ];

        if (Auth::can('users.manage')) {
            try {
                $userManagement = (new UserManagement())->dashboard($cabinetId);
            } catch (Throwable) {
                flash('error', 'Impossible de charger les utilisateurs et permissions.');
            }
        }

        $this->view('settings/index', [
            'title' => 'Parametres',
            'subtitle' => 'Gerez les parametres de votre cabinet et personnalisez votre experience.',
            'topbarSearchPlaceholder' => 'Rechercher un parametre...',
            'topbarSearchAction' => '/settings',
            'topbarSearchValue' => $search,
            'cabinet' => $data['cabinet'],
            'settings' => $data['settings'],
            'users' => $userManagement['users'],
            'roles' => $userManagement['roles'],
            'permissions' => $userManagement['permissions'],
            'permissionGroups' => $userManagement['permissionGroups'],
            'rolePermissions' => $userManagement['rolePermissions'],
            'userStats' => $userManagement['userStats'],
            'auditLogs' => $userManagement['auditLogs'],
            'canManageUsers' => Auth::can('users.manage'),
            'currentUserId' => (int) (Auth::user()['id'] ?? 0),
            'search' => $search,
        ]);
    }

    public function updateCabinet(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings');
        }

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'country' => trim((string) ($_POST['country'] ?? 'Madagascar')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'timezone' => trim((string) ($_POST['timezone'] ?? 'Indian/Antananarivo')),
            'currency' => trim((string) ($_POST['currency'] ?? 'MGA')),
            'locale' => trim((string) ($_POST['locale'] ?? 'fr')),
            'website' => trim((string) ($_POST['website'] ?? '')),
            'tax_identifier' => trim((string) ($_POST['tax_identifier'] ?? '')),
        ];

        if ($data['name'] === '') {
            flash('error', 'Le nom du cabinet est obligatoire.');
            $this->redirect('/settings');
        }

        try {
            (new Setting())->updateCabinet((int) Auth::cabinet()['id'], $data);
            $_SESSION['cabinet']['name'] = $data['name'];
            flash('success', 'Informations du cabinet mises a jour.');
        } catch (Throwable) {
            flash('error', 'Impossible de modifier les informations du cabinet.');
        }

        $this->redirect('/settings');
    }

    public function updateIdentity(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings');
        }

        $primary = $this->color((string) ($_POST['primary_color'] ?? '#2563EB'), '#2563EB');
        $secondary = $this->color((string) ($_POST['secondary_color'] ?? '#10B981'), '#10B981');

        (new Setting())->saveMany((int) Auth::cabinet()['id'], [
            'primary_color' => $primary,
            'secondary_color' => $secondary,
        ]);
        flash('success', 'Identite visuelle mise a jour.');

        $this->redirect('/settings');
    }

    public function updatePreferences(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings');
        }

        $data = [
            'language' => $this->choice('language', ['fr', 'mg'], 'fr'),
            'date_format' => $this->choice('date_format', ['d/m/Y', 'Y-m-d'], 'd/m/Y'),
            'time_format' => $this->choice('time_format', ['H:i', 'h:i A'], 'H:i'),
            'currency' => $this->choice('currency', ['MGA', 'EUR', 'USD'], 'MGA'),
            'currency_symbol' => trim((string) ($_POST['currency_symbol'] ?? 'Ar')) ?: 'Ar',
            'decimal_places' => (string) max(0, min(2, (int) ($_POST['decimal_places'] ?? 0))),
            'theme' => $this->choice('theme', ['light', 'dark'], 'light'),
        ];

        (new Setting())->saveMany((int) Auth::cabinet()['id'], $data);
        flash('success', 'Preferences generales enregistrees.');
        $this->redirect('/settings');
    }

    public function updateNotifications(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings');
        }

        (new Setting())->saveMany((int) Auth::cabinet()['id'], [
            'email_reminders' => isset($_POST['email_reminders']),
            'sms_reminders' => isset($_POST['sms_reminders']),
            'new_patient_notifications' => isset($_POST['new_patient_notifications']),
            'low_stock_alerts' => isset($_POST['low_stock_alerts']),
            'payment_notifications' => isset($_POST['payment_notifications']),
            'system_notifications' => isset($_POST['system_notifications']),
        ]);
        flash('success', 'Notifications mises a jour.');
        $this->redirect('/settings');
    }

    public function updateBackup(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings');
        }

        (new Setting())->saveMany((int) Auth::cabinet()['id'], [
            'backup_frequency' => $this->choice('backup_frequency', ['daily', 'weekly', 'monthly'], 'daily'),
            'backup_retention_days' => (string) max(7, min(365, (int) ($_POST['backup_retention_days'] ?? 30))),
        ]);
        flash('success', 'Parametres de sauvegarde enregistres.');
        $this->redirect('/settings');
    }

    public function updateNumbering(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings');
        }

        (new Setting())->saveMany((int) Auth::cabinet()['id'], [
            'invoice_prefix' => trim((string) ($_POST['invoice_prefix'] ?? 'F-2026-')),
            'receipt_prefix' => trim((string) ($_POST['receipt_prefix'] ?? 'R-2026-')),
            'quote_prefix' => trim((string) ($_POST['quote_prefix'] ?? 'D-2026-')),
            'credit_note_prefix' => trim((string) ($_POST['credit_note_prefix'] ?? 'A-2026-')),
        ]);
        flash('success', 'Numerotation mise a jour.');
        $this->redirect('/settings');
    }

    public function uploadLogo(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings');
        }

        try {
            $model = new Setting();
            $path = $model->storeUpload((int) Auth::cabinet()['id'], $_FILES['logo'] ?? [], 'logo');
            $model->updateLogo((int) Auth::cabinet()['id'], $path);
            flash('success', 'Logo mis a jour.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage() ?: 'Impossible de changer le logo.');
        }

        $this->redirect('/settings');
    }

    public function deleteLogo(): void
    {
        if ($this->validCsrf()) {
            (new Setting())->deleteLogo((int) Auth::cabinet()['id']);
            flash('success', 'Logo supprime.');
        }

        $this->redirect('/settings');
    }

    public function uploadSignature(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings');
        }

        try {
            $model = new Setting();
            $path = $model->storeUpload((int) Auth::cabinet()['id'], $_FILES['signature'] ?? [], 'signature');
            $model->updateSignature((int) Auth::cabinet()['id'], $path);
            flash('success', 'Signature mise a jour.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage() ?: 'Impossible de changer la signature.');
        }

        $this->redirect('/settings');
    }

    public function deleteSignature(): void
    {
        if ($this->validCsrf()) {
            (new Setting())->deleteSignature((int) Auth::cabinet()['id']);
            flash('success', 'Signature supprimee.');
        }

        $this->redirect('/settings');
    }

    public function backupNow(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings');
        }

        try {
            (new Setting())->createBackup((int) Auth::cabinet()['id']);
            flash('success', 'Sauvegarde creee.');
        } catch (Throwable) {
            flash('error', 'Impossible de creer la sauvegarde.');
        }

        $this->redirect('/settings');
    }

    public function storeUser(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings#users');
        }

        try {
            $data = $this->userPayload(true);
            (new UserManagement())->createUser((int) Auth::cabinet()['id'], $data, (int) Auth::user()['id']);
            flash('success', 'Utilisateur cree avec succes.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage() ?: 'Impossible de creer l utilisateur.');
        }

        $this->redirect('/settings#users');
    }

    public function updateUser(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings#users');
        }

        $userId = (int) ($_POST['user_id'] ?? 0);

        try {
            if ($userId <= 0) {
                throw new RuntimeException('Utilisateur introuvable.');
            }

            $data = $this->userPayload(false);
            $currentUser = Auth::user() ?? [];

            if ($userId === (int) ($currentUser['id'] ?? 0)) {
                $data['role_id'] = (int) ($currentUser['role_id'] ?? $data['role_id']);
                $data['status'] = 'active';
            }

            (new UserManagement())->updateUser((int) Auth::cabinet()['id'], $userId, $data, (int) Auth::user()['id']);

            if ($userId === (int) ($currentUser['id'] ?? 0)) {
                $_SESSION['user']['full_name'] = $data['full_name'];
                $_SESSION['user']['email'] = $data['email'];
            }

            flash('success', 'Utilisateur mis a jour.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage() ?: 'Impossible de modifier l utilisateur.');
        }

        $this->redirect('/settings#users');
    }

    public function updateUserStatus(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings#users');
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $status = $this->choice('status', ['active', 'inactive', 'blocked'], 'active');

        try {
            if ($userId <= 0) {
                throw new RuntimeException('Utilisateur introuvable.');
            }

            if ($userId === (int) (Auth::user()['id'] ?? 0) && $status !== 'active') {
                throw new RuntimeException('Vous ne pouvez pas desactiver votre propre compte.');
            }

            (new UserManagement())->updateStatus((int) Auth::cabinet()['id'], $userId, $status, (int) Auth::user()['id']);
            flash('success', 'Statut utilisateur mis a jour.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage() ?: 'Impossible de modifier le statut.');
        }

        $this->redirect('/settings#users');
    }

    public function updateRolePermissions(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings#permissions');
        }

        $roleId = (int) ($_POST['role_id'] ?? 0);
        $permissions = $_POST['permissions'] ?? [];
        $permissions = is_array($permissions) ? array_map('strval', $permissions) : [];

        try {
            if ($roleId <= 0) {
                throw new RuntimeException('Role introuvable.');
            }

            (new UserManagement())->updateRolePermissions((int) Auth::cabinet()['id'], $roleId, $permissions, (int) Auth::user()['id']);
            flash('success', 'Permissions du role mises a jour.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage() ?: 'Impossible de modifier les permissions.');
        }

        $this->redirect('/settings#permissions');
    }

    public function updateAccountPassword(): void
    {
        if (!$this->validCsrf()) {
            $this->redirect('/settings#users');
        }

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        try {
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('Le nouveau mot de passe doit contenir au moins 8 caracteres.');
            }

            if ($newPassword !== $confirmation) {
                throw new RuntimeException('La confirmation du mot de passe ne correspond pas.');
            }

            (new UserManagement())->changeOwnPassword((int) Auth::cabinet()['id'], (int) Auth::user()['id'], $currentPassword, $newPassword);
            flash('success', 'Mot de passe mis a jour.');
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage() ?: 'Impossible de modifier le mot de passe.');
        }

        $this->redirect('/settings#users');
    }

    private function validCsrf(): bool
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            return false;
        }

        return true;
    }

    private function choice(string $key, array $allowed, string $default): string
    {
        $value = trim((string) ($_POST[$key] ?? $default));

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function color(string $value, string $default): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtoupper($value) : $default;
    }

    private function userPayload(bool $requirePassword): array
    {
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $roleId = (int) ($_POST['role_id'] ?? 0);

        if ($fullName === '') {
            throw new RuntimeException('Le nom complet est obligatoire.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Adresse email invalide.');
        }

        if ($roleId <= 0) {
            throw new RuntimeException('Le role est obligatoire.');
        }

        if ($requirePassword || $password !== '') {
            if (strlen($password) < 8) {
                throw new RuntimeException('Le mot de passe doit contenir au moins 8 caracteres.');
            }

            if ($password !== $confirmation) {
                throw new RuntimeException('La confirmation du mot de passe ne correspond pas.');
            }
        }

        return [
            'role_id' => $roleId,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'password' => $password,
            'status' => $this->choice('status', ['active', 'inactive', 'blocked'], 'active'),
        ];
    }
}
