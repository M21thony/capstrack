<?php

namespace App\Http\Controllers;

use App\Support\TableWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download as Excel, for any table on a CaPSA/TSA, Expert or Admin page.
 *
 * The page sends what its own table shows, already narrowed to the columns the
 * person ticked (partials/table-export). There is nothing to look up here, so
 * the same button works on every table, and a filtered table downloads
 * filtered.
 */
class TableExportController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'scope' => ['nullable', 'string', 'max:300'],
            'headings' => ['required', 'array', 'min:1', 'max:40'],
            'headings.*' => ['nullable', 'string', 'max:200'],
            'rows' => ['present', 'array', 'max:5000'],
            'rows.*' => ['array', 'max:40'],
            'rows.*.*' => ['nullable', 'string', 'max:5000'],
        ]);

        $headings = array_map(fn ($h) => (string) $h, array_values($data['headings']));
        $rows = array_map(
            fn (array $row) => array_map(fn ($cell) => (string) $cell, array_values($row)),
            array_values($data['rows']),
        );

        $scope = trim($data['scope'] ?? '');
        $book = TableWorkbook::build($data['title'], $scope, $headings, $rows);
        $fileName = Str::slug($data['title']).'_'.now()->toDateString().'.xlsx';

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
