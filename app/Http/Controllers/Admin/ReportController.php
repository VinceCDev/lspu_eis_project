<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Report;
use App\Services\Auth;
use App\Services\MailService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Ported from backend/Controllers/Admin/ReportController.php. */
class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports', [
            'title' => 'Reports | LSPU - EIS',
            'active' => Auth::role() === 'superadmin' ? 'superadmin_reports' : 'admin_reports',
            'pageCss' => 'admin_reports.css',
            'pageJs' => 'admin_reports.js',
            'extraScripts' => '<script src="'.asset('assets/vendor/exceljs/exceljs.min.js').'"></script>'
                .'<script src="'.asset('assets/vendor/file-saver/FileSaver.min.js').'"></script>'
                .'<script src="'.asset('assets/js/utils/reportHelpers.js').'"></script>',
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $campusId = $this->resolveCampusId($request->query('campus_id'));
        $college = $request->query('college');
        $yearGraduated = $this->resolveYearGraduated($request->query('year_graduated'));

        return response()->json((new ReportService(null, null, $campusId, $college, $yearGraduated))->summary());
    }

    public function fullData(Request $request): JsonResponse
    {
        $campusId = $this->resolveCampusId($request->query('campus_id'));
        $college = $request->query('college');
        $yearGraduated = $this->resolveYearGraduated($request->query('year_graduated'));
        $data = (new ReportService(null, null, $campusId, $college, $yearGraduated))->fullReportData();
        $data['campus_name'] = $campusId !== null ? $this->campusName($campusId) : 'All Campuses';

        return response()->json($data);
    }

    public function colleges(Request $request): JsonResponse
    {
        $campusId = $this->resolveCampusId($request->query('campus_id'));

        return response()->json(['success' => true, 'colleges' => (new Report($campusId))->distinctColleges()]);
    }

    public function years(Request $request): JsonResponse
    {
        $campusId = $this->resolveCampusId($request->query('campus_id'));

        return response()->json(['success' => true, 'years' => (new Report($campusId))->distinctYears()]);
    }

    public function emailReport(Request $request): JsonResponse
    {
        $recipient = trim($request->input('recipient_email', ''));
        $college = $request->input('college');

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Please enter a valid email address.']);
        }

        $campusId = $this->resolveCampusId($request->input('campus_id'));
        $yearGraduated = $this->resolveYearGraduated($request->input('year_graduated'));
        $scopeLabel = $college ?: ($campusId !== null ? $this->campusName($campusId) : 'All Campuses');

        $summary = (new ReportService(null, null, $campusId, $college, $yearGraduated))->summary();

        $filenameScope = preg_replace('/[^A-Za-z0-9]+/', '_', $scopeLabel) ?: 'Report';

        $sent = (new MailService())->send(
            $recipient,
            $recipient,
            "LSPU EIS Alumni Employment Report — {$scopeLabel}",
            $this->reportEmailBody($scopeLabel, $summary),
            '',
            null,
            null,
            [[
                'content' => $this->buildReportExcel($summary),
                'filename' => "LSPU_EIS_Report_{$filenameScope}.xlsx",
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]]
        );

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Report sent successfully.' : 'Failed to send report email.',
        ]);
    }

    private function buildReportExcel(array $summary): string
    {
        $spreadsheet = new Spreadsheet();

        $programSheet = $spreadsheet->getActiveSheet();
        $programSheet->setTitle('Program Stats');
        $programSheet->fromArray(['Program', 'College', 'Total Graduates', 'Employed', 'Employment Rate (%)', 'Match Rate (%)'], null, 'A1');
        $row = 2;
        foreach ($summary['program_stats'] as $p) {
            $programSheet->fromArray([$p['course'], $p['college'], $p['total_graduates'], $p['employed_count'], $p['employed_percentage'], $p['related_percentage']], null, "A{$row}");
            ++$row;
        }
        $this->styleSheetHeader($programSheet, 'F');

        $statusSheet = $spreadsheet->createSheet();
        $statusSheet->setTitle('Employment Status');
        $statusSheet->fromArray(['Status', 'Count', 'Percentage (%)'], null, 'A1');
        $row = 2;
        foreach ($summary['status_stats'] as $s) {
            $statusSheet->fromArray([$s['employment_status_category'], $s['count'], $s['percentage']], null, "A{$row}");
            ++$row;
        }
        $this->styleSheetHeader($statusSheet, 'C');

        $sectorSheet = $spreadsheet->createSheet();
        $sectorSheet->setTitle('Employment Sector');
        $sectorSheet->fromArray(['Sector', 'Count'], null, 'A1');
        $row = 2;
        foreach ($summary['sector_stats'] as $s) {
            $sectorSheet->fromArray([$s['employment_sector'], $s['count']], null, "A{$row}");
            ++$row;
        }
        $this->styleSheetHeader($sectorSheet, 'B');

        $locationSheet = $spreadsheet->createSheet();
        $locationSheet->setTitle('Location of Work');
        $locationSheet->fromArray(['Location', 'Count'], null, 'A1');
        $row = 2;
        foreach ($summary['location_stats'] as $s) {
            $locationSheet->fromArray([$s['location_of_work'], $s['count']], null, "A{$row}");
            ++$row;
        }
        $this->styleSheetHeader($locationSheet, 'B');

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return ob_get_clean();
    }

    private function styleSheetHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $lastColumn): void
    {
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function reportEmailBody(string $scopeLabel, array $summary): string
    {
        $rows = '';
        foreach ($summary['program_stats'] as $program) {
            $employedPct = $program['employed_percentage'] ?? 0;
            $matchPct = $program['related_percentage'] ?? 0;
            $rows .= '<tr>'
                .'<td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;">'.htmlspecialchars($program['course']).'</td>'
                .'<td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:center;">'.(int) $program['total_graduates'].'</td>'
                .'<td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:center;">'.(int) $program['employed_count'].'</td>'
                .'<td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:center;">'.$employedPct.'%</td>'
                .'<td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:center;">'.$matchPct.'%</td>'
                .'</tr>';
        }

        $cards = '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0;">'
            .$this->statCard('Total Graduates', (string) $summary['total_alumni'])
            .$this->statCard('Employed', (string) $summary['total_employed'])
            .$this->statCard('Job Match Rate', $summary['overall_match_rate'].'%')
            .'</div>';

        $table = '<table style="width:100%;border-collapse:collapse;font-size:14px;margin-top:8px;">'
            .'<thead><tr style="background:#f1f5f9;text-align:left;">'
            .'<th style="padding:8px 10px;">Program</th>'
            .'<th style="padding:8px 10px;text-align:center;">Graduates</th>'
            .'<th style="padding:8px 10px;text-align:center;">Employed</th>'
            .'<th style="padding:8px 10px;text-align:center;">Employment Rate</th>'
            .'<th style="padding:8px 10px;text-align:center;">Match Rate</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>';

        $body = "<p>Attached below is the current alumni employment report for <strong>".htmlspecialchars($scopeLabel).'</strong>.</p>'
            .$cards
            .$table;

        return MailService::wrap(
            'Alumni Employment Report',
            $body,
            'View Full Reports in LSPU EIS',
            config('app.url').'/admin_reports'
        );
    }

    private function statCard(string $label, string $value): string
    {
        return '<div style="flex:1;min-width:140px;background:#f1f5f9;border-radius:8px;padding:14px 16px;">'
            .'<p style="margin:0 0 4px;font-size:12px;color:#52606d;text-transform:uppercase;letter-spacing:0.04em;">'.htmlspecialchars($label).'</p>'
            .'<p style="margin:0;font-size:22px;font-weight:700;color:#1A1A1A;">'.htmlspecialchars($value).'</p>'
            .'</div>';
    }

    private function resolveCampusId(?string $raw): ?int
    {
        if (Auth::role() !== 'superadmin') {
            return Auth::campusId();
        }

        return ($raw !== null && $raw !== '') ? (int) $raw : null;
    }

    private function resolveYearGraduated(?string $raw): ?int
    {
        return ($raw !== null && $raw !== '') ? (int) $raw : null;
    }

    private function campusName(int $campusId): string
    {
        foreach ((new Campus())->all() as $campus) {
            if ((int) $campus['campus_id'] === $campusId) {
                return $campus['name'];
            }
        }

        return '';
    }
}
