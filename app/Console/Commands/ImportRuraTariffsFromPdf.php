<?php

namespace App\Console\Commands;

use App\Models\RuraTariff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser;

class ImportRuraTariffsFromPdf extends Command
{
    protected $signature = 'rura:import-pdf
        {path? : Absolute or relative path to the tariff PDF}
        {--sync-corridors : Rebuild corridors/rides from imported tariffs}
        {--ocr : Use OCR pipeline for scanned PDFs (requires pdftoppm and tesseract binaries)}
        {--http-ocr : Use OCR.Space API OCR fallback for scanned PDFs}
        {--ocr-lang=eng : OCR language code passed to tesseract}
        {--fallback-seeder : If PDF text extraction fails, use RuraTariffSeeder as fallback}
        {--dry-run : Parse and show summary without writing to database}';

    protected $description = 'Import Kigali RURA tariff rows from a PDF document';

    public function handle(): int
    {
        $path = (string) ($this->argument('path') ?: base_path('City_of_Kigali_Public_Transport_Tariff_March_2024__2_ (1).pdf'));

        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            $this->error("PDF file not found: {$path}");

            return self::FAILURE;
        }

        $this->info("Parsing PDF: {$path}");

        $text = '';

        if ($this->option('ocr')) {
            $this->line('OCR mode enabled. Attempting scanned PDF extraction...');

            try {
                $text = $this->extractTextUsingOcr($path, (string) $this->option('ocr-lang'));
            } catch (\Throwable $e) {
                $this->warn('OCR extraction failed: '.$e->getMessage());
            }
        }

        if ($text === '' && $this->option('http-ocr')) {
            $this->line('HTTP OCR mode enabled. Attempting OCR.Space extraction...');

            try {
                $text = $this->extractTextUsingHttpOcr($path, (string) $this->option('ocr-lang'));
            } catch (\Throwable $e) {
                $this->warn('HTTP OCR extraction failed: '.$e->getMessage());
            }
        }

        if ($text === '') {
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($path);
                $text = $pdf->getText();
            } catch (\Throwable $e) {
                $this->error('Unable to parse PDF: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $rows = $this->extractTariffRows($text);

        if (empty($rows)) {
            $this->warn('No tariff rows were detected from PDF text extraction.');

            if (! $this->option('fallback-seeder')) {
                $this->error('Re-run with --fallback-seeder to load official tariff seed data when PDF is scan-only.');

                return self::FAILURE;
            }

            $this->warn('Using fallback seeder: Database\\Seeders\\RuraTariffSeeder');
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\RuraTariffSeeder', '--force' => true]);

            if ($this->option('sync-corridors')) {
                $this->call('db:seed', ['--class' => 'Database\\Seeders\\ZoneCorridorSeeder', '--force' => true]);
                $this->info('Zone/corridor and ride fare sync completed.');
            }

            return self::SUCCESS;
        }

        $this->info('Detected tariff rows: '.count($rows));

        if ($this->option('dry-run')) {
            $this->table(['route_code', 'corridor', 'origin_stop', 'destination_stop', 'fare_rwf'], array_slice($rows, 0, 20));

            return self::SUCCESS;
        }

        $createdOrUpdated = 0;

        foreach ($rows as $row) {
            RuraTariff::query()->updateOrCreate(
                [
                    'route_code' => $row['route_code'],
                    'origin_stop' => $row['origin_stop'],
                    'destination_stop' => $row['destination_stop'],
                ],
                $row
            );
            $createdOrUpdated++;
        }

        $this->info("Imported rows: {$createdOrUpdated}");

        if ($this->option('sync-corridors')) {
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\ZoneCorridorSeeder', '--force' => true]);
            $this->info('Zone/corridor and ride fare sync completed.');
        }

        return self::SUCCESS;
    }

    private function extractTextUsingOcr(string $pdfPath, string $lang): string
    {
        if (! $this->binaryExists('pdftoppm')) {
            throw new \RuntimeException('pdftoppm binary is not installed.');
        }

        if (! $this->binaryExists('tesseract')) {
            throw new \RuntimeException('tesseract binary is not installed.');
        }

        $tmpDir = storage_path('app/tmp/rura-ocr-'.uniqid('', true));

        if (! @mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            throw new \RuntimeException('Unable to create OCR temp directory.');
        }

        $imagePrefix = $tmpDir.'/page';
        $convertCmd = sprintf(
            'pdftoppm -png %s %s 2>&1',
            escapeshellarg($pdfPath),
            escapeshellarg($imagePrefix)
        );

        exec($convertCmd, $convertOut, $convertCode);

        if ($convertCode !== 0) {
            $this->cleanupDirectory($tmpDir);
            throw new \RuntimeException('pdftoppm failed: '.implode("\n", $convertOut));
        }

        $images = glob($imagePrefix.'-*.png') ?: [];
        sort($images);

        if (empty($images)) {
            $this->cleanupDirectory($tmpDir);
            throw new \RuntimeException('No page images generated by pdftoppm.');
        }

        $text = '';

        foreach ($images as $image) {
            $ocrCmd = sprintf(
                'tesseract %s stdout -l %s --psm 6 2>/dev/null',
                escapeshellarg($image),
                escapeshellarg($lang)
            );

            exec($ocrCmd, $ocrOut, $ocrCode);

            if ($ocrCode !== 0) {
                continue;
            }

            $text .= "\n".implode("\n", $ocrOut);
        }

        $this->cleanupDirectory($tmpDir);

        return trim($text);
    }

    private function binaryExists(string $binary): bool
    {
        $output = trim((string) shell_exec('command -v '.escapeshellarg($binary).' 2>/dev/null'));

        return $output !== '';
    }

    private function cleanupDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }

    private function extractTextUsingHttpOcr(string $pdfPath, string $lang): string
    {
        $endpoint = (string) config('services.ocr_space.endpoint', 'https://api.ocr.space/parse/image');
        $apiKey = (string) config('services.ocr_space.api_key', 'helloworld');
        $timeout = (int) config('services.ocr_space.timeout', 60);

        if ($apiKey === '') {
            throw new \RuntimeException('OCR_SPACE_API_KEY is not configured.');
        }

        $response = Http::timeout($timeout)
            ->asMultipart()
            ->withHeaders([
                'apikey' => $apiKey,
            ])
            ->attach(
                'file',
                file_get_contents($pdfPath) ?: '',
                basename($pdfPath)
            )
            ->post($endpoint, [
                'language' => $lang,
                'isOverlayRequired' => 'false',
                'isTable' => 'true',
                'OCREngine' => '2',
                'scale' => 'true',
            ]);

        if (! $response->ok()) {
            throw new \RuntimeException('HTTP OCR request failed with status '.$response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('Unexpected OCR response payload.');
        }

        if (($payload['IsErroredOnProcessing'] ?? false) === true) {
            $errors = $payload['ErrorMessage'] ?? ['Unknown OCR processing error.'];
            $errorText = is_array($errors) ? implode('; ', array_map('strval', $errors)) : (string) $errors;

            throw new \RuntimeException($errorText);
        }

        $results = $payload['ParsedResults'] ?? [];

        if (! is_array($results)) {
            return '';
        }

        $combined = '';

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $combined .= "\n".((string) ($result['ParsedText'] ?? ''));
        }

        return trim($combined);
    }

    /**
     * @return array<int, array{route_code: string, corridor: string, origin_stop: string, destination_stop: string, fare_rwf: int}>
     */
    private function extractTariffRows(string $text): array
    {
        $normalized = strtoupper($text);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        preg_match_all(
            '/\b(\d{3})\s+([A-Z])\s+([A-Z][A-Z\s\-\(\)]+?(?:BUS\s+PARK|BUS\s+TERMINAL|TERMINAL|PARK))\s+([A-Z][A-Z\s\-\(\)]+?(?:BUS\s+PARK|BUS\s+TERMINAL|TERMINAL|PARK))\s+(\d{2,5})\b/',
            $normalized,
            $matches,
            PREG_SET_ORDER
        );

        $rows = [];

        foreach ($matches as $match) {
            $routeCode = trim($match[1]);
            $corridor = trim($match[2]);
            $origin = $this->normalizeStop($match[3]);
            $destination = $this->normalizeStop($match[4]);
            $fare = (int) trim($match[5]);

            if ($origin === '' || $destination === '' || $fare <= 0) {
                continue;
            }

            $key = implode('|', [$routeCode, $origin, $destination]);

            $rows[$key] = [
                'route_code' => $routeCode,
                'corridor' => $corridor,
                'origin_stop' => $origin,
                'destination_stop' => $destination,
                'fare_rwf' => $fare,
            ];
        }

        return array_values($rows);
    }

    private function normalizeStop(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return preg_replace('/\s*-\s*/', ' - ', $value) ?? $value;
    }
}
