<?php

namespace app\controllers;

use app\models\Booking;
use app\models\Event;
use app\models\EventZonePrice;
use app\models\Hall;
use app\models\Payment;
use app\models\PaymentSettingsForm;
use app\models\User;
use app\models\Venue;
use Yii;
use yii\base\UserException;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * หลังบ้าน: จัดการรอบการแสดง ราคาโซน ตรวจสลิป รายการจอง ผู้ใช้
 */
class AdminController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [[
                'allow' => true, 'roles' => ['@'],
                'matchCallback' => fn() => Yii::$app->user->identity->isAdmin(),
            ]]],
            'verbs' => ['class' => VerbFilter::class, 'actions' => [
                'block' => ['post'], 'payment-approve' => ['post'], 'payment-reject' => ['post'],
                'booking-cancel' => ['post'], 'user-role' => ['post'], 'sync-seats' => ['post'], 'venue-generate' => ['post'],
            ]],
        ];
    }

    public function actionIndex()
    {
        Yii::$app->booking->releaseExpired();
        $events = Event::find()->orderBy(['show_start' => SORT_DESC])->all();
        $revenue = Booking::find()->select(['event_id', 'total' => 'SUM(total_amount)', 'cnt' => 'SUM(seat_count)'])
            ->where(['status' => Booking::STATUS_PAID])->groupBy('event_id')->indexBy('event_id')->asArray()->all();
        $pendingSlips = Payment::find()->where(['status' => Payment::STATUS_PENDING, 'method' => Payment::METHOD_SLIP])->count();
        return $this->render('index', compact('events', 'revenue', 'pendingSlips'));
    }

    // ---------------------------------------------------------------- events

    public function actionEventCreate()
    {
        if (!Venue::options()) {
            Yii::$app->session->setFlash('warning', 'กรุณาเพิ่มสถานที่ก่อนสร้างการแสดง');
            return $this->redirect(['venue-create']);
        }
        $event = new Event([
            'venue_id' => (int)Yii::$app->request->get('venue_id') ?: array_key_first(Venue::options()),
            'status' => Event::STATUS_DRAFT,
            'category' => 'concert',
            'max_seats_per_booking' => Yii::$app->params['booking']['maxSeatsPerBooking'],
            'hold_minutes' => Yii::$app->params['booking']['holdMinutes'],
        ]);
        return $this->saveEvent($event);
    }

    public function actionEventUpdate($id)
    {
        return $this->saveEvent($this->findEvent($id));
    }

    private function saveEvent(Event $event)
    {
        $prices = $this->zonePriceModels($event);
        $req = Yii::$app->request;
        if ($event->load($req->post())) {
            $event->posterFile = UploadedFile::getInstance($event, 'posterFile');
            $postedPrices = $req->post('ZonePrice', []);
            foreach ($prices as $code => $zp) {
                $zp->price = $postedPrices[$code]['price'] ?? $zp->price;
                $zp->is_open = !empty($postedPrices[$code]['is_open']);
            }
            $valid = $event->validate();
            foreach ($prices as $zp) {
                $valid = $zp->validate() && $valid;
            }
            if ($valid) {
                $tx = Yii::$app->db->beginTransaction();
                try {
                    if ($event->posterFile) {
                        $name = 'p' . time() . '-' . Yii::$app->security->generateRandomString(6) . '.' . $event->posterFile->extension;
                        $event->posterFile->saveAs(Yii::getAlias('@webroot/uploads/posters/') . $name);
                        $event->poster = $name;
                    }
                    $event->save(false);
                    foreach ($prices as $zp) {
                        $zp->event_id = $event->id;
                        $zp->save(false);
                    }
                    $tx->commit();
                } catch (\Throwable $e) {
                    $tx->rollBack();
                    throw $e;
                }
                // เปิดขายแล้วต้องมีรายการที่นั่ง -> สร้าง/อัปเดตให้อัตโนมัติ
                if ($event->status === Event::STATUS_PUBLISHED || $event->hasSeatInventory()) {
                    $n = $event->syncSeatInventory();
                    Yii::$app->session->setFlash('success', "บันทึกแล้ว — ที่นั่งที่เปิดขาย $n ที่");
                } else {
                    Yii::$app->session->setFlash('success', 'บันทึกแล้ว (สถานะร่าง)');
                }
                return $this->redirect(['event-update', 'id' => $event->id]);
            }
        }
        return $this->render('event-form', ['event' => $event, 'prices' => $prices, 'venueSeatCounts' => $this->venueSeatCounts()]);
    }

    /** @return EventZonePrice[] indexed by zone code */
    private function zonePriceModels(Event $event): array
    {
        $existing = $event->isNewRecord ? [] : EventZonePrice::find()->where(['event_id' => $event->id])->indexBy('zone_code')->all();
        $out = [];
        foreach (Hall::zones() as $code => $z) {
            $out[$code] = $existing[$code] ?? new EventZonePrice([
                'zone_code' => $code, 'price' => $z['default_price'], 'is_open' => true,
            ]);
        }
        return $out;
    }

    public function actionSyncSeats($id)
    {
        $n = $this->findEvent($id)->syncSeatInventory();
        Yii::$app->session->setFlash('success', "อัปเดตรายการที่นั่งแล้ว ($n ที่)");
        return $this->redirect(['event-seats', 'id' => $id]);
    }

    /** ผังที่นั่งฝั่งแอดมิน: ดูสถานะ / กันที่นั่ง */
    public function actionEventSeats($id)
    {
        $event = $this->findEvent($id);
        $holders = Yii::$app->db->createCommand("
            SELECT es.seat_id, b.code, u.display_name, es.status
            FROM {{%event_seat}} es JOIN {{%booking}} b ON b.id = es.booking_id JOIN {{%user}} u ON u.id = b.user_id
            WHERE es.event_id = :e AND es.booking_id IS NOT NULL
        ", [':e' => $event->id])->queryAll();
        return $this->render('event-seats', ['event' => $event, 'summary' => $event->seatSummary(), 'holders' => $holders]);
    }

    public function actionBlock($id)
    {
        $event = $this->findEvent($id);
        Yii::$app->response->format = Response::FORMAT_JSON;
        $req = Yii::$app->request;
        $n = Yii::$app->booking->setBlocked($event, (array)$req->post('seats', []), (bool)$req->post('block'));
        return ['ok' => true, 'changed' => $n];
    }

    // -------------------------------------------------------------- payments

    public function actionPayments($status = Payment::STATUS_PENDING)
    {
        $provider = new ActiveDataProvider([
            'query' => Payment::find()->with(['booking.event', 'booking.user', 'booking.seats', 'verifier'])
                ->where(['method' => Payment::METHOD_SLIP])
                ->andFilterWhere(['status' => $status ?: null])
                ->orderBy(['id' => $status === Payment::STATUS_PENDING ? SORT_ASC : SORT_DESC]),
            'pagination' => ['pageSize' => 20],
        ]);
        return $this->render('payments', ['provider' => $provider, 'status' => $status]);
    }

    public function actionPaymentApprove($id)
    {
        $p = $this->findPayment($id);
        try {
            $ok = Yii::$app->booking->approvePayment($p, Yii::$app->user->identity);
            Yii::$app->session->setFlash($ok ? 'success' : 'warning', $ok
                ? "อนุมัติ {$p->booking->code} แล้ว ออกตั๋วเรียบร้อย"
                : "ยืนยันเงินแล้ว แต่ที่นั่งของ {$p->booking->code} ถูกปล่อยไปแล้ว — ต้องคืนเงิน");
        } catch (UserException $e) {
            Yii::$app->session->setFlash('danger', $e->getMessage());
        }
        return $this->redirect(['payments']);
    }

    public function actionPaymentReject($id)
    {
        $p = $this->findPayment($id);
        $reason = trim((string)Yii::$app->request->post('reason')) ?: 'ยอดเงินหรือสลิปไม่ถูกต้อง';
        try {
            Yii::$app->booking->rejectPayment($p, Yii::$app->user->identity, $reason);
            Yii::$app->session->setFlash('info', "ปฏิเสธสลิป {$p->booking->code} แล้ว");
        } catch (UserException $e) {
            Yii::$app->session->setFlash('danger', $e->getMessage());
        }
        return $this->redirect(['payments']);
    }

    // -------------------------------------------------------------- bookings

    public function actionBookings($event_id = null, $status = null, $q = null)
    {
        $query = Booking::find()->with(['event', 'user', 'seats'])->orderBy(['id' => SORT_DESC]);
        $query->andFilterWhere(['event_id' => $event_id ?: null, 'status' => $status ?: null]);
        if ($q) {
            $query->joinWith('user u')->andWhere(['or', ['like', 'code', $q], ['like', 'u.display_name', $q]]);
        }
        return $this->render('bookings', [
            'provider' => new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 30]]),
            'events' => Event::find()->select(['title', 'id'])->indexBy('id')->column(),
            'event_id' => $event_id, 'status' => $status, 'q' => $q,
        ]);
    }

    public function actionBookingCancel($id)
    {
        $b = Booking::findOne((int)$id) ?? throw new NotFoundHttpException();
        $status = Yii::$app->request->post('refund') ? Booking::STATUS_REFUNDED : Booking::STATUS_CANCELLED;
        Yii::$app->booking->adminCancel($b, $status, 'ยกเลิกโดย ' . Yii::$app->user->identity->display_name);
        Yii::$app->session->setFlash('info', "ยกเลิก {$b->code} และปล่อยที่นั่งแล้ว");
        return $this->redirect(Yii::$app->request->referrer ?: ['bookings']);
    }

    /** ส่งออก CSV รายชื่อผู้ซื้อของรอบการแสดง */
    public function actionExport($event_id)
    {
        $event = $this->findEvent($event_id);
        $rows = Booking::find()->with(['user', 'seats'])
            ->where(['event_id' => $event->id, 'status' => Booking::STATUS_PAID])->orderBy('id')->all();
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF"); // BOM ให้ Excel อ่านภาษาไทยได้
        fputcsv($fh, ['รหัสจอง', 'ชื่อ', 'อีเมล', 'เบอร์โทร', 'ที่นั่ง', 'จำนวน', 'ยอดเงิน', 'ชำระเมื่อ', 'เช็คอิน']);
        foreach ($rows as $b) {
            fputcsv($fh, [$b->code, $b->user->display_name, $b->user->email, $b->user->phone, $b->seatLabels(),
                $b->seat_count, $b->total_amount, $b->paid_at, $b->checked_in_at]);
        }
        rewind($fh);
        return Yii::$app->response->sendContentAsFile(stream_get_contents($fh), "bookings-event{$event->id}.csv",
            ['mimeType' => 'text/csv']);
    }

    // ----------------------------------------------------------------- users

    public function actionUsers($q = null)
    {
        $query = User::find()->orderBy(['id' => SORT_DESC]);
        if ($q) {
            $query->andWhere(['or', ['like', 'display_name', $q], ['like', 'email', $q]]);
        }
        return $this->render('users', ['provider' => new ActiveDataProvider(['query' => $query]), 'q' => $q]);
    }

    public function actionUserRole($id)
    {
        $u = User::findOne((int)$id) ?? throw new NotFoundHttpException();
        $role = Yii::$app->request->post('role');
        if ($u->id === Yii::$app->user->id) {
            Yii::$app->session->setFlash('warning', 'ไม่สามารถเปลี่ยนสิทธิ์ตัวเองได้');
        } elseif (in_array($role, [User::ROLE_USER, User::ROLE_STAFF, User::ROLE_ADMIN], true)) {
            $u->updateAttributes(['role' => $role]);
            Yii::$app->session->setFlash('success', "เปลี่ยนสิทธิ์ {$u->display_name} เป็น $role แล้ว");
        }
        return $this->redirect(['users']);
    }

    /** [venue_id => [zone => จำนวนที่นั่ง]] สำหรับแสดงในฟอร์มการแสดง */
    private function venueSeatCounts(): array
    {
        $rows = (new \yii\db\Query())->select(['venue_id', 'zone_code', 'c' => 'COUNT(*)'])->from('{{%seat}}')
            ->where(['is_active' => 1])->groupBy(['venue_id', 'zone_code'])->all();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['venue_id']][$r['zone_code']] = (int)$r['c'];
        }
        return $out;
    }

    // ---------------------------------------------------------------- venues

    public function actionVenues()
    {
        $venues = Venue::find()->orderBy(['is_active' => SORT_DESC, 'name' => SORT_ASC])->all();
        $eventCounts = Event::find()->select(['c' => 'COUNT(*)', 'venue_id'])->groupBy('venue_id')->indexBy('venue_id')->column();
        return $this->render('venues', ['venues' => $venues, 'eventCounts' => $eventCounts]);
    }

    public function actionVenueCreate()
    {
        return $this->saveVenue(new Venue(['layout' => Venue::LAYOUT_GRID, 'is_active' => true]));
    }

    public function actionVenueUpdate($id)
    {
        return $this->saveVenue($this->findVenue($id));
    }

    private function saveVenue(Venue $venue)
    {
        if ($venue->load(Yii::$app->request->post()) && $venue->validate()) {
            $layoutChanged = $venue->isNewRecord || $venue->isAttributeChanged('layout') || $venue->isAttributeChanged('settings')
                || $venue->isGrid();
            $venue->save(false);
            if ($layoutChanged) {
                $n = $venue->generateSeats();
                Yii::$app->session->setFlash('success', "บันทึกสถานที่แล้ว — ผังมี $n ที่นั่ง"
                    . ($venue->hasSales() ? ' (มีการจองแล้ว: ที่นั่งเดิมถูกอัปเดต ไม่ลบประวัติ)' : ''));
            } else {
                Yii::$app->session->setFlash('success', 'บันทึกสถานที่แล้ว');
            }
            return $this->redirect(['venue-update', 'id' => $venue->id]);
        }
        return $this->render('venue-form', ['venue' => $venue]);
    }

    /** สร้างที่นั่งใหม่จากผัง (เช่น หลังแก้ไฟล์ config/venues/*.php) */
    public function actionVenueGenerate($id)
    {
        $venue = $this->findVenue($id);
        $n = $venue->generateSeats();
        Yii::$app->session->setFlash('success', "สร้างผังที่นั่งแล้ว $n ที่นั่ง — การแสดงที่เปิดขายอยู่ให้กด 'อัปเดตรายการที่นั่ง'");
        return $this->redirect(['venue-update', 'id' => $id]);
    }

    /** ข้อมูลผังสำหรับดูตัวอย่าง (seatmap.js) */
    public function actionVenueSeats($id)
    {
        $venue = $this->findVenue($id);
        Yii::$app->response->format = Response::FORMAT_JSON;
        $rows = (new \yii\db\Query())->from('{{%seat}}')->where(['venue_id' => $venue->id, 'is_active' => 1])
            ->orderBy(['floor' => SORT_ASC, 'row_index' => SORT_ASC, 'pos_x' => SORT_ASC])->all();
        $zones = [];
        foreach (Hall::zones() as $code => $z) {
            $zones[$code] = ['label' => $z['label'], 'color' => $z['color']];
        }
        return [
            'seats' => array_map(fn($r) => [
                'id' => (int)$r['id'], 'f' => (int)$r['floor'], 'r' => $r['row_label'], 'n' => (int)$r['seat_number'],
                'l' => $r['label'], 'z' => $r['zone_code'], 'x' => (float)$r['pos_x'], 'y' => (float)$r['pos_y'],
                'w' => (bool)$r['is_wheelchair'], 's' => 'available', 'p' => null,
            ], $rows),
            'zones' => $zones, 'floors' => $venue->floorLabels(), 'seatSize' => $venue->seatSize(),
        ];
    }

    private function findVenue($id): Venue
    {
        return Venue::findOne((int)$id) ?? throw new NotFoundHttpException();
    }

    // -------------------------------------------------------------- settings

    /** ตั้งค่าระบบ: ชื่อระบบ และ PromptPay */
    public function actionSettings()
    {
        $model = new PaymentSettingsForm();
        if ($model->load(Yii::$app->request->post()) && $model->save(Yii::$app->user->id)) {
            Yii::$app->session->setFlash('success', 'บันทึกการตั้งค่าแล้ว');
            return $this->refresh();
        }
        return $this->render('settings', ['model' => $model]);
    }

    // --------------------------------------------------------------- helpers

    private function findEvent($id): Event
    {
        return Event::findOne((int)$id) ?? throw new NotFoundHttpException();
    }

    private function findPayment($id): Payment
    {
        return Payment::find()->with('booking.event')->where(['id' => (int)$id])->one() ?? throw new NotFoundHttpException();
    }
}
