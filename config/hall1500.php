<?php
/**
 * ผังที่นั่ง "ห้องประชุม 1500"
 * ------------------------------------------------------------------
 * ไฟล์นี้คือแหล่งข้อมูลเดียวของผังที่นั่ง แก้ไขให้ตรงกับแบบจริง
 * (เช่น p1500.pdf) แล้วรัน:
 *
 *     php yii hall/generate          # สร้างที่นั่งลงตาราง seat
 *     php yii hall/stats             # ดูจำนวนที่นั่งแยกชั้น/โซน
 *
 * โครงสร้าง
 *  - floors   : ชั้น (ชั้นล่าง / ชั้นบน-ระเบียง)
 *  - rows     : ป้ายแถวเรียงจากหน้าเวที -> หลัง
 *  - sections : บล็อกที่นั่งในแนวนอน (ซ้าย -> ขวา เมื่อมองจากผู้ชมไปเวที)
 *               ระหว่าง section คือทางเดิน (aisle)
 *  - rowOverrides : จำนวนที่นั่งเฉพาะแถว ต่อ section (แถวหน้าที่สั้นกว่า ฯลฯ)
 *  - zone     : ฟังก์ชันกำหนดโซนราคาให้แต่ละที่นั่ง
 *
 * หมายเลขที่นั่ง: numbering = 'row'   -> นับต่อเนื่องทั้งแถว ซ้ายไปขวา (A1..A44)
 *                numbering = 'section' -> เริ่มนับใหม่ทุก section (L-A1, C-A1 ...)
 *
 * ค่าเริ่มต้นด้านล่างเป็นผังตัวอย่างรวม 1,500 ที่นั่ง
 *  ชั้นล่าง 25 แถว x (12+20+12) = 1,100 ที่นั่ง
 *  ชั้นบน   10 แถว x (10+20+10) =   400 ที่นั่ง
 */

$lowerRows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N',
    'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
$upperRows = ['AA', 'BB', 'CC', 'DD', 'EE', 'FF', 'GG', 'HH', 'JJ', 'KK'];

return [
    'name' => 'ห้องประชุม 1500',
    'numbering' => 'row',

    // ขนาดเรขาคณิตสำหรับวาด SVG (หน่วย = px ใน viewBox)
    'seatSize' => 18,     // ระยะห่างที่นั่งแนวนอน
    'rowGap' => 22,       // ระยะห่างแถว
    'aisle' => 36,        // ความกว้างทางเดินระหว่าง section
    'floorGap' => 70,     // ระยะระหว่างชั้นล่างกับชั้นบนในภาพ
    'curve' => 0.00012,   // ความโค้งของแถว (0 = ตรง)

    'floors' => [
        1 => [
            'label' => 'ชั้นล่าง',
            'rows' => $lowerRows,
            'sections' => [
                ['code' => 'L', 'label' => 'ปีกซ้าย', 'seats' => 12],
                ['code' => 'C', 'label' => 'กลาง', 'seats' => 20],
                ['code' => 'R', 'label' => 'ปีกขวา', 'seats' => 12],
            ],
            // ตัวอย่าง: แถว A ปีกซ้าย/ขวา มี 12 ที่เหมือนกัน -> ไม่ต้องใส่
            // 'rowOverrides' => ['A' => ['L' => 10, 'C' => 18, 'R' => 10]],
            'rowOverrides' => [],
            // ที่นั่งวีลแชร์ [แถว => [หมายเลข,...]]
            'wheelchair' => ['Z' => [1, 2, 43, 44]],
        ],
        2 => [
            'label' => 'ชั้นบน (ระเบียง)',
            'rows' => $upperRows,
            'sections' => [
                ['code' => 'BL', 'label' => 'ระเบียงซ้าย', 'seats' => 10],
                ['code' => 'BC', 'label' => 'ระเบียงกลาง', 'seats' => 20],
                ['code' => 'BR', 'label' => 'ระเบียงขวา', 'seats' => 10],
            ],
            'rowOverrides' => [],
            'wheelchair' => [],
        ],
    ],

    // โซนราคา (ราคาจริงกำหนดต่อการแสดงในหน้าแอดมิน, default_price ใช้ตั้งต้น)
    'zones' => [
        'VIP' => ['label' => 'VIP', 'color' => '#d4a017', 'default_price' => 500],
        'A' => ['label' => 'โซน A', 'color' => '#e4572e', 'default_price' => 300],
        'B' => ['label' => 'โซน B', 'color' => '#2e86de', 'default_price' => 200],
        'C' => ['label' => 'โซน C (ชั้นบน)', 'color' => '#27ae60', 'default_price' => 150],
    ],

    /**
     * กำหนดโซนให้ที่นั่ง
     * @param int    $floor
     * @param string $section  code ของ section
     * @param string $row      ป้ายแถว
     * @param int    $rowIndex ลำดับแถวเริ่มที่ 0 (0 = ติดเวทีที่สุด)
     * @param int    $seatNo   หมายเลขที่นั่ง
     */
    'zone' => function (int $floor, string $section, string $row, int $rowIndex, int $seatNo): string {
        if ($floor === 2) {
            return 'C';
        }
        if ($section === 'C' && $rowIndex < 5) {
            return 'VIP';          // กลาง แถว A-E
        }
        return $rowIndex < 12 ? 'A' : 'B';  // แถว A-M = A, แถว N-Z = B
    },
];
