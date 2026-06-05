<?php
/**
 * SimplePDF — Lightweight raw-PDF generator (no external dependencies)
 *
 * Generates a minimal PDF using raw PDF syntax (xref table, page objects, streams).
 * Supports text, horizontal lines, and basic table-like output.
 * Sufficient for 1-2 page result cards.
 */
class SimplePDF
{
    /** @var array PDF objects */
    private array $objects = [];
    /** @var int  Next object ID */
    private int $nextObj = 1;
    /** @var array  Content streams per page */
    private array $pages = [];
    /** @var string  Current page stream buffer */
    private string $stream = '';
    /** @var float  Current Y position (points from top) */
    private float $y = 0;
    /** @var float  Page height */
    private float $pageH = 841.89;  // A4
    /** @var float  Page width */
    private float $pageW = 595.28;  // A4
    /** @var float  Margin */
    private float $margin = 40;
    /** @var string  Document title */
    private string $title = '';

    // Font object IDs
    private int $fontRegularId = 0;
    private int $fontBoldId    = 0;

    public function __construct()
    {
        // Reserve objects: catalog(1), pages(2), font-regular(3), font-bold(4)
        $this->nextObj = 5;
        $this->fontRegularId = 3;
        $this->fontBoldId    = 4;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /** Start a new page */
    public function addPage(): void
    {
        if ($this->stream !== '') {
            $this->pages[] = $this->stream;
        }
        $this->stream = '';
        $this->y      = $this->pageH - $this->margin;
    }

    /**
     * Add a line of text
     *
     * @param string $text   Text content
     * @param float  $size   Font size in points
     * @param bool   $bold   Use bold font
     * @param string $align  'L', 'C', 'R'
     * @param string $color  'black', 'gray', 'red', 'green', 'blue'
     */
    public function addText(
        string $text,
        float $size = 11,
        bool $bold = false,
        string $align = 'L',
        string $color = 'black'
    ): void {
        $this->checkNewPage($size + 4);

        // Color
        $rgb = match($color) {
            'gray'  => '0.4 0.4 0.4',
            'red'   => '0.8 0.1 0.1',
            'green' => '0.1 0.5 0.2',
            'blue'  => '0.1 0.3 0.8',
            default => '0 0 0',
        };

        $fontId = $bold ? $this->fontBoldId : $this->fontRegularId;
        $fontName = $bold ? 'FB' : 'FR';

        $text_safe = $this->pdfString($text);
        $x         = $this->margin;
        $usable    = $this->pageW - 2 * $this->margin;

        // Simple alignment approximation (can't measure exactly without glyph widths)
        if ($align === 'C') {
            $approx_w = strlen($text) * $size * 0.5;
            $x = max($this->margin, ($this->pageW - $approx_w) / 2);
        } elseif ($align === 'R') {
            $approx_w = strlen($text) * $size * 0.5;
            $x = max($this->margin, $this->pageW - $this->margin - $approx_w);
        }

        $this->stream .= "BT\n";
        $this->stream .= "/$fontName $size Tf\n";
        $this->stream .= "$rgb rg\n";
        $this->stream .= "$x {$this->y} Td\n";
        $this->stream .= "($text_safe) Tj\n";
        $this->stream .= "ET\n";

        $this->y -= ($size + 4);
    }

    /**
     * Add a horizontal line across the page
     */
    public function addLine(float $thickness = 0.5, string $color = 'gray'): void
    {
        $this->checkNewPage(8);
        $rgb = match($color) {
            'gray'  => '0.6 0.6 0.6',
            'light' => '0.85 0.85 0.85',
            default => '0 0 0',
        };
        $x1 = $this->margin;
        $x2 = $this->pageW - $this->margin;
        $this->stream .= "$thickness w\n";
        $this->stream .= "$rgb RG\n";
        $this->stream .= "$x1 {$this->y} m $x2 {$this->y} l S\n";
        $this->y -= 8;
    }

    /**
     * Add blank vertical space
     */
    public function addSpace(float $pts = 8): void
    {
        $this->y -= $pts;
    }

    /**
     * Add a two-column row (label and value)
     */
    public function addRow(string $label, string $value, string $valueColor = 'black'): void
    {
        $this->checkNewPage(16);
        $rgb = match($valueColor) {
            'green' => '0.1 0.5 0.2',
            'red'   => '0.8 0.1 0.1',
            'blue'  => '0.1 0.3 0.8',
            'gray'  => '0.4 0.4 0.4',
            default => '0 0 0',
        };
        $lx = $this->margin;
        $vx = $this->margin + 180;
        $sl = $this->pdfString($label);
        $sv = $this->pdfString($value);

        $this->stream .= "BT\n";
        $this->stream .= "/FR 10 Tf\n";
        $this->stream .= "0.4 0.4 0.4 rg\n";
        $this->stream .= "$lx {$this->y} Td\n";
        $this->stream .= "($sl) Tj\n";
        $this->stream .= "ET\n";

        $this->stream .= "BT\n";
        $this->stream .= "/FR 10 Tf\n";
        $this->stream .= "$rgb rg\n";
        $this->stream .= "$vx {$this->y} Td\n";
        $this->stream .= "($sv) Tj\n";
        $this->stream .= "ET\n";

        $this->y -= 15;
    }

    /**
     * Output the PDF as a file download
     */
    public function output(string $filename = 'result.pdf'): void
    {
        // Finalize last page
        if ($this->stream !== '') {
            $this->pages[] = $this->stream;
            $this->stream  = '';
        }

        $pdf = $this->buildPDF();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
    }

    // ---- INTERNALS ----

    private function checkNewPage(float $neededPts): void
    {
        if ($this->y - $neededPts < $this->margin) {
            $this->pages[] = $this->stream;
            $this->stream  = '';
            $this->y       = $this->pageH - $this->margin;
        }
    }

    private function pdfString(string $text): string
    {
        // Encode: strip non-Latin chars, escape PDF special chars
        $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        $text = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], (string)$text);
        return $text;
    }

    private function buildPDF(): string
    {
        $objs   = [];   // objectId => raw object string
        $xref   = [];   // objectId => byte offset

        // Object 3: Regular font (Helvetica)
        $objs[3] = "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        // Object 4: Bold font (Helvetica-Bold)
        $objs[4] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Build page content streams and page objects
        $pageObjIds  = [];
        $streamStart = $this->nextObj;
        $pageStart   = $streamStart + count($this->pages);

        foreach ($this->pages as $pi => $stream) {
            $streamId = $streamStart + $pi;
            $streamLen = strlen($stream);
            $objs[$streamId] = "$streamId 0 obj\n<< /Length $streamLen >>\nstream\n$stream\nendstream\nendobj\n";
        }

        foreach ($this->pages as $pi => $stream) {
            $streamId = $streamStart + $pi;
            $pageId   = $pageStart + $pi;
            $pageObjIds[] = $pageId;
            $objs[$pageId] = "$pageId 0 obj\n<< /Type /Page /Parent 2 0 R "
                . "/MediaBox [0 0 {$this->pageW} {$this->pageH}] "
                . "/Contents $streamId 0 R "
                . "/Resources << /Font << /FR 3 0 R /FB 4 0 R >> >> >>\nendobj\n";
        }

        // Object 2: Pages dictionary
        $kidsArr  = implode(' 0 R ', $pageObjIds) . ' 0 R';
        $objs[2]  = "2 0 obj\n<< /Type /Pages /Kids [$kidsArr] /Count " . count($pageObjIds) . " >>\nendobj\n";

        // Object 1: Catalog
        $objs[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Compose PDF
        $body    = "%PDF-1.4\n";
        $allIds  = array_merge([1, 2, 3, 4], array_keys($objs));
        $allIds  = array_unique($allIds);
        sort($allIds);

        foreach ($allIds as $id) {
            if (!isset($objs[$id])) continue;
            $xref[$id] = strlen($body);
            $body      .= $objs[$id];
        }

        // Cross-reference table
        $xrefOffset = strlen($body);
        $maxId      = max($allIds);
        $body      .= "xref\n0 " . ($maxId + 1) . "\n";
        $body      .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) {
            if (isset($xref[$i])) {
                $body .= sprintf("%010d 00000 n \n", $xref[$i]);
            } else {
                $body .= "0000000000 65535 f \n";
            }
        }

        $titleSafe = $this->pdfString($this->title);
        $body .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R /Info << /Title ($titleSafe) >> >>\n";
        $body .= "startxref\n$xrefOffset\n%%EOF\n";

        return $body;
    }
}
