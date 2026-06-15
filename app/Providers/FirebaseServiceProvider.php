<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;
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

        // Firestore — REQUIRES ext-grpc, only bind when available
        if ($healthService->grpcAvailable()) {
            $this->app->singleton(Firestore::class, function ($app) {
                $factory = $app->make(Factory::class);

                $credentialsPath = config('firebase.credentials');
                if (file_exists($credentialsPath)) {
                    $credentials = json_decode(file_get_contents($credentialsPath), true);
                    if ($credentials !== null) {
                        $factory = $factory->withFirestoreClientConfig([
                            'credentials' => $credentials,
                        ]);
                    }
                }

                $firestoreDb = config('firebase.firestore_database', '(default)');
                return $factory->createFirestore($firestoreDb);
            });
        } else {
            Log::warning('gRPC extension not installed. Using fallback transport.');
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
