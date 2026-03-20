<div class="shadow-[0_0_0_1px_rgba(74,222,128,0.25)]
            min-h-screen bg-gray-950 text-white
            px-4 pt-4 pb-28 rounded-xl">
	<div class="pt-6">
		<x-poof.profile.header :user="$user" />
		<x-poof.profile.info-card :user="$user" />
		{{-- 🏠 Address Manager --}}
		<livewire:client.address-manager :user="$user" />
		<div class="mt-8">
			<form method="POST" action="{{ route('logout') }}">
				@csrf
				<x-poof.ui.button variant="danger">
					Вийти з акаунту
				</x-poof.ui.button>
			</form>
		</div>
	</div>
	{{-- 🔽 ВСЕ sheets — в отдельном слоте --}}
	<x-slot:sheets>
		<x-poof.ui.bottom-sheet name="editProfile" title="Редагувати профіль">
			<livewire:client.profile-form />
		</x-poof.ui.bottom-sheet>
		<template x-teleport="body">
			<x-poof.ui.bottom-sheet
				name="addressForm"
				title="Адреса"
				:hide-header="true"
				body-class="overflow-visible px-0 pb-0"
				panel-class="overflow-hidden bg-transparent"
				actions-class="border-t border-neutral-800 bg-neutral-900/95 p-4 backdrop-blur"
			>
				<livewire:client.address-form wire:key="address-form" />

				<x-slot:actions>
					<button
						type="submit"
						form="address-form"
						class="w-full bg-yellow-400 text-black font-semibold py-3 rounded-xl active:scale-95 transition"
					>
						Зберегти адресу
					</button>
				</x-slot:actions>
			</x-poof.ui.bottom-sheet>
		</template>
		<x-poof.ui.bottom-sheet name="editAvatar" title="Аватар">
			<livewire:client.avatar-form />
		</x-poof.ui.bottom-sheet>
	</x-slot:sheets>
	</div>
