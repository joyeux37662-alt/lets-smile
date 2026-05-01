<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Product
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function stats(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                COALESCE(SUM(quantity * unit_price), 0) AS stock_value,
                COUNT(*) AS products_count,
                SUM(quantity > 0 AND quantity <= minimum_quantity) AS low_stock_count,
                SUM(quantity <= 0) AS out_of_stock_count
            FROM products
            WHERE cabinet_id = :cabinet_id
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $stats = $statement->fetch() ?: [];

        return [
            'stock_value' => (float) ($stats['stock_value'] ?? 0),
            'products_count' => (int) ($stats['products_count'] ?? 0),
            'low_stock_count' => (int) ($stats['low_stock_count'] ?? 0),
            'out_of_stock_count' => (int) ($stats['out_of_stock_count'] ?? 0),
        ];
    }

    public function paginate(int $cabinetId, string $search, int $categoryId, string $status, int $page = 1, int $perPage = 8): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $filter = $this->filterSql($search, $categoryId, $status);

        $count = $this->db->prepare("
            SELECT COUNT(*)
            FROM products p
            LEFT JOIN stock_categories sc ON sc.id = p.category_id
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.cabinet_id = :cabinet_id
              {$filter['where']}
        ");
        $count->execute($filter['params'] + ['cabinet_id' => $cabinetId]);
        $total = (int) $count->fetchColumn();

        $statement = $this->db->prepare("
            SELECT
                p.*,
                sc.name AS category_name,
                s.name AS supplier_name,
                (p.quantity * p.unit_price) AS stock_value,
                CASE
                    WHEN p.quantity <= 0 THEN 'out'
                    WHEN p.quantity <= p.minimum_quantity THEN 'low'
                    ELSE 'in'
                END AS stock_status
            FROM products p
            LEFT JOIN stock_categories sc ON sc.id = p.category_id
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.cabinet_id = :cabinet_id
              {$filter['where']}
            ORDER BY
                FIELD(
                    CASE
                        WHEN p.quantity <= 0 THEN 'out'
                        WHEN p.quantity <= p.minimum_quantity THEN 'low'
                        ELSE 'in'
                    END,
                    'out', 'low', 'in'
                ),
                p.name ASC,
                p.id DESC
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
                p.*,
                sc.name AS category_name,
                s.name AS supplier_name,
                s.phone AS supplier_phone,
                s.email AS supplier_email,
                s.address AS supplier_address,
                (p.quantity * p.unit_price) AS stock_value,
                CASE
                    WHEN p.quantity <= 0 THEN 'out'
                    WHEN p.quantity <= p.minimum_quantity THEN 'low'
                    ELSE 'in'
                END AS stock_status
            FROM products p
            LEFT JOIN stock_categories sc ON sc.id = p.category_id
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.cabinet_id = :cabinet_id
              AND p.id = :id
            LIMIT 1
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);

        $product = $statement->fetch();

        if (!$product) {
            return null;
        }

        $product['movements'] = $this->movements($id);

        return $product;
    }

    public function firstId(int $cabinetId, string $search, int $categoryId, string $status): ?int
    {
        $page = $this->paginate($cabinetId, $search, $categoryId, $status, 1, 1);

        return isset($page['data'][0]['id']) ? (int) $page['data'][0]['id'] : null;
    }

    public function create(int $cabinetId, array $data): int
    {
        $data = $this->normalizeProductData($data);

        $statement = $this->db->prepare("
            INSERT INTO products (
                cabinet_id, category_id, supplier_id, name, reference, barcode,
                quantity, minimum_quantity, unit, unit_price, image_path
            ) VALUES (
                :cabinet_id, :category_id, :supplier_id, :name, :reference, :barcode,
                :quantity, :minimum_quantity, :unit, :unit_price, NULL
            )
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'category_id' => $data['category_id'] ?: null,
            'supplier_id' => $data['supplier_id'] ?: null,
            'name' => $data['name'],
            'reference' => $data['reference'] ?: $this->nextReference($cabinetId),
            'barcode' => $data['barcode'] ?: null,
            'quantity' => $data['quantity'],
            'minimum_quantity' => $data['minimum_quantity'],
            'unit' => $data['unit'] ?: null,
            'unit_price' => $data['unit_price'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $cabinetId, int $id, array $data): void
    {
        $data = $this->normalizeProductData($data);

        $statement = $this->db->prepare("
            UPDATE products
            SET category_id = :category_id,
                supplier_id = :supplier_id,
                name = :name,
                reference = :reference,
                barcode = :barcode,
                quantity = :quantity,
                minimum_quantity = :minimum_quantity,
                unit = :unit,
                unit_price = :unit_price
            WHERE cabinet_id = :cabinet_id
              AND id = :id
        ");
        $statement->execute([
            'category_id' => $data['category_id'] ?: null,
            'supplier_id' => $data['supplier_id'] ?: null,
            'name' => $data['name'],
            'reference' => $data['reference'] ?: $this->nextReference($cabinetId),
            'barcode' => $data['barcode'] ?: null,
            'quantity' => $data['quantity'],
            'minimum_quantity' => $data['minimum_quantity'],
            'unit' => $data['unit'] ?: null,
            'unit_price' => $data['unit_price'],
            'cabinet_id' => $cabinetId,
            'id' => $id,
        ]);
    }

    public function addMovement(int $cabinetId, int $productId, array $data, ?int $userId): void
    {
        $movement = $this->normalizeMovementData($data);
        $product = $this->find($cabinetId, $productId);

        if ($product === null) {
            return;
        }

        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare("
                INSERT INTO stock_movements (product_id, user_id, type, quantity, reason)
                VALUES (:product_id, :user_id, :type, :quantity, :reason)
            ");
            $statement->execute([
                'product_id' => $productId,
                'user_id' => $userId,
                'type' => $movement['type'],
                'quantity' => $movement['quantity'],
                'reason' => $movement['reason'] ?: null,
            ]);

            $newQuantity = $this->quantityAfterMovement((float) $product['quantity'], $movement['type'], (float) $movement['quantity']);

            $update = $this->db->prepare("
                UPDATE products
                SET quantity = :quantity
                WHERE cabinet_id = :cabinet_id
                  AND id = :id
            ");
            $update->execute([
                'quantity' => $newQuantity,
                'cabinet_id' => $cabinetId,
                'id' => $productId,
            ]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function formOptions(int $cabinetId): array
    {
        return [
            'categories' => $this->categories($cabinetId),
            'suppliers' => $this->suppliers($cabinetId),
        ];
    }

    private function normalizeProductData(array $data): array
    {
        return [
            'category_id' => (int) ($data['category_id'] ?? 0),
            'supplier_id' => (int) ($data['supplier_id'] ?? 0),
            'name' => trim((string) ($data['name'] ?? '')),
            'reference' => trim((string) ($data['reference'] ?? '')),
            'barcode' => trim((string) ($data['barcode'] ?? '')),
            'quantity' => max(0, (float) ($data['quantity'] ?? 0)),
            'minimum_quantity' => max(0, (float) ($data['minimum_quantity'] ?? 0)),
            'unit' => trim((string) ($data['unit'] ?? '')),
            'unit_price' => max(0, (float) ($data['unit_price'] ?? 0)),
        ];
    }

    private function normalizeMovementData(array $data): array
    {
        $type = trim((string) ($data['type'] ?? 'in'));

        return [
            'type' => in_array($type, ['in', 'out', 'adjustment'], true) ? $type : 'in',
            'quantity' => max(0, (float) ($data['quantity'] ?? 0)),
            'reason' => trim((string) ($data['reason'] ?? '')),
        ];
    }

    private function quantityAfterMovement(float $current, string $type, float $quantity): float
    {
        return match ($type) {
            'out' => max(0, $current - $quantity),
            'adjustment' => max(0, $quantity),
            default => $current + $quantity,
        };
    }

    private function movements(int $productId): array
    {
        $statement = $this->db->prepare("
            SELECT
                sm.*,
                u.full_name AS user_name
            FROM stock_movements sm
            LEFT JOIN users u ON u.id = sm.user_id
            WHERE sm.product_id = :product_id
            ORDER BY sm.created_at DESC, sm.id DESC
            LIMIT 8
        ");
        $statement->execute(['product_id' => $productId]);

        return $statement->fetchAll();
    }

    private function categories(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, name
            FROM stock_categories
            WHERE cabinet_id = :cabinet_id
            ORDER BY name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function suppliers(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT id, name, phone, email
            FROM suppliers
            WHERE cabinet_id = :cabinet_id
            ORDER BY name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function nextReference(int $cabinetId): string
    {
        $statement = $this->db->prepare("
            SELECT reference
            FROM products
            WHERE cabinet_id = :cabinet_id
              AND reference LIKE 'STK-%'
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $reference = (string) ($statement->fetchColumn() ?: '');
        preg_match('/(\d+)$/', $reference, $matches);
        $next = ((int) ($matches[1] ?? 0)) + 1;

        return 'STK-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function filterSql(string $search, int $categoryId, string $status): array
    {
        $where = '';
        $params = [];

        if ($search !== '') {
            $where .= "
                AND (
                    p.name LIKE :search
                    OR p.reference LIKE :search
                    OR p.barcode LIKE :search
                    OR sc.name LIKE :search
                    OR s.name LIKE :search
                )
            ";
            $params['search'] = '%' . $search . '%';
        }

        if ($categoryId > 0) {
            $where .= ' AND p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($status === 'in') {
            $where .= ' AND p.quantity > p.minimum_quantity';
        } elseif ($status === 'low') {
            $where .= ' AND p.quantity > 0 AND p.quantity <= p.minimum_quantity';
        } elseif ($status === 'out') {
            $where .= ' AND p.quantity <= 0';
        }

        return ['where' => $where, 'params' => $params];
    }
}
