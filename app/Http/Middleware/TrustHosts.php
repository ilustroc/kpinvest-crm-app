<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        return array_values(array_filter([
            $this->allSubdomainsOfApplicationUrl(),
            '^localhost$',
            '^127\.0\.0\.1$',
            config('app.url') ? '^'.preg_quote((string) parse_url(config('app.url'), PHP_URL_HOST), '/').'$' : null,
        ]));
    }

    /**
     * Activar validación de hosts de forma consistente.
     */
    protected function shouldSpecifyTrustedHosts(): bool
    {
        return true;
    }
}
