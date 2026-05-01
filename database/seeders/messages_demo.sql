USE lets_smile;

SET @cabinet_id := (SELECT id FROM cabinets WHERE slug = 'lets-smile' LIMIT 1);
SET @admin_id := (SELECT id FROM users WHERE email = 'admin@letssmile.mg' LIMIT 1);
SET @dentiste_role := (SELECT id FROM roles WHERE name = 'dentiste' LIMIT 1);
SET @assistant_role := (SELECT id FROM roles WHERE name = 'assistant' LIMIT 1);

INSERT IGNORE INTO users (role_id, full_name, email, phone, password_hash, status)
VALUES
  (@dentiste_role, 'Dr. Julien Moreau', 'julien.moreau@letssmile.mg', '+261 34 00 000 02', '$2y$10$7.T4WMliVhjvVGmpnEUVke1j8QoS8imLnzx4Ob/GE8MJWZhoXn8vW', 'active'),
  (@assistant_role, 'Nirina Ravelona', 'nirina.ravelona@letssmile.mg', '+261 34 00 000 03', '$2y$10$7.T4WMliVhjvVGmpnEUVke1j8QoS8imLnzx4Ob/GE8MJWZhoXn8vW', 'active');

INSERT IGNORE INTO user_cabinets (user_id, cabinet_id)
SELECT id, @cabinet_id
FROM users
WHERE email IN ('julien.moreau@letssmile.mg', 'nirina.ravelona@letssmile.mg');

SET @julien_id := (SELECT id FROM users WHERE email = 'julien.moreau@letssmile.mg' LIMIT 1);
SET @assistant_id := (SELECT id FROM users WHERE email = 'nirina.ravelona@letssmile.mg' LIMIT 1);
SET @p_sophie := (SELECT id FROM patients WHERE cabinet_id = @cabinet_id AND reference = 'PAT-00001' LIMIT 1);
SET @p_thomas := (SELECT id FROM patients WHERE cabinet_id = @cabinet_id AND reference = 'PAT-00002' LIMIT 1);
SET @p_julie := (SELECT id FROM patients WHERE cabinet_id = @cabinet_id AND reference = 'PAT-00003' LIMIT 1);
SET @p_antoine := (SELECT id FROM patients WHERE cabinet_id = @cabinet_id AND reference = 'PAT-00004' LIMIT 1);
SET @p_camille := (SELECT id FROM patients WHERE cabinet_id = @cabinet_id AND reference = 'PAT-00005' LIMIT 1);
SET @p_nicolas := (SELECT id FROM patients WHERE cabinet_id = @cabinet_id AND reference = 'PAT-00006' LIMIT 1);

INSERT INTO message_threads (cabinet_id, patient_id, subject, status, created_at, updated_at)
SELECT @cabinet_id, @p_sophie, 'Disponibilites pour detartrage', 'open', '2026-04-30 10:30:00', '2026-04-30 10:34:00'
WHERE @p_sophie IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM message_threads
    WHERE cabinet_id = @cabinet_id AND patient_id = @p_sophie AND subject = 'Disponibilites pour detartrage'
  );
SET @thread_sophie := (SELECT id FROM message_threads WHERE cabinet_id = @cabinet_id AND patient_id = @p_sophie AND subject = 'Disponibilites pour detartrage' LIMIT 1);

INSERT INTO message_participants (thread_id, user_id, patient_id, last_read_at)
SELECT @thread_sophie, @admin_id, NULL, '2026-04-30 10:34:00'
WHERE @thread_sophie IS NOT NULL AND NOT EXISTS (SELECT 1 FROM message_participants WHERE thread_id = @thread_sophie AND user_id = @admin_id);
INSERT INTO message_participants (thread_id, user_id, patient_id, last_read_at)
SELECT @thread_sophie, NULL, @p_sophie, '2026-04-30 10:34:00'
WHERE @thread_sophie IS NOT NULL AND NOT EXISTS (SELECT 1 FROM message_participants WHERE thread_id = @thread_sophie AND patient_id = @p_sophie);

INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at, created_at)
SELECT @thread_sophie, NULL, @p_sophie, 'Bonjour Docteur, j aimerais savoir si vous avez des disponibilites pour un detartrage la semaine prochaine. Merci !', NULL, '2026-04-30 10:30:00'
WHERE @thread_sophie IS NOT NULL AND NOT EXISTS (SELECT 1 FROM messages WHERE thread_id = @thread_sophie AND body LIKE 'Bonjour Docteur,%');
INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at, created_at)
SELECT @thread_sophie, @admin_id, NULL, 'Bonjour Sophie, oui, nous avons des disponibilites mardi a 10h30 ou mercredi a 14h00. Laquelle vous convient le mieux ?', NOW(), '2026-04-30 10:32:00'
WHERE @thread_sophie IS NOT NULL AND NOT EXISTS (SELECT 1 FROM messages WHERE thread_id = @thread_sophie AND body LIKE 'Bonjour Sophie,%');
INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at, created_at)
SELECT @thread_sophie, NULL, @p_sophie, 'Mardi a 10h30 me convient parfaitement. Merci beaucoup !', NULL, '2026-04-30 10:33:00'
WHERE @thread_sophie IS NOT NULL AND NOT EXISTS (SELECT 1 FROM messages WHERE thread_id = @thread_sophie AND body LIKE 'Mardi a 10h30%');
INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at, created_at)
SELECT @thread_sophie, @admin_id, NULL, 'Parfait, c est note. Votre rendez-vous est confirme mardi a 10h30. A tres bientot au cabinet !', NOW(), '2026-04-30 10:34:00'
WHERE @thread_sophie IS NOT NULL AND NOT EXISTS (SELECT 1 FROM messages WHERE thread_id = @thread_sophie AND body LIKE 'Parfait,%');

INSERT INTO message_threads (cabinet_id, patient_id, subject, status, created_at, updated_at)
SELECT @cabinet_id, @p_thomas, 'Accueil et bilan', 'open', '2026-04-30 09:10:00', '2026-04-30 09:15:00'
WHERE @p_thomas IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM message_threads
    WHERE cabinet_id = @cabinet_id AND patient_id = @p_thomas AND subject = 'Accueil et bilan'
  );
SET @thread_thomas := (SELECT id FROM message_threads WHERE cabinet_id = @cabinet_id AND patient_id = @p_thomas AND subject = 'Accueil et bilan' LIMIT 1);
INSERT INTO message_participants (thread_id, user_id, patient_id, last_read_at)
SELECT @thread_thomas, @admin_id, NULL, '2026-04-30 09:15:00'
WHERE @thread_thomas IS NOT NULL AND NOT EXISTS (SELECT 1 FROM message_participants WHERE thread_id = @thread_thomas AND user_id = @admin_id);
INSERT INTO message_participants (thread_id, user_id, patient_id, last_read_at)
SELECT @thread_thomas, NULL, @p_thomas, '2026-04-30 09:15:00'
WHERE @thread_thomas IS NOT NULL AND NOT EXISTS (SELECT 1 FROM message_participants WHERE thread_id = @thread_thomas AND patient_id = @p_thomas);
INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at, created_at)
SELECT @thread_thomas, @admin_id, NULL, 'Merci pour votre accueil hier. Je vous transmets le bilan dans la journee.', NOW(), '2026-04-30 09:15:00'
WHERE @thread_thomas IS NOT NULL AND NOT EXISTS (SELECT 1 FROM messages WHERE thread_id = @thread_thomas);

INSERT INTO message_threads (cabinet_id, patient_id, subject, status, created_at, updated_at)
SELECT @cabinet_id, @p_julie, 'Question blanchiment', 'open', '2026-04-29 16:18:00', '2026-04-29 16:20:00'
WHERE @p_julie IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM message_threads
    WHERE cabinet_id = @cabinet_id AND patient_id = @p_julie AND subject = 'Question blanchiment'
  );
SET @thread_julie := (SELECT id FROM message_threads WHERE cabinet_id = @cabinet_id AND patient_id = @p_julie AND subject = 'Question blanchiment' LIMIT 1);
INSERT INTO message_participants (thread_id, user_id, patient_id, last_read_at)
SELECT @thread_julie, @admin_id, NULL, NULL
WHERE @thread_julie IS NOT NULL AND NOT EXISTS (SELECT 1 FROM message_participants WHERE thread_id = @thread_julie AND user_id = @admin_id);
INSERT INTO message_participants (thread_id, user_id, patient_id, last_read_at)
SELECT @thread_julie, NULL, @p_julie, '2026-04-29 16:18:00'
WHERE @thread_julie IS NOT NULL AND NOT EXISTS (SELECT 1 FROM message_participants WHERE thread_id = @thread_julie AND patient_id = @p_julie);
INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at, created_at)
SELECT @thread_julie, NULL, @p_julie, 'Est-ce que le blanchiment est douloureux apres la seance ?', NULL, '2026-04-29 16:20:00'
WHERE @thread_julie IS NOT NULL AND NOT EXISTS (SELECT 1 FROM messages WHERE thread_id = @thread_julie);

INSERT INTO message_threads (cabinet_id, patient_id, subject, status, created_at, updated_at)
SELECT @cabinet_id, NULL, 'Equipe - Salle 1', 'open', '2026-04-29 15:35:00', '2026-04-29 15:45:00'
WHERE NOT EXISTS (
    SELECT 1 FROM message_threads
    WHERE cabinet_id = @cabinet_id AND patient_id IS NULL AND subject = 'Equipe - Salle 1'
);
SET @thread_team := (SELECT id FROM message_threads WHERE cabinet_id = @cabinet_id AND patient_id IS NULL AND subject = 'Equipe - Salle 1' LIMIT 1);
INSERT INTO message_participants (thread_id, user_id, patient_id, last_read_at)
SELECT @thread_team, @admin_id, NULL, '2026-04-29 15:45:00'
WHERE @thread_team IS NOT NULL AND NOT EXISTS (SELECT 1 FROM message_participants WHERE thread_id = @thread_team AND user_id = @admin_id);
INSERT INTO message_participants (thread_id, user_id, patient_id, last_read_at)
SELECT @thread_team, @assistant_id, NULL, NULL
WHERE @thread_team IS NOT NULL AND NOT EXISTS (SELECT 1 FROM message_participants WHERE thread_id = @thread_team AND user_id = @assistant_id);
INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at, created_at)
SELECT @thread_team, @assistant_id, NULL, 'Nouveau rendez-vous a 14h00, salle 1 preparee.', NULL, '2026-04-29 15:45:00'
WHERE @thread_team IS NOT NULL AND NOT EXISTS (SELECT 1 FROM messages WHERE thread_id = @thread_team);

INSERT INTO message_threads (cabinet_id, patient_id, subject, status, created_at, updated_at)
SELECT @cabinet_id, @p_antoine, 'Retard possible', 'open', '2026-04-28 11:00:00', '2026-04-28 11:05:00'
WHERE @p_antoine IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM message_threads
    WHERE cabinet_id = @cabinet_id AND patient_id = @p_antoine AND subject = 'Retard possible'
  );
SET @thread_antoine := (SELECT id FROM message_threads WHERE cabinet_id = @cabinet_id AND patient_id = @p_antoine AND subject = 'Retard possible' LIMIT 1);
INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at, created_at)
SELECT @thread_antoine, NULL, @p_antoine, 'Je ne pourrai pas etre present a l heure prevue, puis-je decaler de 30 minutes ?', NULL, '2026-04-28 11:05:00'
WHERE @thread_antoine IS NOT NULL AND NOT EXISTS (SELECT 1 FROM messages WHERE thread_id = @thread_antoine);

INSERT INTO message_threads (cabinet_id, patient_id, subject, status, created_at, updated_at)
SELECT @cabinet_id, @p_camille, 'Suivi detartrage', 'closed', '2026-04-25 08:00:00', '2026-04-25 08:12:00'
WHERE @p_camille IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM message_threads
    WHERE cabinet_id = @cabinet_id AND patient_id = @p_camille AND subject = 'Suivi detartrage'
  );
SET @thread_camille := (SELECT id FROM message_threads WHERE cabinet_id = @cabinet_id AND patient_id = @p_camille AND subject = 'Suivi detartrage' LIMIT 1);
INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at, created_at)
SELECT @thread_camille, @admin_id, NULL, 'Merci beaucoup pour votre passage au cabinet.', NOW(), '2026-04-25 08:12:00'
WHERE @thread_camille IS NOT NULL AND NOT EXISTS (SELECT 1 FROM messages WHERE thread_id = @thread_camille);

INSERT INTO message_threads (cabinet_id, patient_id, subject, status, created_at, updated_at)
SELECT @cabinet_id, @p_nicolas, 'Ancienne conversation', 'archived', '2026-04-20 17:00:00', '2026-04-20 17:02:00'
WHERE @p_nicolas IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM message_threads
    WHERE cabinet_id = @cabinet_id AND patient_id = @p_nicolas AND subject = 'Ancienne conversation'
  );
SET @thread_nicolas := (SELECT id FROM message_threads WHERE cabinet_id = @cabinet_id AND patient_id = @p_nicolas AND subject = 'Ancienne conversation' LIMIT 1);
INSERT INTO messages (thread_id, sender_user_id, sender_patient_id, body, read_at, created_at)
SELECT @thread_nicolas, @admin_id, NULL, 'A bientot, nous restons disponibles si besoin.', NOW(), '2026-04-20 17:02:00'
WHERE @thread_nicolas IS NOT NULL AND NOT EXISTS (SELECT 1 FROM messages WHERE thread_id = @thread_nicolas);

UPDATE messages
SET read_at = NULL
WHERE (thread_id = @thread_sophie AND sender_patient_id = @p_sophie)
   OR (thread_id = @thread_julie AND sender_patient_id = @p_julie)
   OR (thread_id = @thread_antoine AND sender_patient_id = @p_antoine)
   OR (thread_id = @thread_team AND sender_user_id = @assistant_id);
