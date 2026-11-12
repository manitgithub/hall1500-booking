<?php

namespace app\components;

use yii\base\Exception;

/**
 * HTTP client เล็ก ๆ ด้วย cURL (ไม่ต้องพึ่ง extension เพิ่ม)
 */
class Http
{
    /**
     * @return array{0:int,1:array|null,2:string} [status, json, raw body]
     */
    public static function request(string $method, string $url, array $opts = []): array
    {
        $ch = curl_init();
        $headers = $opts['headers'] ?? [];
        if (isset($opts['form'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opts['form']));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif (isset($opts['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json'], JSON_UNESCAPED_UNICODE));
            $headers[] = 'Content-Type: application/json';
        }
        if (isset($opts['basicAuth'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $opts['basicAuth']);
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $opts['timeout'] ?? 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("HTTP request failed: $err");
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode($body, true);
        return [$status, is_array($json) ? $json : null, $body];
    }
}
