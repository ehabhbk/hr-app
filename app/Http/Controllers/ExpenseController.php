<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Employee;
use App\Models\Notification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = Expense::with('employee', 'reviewer')->latest();
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
        
        if ($request->user()->hasRole && !in_array('*', $request->user()->role->permissions ?? [])) {
            $empId = $request->user()->employee_id;
            if ($empId) $query->where('employee_id', $empId);
        }
        
        return response()->json(['data' => $query->paginate($request->input('per_page', 25))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'category' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'description' => 'nullable|string',
            'receipt_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($request->hasFile('receipt_file')) {
            $data['receipt_file'] = $request->file('receipt_file')->store('expense-receipts', 'public');
        }

        $expense = Expense::create($data);
        $employee = Employee::find($data['employee_id']);
        $this->logActivity('expense_created', $expense, null, $data, 'طلب مصروفات: ' . ($employee->name ?? ''), $request);

        return response()->json(['data' => $expense, 'message' => 'تم إرسال طلب المصروفات'], 201);
    }

    public function review(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);
        $old = ['status' => $expense->status];

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_note' => 'nullable|string',
        ]);

        $expense->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'reviewed_by' => $request->user()->id,
        ]);

        $this->logActivity('expense_reviewed', $expense, $old, $data, 'مراجعة مصروفات: ' . $data['status'], $request);
        return response()->json(['data' => $expense, 'message' => 'تمت المراجعة']);
    }

    public function markPaid(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);
        $expense->update(['paid' => true]);
        $this->logActivity('expense_paid', $expense, ['paid' => false], ['paid' => true], 'تسوية مصروفات', $request);
        return response()->json(['data' => $expense, 'message' => 'تم التسجيل كمدفوع']);
    }
}
