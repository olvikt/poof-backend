@props([
    'entrypoint' => 'client',
])

@php
    $isCourier = $entrypoint === 'courier';
    $logoSrc = $isCourier ? '/assets/icons/courier-icon-192.png' : '/images/logo-poof.png';
    $logoAlt = $isCourier ? 'POOF Courier' : 'POOF';
@endphp

<div class="flex justify-center pt-6 mb-8">
    <img
        src="{{ $logoSrc }}"
        alt="{{ $logoAlt }}"
        class="w-24 h-24 object-contain"
    />
</div>
