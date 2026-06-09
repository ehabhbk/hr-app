<?php

namespace App\Services;

use Jmrashed\Zkteco\Lib\ZKTeco;
use App\Models\AttendanceDevice;
use Illuminate\Support\Facades\Log;

class ZKTecoService
{
    const CMD_START_ENROLL = 97;

    /**
     * Register user on device and attempt to start fingerprint enrollment.
     * Returns immediately — frontend should call checkEnrollment after user interaction.
     */
    public function enrollFingerprint(AttendanceDevice $device, string $userId, string $name, int $fingerId): array
    {
        $zk = $this->connect($device);
        if (!$zk) {
            return ['success' => false, 'message' => 'فشل الاتصال بالجهاز'];
        }

        try {
            $zk->disableDevice();
            usleep(300000);

            // Check existing templates before modifying
            $existingTemplates = $zk->getFingerprint((int)$userId);
            if (!empty($existingTemplates) && isset($existingTemplates[$fingerId])) {
                $zk->enableDevice();
                $zk->disconnect();
                return [
                    'success' => true,
                    'message' => 'البصمة مسجلة مسبقاً على الجهاز.',
                    'enrolled_on_device' => true,
                    'manual_required' => false,
                ];
            }

            // Remove existing fingerprint for this finger first
            $zk->removeFingerprint((int)$userId, [$fingerId]);

            $zk->setUser($userId, $name, '', 0);
            usleep(300000);

            // Try to start enrollment mode on the device
            $enrollStarted = $this->startEnroll($zk, (int)$userId, $fingerId);

            $zk->enableDevice();
            $zk->disconnect();

            if ($enrollStarted) {
                return [
                    'success' => true,
                    'message' => 'تم إرسال أمر التسجيل للجهاز. ضع إصبعك على الجهاز الآن.',
                    'enrolled_on_device' => false,
                    'manual_required' => false,
                ];
            }

            return [
                'success' => true,
                'message' => 'تم تجهيز المستخدم على الجهاز. اذهب إلى الجهاز > Menu > إدارة المستخدمين > تسجيل البصمة > اختر الرقم ' . $userId,
                'enrolled_on_device' => false,
                'manual_required' => true,
            ];
        } catch (\Exception $e) {
            Log::error('ZKTecoService@enrollFingerprint: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطأ في الاتصال بالجهاز: ' . $e->getMessage(),
                'enrolled_on_device' => false,
                'manual_required' => true,
            ];
        }
    }

    /**
     * Register user on device and start face enrollment.
     */
    public function enrollFace(AttendanceDevice $device, string $userId, string $name): array
    {
        $zk = $this->connect($device);
        if (!$zk) {
            return ['success' => false, 'message' => 'فشل الاتصال بالجهاز'];
        }

        try {
            $zk->disableDevice();
            usleep(300000);

            // Check existing templates before modifying
            $existingTemplates = $zk->getFingerprint((int)$userId);
            if (!empty($existingTemplates)) {
                $zk->enableDevice();
                $zk->disconnect();
                return [
                    'success' => true,
                    'message' => 'الوجه مسجل مسبقاً على الجهاز.',
                    'enrolled_on_device' => true,
                    'manual_required' => false,
                ];
            }

            $zk->setUser($userId, $name, '', 0);
            usleep(300000);

            // Enable face function on device
            $zk->faceFunctionOn();

            // Start enrollment (finger_id 15 = face)
            $enrollStarted = $this->startEnroll($zk, (int)$userId, 15);

            $zk->enableDevice();
            $zk->disconnect();

            if ($enrollStarted) {
                return [
                    'success' => true,
                    'message' => 'تم إرسال أمر تسجيل الوجه للجهاز. يرجى التوجه إلى الجهاز.',
                    'enrolled_on_device' => false,
                    'manual_required' => false,
                ];
            }

            return [
                'success' => true,
                'message' => 'تم تجهيز المستخدم على الجهاز. اتبع التعليمات على شاشة الجهاز لإضافة الوجه.',
                'enrolled_on_device' => false,
                'manual_required' => true,
            ];
        } catch (\Exception $e) {
            Log::error('ZKTecoService@enrollFace: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطأ في الاتصال بالجهاز: ' . $e->getMessage(),
                'enrolled_on_device' => false,
                'manual_required' => true,
            ];
        }
    }

    /**
     * Check if a fingerprint/face was actually enrolled on the device.
     * Uses a fresh connection to query the device.
     */
    public function checkEnrollment(AttendanceDevice $device, string $userId, ?int $fingerId = null): array
    {
        $zk = $this->connect($device);
        if (!$zk) {
            return ['enrolled' => false, 'message' => 'فشل الاتصال بالجهاز'];
        }

        try {
            $zk->disableDevice();
            usleep(300000);

            // Query fingerprint templates for this user
            $templates = $zk->getFingerprint((int)$userId);

            $zk->enableDevice();
            $zk->disconnect();

            if (!empty($templates)) {
                if ($fingerId !== null && isset($templates[$fingerId])) {
                    return [
                        'enrolled' => true,
                        'message' => 'تم تسجيل البصمة بنجاح على الجهاز.',
                        'template_size' => strlen($templates[$fingerId]),
                    ];
                }
                if ($fingerId === null) {
                    return [
                        'enrolled' => true,
                        'message' => 'تم تسجيل بصمة واحدة على الأقل.',
                        'template_count' => count($templates),
                    ];
                }
            }

            return [
                'enrolled' => false,
                'message' => 'لم يتم العثور على بصمة مسجلة. تأكد من وضع الإصبع على الجهاز.',
            ];
        } catch (\Exception $e) {
            Log::error('ZKTecoService@checkEnrollment: ' . $e->getMessage());
            return [
                'enrolled' => false,
                'message' => 'خطأ في التحقق: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Register user on device only (manual mode — current behavior).
     */
    public function registerUserOnly(AttendanceDevice $device, string $userId, string $name): array
    {
        $zk = $this->connect($device);
        if (!$zk) {
            return ['success' => false, 'message' => 'فشل الاتصال بالجهاز'];
        }

        try {
            $zk->disableDevice();
            $zk->setUser($userId, $name, '', 0);
            $zk->enableDevice();
            $zk->disconnect();

            return ['success' => true, 'message' => 'تم تسجيل الموظف على الجهاز'];
        } catch (\Exception $e) {
            Log::error('ZKTecoService@registerUserOnly: ' . $e->getMessage());
            return ['success' => false, 'message' => 'خطأ: ' . $e->getMessage()];
        }
    }

    private function connect(AttendanceDevice $device): ?ZKTeco
    {
        if (!$device->enabled) {
            return null;
        }

        $zk = new ZKTeco($device->host, (int)($device->port ?? 4370));
        if (!$zk->connect()) {
            return null;
        }

        return $zk;
    }

    /**
     * Try to start fingerprint enrollment on device using multiple approaches.
     * ZK devices support different commands depending on firmware version.
     */
    private function startEnroll(ZKTeco $zk, int $uid, int $fingerId): bool
    {
        // Approach 1: Standard StartEnroll (command 0x61 = 97)
        // Parameters: uid(2 bytes LE) + fingerId(1 byte) + flag(1 byte)
        $byte1 = chr($uid % 256);
        $byte2 = chr((int)($uid >> 8));
        $cmdString1 = $byte1 . $byte2 . chr($fingerId) . chr(0);
        if ($zk->_command(self::CMD_START_ENROLL, $cmdString1) !== false) {
            return true;
        }

        // Approach 2: StartEnrollEx (command 0x62 = 98)
        $cmdString2 = $byte1 . $byte2 . chr($fingerId) . chr(0);
        if ($zk->_command(98, $cmdString2) !== false) {
            return true;
        }

        // Approach 3: uid as 4 bytes LE + finger_id
        $cmdString3 = pack('V', $uid) . chr($fingerId);
        if ($zk->_command(self::CMD_START_ENROLL, $cmdString3) !== false) {
            return true;
        }

        // Approach 4: Just uid (2 bytes) without finger_id
        if ($zk->_command(self::CMD_START_ENROLL, $byte1 . $byte2) !== false) {
            return true;
        }

        return false;
    }
}
