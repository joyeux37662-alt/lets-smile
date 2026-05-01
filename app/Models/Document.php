<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class Document
{
    private const MAX_FILE_SIZE = 10485760;
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function stats(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                COUNT(*) AS total_documents,
                SUM(patient_id IS NOT NULL) AS patient_documents,
                SUM(patient_id IS NULL) AS cabinet_documents,
                COALESCE(SUM(file_size), 0) AS used_bytes
            FROM documents
            WHERE cabinet_id = :cabinet_id
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $stats = $statement->fetch() ?: [];

        return [
            'total_documents' => (int) ($stats['total_documents'] ?? 0),
            'patient_documents' => (int) ($stats['patient_documents'] ?? 0),
            'cabinet_documents' => (int) ($stats['cabinet_documents'] ?? 0),
            'used_bytes' => (int) ($stats['used_bytes'] ?? 0),
        ];
    }

    public function categoryCounts(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                dc.id,
                dc.name,
                COUNT(d.id) AS total
            FROM document_categories dc
            LEFT JOIN documents d ON d.category_id = dc.id
                AND d.cabinet_id = dc.cabinet_id
            WHERE dc.cabinet_id = :cabinet_id
            GROUP BY dc.id, dc.name
            ORDER BY dc.id ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    public function paginate(int $cabinetId, string $search, int $categoryId, int $page = 1, int $perPage = 8): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $filter = $this->filterSql($search, $categoryId);

        $count = $this->db->prepare("
            SELECT COUNT(*)
            FROM documents d
            LEFT JOIN patients p ON p.id = d.patient_id
            LEFT JOIN document_categories dc ON dc.id = d.category_id
            LEFT JOIN invoices i ON i.id = d.invoice_id
            LEFT JOIN treatment_plans tp ON tp.id = d.treatment_plan_id
            WHERE d.cabinet_id = :cabinet_id
              {$filter['where']}
        ");
        $count->execute($filter['params'] + ['cabinet_id' => $cabinetId]);
        $total = (int) $count->fetchColumn();

        $statement = $this->db->prepare("
            SELECT
                d.*,
                dc.name AS category_name,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                i.number AS invoice_number,
                tp.reference AS treatment_reference,
                tp.title AS treatment_title,
                u.full_name AS uploaded_by_name
            FROM documents d
            LEFT JOIN document_categories dc ON dc.id = d.category_id
            LEFT JOIN patients p ON p.id = d.patient_id
            LEFT JOIN invoices i ON i.id = d.invoice_id
            LEFT JOIN treatment_plans tp ON tp.id = d.treatment_plan_id
            LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE d.cabinet_id = :cabinet_id
              {$filter['where']}
            ORDER BY d.created_at DESC, d.id DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($filter['params'] + ['cabinet_id' => $cabinetId] as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }

        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'data' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function find(int $cabinetId, int $id): ?array
    {
        $statement = $this->db->prepare("
            SELECT
                d.*,
                dc.name AS category_name,
                p.reference AS patient_reference,
                p.first_name,
                p.last_name,
                p.phone,
                p.email,
                i.number AS invoice_number,
                tp.reference AS treatment_reference,
                tp.title AS treatment_title,
                u.full_name AS uploaded_by_name
            FROM documents d
            LEFT JOIN document_categories dc ON dc.id = d.category_id
            LEFT JOIN patients p ON p.id = d.patient_id
            LEFT JOIN invoices i ON i.id = d.invoice_id
            LEFT JOIN treatment_plans tp ON tp.id = d.treatment_plan_id
            LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE d.cabinet_id = :cabinet_id
              AND d.id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        $document = $statement->fetch();

        return $document ?: null;
    }

    public function firstId(int $cabinetId, string $search, int $categoryId): ?int
    {
        $page = $this->paginate($cabinetId, $search, $categoryId, 1, 1);

        return isset($page['data'][0]['id']) ? (int) $page['data'][0]['id'] : null;
    }

    public function createFromUpload(int $cabinetId, array $data, array $file, ?int $userId): int
    {
        $fileData = $this->validateUpload($file);
        $relativeDirectory = $cabinetId . '/' . date('Y/m');
        $absoluteDirectory = storage_path('documents/' . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory));

        if (!is_dir($absoluteDirectory)) {
            mkdir($absoluteDirectory, 0775, true);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $fileData['extension'];
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($fileData['tmp_name'], $absolutePath)) {
            throw new RuntimeException('Impossible de stocker le fichier.');
        }

        $relativePath = $relativeDirectory . '/' . $storedName;
        $title = trim((string) ($data['title'] ?? ''));
        $title = $title !== '' ? $title : pathinfo($fileData['original_name'], PATHINFO_FILENAME);

        $statement = $this->db->prepare("
            INSERT INTO documents (
                cabinet_id, patient_id, category_id, invoice_id, quote_id,
                treatment_plan_id, uploaded_by, title, file_name, file_path,
                mime_type, file_size, description
            ) VALUES (
                :cabinet_id, :patient_id, :category_id, :invoice_id, NULL,
                :treatment_plan_id, :uploaded_by, :title, :file_name, :file_path,
                :mime_type, :file_size, :description
            )
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'patient_id' => (int) ($data['patient_id'] ?? 0) ?: null,
            'category_id' => (int) ($data['category_id'] ?? 0) ?: null,
            'invoice_id' => (int) ($data['invoice_id'] ?? 0) ?: null,
            'treatment_plan_id' => (int) ($data['treatment_plan_id'] ?? 0) ?: null,
            'uploaded_by' => $userId,
            'title' => $title,
            'file_name' => $fileData['original_name'],
            'file_path' => $relativePath,
            'mime_type' => $fileData['mime_type'],
            'file_size' => $fileData['size'],
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function delete(int $cabinetId, int $id): void
    {
        $document = $this->find($cabinetId, $id);

        if ($document === null) {
            return;
        }

        $statement = $this->db->prepare("
            DELETE FROM documents
            WHERE cabinet_id = :cabinet_id
              AND id = :id
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        $absolutePath = $this->absolutePath((string) $document['file_path']);

        if ($absolutePath !== null && is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }

    public function absolutePath(string $relativePath): ?string
    {
        $base = realpath(storage_path('documents'));

        if ($base === false) {
            return null;
        }

        $path = storage_path('documents/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\')));
        $directory = realpath(dirname($path));

        if ($directory === false || !str_starts_with($directory, $base)) {
            return null;
        }

        return $path;
    }

    public function formOptions(int $cabinetId): array
    {
        return [
            'categories' => $this->categories($cabinetId),
            'patients' => $this->patients($cabinetId),
            'invoices' => $this->invoices($cabinetId),
            'treatments' => $this->treatments($cabinetId),
        ];
    }

    private function validateUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Le fichier est obligatoire.');
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Le fichier dépasse la taille autorisée.');
        }

        $originalName = basename((string) ($file['name'] ?? 'document'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Type de fichier non autorisé.');
        }

        $mimeType = 'application/octet-stream';

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = $finfo ? finfo_file($finfo, (string) $file['tmp_name']) : false;

            if ($finfo) {
                finfo_close($finfo);
            }

            if (is_string($detected) && $detected !== '') {
                $mimeType = $detected;
            }
        }

        return [
            'tmp_name' => (string) $file['tmp_name'],
            'original_name' => $originalName,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $size,
        ];
    }

    private function categories(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, name
            FROM document_categories
            WHERE cabinet_id = :cabinet_id
            ORDER BY id ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function patients(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, reference, first_name, last_name
            FROM patients
            WHERE cabinet_id = :cabinet_id
              AND status != 'archived'
            ORDER BY last_name ASC, first_name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function invoices(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                i.id,
                i.number,
                p.first_name,
                p.last_name
            FROM invoices i
            INNER JOIN patients p ON p.id = i.patient_id
            WHERE i.cabinet_id = :cabinet_id
            ORDER BY i.issue_date DESC, i.id DESC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function treatments(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                tp.id,
                tp.reference,
                tp.title,
                p.first_name,
                p.last_name
            FROM treatment_plans tp
            INNER JOIN patients p ON p.id = tp.patient_id
            WHERE tp.cabinet_id = :cabinet_id
            ORDER BY tp.created_at DESC, tp.id DESC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function filterSql(string $search, int $categoryId): array
    {
        $where = '';
        $params = [];

        if ($search !== '') {
            $where .= "
                AND (
                    d.title LIKE :search
                    OR d.file_name LIKE :search
                    OR dc.name LIKE :search
                    OR p.reference LIKE :search
                    OR p.first_name LIKE :search
                    OR p.last_name LIKE :search
                    OR CONCAT(p.last_name, ' ', p.first_name) LIKE :search
                    OR i.number LIKE :search
                    OR tp.reference LIKE :search
                    OR tp.title LIKE :search
                )
            ";
            $params['search'] = '%' . $search . '%';
        }

        if ($categoryId > 0) {
            $where .= ' AND d.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        return ['where' => $where, 'params' => $params];
    }
}
