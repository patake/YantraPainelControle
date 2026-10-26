<flux:dropdown position="top" align="start" {{ $attributes }}>
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <x-user-menu-items />
</flux:dropdown>
