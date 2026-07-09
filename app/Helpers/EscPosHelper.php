<?php

namespace App\Helpers;

/**
 * ESC/POS Helper untuk thermal printer.
 * Dipakai oleh PrintController untuk generate raw ESC/POS data.
 */
class EscPosHelper
{
    // ESC/POS Commands
    const ESC = "\x1B";

    const GS = "\x1D";

    const LF = "\x0A";

    const CR = "\x0D";

    const ALIGN_LEFT = 0;

    const ALIGN_CENTER = 1;

    const ALIGN_RIGHT = 2;

    private string $buffer = '';

    public function __construct()
    {
        $this->initialize();
    }

    public function initialize(): static
    {
        $this->buffer .= self::ESC.'@';

        return $this;
    }

    public function align(int $align = self::ALIGN_LEFT): static
    {
        $this->buffer .= self::ESC.'a'.chr($align);

        return $this;
    }

    public function bold(bool $bold = true): static
    {
        $this->buffer .= self::ESC.'E'.($bold ? chr(1) : chr(0));

        return $this;
    }

    public function textSize(int $width = 1, int $height = 1): static
    {
        $width = max(1, min(8, $width));
        $height = max(1, min(8, $height));
        $n = (($width - 1) << 4) | ($height - 1);
        $this->buffer .= self::GS.'!'.chr($n);

        return $this;
    }

    public function text(string $text = ''): static
    {
        $this->buffer .= $text;

        return $this;
    }

    public function line(string $text = ''): static
    {
        $this->buffer .= $text.self::LF;

        return $this;
    }

    public function feed(int $lines = 1): static
    {
        $this->buffer .= str_repeat(self::LF, $lines);

        return $this;
    }

    public function cut(int $mode = 0): static
    {
        $this->buffer .= self::GS.'V'.chr($mode);

        return $this;
    }

    public function separator(string $char = '=', int $length = 32): static
    {
        $this->line(str_repeat($char, $length));

        return $this;
    }

    public function twoColumn(string $left, string $right, int $width = 32): static
    {
        $rightLen = mb_strlen($right);
        $leftLen = max(1, $width - $rightLen);
        $this->line(str_pad($left, $leftLen).$right);

        return $this;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function clear(): static
    {
        $this->buffer = '';

        return $this;
    }

    /** Output sebagai base64 (untuk web/BLE) */
    public function output(): string
    {
        return base64_encode($this->buffer);
    }

    /** Output raw bytes */
    public function outputRaw(): string
    {
        return $this->buffer;
    }
}
