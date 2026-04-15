<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'sso' => $user->sso,
            'roles' => $user->getRoleNames(),
        ];

        if ($user->staff) {
            $staff = $user->staff;
            $profile['staff'] = [
                'code' => $staff->code,
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'program_id' => $staff->program_id,
                'program_name' => $staff->program?->name ?? null,
                'position' => $staff->position?->name ?? null,
                'specialization' => $staff->specialization?->name ?? null,
            ];
        }

        if ($user->student) {
            $student = $user->student;
            $profile['student'] = [
                'number' => $student->number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'program_id' => $student->program_id,
                'program_name' => $student->program?->name ?? null,
                'specialization' => $student->specialization?->name ?? null,
            ];
        }

        return response()->json(['data' => $profile]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($validatedData);

        return response()->json(['message' => 'Profile updated successfully', 'data' => $user]);
    }
}
