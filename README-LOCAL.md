# قيصر الشرق — Local Development Setup

## ما يحتاجه المشروع للتشغيل

| المتطلب | الإصدار | الغرض |
|---------|---------|-------|
| **PHP** | 8.x | يخدم ملفات الـ web + API |
| **Node.js** | 18+ | WebSocket server |
| **ws** package | مثبت تلقائياً | مكتبة WebSocket لـ Node |

---

## طريقة التشغيل السريع

**شغّل الملف:**
```
start-servers.bat
```

ده هيفتح 3 نوافذ:
1. PHP server على port **8080** (web.elkaisar.com)
2. PHP server على port **8090** (app.elkaisar.com API)
3. Node.js server على port **8081** (ws.elkaisar.com WebSocket)

ثم يفتح المتصفح على: **http://localhost:8080/local-server.php**

---

## البنية الكاملة

```
web.elkaisar.com (2)/
├── web.elkaisar.com/          ← الـ frontend (HTML + CSS + JS)
│   ├── server.php             ← نسخة production (تشير لـ elkaisar.com)
│   ├── local-server.php       ← نسخة local dev (تشير لـ localhost)
│   ├── router.php             ← PHP router للـ built-in server
│   ├── css-3.4.113/           ← CSS files
│   ├── js-3.4.113/            ← JS files (obfuscated.js هو الكود الرئيسي)
│   └── images/                ← الصور والأصول
│
├── app.elkaisar.com/          ← الـ API server (JSON responses)
│   ├── router.php             ← PHP router
│   ├── api/                   ← API endpoints (كل .html هو JSON response)
│   │   ├── APlayer/           ← Player APIs
│   │   ├── ACity/             ← City APIs
│   │   ├── AWorld/            ← World map APIs
│   │   └── ...               ← باقي الـ endpoints
│   ├── home/HLogIn/           ← Login endpoint
│   └── js-0.0.24/json/        ← Static JSON data files
│
├── ws.elkaisar.com8081/       ← WebSocket API data
│   └── api/                   ← WS API endpoints
│       ├── ATeam/
│       ├── AWorld/
│       └── ...
│
├── ws-server.js               ← Node.js WebSocket + HTTP server
├── start-servers.bat          ← تشغيل كل الـ servers دفعة واحدة
└── node_modules/ws/           ← مكتبة WebSocket
```

---

## URLs المهمة

| URL | الوظيفة |
|-----|---------|
| `http://localhost:8080/local-server.php` | 🎮 اللعبة (للـ local dev) |
| `http://localhost:8090/api/APlayer/getPlayerState` | Player state API |
| `http://localhost:8090/home/HLogIn/playerEnterServerWeb` | Login API |
| `http://localhost:8081/api/AWorld/getWorldCity` | World map data |
| `ws://localhost:8081` | WebSocket connection |

---

## الفرق بين server.php و local-server.php

| | `server.php` | `local-server.php` |
|--|--|--|
| BASE_URL | `https://web.elkaisar.com` | `http://localhost:8080` |
| API_URL | `https://app.elkaisar.com` | `http://localhost:8090` |
| WS_HOST | `ws.elkaisar.com` | `localhost` |
| WS_PORT | `8081` | `8081` |

---

## ملاحظات مهمة

> **WebSocket**: الـ JS يتصل بـ `ws://localhost:8081` في الـ local mode.
> الـ WS server يقبل الاتصالات ويرسل acknowledgment لكل message.

> **API Routing**: كل ملف `.html` في مجلد `api/` يحتوي على JSON response ثابت.
> مثلاً: `GET /api/APlayer/getPlayerState` → يقرأ `api/APlayer/getPlayerState.html`

> **CORS**: الـ router.php بتاع الـ app server بيضيف `Access-Control-Allow-Origin: *`
> علشان الـ browser يقدر يعمل requests من port 8080 لـ port 8090.
