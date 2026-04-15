<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchReview;
use App\Models\ArSys\ResearchReviewDecisionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => []], 401);
        }
        $staffId = $user->staff->id;

        $researches = Research::whereHas('reviewers', function($query) use ($staffId) {
            $query->where('reviewer_id', $staffId);
        })
        ->whereHas('milestone', function($query) {
            $query->where('code', 'Proposal')->where('phase', 'Review');
        })
        ->with(['student.program', 'milestone', 'reviewers.staff', 'reviewers.decision'])
        ->orderBy('student_id', 'ASC')
        ->paginate(15);

        $transformedData = $researches->getCollection()->map(function ($research) {
            return [
                'id' => $research->id,
                'research_title' => $research->title,
                'student_name' => trim(($research->student->first_name ?? '') . ' ' . ($research->student->last_name ?? '')),
                'student_nim' => $research->student->nim ?? 'N/A',
                'milestone_code' => $research->milestone->code ?? 'N/A',
                'milestone_phase' => $research->milestone->phase ?? 'N/A',
                'reviewers' => $research->reviewers->map(function ($reviewer) {
                    return [
                        'reviewer_code' => $reviewer->staff->code ?? 'N/A',
                        'decision' => $reviewer->decision->description ?? 'Not Defined',
                    ];
                }),
            ];
        });

        return response()->json([
            'data' => $transformedData,
            'current_page' => $researches->currentPage(),
            'last_page' => $researches->lastPage(),
            'total' => $researches->total(),
        ]);
    }

    public function show($id)
    {
        $research = Research::with(['student.program', 'reviewers.staff', 'reviewers.decision'])->find($id);

        if (!$research) {
            return response()->json(['message' => 'Research not found.'], 404);
        }

        $fileUrl = $research->file ?? null;

        return response()->json([
            'id' => $research->id,
            'research_title' => $research->title,
            'student_info' => [
                'name' => trim(($research->student->first_name ?? '') . ' ' . ($research->student->last_name ?? '')),
                'nim' => $research->student->nim ?? 'N/A',
            ],
            'reviewers' => $research->reviewers->map(function ($reviewer) {
                return [
                    'name' => trim(($reviewer->staff->first_name ?? '') . ' ' . ($reviewer->staff->last_name ?? 'Unknown')),
                    'code' => $reviewer->staff->code ?? 'N/A',
                    'decision' => $reviewer->decision->description ?? 'Not Defined',
                ];
            }),
            'abstract' => $research->abstract,
            'file_url' => $fileUrl,
        ]);
    }

    public function submit(Request $request, $researchId)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'decision' => 'required|in:approve,reject',
        ]);

        $review = ResearchReview::where('research_id', $researchId)
            ->where('reviewer_id', $user->staff->id)
            ->first();

        if (!$review) {
            return response()->json(['message' => 'Review not found or you are not authorized.'], 404);
        }

        $decisionCode = ($validated['decision'] == 'approve') ? 'APP' : 'RJC';
        $decision = ResearchReviewDecisionType::where('code', $decisionCode)->first();

        if (!$decision) {
            return response()->json(['message' => 'Decision type not found.'], 500);
        }

        $review->decision_id = $decision->id;
        $review->save();

        return response()->json(['message' => 'Review submitted successfully.']);
    }
}
