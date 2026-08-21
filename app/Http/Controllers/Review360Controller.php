<?php

namespace App\Http\Controllers;

use App\Models\Review360;
use Illuminate\Http\Request;

class Review360Controller extends Controller
{
    public function index(Request $request)
    {
        $query = Review360::with('employee', 'reviewer');
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->reviewer_type) $query->where('reviewer_type', $request->reviewer_type);
        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'reviewer_id' => 'required|exists:users,id',
            'reviewer_type' => 'required|in:manager,peer,subordinate,self',
            'communication_score' => 'nullable|numeric|between:0,5',
            'teamwork_score' => 'nullable|numeric|between:0,5',
            'leadership_score' => 'nullable|numeric|between:0,5',
            'technical_score' => 'nullable|numeric|between:0,5',
            'problem_solving_score' => 'nullable|numeric|between:0,5',
            'strengths' => 'nullable|string',
            'improvements' => 'nullable|string',
            'comments' => 'nullable|string',
            'review_period' => 'required|date',
        ]);
        $review = Review360::create($data);
        return response()->json(['message' => 'تم إرسال التقييم', 'data' => $review], 201);
    }

    public function summary(Request $request)
    {
        $employeeId = $request->employee_id;
        $reviews = Review360::where('employee_id', $employeeId)->get();

        $avgScores = [
            'communication' => $reviews->avg('communication_score'),
            'teamwork' => $reviews->avg('teamwork_score'),
            'leadership' => $reviews->avg('leadership_score'),
            'technical' => $reviews->avg('technical_score'),
            'problem_solving' => $reviews->avg('problem_solving_score'),
        ];

        $byType = $reviews->groupBy('reviewer_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'avg_communication' => $group->avg('communication_score'),
                'avg_teamwork' => $group->avg('teamwork_score'),
                'avg_leadership' => $group->avg('leadership_score'),
                'avg_technical' => $group->avg('technical_score'),
                'avg_problem_solving' => $group->avg('problem_solving_score'),
            ];
        });

        return response()->json([
            'total_reviews' => $reviews->count(),
            'average_scores' => $avgScores,
            'overall_average' => collect($avgScores)->filter()->avg(),
            'by_reviewer_type' => $byType,
        ]);
    }

    public function destroy($id)
    {
        Review360::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف التقييم']);
    }
}
