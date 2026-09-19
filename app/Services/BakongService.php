<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

use Konthaina\Khqr\KHQRGenerator;

class BakongService
{
    protected string $apiKey;
    protected string $apiToken;
    protected string $baseUrl;
    protected string $defaultAccountId;
    protected string $defaultMerchantName;
    protected string $defaultMerchantCity;
    protected string $defaultCurrency;

    public function __construct()
    {
        $this->apiKey = config('bakong.api_key', 'cf995da6b1e04dd89f32');
        $this->apiToken = config('bakong.api_token', $this->apiKey);
        $this->baseUrl = rtrim(config('bakong.base_url', 'https://api-bakong.nbc.gov.kh/v1'), '/');
        $this->defaultAccountId = config('bakong.account_id', 'mean_chandara@bkrt');
        $this->defaultMerchantName = config('bakong.merchant_name', 'RoomDz');
        $this->defaultMerchantCity = config('bakong.merchant_city', 'Phnom Penh');
        $this->defaultCurrency = strtoupper(config('bakong.default_currency', 'USD'));
    }

    /**
     * Generate Dynamic KHQR payload according to NBC EMVCo KHQR Standard.
     * Includes Tag 99 with subtag 00 (creation time) and subtag 01 (expiration time).
     *
     * @param array{
     *     account_id?: string,
     *     merchant_name?: string,
     *     merchant_city?: string,
     *     amount: float|int|string,
     *     currency?: string,
     *     bill_number?: string,
     *     mobile_number?: string,
     *     store_label?: string,
     *     terminal_label?: string,
     *     expiry_minutes?: int
     * } $data
     * @return array{
     *     qr_string: string,
     *     md5: string,
     *     qr_image: string,
     *     bill_number: string,
     *     amount: float,
     *     currency: string,
     *     account_id: string,
     *     created_timestamp: string|null,
     *     expiration_timestamp: string|null
     * }
     */
    public function generateDynamicKhqr(array $data): array
    {
        $accountId = $data['account_id'] ?? $this->defaultAccountId;
        $merchantName = mb_substr($data['merchant_name'] ?? $this->defaultMerchantName, 0, 25);
        $merchantCity = mb_substr($data['merchant_city'] ?? $this->defaultMerchantCity, 0, 15);
        $currency = strtoupper($data['currency'] ?? $this->defaultCurrency);
        $amount = (float) $data['amount'];
        $billNumber = $data['bill_number'] ?? ('INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)));
        $mobileNumber = $data['mobile_number'] ?? null;
        $storeLabel = $data['store_label'] ?? 'RoomDz';
        $terminalLabel = $data['terminal_label'] ?? null;

        $expiryMinutes = (int) ($data['expiry_minutes'] ?? config('bakong.qr_expiry_minutes', 5));
        $expirationSeconds = max(60, $expiryMinutes * 60);

        $khqr = new KHQRGenerator(KHQRGenerator::MERCHANT_TYPE_INDIVIDUAL);
        $khqr->setBakongAccountId($accountId)
            ->setMerchantName($merchantName)
            ->setMerchantCity($merchantCity)
            ->setCurrency($currency)
            ->setAmount($amount)
            ->setExpirationDuration($expirationSeconds);

        if (!empty($billNumber)) {
            $khqr->setBillNumber($billNumber);
        }
        if (!empty($mobileNumber)) {
            $khqr->setMobileNumber($mobileNumber);
        }
        if (!empty($storeLabel)) {
            $khqr->setStoreLabel($storeLabel);
        }
        if (!empty($terminalLabel)) {
            $khqr->setTerminalLabel($terminalLabel);
        }

        $res = $khqr->generate();
        $qrString = $res['qr'];
        $md5 = $res['md5'];

        $qrImage = $this->renderQrCode($qrString);

        return [
            'qr_string' => $qrString,
            'md5' => $md5,
            'qr_image' => $qrImage,
            'bill_number' => $billNumber,
            'amount' => $amount,
            'currency' => $currency,
            'account_id' => $accountId,
            'created_timestamp' => $res['createdTimestamp'] ?? null,
            'expiration_timestamp' => $res['expirationTimestamp'] ?? null,
        ];
    }

    /**
     * Generate Static KHQR payload (reusable, no expiration timestamp).
     */
    public function generateStaticKhqr(array $data): array
    {
        $accountId = $data['account_id'] ?? $this->defaultAccountId;
        $merchantName = mb_substr($data['merchant_name'] ?? $this->defaultMerchantName, 0, 25);
        $merchantCity = mb_substr($data['merchant_city'] ?? $this->defaultMerchantCity, 0, 15);
        $currency = strtoupper($data['currency'] ?? $this->defaultCurrency);

        $khqr = (new KHQRGenerator(KHQRGenerator::MERCHANT_TYPE_INDIVIDUAL))
            ->setStatic(true)
            ->setBakongAccountId($accountId)
            ->setMerchantName($merchantName)
            ->setMerchantCity($merchantCity)
            ->setCurrency($currency);

        if (!empty($data['store_label'])) {
            $khqr->setStoreLabel($data['store_label']);
        }

        $res = $khqr->generate();
        $qrString = $res['qr'];
        $md5 = $res['md5'];
        $qrImage = $this->renderQrCode($qrString);

        return [
            'qr_string' => $qrString,
            'md5' => $md5,
            'qr_image' => $qrImage,
            'bill_number' => 'STATIC',
            'amount' => 0.0,
            'currency' => $currency,
            'account_id' => $accountId,
            'created_timestamp' => $res['createdTimestamp'] ?? null,
            'expiration_timestamp' => null,
        ];
    }

    /**
     * Render QR code as a base64-encoded SVG data URI or SVG string.
     */
    public function renderQrCode(string $data): string
    {
        try {
            if (class_exists(QRCode::class)) {
                $qr = new QRCode();
                return $qr->render($data);
            }
        } catch (Exception $e) {
            Log::warning('BakongService: Failed to render QR code: ' . $e->getMessage());
        }

        // Fallback: return clean svg data URI
        return 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><text x="10" y="20">QR Ready</text></svg>';
    }

    /**
     * Get maximum allowed requests for Bakong API.
     */
    public function getRequestLimit(): int
    {
        return (int) config('bakong.request_limit', 100);
    }

    /**
     * Cache key for today's Bakong API request counter.
     */
    protected function getRequestCountCacheKey(): string
    {
        return 'bakong_api_request_count_' . now()->format('Y-m-d');
    }

    /**
     * Get current number of requests sent to Bakong API today.
     */
    public function getRequestCount(): int
    {
        return (int) Cache::get($this->getRequestCountCacheKey(), 0);
    }

    /**
     * Increment the Bakong API request counter by 1 and set expiration to end of day.
     */
    public function incrementRequestCount(): int
    {
        $key = $this->getRequestCountCacheKey();
        if (!Cache::has($key)) {
            Cache::put($key, 1, now()->endOfDay());
            return 1;
        }

        return (int) Cache::increment($key);
    }

    /**
     * Reset Bakong API request counter (for testing or administrative reset).
     */
    public function resetRequestCount(): void
    {
        Cache::forget($this->getRequestCountCacheKey());
        Cache::forget('bakong_api_request_count');
    }

    /**
     * Set a specific Bakong API request count (useful for testing threshold, e.g., set to 99).
     */
    public function setRequestCount(int $count): void
    {
        Cache::put($this->getRequestCountCacheKey(), max(0, $count), now()->endOfDay());
    }

    /**
     * Check if Bakong API request limit has been reached.
     */
    public function isLimitReached(): bool
    {
        return $this->getRequestCount() >= $this->getRequestLimit();
    }

    /**
     * Check transaction status with NBC Bakong Open API by MD5.
     * Increments the request counter by 1 for every call to the Bakong Open API.
     *
     * @param string $md5
     * @return array{
     *     success: bool,
     *     is_paid: bool,
     *     limit_reached: bool,
     *     request_count: int,
     *     request_limit: int,
     *     message: string,
     *     data: mixed,
     *     raw: mixed
     * }
     */
    public function checkTransactionByMd5(string $md5): array
    {
        $limit = $this->getRequestLimit();
        $currentCount = $this->getRequestCount();

        // If request limit is already reached (100 times), prevent further external requests
        if ($currentCount >= $limit) {
            Log::warning("BakongService: Request limit reached ({$currentCount}/{$limit}). Blocking outgoing request.");
            return [
                'success' => false,
                'is_paid' => false,
                'limit_reached' => true,
                'request_count' => $currentCount,
                'request_limit' => $limit,
                'message' => "Bakong API request limit reached ({$currentCount}/{$limit}).",
                'data' => null,
                'raw' => null,
            ];
        }

        // Count +1 every time we send a request to Bakong service
        $newCount = $this->incrementRequestCount();
        $limitReached = ($newCount >= $limit);

        Log::info("BakongService: Request sent to Bakong Open API [{$newCount}/{$limit}] for MD5: {$md5}");

        $url = "{$this->baseUrl}/check_transaction_by_md5";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(12)->post($url, [
                'md5' => $md5,
            ]);

            $json = $response->json();

            // Check if Bakong returns rate limit status (HTTP 429)
            if ($response->status() === 429 || (isset($json['responseMessage']) && stripos($json['responseMessage'], 'limit') !== false)) {
                $limitReached = true;
            }

            // Bakong API response formats:
            // Success (Paid):
            // { "responseCode": 0, "responseMessage": "Success", "data": { "hash": "...", ... } }
            if ($response->successful() && isset($json['responseCode']) && $json['responseCode'] === 0) {
                return [
                    'success' => true,
                    'is_paid' => true,
                    'limit_reached' => $limitReached,
                    'request_count' => $newCount,
                    'request_limit' => $limit,
                    'message' => $json['responseMessage'] ?? 'Transaction completed successfully',
                    'data' => $json['data'] ?? null,
                    'raw' => $json,
                ];
            }

            $errorCode = $json['errorCode'] ?? null;
            $message = $json['responseMessage'] ?? ('HTTP ' . $response->status());

            return [
                'success' => $response->successful(),
                'is_paid' => false,
                'limit_reached' => $limitReached,
                'request_count' => $newCount,
                'request_limit' => $limit,
                'message' => $message,
                'error_code' => $errorCode,
                'data' => $json['data'] ?? null,
                'raw' => $json,
            ];
        } catch (Exception $e) {
            Log::error('BakongService checkTransactionByMd5 error: ' . $e->getMessage());

            return [
                'success' => false,
                'is_paid' => false,
                'limit_reached' => $limitReached,
                'request_count' => $newCount,
                'request_limit' => $limit,
                'message' => 'Failed to connect to Bakong Open API: ' . $e->getMessage(),
                'data' => null,
                'raw' => null,
            ];
        }
    }

    /**
     * Check transaction status with NBC Bakong Open API by Transaction Hash.
     * Increments the request counter by 1 for every call to the Bakong Open API.
     *
     * @param string $hash
     * @return array
     */
    public function checkTransactionByHash(string $hash): array
    {
        $limit = $this->getRequestLimit();
        $currentCount = $this->getRequestCount();

        if ($currentCount >= $limit) {
            Log::warning("BakongService: Request limit reached ({$currentCount}/{$limit}). Blocking outgoing hash request.");
            return [
                'success' => false,
                'is_paid' => false,
                'limit_reached' => true,
                'request_count' => $currentCount,
                'request_limit' => $limit,
                'message' => "Bakong API request limit reached ({$currentCount}/{$limit}).",
                'data' => null,
                'raw' => null,
            ];
        }

        $newCount = $this->incrementRequestCount();
        $limitReached = ($newCount >= $limit);

        Log::info("BakongService: Request sent to Bakong Open API [{$newCount}/{$limit}] for Hash: {$hash}");

        $url = "{$this->baseUrl}/check_transaction_by_hash";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ])->timeout(12)->post($url, [
                'hash' => $hash,
            ]);

            $json = $response->json();

            if ($response->status() === 429 || (isset($json['responseMessage']) && stripos($json['responseMessage'], 'limit') !== false)) {
                $limitReached = true;
            }

            if ($response->successful() && isset($json['responseCode']) && $json['responseCode'] === 0) {
                return [
                    'success' => true,
                    'is_paid' => true,
                    'limit_reached' => $limitReached,
                    'request_count' => $newCount,
                    'request_limit' => $limit,
                    'message' => $json['responseMessage'] ?? 'Success',
                    'data' => $json['data'] ?? null,
                ];
            }

            return [
                'success' => $response->successful(),
                'is_paid' => false,
                'limit_reached' => $limitReached,
                'request_count' => $newCount,
                'request_limit' => $limit,
                'message' => $json['responseMessage'] ?? 'Not found',
                'data' => null,
            ];
        } catch (Exception $e) {
            Log::error('BakongService checkTransactionByHash error: ' . $e->getMessage());

            return [
                'success' => false,
                'is_paid' => false,
                'limit_reached' => $limitReached,
                'request_count' => $newCount,
                'request_limit' => $limit,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Format a TLV (Tag-Length-Value) string.
     */
    protected function formatTlv(string $tag, string $value): string
    {
        $len = strlen($value);
        return sprintf('%02s%02d%s', $tag, $len, $value);
    }

    /**
     * Calculate 16-bit CRC-CCITT (Polynomial: 0x1021, Initial value: 0xFFFF).
     */
    public function calculateCrc16(string $data): string
    {
        $crc = 0xFFFF;
        $polynomial = 0x1021;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(sprintf('%04X', $crc));
    }
}
