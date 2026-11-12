<?php

namespace app\models;

use Yii;

/**
 * อ่านผังห้องจาก config/hall1500.php และคำนวณตำแหน่งที่นั่ง
 */
class Hall
{
    private static ?array $config = null;

    public static function config(): array
    {
        return self::$config ??= require Yii::getAlias('@app/config/hall1500.php');
    }

    public static function zones(): array
    {
        return self::config()['zones'];
    }

    public static function zoneLabel(string $code): string
    {
        return self::zones()[$code]['label'] ?? $code;
    }

    public static function floorLabel(int $floor): string
    {
        return self::config()['floors'][$floor]['label'] ?? "ชั้น $floor";
    }

    /**
     * สร้างรายการที่นั่งทั้งหมดจาก config
     * @return array[] แต่ละรายการตรงกับคอลัมน์ในตาราง seat
     */
    public static function buildSeats(): array
    {
        $c = self::config();
        $w = $c['seatSize'];
        $rowGap = $c['rowGap'];
        $aisle = $c['aisle'];
        $curve = $c['curve'] ?? 0;
        $zoneFn = $c['zone'];
        $numbering = $c['numbering'] ?? 'row';

        // ความกว้างสูงสุดของแต่ละชั้น เพื่อจัดกึ่งกลาง
        $seats = [];
        $offsetY = 0;
        foreach ($c['floors'] as $floor => $f) {
            $maxWidth = 0;
            foreach ($f['rows'] as $row) {
                $maxWidth = max($maxWidth, self::rowWidth($f, $row, $w, $aisle));
            }
            foreach ($f['rows'] as $ri => $row) {
                $counts = self::rowCounts($f, $row);
                $rowWidth = self::rowWidth($f, $row, $w, $aisle);
                // ให้ section กลางอยู่ตรงกลางเสมอ: ใช้ความกว้างของ section ตามค่า default เป็นกรอบ
                $x = 0;
                $n = 0;
                foreach ($f['sections'] as $si => $sec) {
                    $default = $sec['seats'];
                    $cnt = $counts[$sec['code']];
                    $blockWidth = $default * $w;
                    // จัดชิดทางเดิน: ปีกซ้ายชิดขวา, ปีกขวาชิดซ้าย, กลางจัดกึ่งกลาง
                    $last = count($f['sections']) - 1;
                    if ($si === 0 && $last > 0) {
                        $start = $x + ($default - $cnt) * $w;
                    } elseif ($si === $last && $last > 0) {
                        $start = $x;
                    } else {
                        $start = $x + ($default - $cnt) * $w / 2;
                    }
                    $local = 0;
                    for ($i = 0; $i < $cnt; $i++) {
                        $n++;
                        $local++;
                        $no = $numbering === 'section' ? $local : $n;
                        $px = $start + $i * $w + $w / 2;
                        $seats[] = [
                            'floor' => (int)$floor,
                            'section' => $sec['code'],
                            'row_label' => $row,
                            'row_index' => $ri,
                            'seat_number' => $no,
                            'label' => $numbering === 'section' ? "{$sec['code']}-{$row}{$no}" : "{$row}{$no}",
                            'zone_code' => $zoneFn((int)$floor, $sec['code'], $row, $ri, $no),
                            'pos_x' => $px,
                            'pos_y' => $offsetY + $ri * $rowGap,
                            'is_wheelchair' => in_array($no, $f['wheelchair'][$row] ?? [], true) ? 1 : 0,
                            'is_active' => 1,
                        ];
                    }
                    $x += $blockWidth + $aisle;
                }
            }
            // จัดกึ่งกลางทั้งชั้น + ทำแถวโค้ง
            $floorSeats = array_filter($seats, fn($s) => $s['floor'] === (int)$floor);
            foreach ($floorSeats as $k => $s) {
                $dx = $s['pos_x'] - $maxWidth / 2;
                $seats[$k]['pos_x'] = round($dx, 2);
                // แถวโค้งรอบเวที: ที่นั่งริมขยับเข้าใกล้เวทีเล็กน้อย
                $seats[$k]['pos_y'] = round($s['pos_y'] - $curve * $dx * $dx, 2);
            }
            $offsetY += count($f['rows']) * $rowGap + $c['floorGap'];
        }
        return $seats;
    }

    private static function rowCounts(array $f, string $row): array
    {
        $out = [];
        foreach ($f['sections'] as $sec) {
            $out[$sec['code']] = $f['rowOverrides'][$row][$sec['code']] ?? $sec['seats'];
        }
        return $out;
    }

    private static function rowWidth(array $f, string $row, float $w, float $aisle): float
    {
        $width = 0;
        foreach ($f['sections'] as $sec) {
            $width += $sec['seats'] * $w;
        }
        return $width + (count($f['sections']) - 1) * $aisle;
    }
}
