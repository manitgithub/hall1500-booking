<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;

/**
 * LINE Login v2.1 (OAuth 2.0 + OpenID Connect)
 * https://developers.line.biz/en/docs/line-login/integrate-line-login/
 */
class LineLogin extends Component
{
    const AUTHORIZE_URL = 'https://access.line.me/oauth2/v2.1/authorize';
    const TOKEN_URL = 'https://api.line.me/oauth2/v2.1/token';
    const VERIFY_URL = 'https://api.line.me/oauth2/v2.1/verify';

    public $channelId;
    public $channelSecret;
    /** @var string|null normal | aggressive : ชวนผู้ใช้เพิ่มเพื่อน LINE OA */
    public $botPrompt;
    public $scope = 'profile openid email';
    public $callbackRoute = ['/auth/line-callback'];

    public function isConfigured(): bool
    {
        return !empty($this->channelId) && !empty($this->channelSecret);
    }

    public function getRedirectUri(): string
    {
        return Yii::$app->params['appUrl'] . Url::to($this->callbackRoute);
    }

    public function buildAuthorizeUrl(): string
    {
        if (!$this->isConfigured()) {
            throw new InvalidConfigException('ยังไม่ได้ตั้งค่า LINE_CHANNEL_ID / LINE_CHANNEL_SECRET');
        }
        $security = Yii::$app->security;
        $state = $security->generateRandomString(32);
        $nonce = $security->generateRandomString(32);
        Yii::$app->session->set('line_oauth', ['state' => $state, 'nonce' => $nonce]);

        $params = [
            'response_type' => 'code',
            'client_id' => $this->channelId,
            'redirect_uri' => $this->getRedirectUri(),
            'state' => $state,
            'scope' => $this->scope,
            'nonce' => $nonce,
        ];
        if ($this->botPrompt) {
            $params['bot_prompt'] = $this->botPrompt;
        }
        return self::AUTHORIZE_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * แลก code เป็น token แล้ว verify id_token กับ LINE
     * @return array ['sub','name','picture','email']
     */
    public function handleCallback(?string $code, ?string $state): array
    {
        $saved = Yii::$app->session->get('line_oauth');
        Yii::$app->session->remove('line_oauth');
        if (!$saved || !$state || !hash_equals($saved['state'], $state)) {
            throw new BadRequestHttpException('state ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่');
        }
        if (!$code) {
            throw new BadRequestHttpException('ไม่ได้รับ authorization code จาก LINE');
        }

        [$status, $token] = Http::request('POST', self::TOKEN_URL, ['form' => [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
            'client_id' => $this->channelId,
            'client_secret' => $this->channelSecret,
        ]]);
        if ($status !== 200 || empty($token['id_token'])) {
            Yii::error(['line token error', $status, $token], 'line');
            throw new BadRequestHttpException('แลก token กับ LINE ไม่สำเร็จ');
        }

        // ให้ LINE ตรวจลายเซ็น/อายุ/aud/nonce ของ id_token
        [$status, $claims] = Http::request('POST', self::VERIFY_URL, ['form' => [
            'id_token' => $token['id_token'],
            'client_id' => $this->channelId,
            'nonce' => $saved['nonce'],
        ]]);
        if ($status !== 200 || empty($claims['sub'])) {
            Yii::error(['line verify error', $status, $claims], 'line');
            throw new BadRequestHttpException('ตรวจสอบตัวตนกับ LINE ไม่สำเร็จ');
        }

        return [
            'sub' => $claims['sub'],
            'name' => $claims['name'] ?? 'LINE User',
            'picture' => $claims['picture'] ?? null,
            'email' => $claims['email'] ?? null,
        ];
    }
}
