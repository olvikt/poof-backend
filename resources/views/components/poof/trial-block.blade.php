@props([
    'isTrial',
    'trialDays',
    'trialUsed',
])

<x-poof.section title="Пробний винос">
    <div class="grid grid-cols-2 gap-4 w-full">
        <div wire:click="selectTrial(1)">
            <x-poof.trial-option
                :days="1"
                :active="$isTrial && $trialDays === 1"
                :disabled="$trialUsed"
            />
        </div>

        <div wire:click="selectTrial(3)">
            <x-poof.trial-option
                :days="3"
                :active="$isTrial && $trialDays === 3"
                :disabled="$trialUsed"
            />
        </div>
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