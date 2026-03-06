<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialStatementPdfService
{
    public function generateBalanceSheet(Carbon $from, Carbon $to, string $printedBy): array
    {
        $balances = $this->accountBalancesAsOf($to);

        $assets = $balances->where('type', 'asset')->values();
        $liabilities = $balances->where('type', 'liability')->values();

        $equityAmount = $this->retainedEarningsAsOf($to);
        $equity = collect([[
            'name' => 'Retained Earnings',
            'amount' => $equityAmount,
        ]]);

        $totalAssets = (float) $assets->sum('amount');
        $totalLiabilities = (float) $liabilities->sum('amount');
        $totalEquity = (float) $equity->sum('amount');

        return $this->render(
            reportCode: 'balance-sheet',
            reportTitle: 'Balance Sheet',
            from: $from,
            to: $to,
            printedBy: $printedBy,
            summary: [
                'Total Assets (RWF)' => $this->money($totalAssets),
                'Total Liabilities (RWF)' => $this->money($totalLiabilities),
                'Total Equity (RWF)' => $this->money($totalEquity),
                'Liabilities + Equity (RWF)' => $this->money($totalLiabilities + $totalEquity),
            ],
            sections: [
                [
                    'title' => 'Assets',
                    'columns' => ['Account', 'Amount (RWF)'],
                    'rows' => $assets->map(fn ($r) => [$r['name'], $this->money($r['amount'])])->all(),
                ],
                [
                    'title' => 'Liabilities',
                    'columns' => ['Account', 'Amount (RWF)'],
                    'rows' => $liabilities->map(fn ($r) => [$r['name'], $this->money($r['amount'])])->all(),
                ],
                [
                    'title' => 'Equity',
                    'columns' => ['Account', 'Amount (RWF)'],
                    'rows' => $equity->map(fn ($r) => [$r['name'], $this->money($r['amount'])])->all(),
                ],
            ],
            notes: [
                'Generated from ledger balances as of the report end date.',
            ],
        );
    }

    public function generateIncomeStatement(Carbon $from, Carbon $to, string $printedBy): array
    {
        $period = $this->accountMovementsBetween($from, $to);

        $revenues = $period->where('type', 'revenue')->values();
        $expenses = $period->where('type', 'expense')->values();

        $totalRevenue = (float) $revenues->sum('amount');
        $totalExpense = (float) $expenses->sum('amount');
        $netIncome = $totalRevenue - $totalExpense;

        $supplemental = $this->supplementalMoneyData($from, $to);

        return $this->render(
            reportCode: 'income-statement',
            reportTitle: 'Income Statement',
            from: $from,
            to: $to,
            printedBy: $printedBy,
            summary: [
                'Total Revenue (RWF)' => $this->money($totalRevenue),
                'Total Expense (RWF)' => $this->money($totalExpense),
                'Net Income (RWF)' => $this->money($netIncome),
                'Completed Payments (RWF)' => $this->money($supplemental['completed_payments']),
                'Platform Commissions (RWF)' => $this->money($supplemental['platform_commissions']),
            ],
            sections: [
                [
                    'title' => 'Revenue Accounts',
                    'columns' => ['Account', 'Amount (RWF)'],
                    'rows' => $revenues->map(fn ($r) => [$r['name'], $this->money($r['amount'])])->all(),
                ],
                [
                    'title' => 'Expense Accounts',
                    'columns' => ['Account', 'Amount (RWF)'],
                    'rows' => $expenses->map(fn ($r) => [$r['name'], $this->money($r['amount'])])->all(),
                ],
            ],
            notes: [
                'Includes supplemental payment and commission totals for operational context.',
            ],
        );
    }

    public function generateCashFlow(Carbon $from, Carbon $to, string $printedBy): array
    {
        $supplemental = $this->supplementalMoneyData($from, $to);

        $inflows = (float) $supplemental['completed_payments'];
        $outflows = (float) $supplemental['processed_payouts'];
        $refunds = (float) $supplemental['refunds'];

        $opening = $this->cashBalanceAsOf($from->copy()->subDay());
        $closing = $this->cashBalanceAsOf($to);

        return $this->render(
            reportCode: 'cashflow',
            reportTitle: 'Cash Flow Statement',
            from: $from,
            to: $to,
            printedBy: $printedBy,
            summary: [
                'Opening Cash (RWF)' => $this->money($opening),
                'Cash Inflows (RWF)' => $this->money($inflows),
                'Cash Outflows (RWF)' => $this->money($outflows + $refunds),
                'Net Cash Movement (RWF)' => $this->money($inflows - ($outflows + $refunds)),
                'Closing Cash (RWF)' => $this->money($closing),
            ],
            sections: [
                [
                    'title' => 'Operating Cash Movements',
                    'columns' => ['Category', 'Amount (RWF)'],
                    'rows' => [
                        ['Completed payments received', $this->money($inflows)],
                        ['Driver payouts disbursed', $this->money($outflows)],
                        ['Refunds paid out', $this->money($refunds)],
                    ],
                ],
            ],
            notes: [
                'Cash balances are derived from platform cash accounts (Bank/Clearing).',
            ],
        );
    }

    public function generateCashbook(Carbon $from, Carbon $to, string $printedBy): array
    {
        $entries = DB::table('ledger_entries as le')
            ->join('ledger_accounts as la', 'la.id', '=', 'le.account_id')
            ->leftJoin('ledger_transactions as lt', 'lt.id', '=', 'le.transaction_id')
            ->whereBetween('le.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('le.created_at')
            ->limit(5000)
            ->get([
                'le.created_at',
                'lt.uuid as transaction_uuid',
                'la.name as account_name',
                'le.debit',
                'le.credit',
                'le.reference_type',
                'le.reference_id',
                'le.description',
            ]);

        $totalDebit = (float) $entries->sum('debit');
        $totalCredit = (float) $entries->sum('credit');

        $rows = $entries->map(function ($r) {
            return [
                Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                (string) ($r->transaction_uuid ?? 'N/A'),
                (string) ($r->account_name ?? 'N/A'),
                $this->money((float) $r->debit),
                $this->money((float) $r->credit),
                strtoupper((string) ($r->reference_type ?? 'N/A')),
                (string) ($r->reference_id ?? 'N/A'),
                (string) ($r->description ?? 'N/A'),
            ];
        })->all();

        return $this->render(
            reportCode: 'cashbook',
            reportTitle: 'Cashbook Records',
            from: $from,
            to: $to,
            printedBy: $printedBy,
            summary: [
                'Entry Count' => number_format(count($rows)),
                'Total Debit (RWF)' => $this->money($totalDebit),
                'Total Credit (RWF)' => $this->money($totalCredit),
            ],
            sections: [
                [
                    'title' => 'Ledger Transactions',
                    'columns' => ['Date', 'Txn UUID', 'Account', 'Debit', 'Credit', 'Ref Type', 'Ref ID', 'Description'],
                    'rows' => $rows,
                ],
            ],
            notes: [
                'Rows are sourced from ledger_entries and related transaction/account tables.',
            ],
            landscape: true,
        );
    }

    private function render(
        string $reportCode,
        string $reportTitle,
        Carbon $from,
        Carbon $to,
        string $printedBy,
        array $summary,
        array $sections,
        array $notes = [],
        bool $landscape = false,
    ): array {
        $printedAt = now();

        $pdf = Pdf::loadView('reports.financial-report-pdf', [
            'reportTitle' => $reportTitle,
            'reportCode' => $reportCode,
            'periodFrom' => $from->toDateString(),
            'periodTo' => $to->toDateString(),
            'printedBy' => $printedBy,
            'printedAt' => $printedAt->format('Y-m-d H:i:s'),
            'logoDataUri' => $this->logoDataUri(),
            'summary' => $summary,
            'sections' => $sections,
            'notes' => $notes,
        ])->setPaper('a4', $landscape ? 'landscape' : 'portrait');

        return [
            'filename' => $reportCode . '-' . $printedAt->format('Ymd-His') . '.pdf',
            'content' => $pdf->output(),
        ];
    }

    private function accountBalancesAsOf(Carbon $to)
    {
        if (! Schema::hasTable('ledger_accounts') || ! Schema::hasTable('ledger_entries')) {
            return collect();
        }

        return DB::table('ledger_accounts as la')
            ->leftJoin('ledger_entries as le', function ($join) use ($to) {
                $join->on('le.account_id', '=', 'la.id')
                    ->where('le.created_at', '<=', $to->endOfDay());
            })
            ->selectRaw('la.name, la.type, COALESCE(SUM(le.debit), 0) as debit_total, COALESCE(SUM(le.credit), 0) as credit_total')
            ->groupBy('la.id', 'la.name', 'la.type')
            ->orderBy('la.type')
            ->orderBy('la.name')
            ->get()
            ->map(function ($row) {
                $debit = (float) $row->debit_total;
                $credit = (float) $row->credit_total;

                $amount = match ($row->type) {
                    'asset', 'expense' => $debit - $credit,
                    default => $credit - $debit,
                };

                return [
                    'name' => $row->name,
                    'type' => $row->type,
                    'amount' => round($amount, 2),
                ];
            });
    }

    private function accountMovementsBetween(Carbon $from, Carbon $to)
    {
        if (! Schema::hasTable('ledger_accounts') || ! Schema::hasTable('ledger_entries')) {
            return collect();
        }

        return DB::table('ledger_accounts as la')
            ->leftJoin('ledger_entries as le', function ($join) use ($from, $to) {
                $join->on('le.account_id', '=', 'la.id')
                    ->whereBetween('le.created_at', [$from->startOfDay(), $to->endOfDay()]);
            })
            ->selectRaw('la.name, la.type, COALESCE(SUM(le.debit), 0) as debit_total, COALESCE(SUM(le.credit), 0) as credit_total')
            ->whereIn('la.type', ['revenue', 'expense'])
            ->groupBy('la.id', 'la.name', 'la.type')
            ->orderBy('la.type')
            ->orderBy('la.name')
            ->get()
            ->map(function ($row) {
                $debit = (float) $row->debit_total;
                $credit = (float) $row->credit_total;

                $amount = $row->type === 'expense'
                    ? $debit - $credit
                    : $credit - $debit;

                return [
                    'name' => $row->name,
                    'type' => $row->type,
                    'amount' => round($amount, 2),
                ];
            });
    }

    private function retainedEarningsAsOf(Carbon $to): float
    {
        $balances = $this->accountBalancesAsOf($to);
        $revenue = (float) $balances->where('type', 'revenue')->sum('amount');
        $expense = (float) $balances->where('type', 'expense')->sum('amount');

        return round($revenue - $expense, 2);
    }

    private function cashBalanceAsOf(Carbon $date): float
    {
        if (! Schema::hasTable('ledger_accounts') || ! Schema::hasTable('ledger_entries')) {
            return 0.0;
        }

        $rows = DB::table('ledger_accounts as la')
            ->leftJoin('ledger_entries as le', function ($join) use ($date) {
                $join->on('le.account_id', '=', 'la.id')
                    ->where('le.created_at', '<=', $date->endOfDay());
            })
            ->where('la.type', 'asset')
            ->where('la.owner_type', 'platform')
            ->where(function ($q) {
                $q->where('la.name', 'like', '%Bank%')
                    ->orWhere('la.name', 'like', '%Clearing%')
                    ->orWhere('la.name', 'like', '%Mobile Money%');
            })
            ->selectRaw('la.name, COALESCE(SUM(le.debit), 0) as debit_total, COALESCE(SUM(le.credit), 0) as credit_total')
            ->groupBy('la.id', 'la.name')
            ->get();

        return round((float) $rows->sum(fn ($r) => (float) $r->debit_total - (float) $r->credit_total), 2);
    }

    private function supplementalMoneyData(Carbon $from, Carbon $to): array
    {
        $data = [
            'completed_payments' => 0.0,
            'refunds' => 0.0,
            'processed_payouts' => 0.0,
            'platform_commissions' => 0.0,
        ];

        if (Schema::hasTable('payments')) {
            $data['completed_payments'] = (float) DB::table('payments')
                ->whereIn('status', ['COMPLETED', 'completed'])
                ->whereBetween(DB::raw('COALESCE(paid_at, created_at)'), [$from->startOfDay(), $to->endOfDay()])
                ->sum('amount');

            $data['refunds'] = (float) DB::table('payments')
                ->whereIn('status', ['REFUNDED', 'refunded'])
                ->whereBetween(DB::raw('COALESCE(refunded_at, updated_at)'), [$from->startOfDay(), $to->endOfDay()])
                ->sum('amount');
        }

        if (Schema::hasTable('driver_payouts')) {
            $data['processed_payouts'] = (float) DB::table('driver_payouts')
                ->where('status', 'processed')
                ->whereBetween('payout_date', [$from->toDateString(), $to->toDateString()])
                ->sum('payout_amount');
        }

        if (Schema::hasTable('platform_commissions')) {
            $data['platform_commissions'] = (float) DB::table('platform_commissions')
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->sum('commission_amount');
        }

        return $data;
    }

    private function logoDataUri(): ?string
    {
        $paths = [
            public_path('images/logo.png'),
            public_path('images/logo.svg'),
        ];

        foreach ($paths as $path) {
            if (! is_file($path)) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/png';
            $raw = file_get_contents($path);
            if ($raw === false) {
                continue;
            }

            return 'data:' . $mime . ';base64,' . base64_encode($raw);
        }

        return null;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', ',');
    }
}
