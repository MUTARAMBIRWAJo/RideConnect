<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Contract\Auth;
use App\Services\Firebase\FirebaseHealthService;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FirebaseHealthService::class, function () {
            return new FirebaseHealthService();
        });

        // Use the health service to register SDK components if enabled
        $healthService = $this->app->make(FirebaseHealthService::class);

        if ($healthService->isEnabled() && $healthService->credentialsExist()) {
            $this->app->singleton(Factory::class, function () {
                $credentialsPath = config('firebase.credentials');
                $projectId = config('firebase.project_id');
                
                $factory = (new Factory)->withServiceAccount($credentialsPath);
                
                if (file_exists($credentialsPath)) {
                    $credentials = json_decode(file_get_contents($credentialsPath), true);
                    if ($credentials !== null) {
                        $factory = $factory->withFirestoreClientConfig([
                            'credentials' => $credentials,
                        ]);
                    }
                }

                if ($projectId) {
                    $factory = $factory->withProjectId($projectId);
                }
                return $factory;
            });

            $this->app->singleton(Firestore::class, function ($app) {
                $factory = $app->make(Factory::class);
                $firestoreDb = config('firebase.firestore_database', '(default)');
                return $factory->createFirestore($firestoreDb);
            });

            $this->app->singleton(Messaging::class, function ($app) {
                return $app->make(Factory::class)->createMessaging();
            });

            $this->app->singleton(Auth::class, function ($app) {
                return $app->make(Factory::class)->createAuth();
            });
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
