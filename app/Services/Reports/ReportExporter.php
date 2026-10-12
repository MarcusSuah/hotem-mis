<?php

namespace App\Services\Reports;

use App\Support\Money\Money;
use OpenSpout\Writer\WriterInterface;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a catalogue report as CSV or Excel. Money becomes a plain number
 * so spreadsheets can total it, and text that a spreadsheet would treat as
 * a formula is neutralised.
 */
class ReportExporter
{
    /**
     * @param  array{key: string, title: string, columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null}  $report
     */
    public function download(array $report, string $format, string $filename): StreamedResponse
    {
        $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
        $downloadName = "{$filename}.{$extension}";
        $contentType = $extension === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        return response()->streamDownload(function () use ($report, $downloadName): void {
            $writer = SimpleExcelWriter::streamDownload($downloadName, writerCallback: fn (WriterInterface $writer) => $writer->openToFile('php://output'));
            $writer->addHeader(array_column($report['columns'], 'label'));

            foreach ($report['rows'] as $row) {
                $writer->addRow($this->cells($report['columns'], $row));
            }

            if ($report['totals'] !== null) {
                $writer->addRow($this->cells($report['columns'], $report['totals']));
            }

            $writer->close();
        }, $downloadName, ['Content-Type' => $contentType]);
    }

    /**
     * @param  list<array{key: string, label: string, type: string}>  $columns
     * @param  array<string, mixed>  $row
     * @return array<string, string|int|float|null>
     */
    public function cells(array $columns, array $row): array
    {
        $cells = [];

        foreach ($columns as $column) {
            $value = $row[$column['key']] ?? null;

            $cells[$column['label']] = match (true) {
                $value instanceof Money => (float) $value->toDecimal(),
                is_int($value), is_float($value), $value === null => $value,
                default => $this->safeText((string) $value),
            };
        }

        return $cells;
    }

    /**
     * Prefix values a spreadsheet would execute as a formula (CSV injection).
     */
    private function safeText(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
