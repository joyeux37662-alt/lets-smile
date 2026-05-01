USE lets_smile;

SET @cabinet_id := (SELECT id FROM cabinets WHERE slug = 'lets-smile' LIMIT 1);
SET @practitioner_id := (SELECT id FROM users WHERE email = 'admin@letssmile.mg' LIMIT 1);
SET @implant_category := (SELECT id FROM treatment_categories WHERE cabinet_id = @cabinet_id AND name = 'Implantologie' LIMIT 1);
SET @prosthesis_category := (SELECT id FROM treatment_categories WHERE cabinet_id = @cabinet_id AND name = 'Prothèses' LIMIT 1);
SET @care_category := (SELECT id FROM treatment_categories WHERE cabinet_id = @cabinet_id AND name = 'Soins conservateurs' LIMIT 1);
SET @ortho_category := (SELECT id FROM treatment_categories WHERE cabinet_id = @cabinet_id AND name = 'Orthodontie' LIMIT 1);

INSERT IGNORE INTO treatment_plans
  (cabinet_id, patient_id, practitioner_id, category_id, reference, title, status, progress, total_amount, started_at)
SELECT @cabinet_id, p.id, @practitioner_id, @implant_category, 'T-2026-0001', 'Implant + Couronne', 'in_progress', 60, 1850000, '2026-04-10'
FROM patients p WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00001';

INSERT IGNORE INTO treatment_plans
  (cabinet_id, patient_id, practitioner_id, category_id, reference, title, status, progress, total_amount, started_at)
SELECT @cabinet_id, p.id, @practitioner_id, @care_category, 'T-2026-0002', 'Traitement caries multiples', 'in_progress', 40, 720000, '2026-04-12'
FROM patients p WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00002';

INSERT IGNORE INTO treatment_plans
  (cabinet_id, patient_id, practitioner_id, category_id, reference, title, status, progress, total_amount, started_at, completed_at)
SELECT @cabinet_id, p.id, @practitioner_id, @care_category, 'T-2026-0003', 'Blanchiment dentaire', 'completed', 100, 460000, '2026-04-01', '2026-04-18'
FROM patients p WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00003';

INSERT IGNORE INTO treatment_plans
  (cabinet_id, patient_id, practitioner_id, category_id, reference, title, status, progress, total_amount, started_at)
SELECT @cabinet_id, p.id, @practitioner_id, @implant_category, 'T-2026-0004', 'Pose d’implant', 'pending', 0, 1400000, '2026-05-02'
FROM patients p WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00007';

INSERT IGNORE INTO treatment_plans
  (cabinet_id, patient_id, practitioner_id, category_id, reference, title, status, progress, total_amount, started_at)
SELECT @cabinet_id, p.id, @practitioner_id, @ortho_category, 'T-2026-0005', 'Orthodontie bagues', 'suspended', 25, 3200000, '2026-03-20'
FROM patients p WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00004';

INSERT IGNORE INTO treatment_plans
  (cabinet_id, patient_id, practitioner_id, category_id, reference, title, status, progress, total_amount, started_at, completed_at)
SELECT @cabinet_id, p.id, @practitioner_id, @care_category, 'T-2026-0006', 'Détartrage + Polissage', 'completed', 100, 180000, '2026-04-08', '2026-04-10'
FROM patients p WHERE p.cabinet_id = @cabinet_id AND p.reference = 'PAT-00005';

INSERT INTO treatment_steps
  (treatment_plan_id, title, description, planned_date, completed_at, status, amount, sort_order)
SELECT tp.id, steps.title, steps.description, steps.planned_date, steps.completed_at, steps.status, steps.amount, steps.sort_order
FROM treatment_plans tp
JOIN (
  SELECT 'T-2026-0001' reference, 'Consultation et radiographie' title, 'Diagnostic initial' description, '2026-04-10' planned_date, '2026-04-10 10:00:00' completed_at, 'completed' status, 120000 amount, 1 sort_order
  UNION ALL SELECT 'T-2026-0001', 'Pose de l’implant', 'Intervention implantaire', '2026-04-24', '2026-04-24 11:00:00', 'completed', 850000, 2
  UNION ALL SELECT 'T-2026-0001', 'Pose de la couronne', 'Couronne céramique', '2026-05-05', NULL, 'pending', 650000, 3
  UNION ALL SELECT 'T-2026-0001', 'Contrôle final', 'Contrôle post-traitement', '2026-06-12', NULL, 'pending', 230000, 4
  UNION ALL SELECT 'T-2026-0002', 'Soins dent 16', 'Traitement carie', '2026-04-12', '2026-04-12 09:30:00', 'completed', 240000, 1
  UNION ALL SELECT 'T-2026-0002', 'Soins dent 26', 'Traitement carie', '2026-04-26', NULL, 'pending', 240000, 2
  UNION ALL SELECT 'T-2026-0002', 'Contrôle occlusion', 'Ajustement', '2026-05-03', NULL, 'pending', 240000, 3
  UNION ALL SELECT 'T-2026-0003', 'Séance blanchiment', 'Traitement principal', '2026-04-18', '2026-04-18 14:00:00', 'completed', 460000, 1
  UNION ALL SELECT 'T-2026-0004', 'Bilan implantaire', 'Analyse pré-opératoire', '2026-05-02', NULL, 'pending', 260000, 1
  UNION ALL SELECT 'T-2026-0004', 'Pose implant', 'Intervention', '2026-05-20', NULL, 'pending', 1140000, 2
  UNION ALL SELECT 'T-2026-0005', 'Empreintes', 'Préparation orthodontie', '2026-03-20', '2026-03-20 10:00:00', 'completed', 600000, 1
  UNION ALL SELECT 'T-2026-0005', 'Pose des bagues', 'Traitement orthodontique', '2026-04-05', NULL, 'pending', 2600000, 2
  UNION ALL SELECT 'T-2026-0006', 'Détartrage', 'Nettoyage', '2026-04-08', '2026-04-08 11:00:00', 'completed', 120000, 1
  UNION ALL SELECT 'T-2026-0006', 'Polissage', 'Finition', '2026-04-10', '2026-04-10 11:00:00', 'completed', 60000, 2
) steps ON steps.reference = tp.reference
WHERE tp.cabinet_id = @cabinet_id
  AND NOT EXISTS (
    SELECT 1 FROM treatment_steps existing WHERE existing.treatment_plan_id = tp.id
  );

INSERT IGNORE INTO invoices
  (cabinet_id, patient_id, treatment_plan_id, quote_id, number, issue_date, due_date, status, total_amount, paid_amount, currency, notes)
SELECT @cabinet_id, tp.patient_id, tp.id, NULL, 'F-2026-TR-0001', '2026-04-24', '2026-05-24', 'partial', 1850000, 740000, 'MGA', 'Acompte implant + couronne'
FROM treatment_plans tp
WHERE tp.cabinet_id = @cabinet_id AND tp.reference = 'T-2026-0001';
