<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PaymentVerificationController extends Controller
{
    /**
     * Get payment instructions for a trip
     */
    public function getPaymentInstructions(int $tripId)
    {
        $trip = Trip::with('payment')->find($tripId);
        
        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found',
            ], 404);
        }

        if (!$trip->payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found for this trip',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'merchant_name' => 'RideConnect',
                'pay_code' => '*182*8*1*710185#',
                'amount' => $trip->payment->amount,
                'currency' => 'RWF',
                'booking_reference' => 'RC-TRIP-' . str_pad($trip->id, 6, '0', STR_PAD_LEFT),
                'trip_reference' => 'TRIP-' . $trip->id,
                'payment_id' => $trip->payment->id,
                'payment_status' => $trip->payment->status,
            ],
        ]);
    }

    /**
     * Submit payment evidence
     */
    public function submitPaymentEvidence(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|exists:payments,id',
            'payer_phone' => 'required|string|max:20',
            'transaction_reference' => 'nullable|string|max:100',
            'screenshot' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payment = Payment::find($request->payment_id);
        
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        // Check if payment already has a pending submission
        $existingSubmission = PaymentSubmission::where('payment_id', $payment->id)
            ->where('verification_status', 'pending')
            ->first();

        if ($existingSubmission) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification already in progress',
                'data' => [
                    'submission_id' => $existingSubmission->id,
                    'status' => $existingSubmission->verification_status,
                ],
            ], 400);
        }

        // Upload screenshot
        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $screenshotPath = $request->file('screenshot')->store('payment-screenshots', 'public');
        }

        // Create payment submission
        $submission = PaymentSubmission::create([
            'payment_id' => $payment->id,
            'trip_id' => $payment->trip_id,
            'user_id' => auth()->id(),
            'amount' => $payment->amount,
            'payer_phone' => $request->payer_phone,
            'transaction_reference' => $request->transaction_reference,
            'screenshot_path' => $screenshotPath,
            'verification_status' => 'pending',
        ]);

        // Update payment status
        $payment->update([
            'status' => 'pending_verification',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment evidence submitted successfully',
            'data' => [
                'submission_id' => $submission->id,
                'status' => 'pending',
            ],
        ]);
    }

    /**
     * Get payment submission status
     */
    public function getSubmissionStatus(int $submissionId)
    {
        $submission = PaymentSubmission::with(['payment', 'trip'])->find($submissionId);
        
        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $submission->id,
                'verification_status' => $submission->verification_status,
                'amount' => $submission->amount,
                'payment_status' => $submission->payment->status,
                'verified_at' => $submission->verified_at,
                'notes' => $submission->notes,
                'screenshot_url' => $submission->screenshot_path ? Storage::url($submission->screenshot_path) : null,
            ],
        ]);
    }

    /**
     * Get user's payment submissions
     */
    public function getUserSubmissions()
    {
        $submissions = PaymentSubmission::where('user_id', auth()->id())
            ->with(['payment', 'trip'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $submissions,
        ]);
    }
}
