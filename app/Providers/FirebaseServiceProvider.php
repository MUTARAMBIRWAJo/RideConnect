<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Contract\Auth;
use App\Services\Firebase\FirebaseHealthService;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * ARCHITECTURE:
     * - FirebaseHealthService is always available (no external deps)
     * - Factory, Messaging, Auth are bound eagerly when credentials exist
     *   (they use REST/HTTP2, no grpc needed)
     * - Firestore is bound ONLY when ext-grpc is available
     *   (google/cloud-firestore requires the native C extension)
     */
    public function register(): void
    {
        $this->app->singleton(FirebaseHealthService::class, function () {
            return new FirebaseHealthService();
        });

        $healthService = $this->app->make(FirebaseHealthService::class);

        if (!$healthService->isEnabled() || !$healthService->credentialsExist()) {
            return;
        }

        // Factory — always register when credentials are present
        $this->app->singleton(Factory::class, function () {
            $credentialsPath = config('firebase.credentials');
            $projectId = config('firebase.project_id');

            $factory = (new Factory)->withServiceAccount($credentialsPath);

            if ($projectId) {
                $factory = $factory->withProjectId($projectId);
            }

            return $factory;
        });

        // Messaging — uses HTTP/2 transport, no grpc needed
        $this->app->singleton(Messaging::class, function ($app) {
            return $app->make(Factory::class)->createMessaging();
        });

        // Auth — uses REST API, no grpc needed
        $this->app->singleton(Auth::class, function ($app) {
            return $app->make(Factory::class)->createAuth();
        });

        // Firestore is permanently disabled — RTDB-only architecture.
        // No Firestore binding is registered. All real-time writes go to RTDB.
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
