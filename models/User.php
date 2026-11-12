<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * @property int $id
 * @property string|null $line_user_id
 * @property string $display_name
 * @property string|null $picture_url
 * @property string|null $email
 * @property string|null $phone
 * @property string $role
 * @property string $auth_key
 * @property string|null $last_login_at
 */
class User extends ActiveRecord implements IdentityInterface
{
    use TimestampTrait;

    const ROLE_USER = 'user';
    const ROLE_ORGANIZER = 'organizer';
    const ROLE_ADMIN = 'admin';

    public static function tableName()
    {
        return '{{%user}}';
    }

    public function rules()
    {
        return [
            [['display_name'], 'required'],
            [['display_name', 'email'], 'string', 'max' => 255],
            [['email'], 'email'],
            [['phone'], 'match', 'pattern' => '/^0\d{8,9}$/', 'message' => 'เบอร์โทรไม่ถูกต้อง'],
            [['role'], 'in', 'range' => [self::ROLE_USER, self::ROLE_ORGANIZER, self::ROLE_ADMIN]],
        ];
    }

    public function attributeLabels()
    {
        return ['display_name' => 'ชื่อ', 'email' => 'อีเมล', 'phone' => 'เบอร์โทร', 'role' => 'สิทธิ์'];
    }

    /**
     * สร้างหรืออัปเดตผู้ใช้จากโปรไฟล์ LINE
     * @param array $p ['sub','name','picture','email']
     */
    public static function upsertFromLine(array $p): self
    {
        $user = static::findOne(['line_user_id' => $p['sub']]) ?? new static([
            'line_user_id' => $p['sub'],
            'auth_key' => Yii::$app->security->generateRandomString(32),
            'role' => self::ROLE_USER,
        ]);
        $user->display_name = mb_substr($p['name'] ?? 'LINE User', 0, 255);
        $user->picture_url = $p['picture'] ?? $user->picture_url;
        if (!empty($p['email'])) {
            $user->email = $p['email'];
        }
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save(false);
        return $user;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** ผู้จัดงานที่ได้รับอนุมัติ (จัดการได้เฉพาะงานของตัวเอง) */
    public function isOrganizer(): bool
    {
        return $this->role === self::ROLE_ORGANIZER;
    }

    /** เข้าหลังบ้านส่วนจัดการงานได้ (แอดมิน = ทุกงาน, ผู้จัดงาน = งานของตัวเอง) */
    public function canManageEvents(): bool
    {
        return $this->isAdmin() || $this->isOrganizer();
    }

    public static function roleLabels(): array
    {
        return [self::ROLE_USER => 'ผู้ใช้', self::ROLE_ORGANIZER => 'ผู้จัดงาน', self::ROLE_ADMIN => 'แอดมิน'];
    }

    /** ผู้ใช้นี้จัดการงานนี้ได้หรือไม่ (แอดมิน = ทุกงาน, ผู้จัดงาน = งานของตัวเอง) */
    public function managesEvent(Event $event): bool
    {
        return $this->isAdmin() || ($this->isOrganizer() && (int)$event->owner_id === (int)$this->id);
    }

    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        return hash_equals($this->auth_key, (string)$authKey);
    }
}
