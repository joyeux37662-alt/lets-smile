USE lets_smile;

SET @cabinet_id := (SELECT id FROM cabinets WHERE slug = 'lets-smile' LIMIT 1);
SET @practitioner_id := (SELECT id FROM users WHERE email = 'admin@letssmile.mg' LIMIT 1);
SET @room_id := (SELECT id FROM rooms WHERE cabinet_id = @cabinet_id ORDER BY id LIMIT 1);
SET @control_type_id := (SELECT id FROM appointment_types WHERE cabinet_id = @cabinet_id AND name = 'Contrôle annuel' LIMIT 1);
SET @carie_type_id := (SELECT id FROM appointment_types WHERE cabinet_id = @cabinet_id AND name = 'Traitement carie' LIMIT 1);
SET @whitening_type_id := (SELECT id FROM appointment_types WHERE cabinet_id = @cabinet_id AND name = 'Blanchiment' LIMIT 1);

INSERT IGNORE INTO patients
  (cabinet_id, reference, first_name, last_name, gender, birth_date, phone, email, address, profession, status)
VALUES
  (@cabinet_id, 'PAT-00001', 'Sophie', 'Durand', 'female', '1994-04-12', '06 12 34 56 78', 'sophie.durand@email.com', '15 rue des Lilas, Antananarivo', 'Architecte', 'active'),
  (@cabinet_id, 'PAT-00002', 'Thomas', 'Martin', 'male', '1981-02-18', '06 23 45 67 89', 'thomas.martin@email.com', 'Ivandry, Antananarivo', 'Ingénieur', 'active'),
  (@cabinet_id, 'PAT-00003', 'Julie', 'Bernard', 'female', '1998-07-24', '06 34 56 78 90', 'julie.bernard@email.com', 'Analakely, Antananarivo', 'Commerçante', 'active'),
  (@cabinet_id, 'PAT-00004', 'Antoine', 'Leroy', 'male', '1988-10-09', '06 45 67 89 01', 'antoine.leroy@email.com', 'Ambohijatovo, Antananarivo', 'Consultant', 'active'),
  (@cabinet_id, 'PAT-00005', 'Camille', 'Petit', 'female', '1996-12-05', '06 56 78 90 12', 'camille.petit@email.com', 'Ankorondrano, Antananarivo', 'Designer', 'active'),
  (@cabinet_id, 'PAT-00006', 'Nicolas', 'Moreau', 'male', '1986-05-30', '06 67 89 01 23', 'nicolas.moreau@email.com', 'Ambatobe, Antananarivo', 'Entrepreneur', 'inactive'),
  (@cabinet_id, 'PAT-00007', 'Jean', 'Dupont', 'male', '1975-03-14', '06 78 90 12 34', 'jean.dupont@email.com', 'Isoraka, Antananarivo', 'Enseignant', 'active'),
  (@cabinet_id, 'PAT-00008', 'Clara', 'Michel', 'female', '2002-09-20', '06 89 01 23 45', 'clara.michel@email.com', 'Andraharo, Antananarivo', 'Étudiante', 'active');

INSERT INTO patient_medical_records
  (patient_id, blood_group, allergies, medical_history, current_medications, notes, updated_by)
SELECT id, 'A+', 'Aucune connue', 'Aucun antécédent majeur', NULL, 'Patient ne tolère pas les anti-inflammatoires.', @practitioner_id
FROM patients
WHERE cabinet_id = @cabinet_id AND reference = 'PAT-00001'
ON DUPLICATE KEY UPDATE
  blood_group = VALUES(blood_group),
  allergies = VALUES(allergies),
  medical_history = VALUES(medical_history),
  current_medications = VALUES(current_medications),
  notes = VALUES(notes),
  updated_by = VALUES(updated_by);

INSERT INTO patient_medical_records
  (patient_id, blood_group, allergies, medical_history, current_medications, notes, updated_by)
SELECT id, NULL, 'Aucune connue', NULL, NULL, NULL, @practitioner_id
FROM patients
WHERE cabinet_id = @cabinet_id AND reference IN ('PAT-00002','PAT-00003','PAT-00004','PAT-00005','PAT-00006','PAT-00007','PAT-00008')
ON DUPLICATE KEY UPDATE
  allergies = VALUES(allergies),
  updated_by = VALUES(updated_by);

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_id, @control_type_id, '2026-04-22 09:00:00', '2026-04-22 10:00:00', 'completed', 'Contrôle annuel', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00001'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-22 09:00:00');

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_id, @control_type_id, '2026-05-29 09:30:00', '2026-05-29 10:30:00', 'confirmed', 'Contrôle annuel', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00001'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-05-29 09:30:00');

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_id, @carie_type_id, '2026-04-20 10:30:00', '2026-04-20 11:30:00', 'completed', 'Traitement carie', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00002'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-20 10:30:00');

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_id, @whitening_type_id, '2026-04-18 14:00:00', '2026-04-18 15:00:00', 'completed', 'Blanchiment', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00003'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-18 14:00:00');
