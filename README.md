# ระบบจองที่นั่งออนไลน์ (Yii2 + MySQL + LINE Login)

ระบบจองที่นั่งสำหรับหอประชุม โรงละคร ห้องประชุม หรือสถานที่ใดก็ได้ รองรับหลายสถานที่ในระบบเดียว
แต่ละการแสดง (คอนเสิร์ต ละคร สัมมนา) เลือกได้ว่าจัดที่ไหน และจองตามผังที่นั่งของสถานที่นั้น

มีผังละเอียดของ ห้องประชุม 1500 อาคารไทยบุรี มหาวิทยาลัยวลัยลักษณ์ (1,425 ที่นั่ง) มาให้เป็นตัวอย่าง

**ผู้พัฒนา:** [มานิต จิตต์ประไพย](https://manitgithub.github.io/)

## ความสามารถ

| ส่วน | รายละเอียด |
|---|---|
| เข้าสู่ระบบ | LINE Login v2.1 (OAuth2 + OpenID Connect, ตรวจ `state`/`nonce` และ verify `id_token` กับ LINE) |
| สถานที่ | หลายสถานที่ ผังที่นั่งแยกกัน สร้างผังแบบแถว × บล็อกได้จากหน้าแอดมิน หรือใช้ผังละเอียดจากแบบจริง (`config/venues/*.php`) |
| ผังที่นั่ง | SVG แบบโต้ตอบ แยกโซน/ทางเดิน สีตามโซนราคา ซูมได้ ใช้บนมือถือได้ อัปเดตสถานะทุก 15 วินาที |
| การจอง | ล็อกที่นั่งแบบ atomic (กันจองซ้ำ) ให้เวลาชำระตามที่ตั้งไว้ (ค่าเริ่มต้น 15 นาที) หมดเวลาปล่อยที่นั่งอัตโนมัติ |
| ชำระเงิน | ① QR PromptPay พร้อมยอดเงิน + แนบสลิป → แอดมินตรวจ/อนุมัติ ② (ไม่บังคับ) Omise: PromptPay และบัตรเครดิต ยืนยันอัตโนมัติผ่าน webhook |
| ตั๋ว | E-ticket พร้อม QR (1 QR ต่อ 1 รายการจอง) แจ้งผลผ่าน LINE Messaging API (ไม่บังคับ) |
| หน้างาน | หน้าสแกน QR ด้วยกล้องมือถือสำหรับเจ้าหน้าที่ กันการเช็คอินซ้ำ |
| หลังบ้าน | จัดการสถานที่และผังที่นั่ง, ตั้งชื่อระบบ/PromptPay, สร้างการแสดง/ราคาตามโซน/ช่วงเปิดขาย, กันที่นั่ง (ห้องควบคุม/แขก VIP), ตรวจสลิป, ยกเลิก/คืนเงิน, ส่งออก CSV, จัดการสิทธิ์ผู้ใช้ |

สิทธิ์ผู้ใช้: `user` (จองตั๋ว) · `staff` (เช็คอิน) · `admin` (ทุกอย่าง)

## ความต้องการของระบบ

- PHP 8.1 ขึ้นไป พร้อม extension: `pdo_mysql`, `curl`, `gd`, `intl`, `mbstring`
- MySQL 5.7+/8.0 หรือ MariaDB 10.4+
- Composer
- HTTPS บน production (LINE Login บังคับใช้ callback แบบ https)

## ติดตั้ง

```bash
composer install
```

```bash
cp .env.example .env
```

แก้ `.env` ให้ครบ (DB, LINE, `COOKIE_VALIDATION_KEY`) แล้วสร้างฐานข้อมูล:

```bash
mysql -uroot -p -e "CREATE DATABASE hall1500 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

```bash
php yii migrate
```

```bash
php yii hall/generate
```

ใส่ข้อมูลตัวอย่าง (สถานที่ 3 แห่ง ผู้ใช้ การแสดง การจอง และ PromptPay `0931464976`) — ใช้ `--fresh` เพื่อลบแล้วใส่ใหม่:

```bash
php yii seed
```

ทดลองรันบนเครื่อง (ตั้ง `DEV_LOGIN=true` เพื่อใช้ปุ่มล็อกอินทดสอบ user/staff/admin โดยไม่ต้องใช้ LINE):

```bash
php -S localhost:8080 -t web
```

> โปรเจกต์นี้ไม่ใช้ asset-packagist: jQuery, Bootstrap 5 และ Bootstrap Icons โหลดจาก CDN
> (`code.jquery.com`, `cdn.jsdelivr.net`) ส่วนหน้าเช็คอินใช้ `html5-qrcode` จาก `unpkg.com`

### ตั้งแอดมินคนแรก

ล็อกอินด้วย LINE หนึ่งครั้ง แล้วรัน:

```bash
php yii user/list
```

```bash
php yii user/role 1 admin
```

### Cron (ปล่อยที่นั่งที่หมดเวลาชำระ)

ระบบปล่อยที่นั่งให้เองตอนมีคนเปิดผัง แต่ควรตั้ง cron ทุกนาทีไว้ด้วย:

```bash
* * * * * php /var/www/hall1500/yii booking/expire >/dev/null 2>&1
```

## ตั้งค่า LINE

1. เข้า [LINE Developers Console](https://developers.line.biz/console/) → สร้าง Provider → สร้าง channel แบบ **LINE Login**
2. แท็บ *LINE Login* → Callback URL: `https://<โดเมน>/auth/line-callback` (ต้องตรงกับ `APP_URL` ใน `.env`)
3. ถ้าต้องการอีเมล: ขอสิทธิ์ *OpenID Connect → Email address permission*
4. นำ **Channel ID** และ **Channel secret** ใส่ `LINE_CHANNEL_ID`, `LINE_CHANNEL_SECRET`
5. เปลี่ยนสถานะ channel จาก *Developing* เป็น *Published* เมื่อใช้งานจริง

**ส่งข้อความแจ้งผลผ่าน LINE (ไม่บังคับ):** สร้าง LINE Official Account (Messaging API channel) ใน Provider เดียวกัน,
ลิงก์กับ LINE Login channel (*Linked OA*), ตั้ง `LINE_BOT_PROMPT=aggressive` เพื่อชวนผู้ใช้เพิ่มเพื่อนตอนล็อกอิน
แล้วใส่ Channel access token ใน `LINE_MESSAGING_TOKEN`

## ตั้งค่าการชำระเงิน

### PromptPay + แนบสลิป (ค่าเริ่มต้น)
- ตั้งหมายเลขและชื่อบัญชีได้ที่ *หลังบ้าน → ตั้งค่า PromptPay* (เบอร์มือถือ 10 หลัก หรือเลขผู้เสียภาษี 13 หลักของหน่วยงาน)
- `PROMPTPAY_ID` / `PROMPTPAY_NAME` ใน `.env` เป็นค่าเริ่มต้น ใช้เมื่อยังไม่เคยบันทึกในหลังบ้าน
- ผู้ใช้สแกน QR ที่มียอดเงินตรงกับรายการ แล้วแนบสลิป → ที่นั่งถูกล็อกค้างไว้จนกว่าแอดมินตรวจ
- แอดมินดูที่เมนู *หลังบ้าน → ตรวจสลิป* กด **อนุมัติ** (ออกตั๋ว + แจ้ง LINE) หรือ **ไม่ผ่าน** (ผู้ใช้ได้เวลาแนบใหม่อีกรอบ)

### Omise (ไม่บังคับ — ยืนยันอัตโนมัติ)
- ใส่ `OMISE_PUBLIC_KEY`, `OMISE_SECRET_KEY` แล้วปุ่ม "PromptPay (ตัดยอดอัตโนมัติ)" และ "บัตรเครดิต" จะแสดงเอง
- ตั้ง Webhook ใน Omise Dashboard: `https://<โดเมน>/payment/omise-webhook`
- ระบบไม่เชื่อข้อมูลใน webhook ตรง ๆ แต่ดึง charge จาก Omise API มาตรวจสถานะ ยอดเงิน และรหัสจองอีกครั้ง

## สถานที่และผังที่นั่ง

ตั้งชื่อระบบ (แถบเมนู/ชื่อแท็บ) และ PromptPay ได้ที่ *หลังบ้าน → ตั้งค่าระบบ*

เพิ่ม/แก้สถานที่ที่ *หลังบ้าน → สถานที่ / ผังที่นั่ง* แล้วเวลาสร้างการแสดงให้เลือก "สถานที่จัด"
(เปลี่ยนสถานที่ได้จนกว่าจะมีการจอง) ผังที่นั่งมี 2 แบบ:

**1. สร้างผังเอง (แถว × บล็อก)** — กรอกในหน้าเว็บได้เลย
- จำนวนแถว (ป้าย A, B, C … จากหน้าเวที)
- ที่นั่งต่อแถวของแต่ละบล็อก เช่น `8,16,8` = 3 บล็อก มีทางเดินคั่น เลขที่นั่งนับต่อเนื่องทั้งแถว
- โซนราคาเป็นช่วงแถว: VIP / A / B นับจากหน้าเวที แถวที่เหลือเป็นโซน C

**2. ผังละเอียดจากแบบจริง** — ไฟล์ `config/venues/<ชื่อ>.php` (`type => measured`)
กำหนดตำแหน่งที่นั่งรายแถวรายโซน ดูตัวอย่างใน `config/venues/hall1500.php`
ซึ่งวัดจาก [แผนผังห้อง 1500](https://ces.wu.ac.th/registrar/Plan1500.asp):

| ส่วน | แถว | โซน (ซ้าย → ขวาเมื่อหันหน้าเข้าเวที) | ที่นั่ง |
|---|---|---|---|
| ด้านหน้า | A–O | 1 · 2 · 3 · 4 | 197 + 241 + 241 + 197 = 876 |
| ด้านหลัง | P–AA | 5 · 6 · 7 | 165 + 219 + 165 = 549 |
| รวม | | | **1,425** |

เลขที่นั่งนับต่อเนื่องทั้งแถวข้ามโซน (เช่น แถว O = 1–64) ตามแผนผังต้นฉบับ
แถวหลังสุดของโซน 6 ต้นฉบับเขียน "a" ระบบใช้ "AA"

โซนราคา (`config/zones.php`) ใช้ร่วมกันทุกสถานที่: VIP, A, B, C — ราคาจริงตั้งต่อการแสดง

แก้ผังแล้วกด *บันทึกและสร้างผังที่นั่ง* (หรือ *สร้างผังที่นั่งใหม่จากไฟล์*) หรือรัน:

```bash
php yii hall/generate --venue=1
```

ถ้ามีการขายแล้ว ระบบอัปเดตที่นั่งเดิมโดยจับคู่จากชั้น+ป้ายที่นั่ง และไม่ลบประวัติการจอง
จากนั้นกด *อัปเดตรายการที่นั่ง* ในหน้าผังที่นั่งของแต่ละการแสดง

## Deploy (Apache / Nginx)

ให้ document root ชี้ไปที่ `web/` เท่านั้น ห้ามชี้ที่รากโปรเจกต์

Nginx:

```nginx
server {
    server_name booking.example.ac.th;
    root /var/www/hall1500/web;
    index index.php;
    client_max_body_size 6M;
    location / { try_files $uri $uri/ /index.php$is_args$args; }
    location ~ ^/uploads/.*\.php$ { deny all; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

Apache ใช้ `web/.htaccess` ที่มีให้แล้ว (เปิด `mod_rewrite`)

ให้ web server เขียนได้ที่ `runtime/`, `web/assets/`, `web/uploads/`

## โครงสร้างโค้ด

```
config/venues/*.php        ผังที่นั่งละเอียดจากแบบจริง (เช่น hall1500.php)
config/zones.php           โซนราคา VIP/A/B/C
components/BookingService  ล็อกที่นั่ง / หมดเวลา / ยืนยันชำระ / เช็คอิน
components/LineLogin       LINE Login (OAuth2 + OIDC)
components/LineMessaging   ส่งข้อความ LINE
components/PromptPay       สร้าง payload QR PromptPay (EMVCo + CRC16)
components/OmiseGateway    Omise charges + ตรวจสถานะ
models/Venue               สถานที่ + สร้างที่นั่งจากผัง
models/Hall                แปลงผัง (grid / measured) เป็นพิกัดที่นั่ง
web/js/seatmap.js          ผังที่นั่ง SVG ฝั่งผู้ใช้และแอดมิน
migrations/                โครงสร้างฐานข้อมูล
```

### ตารางหลัก

- `venue` สถานที่และรูปแบบผัง
- `seat` ที่นั่งทางกายภาพของแต่ละสถานที่ พร้อมพิกัดสำหรับวาด
- `event` / `event_zone_price` การแสดง (ระบุ `venue_id`) และราคาตามโซน
- `event_seat` สถานะที่นั่งของแต่ละการแสดง: `available` / `held` / `sold` / `blocked`
- `booking` / `booking_seat` รายการจอง (`pending` → `verifying` → `paid`)
- `payment` การชำระเงิน (สลิปหรือ Omise)

### การกันจองซ้ำ

```sql
UPDATE event_seat SET status='held', booking_id=? WHERE event_id=? AND seat_id IN (...) AND status='available'
```

ถ้าจำนวนแถวที่ถูกอัปเดตไม่เท่ากับจำนวนที่นั่งที่เลือก แสดงว่ามีคนจองตัดหน้า ระบบจะ rollback ทั้งรายการ
InnoDB ล็อกแถวระหว่าง UPDATE จึงไม่มีสองคนได้ที่นั่งเดียวกัน

## ผู้พัฒนา

- **มานิต จิตต์ประไพย** — <https://manitgithub.github.io/>
