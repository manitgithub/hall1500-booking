<?php

namespace app\commands;

use app\models\Booking;
use app\models\Event;
use app\models\EventZonePrice;
use app\models\Hall;
use app\models\OrganizerApplication;
use app\models\Payment;
use app\models\Setting;
use app\models\User;
use app\models\Venue;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;

/**
 * ใส่ข้อมูลตัวอย่างสำหรับทดลองบนเครื่อง (ห้ามใช้บน production)
 *   php yii seed            ใส่ข้อมูล (ข้ามถ้ามีการแสดงอยู่แล้ว)
 *   php yii seed --fresh    ลบผู้ใช้ทดสอบ/สถานที่/การแสดง/การจองทั้งหมดแล้วใส่ใหม่
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
        if ($this->fresh) {
            $this->wipe();
        } elseif (Event::find()->exists()) {
            $this->stderr("มีการแสดงอยู่แล้ว — ใช้ --fresh เพื่อลบและใส่ใหม่\n");
            return ExitCode::DATAERR;
        }

        $users = $this->seedUsers();
        $this->seedSettings($users['Udevadmin']);
        $venues = $this->seedVenues();
        $this->seedOrganizers($users);
        $events = $this->seedEvents($venues, $users);
        $this->seedBookings($events, $users);

        $this->stdout("\nเสร็จแล้ว: " . count($users) . " ผู้ใช้, " . count($events) . " การแสดง, "
            . Booking::find()->count() . " รายการจอง\n");
        return ExitCode::OK;
    }

    private function wipe(): void
    {
        $db = Yii::$app->db;
        $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
        foreach (['payment', 'booking_seat', 'event_seat', 'booking', 'event_zone_price', 'event', 'seat', 'venue'] as $t) {
            $db->createCommand()->truncateTable("{{%$t}}")->execute();
        }
        $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
        // ใบสมัครผู้จัดงานของผู้ใช้ทดสอบ (พร้อมไฟล์บัตรตัวอย่าง)
        $testUsers = User::find()->select('id')->where(['or', ['like', 'line_user_id', 'Useed%', false], ['like', 'line_user_id', 'Udev%', false]])->column();
        foreach (OrganizerApplication::findAll(['user_id' => $testUsers]) as $a) {
            @unlink($a->idCardPath());
            $a->delete();
        }
        // ลบเฉพาะผู้ใช้ตัวอย่าง (ไม่แตะผู้ใช้ LINE จริงและผู้ใช้ dev-login)
        User::deleteAll(['like', 'line_user_id', 'Useed%', false]);
        $this->stdout("ลบข้อมูลเดิมแล้ว\n");
    }

    /** @return User[] */
    private function seedUsers(): array
    {
        $list = [
            ['Udevadmin', 'ทดสอบ (admin)', 'admin'],
            ['Udevorganizer', 'ทดสอบ (organizer)', 'organizer'],
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
            ['Useed09', 'วรวุฒิ จัดเก่ง', 'user'],
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

    /** ค่าตั้งค่า PromptPay (แก้ต่อได้ที่ หลังบ้าน → ตั้งค่า PromptPay) */
    private function seedSettings(User $admin): void
    {
        Setting::set(Setting::PROMPTPAY_ID, '0931464976', $admin->id);
        Setting::set(Setting::PROMPTPAY_NAME, Setting::promptpayName() ?: Setting::siteName(), $admin->id);
        $this->stdout('PromptPay: ' . Setting::promptpayId() . ' (' . Setting::promptpayName() . ")\n");
    }

    /**
     * ผู้จัดงานตัวอย่าง: ทดสอบ (organizer) อนุมัติแล้ว, วรวุฒิ รอตรวจสอบ
     * ใช้ภาพบัตรปลอมที่เขียนว่า SAMPLE เท่านั้น
     * @param User[] $users
     */
    private function seedOrganizers(array $users): void
    {
        $terms = Setting::organizerTerms();
        $defs = [
            ['Udevorganizer', 'ผู้จัด ทดสอบ', 'ชุมนุมศิลปะการแสดง', OrganizerApplication::STATUS_APPROVED],
            ['Useed09', 'วรวุฒิ จัดเก่ง', 'ชมรมดนตรีไทย', OrganizerApplication::STATUS_PENDING],
        ];
        foreach ($defs as $i => [$sub, $name, $org, $status]) {
            $u = $users[$sub];
            $file = 'u' . $u->id . '-seed-sample.png';
            $this->sampleIdCard(OrganizerApplication::storageDir() . '/' . $file, $i + 1);
            $a = new OrganizerApplication([
                'user_id' => $u->id, 'full_name' => $name, 'organization' => $org, 'phone' => $u->phone, 'email' => $u->email,
                'id_card_file' => $file, 'id_card_mime' => 'image/png',
                'terms_text' => $terms, 'terms_hash' => hash('sha256', $terms),
                'terms_accepted_at' => date('Y-m-d H:i:s', strtotime('-2 days')), 'accepted_ip' => '127.0.0.1',
                'pdpa_consent' => 1, 'status' => $status,
            ]);
            if ($status === OrganizerApplication::STATUS_APPROVED) {
                $a->reviewed_by = $users['Udevadmin']->id;
                $a->reviewed_at = date('Y-m-d H:i:s', strtotime('-1 day'));
            }
            $a->save(false);
        }
        $this->stdout("ใบสมัครผู้จัดงาน: " . count($defs) . " (อนุมัติ 1, รอตรวจ 1)\n");
    }

    /** ภาพบัตรตัวอย่าง (ไม่ใช่บัตรจริง) */
    private function sampleIdCard(string $path, int $n): void
    {
        $im = imagecreatetruecolor(640, 400);
        imagefill($im, 0, 0, imagecolorallocate($im, 220, 235, 245));
        $dark = imagecolorallocate($im, 40, 60, 90);
        $red = imagecolorallocate($im, 200, 30, 30);
        imagerectangle($im, 10, 10, 629, 389, $dark);
        imagefilledrectangle($im, 40, 110, 200, 300, imagecolorallocate($im, 180, 195, 210));
        imagestring($im, 5, 230, 60, 'SAMPLE ID CARD - NOT A REAL DOCUMENT', $red);
        imagestring($im, 5, 230, 130, 'Name: Test Organizer ' . $n, $dark);
        imagestring($im, 5, 230, 160, 'ID: X-XXXX-XXXXX-XX-X', $dark);
        imagestring($im, 5, 230, 190, 'For development seed data only', $dark);
        imagestring($im, 5, 230, 320, 'SAMPLE  SAMPLE  SAMPLE', $red);
        imagepng($im, $path);
        imagedestroy($im);
    }

    /** @return Venue[] keyed by slug */
    private function seedVenues(): array
    {
        $defs = [
            'hall1500' => ['name' => 'ห้องประชุม 1500', 'location' => 'อาคารไทยบุรี มหาวิทยาลัยวลัยลักษณ์',
                'description' => 'ผังจริง 7 โซน 1,425 ที่นั่ง', 'layout' => 'hall1500'],
            'theatre' => ['name' => 'โรงละครเล็ก', 'location' => 'ศูนย์ศิลปวัฒนธรรม ชั้น 2', 'layout' => Venue::LAYOUT_GRID,
                'grid_rows' => 12, 'grid_sections' => '6,14,6', 'grid_zone_vip' => 2, 'grid_zone_a' => 4, 'grid_zone_b' => 3],
            'seminar' => ['name' => 'ห้องสัมมนา 2', 'location' => 'อาคารเรียนรวม ชั้น 3', 'layout' => Venue::LAYOUT_GRID,
                'grid_rows' => 10, 'grid_sections' => '10,10', 'grid_zone_vip' => 0, 'grid_zone_a' => 5, 'grid_zone_b' => 5],
        ];
        $venues = [];
        foreach ($defs as $key => $def) {
            $v = Venue::findOne(['name' => $def['name']]) ?? new Venue();
            Yii::configure($v, $def);
            $v->is_active = true;
            if (!$v->save()) {
                throw new \RuntimeException("$key: " . json_encode($v->errors, JSON_UNESCAPED_UNICODE));
            }
            $n = $v->generateSeats();
            $this->stdout(sprintf("สถานที่ #%d %-30s %5d ที่นั่ง\n", $v->id, $v->name, $n));
            $venues[$key] = $v;
        }
        return $venues;
    }

    /**
     * @param Venue[] $venues
     * @param User[] $users
     * @return Event[] keyed by slug
     */
    private function seedEvents(array $venues, array $users): array
    {
        $d = fn(string $rel) => date('Y-m-d H:i:00', strtotime($rel));
        $defs = [
            'concert' => [
                'venue_id' => $venues['hall1500']->id,
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
                'venue_id' => $venues['theatre']->id,
                'owner_id' => $users['Udevorganizer']->id, // งานของผู้จัดงาน
                'title' => 'ละครเวที "บ้านริมคลอง"',
                'category' => 'drama',
                'organizer' => 'ชุมนุมศิลปะการแสดง',
                'description' => 'ละครเวทีความยาว 2 ชั่วโมง เล่าเรื่องครอบครัวริมคลองในยุคเปลี่ยนผ่าน',
                'show_start' => $d('+10 days 18:30'), 'show_end' => $d('+10 days 20:30'),
                'sale_start' => $d('-3 days 09:00'), 'sale_end' => $d('+10 days 12:00'),
                'status' => Event::STATUS_PUBLISHED,
                'max_seats_per_booking' => 6,
                // งานนี้รับเงินเข้าบัญชีของผู้จัดเอง (งานอื่นใช้บัญชีกลาง)
                'promptpay_id' => '0812345678',
                'promptpay_name' => 'ชุมนุมศิลปะการแสดง',
                'prices' => ['VIP' => 400, 'A' => 250, 'B' => 150, 'C' => 100],
            ],
            'seminar' => [
                'venue_id' => $venues['seminar']->id,
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
                'venue_id' => $venues['theatre']->id,
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
