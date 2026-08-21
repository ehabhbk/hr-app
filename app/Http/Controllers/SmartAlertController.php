<?php

namespace App\Http\Controllers;

use App\Models\SmartAlert;
use Illuminate\Http\Request;

class SmartAlertController extends Controller
{
    public function index(Request $request)
    {
        $query = SmartAlert::query();
        if ($request->severity) $query->where('severity', $request->severity);
        if ($request->has('unread_only')) $query->where('is_read', false);
        return response()->json($query->orderByDesc('created_at')->limit(50)->get());
    }

    public function markAsRead($id)
    {
        $alert = SmartAlert::findOrFail($id);
        $alert->update(['is_read' => true]);
        return response()->json(['message' => 'تم تحديد التنبيه كمقروء']);
    }

    public function markAllAsRead()
    {
        SmartAlert::where('is_read', false)->update(['is_read' => true]);
        return response()->json(['message' => 'تم تحديد جميع التنبيهات كمقروءة']);
    }

    public function unreadCount()
    {
        return response()->json(['count' => SmartAlert::where('is_read', false)->count()]);
    }

    public function generateAlerts()
    {
        $alerts = [];

        // Absence spike detection
        $todayAbsences = \App\Models\AttendanceRecord::where('date', now()->toDateString())
            ->where('is_absent', true)->count();
        $avgAbsences = \App\Models\AttendanceRecord::whereBetween('date', [
            now()->subDays(30)->toDateString(), now()->subDay()->toDateString()
        ])->where('is_absent', true)->avg('is_absent') ?? 0;

        if ($todayAbsences > $avgAbsences * 1.3 && $avgAbsences > 0) {
            $alerts[] = SmartAlert::create([
                'type' => 'absence_spike',
                'title' => 'ارتفاع غير عادي في الغياب',
                'message' => "عدد الغائبين اليوم ($todayAbsences) أعلى من المعدل اليومي ($avgAbsences)",
                'severity' => 'warning',
                'data' => ['today' => $todayAbsences, 'avg' => round($avgAbsences, 1)],
            ]);
        }

        // Contract expiry alerts
        $expiringContracts = \App\Models\Employee::where('status', 'active')
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [now(), now()->addDays(30)])
            ->count();

        if ($expiringContracts > 0) {
            $alerts[] = SmartAlert::create([
                'type' => 'contract_expiry',
                'title' => 'عقود تنتهي قريباً',
                'message' => "يوجد $expiringContracts عقد تنتهي خلال 30 يوماً",
                'severity' => 'warning',
                'data' => ['count' => $expiringContracts],
            ]);
        }

        // High overtime
        $monthOvertime = \App\Models\OvertimeRequest::where('status', 'approved')
            ->whereMonth('date', now()->month)
            ->sum('hours');

        if ($monthOvertime > 100) {
            $alerts[] = SmartAlert::create([
                'type' => 'overtime_increase',
                'title' => 'ارتفاع ساعات الأوفرتايم',
                'message' => "إجمالي ساعات الأوفرتايم هذا الشهر: $monthOvertime ساعة",
                'severity' => 'critical',
                'data' => ['hours' => $monthOvertime],
            ]);
        }

        return response()->json(['generated' => count($alerts), 'alerts' => $alerts]);
    }

    public function destroy($id)
    {
        SmartAlert::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف التنبيه']);
    }
}
