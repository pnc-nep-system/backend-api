<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Caches rendered PDF binaries so repeat exports avoid re-running the
 * expensive DomPDF render pass (the main bottleneck for large reports).
 *
 * Callers compute a deterministic fingerprint of the underlying data
 * (entry/related-row timestamps, filter parameters, etc.) and pass it to
 * key(). Any data change produces a different key, so cached reports are
 * automatically invalidated — no manual cache clearing is required.
 */
class PdfReportCache
{
    /** How long a rendered PDF is reused before it is regenerated. */
    public const TTL_HOURS = 24;

    /**
     * Build a stable cache key for a PDF report.
     *
     * The current date is part of the key so the "Report Date" printed on the
     * PDF always stays fresh, while the data fingerprint guards against stale
     * content within the same day.
     */
    public static function key(string $prefix, string $fingerprint): string
    {
        return 'pdf:' . $prefix . ':' . date('Y-m-d') . ':' . md5($fingerprint);
    }

    /**
     * Serve a PDF from the cache when a fresh copy exists; otherwise render
     * it once, store the binary, and serve it.
     *
     * @param  string  $cacheKey     Key produced by PdfReportCache::key()
     * @param  string  $filename     Download filename
     * @param  Closure $render       Callable returning a Barryvdh\DomPDF\PDF instance
     * @param  bool    $acceptsGzip  Whether the client sent Accept-Encoding: gzip
     */
    public static function respond(string $cacheKey, string $filename, Closure $render, bool $acceptsGzip = false): Response
    {
        $content = Cache::get($cacheKey);

        if ($content === null) {
            $content = $render()->output();

            // Skip caching for exceptionally large reports — cache stores (and
            // the database store's mediumText column) can be size-limited, and
            // the PDF is still served correctly either way.
            if (strlen($content) < 8388608) { // 8 MB
                Cache::put($cacheKey, $content, now()->addHours(self::TTL_HOURS));
            }
        }

        return static::pdfResponse($content, $filename, $acceptsGzip);
    }

    /**
     * Return the PDF binary as a download response.
     *
     * Large reports compress extremely well, so payloads above 256 KB are
     * gzip-encoded when the client accepts it — browsers and fetch()
     * decompress them transparently, cutting transfer time by a large factor.
     */
    public static function pdfResponse(string $content, string $filename, bool $acceptsGzip = true): Response
    {
        $headers = [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        if ($acceptsGzip && strlen($content) > 262144) { // 256 KB
            $content = gzencode($content, 6);
            $headers['Content-Encoding'] = 'gzip';
            $headers['Vary']             = 'Accept-Encoding';
        }

        return response($content, 200, $headers);
    }
}
