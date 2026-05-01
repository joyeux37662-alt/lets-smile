<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Appointment;
use DateTimeImmutable;
use Throwable;

final class AgendaController extends Controller
{
    public function index(): void
    {
        $cabinet = Auth::cabinet();
        $focusDate = $this->focusDate((string) ($_GET['date'] ?? ''));
        $weekStart = $focusDate->modify('monday this week');
        $filters = [
            'practitioner_id' => max(0, (int) ($_GET['practitioner_id'] ?? 0)),
            'room_id' => max(0, (int) ($_GET['room_id'] ?? 0)),
            'show_cancelled' => isset($_GET['show_cancelled']),
        ];
        $selectedId = isset($_GET['appointment']) ? (int) $_GET['appointment'] : null;

        try {
            $model = new Appointment();
            $appointments = $model->weekAppointments((int) $cabinet['id'], $weekStart, $filters);
            $selectedId = $selectedId ?: ($appointments[0]['id'] ?? $model->firstWeekId((int) $cabinet['id'], $weekStart, $filters));
            $selectedAppointment = $selectedId ? $model->find((int) $cabinet['id'], (int) $selectedId) : null;
            $options = $model->formOptions((int) $cabinet['id']);
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Agenda. Vérifiez la base MySQL.');
            $appointments = [];
            $selectedAppointment = null;
            $options = ['patients' => [], 'practitioners' => [], 'rooms' => [], 'types' => []];
        }

        $this->view('agenda/index', [
            'title' => 'Agenda',
            'subtitle' => 'Gérez vos rendez-vous et l’emploi du temps du cabinet.',
            'topbarSearchPlaceholder' => 'Rechercher (patient, téléphone...)',
            'topbarSearchAction' => '/appointments',
            'topbarSearchValue' => '',
            'topbarActionHtml' => '<button class="button button-primary button-add" type="button" data-modal-open="appointment-create"><span aria-hidden="true">+</span> Nouveau rendez-vous</button>',
            'appointments' => $appointments,
            'selectedAppointment' => $selectedAppointment,
            'options' => $options,
            'weekStart' => $weekStart,
            'filters' => $filters,
        ]);
    }

    private function focusDate(string $value): DateTimeImmutable
    {
        if ($value !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        return new DateTimeImmutable('today');
    }
}
