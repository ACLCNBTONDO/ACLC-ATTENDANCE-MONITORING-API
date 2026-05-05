<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/cors.php';

$user = requireAuth();
$db   = getDB();

// ── Parameters ────────────────────────────────────────────────────────────────
$section = trim($_GET['section'] ?? '');
$dateStr = trim($_GET['date']    ?? date('Y-m-d'));
if (!$section) { ob_end_clean(); respondError('section is required.'); }

// ── Date / school-year helpers ────────────────────────────────────────────────
$ref       = new DateTime($dateStr);
$year      = (int)$ref->format('Y');
$month     = (int)$ref->format('n');       // 1-12
$monthName = strtoupper($ref->format('F')); // JANUARY …
$sy        = $month >= 8 ? "$year - " . ($year + 1) : ($year - 1) . " - $year";

// ── School days (Mon–Fri) for this month ─────────────────────────────────────
$dim = (int)(new DateTime("$year-$month-01"))->modify('last day of')->format('j');
$DOW = [1 => 'M', 2 => 'T', 3 => 'W', 4 => 'TH', 5 => 'F'];
$schoolDays = [];
for ($d = 1; $d <= $dim; $d++) {
    $dt  = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $d));
    $dow = (int)$dt->format('N'); // 1=Mon … 7=Sun
    if ($dow >= 1 && $dow <= 5) {
        $schoolDays[] = ['day' => $d, 'dow' => $dow, 'date' => $dt->format('Y-m-d'), 'label' => $DOW[$dow]];
    }
}
$numDays = count($schoolDays); // ≤ 23

// ── Fetch students ────────────────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT usn, last_name, first_name, middle_name, sex
     FROM students WHERE section = ?
     ORDER BY sex DESC, last_name ASC, first_name ASC"
);
$stmt->bind_param('s', $section);
$stmt->execute();
$allStudents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Fetch attendance for the whole month ─────────────────────────────────────
$pad = fn(int $n) => str_pad($n, 2, '0', STR_PAD_LEFT);
$startDate = "$year-{$pad($month)}-01";
$endDate   = "$year-{$pad($month)}-{$pad($dim)}";

$stmt = $db->prepare(
    "SELECT a.usn, a.attendance_date, a.remarks
     FROM attendance a
     INNER JOIN students s ON s.usn = a.usn
     WHERE s.section = ? AND a.attendance_date BETWEEN ? AND ?"
);
$stmt->bind_param('sss', $section, $startDate, $endDate);
$stmt->execute();
$attRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build map: usn → date → remarks
$attMap = [];
foreach ($attRows as $row) {
    $attMap[$row['usn']][$row['attendance_date']] = $row['remarks'];
}

// getMark: '' = present/attended, 1 = absent, '/' = tardy
function getMark(array $attMap, string $usn, string $date): mixed
{
    $rem = strtolower(trim($attMap[$usn][$date] ?? ''));
    if ($rem === '') return 1;
    if (str_contains($rem, 'tardy') || str_contains($rem, 'late')) return '/';
    return '';
}

$males   = array_values(array_filter($allStudents, fn($s) => strtolower($s['sex']) === 'male'));
$females = array_values(array_filter($allStudents, fn($s) => strtolower($s['sex']) === 'female'));

// ── Helpers ───────────────────────────────────────────────────────────────────
function colLetter(int $col): string
{
    $out = '';
    while ($col > 0) {
        $rem = ($col - 1) % 26;
        $out  = chr(65 + $rem) . $out;
        $col  = intdiv($col - 1, 26);
    }
    return $out;
}

function esc(string $v): string
{
    return htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function numCell(string $ref, int|float $val, string $style): string
{
    return "<c r=\"{$ref}\" s=\"{$style}\"><v>{$val}</v></c>";
}

function strCell(string $ref, string $val, string $style): string
{
    return "<c r=\"{$ref}\" s=\"{$style}\" t=\"inlineStr\"><is><t>" . esc($val) . "</t></is></c>";
}

function emptyCell(string $ref, string $style): string
{
    return "<c r=\"{$ref}\" s=\"{$style}\"/>";
}

// ── Day-column styles (positions 1–25 = cols D–AB) ───────────────────────────
// Extracted from original template row 14
$dayStyles = [
    1 => '254', 2 => '150', 3 => '150', 4 => '151', 5 => '149',
    6 => '152', 7 => '153', 8 => '153', 9 => '153', 10 => '154',
    11 => '155', 12 => '153', 13 => '153', 14 => '153', 15 => '154',
    16 => '156', 17 => '156', 18 => '156', 19 => '156', 20 => '157',
    21 => '156', 22 => '156', 23 => '156', 24 => '156', 25 => '156',
];
// Cols Z, AA, AB (positions 23-25) use style 259 when empty (beyond school days)
$emptyDayStyles = [
    1=>'258',2=>'258',3=>'258',4=>'258',5=>'258',6=>'258',7=>'258',8=>'258',
    9=>'258',10=>'258',11=>'258',12=>'258',13=>'258',14=>'258',15=>'258',
    16=>'258',17=>'258',18=>'258',19=>'258',20=>'258',21=>'258',22=>'258',
    23=>'259',24=>'259',25=>'259',
];

// ── Build row XML ─────────────────────────────────────────────────────────────

function buildStudentRow(int $rowNum, int $seq, array $student, array $schoolDays, array $attMap, array $dayStyles, array $emptyDayStyles): string
{
    $usn  = $student['usn'];
    $name = strtoupper($student['last_name']) . ', ' . $student['first_name']
          . ($student['middle_name'] ? ' ' . $student['middle_name'] : '');

    $r = $rowNum;
    $cells  = numCell("A{$r}", $seq, '148');
    $cells .= strCell("B{$r}", $name, '693');
    $cells .= emptyCell("C{$r}", '694');

    $absent = $tardy = 0;
    for ($pos = 1; $pos <= 25; $pos++) {
        $col = colLetter(3 + $pos); // D=4 … AB=28
        $ref = "{$col}{$r}";
        $sty = $dayStyles[$pos];

        if ($pos <= count($schoolDays)) {
            $sd   = $schoolDays[$pos - 1];
            $mark = getMark($attMap, $usn, $sd['date']);
            if ($mark === 1) {
                $cells .= numCell($ref, 1, $sty);
                $absent++;
            } elseif ($mark === '/') {
                $cells .= strCell($ref, '/', $sty);
                $tardy++;
            } else {
                $cells .= emptyCell($ref, $sty); // present → blank
            }
        } else {
            $cells .= emptyCell($ref, $emptyDayStyles[$pos]);
        }
    }

    $cells .= numCell("AC{$r}", $absent, '158');
    $cells .= emptyCell("AD{$r}", '159');
    if ($tardy) {
        // Replace the AD empty cell
        $cells = preg_replace('/<c r="AD' . $r . '"[^\/]*\/>/', numCell("AD{$r}", $tardy, '159'), $cells);
    }
    $cells .= emptyCell("AE{$r}", '666');
    $cells .= emptyCell("AF{$r}", '667');
    $cells .= emptyCell("AG{$r}", '667');
    $cells .= emptyCell("AH{$r}", '667');
    $cells .= emptyCell("AI{$r}", '667');
    $cells .= emptyCell("AJ{$r}", '668');

    return "<row r=\"{$r}\" spans=\"1:36\" ht=\"21.95\" customHeight=\"1\">{$cells}</row>";
}

function buildTotalRow(int $rowNum, array $students, string $label, array $schoolDays, array $attMap, string $sA, string $sB, string $sC, string $sDcol, string $sEmpty, string $sAC, string $sAD, string $sAE, string $sAFAI, string $sAJ): string
{
    $r      = $rowNum;
    $cells  = strCell("A{$r}", $label, $sA);
    $cells .= emptyCell("B{$r}", $sB);
    $cells .= emptyCell("C{$r}", $sC);

    for ($pos = 1; $pos <= 25; $pos++) {
        $col = colLetter(3 + $pos);
        $ref = "{$col}{$r}";
        if ($pos <= count($schoolDays)) {
            $sd      = $schoolDays[$pos - 1];
            $present = count(array_filter($students, fn($st) => getMark($attMap, $st['usn'], $sd['date']) === ''));
            $cells  .= numCell($ref, $present, $sDcol);
        } else {
            $cells .= emptyCell($ref, $sEmpty);
        }
    }

    $cells .= emptyCell("AC{$r}", $sAC);
    $cells .= emptyCell("AD{$r}", $sAD);
    $cells .= emptyCell("AE{$r}", $sAE);
    $cells .= emptyCell("AF{$r}", $sAFAI);
    $cells .= emptyCell("AG{$r}", $sAFAI);
    $cells .= emptyCell("AH{$r}", $sAFAI);
    $cells .= emptyCell("AI{$r}", $sAFAI);
    $cells .= emptyCell("AJ{$r}", $sAJ);

    return "<row r=\"{$r}\" spans=\"1:36\" ht=\"21.95\" customHeight=\"1\" thickTop=\"1\" thickBot=\"1\">{$cells}</row>";
}

function buildSeparatorRow(int $rowNum): string
{
    $r     = $rowNum;
    $cells = emptyCell("A{$r}", '648') . emptyCell("B{$r}", '648') . emptyCell("C{$r}", '186');
    for ($pos = 1; $pos <= 5; $pos++) {
        $cells .= emptyCell(colLetter(3 + $pos) . $r, '649');
    }
    for ($pos = 6; $pos <= 33; $pos++) {
        $cells .= emptyCell(colLetter(3 + $pos) . $r, '187');
    }
    return "<row r=\"{$r}\" spans=\"1:36\" ht=\"6.75\" customHeight=\"1\" thickBot=\"1\">{$cells}</row>";
}

// ── Assemble new data rows ────────────────────────────────────────────────────
$newRows  = '';
$curRow   = 14;

foreach ($males as $i => $st) {
    $newRows .= buildStudentRow($curRow, $i + 1, $st, $schoolDays, $attMap, $dayStyles, $emptyDayStyles);
    $curRow++;
}
// Male total row
$newRows .= buildTotalRow(
    $curRow, $males, 'MALE  | TOTAL Per Day', $schoolDays, $attMap,
    '725','726','727','143','144','172','165','669','670','671'
);
$curRow++;

foreach ($females as $i => $st) {
    $newRows .= buildStudentRow($curRow, $i + 1, $st, $schoolDays, $attMap, $dayStyles, $emptyDayStyles);
    $curRow++;
}
// Female total row
$newRows .= buildTotalRow(
    $curRow, $females, 'FEMALE  | TOTAL Per Day', $schoolDays, $attMap,
    '678','679','680','178','179','158','159','681','682','683'
);
$curRow++;

// Combined total row
$newRows .= buildTotalRow(
    $curRow, $allStudents, '    Combined TOTAL PER DAY', $schoolDays, $attMap,
    '642','643','644','182','183','184','185','645','646','647'
);
$curRow++;

// Separator row
$newRows .= buildSeparatorRow($curRow);
$curRow++;

// ── Load template ─────────────────────────────────────────────────────────────
$templatePath = __DIR__ . '/../templates/sf2_template.xlsx';
if (!file_exists($templatePath)) {
    ob_end_clean();
    respondError('SF2 template not found on server.', 500);
}

$tmpFile = tempnam(sys_get_temp_dir(), 'sf2_') . '.xlsx';
copy($templatePath, $tmpFile);

// ── ZIP helper: read/write sheet XML without requiring the php-zip extension ──
// Tries ZipArchive first; falls back to shell unzip/zip commands.

/**
 * Read a single file from a ZIP archive.
 * Returns the file contents as a string, or false on failure.
 */
function zipReadFile(string $zipPath, string $entryName): string|false
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) return false;
        $data = $zip->getFromName($entryName);
        $zip->close();
        return $data;
    }

    // Shell fallback
    $tmpDir = sys_get_temp_dir() . '/sf2extract_' . uniqid();
    @mkdir($tmpDir, 0700, true);
    $cmd = 'unzip -p ' . escapeshellarg($zipPath) . ' ' . escapeshellarg($entryName) . ' 2>/dev/null';
    $data = shell_exec($cmd);
    @rmdir($tmpDir);

    return ($data !== null && $data !== '') ? $data : false;
}

/**
 * Replace a single file inside a ZIP archive with new string content.
 * Returns true on success, false on failure.
 */
function zipReplaceFile(string $zipPath, string $entryName, string $content): bool
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) return false;
        $zip->addFromString($entryName, $content);
        $zip->close();
        return true;
    }

    // Shell fallback: extract to a temp dir, overwrite the file, repack
    $tmpDir = sys_get_temp_dir() . '/sf2pack_' . uniqid();
    @mkdir($tmpDir, 0700, true);

    // Extract entire zip
    $ret = null;
    system('unzip -q ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($tmpDir) . ' 2>/dev/null', $ret);
    if ($ret !== 0) {
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        return false;
    }

    // Write new content
    $target = $tmpDir . '/' . $entryName;
    @mkdir(dirname($target), 0700, true);
    if (file_put_contents($target, $content) === false) {
        shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        return false;
    }

    // Repack (zip requires running from inside the dir to preserve paths)
    $escapedZip = escapeshellarg(realpath($zipPath));
    $cmd = 'cd ' . escapeshellarg($tmpDir) . ' && zip -r -q ' . $escapedZip . ' . 2>/dev/null';
    system($cmd, $ret);

    shell_exec('rm -rf ' . escapeshellarg($tmpDir));
    return $ret === 0;
}

// ── Read sheet XML ────────────────────────────────────────────────────────────
$sheetXml = zipReadFile($tmpFile, 'xl/worksheets/sheet6.xml');
if ($sheetXml === false) {
    ob_end_clean();
    respondError('Failed to read SF2 template. Ensure the php-zip extension is enabled or that unzip/zip are available on this server.', 500);
}

// ── Update K6: School Year (shared string → inlineStr) ───────────────────────
$sheetXml = preg_replace(
    '/<c r="K6"([^>]*)t="s"[^>]*>.*?<\/c>/s',
    '<c r="K6"$1t="inlineStr"><is><t>' . esc($sy) . '</t></is></c>',
    $sheetXml
);

// ── Update X6: Month name (shared string → inlineStr) ────────────────────────
$sheetXml = preg_replace(
    '/<c r="X6"([^>]*)t="s"[^>]*>.*?<\/c>/s',
    '<c r="X6"$1t="inlineStr"><is><t>' . esc($monthName) . '</t></is></c>',
    $sheetXml
);

// ── Update AC8: Section (shared string → inlineStr) ──────────────────────────
$sheetXml = preg_replace(
    '/<c r="AC8"([^>]*)t="s"[^>]*>.*?<\/c>/s',
    '<c r="AC8"$1t="inlineStr"><is><t>' . esc($section) . '</t></is></c>',
    $sheetXml
);

// ── Rebuild row 11 (day numbers) ──────────────────────────────────────────────
$row11Cells = '<c r="A11" s="701"/><c r="B11" s="702"/><c r="C11" s="702"/>';
for ($pos = 1; $pos <= 25; $pos++) {
    $col = colLetter(3 + $pos);
    if ($pos <= $numDays) {
        $row11Cells .= numCell("{$col}11", $schoolDays[$pos - 1]['day'], '258');
    } else {
        $row11Cells .= emptyCell("{$col}11", $pos <= 22 ? '258' : '259');
    }
}
$row11Cells .= '<c r="AC11" s="711"/><c r="AD11" s="712"/><c r="AE11" s="716"/>'
             . '<c r="AF11" s="717"/><c r="AG11" s="717"/><c r="AH11" s="717"/>'
             . '<c r="AI11" s="717"/><c r="AJ11" s="718"/>';

$sheetXml = preg_replace(
    '/<row r="11"[^>]*>.*?<\/row>/s',
    '<row r="11" spans="1:36" ht="19.5" customHeight="1" thickBot="1">' . $row11Cells . '</row>',
    $sheetXml
);

// ── Rebuild row 12 (day-of-week labels) ──────────────────────────────────────
$row12Cells = '<c r="A12" s="701"/><c r="B12" s="702"/><c r="C12" s="702"/>';
for ($pos = 1; $pos <= 25; $pos++) {
    $col = colLetter(3 + $pos);
    if ($pos <= $numDays) {
        $row12Cells .= strCell("{$col}12", $schoolDays[$pos - 1]['label'], '260');
    } else {
        $row12Cells .= emptyCell("{$col}12", $pos <= 22 ? '260' : '259');
    }
}
$row12Cells .= '<c r="AC12" s="722" t="inlineStr"><is><t>ABSENT</t></is></c>'
             . '<c r="AD12" s="724" t="inlineStr"><is><t>TARDY</t></is></c>'
             . '<c r="AE12" s="716"/><c r="AF12" s="717"/><c r="AG12" s="717"/>'
             . '<c r="AH12" s="717"/><c r="AI12" s="717"/><c r="AJ12" s="718"/>';

$sheetXml = preg_replace(
    '/<row r="12"[^>]*>.*?<\/row>/s',
    '<row r="12" spans="1:36" ht="24.75" customHeight="1">' . $row12Cells . '</row>',
    $sheetXml
);

// ── Insert student rows before footer row 57 ─────────────────────────────────
// Shift footer rows if students overflow 56
$footerShift = max(0, $curRow - 57);

if ($footerShift > 0) {
    // Renumber all rows >= 57
    $sheetXml = preg_replace_callback(
        '/<row r="(\d+)"/',
        function($m) use ($footerShift) {
            $rn = (int)$m[1];
            return $rn >= 57 ? '<row r="' . ($rn + $footerShift) . '"' : $m[0];
        },
        $sheetXml
    );
    // Also update cell references inside those rows (e.g. A57 → A(57+shift))
    // This is complex, so instead we insert BEFORE the original footer marker
    $firstFooterRow = 57 + $footerShift;
    $sheetXml = str_replace('<row r="' . $firstFooterRow . '"', $newRows . '<row r="' . $firstFooterRow . '"', $sheetXml);
} else {
    $sheetXml = str_replace('<row r="57"', $newRows . '<row r="57"', $sheetXml);
}

// ── Update worksheet dimension ────────────────────────────────────────────────
$lastRow = max(86 + $footerShift, $curRow);
$sheetXml = preg_replace(
    '/<dimension ref="[^"]*"/',
    '<dimension ref="A1:AJ' . $lastRow . '"',
    $sheetXml
);

// ── Write back and stream ─────────────────────────────────────────────────────
if (!zipReplaceFile($tmpFile, 'xl/worksheets/sheet6.xml', $sheetXml)) {
    ob_end_clean();
    respondError('Failed to write SF2 export. Ensure the php-zip extension is enabled or that unzip/zip are available on this server.', 500);
}

$filename = 'SF2_' . preg_replace('/\s+/', '_', $section) . '_' . ucfirst(strtolower($monthName)) . '_' . $year . '.xlsx';

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($tmpFile);
unlink($tmpFile);
exit;
