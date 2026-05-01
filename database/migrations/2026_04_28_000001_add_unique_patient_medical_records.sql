ALTER TABLE patient_medical_records
  ADD UNIQUE KEY medical_records_patient_unique (patient_id);
