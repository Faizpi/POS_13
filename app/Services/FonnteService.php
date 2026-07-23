<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    private string $apiToken;

    private string $baseUrl = 'https://api.fonnte.com';

    public function __construct(?string $apiToken = null)
    {
        $this->apiToken = $apiToken ?? config('services.fonnte.token', '');
    }

    /**
     * Kirim pesan WhatsApp ke satu nomor.
     *
     * @param  string  $target  Nomor tujuan (format: 628xxx atau 08xxx)
     * @param  string  $message  Isi pesan
     */
    public function send(string $target, string $message): bool
    {
        if (empty($this->apiToken)) {
            Log::warning('FonnteService: FONNTE_API_TOKEN tidak dikonfigurasi.');

            return false;
        }

        $normalized = $this->normalizePhone($target);
        if (empty($normalized)) {
            Log::warning("FonnteService: Nomor tidak valid -> [{$target}]");

            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiToken,
            ])->post("{$this->baseUrl}/send", [
                'target' => $normalized,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $body = $response->json();

            if ($response->successful() && ($body['status'] ?? false)) {
                Log::info("FonnteService: Pesan terkirim -> {$normalized}");

                return true;
            }

            Log::warning("FonnteService: Gagal kirim ke {$normalized}", [
                'status' => $response->status(),
                'body' => $body,
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('FonnteService: Exception -> '.$e->getMessage());

            return false;
        }
    }

    /**
     * Kirim pesan ke beberapa nomor sekaligus.
     *
     * @param  array<string>  $targets
     * @return array<string, bool>
     */
    public function sendMultiple(array $targets, string $message): array
    {
        $results = [];
        foreach ($targets as $target) {
            $results[$target] = $this->send($target, $message);
        }

        return $results;
    }

    /**
     * Normalisasi nomor telepon ke format internasional tanpa "+".
     * 08xxx  -> 628xxx
     * 628xxx -> 628xxx (tetap)
     * +62xxx -> 62xxx
     */
    public function normalizePhone(string $raw): string
    {
        $phone = preg_replace("/\D/", '', trim($raw));

        if (empty($phone)) {
            return '';
        }

        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (! str_starts_with($phone, '62')) {
            $phone = '62'.$phone;
        }

        if (strlen($phone) < 10) {
            return '';
        }

        return $phone;
    }
}
