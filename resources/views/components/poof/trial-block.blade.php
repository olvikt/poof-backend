@props([
    'isTrial',
    'trialDays',
    'trialUsed',
])

<div wire:click="selectTrial(1)" class="mb-5">
    <x-poof.trial-option
        marker="trial-promo-card"
        title="1 день безкоштовно"
        subtitle="Перший винос за рахунок сервісу"
        :active="$isTrial && $trialDays === 1"
        :disabled="$trialUsed"
        :used="$trialUsed"
        :trailing="true"
        icon="gift"
    />
</div>

@if($isTrial)
    <button
        type="button"
        wire:click="disableTrial"
        class="mt-1 text-xs text-gray-400 underline"
    >
        ❌ Відмовитись від тесту
    </button>
@endif
