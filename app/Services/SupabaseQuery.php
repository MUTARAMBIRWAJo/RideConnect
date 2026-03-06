<?php

namespace App\Services;

use Illuminate\Http\Client\Response;

class SupabaseQuery
{
    protected SupabaseClient $client;
    protected string $table;
    protected array $select = ['*'];
    protected array $filters = [];
    protected ?string $orderBy = null;
    protected string $orderDirection = 'asc';
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected bool $useServiceRole = false;

    public function __construct(SupabaseClient $client, string $table)
    {
        $this->client = $client;
        $this->table = $table;
    }

    /**
     * Select specific columns
     */
    public function select(array $columns): self
    {
        $this->select = $columns;
        return $this;
    }

    /**
     * Add a where clause
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = 'eq';
        }

        $this->filters[] = "{$column}={$operator}.{$value}";
        return $this;
    }

    /**
     * Add a where clause with 'not equal' operator
     */
    public function whereNot(string $column, mixed $value): self
    {
        $this->filters[] = "{$column}=neq.{$value}";
        return $this;
    }

    /**
     * Add a where clause with 'greater than' operator
     */
    public function whereGreaterThan(string $column, mixed $value): self
    {
        $this->filters[] = "{$column}=gt.{$value}";
        return $this;
    }

    /**
     * Add a where clause with 'greater than or equal' operator
     */
    public function whereGreaterThanOrEqual(string $column, mixed $value): self
    {
        $this->filters[] = "{$column}=gte.{$value}";
        return $this;
    }

    /**
     * Add a where clause with 'less than' operator
     */
    public function whereLessThan(string $column, mixed $value): self
    {
        $this->filters[] = "{$column}=lt.{$value}";
        return $this;
    }

    /**
     * Add a where clause with 'less than or equal' operator
     */
    public function whereLessThanOrEqual(string $column, mixed $value): self
    {
        $this->filters[] = "{$column}=lte.{$value}";
        return $this;
    }

    /**
     * Add a where clause with 'like' operator
     */
    public function whereLike(string $column, string $pattern): self
    {
        $this->filters[] = "{$column}=like.{$pattern}";
        return $this;
    }

    /**
     * Add a where clause with 'ilike' operator (case-insensitive)
     */
    public function whereILike(string $column, string $pattern): self
    {
        $this->filters[] = "{$column}=ilike.{$pattern}";
        return $this;
    }

    /**
     * Add a where clause with 'in' operator
     */
    public function whereIn(string $column, array $values): self
    {
        $valuesStr = implode(',', $values);
        $this->filters[] = "{$column}=in.({$valuesStr})";
        return $this;
    }

    /**
     * Add a where clause with 'is' operator
     */
    public function whereNull(string $column): self
    {
        $this->filters[] = "{$column}=is.null";
        return $this;
    }

    /**
     * Add a where clause checking for non-null values
     */
    public function whereNotNull(string $column): self
    {
        $this->filters[] = "{$column}=not.is.null";
        return $this;
    }

    /**
     * Order by a column
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderBy = $column;
        $this->orderDirection = $direction;
        return $this;
    }

    /**
     * Limit the number of results
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Offset the results
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Use service role key for admin operations
     */
    public function useServiceRole(): self
    {
        $this->useServiceRole = true;
        return $this;
    }

    /**
     * Build the query URL
     */
    protected function buildUrl(): string
    {
        $queryParams = [];

        // Select
        $queryParams[] = 'select=' . implode(',', $this->select);

        // Filters
        if (!empty($this->filters)) {
            $queryParams[] = implode('&', $this->filters);
        }

        // Order
        if ($this->orderBy) {
            $queryParams[] = "order={$this->orderBy}.{$this->orderDirection}";
        }

        // Limit
        if ($this->limit) {
            $queryParams[] = "limit={$this->limit}";
        }

        // Offset
        if ($this->offset) {
            $queryParams[] = "offset={$this->offset}";
        }

        $queryString = implode('&', $queryParams);

        return "/{$this->table}?{$queryString}";
    }

    /**
     * Execute the query and get all results
     */
    public function get(): Response
    {
        $url = $this->buildUrl();
        $headers = $this->client->withAuth($this->useServiceRole ? $this->client->getServiceRoleKey() : null);

        return \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->get("{$this->client->getUrl()}/rest/v1{$url}");
    }

    /**
     * Execute the query and get the first result
     */
    public function first(): ?array
    {
        return $this->limit(1)->get()->json()[0] ?? null;
    }

    /**
     * Execute the query and get a single result by ID
     */
    public function find($id): ?array
    {
        $this->filters = ["id=eq.{$id}"];
        return $this->first();
    }

    /**
     * Count the number of results
     */
    public function count(): int
    {
        $this->select = ['count'];
        $response = $this->get();
        $range = $response->header('Content-Range');
        
        if ($range) {
            $parts = explode('/', $range);
            if (count($parts) === 2) {
                return (int) $parts[1];
            }
        }

        return count($response->json());
    }

    /**
     * Check if records exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Paginate results
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();
        $this->limit($perPage)->offset(($page - 1) * $perPage);
        
        return [
            'data' => $this->get()->json(),
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }
}
