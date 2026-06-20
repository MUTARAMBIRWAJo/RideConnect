<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class GenerateCompleteSwagger extends Command
{
    protected $signature = 'rideconnect:swagger';
    protected $description = 'Generate full Swagger documentation including all Laravel API routes';

    public function handle()
    {
        $this->info("1. Generating base Swagger docs from manual annotations...");
        $this->call('l5-swagger:generate');

        $jsonPath = storage_path('api-docs/api-docs.json');
        if (!file_exists($jsonPath)) {
            $this->error("Base api-docs.json not found!");
            return 1;
        }

        $this->info("2. Reading base Swagger JSON...");
        $swagger = json_decode(file_get_contents($jsonPath), true);

        if (!isset($swagger['paths'])) {
            $swagger['paths'] = [];
        }

        // Ensure security scheme for Bearer Token exists
        if (!isset($swagger['components'])) {
            $swagger['components'] = [];
        }
        if (!isset($swagger['components']['securitySchemes'])) {
            $swagger['components']['securitySchemes'] = [];
        }
        $swagger['components']['securitySchemes']['bearerAuth'] = [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'Enter your Bearer Token to access restricted APIs'
        ];

        // Ensure dynamic server URL for production (Render) support
        $appUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
        $swagger['servers'] = [
            [
                'url' => $appUrl . '/api',
                'description' => 'RideConnect API Server'
            ]
        ];

        $this->info("3. Scanning and merging all Laravel API routes...");
        $routes = Route::getRoutes()->getRoutes();
        $mergedCount = 0;

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Only process API routes
            if (!Str::startsWith($uri, 'api/') && !in_array('api', $route->middleware())) {
                continue;
            }

            // Standardize URI to start with a slash for Swagger
            $swaggerPath = '/' . ltrim($uri, '/');

            // Determine HTTP method
            $methods = array_map('strtolower', array_diff($route->methods(), ['HEAD']));
            if (empty($methods)) {
                continue;
            }

            $method = reset($methods); // e.g. get, post, put, delete

            // Skip if this path + method is already manually documented
            if (isset($swagger['paths'][$swaggerPath][$method])) {
                continue;
            }

            // Extract tags and groups based on prefix
            $tags = [];
            if (preg_match('/(login|register|logout|auth|token|sanctum)/i', $uri)) {
                $tags[] = '🔐 Authentication';
            } elseif (Str::contains($uri, ['/ml', '/ai/', 'predict', 'anomaly'])) {
                $tags[] = '🤖 AI / ML APIs';
            } elseif (Str::contains($uri, ['/admin'])) {
                $tags[] = '🏢 Admin APIs';
            } elseif (Str::contains($uri, ['/officer', '/bus', '/corridor'])) {
                $tags[] = '🚌 Officer / Public Transport';
            } elseif (Str::contains($uri, ['/driver'])) {
                $tags[] = '🚗 Driver APIs';
            } elseif (Str::contains($uri, ['/passenger', '/trip'])) {
                $tags[] = '🧍 Passenger APIs';
            } else {
                $tags[] = '⚙️ Core APIs';
            }

            // Extract Controller and Action details for summary
            $actionName = $route->getActionName();
            $summary = 'Endpoint ' . $swaggerPath;
            if ($actionName && strpos($actionName, '@') !== false) {
                $parts = explode('@', $actionName);
                $controllerClass = class_basename($parts[0]);
                $methodName = $parts[1];
                $summary = str_replace('Controller', '', $controllerClass) . ' -> ' . $methodName;
            }

            // Parse path parameters for Swagger UI parameters block
            $parameters = [];
            preg_match_all('/\{([^}]+)\}/', $swaggerPath, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $paramName) {
                    $parameters[] = [
                        'name' => $paramName,
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string'
                        ],
                        'description' => 'Path parameter ' . $paramName
                    ];
                }
            }

            // Add route to paths
            if (!isset($swagger['paths'][$swaggerPath])) {
                $swagger['paths'][$swaggerPath] = [];
            }

            $isPublic = preg_match('/(login|register)/i', $uri);

            $swagger['paths'][$swaggerPath][$method] = [
                'summary' => $summary,
                'description' => 'Controller: ' . $actionName,
                'tags' => $tags,
                'parameters' => $parameters,
                'security' => $isPublic ? [] : [['bearerAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Successful operation'
                    ]
                ]
            ];

            $mergedCount++;
        }

        $this->info("4. Successfully merged {$mergedCount} undocumented routes into Swagger!");

        $this->info("5. Writing updated Swagger JSON...");
        file_put_contents($jsonPath, json_encode($swagger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Generate YAML copy as well
        $yamlPath = storage_path('api-docs/api-docs.yaml');
        file_put_contents($yamlPath, json_encode($swagger)); // simple fallback for l5-swagger config

        $this->info("6. Resetting caches...");
        $this->call('config:clear');
        $this->call('cache:clear');
        $this->call('route:clear');

        $this->info("SUCCESS: All RideConnect APIs successfully documented in Swagger UI!");
        return 0;
    }
}
