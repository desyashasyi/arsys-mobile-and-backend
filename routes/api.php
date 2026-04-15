<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Api\Staff\SuperviseController;
use App\Http\Controllers\Api\Staff\ReviewController;
use App\Http\Controllers\Api\Staff\PreDefenseController;
use App\Http\Controllers\Api\Staff\FinalDefenseController;
use App\Http\Controllers\Api\Student\ResearchController as StudentResearchController;
use App\Http\Controllers\Api\Student\EventController as StudentEventController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\Specialization\EventController as SpecializationEventController;
use App\Http\Controllers\Api\Specialization\ResearchController as SpecializationResearchController;
use App\Http\Controllers\Api\FcmTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rute yang tidak memerlukan autentikasi
Route::post('/login', [LoginController::class, 'login']);
Route::post('/auth/google', [GoogleController::class, 'loginWithIdToken']);
// Route::post('/register', [RegisterController::class, 'register']); // Disabled

// Rute yang memerlukan autentikasi JWT
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/user', [LoginController::class, 'me']);
    Route::post('/fcm-token', [FcmTokenController::class, 'store']);

    // Rute untuk Staff
    Route::prefix('staff')->name('staff.')->group(function () {
        // Rute untuk Supervisi
        Route::prefix('supervise')->name('supervise.')->group(function () {
            Route::get('/', [SuperviseController::class, 'index'])->name('index');
            Route::get('/{id}', [SuperviseController::class, 'show'])->name('show');
            Route::get('/{id}/approvals', [SuperviseController::class, 'getApprovals'])->name('approvals');
            Route::post('/approvals/{approvalId}/approve', [SuperviseController::class, 'approve'])->name('approve');
        });

        // Rute untuk Review
        Route::prefix('review')->name('review.')->group(function () {
            Route::get('/', [ReviewController::class, 'index'])->name('index');
            Route::get('/{id}', [ReviewController::class, 'show'])->name('show');
            Route::post('/{researchId}/submit', [ReviewController::class, 'submit'])->name('submit');
        });

        // Rute untuk Pre-Defense
        Route::prefix('pre-defense')->name('pre-defense.')->group(function () {
            Route::get('/', [PreDefenseController::class, 'index'])->name('index');
            Route::get('/{id}/participants', [PreDefenseController::class, 'getParticipants'])->name('participants');
            Route::get('/participant/{id}', [PreDefenseController::class, 'getParticipantDetail'])->name('participant.detail');
            Route::post('/examiner/{id}/presence', [PreDefenseController::class, 'toggleExaminerPresence'])->name('examiner.presence');
            Route::get('/staff/search', [PreDefenseController::class, 'searchStaff'])->name('staff.search');
            Route::post('/participant/{id}/add-examiner', [PreDefenseController::class, 'addExaminer'])->name('participant.add_examiner');
            Route::get('/score-guide', [PreDefenseController::class, 'getScoreGuide'])->name('score_guide');
            Route::post('/participant/{id}/score', [PreDefenseController::class, 'submitScore'])->name('participant.score');
        });

        // Rute untuk Final-Defense
        Route::prefix('final-defense')->name('final-defense.')->group(function () {
            Route::get('/', [FinalDefenseController::class, 'index'])->name('index');
            Route::get('/{eventId}/rooms', [FinalDefenseController::class, 'getRooms'])->name('rooms');
            Route::get('/room/{roomId}', [FinalDefenseController::class, 'getRoomDetail'])->name('room.detail');
            Route::post('/room/{roomId}/switch-moderator', [FinalDefenseController::class, 'switchModerator'])->name('room.switch_moderator');
            Route::post('/room/{roomId}/examiner/{examinerId}/presence', [FinalDefenseController::class, 'toggleExaminerPresence'])->name('room.examiner.presence');
            Route::post('/presence/{presenceId}/score', [FinalDefenseController::class, 'submitExaminerScore'])->name('presence.score');
            Route::post('/supervisor/{supervisorId}/score', [FinalDefenseController::class, 'submitSupervisorScore'])->name('supervisor.score');
            Route::get('/score-guide', [FinalDefenseController::class, 'getScoreGuide'])->name('score_guide');
        });
    });

    // Rute untuk Student
    Route::prefix('student')->name('student.')->group(function () {
        Route::prefix('events')->name('events.')->group(function () {
            Route::get('/', [StudentEventController::class, 'index'])->name('index');
            Route::get('/{id}', [StudentEventController::class, 'show'])->name('show');
        });

        Route::prefix('research')->name('research.')->group(function () {
            Route::get('/', [StudentResearchController::class, 'index'])->name('index');
            Route::get('/types', [StudentResearchController::class, 'getResearchTypes'])->name('types');
            Route::post('/', [StudentResearchController::class, 'store'])->name('store');
            Route::get('/{id}', [StudentResearchController::class, 'show'])->name('show');
            Route::put('/{id}', [StudentResearchController::class, 'update'])->name('update');
            Route::delete('/{id}', [StudentResearchController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/submit', [StudentResearchController::class, 'submitProposal'])->name('submit');
            Route::post('/{id}/remark', [StudentResearchController::class, 'addRemark'])->name('remark');
            Route::delete('/{id}/remark/{remarkId}', [StudentResearchController::class, 'deleteRemark'])->name('remark.delete');
            Route::post('/{id}/renew', [StudentResearchController::class, 'renewResearch'])->name('renew');
        });
    });

    // Profile
    Route::get('/user/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/user/profile', [ProfileController::class, 'update'])->name('profile.update');

    // News
    Route::prefix('news')->name('news.')->group(function () {
        Route::get('/', [NewsController::class, 'index'])->name('index');
        Route::post('/', [NewsController::class, 'store'])->name('store');
        Route::put('/{id}', [NewsController::class, 'update'])->name('update');
        Route::delete('/{id}', [NewsController::class, 'destroy'])->name('destroy');
    });

    // Program (Kaprodi) routes
    Route::prefix('program')->name('program.')->group(function () {
        Route::get('/pre-defense', [ProgramController::class, 'preDefenseEvents'])->name('pre-defense.index');
        Route::get('/pre-defense/{eventId}', [ProgramController::class, 'preDefenseDetail'])->name('pre-defense.detail');
        Route::get('/final-defense', [ProgramController::class, 'finalDefenseEvents'])->name('final-defense.index');
        Route::get('/final-defense/{eventId}/rooms', [ProgramController::class, 'finalDefenseRooms'])->name('final-defense.rooms');
        Route::get('/final-defense/{eventId}/room/{roomId}', [ProgramController::class, 'finalDefenseRoomDetail'])->name('final-defense.room.detail');
        Route::get('/approvals', [ProgramController::class, 'approvalList'])->name('approvals.index');
        Route::get('/approvals/{id}', [ProgramController::class, 'approvalDetail'])->name('approvals.detail');
        Route::post('/approvals/{id}/approve', [ProgramController::class, 'approve'])->name('approvals.approve');
    });

    // Staff lookup for frontend autocomplete/search
    Route::get('/staff/search', [\App\Http\Controllers\Api\Staff\ResearchController::class, 'searchStaff'])->name('staff.search');

    // Specialization (KBK) routes
    Route::prefix('specialization')->name('specialization.')->group(function () {
        // Reference data
        Route::get('/event-types', [SpecializationEventController::class, 'eventTypes'])->name('event-types');
        Route::get('/spaces', [SpecializationEventController::class, 'spaces'])->name('spaces');
        Route::get('/sessions', [SpecializationEventController::class, 'sessions'])->name('sessions');
        Route::get('/staff/search', [SpecializationEventController::class, 'searchStaff'])->name('staff.search');

        // Event CRUD
        Route::get('/events', [SpecializationEventController::class, 'index'])->name('events.index');
        Route::post('/events', [SpecializationEventController::class, 'store'])->name('events.store');

        // Pre-defense (must be before /events/{id} to avoid route shadowing)
        Route::get('/events/pre-defense', [SpecializationEventController::class, 'preDefenseList'])->name('events.pre-defense');
        Route::get('/events/pre-defense/{eventId}/participants', [SpecializationEventController::class, 'preDefenseParticipants'])->name('events.pre-defense.participants');
        Route::get('/events/pre-defense/{eventId}/participants/view', [SpecializationEventController::class, 'viewPreDefenseParticipants'])->name('events.pre-defense.participants.view');
        Route::get('/events/pre-defense/participant/{id}', [SpecializationEventController::class, 'participantDetail'])->name('events.pre-defense.participant');
        Route::put('/events/pre-defense/participant/{id}', [SpecializationEventController::class, 'updateParticipant'])->name('events.pre-defense.participant.update');
        Route::post('/events/pre-defense/participant/{id}/examiners', [SpecializationEventController::class, 'addExaminer'])->name('events.pre-defense.participant.examiners.add');
        Route::delete('/events/pre-defense/examiners/{examinerId}', [SpecializationEventController::class, 'removeExaminer'])->name('events.pre-defense.examiners.remove');

        // Final-defense (must be before /events/{id} to avoid route shadowing)
        Route::get('/events/final-defense', [SpecializationEventController::class, 'finalDefenseList'])->name('events.final-defense');
        // Static room/participant sub-routes before parameterized ones
        Route::get('/events/final-defense/upcoming', [SpecializationEventController::class, 'upcomingFinalDefenseEvents'])->name('events.final-defense.upcoming');
        Route::delete('/events/final-defense/rooms/examiners/{examinerId}', [SpecializationEventController::class, 'removeFinalDefenseRoomExaminer'])->name('events.final-defense.room.examiners.remove');
        Route::delete('/events/final-defense/participants/{participantId}/unassign', [SpecializationEventController::class, 'unassignParticipantFromRoom'])->name('events.final-defense.participant.unassign');
        Route::post('/events/final-defense/participants/{participantId}/transfer', [SpecializationEventController::class, 'transferFinalDefenseParticipant'])->name('events.final-defense.participant.transfer');
        Route::get('/events/final-defense/rooms/{roomId}', [SpecializationEventController::class, 'finalDefenseRoomDetail'])->name('events.final-defense.room.detail');
        Route::put('/events/final-defense/rooms/{roomId}', [SpecializationEventController::class, 'updateFinalDefenseRoom'])->name('events.final-defense.room.update');
        Route::delete('/events/final-defense/rooms/{roomId}', [SpecializationEventController::class, 'deleteFinalDefenseRoom'])->name('events.final-defense.room.delete');
        Route::post('/events/final-defense/rooms/{roomId}/examiners', [SpecializationEventController::class, 'addFinalDefenseRoomExaminer'])->name('events.final-defense.room.examiners.add');
        Route::post('/events/final-defense/rooms/{roomId}/assign/{participantId}', [SpecializationEventController::class, 'assignParticipantToRoom'])->name('events.final-defense.room.assign');
        Route::get('/events/final-defense/{eventId}/rooms', [SpecializationEventController::class, 'finalDefenseRooms'])->name('events.final-defense.rooms');
        Route::get('/events/final-defense/{eventId}/rooms/view', [SpecializationEventController::class, 'viewFinalDefenseRooms'])->name('events.final-defense.rooms.view');
        Route::post('/events/final-defense/{eventId}/rooms', [SpecializationEventController::class, 'addFinalDefenseRoom'])->name('events.final-defense.rooms.add');
        Route::post('/events/pre-defense/{eventId}/publish', [SpecializationEventController::class, 'publishPreDefenseSchedules'])->name('events.pre-defense.publish');
        Route::post('/events/final-defense/{eventId}/publish', [SpecializationEventController::class, 'publishFinalDefenseSchedules'])->name('events.final-defense.publish');

        // Event CRUD with {id} (must be after static routes)
        Route::put('/events/{id}', [SpecializationEventController::class, 'update'])->name('events.update');
        Route::delete('/events/{id}', [SpecializationEventController::class, 'destroy'])->name('events.destroy');

        // Research lists
        Route::get('/research/new', [SpecializationResearchController::class, 'newAndRenew']);
        Route::get('/research/review', [SpecializationResearchController::class, 'beingReviewed']);
        Route::get('/research/in-progress', [SpecializationResearchController::class, 'inProgress']);
        Route::get('/research/rejected', [SpecializationResearchController::class, 'rejected']);
        // Research actions (must be before /{id} GET)
        Route::post('/research/{id}/supervisors', [SpecializationResearchController::class, 'addSupervisor']);
        Route::delete('/research/{id}/supervisors/{supervisorId}', [SpecializationResearchController::class, 'removeSupervisor']);
        Route::post('/research/{id}/assign', [SpecializationResearchController::class, 'assign']);
        Route::post('/research/{id}/reviewers', [SpecializationResearchController::class, 'addReviewer']);
        Route::delete('/research/{id}/reviewers/{reviewerId}', [SpecializationResearchController::class, 'removeReviewer']);
        Route::post('/research/{id}/start-review', [SpecializationResearchController::class, 'startReview']);
        Route::get('/research/{id}', [SpecializationResearchController::class, 'show']);

        // Students (for login-as)
        Route::get('/students', [SpecializationResearchController::class, 'students'])->name('students');
    });
});
