<div class="px-4 pt-6 pb-24 max-w-md mx-auto">

    <x-poof.profile.header />

    <x-poof.profile.info-card />

    <x-poof.profile.address-card />

    <div class="mt-8">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-poof.ui.button variant="danger">
                Вийти з акаунту
            </x-poof.ui.button>
        </form>
    </div>

    {{-- Sheets --}}
	<x-poof.ui.bottom-sheet name="editProfile" title="Редагувати профіль">
		<livewire:client.profile-form />
	</x-poof.ui.bottom-sheet>

	<x-poof.ui.bottom-sheet name="editAddress" title="Адреса">
		<livewire:client.address-form />
	</x-poof.ui.bottom-sheet>

	<x-poof.ui.bottom-sheet name="editAvatar" title="Аватар">
		<livewire:client.avatar-form />
	</x-poof.ui.bottom-sheet>

</div>

