<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Models\V3\TripMessageV3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TripMessageControllerV3 extends Controller
{
    public function index($tripId)
    {
        $messages = TripMessageV3::where('trip_id', $tripId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request, $tripId)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $message = TripMessageV3::create([
            'trip_id' => $tripId,
            'sender_id' => $request->user()->id,
            'message' => $request->message,
        ]);

        return response()->json(['success' => true, 'data' => $message]);
    }
}
