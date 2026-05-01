<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Treatment;
use Throwable;

final class TreatmentController extends Controller
{
    public function index(): void
    {
        $cabinet = Auth::cabinet();
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $selectedId = isset($_GET['treatment']) ? (int) $_GET['treatment'] : null;

        try {
            $model = new Treatment();
            $stats = $model->stats((int) $cabinet['id']);
            $treatments = $model->paginate((int) $cabinet['id'], $search, $status, $page);
            $selectedId = $selectedId ?: ($treatments['data'][0]['id'] ?? $model->firstId((int) $cabinet['id'], $search, $status));
            $selectedTreatment = $selectedId ? $model->find((int) $cabinet['id'], (int) $selectedId) : null;
            $options = $model->formOptions((int) $cabinet['id']);
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Traitements. Vérifiez la base MySQL.');
            $stats = ['total' => 0, 'in_progress' => 0, 'pending' => 0, 'completed' => 0];
            $treatments = ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
            $selectedTreatment = null;
            $options = ['patients' => [], 'practitioners' => [], 'categories' => []];
        }

        $this->view('treatments/index', [
            'title' => 'Traitements',
            'subtitle' => 'Suivez l’avancement des plans de traitement de vos patients.',
            'topbarSearchPlaceholder' => 'Rechercher (patient, traitement...)',
            'topbarSearchAction' => '/treatments',
            'topbarSearchValue' => $search,
            'topbarActionHtml' => '<button class="button button-primary button-add" type="button" data-modal-open="treatment-create"><span aria-hidden="true">+</span> Nouveau traitement</button>',
            'stats' => $stats,
            'treatments' => $treatments,
            'selectedTreatment' => $selectedTreatment,
            'options' => $options,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/treatments');
        }

        $data = $this->validatedTreatmentData();

        if ($data === null) {
            $this->redirect('/treatments');
        }

        try {
            $treatmentId = (new Treatment())->create((int) Auth::cabinet()['id'], $data);
            flash('success', 'Plan de traitement ajouté avec succès.');
            $this->redirect('/treatments?treatment=' . $treatmentId);
        } catch (Throwable) {
            flash('error', 'Impossible d’ajouter le plan de traitement.');
            $this->redirect('/treatments');
        }
    }

    public function update(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/treatments');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $data = $this->validatedTreatmentData();

        if ($id <= 0 || $data === null) {
            $this->redirect('/treatments');
        }

        try {
            (new Treatment())->update((int) Auth::cabinet()['id'], $id, $data);
            flash('success', 'Plan de traitement mis à jour.');
            $this->redirect('/treatments?treatment=' . $id);
        } catch (Throwable) {
            flash('error', 'Impossible de modifier le plan de traitement.');
            $this->redirect('/treatments?treatment=' . $id);
        }
    }

    public function status(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            $this->redirect('/treatments');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));

        if ($id > 0) {
            (new Treatment())->setStatus((int) Auth::cabinet()['id'], $id, $status);
            flash('success', $this->statusMessage($status));
        }

        $this->redirect('/treatments?treatment=' . $id);
    }

    private function validatedTreatmentData(): ?array
    {
        $status = trim((string) ($_POST['status'] ?? 'in_progress'));
        $status = in_array($status, ['pending', 'in_progress', 'completed', 'suspended'], true) ? $status : 'in_progress';

        $data = [
            'patient_id' => (int) ($_POST['patient_id'] ?? 0),
            'practitioner_id' => (int) ($_POST['practitioner_id'] ?? 0),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'status' => $status,
            'progress' => max(0, min(100, (int) ($_POST['progress'] ?? 0))),
            'total_amount' => max(0, (float) str_replace(',', '.', (string) ($_POST['total_amount'] ?? 0))),
            'started_at' => trim((string) ($_POST['started_at'] ?? '')),
            'completed_at' => trim((string) ($_POST['completed_at'] ?? '')),
            'steps' => $_POST['steps'] ?? [],
        ];

        if ($data['patient_id'] <= 0 || $data['practitioner_id'] <= 0 || $data['title'] === '') {
            flash('error', 'Le patient, le praticien et le titre du traitement sont obligatoires.');
            return null;
        }

        return $data;
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'suspended' => 'Plan de traitement suspendu.',
            'completed' => 'Plan de traitement terminé.',
            'in_progress' => 'Plan de traitement repris.',
            'cancelled' => 'Plan de traitement annulé.',
            default => 'Statut du plan mis à jour.',
        };
    }
}
