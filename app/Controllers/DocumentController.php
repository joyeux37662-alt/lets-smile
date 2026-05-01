<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Document;
use Throwable;

final class DocumentController extends Controller
{
    public function index(): void
    {
        $cabinet = Auth::cabinet();
        $search = trim((string) ($_GET['q'] ?? ''));
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $selectedId = isset($_GET['document']) ? (int) $_GET['document'] : null;

        try {
            $model = new Document();
            $stats = $model->stats((int) $cabinet['id']);
            $categoryCounts = $model->categoryCounts((int) $cabinet['id']);
            $documents = $model->paginate((int) $cabinet['id'], $search, $categoryId, $page);
            $selectedId = $selectedId ?: ($documents['data'][0]['id'] ?? $model->firstId((int) $cabinet['id'], $search, $categoryId));
            $selectedDocument = $selectedId ? $model->find((int) $cabinet['id'], (int) $selectedId) : null;
            $options = $model->formOptions((int) $cabinet['id']);
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Documents. Vérifiez la base MySQL.');
            $stats = ['total_documents' => 0, 'patient_documents' => 0, 'cabinet_documents' => 0, 'used_bytes' => 0];
            $categoryCounts = [];
            $documents = ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
            $selectedDocument = null;
            $options = ['categories' => [], 'patients' => [], 'invoices' => [], 'treatments' => []];
        }

        $this->view('documents/index', [
            'title' => 'Documents',
            'subtitle' => 'Gérez et accédez facilement à tous les documents de votre cabinet.',
            'topbarSearchPlaceholder' => 'Rechercher un document, un patient...',
            'topbarSearchAction' => '/documents',
            'topbarSearchValue' => $search,
            'topbarActionHtml' => '<button class="button button-primary button-add" type="button" data-modal-open="document-upload"><span aria-hidden="true">+</span> Ajouter un document</button>',
            'stats' => $stats,
            'categoryCounts' => $categoryCounts,
            'documents' => $documents,
            'selectedDocument' => $selectedDocument,
            'options' => $options,
            'search' => $search,
            'categoryId' => $categoryId,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/documents');
        }

        try {
            $documentId = (new Document())->createFromUpload(
                (int) Auth::cabinet()['id'],
                $this->validatedDocumentData(),
                $_FILES['document_file'] ?? [],
                Auth::user()['id'] ?? null
            );
            flash('success', 'Document ajouté avec succès.');
            $this->redirect('/documents?document=' . $documentId);
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage() ?: 'Impossible d’ajouter le document.');
            $this->redirect('/documents');
        }
    }

    public function download(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->redirect('/documents');
        }

        $model = new Document();
        $document = $model->find((int) Auth::cabinet()['id'], $id);

        if ($document === null) {
            flash('error', 'Document introuvable.');
            $this->redirect('/documents');
        }

        $path = $model->absolutePath((string) $document['file_path']);

        if ($path === null || !is_file($path)) {
            flash('error', 'Fichier introuvable dans le stockage local.');
            $this->redirect('/documents?document=' . $id);
        }

        $fileName = str_replace(['"', "\r", "\n"], '', (string) $document['file_name']);
        header('Content-Type: ' . ((string) ($document['mime_type'] ?: 'application/octet-stream')));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function delete(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            $this->redirect('/documents');
        }

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            (new Document())->delete((int) Auth::cabinet()['id'], $id);
            flash('success', 'Document supprimé.');
        }

        $this->redirect('/documents');
    }

    private function validatedDocumentData(): array
    {
        return [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'patient_id' => (int) ($_POST['patient_id'] ?? 0),
            'invoice_id' => (int) ($_POST['invoice_id'] ?? 0),
            'treatment_plan_id' => (int) ($_POST['treatment_plan_id'] ?? 0),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ];
    }
}
