<?php

declare(strict_types=1);

namespace App\Services\Pdf;

final class PdfDocuments
{
    private const LEFT = 40.0;
    private const RIGHT = 555.0;
    private const CONTENT = 515.0;
    private const BOTTOM = 800.0;

    public function invoice(array $cabinet, array $invoice): string
    {
        $pdf = new SimplePdf();
        $y = $this->header($pdf, $cabinet, 'FACTURE', (string) $invoice['number']);

        $this->twoColumns($pdf, $y, [
            'Patient' => full_name($invoice),
            'Reference patient' => (string) ($invoice['patient_reference'] ?? '-'),
            'Telephone' => (string) ($invoice['phone'] ?? '-'),
            'Email' => (string) ($invoice['email'] ?? '-'),
        ], [
            'Date de facture' => format_date($invoice['issue_date'] ?? null),
            'Echeance' => format_date($invoice['due_date'] ?? null),
            'Traitement' => (string) ($invoice['treatment_title'] ?: '-'),
            'Statut' => $this->invoiceStatus((string) $invoice['status']),
        ]);

        $rows = [];
        foreach (($invoice['items'] ?? []) as $item) {
            $rows[] = [
                (string) $item['description'],
                number_format((float) $item['quantity'], 2, ',', ' '),
                format_money($item['unit_price']),
                format_money($item['total']),
            ];
        }

        $this->table($pdf, $y, ['Acte', 'Qté', 'Prix unitaire', 'Total'], $rows, [245, 55, 105, 110], ['left', 'right', 'right', 'right']);
        $this->totals($pdf, $y, [
            'Total facture' => format_money($invoice['total_amount']),
            'Deja paye' => format_money($invoice['paid_amount']),
            'Reste a payer' => format_money(max(0, (float) $invoice['total_amount'] - (float) $invoice['paid_amount'])),
        ]);

        if (!empty($invoice['notes'])) {
            $this->sectionTitle($pdf, $y, 'Notes');
            $y = $pdf->wrappedText(self::LEFT, $y, self::CONTENT, (string) $invoice['notes'], 10, false, '#475569');
        }

        $this->footer($pdf);

        return $pdf->output();
    }

    public function receipt(array $cabinet, array $payment): string
    {
        $pdf = new SimplePdf();
        $y = $this->header($pdf, $cabinet, 'RECU DE PAIEMENT', (string) $payment['reference']);

        $this->twoColumns($pdf, $y, [
            'Patient' => full_name($payment),
            'Reference patient' => (string) ($payment['patient_reference'] ?? '-'),
            'Telephone' => (string) ($payment['phone'] ?? '-'),
            'Facture' => (string) ($payment['invoice_number'] ?: '-'),
        ], [
            'Date de paiement' => format_date($payment['paid_at'] ?? null) . ' a ' . format_time($payment['paid_at'] ?? null),
            'Methode' => (string) ($payment['method_name'] ?? '-'),
            'Statut' => $this->paymentStatus((string) $payment['status']),
            'Ajoute par' => (string) ($payment['created_by_name'] ?: '-'),
        ]);

        $this->amountBox($pdf, $y, 'Montant recu', format_money($payment['amount']), '#10b981');
        $this->keyValues($pdf, $y, [
            'Reference de paiement' => (string) $payment['reference'],
            'Montant facture' => $payment['invoice_id'] ? format_money($payment['invoice_total']) : '-',
            'Reste a payer' => $payment['invoice_id'] ? format_money($payment['invoice_remaining']) : '-',
            'Notes' => (string) ($payment['notes'] ?: 'Aucune note'),
        ]);

        $this->footer($pdf, 'Recu genere automatiquement par Let\'s Smile.');

        return $pdf->output();
    }

    public function quote(array $cabinet, array $quote): string
    {
        $pdf = new SimplePdf();
        $y = $this->header($pdf, $cabinet, 'DEVIS', (string) $quote['number']);

        $this->twoColumns($pdf, $y, [
            'Patient' => full_name($quote),
            'Reference patient' => (string) ($quote['patient_reference'] ?? '-'),
            'Telephone' => (string) ($quote['phone'] ?? '-'),
            'Email' => (string) ($quote['email'] ?? '-'),
        ], [
            'Date du devis' => format_date($quote['issue_date'] ?? null),
            'Valable jusqu au' => format_date($quote['valid_until'] ?? null),
            'Plan de traitement' => (string) ($quote['treatment_title'] ?: '-'),
            'Statut' => $this->quoteStatus((string) ($quote['status'] ?? 'draft')),
        ]);

        $rows = [];
        foreach (($quote['items'] ?? []) as $item) {
            $rows[] = [
                (string) $item['description'],
                number_format((float) $item['quantity'], 2, ',', ' '),
                format_money($item['unit_price']),
                format_money($item['total']),
            ];
        }

        $this->table($pdf, $y, ['Prestation', 'Qté', 'Prix unitaire', 'Total'], $rows, [245, 55, 105, 110], ['left', 'right', 'right', 'right']);
        $this->totals($pdf, $y, ['Total devis' => format_money($quote['total_amount'])]);

        $this->sectionTitle($pdf, $y, 'Conditions');
        $y = $pdf->wrappedText(self::LEFT, $y, self::CONTENT, 'Ce devis est etabli en Ariary malgache. Il pourra etre transforme en facture apres acceptation du patient.', 10, false, '#475569');
        $this->footer($pdf);

        return $pdf->output();
    }

    public function patientRecord(array $cabinet, array $record): string
    {
        $patient = $record['patient'];
        $pdf = new SimplePdf();
        $y = $this->header($pdf, $cabinet, 'DOSSIER PATIENT', (string) $patient['reference']);

        $this->twoColumns($pdf, $y, [
            'Patient' => full_name($patient),
            'Date de naissance' => format_date($patient['birth_date'] ?? null),
            'Age' => $patient['age'] !== null ? $patient['age'] . ' ans' : '-',
            'Statut' => $patient['status'] === 'inactive' ? 'Inactif' : 'Actif',
        ], [
            'Telephone' => (string) ($patient['phone'] ?: '-'),
            'Email' => (string) ($patient['email'] ?: '-'),
            'Adresse' => (string) ($patient['address'] ?: '-'),
            'Profession' => (string) ($patient['profession'] ?: '-'),
        ]);

        $this->sectionTitle($pdf, $y, 'Informations medicales');
        $this->keyValues($pdf, $y, [
            'Groupe sanguin' => (string) ($patient['blood_group'] ?: '-'),
            'Allergies' => (string) ($patient['allergies'] ?: 'Aucune connue'),
            'Antecedents' => (string) ($patient['medical_history'] ?: '-'),
            'Medications' => (string) ($patient['current_medications'] ?: '-'),
            'Commentaires' => (string) ($patient['medical_notes'] ?: '-'),
        ]);

        $this->table($pdf, $y, ['Date', 'Type', 'Praticien', 'Statut'], $record['appointments'], [92, 170, 155, 98], ['left', 'left', 'left', 'left'], 'Rendez-vous recents');
        $this->table($pdf, $y, ['Reference', 'Traitement', 'Progression', 'Montant'], $record['treatments'], [95, 215, 95, 110], ['left', 'left', 'right', 'right'], 'Plans de traitement');
        $this->table($pdf, $y, ['Numero', 'Date', 'Statut', 'Total'], $record['invoices'], [115, 110, 145, 145], ['left', 'left', 'left', 'right'], 'Factures');
        $this->table($pdf, $y, ['Reference', 'Date', 'Methode', 'Montant'], $record['payments'], [130, 110, 145, 130], ['left', 'left', 'left', 'right'], 'Paiements');
        $this->table($pdf, $y, ['Document', 'Categorie', 'Date'], $record['documents'], [250, 140, 125], ['left', 'left', 'left'], 'Documents');

        $this->footer($pdf, 'Dossier patient confidentiel - acces reserve au cabinet.');

        return $pdf->output();
    }

    private function header(SimplePdf $pdf, array $cabinet, string $title, string $number): float
    {
        $pdf->rect(40, 32, self::CONTENT, 76, '#f8fbff', '#dbeafe');
        $pdf->text(58, 58, (string) ($cabinet['name'] ?? 'Let\'s Smile'), 18, true, '#0f172a');
        $pdf->text(58, 78, trim((string) (($cabinet['address'] ?? '') . ' ' . ($cabinet['city'] ?? ''))), 9, false, '#64748b');
        $pdf->text(58, 94, trim((string) (($cabinet['phone'] ?? '') . '  ' . ($cabinet['email'] ?? ''))), 9, false, '#64748b');
        $pdf->text(self::RIGHT - 16, 58, $title, 20, true, '#2563eb', 'right');
        $pdf->text(self::RIGHT - 16, 82, $number, 11, true, '#334155', 'right');
        $pdf->line(40, 124, self::RIGHT, 124, '#dbeafe', 1.2);

        return 148;
    }

    private function twoColumns(SimplePdf $pdf, float &$y, array $left, array $right): void
    {
        $height = 118;
        $this->ensureSpace($pdf, $y, $height);
        $pdf->rect(self::LEFT, $y, 250, $height, '#ffffff', '#e2e8f0');
        $pdf->rect(305, $y, 250, $height, '#ffffff', '#e2e8f0');
        $this->keyValueBlock($pdf, self::LEFT + 14, $y + 22, $left);
        $this->keyValueBlock($pdf, 319, $y + 22, $right);
        $y += $height + 24;
    }

    private function keyValueBlock(SimplePdf $pdf, float $x, float $y, array $values): void
    {
        foreach ($values as $label => $value) {
            $pdf->text($x, $y, (string) $label, 8.5, false, '#64748b');
            $pdf->text($x + 105, $y, (string) $value, 9.5, true, '#0f172a');
            $y += 22;
        }
    }

    private function keyValues(SimplePdf $pdf, float &$y, array $values): void
    {
        foreach ($values as $label => $value) {
            $this->ensureSpace($pdf, $y, 26);
            $pdf->text(self::LEFT, $y, (string) $label, 9, false, '#64748b');
            $y = $pdf->wrappedText(190, $y, 365, (string) $value, 9.5, true, '#0f172a', 13);
            $pdf->line(self::LEFT, $y + 2, self::RIGHT, $y + 2, '#f1f5f9');
            $y += 12;
        }
    }

    private function table(SimplePdf $pdf, float &$y, array $headers, array $rows, array $widths, array $aligns, string $title = ''): void
    {
        if ($title !== '') {
            $this->sectionTitle($pdf, $y, $title);
        }

        $this->ensureSpace($pdf, $y, 52);
        $x = self::LEFT;
        $pdf->rect($x, $y, array_sum($widths), 24, '#eef6ff', '#dbeafe');

        foreach ($headers as $index => $header) {
            $pdf->text($x + 8, $y + 16, (string) $header, 8.5, true, '#1d4ed8');
            $x += $widths[$index];
        }

        $y += 24;

        if ($rows === []) {
            $pdf->rect(self::LEFT, $y, array_sum($widths), 28, '#ffffff', '#e2e8f0');
            $pdf->text(self::LEFT + 8, $y + 18, 'Aucune donnee', 9, false, '#64748b');
            $y += 38;
            return;
        }

        foreach ($rows as $row) {
            $this->ensureSpace($pdf, $y, 34);
            $x = self::LEFT;
            $pdf->rect($x, $y, array_sum($widths), 28, '#ffffff', '#e2e8f0');

            foreach ($row as $index => $value) {
                $align = $aligns[$index] ?? 'left';
                $textX = $x + 8;

                if ($align === 'right') {
                    $textX = $x + $widths[$index] - 8;
                }

                $pdf->text($textX, $y + 18, (string) $value, 8.5, false, '#334155', $align);
                $x += $widths[$index];
            }

            $y += 28;
        }

        $y += 16;
    }

    private function totals(SimplePdf $pdf, float &$y, array $values): void
    {
        $this->ensureSpace($pdf, $y, 34 + (count($values) * 24));
        $x = 335;
        $width = 220;
        $pdf->rect($x, $y, $width, 20 + (count($values) * 24), '#f8fafc', '#e2e8f0');
        $lineY = $y + 22;

        foreach ($values as $label => $value) {
            $pdf->text($x + 14, $lineY, (string) $label, 9.5, false, '#64748b');
            $pdf->text($x + $width - 14, $lineY, (string) $value, 10, true, '#0f172a', 'right');
            $lineY += 24;
        }

        $y += 34 + (count($values) * 24);
    }

    private function amountBox(SimplePdf $pdf, float &$y, string $label, string $amount, string $color): void
    {
        $this->ensureSpace($pdf, $y, 84);
        $pdf->rect(self::LEFT, $y, self::CONTENT, 74, '#f8fafc', '#dbeafe');
        $pdf->text(self::LEFT + 18, $y + 30, $label, 11, false, '#64748b');
        $pdf->text(self::RIGHT - 18, $y + 45, $amount, 28, true, $color, 'right');
        $y += 94;
    }

    private function sectionTitle(SimplePdf $pdf, float &$y, string $title): void
    {
        $this->ensureSpace($pdf, $y, 34);
        $pdf->text(self::LEFT, $y, $title, 13, true, '#0f172a');
        $pdf->line(self::LEFT, $y + 10, self::RIGHT, $y + 10, '#e2e8f0');
        $y += 28;
    }

    private function ensureSpace(SimplePdf $pdf, float &$y, float $needed): void
    {
        if ($y + $needed <= self::BOTTOM) {
            return;
        }

        $this->footer($pdf);
        $pdf->addPage();
        $y = 56;
    }

    private function footer(SimplePdf $pdf, string $note = 'Document genere automatiquement par Let\'s Smile.'): void
    {
        $pdf->line(self::LEFT, 810, self::RIGHT, 810, '#e2e8f0');
        $pdf->text(self::LEFT, 828, $note, 8, false, '#94a3b8');
        $pdf->text(self::RIGHT, 828, date('d/m/Y H:i'), 8, false, '#94a3b8', 'right');
    }

    private function invoiceStatus(string $status): string
    {
        return [
            'draft' => 'Brouillon',
            'pending' => 'En attente',
            'paid' => 'Payee',
            'partial' => 'Partielle',
            'overdue' => 'Impayee',
            'cancelled' => 'Annulee',
            'credit_note' => 'Avoir',
        ][$status] ?? $status;
    }

    private function paymentStatus(string $status): string
    {
        return [
            'received' => 'Recu',
            'pending' => 'En attente',
            'failed' => 'Echoue',
            'refunded' => 'Rembourse',
            'cancelled' => 'Annule',
        ][$status] ?? $status;
    }

    private function quoteStatus(string $status): string
    {
        return [
            'draft' => 'Brouillon',
            'sent' => 'Envoye',
            'accepted' => 'Accepte',
            'refused' => 'Refuse',
            'expired' => 'Expire',
            'cancelled' => 'Annule',
        ][$status] ?? $status;
    }
}
