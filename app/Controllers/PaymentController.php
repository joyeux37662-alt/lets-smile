<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Payment;
use Throwable;

final class PaymentController extends Controller
{
    public function index(): void
    {
        $cabinet = Auth::cabinet();
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $selectedId = isset($_GET['payment']) ? (int) $_GET['payment'] : null;

        try {
            $model = new Payment();
            $stats = $model->stats((int) $cabinet['id']);
            $payments = $model->paginate((int) $cabinet['id'], $search, $status, $page);
            $selectedId = $selectedId ?: ($payments['data'][0]['id'] ?? $model->firstId((int) $cabinet['id'], $search, $status));
            $selectedPayment = $selectedId ? $model->find((int) $cabinet['id'], (int) $selectedId) : null;
            $options = $model->formOptions((int) $cabinet['id']);
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Paiements. Vérifiez la base MySQL.');
            $stats = ['received_amount' => 0, 'received_count' => 0, 'pending_amount' => 0, 'pending_count' => 0, 'failed_amount' => 0, 'failed_count' => 0, 'refunded_amount' => 0, 'refunded_count' => 0];
            $payments = ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
            $selectedPayment = null;
            $options = ['patients' => [], 'invoices' => [], 'methods' => []];
        }

        $this->view('payments/index', [
            'title' => 'Paiements',
            'subtitle' => 'Suivez et gérez les paiements et encaissements de votre cabinet.',
            'topbarSearchPlaceholder' => 'Rechercher (patient, facture, paiement...)',
            'topbarSearchAction' => '/payments',
            'topbarSearchValue' => $search,
            'topbarActionHtml' => '<button class="button button-primary button-add" type="button" data-modal-open="payment-create"><span aria-hidden="true">+</span> Nouveau paiement</button>',
            'stats' => $stats,
            'payments' => $payments,
            'selectedPayment' => $selectedPayment,
            'options' => $options,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/payments');
        }

        $data = $this->validatedPaymentData();

        if ($data === null) {
            $this->redirect('/payments');
        }

        try {
            $paymentId = (new Payment())->create((int) Auth::cabinet()['id'], $data, Auth::user()['id'] ?? null);
            flash('success', 'Paiement enregistré avec succès.');
            $this->redirect('/payments?payment=' . $paymentId);
        } catch (Throwable) {
            flash('error', 'Impossible d’enregistrer le paiement.');
            $this->redirect('/payments');
        }
    }

    public function update(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/payments');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $data = $this->validatedPaymentData();

        if ($id <= 0 || $data === null) {
            $this->redirect('/payments');
        }

        try {
            (new Payment())->update((int) Auth::cabinet()['id'], $id, $data);
            flash('success', 'Paiement mis à jour.');
            $this->redirect('/payments?payment=' . $id);
        } catch (Throwable) {
            flash('error', 'Impossible de modifier le paiement.');
            $this->redirect('/payments?payment=' . $id);
        }
    }

    public function status(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            $this->redirect('/payments');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));

        if ($id > 0) {
            (new Payment())->setStatus((int) Auth::cabinet()['id'], $id, $status);
            flash('success', $this->statusMessage($status));
        }

        $this->redirect('/payments?payment=' . $id);
    }

    private function validatedPaymentData(): ?array
    {
        $status = trim((string) ($_POST['status'] ?? 'received'));
        $status = in_array($status, ['received', 'pending', 'failed', 'refunded', 'cancelled'], true) ? $status : 'received';

        $data = [
            'invoice_id' => (int) ($_POST['invoice_id'] ?? 0),
            'patient_id' => (int) ($_POST['patient_id'] ?? 0),
            'method_id' => (int) ($_POST['method_id'] ?? 0),
            'amount' => max(0, (float) str_replace(',', '.', (string) ($_POST['amount'] ?? 0))),
            'status' => $status,
            'paid_at' => trim((string) ($_POST['paid_at'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];

        if ($data['patient_id'] <= 0 || $data['method_id'] <= 0 || $data['amount'] <= 0) {
            flash('error', 'Le patient, le moyen de paiement et le montant sont obligatoires.');
            return null;
        }

        return $data;
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'received' => 'Paiement marqué comme reçu.',
            'pending' => 'Paiement remis en attente.',
            'failed' => 'Paiement marqué comme échoué.',
            'refunded' => 'Paiement remboursé.',
            'cancelled' => 'Paiement annulé.',
            default => 'Statut du paiement mis à jour.',
        };
    }
}
