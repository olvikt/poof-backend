<div style="max-width:600px;margin:0 auto;padding:20px">

    <h2>Оформити замовлення</h2>

    {{-- АДРЕС --}}
	{{-- MAP --}}
<div style="margin-bottom:15px">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <label><strong>Місце забору</strong></label>

        <button type="button"
                id="use-location-btn"
                style="
                    background:#FFD400;
                    color:#000;
                    border:none;
                    padding:6px 10px;
                    cursor:pointer;
                ">
            📍 Використати мою локацію
        </button>
    </div>

<div
    wire:ignore
    id="map"
    style="
        margin-top:8px;
        height:260px;
        border:2px solid #FFD400;
    ">
</div>

    <div style="font-size:12px;color:#666;margin-top:4px">
        Ви можете клікнути по карті або ввести адресу вручну
    </div>
</div>
    <div style="margin-bottom:10px">
        <label>Адреса</label><br>
        <input type="text"
               wire:model.defer="address_text"
               placeholder="Улица и дом"
               style="width:100%">
        @error('address_text') <div style="color:red">{{ $message }}</div> @enderror
    </div>

    {{-- ДЕТАЛИ АДРЕСА --}}
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:5px;margin-bottom:10px">
        <input wire:model.defer="entrance" placeholder="Підъезд">
        <input wire:model.defer="floor" placeholder="Этаж">
        <input wire:model.defer="apartment" placeholder="Кв / офис">
        <input wire:model.defer="intercom" placeholder="Домофон">
    </div>

    {{-- КОММЕНТАРИЙ --}}
    <div style="margin-bottom:10px">
        <textarea wire:model.defer="comment"
                  placeholder="Комментар (наприлад, № перепустка)"
                  style="width:100%"></textarea>
    </div>

    {{-- ДАТА --}}
    <div style="margin-bottom:10px">
        <label>Дата</label><br>
        <input type="date" wire:model="scheduled_date">
        @error('scheduled_date') <div style="color:red">{{ $message }}</div> @enderror
    </div>

    {{-- ВРЕМЯ --}}
    <div style="margin-bottom:10px">
        <label>Время</label><br>
        @foreach($timeSlots as [$from, $to])
            <button type="button"
                    wire:click="selectTimeSlot('{{ $from }}','{{ $to }}')"
                    style="margin:2px;
                    {{ $scheduled_time_from === $from ? 'font-weight:bold;background:#ddd' : '' }}">
                {{ $from }} – {{ $to }}
            </button>
        @endforeach
        @error('scheduled_time_from') <div style="color:red">{{ $message }}</div> @enderror
    </div>

    {{-- СПОСОБ ПЕРЕДАЧИ --}}
    <div style="margin-bottom:10px">
        <label>Как передати мусор?</label><br>
        <label>
            <input type="radio" wire:model="handover_type" value="door">
            Виставлю за дверима
        </label>
        <label style="margin-left:10px">
            <input type="radio" wire:model="handover_type" value="hand">
            Передам у руки
        </label>
    </div>

    {{-- МЕШКИ --}}
    <div style="margin-bottom:10px">
        <label>Кількість мішків</label><br>

        @foreach($pricing as $count => $bagPrice)
            <button type="button"
                    wire:click="selectBags({{ $count }})"
                    style="
                        margin:2px;
                        padding:4px 10px;
                        {{ $bags_count === $count && ! $is_trial ? 'font-weight:bold;background:#ddd' : '' }}
                    ">
                {{ $count }} ({{ $bagPrice }} ₴)
            </button>
        @endforeach

        <div style="font-size:12px;color:#666">До 6 кг у мішку</div>
    </div>

    {{-- ПРОМОКОД --}}
    <div style="margin-bottom:10px">
        <label>Промокод</label><br>
        <input type="text"
               wire:model.defer="promo_code"
               placeholder="Введите промокод"
               style="width:100%">
    </div>

    {{-- ТЕСТОВЫЙ ВЫНОС --}}
    <div style="margin-bottom:15px;border-top:1px solid #ddd;padding-top:10px">
        <label><strong>Пробний винос (0 грн)</strong></label><br>

        <button type="button"
                wire:click="selectTrial(1)"
                style="margin:2px;
                {{ $is_trial && $trial_days === 1 ? 'font-weight:bold;background:#c8f7c5' : '' }}">
            1 день безкоштовно
        </button>

        <button type="button"
                wire:click="selectTrial(3)"
                style="margin:2px;
                {{ $is_trial && $trial_days === 3 ? 'font-weight:bold;background:#c8f7c5' : '' }}">
            3 дня безкоштовно
        </button>

        @if($is_trial)
            <div style="margin-top:5px">
                <button type="button"
                        wire:click="disableTrial"
                        style="font-size:12px">
                    ❌ Відхилити тест
                </button>
            </div>
        @endif
    </div>

    {{-- ИТОГ --}}
    <div style="margin-bottom:15px">
	@if (session()->has('error'))
    <div style="background:#fdecea;color:#b71c1c;padding:10px;margin-bottom:10px">
        {{ session('error') }}
    </div>
@endif
	
        <strong>
            До оплати:
            {{ $price }} ₴
            @if($is_trial)
                <span style="color:green">(тестовий період)</span>
            @endif
        </strong>
    </div>

    {{-- SUBMIT --}}
<button type="button"
        wire:click="submit"
        wire:loading.attr="disabled"
        style="padding:10px 20px;opacity:1"
>
    <span wire:loading.remove>
        {{ $is_trial ? 'Оформити тестовий винос' : 'Зробити замовлення' }}
    </span>

    <span wire:loading>
        ⏳ Обробка…
    </span>
</button>


@if($showPaymentModal)
<div style="position:fixed;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <div style="background:#fff;padding:18px;max-width:420px;width:92%;border-radius:12px;">
        <div style="font-size:18px;font-weight:700;margin-bottom:8px;">✅ Ваше замовлення прийнято</div>

        <div style="margin-bottom:12px;color:#333;">
            Після оплати ми підберемо курʼєра для виконання замовлення.
        </div>

        <div style="font-size:13px;color:#444;line-height:1.45;margin-bottom:14px;">
            🕒 Курʼєр зазвичай знаходиться протягом 5–15 хвилин<br>
            🛡 Оплата безпечна, замовлення можна скасувати<br>
            🔄 Оплатити можна пізніше в історії замовлень
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
            <a href="{{ route('client.orders') }}"
               style="padding:10px 12px;border:1px solid #ddd;border-radius:10px;text-decoration:none;color:#111;">
                Оплатити пізніше
            </a>

            <a href="{{ url('/client/orders') }}"
               style="padding:10px 12px;background:#FFD400;color:#000;border-radius:10px;text-decoration:none;font-weight:700;">
                Оплатити зараз {{ $price }} грн
            </a>
        </div>
    </div>
</div>
@endif
</div>





<script>
document.addEventListener('DOMContentLoaded', function () {

    // ⛑ защита
    if (typeof L === 'undefined') {
        console.error('Leaflet не загружен');
        return;
    }

    const mapElement = document.getElementById('map');
    if (! mapElement) return;

    const defaultLat = 50.4501; // Київ
    const defaultLng = 30.5234;

    const map = L.map('map').setView([defaultLat, defaultLng], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    let marker = null;

    function setMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);

            marker.on('dragend', function (e) {
                const pos = e.target.getLatLng();
                updateLocation(pos.lat, pos.lng);
            });
        }

        map.setView([lat, lng], 16);
        updateLocation(lat, lng);
    }

	async function reverseGeocode(lat, lng) {
		try {
			const res = await fetch(
				`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`,
				{
					headers: {
						'Accept': 'application/json',
					}
				}
			);

			const data = await res.json();

			if (!data.address) return '';

			const road = data.address.road ?? '';
			const house = data.address.house_number ?? '';

			return [road, house].filter(Boolean).join(' ');
		} catch (e) {
			console.error('Reverse geocode error', e);
			return '';
		}
	}

	async function updateLocation(lat, lng) {
		let address = await reverseGeocode(lat, lng);

		if (window.Livewire) {
			Livewire.dispatch('set-location', {
				lat: lat,
				lng: lng,
				address: address,
			});
		}
	}

    map.on('click', function (e) {
        setMarker(e.latlng.lat, e.latlng.lng);
    });

    const btn = document.getElementById('use-location-btn');
    if (btn) {
        btn.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('Геолокація не підтримується');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    setMarker(
                        pos.coords.latitude,
                        pos.coords.longitude
                    );
                },
                () => {
                    alert('Не вдалося отримати локацію');
                }
            );
        });
    }

    // ⛑ ФИКС: заставляем Leaflet пересчитать размеры
    setTimeout(() => {
        map.invalidateSize();
    }, 200);

});
</script>

