<div class="flex items-center gap-4 mb-6">
    <x-poof.profile.avatar />

    <div>
        <h1 class="text-xl font-black text-white">
            {{ auth()->user()->name ?? 'Профіль' }}
        </h1>
        <p class="text-gray-400 text-sm">
            Керування обліковим записом
        </p>
    </div>
</div>
