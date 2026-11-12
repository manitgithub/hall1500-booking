<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * ใบสมัครเป็นผู้จัดงาน
 * @property int $id
 * @property int $user_id
 * @property string $full_name
 * @property string|null $organization
 * @property string $phone
 * @property string|null $email
 * @property string $id_card_file
 * @property string $id_card_mime
 * @property string $terms_hash
 * @property string $terms_text
 * @property string $terms_accepted_at
 * @property string|null $accepted_ip
 * @property bool $pdpa_consent
 * @property string $status
 * @property int|null $reviewed_by
 * @property string|null $reviewed_at
 * @property string|null $review_note
 *
 * @property User $user
 * @property User|null $reviewer
 */
class OrganizerApplication extends ActiveRecord
{
    use TimestampTrait;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_REVOKED = 'revoked';

    const MAX_FILE = 5 * 1024 * 1024;

    /** @var UploadedFile|null */
    public $idCardUpload;
    /** @var bool */
    public $acceptTerms = false;

    public static function tableName()
    {
        return '{{%organizer_application}}';
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'รอตรวจสอบ',
            self::STATUS_APPROVED => 'อนุมัติแล้ว',
            self::STATUS_REJECTED => 'ไม่อนุมัติ',
            self::STATUS_REVOKED => 'ถูกระงับสิทธิ์',
        ];
    }

    public static function statusBadge(string $s): string
    {
        return ['pending' => 'warning text-dark', 'approved' => 'success', 'rejected' => 'danger', 'revoked' => 'secondary'][$s] ?? 'light';
    }

    public static function storageDir(): string
    {
        $dir = Yii::getAlias('@app/storage/id-cards');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    public function rules()
    {
        return [
            [['full_name', 'phone'], 'required'],
            [['full_name', 'organization', 'email', 'phone'], 'trim'],
            [['full_name', 'organization'], 'string', 'max' => 255],
            [['phone'], 'match', 'pattern' => '/^0\d{8,9}$/', 'message' => 'เบอร์โทร 9-10 หลัก ขึ้นต้นด้วย 0 (ตัวเลขเท่านั้น)'],
            [['email'], 'email'],
            [['idCardUpload'], 'file', 'skipOnEmpty' => false, 'extensions' => 'jpg, jpeg, png, webp, pdf',
                'mimeTypes' => 'image/jpeg, image/png, image/webp, application/pdf', 'maxSize' => self::MAX_FILE,
                'uploadRequired' => 'กรุณาแนบสำเนาบัตรประชาชน', 'on' => 'apply'],
            [['acceptTerms'], 'required', 'requiredValue' => 1, 'message' => 'ต้องอ่านและยอมรับเงื่อนไขก่อนสมัคร', 'on' => 'apply'],
            [['pdpa_consent'], 'required', 'requiredValue' => 1, 'message' => 'ต้องยินยอมให้เก็บสำเนาบัตรเพื่อยืนยันตัวตน', 'on' => 'apply'],
        ];
    }

    public function scenarios()
    {
        $s = parent::scenarios();
        $s['apply'] = ['full_name', 'organization', 'phone', 'email', 'idCardUpload', 'acceptTerms', 'pdpa_consent'];
        return $s;
    }

    public function attributeLabels()
    {
        return [
            'full_name' => 'ชื่อ-นามสกุล (ตามบัตรประชาชน)',
            'organization' => 'หน่วยงาน / ชมรม / บริษัท (ถ้ามี)',
            'phone' => 'เบอร์โทรติดต่อ',
            'email' => 'อีเมล',
            'idCardUpload' => 'สำเนาบัตรประชาชน',
            'acceptTerms' => 'ข้าพเจ้าได้อ่านและยอมรับเงื่อนไขการใช้งานสำหรับผู้จัดงาน และยอมรับว่าเป็นผู้รับผิดชอบงานของตนเองทั้งหมด โดยไม่เกี่ยวข้องกับผู้ดูแลระบบและผู้พัฒนาระบบ',
            'pdpa_consent' => 'ข้าพเจ้ายินยอมให้ผู้ดูแลระบบเก็บสำเนาบัตรประชาชนและข้อมูลติดต่อนี้ เพื่อยืนยันตัวตนและติดต่อเกี่ยวกับงานที่จัด',
            'status' => 'สถานะ', 'review_note' => 'หมายเหตุจากผู้ดูแล', 'created_at' => 'ส่งเมื่อ',
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getReviewer()
    {
        return $this->hasOne(User::class, ['id' => 'reviewed_by']);
    }

    public static function latestFor(int $userId): ?self
    {
        return static::find()->where(['user_id' => $userId])->orderBy(['id' => SORT_DESC])->one();
    }

    /**
     * บันทึกใบสมัคร: เก็บไฟล์บัตรนอก web root และบันทึกฉบับเงื่อนไขที่ยอมรับ
     */
    public function submit(User $user, string $termsText, ?string $ip): bool
    {
        $this->scenario = 'apply';
        $this->idCardUpload = UploadedFile::getInstance($this, 'idCardUpload');
        if (!$this->validate()) {
            return false;
        }
        $ext = strtolower($this->idCardUpload->extension);
        $name = 'u' . $user->id . '-' . time() . '-' . Yii::$app->security->generateRandomString(16) . '.' . $ext;
        if (!$this->idCardUpload->saveAs(self::storageDir() . '/' . $name)) {
            $this->addError('idCardUpload', 'บันทึกไฟล์ไม่สำเร็จ กรุณาลองใหม่');
            return false;
        }
        $this->user_id = $user->id;
        $this->id_card_file = $name;
        $this->id_card_mime = $this->idCardUpload->type ?: 'application/octet-stream';
        $this->terms_text = $termsText;
        $this->terms_hash = hash('sha256', $termsText);
        $this->terms_accepted_at = date('Y-m-d H:i:s');
        $this->accepted_ip = $ip;
        $this->pdpa_consent = 1;
        $this->status = self::STATUS_PENDING;
        return $this->save(false);
    }

    public function idCardPath(): string
    {
        return self::storageDir() . '/' . basename($this->id_card_file);
    }

    public function review(string $status, User $admin, string $note = ''): void
    {
        $tx = static::getDb()->beginTransaction();
        try {
            $this->updateAttributes([
                'status' => $status, 'reviewed_by' => $admin->id,
                'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => $note ?: null,
            ]);
            $u = $this->user;
            if ($status === self::STATUS_APPROVED && $u->role === User::ROLE_USER) {
                $u->updateAttributes(['role' => User::ROLE_ORGANIZER]);
            } elseif (in_array($status, [self::STATUS_REJECTED, self::STATUS_REVOKED], true) && $u->role === User::ROLE_ORGANIZER) {
                $u->updateAttributes(['role' => User::ROLE_USER]);
                // ระงับสิทธิ์: ปิดการขายงานที่ยังเปิดอยู่ของผู้จัดคนนี้
                if ($status === self::STATUS_REVOKED) {
                    Event::updateAll(['status' => Event::STATUS_CLOSED],
                        ['owner_id' => $u->id, 'status' => Event::STATUS_PUBLISHED]);
                }
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }
}
