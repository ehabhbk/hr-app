<?php

use App\Http\Controllers\AdvancesController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CustomBankController;
use App\Http\Controllers\DeductionsController;
use App\Http\Controllers\AttendanceDeviceController;
use App\Http\Controllers\AttendanceDeviceLogsController;
use App\Http\Controllers\AttendanceDeviceSettingController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankExportController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\HolidaysController;
use App\Http\Controllers\IncentivesController;
use App\Http\Controllers\LeavesController;
use App\Http\Controllers\LettersController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PdfExportController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\ShiftAssignmentController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ResignationRequestController;
use App\Http\Controllers\WarningsController;
use App\Http\Controllers\WorkShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catch-all for browser access (returns 405 Method Not Allowed)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return response()->json(['error' => 'API Endpoint', 'message' => 'This is an API endpoint. Please use the frontend application.'], 405);
});

/*
|--------------------------------------------------------------------------
| Public auth routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register']);

/*
|--------------------------------------------------------------------------
| Protected routes (require sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Current user info and permissions
    Route::get('/me', [MeController::class, 'me']);

    /*
    |-----------------------------------------------------------------------
    | Attendance device settings (singleton)
    |-----------------------------------------------------------------------
    */
    Route::get('/attendance-device/settings', [AttendanceDeviceSettingController::class, 'show']);
    Route::put('/attendance-device/settings', [AttendanceDeviceSettingController::class, 'update']);
    Route::post('/attendance-device/settings/test', [AttendanceDeviceSettingController::class, 'testConnection']);
    Route::post('/attendance-device/settings/sync', [AttendanceDeviceSettingController::class, 'sync']);

    /*
    |-----------------------------------------------------------------------
    | Attendance devices CRUD
    |-----------------------------------------------------------------------
    */
    Route::get('/attendance-device', [AttendanceDeviceController::class, 'index']);
    Route::post('/attendance-device', [AttendanceDeviceController::class, 'store']);
    Route::get('/attendance-device/{id}', [AttendanceDeviceController::class, 'show']);
    Route::put('/attendance-device/{id}', [AttendanceDeviceController::class, 'update']);
    Route::delete('/attendance-device/{id}', [AttendanceDeviceController::class, 'destroy']);
    Route::post('/attendance-device/{id}/test', [AttendanceDeviceController::class, 'testConnection']);
    Route::post('/attendance-device/{id}/sync', [AttendanceDeviceController::class, 'sync']);
    Route::post('/attendance-device/{id}/enable', [AttendanceDeviceController::class, 'enableDevice']);
    Route::post('/attendance-device/{id}/disable', [AttendanceDeviceController::class, 'disableDevice']);
    Route::post('/attendance-device/{id}/register-user', [AttendanceDeviceController::class, 'registerUser']);
    Route::post('/attendance-device/{id}/remove-user', [AttendanceDeviceController::class, 'removeUser']);
    Route::post('/attendance-device/sync-all', [AttendanceDeviceController::class, 'syncAll']);
    Route::post('/attendance-device/{id}/set-time', [AttendanceDeviceController::class, 'setTime']);
    Route::get('/attendance-device/{id}/fingerprints', [AttendanceDeviceController::class, 'downloadFingerprints']);
    Route::get('/attendance-device/{id}/fingerprints/{uid}', [AttendanceDeviceController::class, 'downloadFingerprints']);
    Route::post('/attendance-device/{id}/upload-fingerprints', [AttendanceDeviceController::class, 'uploadFingerprints']);
    Route::post('/attendance-device/{id}/enroll-fingerprint', [AttendanceDeviceController::class, 'enrollFingerprint']);
    Route::post('/attendance-device/{id}/enroll-face', [AttendanceDeviceController::class, 'enrollFace']);
    Route::post('/attendance-device/{id}/check-enrollment', [AttendanceDeviceController::class, 'checkEnrollment']);
    Route::post('/attendance-device/{id}/register-user-manual', [AttendanceDeviceController::class, 'registerUserManually']);

    Route::get('/attendance-device/{id}/info', [AttendanceDeviceController::class, 'getDeviceInfo']);
    Route::get('/attendance-device/{id}/users', [AttendanceDeviceController::class, 'getDeviceUsers']);

    // Biometric enrollment endpoints (used by frontend)
    Route::post('/attendance-device/{id}/register-fingerprint', [AttendanceDeviceController::class, 'registerFingerprint']);
    Route::post('/attendance-device/{id}/enroll-fingerprint', [AttendanceDeviceController::class, 'enrollFingerprint']);
    Route::post('/attendance-device/{id}/register-face', [AttendanceDeviceController::class, 'registerFace']);
    Route::post('/attendance-device/{id}/enroll-face', [AttendanceDeviceController::class, 'enrollFace']);
    Route::post('/attendance-device/{id}/check-enrollment', [AttendanceDeviceController::class, 'checkEnrollment']);
    Route::post('/attendance-device/{id}/register-user-manual', [AttendanceDeviceController::class, 'registerUserManual']);

    Route::get('/attendance-device/{id}/face-data', [AttendanceDeviceController::class, 'downloadFaceData']);
    Route::get('/attendance-device/{id}/face-data/{employeeId}', [AttendanceDeviceController::class, 'downloadFaceData']);
    Route::post('/attendance-device/{id}/register-employee', [AttendanceDeviceController::class, 'registerEmployeeOnDevice']);
    Route::post('/attendance-device/{id}/remove-employee', [AttendanceDeviceController::class, 'removeEmployeeFromDevice']);

    /*
    |-----------------------------------------------------------------------
    | Attendance device logs (same endpoint with optional filters)
    |-----------------------------------------------------------------------
    */
    Route::get('/attendance-logs', [AttendanceDeviceLogsController::class, 'index']);
    Route::get('/attendance-logs/{id}', [AttendanceDeviceLogsController::class, 'show']);
    Route::post('/attendance-logs/{id}/excuse', [AttendanceDeviceLogsController::class, 'excuse']);

    /*
    |-----------------------------------------------------------------------
    | Uploads
    |-----------------------------------------------------------------------
    */
    Route::post('/uploads/avatar', [UploadController::class, 'avatar']);
    Route::get('/files/cv/{employeeId}', [UploadController::class, 'getCv']);

    /*
    |-----------------------------------------------------------------------
    | Users
    |-----------------------------------------------------------------------
    */
    Route::get('/me', [UserController::class, 'me']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    /*
    |-----------------------------------------------------------------------
    | Employees (API)
    |-----------------------------------------------------------------------
    */
    Route::get('/employees', [EmployeesController::class, 'index']);
    Route::post('/employees', [EmployeesController::class, 'store']);
    Route::get('/employees/{id}', [EmployeesController::class, 'show']);
    Route::put('/employees/{id}', [EmployeesController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeesController::class, 'destroy']);
    Route::post('/employees/{id}/terminate', [EmployeesController::class, 'terminate']);
    Route::post('/employees/{id}/restore', [EmployeesController::class, 'restore']);
    Route::get('/employees/{id}/contract', [ContractController::class, 'generate']);
    Route::get('/employees/{id}/fingerprints', [EmployeesController::class, 'getFingerprints']);
    Route::post('/employees/{id}/fingerprint', [EmployeesController::class, 'addFingerprint']);
    Route::delete('/employees/{id}/fingerprint/{fingerprintId}', [EmployeesController::class, 'deleteFingerprint']);

    /*
    |-----------------------------------------------------------------------
    | Departments
    |-----------------------------------------------------------------------
    */
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/{id}', [DepartmentController::class, 'show']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::put('/departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

    /*
    |-----------------------------------------------------------------------
    | Settings (key/value JSON store)
    | NOTE: Specific routes MUST come BEFORE the generic {key} routes!
    |-----------------------------------------------------------------------
    */
    Route::get('/settings/attendance', [SettingsController::class, 'getAttendanceSettings']);
    Route::put('/settings/attendance', [SettingsController::class, 'updateAttendanceSettings']);
    Route::get('/settings/salary', [SettingsController::class, 'getSalarySettings']);
    Route::put('/settings/salary', [SettingsController::class, 'updateSalarySettings']);
    Route::get('/settings/tax-brackets', [SettingsController::class, 'getTaxBrackets']);
    Route::put('/settings/tax-brackets', [SettingsController::class, 'updateTaxBrackets']);
    Route::get('/settings/leaves', [SettingsController::class, 'getLeaveSettings']);
    Route::put('/settings/leaves', [SettingsController::class, 'updateLeaveSettings']);
    Route::get('/settings/advances', [SettingsController::class, 'getAdvanceSettings']);
    Route::put('/settings/advances', [SettingsController::class, 'updateAdvanceSettings']);
    Route::get('/settings/salary-increase', [SettingsController::class, 'getSalaryIncrease']);
    Route::put('/settings/salary-increase', [SettingsController::class, 'updateSalaryIncrease']);
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::get('/settings/{key}', [SettingsController::class, 'show']);
    Route::put('/settings/{key}', [SettingsController::class, 'update']);

    /*
    |-----------------------------------------------------------------------
    | Organization settings (using settings table)
    |-----------------------------------------------------------------------
    */
    Route::get('/organization', [SettingsController::class, 'organization']);
    Route::put('/organization', [SettingsController::class, 'updateOrganization']);
    Route::post('/organization', [SettingsController::class, 'updateOrganization']);
    Route::get('/setting-audits', [SettingsController::class, 'audits']);
    Route::get('/currency', [SettingsController::class, 'getCurrency']);

    /*
    |-----------------------------------------------------------------------
    | WhatsApp Settings
    |-----------------------------------------------------------------------
    */
    Route::get('/settings/whatsapp', [SettingsController::class, 'getWhatsAppSettings']);
    Route::put('/settings/whatsapp', [SettingsController::class, 'updateWhatsAppSettings']);
    Route::post('/settings/whatsapp/test', [SettingsController::class, 'testWhatsApp']);

    /*
    |-----------------------------------------------------------------------
    | Work Shifts (الورديات)
    |-----------------------------------------------------------------------
    */
    Route::get('/work-shifts', [WorkShiftController::class, 'index']);
    Route::post('/work-shifts', [WorkShiftController::class, 'store']);
    Route::put('/work-shifts/{id}', [WorkShiftController::class, 'update']);
    Route::delete('/work-shifts/{id}', [WorkShiftController::class, 'destroy']);

    Route::get('/shift-assignments', [ShiftAssignmentController::class, 'index']);
    Route::post('/shift-assignments', [ShiftAssignmentController::class, 'store']);
    Route::delete('/shift-assignments/{id}', [ShiftAssignmentController::class, 'destroy']);

    /*
    |-----------------------------------------------------------------------
    | Attendance Records (سجلات الحضور)
    |-----------------------------------------------------------------------
    */
    Route::get('/attendance-records', [AttendanceRecordController::class, 'index']);
    Route::post('/attendance-records', [AttendanceRecordController::class, 'store']);
    Route::post('/attendance-records/process-logs', [AttendanceRecordController::class, 'processFromDeviceLogs']);
    Route::get('/attendance-records/monthly-report', [AttendanceRecordController::class, 'monthlyReport']);
    Route::post('/attendance-records/recalculate', [AttendanceRecordController::class, 'recalculateAll']);
    Route::post('/attendance-records/{id}/excuse', [AttendanceRecordController::class, 'excuseDelay']);
    Route::post('/attendance-records/{id}/excuse-delay', [AttendanceRecordController::class, 'excuseDelay']);
    Route::post('/attendance-records/{id}/excuse-early-leave', [AttendanceRecordController::class, 'excuseEarlyLeave']);
    Route::post('/attendance-records/{id}/excuse-absence', [AttendanceRecordController::class, 'excuseAbsence']);
    Route::post('/attendance-records/{id}/cancel-deduction', [AttendanceRecordController::class, 'cancelDeduction']);
    Route::post('/attendance-records/{id}/apply-deduction', [AttendanceRecordController::class, 'applyDeduction']);
    Route::get('/pdf/export/attendance', [AttendanceRecordController::class, 'exportPdf']);

    /*
    |-----------------------------------------------------------------------
    | Holidays (public holidays)
    |-----------------------------------------------------------------------
    */
    Route::get('/holidays', [HolidaysController::class, 'index']);
    Route::post('/holidays', [HolidaysController::class, 'store']);
    Route::delete('/holidays/{id}', [HolidaysController::class, 'destroy']);

    /*
    |-----------------------------------------------------------------------
    | Incentives (الحوافز)
    |-----------------------------------------------------------------------
    */
    Route::get('/incentives', [IncentivesController::class, 'index']);
    Route::post('/incentives', [IncentivesController::class, 'store']);
    Route::delete('/incentives/{id}', [IncentivesController::class, 'destroy']);

    /*
    |-----------------------------------------------------------------------
    | Deductions (الخصومات)
    |-----------------------------------------------------------------------
    */
    Route::get('/deductions', [DeductionsController::class, 'index']);
    Route::post('/deductions', [DeductionsController::class, 'store']);
    Route::delete('/deductions/{id}', [DeductionsController::class, 'destroy']);

    /*
    |-----------------------------------------------------------------------
    | Leaves (requests)
    |-----------------------------------------------------------------------
    */
    Route::get('/leaves/requests', [LeavesController::class, 'index']);
    Route::post('/leaves/requests', [LeavesController::class, 'store']);
    Route::post('/leaves/requests/{id}/status', [LeavesController::class, 'updateStatus']);
    Route::get('/leaves/check-expired', [LeavesController::class, 'checkExpiredLeaves']);

    /*
    |-----------------------------------------------------------------------
    | Advances (requests)
    |-----------------------------------------------------------------------
    */
    Route::get('/advances/requests', [AdvancesController::class, 'index']);
    Route::post('/advances/requests', [AdvancesController::class, 'store']);
    Route::post('/advances/requests/{id}/approve', [AdvancesController::class, 'approve']);
    Route::post('/advances/requests/{id}/reject', [AdvancesController::class, 'reject']);

    /*
    |-----------------------------------------------------------------------
    | Resignation Requests
    |-----------------------------------------------------------------------
    */
    Route::get('/resignation-requests', [ResignationRequestController::class, 'index']);
    Route::post('/resignation-requests', [ResignationRequestController::class, 'store']);
    Route::post('/resignation-requests/{id}/status', [ResignationRequestController::class, 'updateStatus']);

    /*
    |-----------------------------------------------------------------------
    | Warnings / Discipline
    |-----------------------------------------------------------------------
    */
    Route::get('/discipline/warnings', [WarningsController::class, 'index']);
    Route::post('/discipline/warnings', [WarningsController::class, 'store']);
    Route::delete('/discipline/warnings/{id}', [WarningsController::class, 'destroy']);

    /*
    |-----------------------------------------------------------------------
    | Financials helper (optional)
    |-----------------------------------------------------------------------
    */
    Route::get('/financials', [SettingsController::class, 'show']);
    Route::put('/financials', [SettingsController::class, 'update']);

    /*
    |-----------------------------------------------------------------------
    | Reports
    |-----------------------------------------------------------------------
    */
    Route::get('/reports/salary', [ReportsController::class, 'salaryReport']);
    Route::get('/reports/income-tax', [ReportsController::class, 'incomeTaxReport']);
    Route::get('/reports/salary-increase', [ReportsController::class, 'salaryIncreaseReport']);
    Route::get('/reports/leave-warning', [ReportsController::class, 'leaveWarningReport']);
    Route::get('/reports/employee-evaluation', [ReportsController::class, 'employeeEvaluationReport']);
    Route::get('/reports/department', [ReportsController::class, 'departmentReport']);
    Route::get('/reports/summary', [ReportsController::class, 'summary']);
    Route::get('/reports/history', [ReportsController::class, 'reportHistory']);
    Route::get('/reports/employee-detailed', [ReportsController::class, 'employeeDetailedReport']);
    Route::get('/letters/history', [ReportsController::class, 'letterHistory']);

    /*
    |-----------------------------------------------------------------------
    | Letters
    |-----------------------------------------------------------------------
    */
    Route::post('/letters/generate', [LettersController::class, 'generate']);
    Route::post('/letters/export-pdf', [LettersController::class, 'exportPdf']);

    /*
    |-----------------------------------------------------------------------
    | PDF Exports
    |-----------------------------------------------------------------------
    */
    Route::get('/pdf/salary-report', [PdfExportController::class, 'salaryReport']);
    Route::get('/pdf/income-tax-report', [PdfExportController::class, 'incomeTaxReport']);
    Route::get('/pdf/leave-warning-report', [PdfExportController::class, 'leaveWarningReport']);
    Route::get('/pdf/department-report', [PdfExportController::class, 'departmentReport']);
    Route::get('/pdf/salary-increase-report', [PdfExportController::class, 'salaryIncreaseReport']);
    Route::get('/pdf/letter', [PdfExportController::class, 'letter']);
    Route::get('/pdf/employee-detailed-report', [PdfExportController::class, 'employeeDetailedReport']);

    /*
    |-----------------------------------------------------------------------
    | Dashboard
    |-----------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/quick-stats', [DashboardController::class, 'quickStats']);

    /*
    |-----------------------------------------------------------------------
    | Notifications
    |-----------------------------------------------------------------------
    */
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::post('/notifications/send', [NotificationController::class, 'send']);

    /*
    |-----------------------------------------------------------------------
    | Bank Exports
    |-----------------------------------------------------------------------
    */
    Route::get('/bank-exports', [BankExportController::class, 'index']);
    Route::get('/bank-exports/banks', [BankExportController::class, 'getBanks']);
    Route::post('/bank-exports/generate', [BankExportController::class, 'generate']);
    Route::get('/bank-exports/{id}/download', [BankExportController::class, 'download']);
    Route::delete('/bank-exports/{id}', [BankExportController::class, 'destroy']);

    /*
    |-----------------------------------------------------------------------
    | Custom Banks
    |-----------------------------------------------------------------------
    */
    Route::get('/banks/custom', [CustomBankController::class, 'index']);
    Route::post('/banks/custom', [CustomBankController::class, 'store']);
    Route::delete('/banks/custom/{id}', [CustomBankController::class, 'destroy']);

    /*
    |-----------------------------------------------------------------------
    | Roles & Permissions
    |-----------------------------------------------------------------------
    */
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

    /*
    |-----------------------------------------------------------------------
    | Settlement Settings (التسوية والمعاشات)
    |-----------------------------------------------------------------------
    */
    Route::get('/settlements', [SettlementController::class, 'index']);
    Route::get('/settlements/{key}', [SettlementController::class, 'show']);
    Route::put('/settlements/{key}', [SettlementController::class, 'update']);
    Route::put('/settlements', [SettlementController::class, 'updateAll']);
    Route::get('/settlements/calculate/{employeeId}', [SettlementController::class, 'calculateEmployee']);
    Route::get('/settlements/calculate', [SettlementController::class, 'calculateAllEmployees']);
    Route::get('/settlements/export/{employeeId}', [SettlementController::class, 'exportSettlementPdf']);

    /*
    |-----------------------------------------------------------------------
    | Employee Assets (عهد الموظفين)
    |-----------------------------------------------------------------------
    */
    Route::get('/employee-assets', [AssetController::class, 'index']);
    Route::post('/employee-assets', [AssetController::class, 'store']);
    Route::get('/employee-assets/{id}', [AssetController::class, 'show']);
    Route::put('/employee-assets/{id}', [AssetController::class, 'update']);
    Route::delete('/employee-assets/{id}', [AssetController::class, 'destroy']);
    Route::post('/employee-assets/{id}/return', [AssetController::class, 'returnAsset']);
});
