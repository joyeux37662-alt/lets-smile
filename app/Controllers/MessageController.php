<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Message;
use Throwable;

final class MessageController extends Controller
{
    public function index(): void
    {
        $cabinet = Auth::cabinet();
        $user = Auth::user();
        $cabinetId = (int) ($cabinet['id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        $search = trim((string) ($_GET['q'] ?? ''));
        $scope = trim((string) ($_GET['scope'] ?? 'all'));
        $scope = in_array($scope, ['all', 'unread', 'patients', 'team', 'archived'], true) ? $scope : 'all';
        $selectedId = isset($_GET['thread']) ? (int) $_GET['thread'] : null;

        try {
            $model = new Message();
            $stats = $model->stats($cabinetId, $userId);
            $threads = $model->threads($cabinetId, $search, $scope, $userId);
            $selectedId = $selectedId ?: ($threads[0]['id'] ?? $model->firstId($cabinetId, $search, $scope, $userId));

            if ($selectedId) {
                $model->markRead($cabinetId, (int) $selectedId, $userId);
            }

            $selectedThread = $selectedId ? $model->find($cabinetId, (int) $selectedId) : null;
            $options = $model->formOptions($cabinetId);
        } catch (Throwable) {
            flash('error', 'Impossible de charger le module Messages. Vérifiez la base MySQL.');
            $stats = [
                'inbox_count' => 0,
                'unread_threads' => 0,
                'patient_threads' => 0,
                'patient_unread_threads' => 0,
                'team_threads' => 0,
                'team_unread_threads' => 0,
                'archived_threads' => 0,
            ];
            $threads = [];
            $selectedThread = null;
            $options = ['patients' => [], 'users' => []];
        }

        $this->view('messages/index', [
            'title' => 'Messages',
            'subtitle' => 'Communiquez facilement avec vos patients et votre équipe.',
            'topbarSearchPlaceholder' => 'Rechercher un message, un patient...',
            'topbarSearchAction' => '/messages',
            'topbarSearchValue' => $search,
            'topbarActionHtml' => '<button class="button button-primary button-add" type="button" data-modal-open="message-create"><span aria-hidden="true">+</span> Nouveau message</button>',
            'stats' => $stats,
            'threads' => $threads,
            'selectedThread' => $selectedThread,
            'options' => $options,
            'search' => $search,
            'scope' => $scope,
        ]);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/messages');
        }

        $data = $this->validatedThreadData();

        if ($data === null) {
            $this->redirect('/messages');
        }

        try {
            $threadId = (new Message())->createThread((int) Auth::cabinet()['id'], $data, (int) (Auth::user()['id'] ?? 0));
            flash('success', 'Message envoyé avec succès.');
            $this->redirect('/messages?thread=' . $threadId);
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage() ?: 'Impossible d’envoyer le message.');
            $this->redirect('/messages');
        }
    }

    public function reply(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/messages');
        }

        $threadId = (int) ($_POST['thread_id'] ?? 0);
        $body = trim((string) ($_POST['body'] ?? ''));

        if ($threadId <= 0 || $body === '') {
            flash('error', 'Le message est obligatoire.');
            $this->redirect('/messages?thread=' . $threadId);
        }

        try {
            (new Message())->reply((int) Auth::cabinet()['id'], $threadId, $body, (int) (Auth::user()['id'] ?? 0));
            flash('success', 'Réponse envoyée.');
        } catch (Throwable) {
            flash('error', 'Impossible d’envoyer la réponse.');
        }

        $this->redirect('/messages?thread=' . $threadId);
    }

    public function status(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            $this->redirect('/messages');
        }

        $threadId = (int) ($_POST['thread_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));

        if ($threadId > 0) {
            (new Message())->setStatus((int) Auth::cabinet()['id'], $threadId, $status);
            flash('success', $this->statusMessage($status));
        }

        $this->redirect('/messages?thread=' . $threadId);
    }

    private function validatedThreadData(): ?array
    {
        $data = [
            'patient_id' => (int) ($_POST['patient_id'] ?? 0),
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'body' => trim((string) ($_POST['body'] ?? '')),
        ];

        if ($data['body'] === '') {
            flash('error', 'Le message est obligatoire.');
            return null;
        }

        return $data;
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'open' => 'Conversation rouverte.',
            'closed' => 'Conversation fermée.',
            'archived' => 'Conversation archivée.',
            default => 'Statut de la conversation mis à jour.',
        };
    }
}
