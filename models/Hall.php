<?php

namespace app\models;

use Yii;

/**
 * ตัวสร้างผังที่นั่งจาก layout config
 *  - grid     : แถว x บล็อกที่นั่ง (สร้าง/แก้จากหน้าแอดมิน)
 *  - measured : ตำแหน่งที่วัดจากแบบจริง (ไฟล์ใน config/venues/)
 */
class Hall
{
    private static ?array $zones = null;

    public static function zones(): array
    {
        return self::$zones ??= require Yii::getAlias('@app/config/zones.php');
    }

    public static function zoneLabel(string $code): string
    {
        return self::zones()[$code]['label'] ?? $code;
    }

    /** ผังสำเร็จรูปที่มีไฟล์ใน config/venues/ [key => ชื่อ] */
    public static function layoutFiles(): array
    {
        $out = [];
        foreach (glob(Yii::getAlias('@app/config/venues/*.php')) as $file) {
            $out[basename($file, '.php')] = basename($file);
        }
        return $out;
    }

    public static function loadLayoutFile(string $key): array
    {
        if (!preg_match('/^[a-z0-9_-]+$/i', $key) || !isset(self::layoutFiles()[$key])) {
            throw new \InvalidArgumentException("ไม่พบผัง config/venues/$key.php");
        }
        return require Yii::getAlias("@app/config/venues/$key.php");
    }

    /** ป้ายแถวอัตโนมัติ: A..Z, AA..ZZ */
    public static function rowLabels(int $n): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $i < 26 ? chr(65 + $i) : str_repeat(chr(65 + $i % 26), intdiv($i, 26) + 1);
        }
        return $out;
    }

    /**
     * สร้างรายการที่นั่งจาก layout
     * @return array[] คอลัมน์ตรงกับตาราง seat (ยกเว้น venue_id)
     */
    public static function buildSeats(array $layout): array
    {
        $seats = ($layout['type'] ?? 'grid') === 'measured' ? self::buildMeasured($layout) : self::buildGrid($layout);
        // จัดกึ่งกลางแนวนอนรอบ x = 0 (เวทีอยู่ที่ x = 0)
        $xs = array_column($seats, 'pos_x');
        if ($xs) {
            $mid = (min($xs) + max($xs)) / 2;
            foreach ($seats as &$s) {
                $s['pos_x'] = round($s['pos_x'] - $mid, 2);
                $s['pos_y'] = round($s['pos_y'], 2);
            }
        }
        return $seats;
    }

    private static function seat(int $floor, string $section, string $row, int $ri, int $no, string $zone, float $x, float $y, bool $wc = false): array
    {
        return [
            'floor' => $floor,
            'section' => $section,
            'row_label' => $row,
            'row_index' => $ri,
            'seat_number' => $no,
            'label' => $row . $no,
            'zone_code' => $zone,
            'pos_x' => $x,
            'pos_y' => $y,
            'is_wheelchair' => $wc ? 1 : 0,
            'is_active' => 1,
        ];
    }

    /**
     * ผังวัดจากแบบจริง: ภาพต้นฉบับมีเวทีอยู่ด้านล่าง จึงหมุน 180° (x -> width - x)
     * หมายเลขนับต่อเนื่องทั้งแถวตามลำดับโซน
     */
    private static function buildMeasured(array $c): array
    {
        $scale = $c['seatSize'] / $c['pitch'];
        $zoneFn = $c['zone'];
        $seats = [];
        $offsetY = 0;
        foreach ($c['floors'] as $floor => $f) {
            $counter = [];
            $offsetX = 0;
            foreach ($f['zones'] as $z) {
                foreach ($f['rows'] as $ri => $row) {
                    if (!isset($z['rows'][$row])) {
                        continue;
                    }
                    [$xl, $xr, $n] = $z['rows'][$row];
                    $p = ($xr - $xl) / ($n + 1);
                    for ($i = 1; $i <= $n; $i++) {
                        $no = ($counter[$row] ?? 0) + 1;
                        $counter[$row] = $no;
                        $x = ($offsetX + ($z['width'] - $xr) + $i * $p) * $scale;
                        $seats[] = self::seat((int)$floor, (string)$z['code'], $row, $ri, $no,
                            $zoneFn((int)$floor, (string)$z['code'], $row, $ri, $no), $x, $offsetY + $ri * $c['rowGap']);
                    }
                }
                $offsetX += $z['width'] + ($c['zoneGap'] ?? 0);
            }
            $offsetY += count($f['rows']) * $c['rowGap'] + $c['floorGap'];
        }
        return $seats;
    }

    /**
     * ผังแบบแถว x บล็อก
     * settings: rows (จำนวนแถว), sections (ที่นั่งต่อแถวของแต่ละบล็อก ซ้าย->ขวา),
     *           zoneRows ([โซน => จำนวนแถว] เรียงจากหน้าเวที แถวที่เหลือใช้ restZone)
     */
    private static function buildGrid(array $c): array
    {
        $w = $c['seatSize'];
        $aisle = $c['aisle'];
        $rows = self::rowLabels((int)$c['rows']);
        $bands = [];
        foreach ($c['zoneRows'] ?? [] as $zone => $n) {
            for ($i = 0; $i < (int)$n; $i++) {
                $bands[] = $zone;
            }
        }
        $seats = [];
        foreach ($rows as $ri => $row) {
            $zone = $bands[$ri] ?? $c['restZone'];
            $x = 0;
            $no = 0;
            foreach ($c['sections'] as $si => $cnt) {
                for ($i = 0; $i < $cnt; $i++) {
                    $no++;
                    $seats[] = self::seat(1, (string)($si + 1), $row, $ri, $no, $zone, $x + $i * $w + $w / 2, $ri * $c['rowGap']);
                }
                $x += $cnt * $w + $aisle;
            }
        }
        // แถวโค้งรอบเวทีเล็กน้อย
        $xs = array_column($seats, 'pos_x');
        $mid = $xs ? (min($xs) + max($xs)) / 2 : 0;
        foreach ($seats as &$s) {
            $dx = $s['pos_x'] - $mid;
            $s['pos_y'] -= ($c['curve'] ?? 0) * $dx * $dx;
        }
        return $seats;
    }
}
