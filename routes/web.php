<?php

use App\Services\BakongService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/test-cloudinary', function () {
    return 'Cloudinary Laravel is working!';
});

Route::get('/test-payment', function () {
    $bakong = app(BakongService::class);
    $amount = (float) request('amount', 0.01);
    $currency = strtoupper(request('currency', 'USD'));
    $type = request('type', 'dynamic');
    $expiryMinutes = (int) request('expiry', 15);
    $accountId = trim(request('account_id', config('bakong.account_id', 'mean_chandara@bkrt')));
    $merchantName = trim(request('merchant_name', config('bakong.merchant_name', 'RoomDz')));
    $merchantCity = config('bakong.merchant_city', 'Phnom Penh');

    if (empty($accountId)) {
        $accountId = 'mean_chandara@bkrt';
    }
    if (empty($merchantName)) {
        $merchantName = 'RoomDz';
    }

    if ($type === 'static') {
        $khqr = $bakong->generateStaticKhqr([
            'account_id'    => $accountId,
            'merchant_name' => $merchantName,
            'merchant_city' => $merchantCity,
            'currency'      => $currency,
            'store_label'   => 'RoomDz Test',
        ]);
        $billNumber = 'STATIC-' . strtoupper(substr(uniqid(), -6));
    } else {
        $khqr = $bakong->generateDynamicKhqr([
            'account_id'     => $accountId,
            'merchant_name'  => $merchantName,
            'merchant_city'  => $merchantCity,
            'amount'         => $amount,
            'currency'       => $currency,
            'expiry_minutes' => $expiryMinutes,
            'store_label'    => 'RoomDz Test',
        ]);
        $billNumber = $khqr['bill_number'];
    }

    $formattedAmount = ($currency === 'KHR') ? number_format($amount, 0) : number_format($amount, 2);

    // Save or create Payment record in DB for status tracking
    $expiresAt = ($type === 'dynamic') ? now()->addMinutes($expiryMinutes) : null;
    $payment = \App\Models\Payment::create([
        'bill_number'       => $billNumber,
        'amount'            => $amount,
        'currency'          => $currency,
        'payment_type'      => 'general',
        'status'            => 'pending',
        'qr_string'         => $khqr['qr_string'],
        'md5'               => $khqr['md5'],
        'bakong_account_id' => $accountId,
        'description'       => 'Test Payment for RoomDz',
        'expires_at'        => $expiresAt,
    ]);

    return response(view('test-payment', [
        'paymentId'      => $payment->id,
        'qrString'       => $khqr['qr_string'],
        'md5'            => $khqr['md5'],
        'amount'         => $formattedAmount,
        'rawAmount'      => $amount,
        'currency'       => $currency,
        'billNumber'     => $billNumber,
        'accountId'      => $accountId,
        'merchantName'   => $merchantName,
        'expiryMinutes'  => $expiryMinutes,
        'type'           => $type,
    ]));
});
