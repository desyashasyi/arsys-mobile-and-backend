<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\ArSys\AcademicYear;
use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchConfig;
use App\Models\ArSys\ResearchConfigBase;
use App\Models\ArSys\ResearchFile;
use App\Models\ArSys\ResearchLog;
use App\Models\ArSys\ResearchLogType;
use App\Models\ArSys\ResearchMilestone;
use App\Models\ArSys\ResearchMilestoneLog;
use App\Models\ArSys\ResearchModel;
use App\Models\ArSys\ResearchRemark;
use App\Models\ArSys\ResearchType;
use App\Models\ArSys\ResearchTypeBase;
use App\Models\ArSys\DefenseApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResearchController extends Controller
{
    /**
     * List all research for the authenticated student.
     */
    public function index()
    {
        $user = Auth::user();
        $student = $user->student;

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student profile not found.'], 404);
        }

        $researches = Research::where('student_id', $student->id)
            ->with([
                'type.base',
                'milestone',
                'supervisor.staff',
                'history' => function ($q) {
                    $q->where('status', 1)->with('type');
                },
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($research) {
                $activeLog = $research->history->first();
                $logCode = $activeLog?->type?->code;

                return [
                    'id' => $research->id,
                    'code' => $research->code,
                    'title' => $research->title,
                    'type_name' => $research->type?->base?->code . ' - ' . $research->type?->base?->description,
                    'milestone_code' => $research->milestone?->code,
                    'milestone_phase' => $research->milestone?->phase,
                    'status_code' => $logCode,
                    'supervisors' => $research->supervisor->map(function ($s) {
                        return [
                            'id' => $s->id,
                            'name' => $s->staff ? trim($s->staff->first_name . ' ' . $s->staff->last_name) : 'N/A',
                            'code' => $s->staff?->code ?? 'N/A',
                        ];
                    }),
                ];
            });

        return response()->json(['success' => true, 'data' => $researches]);
    }

    /**
     * Show detailed info of a single research.
     */
    public function show($id)
    {
        $user = Auth::user();
        $student = $user->student;

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student profile not found.'], 404);
        }

        $research = Research::where('id', $id)
            ->where('student_id', $student->id)
            ->with([
                'type.base.model',
                'milestone',
                'supervisor.staff',
                'reviewers.staff',
                'remark' => function ($q) {
                    $q->orderBy('id', 'desc')->with('user.staff', 'user.student');
                },
                'history' => function ($q) {
                    $q->orderBy('id', 'desc')->with('type');
                },
                'proposalFile',
                'defenseApproval.staff',
                'defenseApproval.defenseModel',
            ])
            ->first();

        if (!$research) {
            return response()->json(['success' => false, 'message' => 'Research not found.'], 404);
        }

        $activeLog = $research->history->where('status', 1)->first();
        $logCode = $activeLog?->type?->code;

        // Determine warning state
        $warningState = null;
        if (in_array($logCode, ['FRE', 'REN', 'RJC', 'SIASPRO'])) {
            $warningState = $logCode;
        }

        // Determine available actions based on status
        $actions = $this->getAvailableActions($research, $logCode);

        $data = [
            'id' => $research->id,
            'code' => $research->code,
            'title' => $research->title,
            'abstract' => $research->abstract,
            'file' => $research->file,
            'type_name' => $research->type?->base?->code . ' - ' . $research->type?->base?->description,
            'type_id' => $research->type_id,
            'milestone_code' => $research->milestone?->code,
            'milestone_phase' => $research->milestone?->phase,
            'milestone_sequence' => $research->milestone?->sequence,
            'model_code' => $research->type?->base?->model?->code,
            'status_code' => $logCode,
            'warning_state' => $warningState,
            'actions' => $actions,
            'supervisors' => $research->supervisor->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->staff ? trim($s->staff->first_name . ' ' . $s->staff->last_name) : 'N/A',
                    'code' => $s->staff?->code ?? 'N/A',
                ];
            }),
            'reviewers' => $research->reviewers->map(function ($r) {
                return [
                    'id' => $r->id,
                    'name' => $r->staff ? trim($r->staff->first_name . ' ' . $r->staff->last_name) : 'N/A',
                    'code' => $r->staff?->code ?? 'N/A',
                    'decision' => $r->decision,
                ];
            }),
            'remarks' => $research->remark->map(function ($r) {
                $authorName = 'Unknown';
                if ($r->user) {
                    if ($r->user->staff) {
                        $authorName = trim($r->user->staff->first_name . ' ' . $r->user->staff->last_name);
                    } elseif ($r->user->student) {
                        $authorName = trim($r->user->student->first_name . ' ' . $r->user->student->last_name);
                    } else {
                        $authorName = $r->user->name;
                    }
                }
                return [
                    'id' => $r->id,
                    'message' => strip_tags($r->message, '<p><strong><em><u><b><i><ul><ol><li>'),
                    'author' => $authorName,
                    'created_at' => $r->created_at?->format('d M Y H:i'),
                ];
            }),
            'history' => $research->history->map(function ($h) {
                return [
                    'id' => $h->id,
                    'type_code' => $h->type?->code,
                    'type_description' => $h->type?->description,
                    'message' => $h->message,
                    'status' => $h->status,
                    'created_at' => $h->created_at?->format('d M Y H:i'),
                ];
            }),
            'approvals' => $research->defenseApproval->map(function ($a) {
                return [
                    'id' => $a->id,
                    'approver_name' => $a->staff ? trim($a->staff->first_name . ' ' . $a->staff->last_name) : 'N/A',
                    'role' => $a->role,
                    'decision' => $a->decision,
                    'defense_model' => $a->defenseModel?->description,
                    'created_at' => $a->created_at?->format('d M Y H:i'),
                ];
            }),
            'proposal_files' => $research->proposalFile->map(function ($f) {
                return [
                    'id' => $f->id,
                    'file' => $f->file,
                ];
            }),
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get research types available for this student.
     */
    public function getResearchTypes()
    {
        $user = Auth::user();
        $student = $user->student;

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student profile not found.'], 404);
        }

        $types = ResearchType::where('program_id', $student->program_id)
            ->where('status', 1)
            ->with('base.model')
            ->get()
            ->map(function ($type) {
                return [
                    'id' => $type->id,
                    'code' => $type->base?->code,
                    'description' => $type->base?->description,
                    'model_code' => $type->base?->model?->code,
                ];
            });

        return response()->json($types);
    }

    /**
     * Create a new research.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $student = $user->student;

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student profile not found.'], 404);
        }

        $request->validate([
            'type_id' => 'required|exists:arsys_research_type,id',
            'title' => 'required|string|max:500',
            'abstract' => 'required|string',
            'file_url' => 'nullable|url',
        ]);

        DB::beginTransaction();
        try {
            $type = ResearchType::with('base.model')->find($request->type_id);
            $researchModel = $type->base?->model;

            // Get first milestone for this research model (sequence > 0, as 0 is reserved for Rejected)
            $firstMilestone = ResearchMilestone::where('research_model_id', $researchModel?->id)
                ->where('sequence', '>', 0)
                ->orderBy('sequence')
                ->first();

            // Generate research code
            $existingCount = Research::where('student_id', $student->id)
                ->where('type_id', $request->type_id)
                ->count();
            $code = ($type->base?->code ?? 'RS') . '-' . $student->number . '-' . ($existingCount + 1);

            // Get latest academic year
            $academicYear = AcademicYear::latest('id')->first();

            $research = Research::create([
                'student_id' => $student->id,
                'type_id' => $request->type_id,
                'title' => $request->title,
                'abstract' => $request->abstract,
                'file' => $request->file_url,
                'code' => $code,
                'milestone_id' => $firstMilestone?->id,
                'academic_year_id' => $academicYear?->id,
            ]);

            // Create milestone log
            if ($firstMilestone) {
                ResearchMilestoneLog::create([
                    'research_id' => $research->id,
                    'milestone_id' => $firstMilestone->id,
                ]);
            }

            // Create CRE (Created) log
            $creLogType = ResearchLogType::where('code', 'CRE')->first();
            if ($creLogType) {
                ResearchLog::create([
                    'research_id' => $research->id,
                    'type_id' => $creLogType->id,
                    'loger_id' => $user->id,
                    'message' => $creLogType->description,
                    'status' => 1,
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Research created successfully.', 'data' => ['id' => $research->id]]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Research create error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create research: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update research title, abstract, and file.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $student = $user->student;

        $research = Research::where('id', $id)->where('student_id', $student->id)->first();
        if (!$research) {
            return response()->json(['success' => false, 'message' => 'Research not found.'], 404);
        }

        $request->validate([
            'title' => 'required|string|max:500',
            'abstract' => 'required|string',
            'file_url' => 'nullable|url',
        ]);

        DB::beginTransaction();
        try {
            $research->update([
                'title' => $request->title,
                'abstract' => $request->abstract,
                'file' => $request->file_url ?? $research->file,
            ]);

            // Create UPD log
            $updLogType = ResearchLogType::where('code', 'UPD')->first();
            if ($updLogType) {
                ResearchLog::create([
                    'research_id' => $research->id,
                    'type_id' => $updLogType->id,
                    'loger_id' => $user->id,
                    'message' => $updLogType->description,
                    'status' => 1,
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Research updated successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Research update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update research: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a research (only if in CRE/write state).
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $student = $user->student;

        $research = Research::where('id', $id)->where('student_id', $student->id)->first();
        if (!$research) {
            return response()->json(['success' => false, 'message' => 'Research not found.'], 404);
        }

        // Check if in write/CRE state
        $activeLog = ResearchLog::where('research_id', $research->id)
            ->where('status', 1)
            ->whereHas('type', fn($q) => $q->where('code', 'CRE'))
            ->first();

        if (!$activeLog) {
            return response()->json(['success' => false, 'message' => 'Research can only be deleted in proposal/write state.'], 403);
        }

        $research->delete();
        return response()->json(['success' => true, 'message' => 'Research deleted.']);
    }

    /**
     * Submit proposal for review.
     */
    public function submitProposal($id)
    {
        $user = Auth::user();
        $student = $user->student;

        $research = Research::where('id', $id)
            ->where('student_id', $student->id)
            ->with('type.base.model', 'milestone')
            ->first();

        if (!$research) {
            return response()->json(['success' => false, 'message' => 'Research not found.'], 404);
        }

        // Check if in CRE state
        $creLog = ResearchLog::where('research_id', $research->id)
            ->where('status', 1)
            ->whereHas('type', fn($q) => $q->where('code', 'CRE'))
            ->first();

        if (!$creLog) {
            return response()->json(['success' => false, 'message' => 'Research is not in proposal state.'], 403);
        }

        // Block submit if student has any research under review (SUB state) or already active (ACT state)
        $hasActiveOrReview = Research::where('student_id', $student->id)
            ->where('id', '!=', $research->id)
            ->whereHas('history', function ($q) {
                $q->where('status', 1)
                    ->whereHas('type', fn($t) => $t->whereIn('code', ['SUB', 'REV', 'ACT']));
            })
            ->exists();

        if ($hasActiveOrReview) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot submit a new proposal while another research is under review or already active.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Deactivate CRE log
            $creLog->update(['status' => null]);

            // Advance milestone
            $nextMilestone = ResearchMilestone::where('research_model_id', $research->type?->base?->model?->id)
                ->where('sequence', ($research->milestone?->sequence ?? 0) + 1)
                ->first();

            if ($nextMilestone) {
                $research->update(['milestone_id' => $nextMilestone->id]);
                ResearchMilestoneLog::create([
                    'research_id' => $research->id,
                    'milestone_id' => $nextMilestone->id,
                ]);
            }

            // Create SUB log
            $subLogType = ResearchLogType::where('code', 'SUB')->first();
            if ($subLogType) {
                ResearchLog::create([
                    'research_id' => $research->id,
                    'type_id' => $subLogType->id,
                    'loger_id' => $user->id,
                    'message' => $subLogType->description,
                    'status' => 1,
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Proposal submitted for review.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Submit proposal error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to submit: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Add a remark to a research.
     */
    public function addRemark(Request $request, $id)
    {
        $user = Auth::user();
        $student = $user->student;

        $research = Research::where('id', $id)->where('student_id', $student->id)->first();
        if (!$research) {
            return response()->json(['success' => false, 'message' => 'Research not found.'], 404);
        }

        $request->validate(['message' => 'required|string']);

        ResearchRemark::create([
            'research_id' => $research->id,
            'discussant_id' => $user->id,
            'message' => $request->message,
        ]);

        return response()->json(['success' => true, 'message' => 'Remark added.']);
    }

    /**
     * Delete own remark.
     */
    public function deleteRemark($id, $remarkId)
    {
        $user = Auth::user();

        $remark = ResearchRemark::where('id', $remarkId)
            ->where('discussant_id', $user->id)
            ->first();

        if (!$remark) {
            return response()->json(['success' => false, 'message' => 'Remark not found or not yours.'], 404);
        }

        $remark->delete();
        return response()->json(['success' => true, 'message' => 'Remark deleted.']);
    }

    /**
     * Renew frozen research.
     */
    public function renewResearch($id)
    {
        $user = Auth::user();
        $student = $user->student;

        $research = Research::where('id', $id)->where('student_id', $student->id)->first();
        if (!$research) {
            return response()->json(['success' => false, 'message' => 'Research not found.'], 404);
        }

        $freezeLog = ResearchLog::where('research_id', $research->id)
            ->where('status', 1)
            ->whereHas('type', fn($q) => $q->where('code', 'FRE'))
            ->first();

        if (!$freezeLog) {
            return response()->json(['success' => false, 'message' => 'Research is not frozen.'], 403);
        }

        DB::beginTransaction();
        try {
            $freezeLog->update(['status' => null]);

            $renLogType = ResearchLogType::where('code', 'REN')->first();
            if ($renLogType) {
                ResearchLog::create([
                    'research_id' => $research->id,
                    'type_id' => $renLogType->id,
                    'loger_id' => $user->id,
                    'message' => $renLogType->description ?? 'Research renewal requested',
                    'status' => 1,
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Research renewal requested.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Determine available actions for a research based on its state.
     */
    private function getAvailableActions($research, $logCode)
    {
        $actions = [];
        $milestoneCode = $research->milestone?->code;
        $milestonePhase = $research->milestone?->phase;

        switch ($logCode) {
            case 'CRE':
                $actions = ['edit', 'delete', 'submit'];
                break;
            case 'SUB':
                // Proposal submitted, waiting for review - no student actions
                break;
            case 'REV':
                // Under review - no student actions
                break;
            case 'ACT':
                if ($milestonePhase === 'In progress' || $milestonePhase === 'In Progress') {
                    if ($milestoneCode === 'Pre-defense') {
                        $actions = ['propose_predefense'];
                    } elseif ($milestoneCode === 'Final-defense') {
                        $actions = ['propose_finaldefense'];
                    } elseif ($milestoneCode === 'Seminar') {
                        $actions = ['propose_seminar'];
                    }
                }
                break;
            case 'FRE':
                $actions = ['renew'];
                break;
            case null:
                // No active log — check milestone to infer state
                if ($milestonePhase === 'Created' || $milestonePhase === 'Rejected') {
                    $actions = ['edit', 'delete', 'submit'];
                }
                break;
        }

        return $actions;
    }
}
