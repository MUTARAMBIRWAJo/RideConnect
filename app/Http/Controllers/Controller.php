<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(title: "RideConnect API", version: "3.0.0", description: "API documentation for the RideConnect V3 Trip System")]
#[OA\Server(url: "/api", description: "Default API Server")]
abstract class Controller
{
    //
}
