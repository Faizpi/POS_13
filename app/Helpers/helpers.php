<?php

if (! function_exists('formatJson')) {
    /**
     * Format a JSON string with syntax highlighting for display.
     */
    function formatJson($jsonString)
    {
        $decoded = json_decode($jsonString);
        if ($decoded === null) {
            return e($jsonString);
        }

        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $pretty = e($pretty);
        $pretty = preg_replace('/&quot;([^&]+?)&quot;\s*:/', '<span style="color:#1D4ED8">"$1"</span>:', $pretty);
        $pretty = preg_replace('/:\s*&quot;(.*?)&quot;/', ': <span style="color:#0369a1">"$1"</span>', $pretty);
        $pretty = preg_replace('/:\s*(\d+\.?\d*)/', ': <span style="color:#b45309">$1</span>', $pretty);
        $pretty = preg_replace('/:\s*(true|false|null)/', ': <span style="color:#3b82f6">$1</span>', $pretty);

        return $pretty;
    }
}

if (! function_exists('format_rupiah')) {
    /**
     * Format angka ke format Rupiah Indonesia.
     * Contoh: 1500000 → Rp1.500.000,00
     */
    function format_rupiah($value, string $prefix = 'Rp'): string
    {
        $amount = is_numeric($value) ? (float) $value : 0.0;

        return $prefix.number_format($amount, 2, ',', '.');
    }
}

if (! function_exists('receipt_limit_text')) {
    /**
     * Potong teks untuk struk thermal (max karakter).
     */
    function receipt_limit_text($value, int $max = 20): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }

        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($length <= $max) {
            return $text;
        }

        $sliceLength = max(0, $max - 3);
        $slice = function_exists('mb_substr')
            ? mb_substr($text, 0, $sliceLength, 'UTF-8')
            : substr($text, 0, $sliceLength);

        return $slice.'...';
    }
}

if (! function_exists('receipt_format_phone')) {
    /**
     * Normalisasi nomor telepon Indonesia ke format +62 xxx-xxxx-xxxx.
     * Mendukung format 08xx, 628xx, 8xx, +628xx.
     */
    function receipt_format_phone($value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        // Kalau ada @, itu email — kembalikan apa adanya
        if (strpos($raw, '@') !== false) {
            return $raw;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return $raw;
        }

        // Fix 6208xxx → 628xxx
        if (substr($digits, 0, 3) === '620') {
            $digits = '62'.substr($digits, 3);
        }

        $groupDigits = function (string $numbers): string {
            if ($numbers === '') {
                return '';
            }
            if (strlen($numbers) >= 10) {
                return substr($numbers, 0, 3).'-'.substr($numbers, 3, 4).'-'.substr($numbers, 7);
            }

            return implode('-', str_split($numbers, 4));
        };

        if (substr($digits, 0, 2) === '62') {
            $formatted = $groupDigits(substr($digits, 2));

            return $formatted !== '' ? '+62 '.$formatted : '+62';
        }

        if (substr($digits, 0, 1) === '0') {
            $formatted = $groupDigits(substr($digits, 1));

            return $formatted !== '' ? '+62 '.$formatted : '+62';
        }

        if (substr($digits, 0, 1) === '8' && strlen($digits) >= 9) {
            return '+62 '.$groupDigits($digits);
        }

        if (substr($raw, 0, 1) === '+') {
            return '+'.$groupDigits($digits);
        }

        return $groupDigits($digits);
    }
}
