<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OperationsIntelligenceDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsIntelligenceExportController extends Controller
{
    public function csv(Request $request, OperationsIntelligenceDataService $service): StreamedResponse
    {
        abort_unless($this->canExport($request->user()), 403);

        [$from, $to] = $this->resolveDateRange($request);
        $snapshot = $service->snapshot($from, $to);
        $fileName = 'operations-intelligence-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($snapshot): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['Section', 'Metric', 'Value']);

            foreach ($snapshot['kpis'] as $metric => $value) {
                fputcsv($out, ['KPIs', (string) $metric, (string) $value]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Daily Trend', 'Day', 'Rides']);

            foreach (($snapshot['daily_trend']['labels'] ?? []) as $index => $label) {
                $value = $snapshot['daily_trend']['values'][$index] ?? 0;
                fputcsv($out, ['Daily Trend', (string) $label, (string) $value]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Status Mix', 'Status', 'Count']);

            foreach (($snapshot['status_mix']['labels'] ?? []) as $index => $label) {
                $value = $snapshot['status_mix']['values'][$index] ?? 0;
                fputcsv($out, ['Status Mix', (string) $label, (string) $value]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Top Routes', 'From', 'To', 'Trips']);

            foreach (($snapshot['top_routes'] ?? []) as $route) {
                fputcsv($out, [
                    'Top Routes',
                    (string) ($route['from'] ?? ''),
                    (string) ($route['to'] ?? ''),
                    (string) ($route['total'] ?? 0),
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function pdf(Request $request, OperationsIntelligenceDataService $service)
    {
        abort_unless($this->canExport($request->user()), 403);

        [$from, $to] = $this->resolveDateRange($request);
        $snapshot = $service->snapshot($from, $to);

        $pdf = Pdf::loadView('exports.operations-intelligence-pdf', [
            'snapshot' => $snapshot,
            'generatedAt' => now()->toDateTimeString(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('operations-intelligence-' . now()->format('Ymd-His') . '.pdf');
    }

    public function xlsx(Request $request, OperationsIntelligenceDataService $service): BinaryFileResponse
    {
        abort_unless($this->canExport($request->user()), 403);

        [$from, $to] = $this->resolveDateRange($request);
        $snapshot = $service->snapshot($from, $to);

        $tmp = tempnam(sys_get_temp_dir(), 'ops-intel-');
        if ($tmp === false) {
            abort(500, 'Failed to initialize export file.');
        }

        $xlsxPath = $tmp . '.xlsx';
        @unlink($tmp);

        $writer = new Writer();
        $writer->openToFile($xlsxPath);

        $writer->addRow(Row::fromValues(['Operations Intelligence Report']));
        $writer->addRow(Row::fromValues(['From', $snapshot['period']['from'] ?? '']));
        $writer->addRow(Row::fromValues(['To', $snapshot['period']['to'] ?? '']));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['KPI', 'Value']));
        foreach (($snapshot['kpis'] ?? []) as $metric => $value) {
            $writer->addRow(Row::fromValues([(string) $metric, (string) $value]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Daily Trend', 'Rides']));
        foreach (($snapshot['daily_trend']['labels'] ?? []) as $index => $label) {
            $writer->addRow(Row::fromValues([
                (string) $label,
                (int) (($snapshot['daily_trend']['values'][$index] ?? 0)),
            ]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Status Mix', 'Count']));
        foreach (($snapshot['status_mix']['labels'] ?? []) as $index => $label) {
            $writer->addRow(Row::fromValues([
                (string) $label,
                (int) (($snapshot['status_mix']['values'][$index] ?? 0)),
            ]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['From', 'To', 'Trips']));
        foreach (($snapshot['top_routes'] ?? []) as $route) {
            $writer->addRow(Row::fromValues([
                (string) ($route['from'] ?? ''),
                (string) ($route['to'] ?? ''),
                (int) ($route['total'] ?? 0),
            ]));
        }

        $writer->close();

        return response()->download(
            $xlsxPath,
            'operations-intelligence-' . now()->format('Ymd-His') . '.xlsx',
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
