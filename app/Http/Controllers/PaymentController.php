<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Room;
use App\Services\BakongService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    protected BakongService $bakongService;

    public function __construct(BakongService $bakongService)
    {
        $this->bakongService = $bakongService;
    }

    /**
     * Create a dynamic KHQR payment for Room Booking Deposit, Rent, or custom amount.
     *
     * POST /api/payments/create-qr
     */
    public function createQr(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'nullable|integer|exists:rooms,id',
            'amount' => 'nullable|numeric|min:0.01',
            'currency' => 'nullable|string|in:USD,KHR,usd,khr',
            'payment_type' => 'nullable|string|in:booking_deposit,rent,general',
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:30',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user('sanctum');
        $room = null;
        $amount = $request->input('amount');
        $currency = strtoupper($request->input('currency', config('bakong.default_currency', 'USD')));
        $paymentType = $request->input('payment_type', 'booking_deposit');
        $customerName = $request->input('customer_name', $user?->name);
        $customerPhone = $request->input('customer_phone', $user?->phone);
        $description = $request->input('description');

        // If room is specified, load room and derive defaults
        if ($request->filled('room_id')) {
            $room = Room::with(['detail', 'user'])->find($request->input('room_id'));

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found',
                ], 404);
            }

            // Default amount to room price if not explicitly provided
            if (empty($amount)) {
                $amount = (float) $room->price;
            }

            if (empty($description)) {
                $typeName = ($paymentType === 'rent') ? 'Rent Payment' : 'Room Booking Deposit';
                $description = "{$typeName} for {$room->name}";
            }
        }

        if (empty($amount) || $amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Payment amount is required and must be greater than 0',
            ], 422);
        }

        // Bill number generation
        $billNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        // Receiver Bakong Account ID: Landlord's account ID if set, otherwise fallback to RoomDz platform default
        $receiverAccountId = config('bakong.account_id', 'roomdz@nbc');
        $merchantName = config('bakong.merchant_name', 'RoomDz');

        if ($room && $room->relationLoaded('user') && $room->user) {
            if (!empty($room->user->bakong_account_id)) {
                $receiverAccountId = $room->user->bakong_account_id;
                $merchantName = !empty($room->user->bakong_merchant_name)
                    ? $room->user->bakong_merchant_name
                    : $room->user->name;
            }
        }

        try {
            // Generate EMVCo KHQR code, MD5 hash, and QR image
            $expiryMinutes = (int) config('bakong.qr_expiry_minutes', 5);
            $khqr = $this->bakongService->generateDynamicKhqr([
                'account_id' => $receiverAccountId,
                'merchant_name' => $merchantName,
                'merchant_city' => config('bakong.merchant_city', 'Phnom Penh'),
                'amount' => $amount,
                'currency' => $currency,
                'bill_number' => $billNumber,
                'mobile_number' => $customerPhone,
                'store_label' => 'RoomDz',
                'expiry_minutes' => $expiryMinutes,
            ]);

            $expiresAt = now()->addMinutes($expiryMinutes);

            // Store payment record in database
            $payment = Payment::create([
                'user_id' => $user?->id,
                'room_id' => $room?->id,
                'bill_number' => $billNumber,
                'amount' => $amount,
                'currency' => $currency,
                'payment_type' => $paymentType,
                'status' => 'pending',
                'qr_string' => $khqr['qr_string'],
                'md5' => $khqr['md5'],
                'bakong_account_id' => $receiverAccountId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'description' => $description,
                'expires_at' => $expiresAt,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dynamic Bakong KHQR generated successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'bill_number' => $payment->bill_number,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'payment_type' => $payment->payment_type,
                    'status' => $payment->status,
                    'qr_string' => $khqr['qr_string'],
                    'qr_image' => $khqr['qr_image'],
                    'md5' => $khqr['md5'],
                    'bakong_account_id' => $receiverAccountId,
                    'merchant_name' => $merchantName,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'description' => $description,
                    'expires_at' => $expiresAt->toISOString(),
                    'room' => $room ? [
                        'id' => $room->id,
                        'name' => $room->name,
                        'price' => (float) $room->price,
                        'price_period' => $room->price_period,
                        'image' => $room->image,
                    ] : null,
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Bakong KHQR: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check payment status by payment ID or MD5 hash.
     * Automatically queries the NBC Bakong Open API if the status is currently pending.
     *
     * GET /api/payments/{id}/status
     * POST /api/payments/{id}/check
     */
    public function checkStatus(Request $request, int $id): JsonResponse
    {
        // Support lookup by primary ID or bill_number or md5
        $payment = is_numeric($id)
            ? Payment::with('room')->find($id)
            : Payment::with('room')->where('bill_number', $id)->orWhere('md5', $id)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found',
            ], 404);
        }

        // If already marked as completed, return immediate success
        if ($payment->isCompleted()) {
            return response()->json([
                'success' => true,
                'status' => 'completed',
                'is_paid' => true,
                'message' => 'Payment has been completed',
                'data' => [
                    'payment_id' => $payment->id,
                    'bill_number' => $payment->bill_number,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at?->toISOString(),
                    'bakong_hash' => $payment->bakong_hash,
                ],
            ]);
        }

        // Check if payment expired
        if ($payment->isExpired()) {
            if ($payment->status !== 'expired') {
                $payment->update(['status' => 'expired']);
            }
            return response()->json([
                'success' => true,
                'status' => 'expired',
                'is_paid' => false,
                'message' => 'This payment QR code has expired. Please create a new QR.',
                'data' => [
                    'payment_id' => $payment->id,
                    'bill_number' => $payment->bill_number,
                    'status' => 'expired',
                ],
            ]);
        }

        // Query NBC Bakong Open API using the MD5 hash
        $bakongResult = $this->bakongService->checkTransactionByMd5($payment->md5);

        if ($bakongResult['is_paid']) {
            $details = $bakongResult['data'] ?? [];
            $hash = $details['hash'] ?? null;

            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'bakong_hash' => $hash,
                'payment_details' => $bakongResult['raw'] ?? $details,
            ]);

            // If attached to room and is booking deposit, update room units if applicable
            if ($payment->room_id && $payment->room) {
                $room = $payment->room;
                if ($payment->payment_type === 'booking_deposit') {
                    if ($room->available_units !== null && $room->available_units > 0) {
                        $room->decrement('available_units');
                        if ($room->available_units <= 0) {
                            $room->update(['status' => 'RENTED']);
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'status' => 'completed',
                'is_paid' => true,
                'message' => 'Payment verified successfully via Bakong!',
                'data' => [
                    'payment_id' => $payment->id,
                    'bill_number' => $payment->bill_number,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at?->toISOString(),
                    'bakong_hash' => $hash,
                    'transaction' => $details,
                ],
            ]);
        }

        // Still pending
        return response()->json([
            'success' => true,
            'status' => 'pending',
            'is_paid' => false,
            'message' => $bakongResult['message'] ?? 'Payment pending. Waiting for customer scan and confirmation.',
            'data' => [
                'payment_id' => $payment->id,
                'bill_number' => $payment->bill_number,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'expires_at' => $payment->expires_at?->toISOString(),
                'bakong_response' => $bakongResult['message'] ?? null,
            ],
        ]);
    }

    /**
     * Simulate a successful payment completion (Useful for testing UI flow & callbacks).
     *
     * POST /api/payments/{id}/simulate-success
     */
    public function simulateSuccess(Request $request, int $id): JsonResponse
    {
        $payment = is_numeric($id)
            ? Payment::with('room')->find($id)
            : Payment::with('room')->where('bill_number', $id)->orWhere('md5', $id)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found',
            ], 404);
        }

        $mockHash = 'SIM-' . strtoupper(bin2hex(random_bytes(16)));
        $payment->update([
            'status' => 'completed',
            'paid_at' => now(),
            'bakong_hash' => $mockHash,
            'payment_details' => [
                'simulated' => true,
                'hash' => $mockHash,
                'fromAccountId' => 'test_payer@bakong',
                'toAccountId' => $payment->bakong_account_id,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'acknowledgedDate' => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'status' => 'completed',
            'is_paid' => true,
            'message' => 'Payment successfully simulated as completed!',
            'data' => [
                'payment_id' => $payment->id,
                'bill_number' => $payment->bill_number,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at?->toISOString(),
                'bakong_hash' => $mockHash,
            ],
        ]);
    }

    /**
     * Get details of a single payment.
     *
     * GET /api/payments/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $payment = is_numeric($id)
            ? Payment::with(['room', 'user'])->find($id)
            : Payment::with(['room', 'user'])->where('bill_number', $id)->orWhere('md5', $id)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $payment->id,
                'bill_number' => $payment->bill_number,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'payment_type' => $payment->payment_type,
                'status' => $payment->status,
                'qr_string' => $payment->qr_string,
                'md5' => $payment->md5,
                'bakong_hash' => $payment->bakong_hash,
                'bakong_account_id' => $payment->bakong_account_id,
                'customer_name' => $payment->customer_name,
                'customer_phone' => $payment->customer_phone,
                'description' => $payment->description,
                'paid_at' => $payment->paid_at?->toISOString(),
                'expires_at' => $payment->expires_at?->toISOString(),
                'created_at' => $payment->created_at?->toISOString(),
                'room' => $payment->room ? [
                    'id' => $payment->room->id,
                    'name' => $payment->room->name,
                    'price' => (float) $payment->room->price,
                    'image' => $payment->room->image,
                    'address' => $payment->room->address,
                ] : null,
                'user' => $payment->user ? [
                    'id' => $payment->user->id,
                    'name' => $payment->user->name,
                    'email' => $payment->user->email,
                ] : null,
            ],
        ]);
    }

    /**
     * Get payment history for authenticated user or landlord.
     *
     * GET /api/payments
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Payment::with('room');

        // If owner or admin, can see all or own rooms' payments; regular users see their own
        if ($user->role && in_array($user->role->name, ['admin', 'owner'])) {
            if ($request->has('my_rooms_only') && $request->boolean('my_rooms_only')) {
                $roomIds = Room::where('user_id', $user->id)->pluck('id');
                $query->whereIn('room_id', $roomIds);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->input('payment_type'));
        }

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->input('room_id'));
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }
}
