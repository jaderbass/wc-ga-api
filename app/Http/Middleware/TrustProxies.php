<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Vertraue allen Proxies (Managed-Hosting typisch).
     * Wenn du eine feste IP/Range hast, kannst du die hier eintragen.
     */
    protected $proxies = '*';

    /**
     * Nutze alle Forwarded-Header (X-Forwarded-Host/Proto/Port/For).
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_AWS_ELB;
}