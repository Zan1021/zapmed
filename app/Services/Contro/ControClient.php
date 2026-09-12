<?php

namespace App\Services\Contro;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client for the Contro CRM API (specs/contro-rebuild/03-contro-import-blueprint.md §1).
 *
 * Responsibilities ONLY: authenticate, and fetch a page of an entity set filtered by watermark.
 * No persistence, no business logic — that's ControPullService's job. Uses Laravel's Http facade so
 * tests fake it with Http::fake() (no live calls; live creds/base URL are pending from Craig).
 *
 * Key protocol details baked in:
 *  - Accept: application/json;IEEE754Compatible=true  -> int64 ids arrive as STRINGS.
 *  - Bearer auth from POST {login_path} (email + password).
 *  - OData-ish $filter=<watermarkField> gt <hwm>, $top paging.
 */
class ControClient
{
    private ?string $token = null;

    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $email = null,
        private readonly ?string $password = null,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: rtrim((string) config('contro.api.base_url'), '/') ?: null,
            email: config('contro.api.email'),
            password: config('contro.api.password'),
        );
    }

    /** Authenticate and cache the bearer token. Throws if creds/base URL are missing or login fails. */
    public function authenticate(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }
        if (!$this->baseUrl) {
            throw new RuntimeException('Contro base URL not configured (CONTRO_API_BASE_URL).');
        }

        $response = Http::acceptJson()
            ->timeout(30)
            ->post($this->baseUrl . config('contro.api.login_path'), [
                'email' => $this->email,
                'password' => $this->password,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException("Contro auth failed: HTTP {$response->status()}");
        }

        // Login response token field shape is unconfirmed (blocker Q); accept the common variants.
        $token = $response->json('token')
            ?? $response->json('accessToken')
            ?? $response->json('access_token')
            ?? $response->json('data.token');

        if (!$token) {
            throw new RuntimeException('Contro auth response contained no recognizable token field.');
        }

        return $this->token = $token;
    }

    /**
     * Fetch one page of an entity set with rows whose watermark is strictly greater than $sinceWatermark.
     *
     * @return array<int,array<string,mixed>>  the raw rows (Contro DTOs)
     */
    public function fetchPage(string $path, string $watermarkField, ?string $sinceWatermark, int $skip, int $top): array
    {
        $query = ['$top' => $top, '$skip' => $skip, '$orderby' => "{$watermarkField} asc"];
        if ($sinceWatermark !== null && $sinceWatermark !== '') {
            $query['$filter'] = "{$watermarkField} gt {$sinceWatermark}";
        }

        $response = $this->request()->get($this->baseUrl . $path, $query);

        if (!$response->successful()) {
            throw new RuntimeException("Contro fetch {$path} failed: HTTP {$response->status()}");
        }

        $body = $response->json();
        // Accept either a bare array or an OData-style {value: [...]} envelope.
        return $body['value'] ?? (is_array($body) ? $body : []);
    }

    /** Optional per-record enrichment (orders detail endpoint). Returns the raw detail row. */
    public function fetchDetail(string $detailPath): array
    {
        $response = $this->request()->get($this->baseUrl . $detailPath);
        if (!$response->successful()) {
            throw new RuntimeException("Contro detail fetch {$detailPath} failed: HTTP {$response->status()}");
        }

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->authenticate())
            ->withHeaders(['Accept' => config('contro.api.accept_header')])
            ->timeout(30)
            ->retry(config('contro.api.max_retries', 5), config('contro.api.retry_backoff_ms', 500));
    }
}
