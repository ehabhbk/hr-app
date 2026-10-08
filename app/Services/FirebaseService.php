<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * تكامل Firebase بدون حزم خارجية (البيئة بلا إنترنت للتثبيت):
 * - إشعارات FCM عبر HTTP v1 (OAuth2 RS256 بتوقيع openssl).
 * - مرآة Firestore عبر REST (كل حقول MySQL) للمزامنة مع الجوال.
 */
class FirebaseService
{
    protected $projectId;
    protected $clientEmail;
    protected $privateKey;
    protected $http;

    public function __construct()
    {
        $this->projectId = config('firebase.project_id');
        $this->clientEmail = config('firebase.client_email');
        $this->privateKey = $this->resolvePrivateKey();
        $this->http = new Client(['timeout' => 15]);
    }

    public static function enabled(): bool
    {
        return (bool) config('firebase.enabled')
            && (new static)->credentialsReady();
    }

    protected function credentialsReady(): bool
    {
        return !empty($this->projectId) && !empty($this->clientEmail) && !empty($this->privateKey);
    }

    protected function resolvePrivateKey(): string
    {
        $key = (string) config('firebase.private_key');
        if ($key !== '') {
            return str_replace('\n', "\n", $key);
        }
        $file = (string) config('firebase.credentials_file');
        if ($file !== '' && is_file($file)) {
            $json = json_decode((string) file_get_contents($file), true) ?? [];
            if (!empty($json['project_id']) && empty($this->projectId)) {
                $this->projectId = $json['project_id'];
            }
            if (!empty($json['client_email']) && empty($this->clientEmail)) {
                $this->clientEmail = $json['client_email'];
            }
            return str_replace('\n', "\n", (string) ($json['private_key'] ?? ''));
        }
        return '';
    }

    /* ---------------- OAuth2 ---------------- */

    protected function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function accessToken(): ?string
    {
        if (!$this->credentialsReady()) {
            return null;
        }
        return Cache::remember('firebase_access_token', 50 * 60, function () {
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss' => $this->clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging https://www.googleapis.com/auth/datastore',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $signing = $header . '.' . $claims;
            if (!openssl_sign($signing, $sig, $this->privateKey, OPENSSL_ALGO_SHA256)) {
                Log::error('Firebase JWT sign failed');
                return null;
            }
            try {
                $res = $this->http->post('https://oauth2.googleapis.com/token', [
                    'form_params' => [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $signing . '.' . $this->base64Url($sig),
                    ],
                ]);
                $body = json_decode((string) $res->getBody(), true) ?? [];
                return $body['access_token'] ?? null;
            } catch (\Exception $e) {
                Log::error('Firebase OAuth error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /* ---------------- FCM ---------------- */

    /**
     * إرسال إشعار فوري لرمز/رموز FCM.
     */
    public function sendToTokens($tokens, string $title, string $body, array $data = []): array
    {
        $tokens = (array) $tokens;
        $result = ['sent' => 0, 'failed' => 0];
        if (!static::enabled() || empty($tokens)) {
            return $result;
        }
        $token = $this->accessToken();
        if (!$token) {
            return $result;
        }
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        foreach (array_unique($tokens) as $t) {
            try {
                $this->http->post($url, [
                    'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                    'json' => [
                        'message' => [
                            'token' => $t,
                            'notification' => ['title' => $title, 'body' => $body],
                            'data' => array_map('strval', $data),
                            'android' => ['priority' => 'high'],
                        ],
                    ],
                ]);
                $result['sent']++;
            } catch (\Exception $e) {
                Log::warning('FCM send failed: ' . $e->getMessage());
                $result['failed']++;
            }
        }
        return $result;
    }

    /**
     * إرسال لموظف عبر أجهزته المسجلة.
     */
    public function sendToEmployee($employee, string $title, string $body, array $data = []): array
    {
        if (!$employee) {
            return ['sent' => 0, 'failed' => 0];
        }
        $tokens = \App\Models\MobileDevice::where('employee_id', $employee->id)
            ->pluck('fcm_token')->all();
        if ($employee->user_id) {
            $tokens = array_merge($tokens, \App\Models\MobileDevice::where('user_id', $employee->user_id)
                ->pluck('fcm_token')->all());
        }
        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * إشعار موظف: صندوق وارد + دفع FCM فوري. لا يرمي استثناءً أبداً.
     */
    public static function notifyEmployee($employee, string $type, string $title, string $message, array $data = []): void
    {
        if (!$employee) {
            return;
        }
        try {
            $actorId = null;
            try {
                $actorId = auth()->id();
            } catch (\Exception $e) {
            }
            if (!$actorId) {
                $actorId = \App\Models\User::orderBy('id')->value('id');
            }
            if ($actorId) {
                \App\Models\Notification::send($actorId, $type, $title, $message, $data, $employee->user_id);
            }
        } catch (\Exception $e) {
            Log::warning('notifyEmployee inbox failed: ' . $e->getMessage());
        }
        try {
            (new static)->sendToEmployee($employee, $title, $message, $data);
        } catch (\Exception $e) {
            Log::warning('notifyEmployee push failed: ' . $e->getMessage());
        }
    }

    /**
     * إرسال لمستخدم (مدير) عبر أجهزته.
     */
    public function sendToUser($userId, string $title, string $body, array $data = []): array
    {
        $tokens = \App\Models\MobileDevice::where('user_id', $userId)->pluck('fcm_token')->all();
        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /* ---------------- Firestore mirror ---------------- */

    protected function firestoreValue($value): array
    {
        if (is_null($value)) {
            return ['nullValue' => null];
        }
        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }
        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }
        if (is_float($value)) {
            return ['doubleValue' => $value];
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return ['arrayValue' => ['values' => array_map([$this, 'firestoreValue'], $value)]];
            }
            $fields = [];
            foreach ($value as $k => $v) {
                $fields[(string) $k] = $this->firestoreValue($v);
            }
            return ['mapValue' => ['fields' => $fields]];
        }
        return ['stringValue' => (string) $value];
    }

    protected function docPath(string $collection, $docId): string
    {
        $id = rawurlencode((string) $docId);
        return "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/{$collection}/{$id}";
    }

    /**
     * نسخ سجل MySQL إلى Firestore بكل حقوله.
     */
    public function firestoreUpsert(string $collection, $docId, array $attributes): bool
    {
        if (!static::enabled()) {
            return false;
        }
        $token = $this->accessToken();
        if (!$token) {
            return false;
        }
        $fields = [];
        foreach ($attributes as $k => $v) {
            $fields[(string) $k] = $this->firestoreValue($v);
        }
        try {
            $this->http->request('PATCH', $this->docPath($collection, $docId), [
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'json' => ['fields' => $fields],
            ]);
            return true;
        } catch (\Exception $e) {
            Log::warning('Firestore upsert failed: ' . $e->getMessage());
            return false;
        }
    }

    public function firestoreDelete(string $collection, $docId): bool
    {
        if (!static::enabled()) {
            return false;
        }
        $token = $this->accessToken();
        if (!$token) {
            return false;
        }
        try {
            $this->http->delete($this->docPath($collection, $docId), [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);
            return true;
        } catch (\Exception $e) {
            Log::warning('Firestore delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * مزامنة موديل كامل (كل الأعمدة) إلى مجموعته في Firestore.
     */
    public function syncModel($model): bool
    {
        $map = [
            'employees' => 'employees',
            'attendance_records' => 'attendance_records',
            'advances_requests' => 'advance_requests',
            'leaves' => 'leaves',
            'warnings' => 'warnings',
            'notifications' => 'notifications',
            'work_shifts' => 'work_shifts',
            'departments' => 'departments',
        ];
        $table = $model->getTable();
        if (!isset($map[$table])) {
            return false;
        }
        $attrs = $model->getAttributes();
        // حقول مساعدة للعرض في الجوال
        if ($table === 'attendance_records' && $model->relationLoaded('employee')) {
            $attrs['employee_name'] = $model->employee?->name;
        }
        if (in_array($table, ['advances_requests', 'leaves', 'warnings']) && method_exists($model, 'employee')) {
            try {
                $attrs['employee_name'] = $model->employee?->name;
            } catch (\Exception $e) {
            }
        }
        return $this->firestoreUpsert($map[$table], $model->getKey(), $attrs);
    }
}
