<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkShift extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'work_shifts';

    protected $fillable = ['name', 'start_time', 'end_time', 'is_overnight', 'week_days', 'weekend_days', 'daily_hours', 'notes', 'active'];

    protected $casts = ['week_days' => 'array', 'weekend_days' => 'array', 'active' => 'boolean', 'is_overnight' => 'boolean'];

    public function assignments()
    {
        return $this->hasMany(ShiftAssignment::class, 'work_shift_id');
    }

    public function permanentAssignments()
    {
        return $this->hasMany(ShiftAssignment::class, 'work_shift_id')->whereNull('date');
    }

    /**
     * وردية بصمة حضور لموظف بلا وردية مسندة (لأقرب وردية):
     * - إن كانت بداية وردية بعد البصمة بمدة قصيرة (<= 90 دقيقة) فهي ورديته
     *   (حضور مبكر: بصمة 7:00 تتبع الصباحية 8:00 لا الليلية).
     * - وإلا فأحدث وردية بدأت قبل البصمة (بصمة الظهر تتبع الصباحية
     *   الجارية لا المسائية القادمة — فلا تُسمَّى "مبكر" ظلماً).
     * عند التساوي تُفضَّل الأقصر مدة ثم الأقدم.
     */
    public static function matchForPunch(Carbon $punch, string $tz = 'Africa/Khartoum')
    {
        return static::matchBoundary($punch, $tz, 'start_time', 90);
    }

    /**
     * لحظة نهاية الوردية الأقرب لبصمة انصراف (بحثاً في أمس واليوم والغد):
     * - نهاية قادمة خلال 90 دقيقة أولاً (خارج قبل نهايته بقليل).
     * - وإلا أحدث نهاية مرت (تجاوزها = تأخير/عمل إضافي).
     * تُرجع Carbon كاملة بالتاريخ الصحيح (نهاية أمس لانصراف 00:30 مثلاً).
     */
    public static function matchEndDateTime(Carbon $punch, string $tz = 'Africa/Khartoum', int $window = 90)
    {
        $shifts = static::where('active', true)->get();
        if ($shifts->isEmpty()) {
            return null;
        }

        $base = $punch->copy()->setTimezone($tz);
        $day0 = $base->copy()->startOfDay();
        $toMin = fn($hms) => ((int) substr($hms, 0, 2)) * 60 + ((int) substr($hms, 3, 2));
        $tMin = $toMin($base->format('H:i:s'));

        $better = fn($dNew, $lNew, $iNew, $dOld, $lOld, $iOld) =>
            $dNew < $dOld || ($dNew === $dOld && ($lNew < $lOld || ($lNew === $lOld && $iNew < $iOld)));
        $betterPast = fn($dNew, $lNew, $iNew, $dOld, $lOld, $iOld) =>
            $dNew > $dOld || ($dNew === $dOld && ($lNew < $lOld || ($lNew === $lOld && $iNew < $iOld)));

        $upD = PHP_INT_MAX;
        $upOff = 0;
        $upEnd = 0;
        $upLen = PHP_INT_MAX;
        $upId = PHP_INT_MAX;

        $pastD = -PHP_INT_MAX;
        $pastOff = 0;
        $pastEnd = 0;
        $pastLen = PHP_INT_MAX;
        $pastId = PHP_INT_MAX;

        foreach ($shifts as $s) {
            if (!$s->start_time || !$s->end_time) {
                continue;
            }
            $eMin = $toMin(substr($s->end_time, 0, 5) . ':00');
            $len = static::spanMinutes($s);

            foreach ([-1, 0, 1] as $off) {
                $d = $off * 24 * 60 + $eMin - $tMin;
                if ($d >= 0) {
                    if ($better($d, $len, $s->id, $upD, $upLen, $upId)) {
                        $upD = $d;
                        $upOff = $off;
                        $upEnd = $eMin;
                        $upLen = $len;
                        $upId = $s->id;
                    }
                } elseif ($betterPast($d, $len, $s->id, $pastD, $pastLen, $pastId)) {
                    $pastD = $d;
                    $pastOff = $off;
                    $pastEnd = $eMin;
                    $pastLen = $len;
                    $pastId = $s->id;
                }
            }
        }

        if ($upD <= $window) {
            return $day0->copy()->addDays($upOff)->setTime((int) floor($upEnd / 60), $upEnd % 60);
        }

        if ($pastD > -PHP_INT_MAX) {
            return $day0->copy()->addDays($pastOff)->setTime((int) floor($pastEnd / 60), $pastEnd % 60);
        }

        return null;
    }

    /**
     * مدة الوردية بالدقائق (24 ساعة للشاملة).
     */
    private static function spanMinutes($s): int
    {
        $toMin = fn($hms) => ((int) substr($hms, 0, 2)) * 60 + ((int) substr($hms, 3, 2));
        $stMin = $toMin(substr($s->start_time, 0, 5) . ':00');
        $enMin = $toMin(substr($s->end_time, 0, 5) . ':00');
        $overnight = (bool) $s->is_overnight || $s->end_time < $s->start_time;
        $len = $overnight ? (24 * 60 - $stMin + $enMin) : ($enMin - $stMin);
        return $len > 0 ? $len : 24 * 60;
    }

    /**
     * مطابقة عامة لحدّ زمني (بداية أو نهاية): قادم قريب أولاً ثم الأحدث ماضياً.
     */
    private static function matchBoundary(Carbon $punch, string $tz, string $field, int $window)
    {
        $shifts = static::where('active', true)->get();
        if ($shifts->isEmpty()) {
            return null;
        }

        $t = $punch->copy()->setTimezone($tz)->format('H:i:s');
        $toMin = fn($hms) => ((int) substr($hms, 0, 2)) * 60 + ((int) substr($hms, 3, 2));
        $tMin = $toMin($t);

        // أفضل مرشح حسب (فرق أصغر، مدة أقصر، id أصغر)
        $better = fn($dNew, $lNew, $iNew, $dOld, $lOld, $iOld) =>
            $dNew < $dOld || ($dNew === $dOld && ($lNew < $lOld || ($lNew === $lOld && $iNew < $iOld)));

        $upcoming = null;
        $upcomingIn = PHP_INT_MAX;
        $upcomingLen = PHP_INT_MAX;
        $upcomingId = PHP_INT_MAX;

        $past = null;
        $pastAgo = PHP_INT_MAX;
        $pastLen = PHP_INT_MAX;
        $pastId = PHP_INT_MAX;

        foreach ($shifts as $s) {
            if (!$s->start_time || !$s->end_time) {
                continue;
            }
            $fMin = $toMin(substr($s->{$field}, 0, 5) . ':00');
            $len = static::spanMinutes($s);

            // حدّ قادم اليوم
            if ($fMin >= $tMin) {
                $d = $fMin - $tMin;
                if ($better($d, $len, $s->id, $upcomingIn, $upcomingLen, $upcomingId)) {
                    $upcoming = $s;
                    $upcomingIn = $d;
                    $upcomingLen = $len;
                    $upcomingId = $s->id;
                }
            }

            // حدّ ماضٍ اليوم
            if ($fMin <= $tMin) {
                $d = $tMin - $fMin;
                if ($better($d, $len, $s->id, $pastAgo, $pastLen, $pastId)) {
                    $past = $s;
                    $pastAgo = $d;
                    $pastLen = $len;
                    $pastId = $s->id;
                }
            }

            // حدّ ماضٍ من الأمس (نهايات الليلية تحمل إلى اليوم)
            $dPrev = (24 * 60 - $fMin) + $tMin;
            if ($better($dPrev, $len, $s->id, $pastAgo, $pastLen, $pastId)) {
                $past = $s;
                $pastAgo = $dPrev;
                $pastLen = $len;
                $pastId = $s->id;
            }
        }

        if ($upcoming && $upcomingIn <= $window) {
            return $upcoming;
        }

        return $past ?? $upcoming;
    }
}
