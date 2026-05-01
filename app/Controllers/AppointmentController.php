<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Appointment;
use Throwable;

final class AppointmentController extends Controller
{
    public function index(): void
    {
        $cabinet = Auth::cabinet();
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $selectedId = isset($_GET['appointment']) ? (int) $_GET['appointment'] : null;

        try {
            $model = new Appointment();
            $stats = $model->stats((int) $cabinet['id']);
            $appointments = $model->paginate((int) $cabinet['id'], $search, $status, $page);
            $selectedId = $selectedId ?: ($appointments['data'][0]['id'] ?? $model->firstId((int) $cabinet['id'], $search, $status));
            $selectedAppointment = $selectedId ? $model->find((int) $cabinet['id'], (int) $selectedId) : null;
            $options = $model->formOptions((int) $cabinet['id']);
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Rendez-vous. Vérifiez la base MySQL.');
            $stats = ['today' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0, 'upcoming_total' => 1];
            $appointments = ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
            $selectedAppointment = null;
            $options = ['patients' => [], 'practitioners' => [], 'rooms' => [], 'types' => []];
        }

        $this->view('appointments/index', [
            'title' => 'Rendez-vous',
            'subtitle' => 'Consultez, gérez et suivez tous les rendez-vous du cabinet.',
            'topbarSearchPlaceholder' => 'Rechercher (patient, type de soin...)',
            'topbarSearchAction' => '/appointments',
            'topbarSearchValue' => $search,
            'topbarActionHtml' => '<button class="button button-primary button-add" type="button" data-modal-open="appointment-create"><span aria-hidden="true">+</span> Nouveau rendez-vous</button>',
            'stats' => $stats,
            'appointments' => $appointments,
            'selectedAppointment' => $selectedAppointment,
            'options' => $options,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/appointments');
        }

        $data = $this->validatedAppointmentData();

        if ($data === null) {
            $this->redirect('/appointments');
        }

        try {
            $appointmentId = (new Appointment())->create((int) Auth::cabinet()['id'], $data, Auth::user()['id'] ?? null);
            flash('success', 'Rendez-vous ajouté avec succès.');
            $this->redirect($this->returnToPath($_POST['return_to'] ?? '', $appointmentId) ?? '/appointments?appointment=' . $appointmentId);
        } catch (Throwable) {
            flash('error', 'Impossible d’ajouter le rendez-vous.');
            $this->redirect('/appointments');
        }
    }

    public function update(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/appointments');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $data = $this->validatedAppointmentData();

        if ($id <= 0 || $data === null) {
            $this->redirect('/appointments');
        }

        try {
            (new Appointment())->update((int) Auth::cabinet()['id'], $id, $data);
            flash('success', 'Rendez-vous mis à jour.');
            $this->redirect($this->returnToPath($_POST['return_to'] ?? '', $id) ?? '/appointments?appointment=' . $id);
        } catch (Throwable) {
            flash('error', 'Impossible de modifier le rendez-vous.');
            $this->redirect('/appointments?appointment=' . $id);
        }
    }

    public function cancel(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            $this->redirect('/appointments');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($id > 0) {
            (new Appointment())->cancel((int) Auth::cabinet()['id'], $id, $reason);
            flash('success', 'Rendez-vous annulé.');
        }

        $this->redirect('/appointments?appointment=' . $id);
    }

    private function validatedAppointmentData(): ?array
    {
        $status = trim((string) ($_POST['status'] ?? 'confirmed'));
        $status = in_array($status, ['pending', 'confirmed', 'completed', 'cancelled', 'missed'], true) ? $status : 'confirmed';

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $practitionerId = (int) ($_POST['practitioner_id'] ?? 0);
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $typeId = (int) ($_POST['appointment_type_id'] ?? 0);

        $date = trim((string) ($_POST['date'] ?? ''));
        $startTime = trim((string) ($_POST['start_time'] ?? ''));
        $endTime = trim((string) ($_POST['end_time'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($patientId <= 0 || $practitionerId <= 0 || $date === '' || $startTime === '') {
            flash('error', 'Le patient, le praticien, la date et l’heure de début sont obligatoires.');
            return null;
        }

        $dates = (new Appointment())->normalizeDateTime($date, $startTime, $endTime);

        if ($dates === null) {
            flash('error', 'La date ou l’heure du rendez-vous n’est pas valide.');
            return null;
        }

        return [
            'patient_id' => $patientId,
            'practitioner_id' => $practitionerId,
            'room_id' => $roomId,
            'appointment_type_id' => $typeId,
            'starts_at' => $dates['starts_at'],
            'ends_at' => $dates['ends_at'],
            'status' => $status,
            'notes' => $notes,
        ];
    }

    private function returnToPath(mixed $returnTo, int $appointmentId): ?string
    {
        $returnTo = trim((string) $returnTo);

        if ($returnTo === '') {
            return null;
        }

        $path = parse_url($returnTo, PHP_URL_PATH) ?: '';

        if (!in_array($path, ['/agenda', '/appointments', '/rendez-vous'], true)) {
            return null;
        }

        $separator = str_contains($returnTo, '?') ? '&' : '?';

        return $returnTo . $separator . 'appointment=' . $appointmentId;
    }
}
