<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Mary\Traits\Toast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts.guest')] #[Title('Login')] class extends Component
{
    use Toast;

    public string $email = '';
    public string $password = '';

    public function redirectToSso(): mixed
    {
        return redirect()->route('auth.cas.redirect');
    }

    protected $rules = [
        'email'    => 'required|email',
        'password' => 'required|min:6',
    ];

    public function login()
    {
        $this->validate();

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            return redirect('/');
        }

        $this->error('Invalid email or password.', position: 'toast-top toast-center');
    }
};
?>

<div class="flex min-h-screen items-center justify-center bg-base-200 px-4">
    <div class="w-full max-w-sm">

        {{-- Logo & App Name --}}
        <div class="mb-8 text-center">
            <div class="mb-3 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-primary text-primary-content shadow-lg">
                <x-icon name="o-academic-cap" class="h-9 w-9" />
            </div>
            <h1 class="text-2xl font-bold text-base-content">ArSys</h1>
            <p class="mt-1 text-sm text-base-content/60">Advanced Research Support System</p>
        </div>

        <x-card shadow class="rounded-2xl">

            {{-- SSO UPI (Primary) --}}
            <x-button
                label="Login via SSO UPI"
                icon="o-identification"
                class="btn-primary w-full"
                wire:click="redirectToSso"
                spinner="redirectToSso"
            />

            <div class="divider my-4 text-xs text-base-content/40">or with a local account</div>

            {{-- Local login form --}}
            <x-form wire:submit="login">
                <x-input
                    label="Email"
                    wire:model="email"
                    type="email"
                    placeholder="email@upi.edu"
                    icon="o-envelope"
                />
                <x-password
                    label="Password"
                    wire:model="password"
                    icon="o-key"
                    right
                />
                <x-button
                    label="Login"
                    type="submit"
                    class="btn-outline w-full"
                    spinner="login"
                />
            </x-form>

        </x-card>

        <p class="mt-6 text-center text-xs text-base-content/40">
            Universitas Pendidikan Indonesia &copy; {{ date('Y') }}
        </p>

    </div>
</div>