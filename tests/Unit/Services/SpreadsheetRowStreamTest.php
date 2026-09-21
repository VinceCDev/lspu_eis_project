<?php

namespace Tests\Unit\Services;

use App\Services\SpreadsheetRowStream;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpreadsheetRowStreamTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'srs').'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function testStreamsSheetsRowsBlankGapsRichTextAndDates(): void
    {
        $wb = new Spreadsheet();
        $wb->getActiveSheet()->setTitle('Summary')->fromArray([['a', 'b']], null, 'A1');
        $ws = $wb->createSheet()->setTitle('CCS');
        $ws->fromArray([['No.', 'Name of Graduates', 'Date']], null, 'A2');
        $ws->setCellValue('A4', 1);
        $ws->setCellValue('B4', 'DELA CRUZ, MARY');
        $ws->setCellValue('C4', Date::PHPToExcel(new \DateTime('2023-06-15')));
        $rich = new RichText();
        $rich->createTextRun('LOPEZ, ')->getFont()->setBold(true);
        $rich->createTextRun('LIZA');
        $ws->setCellValue('B6', $rich);
        $ws->setCellValue('D6', 'skips column C');
        (new Xlsx($wb))->save($this->file);

        $stream = SpreadsheetRowStream::open($this->file, 'xlsx');
        $sheets = $stream->sheets();
        $this->assertSame(['Summary', 'CCS'], array_column($sheets, 'name'));
        $this->assertGreaterThanOrEqual(6, $sheets[1]['rows']);

        $rows = iterator_to_array($stream->rows($sheets[1]['ref']));
        $this->assertSame([2, 4, 6], array_keys($rows), 'real row numbers are preserved; blank rows are skipped');
        $this->assertSame(['No.', 'Name of Graduates', 'Date'], $rows[2]);
        $this->assertSame('1', $rows[4][0]);
        $this->assertSame('DELA CRUZ, MARY', $rows[4][1]);
        $this->assertEqualsWithDelta(Date::PHPToExcel(new \DateTime('2023-06-15')), (float) $rows[4][2], 0.0001, 'dates come back as the Excel serial the importer already understands');
        $this->assertSame('LOPEZ, LIZA', $rows[6][1], 'rich-text runs are concatenated');
        $this->assertSame(['', 'LOPEZ, LIZA', '', 'skips column C'], $rows[6], 'missing cells are padded so column indexes line up');
    }

    public function testStreamsCsvWithBomAndQuotedCommas(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'srs').'.csv';
        file_put_contents($csv, "\xEF\xBB\xBFNo.,Name of Graduates\n1,\"CRUZ, JUAN\"\n\n2,SANTOS\n");
        $stream = SpreadsheetRowStream::open($csv, 'csv');
        $rows = iterator_to_array($stream->rows(''));
        @unlink($csv);

        $this->assertSame(['No.', 'Name of Graduates'], $rows[1], 'UTF-8 BOM stripped');
        $this->assertSame(['1', 'CRUZ, JUAN'], $rows[2]);
        $this->assertSame(['2', 'SANTOS'], $rows[4]);
    }

    public function testMemoryStaysFlatOnALargeSheet(): void
    {
        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        for ($r = 1; $r <= 4000; ++$r) {
            $ws->fromArray([[$r, "NAME {$r}", 'BSIT', 'x@y.test']], null, "A{$r}");
        }
        (new Xlsx($wb))->save($this->file);
        unset($wb, $ws);

        $stream = SpreadsheetRowStream::open($this->file, 'xlsx');
        $ref = $stream->sheets()[0]['ref'];
        $before = memory_get_usage();
        $n = 0;
        $peakDelta = 0;
        foreach ($stream->rows($ref) as $row) {
            ++$n;
            $peakDelta = max($peakDelta, memory_get_usage() - $before);
        }
        $this->assertSame(4000, $n);
        $this->assertLessThan(8 * 1024 * 1024, $peakDelta, 'streaming reader must not retain the whole sheet');
    }
}
