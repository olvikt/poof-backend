@props([
    'isTrial',
    'trialDays',
    'trialUsed',
])

<x-poof.section title="Спецпропозиції">
    <div wire:click="selectTrial(1)">
        <x-poof.trial-option
            title="1 день безкоштовно"
            subtitle="Перший винос за рахунок сервісу"
            :active="$isTrial && $trialDays === 1"
            :disabled="$trialUsed"
            :used="$trialUsed"
        />
    </div>

    @if($isTrial)
        <button
            type="button"
            wire:click="disableTrial"
            class="mt-3 text-xs text-gray-400 underline"
        >
            ❌ Відмовитись від тесту
        </button>
    @endif
</x-poof.section>
