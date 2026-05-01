<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Invoice;
use Throwable;

final class InvoiceController extends Controller
{
    public function index(): void
    {
        $cabinet = Auth::cabinet();
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $selectedId = isset($_GET['invoice']) ? (int) $_GET['invoice'] : null;

        try {
            $model = new Invoice();
            $stats = $model->stats((int) $cabinet['id']);
            $invoices = $model->paginate((int) $cabinet['id'], $search, $status, $page);
            $selectedId = $selectedId ?: ($invoices['data'][0]['id'] ?? $model->firstId((int) $cabinet['id'], $search, $status));
            $selectedInvoice = $selectedId ? $model->find((int) $cabinet['id'], (int) $selectedId) : null;
            $options = $model->formOptions((int) $cabinet['id']);
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Facturation. Vérifiez la base MySQL.');
            $stats = ['turnover' => 0, 'collected' => 0, 'pending_amount' => 0, 'pending_count' => 0, 'overdue_amount' => 0, 'overdue_count' => 0];
            $invoices = ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
            $selectedInvoice = null;
            $options = ['patients' => [], 'treatments' => []];
        }

        $this->view('billing/index', [
            'title' => 'Facturation',
            'subtitle' => 'Gérez vos factures, devis et encaissements.',
            'topbarSearchPlaceholder' => 'Rechercher (patient, facture, n° facture...)',
            'topbarSearchAction' => '/billing',
            'topbarSearchValue' => $search,
            'topbarActionHtml' => '<button class="button button-primary button-add" type="button" data-modal-open="invoice-create"><span aria-hidden="true">+</span> Nouvelle facture</button>',
            'stats' => $stats,
            'invoices' => $invoices,
            'selectedInvoice' => $selectedInvoice,
            'options' => $options,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/billing');
        }

        $data = $this->validatedInvoiceData();

        if ($data === null) {
            $this->redirect('/billing');
        }

        try {
            $invoiceId = (new Invoice())->create((int) Auth::cabinet()['id'], $data);
            flash('success', 'Facture créée avec succès.');
            $this->redirect('/billing?invoice=' . $invoiceId);
        } catch (Throwable) {
            flash('error', 'Impossible de créer la facture.');
            $this->redirect('/billing');
        }
    }

    public function update(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/billing');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $data = $this->validatedInvoiceData();

        if ($id <= 0 || $data === null) {
            $this->redirect('/billing');
        }

        try {
            (new Invoice())->update((int) Auth::cabinet()['id'], $id, $data);
            flash('success', 'Facture mise à jour.');
            $this->redirect('/billing?invoice=' . $id);
        } catch (Throwable) {
            flash('error', 'Impossible de modifier la facture.');
            $this->redirect('/billing?invoice=' . $id);
        }
    }

    public function status(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            $this->redirect('/billing');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));

        if ($id > 0) {
            (new Invoice())->setStatus((int) Auth::cabinet()['id'], $id, $status);
            flash('success', $this->statusMessage($status));
        }

        $this->redirect('/billing?invoice=' . $id);
    }

    private function validatedInvoiceData(): ?array
    {
        $status = trim((string) ($_POST['status'] ?? 'pending'));
        $status = in_array($status, ['draft', 'pending', 'paid', 'partial', 'overdue', 'cancelled', 'credit_note'], true) ? $status : 'pending';

        $data = [
            'patient_id' => (int) ($_POST['patient_id'] ?? 0),
            'treatment_plan_id' => (int) ($_POST['treatment_plan_id'] ?? 0),
            'issue_date' => trim((string) ($_POST['issue_date'] ?? date('Y-m-d'))),
            'due_date' => trim((string) ($_POST['due_date'] ?? '')),
            'status' => $status,
            'paid_amount' => max(0, (float) str_replace(',', '.', (string) ($_POST['paid_amount'] ?? 0))),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'items' => $_POST['items'] ?? [],
        ];

        if ($data['patient_id'] <= 0 || $data['issue_date'] === '') {
            flash('error', 'Le patient et la date de facture sont obligatoires.');
            return null;
        }

        return $data;
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'paid' => 'Facture marquée comme payée.',
            'pending' => 'Facture remise en attente.',
            'cancelled' => 'Facture annulée.',
            'draft' => 'Facture passée en brouillon.',
            default => 'Statut de la facture mis à jour.',
        };
    }
}
