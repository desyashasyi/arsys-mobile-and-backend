<?php

namespace App\Http\Controllers\Api\Specialization;

use App\Http\Controllers\Controller;
use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchMilestone;
use App\Models\ArSys\ResearchReview;
use App\Models\ArSys\ResearchSupervisor;
use App\Models\ArSys\Staff;
use App\Models\ArSys\Student;
use Illuminate\Http\Request;

class ResearchController extends Controller
{
    private function getProgramId(): ?int
    {
        return auth()->user()->staff?->program_id;
    }

    private function baseQuery(int $programId)
    {
        return Research::with(['student.program', 'milestone', 'supervisor.staff'])
            ->whereHas('student', fn($q) => $q->where('program_id', $programId));
    }

    private function formatResearch(Research $r): array
    {
        $student = $r->student;
        $supervisors = $r->supervisor->map(fn($s) => [
            'name'  => $s->staff ? ($s->staff->first_name . ' ' . $s->staff->last_name) : '-',
            'order' => $s->order,
        ]);

        return [
            'id'               => $r->id,
            'title'            => $r->title,
            'milestone_code'   => $r->milestone?->code ?? '-',
            'milestone_phase'  => $r->milestone?->phase ?? '-',
            'student_name'     => $student ? ($student->first_name . ' ' . $student->last_name) : '-',
            'student_number'   => $student?->nim ?? '-',
            'supervisors'      => $supervisors,
            'updated_at'       => $r->updated_at?->toDateString(),
        ];
    }

    // GET /specialization/research/new
    public function newAndRenew(Request $request)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['message' => 'Program not found.'], 403);
        }

        $research = $this->baseQuery($programId)
            ->whereHas('history', fn($q) => $q->whereHas('type', fn($q2) => $q2->whereIn('code', ['SUB', 'REN'])))
            ->whereHas('milestone', fn($q) => $q->where('code', 'Proposal')->where('phase', 'Submitted'))
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        return response()->json([
            'data'         => collect($research->items())->map(fn($r) => $this->formatResearch($r)),
            'current_page' => $research->currentPage(),
            'last_page'    => $research->lastPage(),
            'total'        => $research->total(),
        ]);
    }

    // GET /specialization/research/review
    public function beingReviewed(Request $request)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['message' => 'Program not found.'], 403);
        }

        $research = $this->baseQuery($programId)
            ->whereHas('milestone', fn($q) => $q->where('code', 'Proposal')->where('phase', 'Review'))
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        return response()->json([
            'data'         => collect($research->items())->map(fn($r) => $this->formatResearch($r)),
            'current_page' => $research->currentPage(),
            'last_page'    => $research->lastPage(),
            'total'        => $research->total(),
        ]);
    }

    // GET /specialization/research/in-progress
    public function inProgress(Request $request)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['message' => 'Program not found.'], 403);
        }

        $research = $this->baseQuery($programId)
            ->whereHas('supervisor')
            ->whereHas('milestone', fn($q) => $q->whereIn('code', ['Pre-defense', 'Final-defense']))
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        return response()->json([
            'data'         => collect($research->items())->map(fn($r) => $this->formatResearch($r)),
            'current_page' => $research->currentPage(),
            'last_page'    => $research->lastPage(),
            'total'        => $research->total(),
        ]);
    }

    // GET /specialization/research/rejected
    public function rejected(Request $request)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['message' => 'Program not found.'], 403);
        }

        $research = $this->baseQuery($programId)
            ->whereHas('history', fn($q) => $q->whereHas('type', fn($q2) => $q2->where('code', 'RJC')))
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        return response()->json([
            'data'         => collect($research->items())->map(fn($r) => $this->formatResearch($r)),
            'current_page' => $research->currentPage(),
            'last_page'    => $research->lastPage(),
            'total'        => $research->total(),
        ]);
    }

    // GET /specialization/research/{id}
    public function show($id)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['message' => 'Program not found.'], 403);
        }

        $research = Research::with([
            'student.program',
            'milestone',
            'supervisor.staff',
            'reviewers.staff',
            'reviewers.decision',
        ])->whereHas('student', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($id);

        $student = $research->student;

        return response()->json([
            'id'              => $research->id,
            'title'           => $research->title,
            'abstract'        => $research->abstract,
            'milestone_code'  => $research->milestone?->code ?? '-',
            'milestone_phase' => $research->milestone?->phase ?? '-',
            'student_name'    => $student ? trim($student->first_name . ' ' . $student->last_name) : '-',
            'student_number'  => $student?->nim ?? '-',
            'supervisors'     => $research->supervisor->map(fn($s) => [
                'id'    => $s->id,
                'name'  => $s->staff ? trim($s->staff->first_name . ' ' . $s->staff->last_name) : '-',
                'code'  => $s->staff?->code ?? '-',
                'order' => $s->order,
            ]),
            'reviewers'       => $research->reviewers->map(fn($r) => [
                'id'       => $r->id,
                'name'     => $r->staff ? trim($r->staff->first_name . ' ' . $r->staff->last_name) : '-',
                'code'     => $r->staff?->code ?? '-',
                'decision' => $r->decision?->description ?? 'Pending',
            ]),
            'updated_at'      => $research->updated_at?->toDateString(),
        ]);
    }

    // POST /specialization/research/{id}/supervisors
    public function addSupervisor(Request $request, $id)
    {
        $programId = $this->getProgramId();
        if (!$programId) return response()->json(['message' => 'Program not found.'], 403);

        $research = Research::whereHas('student', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($id);

        $request->validate([
            'staff_id' => 'required|integer',
            'order'    => 'required|integer|min:1|max:2',
        ]);

        if ($research->supervisor()->where('supervisor_id', $request->staff_id)->exists()) {
            return response()->json(['message' => 'Staff is already assigned as supervisor.'], 422);
        }

        $supervisor = ResearchSupervisor::create([
            'research_id'   => $research->id,
            'supervisor_id' => $request->staff_id,
            'order'         => $request->order,
        ]);

        return response()->json(['message' => 'Supervisor added.', 'id' => $supervisor->id]);
    }

    // DELETE /specialization/research/{id}/supervisors/{supervisorId}
    public function removeSupervisor($id, $supervisorId)
    {
        $programId = $this->getProgramId();
        if (!$programId) return response()->json(['message' => 'Program not found.'], 403);

        $research = Research::whereHas('student', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($id);

        $supervisor = ResearchSupervisor::where('research_id', $research->id)->findOrFail($supervisorId);
        $supervisor->delete();

        return response()->json(['message' => 'Supervisor removed.']);
    }

    // POST /specialization/research/{id}/assign  (Proposal|Submit → Pre-defense|In Progress)
    public function assign($id)
    {
        $programId = $this->getProgramId();
        if (!$programId) return response()->json(['message' => 'Program not found.'], 403);

        $research = Research::with(['supervisor'])
            ->whereHas('student', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($id);

        if ($research->supervisor->isEmpty()) {
            return response()->json(['message' => 'Supervisor must be assigned before assigning to Pre-defense.'], 422);
        }

        $milestone = ResearchMilestone::where('code', 'Pre-defense')->where('phase', 'In Progress')->first();
        if (!$milestone) {
            return response()->json(['message' => 'Milestone "Pre-defense | In Progress" not found.'], 500);
        }

        $research->milestone_id = $milestone->id;
        $research->save();

        return response()->json(['message' => 'Research moved to Pre-defense | In Progress.']);
    }

    // POST /specialization/research/{id}/reviewers
    public function addReviewer(Request $request, $id)
    {
        $programId = $this->getProgramId();
        if (!$programId) return response()->json(['message' => 'Program not found.'], 403);

        $research = Research::whereHas('student', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($id);

        $request->validate(['staff_id' => 'required|integer']);

        if ($research->reviewers()->where('reviewer_id', $request->staff_id)->exists()) {
            return response()->json(['message' => 'Staff is already assigned as reviewer.'], 422);
        }

        $reviewer = ResearchReview::create([
            'research_id' => $research->id,
            'reviewer_id' => $request->staff_id,
        ]);

        return response()->json(['message' => 'Reviewer added.', 'id' => $reviewer->id]);
    }

    // DELETE /specialization/research/{id}/reviewers/{reviewerId}
    public function removeReviewer($id, $reviewerId)
    {
        $programId = $this->getProgramId();
        if (!$programId) return response()->json(['message' => 'Program not found.'], 403);

        $research = Research::whereHas('student', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($id);

        $reviewer = ResearchReview::where('research_id', $research->id)->findOrFail($reviewerId);
        $reviewer->delete();

        return response()->json(['message' => 'Reviewer removed.']);
    }

    // POST /specialization/research/{id}/start-review  (Proposal|Submit → Proposal|Review)
    public function startReview($id)
    {
        $programId = $this->getProgramId();
        if (!$programId) return response()->json(['message' => 'Program not found.'], 403);

        $research = Research::with(['reviewers'])
            ->whereHas('student', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($id);

        if ($research->reviewers->isEmpty()) {
            return response()->json(['message' => 'Reviewer must be assigned before starting review.'], 422);
        }

        $milestone = ResearchMilestone::where('code', 'Proposal')->where('phase', 'Review')->first();
        if (!$milestone) {
            return response()->json(['message' => 'Milestone "Proposal | Review" not found.'], 500);
        }

        $research->milestone_id = $milestone->id;
        $research->save();

        return response()->json(['message' => 'Review started. Research moved to Proposal | Review.']);
    }

    // GET /specialization/students
    public function students(Request $request)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['message' => 'Program not found.'], 403);
        }

        $query = $request->query('query', '');

        $students = Student::where('program_id', $programId)
            ->when($query, fn($q) => $q->where(function ($q2) use ($query) {
                $q2->where('first_name', 'like', "%$query%")
                    ->orWhere('last_name', 'like', "%$query%")
                    ->orWhere('number', 'like', "%$query%");
            }))
            ->with('program')
            ->orderBy('first_name')
            ->get();

        return response()->json(['data' => $students->map(fn($s) => [
            'id'     => $s->id,
            'name'   => trim($s->first_name . ' ' . $s->last_name),
            'number' => $s->nim,
        ])]);
    }
}