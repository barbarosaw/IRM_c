# Timeworks API Documentation

## API Bağlantı Bilgileri

### Base URL
```
https://api.timeworks.abroadworks.com/api/v1/
```

### Organization ID
```
2
```

### Authentication
API, JWT (JSON Web Token) tabanlı kimlik doğrulama kullanır.

#### Refresh Token (Uzun Süreli)
```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJiYXJiYXJvc0BhYnJvYWR3b3Jrcy5jb20iLCJleHAiOjE3NjY0NDUyMTEsInR5cGUiOiJyZWZyZXNoIn0.DyAnvsDtxWMBu4h-E-HIJKxFI0pT1eytHppXwkjNhFk
```

**Token Expiry:** 1766445211 (Unix timestamp) = ~2026-01-22

---

## Bağlantının Aktif Tutulması

### Access Token Yenileme
Access token kısa ömürlüdür ve her API çağrısı öncesinde refresh token ile yenilenmelidir.

```php
function getAccessToken() {
    $refreshTokenUrl = 'https://api.timeworks.abroadworks.com/api/v1/refresh-token';
    $refreshToken = 'YOUR_REFRESH_TOKEN';

    $ch = curl_init($refreshTokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $refreshToken
        ],
        CURLOPT_POSTFIELDS => json_encode(['refresh_token' => $refreshToken]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    return $tokenData['access_token'] ?? null;
}
```

### Ortak HTTP Headers
Her API isteğinde aşağıdaki header'lar kullanılmalıdır:
```
Content-Type: application/json
Authorization: Bearer {access_token}
```

### CURL Ayarları
```php
CURLOPT_SSL_VERIFYPEER => false,
CURLOPT_SSL_VERIFYHOST => false,
CURLOPT_TIMEOUT => 30  // veya 60 büyük istekler için
```

---

## Endpoints

### 1. Refresh Token (Access Token Alma)

**Endpoint:** `POST /refresh-token`

**Description:** Refresh token kullanarak yeni access token alır.

**Request:**
```http
POST https://api.timeworks.abroadworks.com/api/v1/refresh-token
Content-Type: application/json
Authorization: Bearer {refresh_token}

{
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response (200 OK):**
```json
{
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJiYXJiYXJvc0BhYnJvYWR3b3Jrcy5jb20iLCJleHAiOjE3MzQ0NTI4MDAsInR5cGUiOiJhY2Nlc3MifQ.xxxxx",
    "token_type": "bearer",
    "expires_in": 3600
}
```

---

### 2. User List (Kullanıcı Listesi)

**Endpoint:** `GET /user/list`

**Description:** Organizasyondaki tüm kullanıcıları listeler.

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| organization_id | integer | Yes | Organizasyon ID |
| limit | integer | No | Maksimum kayıt sayısı (default: 100) |
| offset | integer | No | Sayfalama için başlangıç noktası |

**Request:**
```http
GET https://api.timeworks.abroadworks.com/api/v1/user/list?organization_id=2&limit=700&offset=0
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Response (200 OK):**
```json
{
    "items": [
        {
            "user_id": "abc123-def456-ghi789",
            "full_name": "John Doe",
            "email": "john.doe@example.com",
            "timezone": "Europe/Istanbul",
            "status": "active",
            "created_at": "2024-01-15T10:30:00Z",
            "last_login_local": "2025-12-15T09:00:00",
            "roles": [
                {
                    "id": "role-001",
                    "name": "Developer"
                },
                {
                    "id": "role-002",
                    "name": "Team Lead"
                }
            ]
        },
        {
            "user_id": "xyz789-uvw456-rst123",
            "full_name": "Jane Smith",
            "email": "jane.smith@example.com",
            "timezone": "America/New_York",
            "status": "active",
            "created_at": "2024-02-20T14:15:00Z",
            "last_login_local": null,
            "roles": [
                {
                    "id": "role-003",
                    "name": "Designer"
                }
            ]
        }
    ],
    "total": 245,
    "limit": 700,
    "offset": 0
}
```

---

### 3. Projects List (Proje Listesi)

**Endpoint:** `GET /projects/{organization_id}`

**Description:** Organizasyondaki tüm projeleri ve üyelerini listeler.

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| organization_id | path | Yes | Organizasyon ID |
| limit | integer | No | Maksimum kayıt sayısı |
| offset | integer | No | Sayfalama için başlangıç noktası |

**Request:**
```http
GET https://api.timeworks.abroadworks.com/api/v1/projects/2?limit=600&offset=0
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Response (200 OK):**
```json
{
    "items": [
        {
            "id": "proj-001",
            "name": "Mobile App Development",
            "description": "iOS and Android app for client X",
            "status": "active",
            "progress": 65,
            "is_billable": true,
            "task_count": 42,
            "created_at": "2024-03-01T08:00:00Z",
            "updated_at": "2025-12-10T15:30:00Z",
            "users": [
                {
                    "user_id": "abc123-def456-ghi789",
                    "full_name": "John Doe",
                    "email": "john.doe@example.com"
                },
                {
                    "user_id": "xyz789-uvw456-rst123",
                    "full_name": "Jane Smith",
                    "email": "jane.smith@example.com"
                }
            ]
        },
        {
            "id": "proj-002",
            "name": "Website Redesign",
            "description": "Corporate website overhaul",
            "status": "active",
            "progress": 30,
            "is_billable": true,
            "task_count": 18,
            "created_at": "2024-06-15T10:00:00Z",
            "updated_at": "2025-12-08T11:45:00Z",
            "users": []
        }
    ],
    "total": 87,
    "limit": 600,
    "offset": 0
}
```

---

### 4. Project Members (Proje Üyeleri)

**Endpoint:** `GET /project-members/{organization_id}/{project_id}`

**Description:** Belirli bir projenin üyelerini detaylı listeler.

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| organization_id | path | Yes | Organizasyon ID |
| project_id | path | Yes | Proje ID |
| limit | integer | No | Maksimum kayıt sayısı |
| offset | integer | No | Sayfalama için başlangıç noktası |

**Request:**
```http
GET https://api.timeworks.abroadworks.com/api/v1/project-members/2/proj-001?limit=150&offset=0
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Response (200 OK):**
```json
{
    "items": [
        {
            "user_id": "abc123-def456-ghi789",
            "full_name": "John Doe",
            "email": "john.doe@example.com",
            "role": "Developer"
        },
        {
            "user_id": "xyz789-uvw456-rst123",
            "full_name": "Jane Smith",
            "email": "jane.smith@example.com",
            "role": "Designer"
        }
    ],
    "total": 12,
    "limit": 150,
    "offset": 0
}
```

---

### 5. User Time Sheet Report (Kullanıcı Zaman Raporu)

**Endpoint:** `GET /reports/user-time-sheet/{organization_id}/{user_id}`

**Description:** Belirli bir kullanıcının belirtilen tarih aralığındaki çalışma süresini raporlar.

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| organization_id | path | Yes | Organizasyon ID |
| user_id | path | Yes | Kullanıcı ID |
| start_date | string | Yes | Başlangıç tarihi (YYYY-MM-DD) |
| end_date | string | Yes | Bitiş tarihi (YYYY-MM-DD) |
| timezone_str | string | No | Timezone (default: UTC) |
| offset | integer | No | Sayfalama için başlangıç noktası |
| limit | integer | No | Maksimum gün sayısı |

**Request (Tek Gün):**
```http
GET https://api.timeworks.abroadworks.com/api/v1/reports/user-time-sheet/2/abc123-def456-ghi789?start_date=2025-12-16&end_date=2025-12-16&timezone_str=Europe/Istanbul&offset=0&limit=1
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Request (Bir Ay):**
```http
GET https://api.timeworks.abroadworks.com/api/v1/reports/user-time-sheet/2/abc123-def456-ghi789?start_date=2025-12-01&end_date=2025-12-31&timezone_str=Europe/Istanbul&offset=0&limit=31
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Response (200 OK):**
```json
{
    "report": {
        "2025-12-16": {
            "total_user_duration": "07:45:30",
            "total_user_duration_seconds": 27930,
            "projects": [
                {
                    "project_id": "proj-001",
                    "project_name": "Mobile App Development",
                    "duration": "05:30:00",
                    "duration_seconds": 19800
                },
                {
                    "project_id": "proj-002",
                    "project_name": "Website Redesign",
                    "duration": "02:15:30",
                    "duration_seconds": 8130
                }
            ]
        },
        "2025-12-15": {
            "total_user_duration": "08:00:00",
            "total_user_duration_seconds": 28800,
            "projects": [
                {
                    "project_id": "proj-001",
                    "project_name": "Mobile App Development",
                    "duration": "08:00:00",
                    "duration_seconds": 28800
                }
            ]
        }
    },
    "user_id": "abc123-def456-ghi789",
    "start_date": "2025-12-01",
    "end_date": "2025-12-31"
}
```

**Response (Aktivite Yok):**
```json
{
    "report": {},
    "user_id": "xyz789-uvw456-rst123",
    "start_date": "2025-12-16",
    "end_date": "2025-12-16"
}
```

---

## Hata Kodları

| HTTP Code | Description |
|-----------|-------------|
| 200 | Başarılı istek |
| 400 | Geçersiz istek parametreleri |
| 401 | Kimlik doğrulama hatası (token geçersiz veya süresi dolmuş) |
| 403 | Yetkisiz erişim |
| 404 | Kaynak bulunamadı |
| 429 | Rate limit aşıldı |
| 500 | Sunucu hatası |

---

## Kullanım Örnekleri (PHP)

### Tam Senkronizasyon Akışı

```php
<?php
// 1. Access token al
$token = getAccessToken();
if (!$token) {
    die('Token alınamadı');
}

// 2. Kullanıcıları çek
$usersUrl = 'https://api.timeworks.abroadworks.com/api/v1/user/list?organization_id=2&limit=700&offset=0';
$users = makeApiRequest($usersUrl, $token);

// 3. Projeleri çek
$projectsUrl = 'https://api.timeworks.abroadworks.com/api/v1/projects/2?limit=600&offset=0';
$projects = makeApiRequest($projectsUrl, $token);

// 4. Kullanıcı raporu çek
$userId = 'abc123-def456-ghi789';
$reportUrl = 'https://api.timeworks.abroadworks.com/api/v1/reports/user-time-sheet/2/' . $userId . '?start_date=2025-12-16&end_date=2025-12-16&timezone_str=Europe/Istanbul';
$report = makeApiRequest($reportUrl, $token);

function makeApiRequest($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    return json_decode($response, true);
}
```

---

## Yerel Dosyalar

Bu projede kullanılan PHP dosyaları:

| Dosya | Açıklama |
|-------|----------|
| `data.php` | Ana API fonksiyonları ve token yönetimi |
| `sync.php` | Kullanıcı senkronizasyonu |
| `sync_projects.php` | Proje senkronizasyonu |
| `check_daily.php` | Günlük aktivite kontrolü (tek gün) |
| `check_login.php` | Aylık aktivite kontrolü |
| `daily_report.php` | Günlük rapor arayüzü |

---

## Notlar

1. **Rate Limiting:** API'ye çok sık istek atmaktan kaçının. İstekler arasında en az 200ms bekleyin.

2. **Token Yenileme:** Access token süresi dolduğunda (401 hatası) refresh token ile yenileyin.

3. **Timezone:** Rapor isteklerinde timezone parametresi önemlidir. Türkiye için `Europe/Istanbul` kullanın.

4. **Pagination:** Büyük veri setleri için `limit` ve `offset` parametrelerini kullanın.

5. **SSL:** Test ortamında SSL doğrulama kapatılmıştır. Production'da açılması önerilir.
