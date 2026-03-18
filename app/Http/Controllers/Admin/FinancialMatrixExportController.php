<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FinancialMatrixDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialMatrixExportController extends Controller
{
    public function csv(Request $request, FinancialMatrixDataService $service): StreamedResponse
    {
        abort_unless($this->canExport($request->user()), 403);

        [$from, $to] = $this->resolveDateRange($request);
        $snapshot = $service->snapshot($from, $to);
        $fileName = 'financial-matrix-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($snapshot): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['Metric', 'Value']);
            fputcsv($out, ['Gross Revenue (Range)', (string) ($snapshot['matrix']['gross_range'] ?? 0)]);
            fputcsv($out, ['Gross Revenue (Last 7d)', (string) ($snapshot['matrix']['gross_7d'] ?? 0)]);
            fputcsv($out, ['Platform Commission (Range)', (string) ($snapshot['matrix']['commission_range'] ?? 0)]);
            fputcsv($out, ['Driver Payouts (Range)', (string) ($snapshot['matrix']['payouts_range'] ?? 0)]);
            fputcsv($out, ['Pending Payouts', (string) ($snapshot['matrix']['pending_payouts'] ?? 0)]);
            fputcsv($out, ['Take Rate (%)', (string) ($snapshot['matrix']['take_rate'] ?? 0)]);

            fputcsv($out, []);
            fputcsv($out, ['Date', 'Gross', 'Transactions']);

            foreach (($snapshot['daily_rows'] ?? []) as $row) {
                fputcsv($out, [
                    (string) ($row['day'] ?? ''),
                    (string) ($row['gross'] ?? 0),
                    (string) ($row['txns'] ?? 0),
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function pdf(Request $request, FinancialMatrixDataService $service)
    {
        abort_unless($this->canExport($request->user()), 403);

        [$from, $to] = $this->resolveDateRange($request);
        $snapshot = $service->snapshot($from, $to);

        $pdf = Pdf::loadView('exports.financial-matrix-pdf', [
            'snapshot' => $snapshot,
            'generatedAt' => now()->toDateTimeString(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('financial-matrix-' . now()->format('Ymd-His') . '.pdf');
    }

    public function xlsx(Request $request, FinancialMatrixDataService $service): BinaryFileResponse
    {
        abort_unless($this->canExport($request->user()), 403);

        [$from, $to] = $this->resolveDateRange($request);
        $snapshot = $service->snapshot($from, $to);

        $tmp = tempnam(sys_get_temp_dir(), 'fin-matrix-');
        if ($tmp === false) {
            abort(500, 'Failed to initialize export file.');
        }

        $xlsxPath = $tmp . '.xlsx';
        @unlink($tmp);

        $writer = new Writer();
        $writer->openToFile($xlsxPath);

        $writer->addRow(Row::fromValues(['Financial Matrix Report']));
        $writer->addRow(Row::fromValues(['From', $snapshot['period']['from'] ?? '']));
        $writer->addRow(Row::fromValues(['To', $snapshot['period']['to'] ?? '']));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Metric', 'Value']));
        $writer->addRow(Row::fromValues(['Gross Revenue (Range)', (float) ($snapshot['matrix']['gross_range'] ?? 0)]));
        $writer->addRow(Row::fromValues(['Gross Revenue (Last 7d)', (float) ($snapshot['matrix']['gross_7d'] ?? 0)]));
        $writer->addRow(Row::fromValues(['Platform Commission (Range)', (float) ($snapshot['matrix']['commission_range'] ?? 0)]));
        $writer->addRow(Row::fromValues(['Driver Payouts (Range)', (float) ($snapshot['matrix']['payouts_range'] ?? 0)]));
        $writer->addRow(Row::fromValues(['Pending Payouts', (int) ($snapshot['matrix']['pending_payouts'] ?? 0)]));
        $writer->addRow(Row::fromValues(['Take Rate (%)', (float) ($snapshot['matrix']['take_rate'] ?? 0)]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Date', 'Gross', 'Transactions']));
        foreach (($snapshot['daily_rows'] ?? []) as $row) {
            $writer->addRow(Row::fromValues([
                (string) ($row['day'] ?? ''),
                (float) ($row['gross'] ?? 0),
                (int) ($row['txns'] ?? 0),
            ]));
        }

        $writer->close();

        return response()->download(
            $xlsxPath,
            'financial-matrix-' . now()->format('Ymd-His') . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(Request $request): array
    {
        $from = (string) $request->query('from', now()->subDays(29)->toDateString());
        $to = (string) $request->query('to', now()->toDateString());

        $fromDate = Carbon::parse($from)->toDateString();
        $toDate = Carbon::parse($to)->toDateString();

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    private function canExport(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $enumRole = $user->role?->value ?? (string) $user->role;
        if (in_array($enumRole, UserRole::managerRoles(), true)) {
            return true;
        }

        return method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['Super_admin', 'Admin', 'Officer', 'Accountant'])
            : false;
    }
}
