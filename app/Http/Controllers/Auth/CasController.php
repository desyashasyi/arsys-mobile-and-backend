<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ArSys\Staff;
use App\Models\ArSys\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class CasController extends Controller
{
    /**
     * Redirect user to CAS login page.
     */
    public function redirect()
    {
        $serviceUrl = url('/auth/cas/callback');
        $casLoginUrl = sprintf(
            'https://%s%s/login?service=%s',
            config('cas.hostname'),
            config('cas.uri'),
            urlencode($serviceUrl)
        );

        return redirect($casLoginUrl);
    }

    /**
     * Handle CAS callback after successful authentication.
     */
    public function callback(Request $request)
    {
        $ticket = $request->query('ticket');

        if (!$ticket) {
            return redirect()->route('login')->withErrors(['sso' => 'Tidak ada tiket dari SSO. Silakan coba lagi.']);
        }

        $ssoId = $this->validateTicket($ticket);

        if (!$ssoId) {
            return redirect()->route('login')->withErrors(['sso' => 'Validasi SSO gagal. Silakan coba lagi.']);
        }

        $user = $this->findOrCreateUser($ssoId);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return $this->redirectAfterLogin($user);
    }

    /**
     * Validate CAS ticket and return the SSO user ID.
     */
    private function validateTicket(string $ticket): ?string
    {
        $serviceUrl = url('/auth/cas/callback');
        $validateUrl = sprintf(
            'https://%s%s/serviceValidate?service=%s&ticket=%s',
            config('cas.hostname'),
            config('cas.uri'),
            urlencode($serviceUrl),
            urlencode($ticket)
        );

        try {
            $response = Http::withOptions(['verify' => false])->get($validateUrl);

            if (!$response->successful()) {
                return null;
            }

            if (preg_match('/<cas:user>(.+?)<\/cas:user>/s', $response->body(), $matches)) {
                return trim($matches[1]);
            }

            return null;
        } catch (\Exception $e) {
            logger()->error('CAS ticket validation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Find existing user or create a new one based on SSO ID.
     *
     * SSO ID convention (from UPI):
     *  - length > 7  → Staff (NIP, e.g. "197608272009121001")
     *  - length <= 7 → Student (NIM, e.g. "2001234")
     */
    private function findOrCreateUser(string $ssoId): User
    {
        $user = User::where('sso', $ssoId)->first();

        if ($user) {
            return $user;
        }

        // Determine role by SSO ID length
        if (strlen($ssoId) > 7) {
            // Staff
            $user = User::create(['sso' => $ssoId, 'name' => $ssoId]);
            $user->assignRole('staff');

            // Link to existing staff record if exists
            $staff = Staff::where('sso', $ssoId)->first();
            if ($staff && !$staff->user_id) {
                $staff->update(['user_id' => $user->id]);
                $fullName = trim(($staff->front_title ? $staff->front_title . ' ' : '') . $staff->first_name . ' ' . $staff->last_name);
                $user->update(['name' => $fullName ?: $ssoId]);
            }
        } else {
            // Student
            $user = User::create(['sso' => $ssoId, 'name' => 's' . $ssoId]);
            $user->assignRole('student');

            // Link to existing student record by code (NIM)
            $student = Student::where('code', $ssoId)->first();
            if ($student && !$student->user_id) {
                $student->update(['user_id' => $user->id]);
                $fullName = trim($student->first_name . ' ' . $student->last_name);
                $user->update(['name' => $fullName ?: ('s' . $ssoId)]);
            }
        }

        return $user;
    }

    /**
     * Redirect user to the appropriate page based on their role.
     */
    private function redirectAfterLogin(User $user)
    {
        return redirect('/');
    }

    /**
     * Logout from both local session and CAS.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $casLogoutUrl = config('cas.logout_url');
        $redirectAfter = urlencode(route('login'));

        return redirect("{$casLogoutUrl}?service={$redirectAfter}");
    }
}
