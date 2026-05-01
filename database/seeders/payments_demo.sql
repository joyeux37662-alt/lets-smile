USE lets_smile;

SET @cabinet_id := (SELECT id FROM cabinets WHERE slug = 'lets-smile' LIMIT 1);
SET @user_id := (SELECT id FROM users WHERE email = 'admin@letssmile.mg' LIMIT 1);
SET @cash_id := COALESCE((SELECT id FROM payment_methods WHERE cabinet_id = @cabinet_id AND name LIKE 'Esp%' LIMIT 1), (SELECT id FROM payment_methods WHERE cabinet_id = @cabinet_id ORDER BY id LIMIT 1));
SET @mobile_id := COALESCE((SELECT id FROM payment_methods WHERE cabinet_id = @cabinet_id AND name LIKE 'Mobile%' LIMIT 1), @cash_id);
SET @card_id := COALESCE((SELECT id FROM payment_methods WHERE cabinet_id = @cabinet_id AND name LIKE 'Carte%' LIMIT 1), @cash_id);
SET @bank_id := COALESCE((SELECT id FROM payment_methods WHERE cabinet_id = @cabinet_id AND name LIKE 'Virement%' LIMIT 1), @cash_id);
SET @check_id := COALESCE((SELECT id FROM payment_methods WHERE cabinet_id = @cabinet_id AND name LIKE 'Ch%' LIMIT 1), @cash_id);

INSERT IGNORE INTO payments
  (cabinet_id, invoice_id, patient_id, method_id, reference, amount, status, paid_at, notes, created_by)
SELECT
  @cabinet_id,
  i.id,
  COALESCE(i.patient_id, patient_fallback.id),
  data.method_id,
  data.reference,
  data.amount,
  data.status,
  data.paid_at,
  data.notes,
  @user_id
FROM (
  SELECT 'PAY-000124' reference, 'F-2026-0001' invoice_number, NULL patient_ref, @card_id method_id, 350000 amount, 'received' status, '2026-04-22 14:32:00' paid_at, 'Paiement par carte bancaire' notes
  UNION ALL SELECT 'PAY-000125', 'F-2026-0002', NULL, @cash_id, 450000, 'received', '2026-04-20 11:15:00', 'Paiement espèces au cabinet'
  UNION ALL SELECT 'PAY-000126', 'F-2026-TR-0001', NULL, @bank_id, 740000, 'received', '2026-04-18 10:03:00', 'Acompte sur plan de traitement'
  UNION ALL SELECT 'PAY-000127', 'F-2026-0003', NULL, @mobile_id, 300000, 'pending', '2026-04-17 16:45:00', 'Paiement mobile money à confirmer'
  UNION ALL SELECT 'PAY-000128', 'F-2026-0004', NULL, @card_id, 280000, 'received', '2026-04-15 09:22:00', 'Paiement validé'
  UNION ALL SELECT 'PAY-000129', 'F-2026-0005', NULL, @check_id, 150000, 'failed', '2026-04-12 09:10:00', 'Chèque refusé'
  UNION ALL SELECT 'PAY-000130', 'F-2026-0006', NULL, @bank_id, 150000, 'cancelled', '2026-04-10 13:30:00', 'Paiement annulé'
  UNION ALL SELECT 'PAY-000131', NULL, 'PAT-00008', @card_id, 150000, 'refunded', '2026-04-08 12:05:00', 'Remboursement avoir patient'
) data
LEFT JOIN invoices i ON i.cabinet_id = @cabinet_id AND i.number = data.invoice_number
LEFT JOIN patients patient_fallback ON patient_fallback.cabinet_id = @cabinet_id AND patient_fallback.reference = data.patient_ref
WHERE COALESCE(i.patient_id, patient_fallback.id) IS NOT NULL;
