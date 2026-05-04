<?php

return [
    'url' => env('SUPABASE_URL', ''),
    'key' => env('SUPABASE_KEY', env('SUPABASE_ANON_KEY', '')),
    'anon_key' => env('SUPABASE_ANON_KEY', env('SUPABASE_KEY', '')),
    'service_role_key' => env('SUPABASE_SERVICE_KEY', env('SUPABASE_SERVICE_ROLE_KEY', '')),
    'service_key' => env('SUPABASE_SERVICE_KEY', env('SUPABASE_SERVICE_ROLE_KEY', '')),
    'jwt_secret' => env('SUPABASE_JWT_SECRET', ''),
];
