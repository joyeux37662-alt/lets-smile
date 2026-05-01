<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Product;
use Throwable;

final class StockController extends Controller
{
    public function index(): void
    {
        $cabinet = Auth::cabinet();
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $selectedId = isset($_GET['product']) ? (int) $_GET['product'] : null;

        try {
            $model = new Product();
            $stats = $model->stats((int) $cabinet['id']);
            $products = $model->paginate((int) $cabinet['id'], $search, $categoryId, $status, $page);
            $selectedId = $selectedId ?: ($products['data'][0]['id'] ?? $model->firstId((int) $cabinet['id'], $search, $categoryId, $status));
            $selectedProduct = $selectedId ? $model->find((int) $cabinet['id'], (int) $selectedId) : null;
            $options = $model->formOptions((int) $cabinet['id']);
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Stock. Vérifiez la base MySQL.');
            $stats = ['stock_value' => 0, 'products_count' => 0, 'low_stock_count' => 0, 'out_of_stock_count' => 0];
            $products = ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
            $selectedProduct = null;
            $options = ['categories' => [], 'suppliers' => []];
        }

        $this->view('stock/index', [
            'title' => 'Stock',
            'subtitle' => 'Gérez vos produits, suivez les niveaux de stock et les mouvements.',
            'topbarSearchPlaceholder' => 'Rechercher un produit, référence, catégorie...',
            'topbarSearchAction' => '/stock',
            'topbarSearchValue' => $search,
            'topbarActionHtml' => '<button class="button button-primary button-add" type="button" data-modal-open="product-create"><span aria-hidden="true">+</span> Ajouter un produit</button>',
            'stats' => $stats,
            'products' => $products,
            'selectedProduct' => $selectedProduct,
            'options' => $options,
            'search' => $search,
            'status' => $status,
            'categoryId' => $categoryId,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/stock');
        }

        $data = $this->validatedProductData();

        if ($data === null) {
            $this->redirect('/stock');
        }

        try {
            $productId = (new Product())->create((int) Auth::cabinet()['id'], $data);
            flash('success', 'Produit ajouté avec succès.');
            $this->redirect('/stock?product=' . $productId);
        } catch (Throwable) {
            flash('error', 'Impossible d’ajouter le produit.');
            $this->redirect('/stock');
        }
    }

    public function update(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/stock');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $data = $this->validatedProductData();

        if ($id <= 0 || $data === null) {
            $this->redirect('/stock');
        }

        try {
            (new Product())->update((int) Auth::cabinet()['id'], $id, $data);
            flash('success', 'Produit mis à jour.');
            $this->redirect('/stock?product=' . $id);
        } catch (Throwable) {
            flash('error', 'Impossible de modifier le produit.');
            $this->redirect('/stock?product=' . $id);
        }
    }

    public function movement(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/stock');
        }

        $productId = (int) ($_POST['product_id'] ?? 0);
        $data = $this->validatedMovementData();

        if ($productId <= 0 || $data === null) {
            $this->redirect('/stock');
        }

        try {
            (new Product())->addMovement((int) Auth::cabinet()['id'], $productId, $data, Auth::user()['id'] ?? null);
            flash('success', 'Mouvement de stock enregistré.');
            $this->redirect('/stock?product=' . $productId);
        } catch (Throwable) {
            flash('error', 'Impossible d’enregistrer le mouvement de stock.');
            $this->redirect('/stock?product=' . $productId);
        }
    }

    private function validatedProductData(): ?array
    {
        $data = [
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'supplier_id' => (int) ($_POST['supplier_id'] ?? 0),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'reference' => trim((string) ($_POST['reference'] ?? '')),
            'barcode' => trim((string) ($_POST['barcode'] ?? '')),
            'quantity' => max(0, (float) str_replace(',', '.', (string) ($_POST['quantity'] ?? 0))),
            'minimum_quantity' => max(0, (float) str_replace(',', '.', (string) ($_POST['minimum_quantity'] ?? 0))),
            'unit' => trim((string) ($_POST['unit'] ?? '')),
            'unit_price' => max(0, (float) str_replace(',', '.', (string) ($_POST['unit_price'] ?? 0))),
        ];

        if ($data['name'] === '') {
            flash('error', 'Le nom du produit est obligatoire.');
            return null;
        }

        return $data;
    }

    private function validatedMovementData(): ?array
    {
        $type = trim((string) ($_POST['type'] ?? 'in'));
        $type = in_array($type, ['in', 'out', 'adjustment'], true) ? $type : 'in';

        $data = [
            'type' => $type,
            'quantity' => max(0, (float) str_replace(',', '.', (string) ($_POST['quantity'] ?? 0))),
            'reason' => trim((string) ($_POST['reason'] ?? '')),
        ];

        if ($data['quantity'] <= 0) {
            flash('error', 'La quantité du mouvement est obligatoire.');
            return null;
        }

        return $data;
    }
}
