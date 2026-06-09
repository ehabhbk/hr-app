<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function generate(Request $request, $employeeId)
    {
        $employee = Employee::with('department')->findOrFail($employeeId);

        $orgSetting = Setting::where('key', 'organization')->first();
        $org = $orgSetting ? $orgSetting->value : [];

        $orgName = $org['name'] ?? '_________';
        $orgAddress = $org['address'] ?? '_________';
        $orgPhone = $org['phone'] ?? '_________';
        $managerName = $org['manager_name'] ?? '_________';
        $orgLogo = isset($org['logo']) ? public_path('storage/' . $org['logo']) : null;
        $orgStamp = isset($org['stamp']) ? public_path('storage/' . $org['stamp']) : null;

        $logoBase64 = null;
        $stampBase64 = null;
        
        if ($orgLogo && file_exists($orgLogo)) {
            $logoBase64 = 'data:image/' . pathinfo($orgLogo, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($orgLogo));
        }
        if ($orgStamp && file_exists($orgStamp)) {
            $stampBase64 = 'data:image/' . pathinfo($orgStamp, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($orgStamp));
        }

        $durationMonths = $request->input('duration_months', 12);
        $baseSalary = floatval($employee->base_salary) ?: 0;
        $positionAllowance = floatval($employee->position_allowance) ?: 0;
        $totalSalary = $baseSalary + $positionAllowance;

        $data = [
            'orgName' => $orgName,
            'orgAddress' => $orgAddress,
            'orgPhone' => $orgPhone,
            'managerName' => $managerName,
            'employee' => $employee,
            'baseSalary' => $baseSalary,
            'positionAllowance' => $positionAllowance,
            'totalSalary' => $totalSalary,
            'durationMonths' => $durationMonths,
            'date' => now()->format('Y-m-d'),
            'logoBase64' => $logoBase64,
            'stampBase64' => $stampBase64,
        ];

        $pdf = Pdf::loadView('contracts.employment', $data);

        return $pdf->download("contract_{$employee->name}_{$data['date']}.pdf");
    }
}
