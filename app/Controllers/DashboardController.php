<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('dashboard/index', [
            'title' => 'Tableau de bord',
            'stats' => [
                ['label' => 'Patients', 'value' => '0', 'hint' => 'Prêt à ajouter vos patients', 'tone' => 'blue'],
                ['label' => 'Rendez-vous', 'value' => '0', 'hint' => 'Agenda du jour', 'tone' => 'green'],
                ['label' => 'Chiffre d’affaires', 'value' => format_money(0), 'hint' => 'Devise MGA configurée', 'tone' => 'purple'],
                ['label' => 'Impayés', 'value' => format_money(0), 'hint' => 'Aucune facture en retard', 'tone' => 'orange'],
            ],
        ]);
    }
}
