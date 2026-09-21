<?php
/**
 * Builds a deliberately messy tracer workbook with PhpSpreadsheet, to check that the streaming reader produces
 * exactly the same importer result as the old load-everything path:
 *   two sheets (one junk), two-row merged header, running-number column, real Excel DATE cells, numeric phone numbers,
 *   blank rows, TOTAL/subtotal rows, a formula cell, rich text, duplicate e-mails, unknown program codes.
 *
 *   php tests/Performance/make_messy_workbook.php C:/lspu_perf/files/messy.xlsx
 */
require __DIR__.'/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$wb = new Spreadsheet();
$junk = $wb->getActiveSheet();
$junk->setTitle('Summary');
$junk->fromArray([['Pivot summary'], ['College', 'Count'], ['CCS', '=1+2']], null, 'A1');

$ws = $wb->createSheet();
$ws->setTitle('CCS');
$ws->fromArray([['LAGUNA STATE POLYTECHNIC UNIVERSITY'], [''], ['College of Computer Studies']], null, 'A1');
$ws->fromArray([['No.', 'Name of Graduates', 'Program Name', 'Sex', 'Date of Graduation', 'Status of Employment After College', 'Sector', 'Company & Position', 'Location of Employment', 'E-mail', 'Contact Number', 'Home Address', 'Date Hired', 'Green Jobs?']], null, 'A5');
$ws->fromArray([['', '', '', '', '', '(a)', '', '', '', '', '', '', '', '']], null, 'A6');

$row = 7;
$people = [
    ['DELA CRUZ, MARY ROSE R.', 'BSIT', 'Female', '2023-06-15', 'Probationary', 'Government', 'Acme Corp. / Developer', 'Local', 'mary@x.test', 9171234567, 'Los Baños, Laguna', '2023-09-01', 'Yes'],
    ['SANTOS, JUAN', 'BSCS', 'Male', '2022-06-15', 'Self-Employed', 'Private', 'Own Shop / Owner', 'Local', 'juan@x.test', '09171234568', 'San Pablo City, Laguna', '', 'No'],
    ['MENDOZA, ANA B.', 'XYZUNKNOWN', 'F', '2021-06-15', 'Unemployed', '', '', '', '', '', 'Calamba, Laguna', '', ''],
    ['DELA CRUZ, MARY ROSE R.', 'BSIT', 'Female', '2023-06-15', 'Regular', 'Private', 'Dupe Co / Dev', 'Local', 'mary@x.test', 9171234567, 'Los Baños, Laguna', '', ''],
    ['GARCIA, PEDRO Ñ.', 'BSIS', 'Male', '2020-06-15', 'Contractual', 'Private', 'Globex / Analyst', 'Abroad', '', 9179999999, 'Siniloan, Laguna', '2020-08-15', 'No'],
];
$n = 0;
foreach ($people as $p) {
    ++$n;
    $ws->setCellValue("A{$row}", $n);
    $ws->setCellValue("B{$row}", $p[0]);
    $ws->setCellValue("C{$row}", $p[1]);
    $ws->setCellValue("D{$row}", $p[2]);
    $ws->setCellValue("E{$row}", Date::PHPToExcel(new DateTime($p[3])));
    $ws->getStyle("E{$row}")->getNumberFormat()->setFormatCode('mmmm d, yyyy');
    $ws->setCellValue("F{$row}", $p[4]);
    $ws->setCellValue("G{$row}", $p[5]);
    $ws->setCellValue("H{$row}", $p[6]);
    $ws->setCellValue("I{$row}", $p[7]);
    $ws->setCellValue("J{$row}", $p[8]);
    $ws->setCellValue("K{$row}", $p[9]);
    $ws->setCellValue("L{$row}", $p[10]);
    if ($p[11] !== '') {
        $ws->setCellValue("M{$row}", Date::PHPToExcel(new DateTime($p[11])));
        $ws->getStyle("M{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD);
    }
    $ws->setCellValue("N{$row}", $p[12]);
    ++$row;
    if ($n === 2) {
        ++$row;   // blank row in the middle
    }
}
$rich = new RichText();
$rich->createTextRun('LOPEZ, ')->getFont()->setBold(true);
$rich->createTextRun('LIZA M.');
$ws->setCellValue("A{$row}", $n + 1);
$ws->setCellValue("B{$row}", $rich);
$ws->setCellValue("C{$row}", 'BSIT');
$ws->setCellValue("L{$row}", 'Lucena, Quezon');
++$row;
$ws->setCellValue("A{$row}", '=COUNTA(B7:B12)');
$ws->setCellValue("B{$row}", 'TOTAL');
++$row;
$ws->setCellValue("A{$row}", 99);
$ws->setCellValue("B{$row}", '99 REG');

(new PhpOffice\PhpSpreadsheet\Writer\Xlsx($wb))->save($argv[1]);
echo "wrote {$argv[1]}\n";
