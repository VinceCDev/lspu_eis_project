<?php

namespace App\Services;

use ZipArchive;

/**
 * Forward-only, constant-memory row reader for .xlsx and .csv files.
 *
 * Why not PhpSpreadsheet: IOFactory::load() materialises every cell as an
 * object — measured at ~12.8 KB per graduate row, so a 100,000-row workbook
 * exhausts the importer's 1 GB memory_limit before a single row is written
 * (and six concurrent imports would each hold ~1 GB). This reader streams the
 * sheet XML and keeps only the shared-string table in memory.
 *
 * Cell values come back as trimmed display strings, like the importer's old
 * rangeToArray() grid. Numeric cells are returned as their raw stored text
 * (dates as Excel serials) — the importer's fromExcelSerial() already turns
 * those into dates.
 */
final class SpreadsheetRowStream
{
    private const MAX_COLUMNS = 40;

    /** @var string[]|null */
    private ?array $shared = null;

    private function __construct(private readonly string $path, private readonly string $kind)
    {
    }

    public static function supports(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return true;
        }
        if ($ext !== 'xlsx') {
            // uploaded temp files have no extension — sniff the zip signature
            $fh = @fopen($path, 'rb');
            $sig = $fh ? fread($fh, 4) : '';
            $fh && fclose($fh);

            return $sig === "PK\x03\x04" && self::hasEntry($path, 'xl/workbook.xml');
        }

        return true;
    }

    public static function open(string $path, ?string $clientExtension = null): self
    {
        $ext = strtolower($clientExtension ?? pathinfo($path, PATHINFO_EXTENSION));
        $isCsv = $ext === 'csv' || (!self::hasEntry($path, 'xl/workbook.xml') && $ext !== 'xlsx');

        return new self($path, $isCsv ? 'csv' : 'xlsx');
    }

    private static function hasEntry(string $path, string $entry): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName($entry) !== false;
        $zip->close();

        return $ok;
    }

    /**
     * @return array<int, array{name: string, ref: string, rows: int}>  ref = zip entry (xlsx) or '' (csv); rows = estimated row count
     */
    public function sheets(): array
    {
        if ($this->kind === 'csv') {
            $n = 0;
            $fh = fopen($this->path, 'rb');
            while ($fh && !feof($fh)) {
                $n += substr_count((string) fread($fh, 1 << 20), "\n");
            }
            $fh && fclose($fh);

            return [['name' => 'CSV', 'ref' => '', 'rows' => $n + 1]];
        }

        $zip = new ZipArchive();
        if ($zip->open($this->path) !== true) {
            throw new \RuntimeException('Could not open the workbook (is it a valid .xlsx file?).');
        }
        $rels = [];
        $relXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relXml !== false && preg_match_all('/<Relationship\b[^>]*>/i', $relXml, $m)) {
            foreach ($m[0] as $tag) {
                if (preg_match('/\bId="([^"]+)"/', $tag, $id) && preg_match('/\bTarget="([^"]+)"/', $tag, $t)) {
                    $target = ltrim($t[1], '/');
                    $rels[$id[1]] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }
        $wb = (string) $zip->getFromName('xl/workbook.xml');
        $out = [];
        if (preg_match_all('/<sheet\b[^>]*>/i', $wb, $m)) {
            foreach ($m[0] as $tag) {
                if (!preg_match('/\bname="([^"]*)"/', $tag, $name) || !preg_match('/\br:id="([^"]+)"/', $tag, $rid) || !isset($rels[$rid[1]])) {
                    continue;
                }
                $rows = 0;
                $stream = $zip->getStream($rels[$rid[1]]);
                if ($stream) {
                    $head = (string) fread($stream, 8192);
                    fclose($stream);
                    if (preg_match('/<dimension\b[^>]*ref="[A-Z]+\d+:[A-Z]+(\d+)"/i', $head, $d)) {
                        $rows = (int) $d[1];
                    }
                }
                $out[] = ['name' => html_entity_decode($name[1], ENT_QUOTES | ENT_XML1), 'ref' => $rels[$rid[1]], 'rows' => $rows];
            }
        }
        $zip->close();

        return $out;
    }

    /** @return \Generator<int, string[]>  1-based row number => 0-based cells (blank rows are skipped) */
    public function rows(string $ref): \Generator
    {
        if ($this->kind === 'csv') {
            yield from $this->csvRows();

            return;
        }

        $this->loadSharedStrings();
        $reader = new \XMLReader();
        if (!$reader->open('zip://'.$this->path.'#'.$ref, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException("Could not read worksheet {$ref}.");
        }
        $doc = new \DOMDocument();
        $rowNo = 0;
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            $rowNo = (int) ($reader->getAttribute('r') ?: $rowNo + 1);
            $node = $reader->expand($doc);
            $cells = [];
            $col = 0;
            foreach ($node->childNodes as $c) {
                if (!$c instanceof \DOMElement || $c->localName !== 'c') {
                    continue;
                }
                $cellRef = $c->getAttribute('r');
                if ($cellRef !== '' && preg_match('/^([A-Z]+)/', $cellRef, $m)) {
                    $col = self::columnIndex($m[1]);
                }
                if ($col < self::MAX_COLUMNS) {
                    $cells[$col] = trim($this->cellText($c));
                }
                ++$col;
            }
            if ($cells !== []) {
                $max = min(self::MAX_COLUMNS - 1, max(array_keys($cells)));
                $line = [];
                for ($i = 0; $i <= $max; ++$i) {
                    $line[] = $cells[$i] ?? '';
                }
                yield $rowNo => $line;
            }
        }
        $reader->close();
    }

    private function csvRows(): \Generator
    {
        $fh = fopen($this->path, 'rb');
        if (!$fh) {
            throw new \RuntimeException('Could not read the CSV file.');
        }
        $rowNo = 0;
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            ++$rowNo;
            if ($rowNo === 1 && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);   // UTF-8 BOM
            }
            if ($row === [null]) {
                continue;
            }
            yield $rowNo => array_map(static fn ($v) => trim((string) ($v ?? '')), array_slice($row, 0, self::MAX_COLUMNS));
        }
        fclose($fh);
    }

    private function cellText(\DOMElement $c): string
    {
        $t = $c->getAttribute('t');
        if ($t === 'inlineStr') {
            $s = '';
            foreach ($c->getElementsByTagName('t') as $tn) {
                $s .= $tn->textContent;
            }

            return $s;
        }
        $v = '';
        foreach ($c->childNodes as $ch) {
            if ($ch instanceof \DOMElement && $ch->localName === 'v') {
                $v = $ch->textContent;
                break;
            }
        }

        return match ($t) {
            's' => $this->shared[(int) $v] ?? '',
            'b' => $v === '1' ? 'TRUE' : 'FALSE',
            'e' => '',
            default => $v,
        };
    }

    private function loadSharedStrings(): void
    {
        if ($this->shared !== null) {
            return;
        }
        $this->shared = [];
        $zip = new ZipArchive();
        if ($zip->open($this->path) !== true || $zip->locateName('xl/sharedStrings.xml') === false) {
            return;
        }
        $zip->close();
        $reader = new \XMLReader();
        $reader->open('zip://'.$this->path.'#xl/sharedStrings.xml', null, LIBXML_NONET | LIBXML_COMPACT);
        $cur = null;
        $inPhonetic = false;
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT) {
                if ($reader->localName === 'si') {
                    $cur = '';
                    if ($reader->isEmptyElement) {
                        $this->shared[] = '';
                        $cur = null;
                    }
                } elseif ($reader->localName === 'rPh') {
                    $inPhonetic = true;
                } elseif ($reader->localName === 't' && $cur !== null && !$inPhonetic && !$reader->isEmptyElement) {
                    $cur .= $reader->readString();
                }
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT) {
                if ($reader->localName === 'si' && $cur !== null) {
                    $this->shared[] = $cur;
                    $cur = null;
                } elseif ($reader->localName === 'rPh') {
                    $inPhonetic = false;
                }
            }
        }
        $reader->close();
    }

    private static function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
