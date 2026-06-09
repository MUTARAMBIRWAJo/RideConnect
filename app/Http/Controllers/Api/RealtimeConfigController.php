<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RealtimeConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $config = config('realtime');

        return response()->json([
            'enabled'  => (bool) $config['enabled'],
            'provider' => $config['provider'],
            'host'     => $config['host'],
            'port'     => $config['port'],
            'scheme'   => $config['scheme'],
            'ws_path'  => $config['ws_path'],
            'app_key'  => $config['app_key'],
            'channels' => $config['channels'],
        ]);
    }
}
