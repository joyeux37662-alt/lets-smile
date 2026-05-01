<?php
$selected = $selectedThread;
$currentScope = $scope ?? 'all';
$threadUrl = static function (int $threadId) use ($search, $currentScope): string {
    return app_url('/messages?' . http_build_query(array_filter([
        'q' => $search ?? '',
        'scope' => $currentScope !== 'all' ? $currentScope : '',
        'thread' => $threadId,
    ], static fn ($value) => $value !== '')));
};
$scopeUrl = static function (string $targetScope) use ($search): string {
    return app_url('/messages?' . http_build_query(array_filter([
        'q' => $search ?? '',
        'scope' => $targetScope !== 'all' ? $targetScope : '',
    ], static fn ($value) => $value !== '')));
};
$threadName = static function (?array $thread): string {
    if (!$thread) {
        return 'Conversation';
    }

    if (!empty($thread['patient_id'])) {
        return full_name($thread);
    }

    return $thread['subject'] ?: 'Équipe cabinet';
};
$threadMeta = static function (array $thread): string {
    if (!empty($thread['patient_id'])) {
        $parts = ['Patient'];

        if (!empty($thread['patient_age'])) {
            $parts[] = $thread['patient_age'] . ' ans';
        }

        if (!empty($thread['phone'])) {
            $parts[] = $thread['phone'];
        }

        return implode(' • ', $parts);
    }

    return 'Conversation équipe';
};
$messageAuthor = static function (array $message): string {
    if (!empty($message['sender_user_id'])) {
        return $message['sender_user_name'] ?: 'Cabinet';
    }

    return trim((string) (($message['sender_patient_first_name'] ?? '') . ' ' . ($message['sender_patient_last_name'] ?? ''))) ?: 'Patient';
};
$statusLabels = [
    'open' => 'Ouverte',
    'closed' => 'Fermée',
    'archived' => 'Archivée',
];
$scopeTabs = [
    'all' => 'Tous',
    'unread' => 'Non lus',
    'patients' => 'Patients',
    'team' => 'Équipe',
];
$defaultMessage = [
    'patient_id' => '',
    'subject' => '',
    'body' => '',
];
?>

<section class="message-stat-grid" aria-label="Résumé des messages">
    <article class="message-stat-card message-stat-blue">
        <span class="message-stat-icon msg-inbox" aria-hidden="true"></span>
        <div>
            <p>Boîte de réception</p>
            <strong><?= e((string) ($stats['inbox_count'] ?? 0)) ?></strong>
            <small>+ <?= e((string) ($stats['unread_threads'] ?? 0)) ?> non lus</small>
        </div>
    </article>
    <article class="message-stat-card message-stat-green">
        <span class="message-stat-icon msg-patient" aria-hidden="true"></span>
        <div>
            <p>Messages patients</p>
            <strong><?= e((string) ($stats['patient_threads'] ?? 0)) ?></strong>
            <small>+ <?= e((string) ($stats['patient_unread_threads'] ?? 0)) ?> non lus</small>
        </div>
    </article>
    <article class="message-stat-card message-stat-orange">
        <span class="message-stat-icon msg-team" aria-hidden="true"></span>
        <div>
            <p>Messages équipe</p>
            <strong><?= e((string) ($stats['team_threads'] ?? 0)) ?></strong>
            <small><?= e((string) ($stats['team_unread_threads'] ?? 0)) ?> à traiter</small>
        </div>
    </article>
    <article class="message-stat-card message-stat-purple">
        <span class="message-stat-icon msg-archive" aria-hidden="true"></span>
        <div>
            <p>Messages archives</p>
            <strong><?= e((string) ($stats['archived_threads'] ?? 0)) ?></strong>
            <small><a href="<?= e($scopeUrl('archived')) ?>">Voir les archives</a></small>
        </div>
    </article>
</section>

<section class="messages-workspace">
    <aside class="conversation-card">
        <header>
            <h2>Conversations</h2>
            <form class="conversation-search" method="get" action="<?= e(app_url('/messages')) ?>">
                <input type="hidden" name="scope" value="<?= e($currentScope !== 'all' ? $currentScope : '') ?>">
                <input type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Rechercher...">
                <button type="submit" aria-label="Rechercher"></button>
            </form>
            <nav class="conversation-tabs" aria-label="Filtrer les conversations">
                <?php foreach ($scopeTabs as $key => $label): ?>
                    <a class="<?= $currentScope === $key ? 'active' : '' ?>" href="<?= e($scopeUrl($key)) ?>">
                        <?= e($label) ?>
                        <?php if ($key === 'unread' && (int) ($stats['unread_threads'] ?? 0) > 0): ?>
                            <span><?= e((string) $stats['unread_threads']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </header>

        <div class="conversation-list">
            <?php if (($threads ?? []) === []): ?>
                <section class="conversation-empty">
                    <strong>Aucune conversation</strong>
                    <p>Démarrez un nouveau message pour alimenter la boîte de réception.</p>
                    <button class="button button-primary" type="button" data-modal-open="message-create">Nouveau message</button>
                </section>
            <?php endif; ?>

            <?php foreach (($threads ?? []) as $thread): ?>
                <?php $isSelected = $selected && (int) $selected['id'] === (int) $thread['id']; ?>
                <a class="conversation-item <?= $isSelected ? 'active' : '' ?>" href="<?= e($threadUrl((int) $thread['id'])) ?>">
                    <span class="patient-avatar"><?= e(!empty($thread['patient_id']) ? initials($thread) : 'EQ') ?></span>
                    <span class="conversation-copy">
                        <strong><?= e($threadName($thread)) ?></strong>
                        <small><?= e($thread['last_body'] ?: ($thread['subject'] ?: 'Aucun message')) ?></small>
                    </span>
                    <span class="conversation-meta">
                        <time><?= e($thread['last_message_at'] ? format_time($thread['last_message_at']) : format_date($thread['created_at'])) ?></time>
                        <?php if ((int) ($thread['unread_count'] ?? 0) > 0): ?>
                            <b><?= e((string) $thread['unread_count']) ?></b>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <article class="chat-panel">
        <?php if ($selected): ?>
            <header class="chat-header">
                <div>
                    <span class="patient-avatar"><?= e(!empty($selected['patient_id']) ? initials($selected) : 'EQ') ?></span>
                    <span>
                        <h2><?= e($threadName($selected)) ?></h2>
                        <p><?= e($threadMeta($selected)) ?></p>
                    </span>
                </div>
                <nav aria-label="Actions rapides">
                    <a class="chat-icon chat-call" href="<?= e(!empty($selected['phone']) ? 'tel:' . preg_replace('/\s+/', '', (string) $selected['phone']) : '#') ?>" aria-label="Téléphoner"></a>
                    <a class="chat-icon chat-video" href="#" aria-label="Appel vidéo"></a>
                    <form method="post" action="<?= e(app_url('/messages/status')) ?>" data-confirm="Archiver cette conversation ?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="thread_id" value="<?= e($selected['id']) ?>">
                        <input type="hidden" name="status" value="archived">
                        <button class="chat-icon chat-more" type="submit" aria-label="Archiver"></button>
                    </form>
                </nav>
            </header>

            <section class="chat-body" aria-label="Messages de la conversation">
                <?php foreach (($selected['messages'] ?? []) as $message): ?>
                    <?php $isMine = !empty($message['sender_user_id']); ?>
                    <article class="chat-message <?= $isMine ? 'is-mine' : 'is-theirs' ?>">
                        <?php if (!$isMine): ?>
                            <span class="patient-avatar small"><?= e(!empty($selected['patient_id']) ? initials($selected) : 'EQ') ?></span>
                        <?php endif; ?>
                        <div>
                            <p><?= nl2br(e($message['body'])) ?></p>
                            <small><?= e($messageAuthor($message)) ?> • <?= e(format_time($message['created_at'])) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <form class="chat-composer" method="post" action="<?= e(app_url('/messages/reply')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="thread_id" value="<?= e($selected['id']) ?>">
                <textarea name="body" rows="3" placeholder="Écrire un message..." required></textarea>
                <footer>
                    <button class="composer-tool" type="button" aria-label="Joindre un fichier"></button>
                    <button class="composer-emoji" type="button" aria-label="Emoji"></button>
                    <button class="composer-send" type="submit" aria-label="Envoyer"></button>
                </footer>
            </form>
        <?php else: ?>
            <section class="chat-empty">
                <span class="message-stat-icon msg-inbox" aria-hidden="true"></span>
                <h2>Aucune conversation sélectionnée</h2>
                <p>Choisissez une conversation ou créez un nouveau message.</p>
                <button class="button button-primary" type="button" data-modal-open="message-create">Nouveau message</button>
            </section>
        <?php endif; ?>
    </article>

    <aside class="message-detail-card">
        <?php if ($selected): ?>
            <div class="detail-close" aria-hidden="true">×</div>
            <section class="message-contact-head">
                <span class="patient-avatar large"><?= e(!empty($selected['patient_id']) ? initials($selected) : 'EQ') ?></span>
                <h2><?= e($threadName($selected)) ?></h2>
                <p>
                    <?php if (!empty($selected['patient_id'])): ?>
                        <?= e(($selected['patient_age'] ?: '-') . ' ans') ?> • <?= e($selected['birth_date'] ? format_date($selected['birth_date']) : '-') ?> • <?= e($selected['patient_reference']) ?>
                    <?php else: ?>
                        <?= e($selected['subject'] ?: 'Équipe Let’s Smile') ?>
                    <?php endif; ?>
                </p>
                <span class="message-status message-status-<?= e($selected['status']) ?>"><?= e($statusLabels[$selected['status']] ?? $selected['status']) ?></span>
            </section>

            <section class="message-contact-section">
                <dl class="message-contact-list">
                    <div><dt>Telephone</dt><dd><?= e($selected['phone'] ?: '-') ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($selected['email'] ?: '-') ?></dd></div>
                    <div><dt>Sujet</dt><dd><?= e($selected['subject'] ?: '-') ?></dd></div>
                </dl>
            </section>

            <section class="message-contact-section">
                <h3>Informations</h3>
                <dl class="message-contact-list">
                    <div>
                        <dt>Dernier rendez-vous</dt>
                        <dd>
                            <?php if (!empty($selected['last_appointment'])): ?>
                                <?= e(format_date($selected['last_appointment']['starts_at'])) ?> • <?= e($selected['last_appointment']['type_name'] ?: '-') ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Prochain rendez-vous</dt>
                        <dd>
                            <?php if (!empty($selected['next_appointment'])): ?>
                                <?= e(format_date($selected['next_appointment']['starts_at'])) ?> à <?= e(format_time($selected['next_appointment']['starts_at'])) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Traitement en cours</dt>
                        <dd>
                            <?php if (!empty($selected['current_treatment'])): ?>
                                <?= e($selected['current_treatment']['title']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
                <?php if (!empty($selected['patient_id'])): ?>
                    <a class="button button-light button-full" href="<?= e(app_url('/patients?patient=' . (int) $selected['patient_id'])) ?>">Voir le profil complet</a>
                <?php endif; ?>
            </section>

            <section class="message-contact-section message-options">
                <h3>Options</h3>
                <?php if (!empty($selected['patient_id'])): ?>
                    <a href="<?= e(app_url('/appointments?patient=' . (int) $selected['patient_id'])) ?>">Créer un rendez-vous</a>
                    <a href="<?= e(app_url('/documents?patient=' . (int) $selected['patient_id'])) ?>">Envoyer un document</a>
                <?php endif; ?>
                <a href="<?= e(!empty($selected['phone']) ? 'tel:' . preg_replace('/\s+/', '', (string) $selected['phone']) : '#') ?>">Démarrer un appel</a>
                <form method="post" action="<?= e(app_url('/messages/status')) ?>" data-confirm="Archiver cette conversation ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="thread_id" value="<?= e($selected['id']) ?>">
                    <input type="hidden" name="status" value="archived">
                    <button class="link-danger" type="submit">Archiver le contact</button>
                </form>
            </section>
        <?php else: ?>
            <section class="detail-empty">
                <span class="patient-avatar large">ME</span>
                <h2>Aucun contact</h2>
                <p>La fiche du contact s'affichera ici.</p>
            </section>
        <?php endif; ?>
    </aside>
</section>

<?php
$formMessage = $defaultMessage;
require app_path('Views/messages/message-form.php');
?>
