<?php

use App\Controllers\AuthController;
use App\Controllers\AgendaController;
use App\Controllers\AppointmentController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentController;
use App\Controllers\InvoiceController;
use App\Controllers\MessageController;
use App\Controllers\PatientController;
use App\Controllers\PaymentController;
use App\Controllers\PdfExportController;
use App\Controllers\ReportController;
use App\Controllers\SettingController;
use App\Controllers\StockController;
use App\Controllers\TreatmentController;

$router->get('/', [AuthController::class, 'showLogin'], ['guest']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth', 'permission:dashboard.view']);

$router->get('/agenda', [AgendaController::class, 'index'], ['auth', 'permission:agenda.view']);

$router->get('/patients', [PatientController::class, 'index'], ['auth', 'permission:patients.view']);
$router->post('/patients/store', [PatientController::class, 'store'], ['auth', 'permission:patients.manage']);
$router->post('/patients/update', [PatientController::class, 'update'], ['auth', 'permission:patients.manage']);
$router->post('/patients/archive', [PatientController::class, 'archive'], ['auth', 'permission:patients.manage']);
$router->get('/patients/pdf', [PdfExportController::class, 'patientRecord'], ['auth', 'permission:patients.view']);

$router->get('/appointments', [AppointmentController::class, 'index'], ['auth', 'permission:appointments.view']);
$router->get('/rendez-vous', [AppointmentController::class, 'index'], ['auth', 'permission:appointments.view']);
$router->post('/appointments/store', [AppointmentController::class, 'store'], ['auth', 'permission:appointments.manage']);
$router->post('/appointments/update', [AppointmentController::class, 'update'], ['auth', 'permission:appointments.manage']);
$router->post('/appointments/cancel', [AppointmentController::class, 'cancel'], ['auth', 'permission:appointments.manage']);

$router->get('/treatments', [TreatmentController::class, 'index'], ['auth', 'permission:treatments.view']);
$router->get('/traitements', [TreatmentController::class, 'index'], ['auth', 'permission:treatments.view']);
$router->post('/treatments/store', [TreatmentController::class, 'store'], ['auth', 'permission:treatments.manage']);
$router->post('/treatments/update', [TreatmentController::class, 'update'], ['auth', 'permission:treatments.manage']);
$router->post('/treatments/status', [TreatmentController::class, 'status'], ['auth', 'permission:treatments.manage']);
$router->get('/treatments/quote-pdf', [PdfExportController::class, 'treatmentQuote'], ['auth', 'permission:treatments.view']);

$router->get('/billing', [InvoiceController::class, 'index'], ['auth', 'permission:billing.view']);
$router->get('/facturation', [InvoiceController::class, 'index'], ['auth', 'permission:billing.view']);
$router->post('/billing/store', [InvoiceController::class, 'store'], ['auth', 'permission:billing.manage']);
$router->post('/billing/update', [InvoiceController::class, 'update'], ['auth', 'permission:billing.manage']);
$router->post('/billing/status', [InvoiceController::class, 'status'], ['auth', 'permission:billing.manage']);
$router->get('/billing/pdf', [PdfExportController::class, 'invoice'], ['auth', 'permission:billing.view']);
$router->get('/quotes/pdf', [PdfExportController::class, 'quote'], ['auth', 'permission:billing.view']);

$router->get('/payments', [PaymentController::class, 'index'], ['auth', 'permission:payments.view']);
$router->get('/paiements', [PaymentController::class, 'index'], ['auth', 'permission:payments.view']);
$router->post('/payments/store', [PaymentController::class, 'store'], ['auth', 'permission:payments.manage']);
$router->post('/payments/update', [PaymentController::class, 'update'], ['auth', 'permission:payments.manage']);
$router->post('/payments/status', [PaymentController::class, 'status'], ['auth', 'permission:payments.manage']);
$router->get('/payments/receipt', [PdfExportController::class, 'receipt'], ['auth', 'permission:payments.view']);

$router->get('/stock', [StockController::class, 'index'], ['auth', 'permission:stock.view']);
$router->post('/stock/store', [StockController::class, 'store'], ['auth', 'permission:stock.manage']);
$router->post('/stock/update', [StockController::class, 'update'], ['auth', 'permission:stock.manage']);
$router->post('/stock/movement', [StockController::class, 'movement'], ['auth', 'permission:stock.manage']);

$router->get('/documents', [DocumentController::class, 'index'], ['auth', 'permission:documents.view']);
$router->post('/documents/store', [DocumentController::class, 'store'], ['auth', 'permission:documents.manage']);
$router->get('/documents/download', [DocumentController::class, 'download'], ['auth', 'permission:documents.view']);
$router->post('/documents/delete', [DocumentController::class, 'delete'], ['auth', 'permission:documents.manage']);

$router->get('/messages', [MessageController::class, 'index'], ['auth', 'permission:messages.view']);
$router->post('/messages/store', [MessageController::class, 'store'], ['auth', 'permission:messages.manage']);
$router->post('/messages/reply', [MessageController::class, 'reply'], ['auth', 'permission:messages.manage']);
$router->post('/messages/status', [MessageController::class, 'status'], ['auth', 'permission:messages.manage']);

$router->get('/reports', [ReportController::class, 'index'], ['auth', 'permission:reports.view']);
$router->get('/rapports', [ReportController::class, 'index'], ['auth', 'permission:reports.view']);
$router->get('/reports/export', [ReportController::class, 'export'], ['auth', 'permission:reports.view']);

$router->get('/settings', [SettingController::class, 'index'], ['auth', 'permission:settings.manage']);
$router->get('/parametres', [SettingController::class, 'index'], ['auth', 'permission:settings.manage']);
$router->post('/settings/cabinet', [SettingController::class, 'updateCabinet'], ['auth', 'permission:settings.manage']);
$router->post('/settings/identity', [SettingController::class, 'updateIdentity'], ['auth', 'permission:settings.manage']);
$router->post('/settings/preferences', [SettingController::class, 'updatePreferences'], ['auth', 'permission:settings.manage']);
$router->post('/settings/notifications', [SettingController::class, 'updateNotifications'], ['auth', 'permission:settings.manage']);
$router->post('/settings/backup', [SettingController::class, 'updateBackup'], ['auth', 'permission:settings.manage']);
$router->post('/settings/backup-now', [SettingController::class, 'backupNow'], ['auth', 'permission:settings.manage']);
$router->post('/settings/numbering', [SettingController::class, 'updateNumbering'], ['auth', 'permission:settings.manage']);
$router->post('/settings/logo', [SettingController::class, 'uploadLogo'], ['auth', 'permission:settings.manage']);
$router->post('/settings/logo/delete', [SettingController::class, 'deleteLogo'], ['auth', 'permission:settings.manage']);
$router->post('/settings/signature', [SettingController::class, 'uploadSignature'], ['auth', 'permission:settings.manage']);
$router->post('/settings/signature/delete', [SettingController::class, 'deleteSignature'], ['auth', 'permission:settings.manage']);
$router->post('/settings/users/store', [SettingController::class, 'storeUser'], ['auth', 'permission:users.manage']);
$router->post('/settings/users/update', [SettingController::class, 'updateUser'], ['auth', 'permission:users.manage']);
$router->post('/settings/users/status', [SettingController::class, 'updateUserStatus'], ['auth', 'permission:users.manage']);
$router->post('/settings/roles/permissions', [SettingController::class, 'updateRolePermissions'], ['auth', 'permission:users.manage']);
$router->post('/settings/account/password', [SettingController::class, 'updateAccountPassword'], ['auth']);
