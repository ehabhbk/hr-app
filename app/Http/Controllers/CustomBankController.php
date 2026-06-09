<?php

namespace App\Http\Controllers;

use App\Models\CustomBank;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomBankController extends Controller
{
    public function index()
    {
        $banks = CustomBank::orderBy('name')->get();
        return response()->json(['data' => $banks]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Check if bank already exists
        $key = Str::slug($request->name, '_');
        $exists = CustomBank::where('key', $key)->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'هذا البنك موجود مسبقاً',
                'error' => 'bank_exists'
            ], 422);
        }

        $bank = CustomBank::create([
            'name' => $request->name,
            'key' => $key,
            'icon' => '🏦',
        ]);

        return response()->json(['data' => $bank], 201);
    }

    public function destroy($id)
    {
        $bank = CustomBank::findOrFail($id);
        $bank->delete();

        return response()->json(['message' => 'تم حذف البنك بنجاح']);
    }
}
