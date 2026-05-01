<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class Setting
{
    private PDO $db;

    private const DEFAULTS = [
        'primary_color' => '#2563EB',
        'secondary_color' => '#10B981',
        'language' => 'fr',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
        'currency' => 'MGA',
        'currency_symbol' => 'Ar',
        'decimal_places' => '0',
        'theme' => 'light',
        'email_reminders' => '1',
        'sms_reminders' => '1',
        'new_patient_notifications' => '1',
        'low_stock_alerts' => '1',
        'payment_notifications' => '1',
        'system_notifications' => '0',
        'backup_frequency' => 'daily',
        'backup_retention_days' => '30',
        'invoice_prefix' => 'F-2026-',
        'receipt_prefix' => 'R-2026-',
        'quote_prefix' => 'D-2026-',
        'credit_note_prefix' => 'A-2026-',
        'website' => '',
        'tax_identifier' => '',
        'signature_path' => '',
        'last_backup_at' => '',
        'last_backup_file' => '',
    ];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function dashboard(int $cabinetId): array
    {
        return [
            'cabinet' => $this->cabinet($cabinetId),
            'settings' => $this->settings($cabinetId),
            'users' => $this->users($cabinetId),
            'roles' => $this->roles(),
        ];
    }

    public function updateCabinet(int $cabinetId, array $data): void
    {
        $statement = $this->db->prepare("
            UPDATE cabinets
            SET name = :name,
                address = :address,
                city = :city,
                country = :country,
                phone = :phone,
                email = :email,
                timezone = :timezone,
                currency = :currency,
                locale = :locale
            WHERE id = :id
        ");
        $statement->execute([
            'name' => $data['name'],
            'address' => $data['address'] ?: null,
            'city' => $data['city'] ?: null,
            'country' => $data['country'] ?: 'Madagascar',
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'timezone' => $data['timezone'] ?: 'Indian/Antananarivo',
            'currency' => $data['currency'] ?: 'MGA',
            'locale' => $data['locale'] ?: 'fr',
            'id' => $cabinetId,
        ]);

        $this->saveMany($cabinetId, [
            'website' => $data['website'] ?? '',
            'tax_identifier' => $data['tax_identifier'] ?? '',
        ]);
    }

    public function saveMany(int $cabinetId, array $settings): void
    {
        $statement = $this->db->prepare("
            INSERT INTO settings (cabinet_id, setting_key, setting_value)
            VALUES (:cabinet_id, :setting_key, :setting_value)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($settings as $key => $value) {
            $statement->execute([
                'cabinet_id' => $cabinetId,
                'setting_key' => (string) $key,
                'setting_value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            ]);
        }
    }

    public function updateLogo(int $cabinetId, string $relativePath): void
    {
        $statement = $this->db->prepare("
            UPDATE cabinets
            SET logo_path = :logo_path
            WHERE id = :id
        ");
        $statement->execute([
            'logo_path' => $relativePath,
            'id' => $cabinetId,
        ]);
    }

    public function deleteLogo(int $cabinetId): void
    {
        $cabinet = $this->cabinet($cabinetId);

        if (!empty($cabinet['logo_path'])) {
            $this->deletePublicUpload((string) $cabinet['logo_path']);
        }

        $this->updateLogo($cabinetId, '');
    }

    public function updateSignature(int $cabinetId, string $relativePath): void
    {
        $settings = $this->settings($cabinetId);

        if (!empty($settings['signature_path'])) {
            $this->deletePublicUpload((string) $settings['signature_path']);
        }

        $this->saveMany($cabinetId, ['signature_path' => $relativePath]);
    }

    public function deleteSignature(int $cabinetId): void
    {
        $settings = $this->settings($cabinetId);

        if (!empty($settings['signature_path'])) {
            $this->deletePublicUpload((string) $settings['signature_path']);
        }

        $this->saveMany($cabinetId, ['signature_path' => '']);
    }

    public function storeUpload(int $cabinetId, array $file, string $kind): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Le fichier est obligatoire.');
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            throw new RuntimeException('Le fichier dépasse la taille autorisée.');
        }

        $originalName = basename((string) ($file['name'] ?? 'upload'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            throw new RuntimeException('Format non autorisé. Utilisez PNG ou JPG.');
        }

        $directory = public_path('uploads/cabinets/' . $cabinetId);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $fileName = $kind . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
            throw new RuntimeException('Impossible de stocker le fichier.');
        }

        return 'uploads/cabinets/' . $cabinetId . '/' . $fileName;
    }

    public function createBackup(int $cabinetId): string
    {
        $cabinet = $this->cabinet($cabinetId);
        $settings = $this->settings($cabinetId);
        $directory = storage_path('backups');

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $fileName = 'lets-smile-settings-' . date('Ymd-His') . '.json';
        $path = $directory . DIRECTORY_SEPARATOR . $fileName;
        $payload = [
            'created_at' => date('c'),
            'cabinet' => $cabinet,
            'settings' => $settings,
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $this->saveMany($cabinetId, [
            'last_backup_at' => date('Y-m-d H:i:s'),
            'last_backup_file' => 'storage/backups/' . $fileName,
        ]);

        return $path;
    }

    private function cabinet(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT *
            FROM cabinets
            WHERE id = :id
            LIMIT 1
        ");
        $statement->execute(['id' => $cabinetId]);

        return $statement->fetch() ?: [];
    }

    private function settings(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT setting_key, setting_value
            FROM settings
            WHERE cabinet_id = :cabinet_id
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        $settings = self::DEFAULTS;

        foreach ($statement->fetchAll() as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }

        return $settings;
    }

    private function users(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                u.id,
                u.full_name,
                u.email,
                u.phone,
                u.status,
                u.last_login_at,
                r.label AS role_label
            FROM users u
            INNER JOIN user_cabinets uc ON uc.user_id = u.id
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE uc.cabinet_id = :cabinet_id
            ORDER BY u.status = 'active' DESC, u.full_name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function roles(): array
    {
        $statement = $this->db->query("
            SELECT id, name, label
            FROM roles
            ORDER BY id ASC
        ");

        return $statement->fetchAll();
    }

    private function deletePublicUpload(string $relativePath): void
    {
        $base = realpath(public_path('uploads'));

        if ($base === false) {
            return;
        }

        $path = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\')));
        $directory = realpath(dirname($path));

        if ($directory === false || !str_starts_with($directory, $base)) {
            return;
        }

        if (is_file($path)) {
            unlink($path);
        }
    }
}
