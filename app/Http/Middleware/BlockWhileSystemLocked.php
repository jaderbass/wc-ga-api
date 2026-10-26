<?php

namespace App\Http\Middleware;

use App\Support\SystemLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abgeschaltete Datenbank: alles außer dem Backend beantworten wir mit 503.
 * Wer ins Backend darf, entscheidet dort {@see \App\Models\User::canAccessPanel()}
 * (abgeschaltet nur geschützte Konten); Login und Livewire bleiben erreichbar.
 */
class BlockWhileSystemLocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SystemLock::isLocked()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user !== null && method_exists($user, 'isProtectedAccount') && $user->isProtectedAccount()) {
            return $next($request);
        }

        if ($request->is('/', 'admin', 'admin/*', 'livewire/*')) {
            return $next($request);
        }

        abort(503);
    }
}
