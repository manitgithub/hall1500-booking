<?php

namespace app\components;

/**
 * สร้าง payload QR PromptPay (มาตรฐาน EMVCo / Thai QR Payment)
 */
class PromptPay
{
    const AID = 'A000000677010111';

    /**
     * @param string $id เบอร์มือถือ 10 หลัก, เลขบัตร/เลขผู้เสียภาษี 13 หลัก หรือ e-Wallet 15 หลัก
     * @param float|null $amount จำนวนเงิน (null = ให้ผู้โอนกรอกเอง)
     */
    public static function payload(string $id, ?float $amount = null): string
    {
        $id = preg_replace('/\D/', '', $id);
        $len = strlen($id);
        if ($len === 10) {
            $target = self::tlv('01', '0066' . substr($id, 1)); // mobile -> 0066XXXXXXXXX
        } elseif ($len === 13) {
            $target = self::tlv('02', $id);
        } elseif ($len === 15) {
            $target = self::tlv('03', $id);
        } else {
            throw new \InvalidArgumentException('PromptPay ID ไม่ถูกต้อง');
        }

        $data = self::tlv('00', '01')
            . self::tlv('01', $amount ? '12' : '11')
            . self::tlv('29', self::tlv('00', self::AID) . $target)
            . self::tlv('53', '764')
            . ($amount ? self::tlv('54', number_format($amount, 2, '.', '')) : '')
            . self::tlv('58', 'TH');
        $data .= '6304';
        return $data . self::crc16($data);
    }

    private static function tlv(string $tag, string $value): string
    {
        return $tag . str_pad((string)strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    /** CRC-16/CCITT-FALSE */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
