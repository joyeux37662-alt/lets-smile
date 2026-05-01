USE lets_smile;

SET @cabinet_id := (SELECT id FROM cabinets WHERE slug = 'lets-smile' LIMIT 1);
SET @practitioner_id := (SELECT id FROM users WHERE email = 'admin@letssmile.mg' LIMIT 1);
SET @room_1 := (SELECT id FROM rooms WHERE cabinet_id = @cabinet_id AND name = 'Salle 1' LIMIT 1);
SET @room_2 := (SELECT id FROM rooms WHERE cabinet_id = @cabinet_id AND name = 'Salle 2' LIMIT 1);
SET @control_type_id := (SELECT id FROM appointment_types WHERE cabinet_id = @cabinet_id AND name = 'Contrôle annuel' LIMIT 1);
SET @carie_type_id := (SELECT id FROM appointment_types WHERE cabinet_id = @cabinet_id AND name = 'Traitement carie' LIMIT 1);
SET @whitening_type_id := (SELECT id FROM appointment_types WHERE cabinet_id = @cabinet_id AND name = 'Blanchiment' LIMIT 1);
SET @scaling_type_id := (SELECT id FROM appointment_types WHERE cabinet_id = @cabinet_id AND name = 'Détartrage' LIMIT 1);
SET @implant_type_id := (SELECT id FROM appointment_types WHERE cabinet_id = @cabinet_id AND name = 'Pose d’implant' LIMIT 1);

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_1, @control_type_id, '2026-04-28 09:00:00', '2026-04-28 10:00:00', 'confirmed', 'Contrôle annuel', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00001'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-28 09:00:00');

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_1, @carie_type_id, '2026-04-28 10:30:00', '2026-04-28 11:30:00', 'confirmed', 'Traitement carie', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00002'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-28 10:30:00');

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_2, @whitening_type_id, '2026-04-28 14:00:00', '2026-04-28 15:00:00', 'confirmed', 'Blanchiment', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00003'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-28 14:00:00');

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_2, @implant_type_id, '2026-04-28 15:30:00', '2026-04-28 16:30:00', 'pending', 'Pose d’implant', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00007'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-28 15:30:00');

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_1, @carie_type_id, '2026-04-29 09:30:00', '2026-04-29 10:30:00', 'confirmed', 'Traitement carie', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00004'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-29 09:30:00');

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_1, @scaling_type_id, '2026-04-29 11:00:00', '2026-04-29 12:00:00', 'pending', 'Détartrage', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00005'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-29 11:00:00');

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_2, @control_type_id, '2026-04-29 14:30:00', '2026-04-29 15:30:00', 'confirmed', 'Contrôle annuel', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00006'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-29 14:30:00');

INSERT INTO appointments
  (cabinet_id, patient_id, practitioner_id, room_id, appointment_type_id, starts_at, ends_at, status, cancellation_reason, notes, created_by)
SELECT @cabinet_id, p.id, @practitioner_id, @room_2, @whitening_type_id, '2026-04-29 16:00:00', '2026-04-29 17:00:00', 'cancelled', 'Patient indisponible', 'Orthodontie', @practitioner_id
FROM patients p
WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00008'
  AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.starts_at = '2026-04-29 16:00:00');
