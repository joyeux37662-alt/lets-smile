<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Patient;
use Throwable;

final class PatientController extends Controller
{
    public function index(): void
    {
        $cabinet = Auth::cabinet();
        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $selectedId = isset($_GET['patient']) ? (int) $_GET['patient'] : null;

        try {
            $model = new Patient();
            $stats = $model->stats((int) $cabinet['id']);
            $patients = $model->paginate((int) $cabinet['id'], $search, $page);
            $selectedId = $selectedId ?: ($patients['data'][0]['id'] ?? $model->firstId((int) $cabinet['id']));
            $selectedPatient = $selectedId ? $model->find((int) $cabinet['id'], (int) $selectedId) : null;
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Patients. Vérifiez la base MySQL.');
            $stats = ['total' => 0, 'new_this_month' => 0, 'active' => 0, 'inactive' => 0];
            $patients = ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
            $selectedPatient = null;
        }

        $this->view('patients/index', [
            'title' => 'Patients',
            'subtitle' => 'Consultez et gérez les informations de vos patients.',
            'topbarSearchPlaceholder' => 'Rechercher (nom, téléphone, email...)',
            'topbarSearchAction' => '/patients',
            'topbarSearchValue' => $search,
            'topbarActionHtml' => '<button class="button button-primary button-add" type="button" data-modal-open="patient-create"><span aria-hidden="true">+</span> Nouveau patient</button>',
            'stats' => $stats,
            'patients' => $patients,
            'selectedPatient' => $selectedPatient,
            'search' => $search,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/patients');
        }

        $data = $this->validatedPatientData();

        if ($data === null) {
            $this->redirect('/patients');
        }

        try {
            $patientId = (new Patient())->create((int) Auth::cabinet()['id'], $data, Auth::user()['id'] ?? null);
            flash('success', 'Patient ajouté avec succès.');
            $this->redirect('/patients?patient=' . $patientId);
        } catch (Throwable) {
            flash('error', 'Impossible d’ajouter le patient.');
            $this->redirect('/patients');
        }
    }

    public function update(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/patients');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $data = $this->validatedPatientData();

        if ($id <= 0 || $data === null) {
            $this->redirect('/patients');
        }

        try {
            (new Patient())->update((int) Auth::cabinet()['id'], $id, $data, Auth::user()['id'] ?? null);
            flash('success', 'Fiche patient mise à jour.');
            $this->redirect('/patients?patient=' . $id);
        } catch (Throwable) {
            flash('error', 'Impossible de modifier le patient.');
            $this->redirect('/patients?patient=' . $id);
        }
    }

    public function archive(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            $this->redirect('/patients');
        }

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            (new Patient())->archive((int) Auth::cabinet()['id'], $id);
            flash('success', 'Patient archivé.');
        }

        $this->redirect('/patients');
    }

    private function validatedPatientData(): ?array
    {
        $data = [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'gender' => trim((string) ($_POST['gender'] ?? '')),
            'birth_date' => trim((string) ($_POST['birth_date'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'profession' => trim((string) ($_POST['profession'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
            'blood_group' => trim((string) ($_POST['blood_group'] ?? '')),
            'allergies' => trim((string) ($_POST['allergies'] ?? '')),
            'medical_history' => trim((string) ($_POST['medical_history'] ?? '')),
            'current_medications' => trim((string) ($_POST['current_medications'] ?? '')),
            'medical_notes' => trim((string) ($_POST['medical_notes'] ?? '')),
        ];

        if ($data['first_name'] === '' || $data['last_name'] === '') {
            flash('error', 'Le nom et le prénom du patient sont obligatoires.');
            return null;
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', 'L’adresse email du patient n’est pas valide.');
            return null;
        }

        if (!in_array($data['gender'], ['', 'male', 'female', 'other'], true)) {
            $data['gender'] = '';
        }

        if (!in_array($data['status'], ['active', 'inactive'], true)) {
            $data['status'] = 'active';
        }

        return $data;
    }
}
