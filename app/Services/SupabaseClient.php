<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class SupabaseClient
{
    protected string $url;
    protected string $key;
    protected ?string $serviceRoleKey;

    public function __construct(string $url, string $key, ?string $serviceRoleKey = null)
    {
        $this->url = rtrim($url, '/');
        $this->key = $key;
        $this->serviceRoleKey = $serviceRoleKey;
    }

    /**
     * Get the base URL for Supabase
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get the service role key
     */
    public function getServiceRoleKey(): ?string
    {
        return $this->serviceRoleKey;
    }

    /**
     * Create a request with authorization header
     */
    protected function withAuth(?string $serviceRoleKey = null): array
    {
        return [
            'Authorization' => 'Bearer ' . ($serviceRoleKey ?? $this->key),
            'apikey' => $this->key,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Make a request to Supabase REST API
     */
    protected function request(
        string $method,
        string $endpoint,
        ?array $data = null,
        bool $useServiceRole = false
    ): Response {
        $url = "{$this->url}/rest/v1{$endpoint}";
        $headers = $this->withAuth($useServiceRole ? $this->serviceRoleKey : null);

        return Http::withHeaders($headers)
            ->$method($url, $data ?? []);
    }

    /**
     * Query a table
     */
    public function table(string $table): SupabaseQuery
    {
        return new SupabaseQuery($this, $table);
    }

    /**
     * Insert a record
     */
    public function insert(string $table, array $data): Response
    {
        return $this->request('POST', "/{$table}", $data);
    }

    /**
     * Update records
     */
    public function update(string $table, array $data, string $column, mixed $value): Response
    {
        return $this->request('PATCH', "/{$table}?{$column}=eq.{$value}", $data, true);
    }

    /**
     * Delete records
     */
    public function delete(string $table, string $column, mixed $value): Response
    {
        return $this->request('DELETE', "/{$table}?{$column}=eq.{$value}", null, true);
    }

    /**
     * Find a single record
     */
    public function find(string $table, string $column, mixed $value): Response
    {
        return $this->request('GET', "/{$table}?{$column}=eq.{$value}");
    }

    /**
     * Get all records from a table
     */
    public function get(string $table): Response
    {
        return $this->request('GET', "/{$table}");
    }

    /**
     * Authenticate a user with email and password
     */
    public function signInWithPassword(string $email, string $password): Response
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/token?grant_type=password", [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * Sign up a new user
     */
    public function signUp(string $email, string $password, array $metadata = []): Response
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/signup", [
            'email' => $email,
            'password' => $password,
            'data' => $metadata,
        ]);
    }

    /**
     * Sign out
     */
    public function signOut(string $accessToken): Response
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/logout");
    }

    /**
     * Verify JWT token
     */
    public function verifyToken(string $token): array
    {
        $result = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'apikey' => $this->key,
        ])->get("{$this->url}/auth/v1/userinfo");

        return $result->json();
    }

    /**
     * Send a magic link for passwordless sign-in
     */
    public function sendMagicLink(string $email): Response
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/magiclink", [
            'email' => $email,
        ]);
    }

    /**
     * Reset password for email
     */
    public function resetPassword(string $email): Response
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/recover", [
            'email' => $email,
        ]);
    }

    /**
     * Invoke an Edge Function
     */
    public function invokeFunction(string $functionName, array $data = [], string $region = 'us-east-1'): Response
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'Content-Type' => 'application/json',
        ])->post(
            "{$this->url}/functions/v1/{$functionName}",
            ['data' => $data]
        );
    }

    /**
     * Storage: List buckets
     */
    public function listBuckets(): Response
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'apikey' => $this->key,
        ])->get("{$this->url}/storage/v1/bucket");
    }

    /**
     * Storage: Upload a file
     */
    public function uploadFile(string $bucket, string $path, string $fileContent, string $mimeType = 'application/octet-stream'): Response
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'apikey' => $this->key,
            'Content-Type' => $mimeType,
        ])->withBody($fileContent, $mimeType)
            ->post("{$this->url}/storage/v1/object/{$bucket}/{$path}");
    }

    /**
     * Storage: Download a file
     */
    public function downloadFile(string $bucket, string $path): Response
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'apikey' => $this->key,
        ])->get("{$this->url}/storage/v1/object/{$bucket}/{$path}");
    }

    /**
     * Storage: Delete a file
     */
    public function deleteFile(string $bucket, string $path): Response
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'apikey' => $this->key,
        ])->delete("{$this->url}/storage/v1/object/{$bucket}/{$path}");
    }
}
