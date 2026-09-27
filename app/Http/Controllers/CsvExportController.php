<?php

namespace App\Http\Controllers;

use App\Support\CsvExport\CsvExportRegistry;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportController extends Controller
{
    /** Rows fetched per query page, and roughly how often the connection is flushed to the client. */
    private const CHUNK_SIZE = 500;

    public function __invoke(string $slug): StreamedResponse
    {
        $definition = CsvExportRegistry::find($slug);

        abort_if(! $definition, 404);
        abort_unless(! $definition->ability || auth()->user()->can($definition->ability), 403);

        $query = ($definition->query)();
        $totalRows = (clone $query)->count();

        return new StreamedResponse(function () use ($definition, $query) {
            // Large exports must reach the browser as they're generated, not all at once at the
            // end — buffered output is what makes big CSVs look "stuck" and eventually time out
            // behind a reverse proxy (e.g. Cloudflare's idle-connection limit). ob_flush (not
            // ob_end_flush) only drains the current buffer without closing it, which is safe to
            // call repeatedly even inside a test runner's own output buffer.
            $flush = function () {
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $handle = fopen('php://output', 'w');
            fputcsv($handle, $definition->headers);
            $flush();

            $query->chunkById(self::CHUNK_SIZE, function ($rows) use ($handle, $definition, $flush) {
                foreach ($rows as $row) {
                    fputcsv($handle, ($definition->mapRow)($row));
                }

                $flush();
            });

            fclose($handle);
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$definition->filename().'"',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Csv-Total-Rows' => (string) $totalRows,
        ]);
    }
}
