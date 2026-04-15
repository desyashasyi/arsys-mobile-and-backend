<x-menu activate-by-route>

    {{-- User --}}
    @if($user = auth()->user())
        <x-menu-separator />
        <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 !-my-2 rounded">
            <x-slot:actions>
                <x-button icon="o-power" class="btn-circle btn-ghost btn-xs" tooltip-left="logoff" no-wire-navigate link="/logout" />
            </x-slot:actions>
        </x-list-item>
    @endif

    {{-- SUPER ADMIN --}}
    @role('super_admin')
        <x-menu-separator title="Super Admin" />
        <x-menu-sub title="User Management" icon="o-users">
            <x-menu-item title="Staff" icon="o-identification" link="{{ route('super-admin.staff.web') }}" />
        </x-menu-sub>
    @endrole

    {{--
        Tambahkan item menu di sini hanya jika halaman sudah menggunakan layouts.app.
        Halaman mobile (layouts.mobile-app) tidak masuk sidebar ini.
    --}}

</x-menu>
