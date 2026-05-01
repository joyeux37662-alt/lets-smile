USE lets_smile;

SET @cabinet_id := (SELECT id FROM cabinets WHERE slug = 'lets-smile' LIMIT 1);

INSERT IGNORE INTO invoices
  (cabinet_id, patient_id, treatment_plan_id, quote_id, number, issue_date, due_date, status, total_amount, paid_amount, currency, notes)
SELECT @cabinet_id, p.id, tp.id, NULL, data.number, data.issue_date, data.due_date, data.status, data.total_amount, data.paid_amount, 'MGA', data.notes
FROM (
  SELECT 'F-2026-0001' number, 'PAT-00001' patient_ref, 'T-2026-0001' treatment_ref, '2026-04-22' issue_date, '2026-05-22' due_date, 'paid' status, 350000 total_amount, 350000 paid_amount, 'Contrôle annuel et soins' notes
  UNION ALL SELECT 'F-2026-0002', 'PAT-00002', 'T-2026-0002', '2026-04-20', '2026-05-20', 'paid', 450000, 450000, 'Traitement carie'
  UNION ALL SELECT 'F-2026-0003', 'PAT-00003', 'T-2026-0003', '2026-04-18', '2026-05-18', 'pending', 1200000, 0, 'Blanchiment et suivi'
  UNION ALL SELECT 'F-2026-0004', 'PAT-00007', 'T-2026-0004', '2026-04-15', '2026-05-15', 'pending', 280000, 0, 'Bilan implantaire'
  UNION ALL SELECT 'F-2026-0005', 'PAT-00004', 'T-2026-0005', '2026-03-25', '2026-04-25', 'overdue', 650000, 0, 'Orthodontie'
  UNION ALL SELECT 'F-2026-0006', 'PAT-00005', 'T-2026-0006', '2026-04-10', '2026-05-10', 'overdue', 300000, 0, 'Détartrage + polissage'
  UNION ALL SELECT 'F-2026-0007', 'PAT-00006', NULL, '2026-04-02', '2026-05-02', 'paid', 900000, 900000, 'Couronne céramique'
  UNION ALL SELECT 'A-2026-0001', 'PAT-00008', NULL, '2026-04-30', '2026-04-30', 'credit_note', -150000, 0, 'Avoir commercial'
) data
INNER JOIN patients p ON p.cabinet_id = @cabinet_id AND p.reference = data.patient_ref
LEFT JOIN treatment_plans tp ON tp.cabinet_id = @cabinet_id AND tp.reference = data.treatment_ref;

INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total)
SELECT i.id, items.description, items.quantity, items.unit_price, items.quantity * items.unit_price
FROM invoices i
JOIN (
  SELECT 'F-2026-0001' number, 'Consultation' description, 1 quantity, 120000 unit_price
  UNION ALL SELECT 'F-2026-0001', 'Contrôle annuel', 1, 230000
  UNION ALL SELECT 'F-2026-0002', 'Traitement carie', 1, 450000
  UNION ALL SELECT 'F-2026-0003', 'Blanchiment dentaire', 1, 800000
  UNION ALL SELECT 'F-2026-0003', 'Radiographie rétro-alvéolaire', 1, 60000
  UNION ALL SELECT 'F-2026-0003', 'Application de fluor', 1, 80000
  UNION ALL SELECT 'F-2026-0003', 'Consultation', 1, 260000
  UNION ALL SELECT 'F-2026-0004', 'Bilan implantaire', 1, 280000
  UNION ALL SELECT 'F-2026-0005', 'Orthodontie - séance', 1, 650000
  UNION ALL SELECT 'F-2026-0006', 'Détartrage', 1, 180000
  UNION ALL SELECT 'F-2026-0006', 'Polissage', 1, 120000
  UNION ALL SELECT 'F-2026-0007', 'Couronne céramique', 1, 900000
  UNION ALL SELECT 'A-2026-0001', 'Avoir sur facture', 1, -150000
) items ON items.number = i.number
WHERE i.cabinet_id = @cabinet_id
  AND NOT EXISTS (SELECT 1 FROM invoice_items existing WHERE existing.invoice_id = i.id);
