<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ComplianceReport;
use App\Models\Manager;
use App\Models\TaxRule;
use App\Modules\Compliance\DTOs\ComplianceReportDTO;
use App\Modules\Compliance\Services\ComplianceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ComplianceReportTest
 *
 * Verifies:
 * 1. A report can be requested (creates pending record)
 * 2. CSV generation produces a downloadable file
 * 3. JSON generation produces valid JSON
 * 4. Failed generation marks status = 'failed'
 */
class ComplianceReportTest extends TestCase
{
    use RefreshDatabase;

    private ComplianceReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->service = app(ComplianceReportService::class);
    }

    // -----------------------------------------------------------------------
    // Request / Pending lifecycle
    // -----------------------------------------------------------------------

    public function test_requesting_report_creates_pending_record(): void
    {
        $dto = new ComplianceReportDTO(
            reportType:  'daily_ride_summary',
            format:      'csv',
            periodFrom:  '2025-01-01',
            periodTo:    '2025-01-31',
            requestedBy: 1,
        );

        $report = $this->service->request($dto);

        $this->assertInstanceOf(ComplianceReport::class, $report);
        $this->assertSame('pending', $report->status);
        $this->assertSame('daily_ride_summary', $report->report_type);
        $this->assertDatabaseHas('compliance_reports', ['id' => $report->id, 'status' => 'pending']);
    }

    // -----------------------------------------------------------------------
    // CSV generation
    // -----------------------------------------------------------------------

    public function test_csv_report_generates_and_marks_ready(): void
    {
        $report = ComplianceReport::create([
            'report_type'  => 'commission_breakdown',
            'format'       => 'csv',
            'period_from'  => '2025-01-01',
            'period_to'    => '2025-01-31',
            'status'       => 'pending',
            'requested_by' => 1,
        ]);

        $generated = $this->service->generate($report->id);

        $this->assertSame('ready', $generated->status);
        $this->assertNotNull($generated->file_path);

        Storage::disk('local')->assertExists($generated->file_path);
    }

    public function test_csv_report_file_is_valid_utf8(): void
    {
        $report = ComplianceReport::create([
            'report_type'  => 'daily_ride_summary',
            'format'       => 'csv',
            'period_from'  => '2025-01-01',
            'period_to'    => '2025-01-31',
            'status'       => 'pending',
            'requested_by' => 1,
        ]);

        $generated = $this->service->generate($report->id);

        $content = Storage::disk('local')->get($generated->file_path);
        $this->assertNotNull($content);
        $this->assertTrue(mb_check_encoding($content, 'UTF-8'));
    }

    // -----------------------------------------------------------------------
    // JSON generation
    // -----------------------------------------------------------------------

    public function test_json_report_generates_valid_json(): void
    {
        $report = ComplianceReport::create([
            'report_type'  => 'fraud_incident_report',
            'format'       => 'json',
            'period_from'  => '2025-01-01',
            'period_to'    => '2025-01-31',
            'status'       => 'pending',
            'requested_by' => 1,
        ]);

        $generated = $this->service->generate($report->id);

        $this->assertSame('ready', $generated->status);

        $content = Storage::disk('local')->get($generated->file_path);
        $decoded = json_decode($content, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('report', $decoded);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertSame('fraud_incident_report', $decoded['report']);
    }

    // -----------------------------------------------------------------------
    // Summary data is stored
    // -----------------------------------------------------------------------

    public function test_generated_report_stores_row_count_in_summary(): void
    {
        $report = ComplianceReport::create([
            'report_type'  => 'refund_report',
            'format'       => 'json',
            'period_from'  => '2025-01-01',
            'period_to'    => '2025-01-31',
            'status'       => 'pending',
            'requested_by' => 1,
        ]);

        $generated = $this->service->generate($report->id);

        $summary = $generated->summary_data;
        $this->assertArrayHasKey('row_count', $summary);
        $this->assertArrayHasKey('generated_at', $summary);
    }

    // -----------------------------------------------------------------------
    // Failure handling
    // -----------------------------------------------------------------------

    public function test_unknown_report_type_marks_status_as_failed(): void
    {
        $report = ComplianceReport::forceCreate([
            'report_type'  => 'invalid_type',
            'format'       => 'csv',
            'period_from'  => '2025-01-01',
            'period_to'    => '2025-01-31',
            'status'       => 'pending',
            'requested_by' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->service->generate($report->id);
        } catch (\Throwable $e) {
            $report->refresh();
            $this->assertSame('failed', $report->status);
            throw $e;
        }
    }
}
