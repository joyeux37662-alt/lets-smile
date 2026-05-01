USE lets_smile;

SET @cabinet_id := (SELECT id FROM cabinets WHERE slug = 'lets-smile' LIMIT 1);
SET @user_id := (SELECT id FROM users WHERE email = 'admin@letssmile.mg' LIMIT 1);
SET @cat_patient := (SELECT id FROM document_categories WHERE cabinet_id = @cabinet_id AND name = 'Documents patients' LIMIT 1);
SET @cat_cabinet := (SELECT id FROM document_categories WHERE cabinet_id = @cabinet_id AND name = 'Documents cabinet' LIMIT 1);
SET @cat_devis := COALESCE((SELECT id FROM document_categories WHERE cabinet_id = @cabinet_id AND name = 'Devis' LIMIT 1), @cat_patient);
SET @cat_factures := COALESCE((SELECT id FROM document_categories WHERE cabinet_id = @cabinet_id AND name LIKE 'Fact%' LIMIT 1), @cat_patient);
SET @cat_radio := COALESCE((SELECT id FROM document_categories WHERE cabinet_id = @cabinet_id AND name = 'Radiologie' LIMIT 1), @cat_patient);
SET @cat_admin := COALESCE((SELECT id FROM document_categories WHERE cabinet_id = @cabinet_id AND name LIKE 'Administr%' LIMIT 1), @cat_cabinet);
SET @cat_traitement := COALESCE((SELECT id FROM document_categories WHERE cabinet_id = @cabinet_id AND name = 'Traitement' LIMIT 1), @cat_patient);

INSERT INTO documents
  (cabinet_id, patient_id, category_id, invoice_id, quote_id, treatment_plan_id, uploaded_by, title, file_name, file_path, mime_type, file_size, description, created_at)
SELECT
  @cabinet_id,
  p.id,
  data.category_id,
  i.id,
  NULL,
  tp.id,
  @user_id,
  data.title,
  data.file_name,
  data.file_path,
  data.mime_type,
  data.file_size,
  data.description,
  data.created_at
FROM (
  SELECT 'PAT-00001' patient_ref, @cat_devis category_id, 'F-2026-0001' invoice_number, 'T-2026-0001' treatment_ref, 'Devis implant 2026-001' title, 'devis_implant_2026_001.pdf' file_name, 'demo/devis_implant_2026_001.pdf' file_path, 'application/pdf' mime_type, 245000 file_size, 'Devis pour implant dentaire' description, '2026-04-20 14:32:00' created_at
  UNION ALL SELECT 'PAT-00001', @cat_factures, 'F-2026-0001', NULL, 'Facture F-2026-0001', 'facture_f_2026_0001.pdf', 'demo/facture_f_2026_0001.pdf', 'application/pdf', 198000, 'Facture de consultation et soins', '2026-04-20 14:31:00'
  UNION ALL SELECT 'PAT-00002', @cat_radio, NULL, NULL, 'Radio panoramique 2026-04', 'radio_panoramique_2026_04.jpg', 'demo/radio_panoramique_2026_04.jpg', 'image/jpeg', 1200000, 'Radiographie panoramique', '2026-04-18 10:15:00'
  UNION ALL SELECT 'PAT-00003', @cat_admin, NULL, 'T-2026-0003', 'Consentement traitement', 'consentement_traitement.pdf', 'demo/consentement_traitement.pdf', 'application/pdf', 156000, 'Consentement signe avant traitement', '2026-04-18 10:10:00'
  UNION ALL SELECT 'PAT-00004', @cat_traitement, NULL, 'T-2026-0004', 'Plan de traitement', 'plan_traitement.docx', 'demo/plan_traitement.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 78000, 'Plan de traitement patient', '2026-04-15 16:45:00'
  UNION ALL SELECT 'PAT-00005', @cat_admin, NULL, NULL, 'Attestation soins', 'attestation_soins.pdf', 'demo/attestation_soins.pdf', 'application/pdf', 112000, 'Attestation de soins dentaires', '2026-04-10 09:20:00'
  UNION ALL SELECT 'PAT-00006', @cat_patient, NULL, NULL, 'Photo clinique 2026-04', 'photo_clinique_2026_04.jpg', 'demo/photo_clinique_2026_04.jpg', 'image/jpeg', 3600000, 'Photo clinique intra-orale', '2026-04-10 09:10:00'
  UNION ALL SELECT NULL, @cat_cabinet, NULL, NULL, 'Protocole sterilisation', 'protocole_sterilisation.pdf', 'demo/protocole_sterilisation.pdf', 'application/pdf', 201000, 'Procedure interne du cabinet', '2026-04-02 13:30:00'
) data
LEFT JOIN patients p ON p.cabinet_id = @cabinet_id AND p.reference = data.patient_ref
LEFT JOIN invoices i ON i.cabinet_id = @cabinet_id AND i.number = data.invoice_number
LEFT JOIN treatment_plans tp ON tp.cabinet_id = @cabinet_id AND tp.reference = data.treatment_ref
WHERE NOT EXISTS (
  SELECT 1
  FROM documents d
  WHERE d.cabinet_id = @cabinet_id
    AND d.file_name = data.file_name
);
