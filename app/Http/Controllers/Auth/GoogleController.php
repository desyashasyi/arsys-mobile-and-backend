<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    // === Web routes (existing) ===

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $user = $this->findOrCreateUser(
                $googleUser->getId(),
                $googleUser->getEmail(),
                $googleUser->getName(),
            );

            Auth::login($user);
            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            Log::error('Google Login Error: ' . $e->getMessage());
            return redirect('/login')->with('error', 'Something went wrong or you have rejected the app.');
        }
    }

    // === API route (mobile) ===

    /**
     * Handle Google Sign-In from mobile app.
     * Receives a Google ID token, verifies it via Google API, and returns a Sanctum token.
     */
    public function loginWithIdToken(Request $request)
    {
        $request->validate([
            'id_token' => 'nullable|string',
            'access_token' => 'nullable|string',
        ]);

        if (!$request->id_token && !$request->access_token) {
            return response()->json([
                'message' => 'Either id_token or access_token is required.',
            ], 422);
        }

        $email = null;
        $googleId = null;
        $name = null;

        if ($request->id_token) {
            // Verify ID token with Google (mobile/Android)
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $request->id_token,
            ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Invalid Google ID token.',
                ], 401);
            }

            $payload = $response->json();

            // Validate audience matches our client ID
            $expectedClientId = config('services.google.client_id');
            if (($payload['aud'] ?? null) !== $expectedClientId) {
                return response()->json(['message' => 'Invalid Google token audience.'], 401);
            }

            $email = $payload['email'] ?? null;
            $googleId = $payload['sub'] ?? null;
            $name = $payload['name'] ?? $email;
        } else {
            // Verify access token with Google (web)
            $response = Http::get('https://www.googleapis.com/oauth2/v3/userinfo', [
                'access_token' => $request->access_token,
            ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Invalid Google access token.',
                ], 401);
            }

            $payload = $response->json();
            $email = $payload['email'] ?? null;
            $googleId = $payload['sub'] ?? null;
            $name = $payload['name'] ?? $email;
        }

        if (!$email || !$googleId) {
            return response()->json([
                'message' => 'Could not retrieve user information from Google.',
            ], 401);
        }

        $user = $this->findOrCreateUser($googleId, $email, $name);

        $token = $user->createToken('auth_token')->plainTextToken;

        $userData = $user->toArray();
        if ($user->staff) {
            $userData['staff_id'] = $user->staff->id;
            $userData['program_id'] = $user->staff->program_id;
        }
        if ($user->student) {
            $userData['student_id'] = $user->student->id;
            $userData['program_id'] = $user->student->program_id;
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $userData,
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * Find existing user by google_id or email, or create a new one.
     */
    private function findOrCreateUser(string $googleId, string $email, string $name): User
    {
        $user = User::where('google_id', $googleId)->first();
        if ($user) {
            return $user;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->update(['google_id' => $googleId]);
            return $user;
        }

        return User::create([
            'name' => $name,
            'email' => $email,
            'google_id' => $googleId,
            'password' => Hash::make(uniqid()),
        ]);
    }
}
