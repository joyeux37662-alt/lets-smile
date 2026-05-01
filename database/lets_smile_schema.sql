-- Installation complète de Let's Smile.
-- Attention : ce script réinitialise les tables existantes de la base lets_smile.

CREATE DATABASE IF NOT EXISTS lets_smile
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lets_smile;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS reports_exports;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS message_participants;
DROP TABLE IF EXISTS message_threads;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS document_categories;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS stock_categories;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS quote_items;
DROP TABLE IF EXISTS quotes;
DROP TABLE IF EXISTS treatment_steps;
DROP TABLE IF EXISTS treatment_plans;
DROP TABLE IF EXISTS treatment_categories;
DROP TABLE IF EXISTS appointment_reminders;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS appointment_types;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS patient_notes;
DROP TABLE IF EXISTS patient_medical_records;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS user_cabinets;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS cabinets;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE cabinets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  address TEXT NULL,
  city VARCHAR(100) NULL,
  country VARCHAR(100) DEFAULT 'Madagascar',
  phone VARCHAR(40) NULL,
  email VARCHAR(150) NULL,
  logo_path VARCHAR(255) NULL,
  timezone VARCHAR(80) DEFAULT 'Indian/Antananarivo',
  currency VARCHAR(10) DEFAULT 'MGA',
  locale VARCHAR(10) DEFAULT 'fr',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY settings_unique_key (cabinet_id, setting_key),
  CONSTRAINT fk_settings_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  label VARCHAR(180) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) NULL,
  status ENUM('active','inactive','blocked') DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_cabinets (
  user_id BIGINT UNSIGNED NOT NULL,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, cabinet_id),
  CONSTRAINT fk_user_cabinets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_cabinets_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE patients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  reference VARCHAR(40) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  gender ENUM('male','female','other') NULL,
  birth_date DATE NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(150) NULL,
  address TEXT NULL,
  profession VARCHAR(120) NULL,
  status ENUM('active','inactive','archived') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY patients_reference_unique (cabinet_id, reference),
  INDEX idx_patients_name (last_name, first_name),
  CONSTRAINT fk_patients_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE patient_medical_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id BIGINT UNSIGNED NOT NULL,
  blood_group VARCHAR(10) NULL,
  allergies TEXT NULL,
  medical_history TEXT NULL,
  current_medications TEXT NULL,
  notes TEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY medical_records_patient_unique (patient_id),
  CONSTRAINT fk_medical_records_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_medical_records_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE patient_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_patient_notes_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_patient_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rooms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_rooms_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointment_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  color VARCHAR(20) DEFAULT '#2563EB',
  duration_minutes INT DEFAULT 30,
  is_active TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_appointment_types_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  patient_id BIGINT UNSIGNED NOT NULL,
  practitioner_id BIGINT UNSIGNED NOT NULL,
  room_id BIGINT UNSIGNED NULL,
  appointment_type_id BIGINT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  status ENUM('pending','confirmed','completed','cancelled','missed') DEFAULT 'pending',
  cancellation_reason VARCHAR(180) NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_appointments_cabinet_date (cabinet_id, starts_at),
  INDEX idx_appointments_patient (patient_id),
  CONSTRAINT fk_appointments_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointments_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointments_practitioner FOREIGN KEY (practitioner_id) REFERENCES users(id),
  CONSTRAINT fk_appointments_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
  CONSTRAINT fk_appointments_type FOREIGN KEY (appointment_type_id) REFERENCES appointment_types(id) ON DELETE SET NULL,
  CONSTRAINT fk_appointments_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointment_reminders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('email','sms','phone','internal') DEFAULT 'internal',
  send_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  status ENUM('pending','sent','failed','cancelled') DEFAULT 'pending',
  CONSTRAINT fk_reminders_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE treatment_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  color VARCHAR(20) NULL,
  CONSTRAINT fk_treatment_categories_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE treatment_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  patient_id BIGINT UNSIGNED NOT NULL,
  practitioner_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  reference VARCHAR(40) NOT NULL,
  title VARCHAR(180) NOT NULL,
  status ENUM('pending','in_progress','completed','suspended','cancelled') DEFAULT 'pending',
  progress TINYINT UNSIGNED DEFAULT 0,
  total_amount DECIMAL(14,2) DEFAULT 0,
  started_at DATE NULL,
  completed_at DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY treatment_reference_unique (cabinet_id, reference),
  CONSTRAINT fk_treatment_plans_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_treatment_plans_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_treatment_plans_practitioner FOREIGN KEY (practitioner_id) REFERENCES users(id),
  CONSTRAINT fk_treatment_plans_category FOREIGN KEY (category_id) REFERENCES treatment_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE treatment_steps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  treatment_plan_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  planned_date DATE NULL,
  completed_at DATETIME NULL,
  status ENUM('pending','completed','cancelled') DEFAULT 'pending',
  amount DECIMAL(14,2) DEFAULT 0,
  sort_order INT DEFAULT 0,
  CONSTRAINT fk_treatment_steps_plan FOREIGN KEY (treatment_plan_id) REFERENCES treatment_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  patient_id BIGINT UNSIGNED NOT NULL,
  treatment_plan_id BIGINT UNSIGNED NULL,
  number VARCHAR(40) NOT NULL,
  issue_date DATE NOT NULL,
  valid_until DATE NULL,
  status ENUM('draft','sent','accepted','refused','expired','cancelled') DEFAULT 'draft',
  total_amount DECIMAL(14,2) DEFAULT 0,
  currency VARCHAR(10) DEFAULT 'MGA',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY quotes_number_unique (cabinet_id, number),
  CONSTRAINT fk_quotes_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_quotes_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_quotes_treatment FOREIGN KEY (treatment_plan_id) REFERENCES treatment_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL,
  total DECIMAL(14,2) NOT NULL,
  CONSTRAINT fk_quote_items_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  patient_id BIGINT UNSIGNED NOT NULL,
  treatment_plan_id BIGINT UNSIGNED NULL,
  quote_id BIGINT UNSIGNED NULL,
  number VARCHAR(40) NOT NULL,
  issue_date DATE NOT NULL,
  due_date DATE NULL,
  status ENUM('draft','pending','paid','partial','overdue','cancelled','credit_note') DEFAULT 'pending',
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency VARCHAR(10) DEFAULT 'MGA',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY invoices_number_unique (cabinet_id, number),
  INDEX idx_invoices_patient (patient_id),
  CONSTRAINT fk_invoices_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_invoices_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_invoices_treatment FOREIGN KEY (treatment_plan_id) REFERENCES treatment_plans(id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL,
  total DECIMAL(14,2) NOT NULL,
  CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_methods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_payment_methods_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NULL,
  patient_id BIGINT UNSIGNED NOT NULL,
  method_id BIGINT UNSIGNED NOT NULL,
  reference VARCHAR(60) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  status ENUM('received','pending','failed','refunded','cancelled') DEFAULT 'received',
  paid_at DATETIME NOT NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY payments_reference_unique (cabinet_id, reference),
  CONSTRAINT fk_payments_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_method FOREIGN KEY (method_id) REFERENCES payment_methods(id),
  CONSTRAINT fk_payments_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  CONSTRAINT fk_stock_categories_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(150) NULL,
  address TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_suppliers_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  supplier_id BIGINT UNSIGNED NULL,
  name VARCHAR(180) NOT NULL,
  reference VARCHAR(80) NULL,
  barcode VARCHAR(80) NULL,
  quantity DECIMAL(12,2) DEFAULT 0,
  minimum_quantity DECIMAL(12,2) DEFAULT 0,
  unit VARCHAR(40) NULL,
  unit_price DECIMAL(14,2) DEFAULT 0,
  image_path VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_products_reference (cabinet_id, reference),
  CONSTRAINT fk_products_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES stock_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  type ENUM('in','out','adjustment') NOT NULL,
  quantity DECIMAL(12,2) NOT NULL,
  reason VARCHAR(180) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_stock_movements_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_movements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  CONSTRAINT fk_document_categories_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  patient_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NULL,
  invoice_id BIGINT UNSIGNED NULL,
  quote_id BIGINT UNSIGNED NULL,
  treatment_plan_id BIGINT UNSIGNED NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  file_size BIGINT UNSIGNED DEFAULT 0,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_documents_patient (patient_id),
  CONSTRAINT fk_documents_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_documents_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
  CONSTRAINT fk_documents_category FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_documents_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
  CONSTRAINT fk_documents_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL,
  CONSTRAINT fk_documents_treatment FOREIGN KEY (treatment_plan_id) REFERENCES treatment_plans(id) ON DELETE SET NULL,
  CONSTRAINT fk_documents_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_threads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  patient_id BIGINT UNSIGNED NULL,
  subject VARCHAR(180) NULL,
  status ENUM('open','closed','archived') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_message_threads_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_threads_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  patient_id BIGINT UNSIGNED NULL,
  last_read_at DATETIME NULL,
  CONSTRAINT fk_message_participants_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_participants_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NULL,
  sender_patient_id BIGINT UNSIGNED NULL,
  body TEXT NOT NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_messages_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_messages_sender_patient FOREIGN KEY (sender_patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  type VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports_exports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  report_type VARCHAR(80) NOT NULL,
  file_path VARCHAR(255) NULL,
  filters_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_exports_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_exports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cabinet_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(120) NULL,
  entity_id BIGINT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(60) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_logs_entity (entity_type, entity_id),
  CONSTRAINT fk_audit_logs_cabinet FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE SET NULL,
  CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cabinets (name, slug, address, city, phone, email, timezone, currency, locale)
VALUES ('Let''s Smile Cabinet Dentaire', 'lets-smile', 'Antananarivo', 'Antananarivo', '+261 34 00 000 00', 'contact@letssmile.mg', 'Indian/Antananarivo', 'MGA', 'fr');

INSERT INTO roles (name, label) VALUES
('super_admin', 'Super administrateur'),
('admin_cabinet', 'Administrateur cabinet'),
('dentiste', 'Dentiste'),
('assistant', 'Assistant dentaire'),
('secretaire', 'Secrétaire'),
('comptable', 'Comptable'),
('lecture_seule', 'Lecture seule');

INSERT INTO permissions (name, label) VALUES
('dashboard.view', 'Voir le tableau de bord'),
('agenda.view', 'Voir l’agenda'),
('agenda.manage', 'Gérer l’agenda'),
('patients.view', 'Voir les patients'),
('patients.manage', 'Gérer les patients'),
('appointments.view', 'Voir les rendez-vous'),
('appointments.manage', 'Gérer les rendez-vous'),
('treatments.view', 'Voir les traitements'),
('treatments.manage', 'Gérer les traitements'),
('billing.view', 'Voir la facturation'),
('billing.manage', 'Gérer la facturation'),
('payments.view', 'Voir les paiements'),
('payments.manage', 'Gérer les paiements'),
('stock.view', 'Voir le stock'),
('stock.manage', 'Gérer le stock'),
('documents.view', 'Voir les documents'),
('documents.manage', 'Gérer les documents'),
('messages.view', 'Voir les messages'),
('messages.manage', 'Gérer les messages'),
('reports.view', 'Voir les rapports'),
('settings.manage', 'Gérer les paramètres'),
('users.manage', 'Gérer les utilisateurs');

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.name IN ('super_admin', 'admin_cabinet');

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.name = 'dentiste'
  AND permissions.name IN ('dashboard.view','agenda.view','agenda.manage','patients.view','patients.manage','appointments.view','appointments.manage','treatments.view','treatments.manage','documents.view','documents.manage','messages.view','messages.manage','reports.view');

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.name = 'secretaire'
  AND permissions.name IN ('dashboard.view','agenda.view','agenda.manage','patients.view','patients.manage','appointments.view','appointments.manage','billing.view','billing.manage','payments.view','payments.manage','documents.view','documents.manage','messages.view','messages.manage');

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.name = 'assistant'
  AND permissions.name IN ('dashboard.view','agenda.view','patients.view','patients.manage','appointments.view','appointments.manage','treatments.view','stock.view','stock.manage','documents.view','documents.manage');

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.name = 'comptable'
  AND permissions.name IN ('dashboard.view','billing.view','billing.manage','payments.view','payments.manage','reports.view');

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.name = 'lecture_seule'
  AND permissions.name LIKE '%.view';

INSERT INTO users (role_id, full_name, email, phone, password_hash, status)
VALUES (
  (SELECT id FROM roles WHERE name = 'admin_cabinet'),
  'Dr. Aina Rakoto',
  'admin@letssmile.mg',
  '+261 34 00 000 01',
  '$2y$10$7.T4WMliVhjvVGmpnEUVke1j8QoS8imLnzx4Ob/GE8MJWZhoXn8vW',
  'active'
);

INSERT INTO user_cabinets (user_id, cabinet_id)
VALUES (
  (SELECT id FROM users WHERE email = 'admin@letssmile.mg'),
  (SELECT id FROM cabinets WHERE slug = 'lets-smile')
);

INSERT INTO rooms (cabinet_id, name) VALUES
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Salle 1'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Salle 2');

INSERT INTO appointment_types (cabinet_id, name, color, duration_minutes) VALUES
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Contrôle annuel', '#2563EB', 60),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Traitement carie', '#10B981', 60),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Blanchiment', '#8B5CF6', 60),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Détartrage', '#EC4899', 60),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Pose d’implant', '#F59E0B', 90);

INSERT INTO payment_methods (cabinet_id, name) VALUES
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Espèces'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Mobile Money'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Carte bancaire'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Virement bancaire'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Chèque');

INSERT INTO treatment_categories (cabinet_id, name, color) VALUES
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Soins conservateurs', '#2563EB'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Prothèses', '#10B981'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Implantologie', '#F59E0B'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Orthodontie', '#8B5CF6');

INSERT INTO stock_categories (cabinet_id, name) VALUES
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Consommables'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Médicaments'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Matériaux'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Équipements');

INSERT INTO document_categories (cabinet_id, name) VALUES
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Documents patients'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Documents cabinet'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Devis'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Factures'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'Radiologie');

INSERT INTO settings (cabinet_id, setting_key, setting_value) VALUES
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'date_format', 'd/m/Y'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'time_format', 'H:i'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'currency', 'MGA'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'currency_symbol', 'Ar'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'decimal_places', '0'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'invoice_prefix', 'F-'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'quote_prefix', 'D-'),
((SELECT id FROM cabinets WHERE slug = 'lets-smile'), 'receipt_prefix', 'R-');
