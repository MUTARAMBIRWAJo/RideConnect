<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Matching mode
    |--------------------------------------------------------------------------
    | 'live'  : normal matching (nearest eligible driver, then optional ML).
    | 'debug' : assign the nearest available driver immediately at the widest
    |           radius — useful for end-to-end testing without live drivers.
    */
    'mode' => env('MATCHING_MODE', 'live'),

    /*
    | When true, the first match attempt uses a fast local (DB + haversine)
    | search with NO ML/route call on the critical path. ML becomes optional
    | background refinement so the passenger never waits on a cold ML dyno.
    */
    'fast_match' => env('MATCHING_FAST_MATCH', true),
];
