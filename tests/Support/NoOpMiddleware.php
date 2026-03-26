<?php

declare(strict_types=1);

namespace Fissible\Fault\Tests\Support;

class NoOpMiddleware
{
    public function handle($request, $next)
    {
        return $next($request);
    }
}
