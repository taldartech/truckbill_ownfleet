<?php

namespace Tests\Unit\Services;

use App\Services\BulkUploadService;
use App\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BulkUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private BulkUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BulkUploadService();
    }

    public function test_download_template_csv_and_xlsx(): void
    {
        // CSV format
        $this->get('/?format=csv');
        $csvResponse = $this->service->downloadTemplate(['Name', 'Phone', 'Address'], 'drivers.xlsx', ['Name']);
        $this->assertSame(200, $csvResponse->getStatusCode());
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('Content-Type'));

        // XLSX format
        $this->get('/?format=xlsx');
        $xlsxResponse = $this->service->downloadTemplate(['Name', 'Phone', 'Address'], 'drivers.csv', ['Name']);
        $this->assertSame(200, $xlsxResponse->getStatusCode());
        $this->assertStringContainsString('spreadsheet', $xlsxResponse->headers->get('Content-Type'));
    }

    public function test_process_unreadable_or_unsupported_file(): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'test_unreadable_');
        $nonExistent = new UploadedFile($temp, 'test.csv', 'text/csv', null, true);
        unlink($temp);

        $result = $this->service->process($nonExistent, Tenant::class, []);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to read uploaded file', $result['message']);

        // Unsupported extension
        $fakePdf = UploadedFile::fake()->create('document.pdf', 10, 'application/pdf');
        $resultPdf = $this->service->process($fakePdf, Tenant::class, []);
        $this->assertFalse($resultPdf['success']);
        $this->assertStringContainsString('Unsupported file format', $resultPdf['message']);
    }

    public function test_process_empty_csv_and_missing_required_headers(): void
    {
        $emptyCsv = UploadedFile::fake()->createWithContent('empty.csv', '');
        $resEmpty = $this->service->process($emptyCsv, Tenant::class, [
            'name' => ['label' => 'Name', 'required' => true],
        ]);
        $this->assertFalse($resEmpty['success']);
        $this->assertStringContainsString('headers', $resEmpty['message']);

        // Missing required headers
        $csvMissingRequired = UploadedFile::fake()->createWithContent(
            'drivers.csv',
            "Phone,Address\n9876543210,Mumbai"
        );
        $resMissing = $this->service->process($csvMissingRequired, Tenant::class, [
            'name' => ['label' => 'Name', 'required' => true],
            'phone' => ['label' => 'Phone', 'required' => true],
        ]);
        $this->assertFalse($resMissing['success']);
        $this->assertStringContainsString('Missing required column', $resMissing['message']);
    }

    public function test_process_csv_with_validation_errors_duplicates_and_empty_rows(): void
    {
        $csvContent = implode("\n", [
            'Name,Phone,Daily Wage',
            'Driver A,9876543210,500',
            '   ,   ,   ', // Empty row to be skipped
            'Driver A,9876543210,600', // Duplicate in batch
            'Driver B,invalid-phone,abc', // Validation failure
            'Driver C,9876543212,700', // Valid row
        ]);

        $file = UploadedFile::fake()->createWithContent('drivers.csv', $csvContent);

        $savedRows = [];
        $result = $this->service->process(
            $file,
            Tenant::class,
            [
                'name' => [
                    'label' => 'Name',
                    'required' => true,
                    'unique' => true,
                    'rules' => ['required', 'string'],
                ],
                'phone' => [
                    'label' => 'Phone',
                    'required' => true,
                    'unique' => true,
                    'rules' => ['required', 'regex:/^[0-9]{10}$/'],
                ],
                'daily_wage' => [
                    'label' => 'Daily Wage',
                    'rules' => ['nullable', 'numeric'],
                    'transform' => fn ($val) => is_numeric($val) ? (float) $val : $val,
                ],
            ],
            saveHandler: function ($data, $row) use (&$savedRows) {
                $savedRows[] = $data;
            }
        );

        $this->assertSame(4, $result['summary']['total']);
        $this->assertSame(2, $result['summary']['successful']); // Driver A (first occurrence) & Driver C
        $this->assertSame(2, $result['summary']['failed']); // Driver A duplicate & Driver B invalid phone
        $this->assertSame(1, $result['summary']['skipped']); // 1 empty row
        $this->assertCount(2, $savedRows);
    }

    public function test_process_xlsx_file_successfully(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Code');
        $sheet->setCellValue('A2', 'Item One');
        $sheet->setCellValue('B2', 'ITM001');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedXlsx = new UploadedFile(
            $tempPath,
            'items.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $savedItems = [];
        $result = $this->service->process(
            $uploadedXlsx,
            Tenant::class,
            [
                'name' => ['label' => 'Name', 'required' => true],
                'code' => ['label' => 'Code', 'required' => true],
            ],
            saveHandler: function ($data, $row) use (&$savedItems) {
                $savedItems[] = $data;
            }
        );

        @unlink($tempPath);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['summary']['successful']);
        $this->assertSame('Item One', $savedItems[0]['name']);
    }
}
