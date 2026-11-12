<?php

namespace app\commands;

use app\models\Booking;
use app\models\Event;
use app\models\EventZonePrice;
use app\models\Hall;
use app\models\Payment;
use app\models\User;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;

/**
 * ใส่ข้อมูลตัวอย่างสำหรับทดลองบนเครื่อง (ห้ามใช้บน production)
 *   php yii seed            ใส่ข้อมูล (ข้ามถ้ามีการแสดงอยู่แล้ว)
 *   php yii seed --fresh    ลบผู้ใช้ทดสอบ/การแสดง/การจองทั้งหมดแล้วใส่ใหม่
 */
class SeedController extends Controller
{
    /** @var bool ลบข้อมูลเดิมก่อน */
    public $fresh = false;

    public function options($actionID)
    {
        return ['fresh'];
    }

    public function actionIndex()
    {
        if (YII_ENV_PROD) {
            $this->stderr("ปฏิเสธ: YII_ENV=prod\n");
            return ExitCode::NOPERM;
        }
        if (!(new Query())->from('{{%seat}}')->exists()) {
            $this->stderr("ยังไม่มีที่นั่ง — รัน php yii hall/generate ก่อน\n");
            return ExitCode::DATAERR;
        }
        if ($this->fresh) {
            $this->wipe();
        } elseif (Event::find()->exists()) {
            $this->stderr("มีการแสดงอยู่แล้ว — ใช้ --fresh เพื่อลบและใส่ใหม่\n");
            return ExitCode::DATAERR;
        }

        $users = $this->seedUsers();
        $events = $this->seedEvents();
        $this->seedBookings($events, $users);

        $this->stdout("\nเสร็จแล้ว: " . count($users) . " ผู้ใช้, " . count($events) . " การแสดง, "
            . Booking::find()->count() . " รายการจอง\n");
        return ExitCode::OK;
    }

    private function wipe(): void
    {
        $db = Yii::$app->db;
        $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
        foreach (['payment', 'booking_seat', 'event_seat', 'booking', 'event_zone_price', 'event'] as $t) {
            $db->createCommand()->truncateTable("{{%$t}}")->execute();
        }
        $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
        // ลบเฉพาะผู้ใช้ตัวอย่าง (ไม่แตะผู้ใช้ LINE จริงและผู้ใช้ dev-login)
        User::deleteAll(['like', 'line_user_id', 'Useed%', false]);
        $this->stdout("ลบข้อมูลเดิมแล้ว\n");
    }

    /** @return User[] */
    private function seedUsers(): array
    {
        $list = [
            ['Udevadmin', 'ทดสอบ (admin)', 'admin'],
            ['Udevstaff', 'ทดสอบ (staff)', 'staff'],
            ['Udevuser', 'ทดสอบ (user)', 'user'],
            ['Useed01', 'สมชาย ใจดี', 'user'],
            ['Useed02', 'สมหญิง รักเรียน', 'user'],
            ['Useed03', 'ปิยะพงษ์ ศรีสุข', 'user'],
            ['Useed04', 'กมลวรรณ ทองดี', 'user'],
            ['Useed05', 'ณัฐวุฒิ แก้วมณี', 'user'],
            ['Useed06', 'อรอุมา บุญมา', 'user'],
            ['Useed07', 'ธีรศักดิ์ พรหมวงศ์', 'user'],
            ['Useed08', 'วิภาวดี จันทร์เพ็ญ', 'user'],
        ];
        $users = [];
        foreach ($list as $i => [$sub, $name, $role]) {
            $u = User::upsertFromLine(['sub' => $sub, 'name' => $name]);
            $u->updateAttributes([
                'role' => $role,
                'phone' => $u->phone ?: sprintf('08%08d', 12340000 + $i),
                'email' => $u->email ?: strtolower($sub) . '@example.com',
            ]);
            $users[$sub] = $u;
        }
        $this->stdout('ผู้ใช้: ' . count($users) . "\n");
        return $users;
    }

    /** @return Event[] keyed by slug */
    private function seedEvents(): array
    {
        $d = fn(string $rel) => date('Y-m-d H:i:00', strtotime($rel));
        $defs = [
            'concert' => [
                'title' => 'คอนเสิร์ตการกุศล "เสียงเพลงแห่งมิตรภาพ"',
                'category' => 'concert',
                'organizer' => 'ชมรมดนตรีสากล',
                'description' => "คอนเสิร์ตการกุศลประจำปี รายได้ทั้งหมดมอบให้กองทุนทุนการศึกษา\nร่วมด้วยวงดนตรีนักศึกษาและศิลปินรับเชิญ",
                'show_start' => $d('+21 days 19:00'), 'show_end' => $d('+21 days 22:00'),
                'sale_start' => $d('-7 days 09:00'), 'sale_end' => $d('+21 days 17:00'),
                'status' => Event::STATUS_PUBLISHED,
                'prices' => ['VIP' => 800, 'A' => 500, 'B' => 300, 'C' => 200],
            ],
            'drama' => [
                'title' => 'ละครเวที "บ้านริมคลอง"',
                'category' => 'drama',
                'organizer' => 'ชุมนุมศิลปะการแสดง',
                'description' => 'ละครเวทีความยาว 2 ชั่วโมง เล่าเรื่องครอบครัวริมคลองในยุคเปลี่ยนผ่าน',
                'show_start' => $d('+10 days 18:30'), 'show_end' => $d('+10 days 20:30'),
                'sale_start' => $d('-3 days 09:00'), 'sale_end' => $d('+10 days 12:00'),
                'status' => Event::STATUS_PUBLISHED,
                'max_seats_per_booking' => 6,
                'prices' => ['VIP' => 400, 'A' => 250, 'B' => 150, 'C' => 100],
            ],
            'seminar' => [
                'title' => 'สัมมนา "AI กับการเรียนรู้ยุคใหม่"',
                'category' => 'seminar',
                'organizer' => 'สำนักวิชาสารสนเทศศาสตร์',
                'description' => 'บรรยายและเสวนาโดยวิทยากรจากภาคเอกชนและมหาวิทยาลัย (เปิดจองเร็ว ๆ นี้)',
                'show_start' => $d('+45 days 09:00'), 'show_end' => $d('+45 days 16:00'),
                'sale_start' => $d('+5 days 09:00'), 'sale_end' => $d('+44 days 17:00'),
                'status' => Event::STATUS_PUBLISHED,
                'prices' => ['A' => 0, 'B' => 0, 'C' => 0],
            ],
            'draft' => [
                'title' => 'ละครสั้นปีใหม่ (ร่าง)',
                'category' => 'drama',
                'organizer' => 'สโมสรนักศึกษา',
                'description' => 'ยังไม่เปิดเผย',
                'show_start' => $d('+60 days 19:00'),
                'status' => Event::STATUS_DRAFT,
                'prices' => ['VIP' => 300, 'A' => 200, 'B' => 120, 'C' => 80],
            ],
        ];

        $zones = array_keys(Hall::zones());
        $events = [];
        foreach ($defs as $key => $def) {
            $prices = $def['prices'];
            unset($def['prices']);
            $e = new Event($def);
            if (!$e->save()) {
                throw new \RuntimeException("$key: " . json_encode($e->errors, JSON_UNESCAPED_UNICODE));
            }
            foreach ($prices as $zone => $price) {
                if (in_array($zone, $zones, true)) {
                    (new EventZonePrice(['event_id' => $e->id, 'zone_code' => $zone, 'price' => $price, 'is_open' => 1]))->save(false);
                }
            }
            $e->refresh();
            $n = $e->syncSeatInventory();
            $this->stdout(sprintf("การแสดง #%d %-45s %4d ที่นั่ง\n", $e->id, $e->title, $n));
            $events[$key] = $e;
        }
        return $events;
    }

    /**
     * @param Event[] $events
     * @param User[] $users
     */
    private function seedBookings(array $events, array $users): void
    {
        $svc = Yii::$app->booking;
        $staff = $users['Udevstaff'];
        $admin = $users['Udevadmin'];
        $buyers = array_values(array_filter($users, fn($u) => str_starts_with($u->line_user_id, 'Useed')));

        foreach (['concert', 'drama'] as $key) {
            $e = $events[$key];

            // กันที่นั่ง: ห้องควบคุมเสียง/แสง แถวท้ายชั้นล่าง
            $blocked = $this->pickSeats($e, 8, 'DESC');
            $svc->setBlocked($e, $blocked, true);

            // จองและชำระแล้ว (บางรายการเช็คอินแล้ว)
            foreach (array_slice($buyers, 0, 6) as $i => $u) {
                $b = $svc->hold($e, $u, $this->pickSeats($e, 2 + $i % 3));
                $this->addPayment($b, $i % 2 ? Payment::METHOD_SLIP : Payment::METHOD_OMISE_PROMPTPAY, Payment::STATUS_SUCCESS, $admin);
                $b->refresh();
                $svc->confirmPaid($b);
                if ($i < 2) {
                    $b->updateAttributes(['checked_in_at' => date('Y-m-d H:i:s'), 'checked_in_by' => $staff->id]);
                }
            }

            // แนบสลิปแล้ว รอตรวจ
            $b = $svc->hold($e, $buyers[6], $this->pickSeats($e, 3));
            $this->addPayment($b, Payment::METHOD_SLIP, Payment::STATUS_PENDING);
            $b->updateAttributes(['status' => Booking::STATUS_VERIFYING]);

            // ล็อกที่นั่งไว้ ยังไม่ชำระ
            $svc->hold($e, $buyers[7], $this->pickSeats($e, 2));
        }

        // การจองของผู้ใช้ dev-login (user) ให้เห็นตั๋วในหน้า "การจองของฉัน"
        $me = $users['Udevuser'];
        $b = $svc->hold($events['concert'], $me, $this->pickSeats($events['concert'], 2));
        $this->addPayment($b, Payment::METHOD_SLIP, Payment::STATUS_SUCCESS, $admin);
        $b->refresh();
        $svc->confirmPaid($b);
        $svc->hold($events['drama'], $me, $this->pickSeats($events['drama'], 2));

        $this->stdout("การจอง: " . Booking::find()->count() . "\n");
    }

    /** เลือกที่นั่งว่างติดกันแบบสุ่มหนึ่งแถว (หรือแถวท้ายสุดเมื่อ $order = DESC) */
    private function pickSeats(Event $e, int $n, string $order = 'RAND'): array
    {
        $rows = (new Query())
            ->select(['s.floor', 's.section', 's.row_label'])
            ->from('{{%event_seat}} es')->innerJoin('{{%seat}} s', 's.id = es.seat_id')
            ->where(['es.event_id' => $e->id, 'es.status' => 'available', 's.floor' => 1])
            ->groupBy(['s.floor', 's.section', 's.row_label', 's.row_index'])
            ->having(['>=', 'COUNT(*)', $n])
            ->orderBy($order === 'DESC' ? ['s.row_index' => SORT_DESC, 's.section' => SORT_ASC] : new \yii\db\Expression('RAND()'))
            ->limit(1)->one();
        $ids = (new Query())->select('s.id')
            ->from('{{%event_seat}} es')->innerJoin('{{%seat}} s', 's.id = es.seat_id')
            ->where(['es.event_id' => $e->id, 'es.status' => 'available'] + [
                's.floor' => $rows['floor'], 's.section' => $rows['section'], 's.row_label' => $rows['row_label'],
            ])
            ->orderBy('s.seat_number')->limit($n)->column();
        return array_map('intval', $ids);
    }

    private function addPayment(Booking $b, string $method, string $status, ?User $verifier = null): void
    {
        $p = new Payment([
            'booking_id' => $b->id,
            'method' => $method,
            'amount' => $b->total_amount,
            'status' => $status,
            'transferred_at' => date('Y-m-d H:i:s', time() - 300),
            'gateway_ref' => $method === Payment::METHOD_SLIP ? null : 'chrg_test_' . Yii::$app->security->generateRandomString(12),
            'note' => 'ข้อมูลตัวอย่าง (seed)',
        ]);
        if ($verifier && $status === Payment::STATUS_SUCCESS && $method === Payment::METHOD_SLIP) {
            $p->verified_by = $verifier->id;
            $p->verified_at = date('Y-m-d H:i:s');
        }
        $p->save(false);
    }
}
