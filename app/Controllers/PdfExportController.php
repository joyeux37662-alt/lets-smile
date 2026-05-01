<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\PdfExport;
use App\Services\Pdf\PdfDocuments;
use Throwable;

final class PdfExportController extends Controller
{
    public function invoice(): void
    {
        $cabinetId = (int) (Auth::cabinet()['id'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);

        try {
            $model = new PdfExport();
            $invoice = $id > 0 ? $model->invoice($cabinetId, $id) : null;

            if (!$invoice) {
                flash('error', 'Facture introuvable.');
                $this->redirect('/billing');
            }

            $pdf = (new PdfDocuments())->invoice($model->cabinet($cabinetId), $invoice);
            $this->download($pdf, 'facture-' . $this->slug((string) $invoice['number']) . '.pdf');
        } catch (Throwable) {
            flash('error', 'Impossible de generer la facture PDF.');
            $this->redirect('/billing');
        }
    }

    public function receipt(): void
    {
        $cabinetId = (int) (Auth::cabinet()['id'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);

        try {
            $model = new PdfExport();
            $payment = $id > 0 ? $model->payment($cabinetId, $id) : null;

            if (!$payment) {
                flash('error', 'Paiement introuvable.');
                $this->redirect('/payments');
            }

            $pdf = (new PdfDocuments())->receipt($model->cabinet($cabinetId), $payment);
            $this->download($pdf, 'recu-' . $this->slug((string) $payment['reference']) . '.pdf');
        } catch (Throwable) {
            flash('error', 'Impossible de generer le recu PDF.');
            $this->redirect('/payments');
        }
    }

    public function quote(): void
    {
        $cabinetId = (int) (Auth::cabinet()['id'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);

        try {
            $model = new PdfExport();
            $quote = $id > 0 ? $model->quote($cabinetId, $id) : null;

            if (!$quote) {
                flash('error', 'Devis introuvable.');
                $this->redirect('/treatments');
            }

            $pdf = (new PdfDocuments())->quote($model->cabinet($cabinetId), $quote);
            $this->download($pdf, 'devis-' . $this->slug((string) $quote['number']) . '.pdf');
        } catch (Throwable) {
            flash('error', 'Impossible de generer le devis PDF.');
            $this->redirect('/treatments');
        }
    }

    public function treatmentQuote(): void
    {
        $cabinetId = (int) (Auth::cabinet()['id'] ?? 0);
        $id = (int) ($_GET['treatment'] ?? 0);

        try {
            $model = new PdfExport();
            $quote = $id > 0 ? $model->quoteFromTreatment($cabinetId, $id) : null;

            if (!$quote) {
                flash('error', 'Plan de traitement introuvable.');
                $this->redirect('/treatments');
            }

            $pdf = (new PdfDocuments())->quote($model->cabinet($cabinetId), $quote);
            $this->download($pdf, 'devis-' . $this->slug((string) $quote['number']) . '.pdf');
        } catch (Throwable) {
            flash('error', 'Impossible de generer le devis PDF.');
            $this->redirect('/treatments');
        }
    }

    public function patientRecord(): void
    {
        $cabinetId = (int) (Auth::cabinet()['id'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);

        try {
            $model = new PdfExport();
            $record = $id > 0 ? $model->patientRecord($cabinetId, $id) : null;

            if (!$record) {
                flash('error', 'Patient introuvable.');
                $this->redirect('/patients');
            }

            $pdf = (new PdfDocuments())->patientRecord($model->cabinet($cabinetId), $record);
            $this->download($pdf, 'dossier-patient-' . $this->slug((string) $record['patient']['reference']) . '.pdf');
        } catch (Throwable) {
            flash('error', 'Impossible de generer le dossier patient PDF.');
            $this->redirect('/patients');
        }
    }

    private function download(string $content, string $filename): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $content;
        exit;
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: 'document';

        return trim($value, '-') ?: 'document';
    }
}
