<div>
<div
    id="order-create-root"
    class="bg-gray-950 rounded-2xl max-w-md mx-auto mt-8 px-4 py-6 pb-[calc(12rem+env(safe-area-inset-bottom))] text-white">
    {{-- TITLE --}}
    <h1 class="text-xl font-extrabold mb-4">
        🧹 Оформити замовлення
    </h1>
    <div class="mb-5">
       {{-- ================= MAP ================= --}}  	
	    <x-poof.map :lat="$lat" :lng="$lng">
			Місце забору
		</x-poof.map>

		{{-- ================= ADDRESS ================= --}}
		<div class="mt-4 mb-4">
			<x-poof.section title="Адреса">
				<div class="flex items-center justify-between mb-2">
					<span class="text-xs text-gray-400">
						Вкажіть адресу забору
					</span>

					<button
						type="button"
						x-data
						@click="$dispatch('sheet:open', { name: 'addressPicker' })"
						data-e2e="open-address-picker"
						class="text-xs text-yellow-400 font-semibold hover:opacity-80 transition"
					>
						Обрати збережену
					</button>
				</div>

				{{-- Street + House --}}
				<div class="flex gap-2">
					{{-- Вулиця --}}
					<div class="flex-1 min-w-0">
						<x-poof.input-floating
							label="Вулиця"
							model="street"
							live
						/>
					</div>

					{{-- Будинок --}}
					<div class="w-24 shrink-0">
						<x-poof.input-floating
							label="Дім"
							model="house"
							center
							live
						/>
					</div>
				</div>

				@error('address_text')
					<div class="text-red-400 text-xs mt-1">{{ $message }}</div>
				@enderror

				<p class="text-xs text-gray-500 mt-2">
					Оберіть збережену адресу або натисніть на мапу, щоб поставити точку.
				</p>
			</x-poof.section>
		</div>

		{{-- DETAILS --}}
		<div class="mb-4" data-e2e="order-address-details" x-data x-on:order-address-details-invalid.window="$el.scrollIntoView({ behavior: 'smooth', block: 'start' }); var detail = $event.detail || {}; var field = detail.field || null; var target = field ? $el.querySelector('[data-address-detail-field=' + field + '] input') : null; if (!target) { target = $el.querySelector('[data-address-detail-field] input'); } if (target) { target.focus(); }">
			<div class="building-type-panel flex items-center justify-between gap-3 rounded-[1.15rem] px-1 py-1.5 mb-3">
				<div class="min-w-0 flex-1">
					<p class="text-sm font-semibold leading-5 text-white">Приватний будинок</p>
					<p class="mt-0.5 text-xs leading-4 text-neutral-400">Увімкніть якщо будинок приватний.</p>
				</div>

				<button
					type="button"
					role="switch"
					aria-label="Приватний будинок"
					aria-checked="{{ $is_private_house ? 'true' : 'false' }}"
					wire:click.prevent="$toggle('is_private_house')"
					class="building-type-switch relative inline-flex h-8 w-14 shrink-0 items-center rounded-full border transition focus:outline-none focus:ring-2 focus:ring-yellow-300/60 {{ $is_private_house ? 'border-yellow-300 bg-yellow-400' : 'border-neutral-600 bg-neutral-700' }}"
				>
					<span class="sr-only">Приватний будинок</span>
					<span class="building-type-switch-thumb pointer-events-none inline-block h-6 w-6 rounded-full bg-white shadow-md transition-transform {{ $is_private_house ? 'translate-x-7' : 'translate-x-1' }}"></span>
				</button>
			</div>

			@if ($errors->has('entrance') || $errors->has('floor') || $errors->has('apartment'))
				<div class="sticky top-2 z-10 rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-red-300 text-xs mb-2" data-e2e="order-address-details-error">Перевірте обов'язкові поля деталізації адреси.</div>
			@endif

			@if($is_private_house)
				<p class="text-xs text-neutral-400">Для приватного будинку підʼїзд, поверх, квартира та домофон не потрібні.</p>
			@else
				<div class="flex gap-2">
					<div data-address-detail-field="entrance"><x-poof.input-floating label="Підʼїзд *" model="entrance" center />@error('entrance')<div class="text-red-400 text-xs mt-1">{{ $message }}</div>@enderror</div>
					<div data-address-detail-field="floor"><x-poof.input-floating label="Поверх *" model="floor" center />@error('floor')<div class="text-red-400 text-xs mt-1">{{ $message }}</div>@enderror</div>
					<div data-address-detail-field="apartment"><x-poof.input-floating label="Квартира *" model="apartment" center />@error('apartment')<div class="text-red-400 text-xs mt-1">{{ $message }}</div>@enderror</div>
					<div data-address-detail-field="intercom"><x-poof.input-floating label="Домофон" model="intercom" center />@error('intercom')<div class="text-red-400 text-xs mt-1">{{ $message }}</div>@enderror</div>
				</div>
			@endif
		</div>

		{{-- COMMENT --}}
		<textarea
			wire:model.defer="comment"
			rows="3"
			placeholder="Коментар (Домофон, охорона, примітки)"
			class="w-full mb-4 poof-input resize-none"
		></textarea>

		{{-- ================= DATE ================= --}}
		<div class="mb-5">
			<x-poof.section title="Режим виконання">
				<div class="grid grid-cols-2 gap-2">
					<button
						type="button"
						wire:click="$set('service_mode', '{{ \App\Models\Order::SERVICE_MODE_ASAP }}')"
						class="rounded-xl border px-3 py-2 text-sm font-semibold {{ $service_mode === \App\Models\Order::SERVICE_MODE_ASAP ? 'border-yellow-400 bg-yellow-400 text-black' : 'border-gray-700 bg-neutral-800 text-gray-200' }}"
					>
						Якнайшвидше
					</button>
					<button
						type="button"
						wire:click="$set('service_mode', '{{ \App\Models\Order::SERVICE_MODE_PREFERRED_WINDOW }}')"
						class="rounded-xl border px-3 py-2 text-sm font-semibold {{ $service_mode === \App\Models\Order::SERVICE_MODE_PREFERRED_WINDOW ? 'border-yellow-400 bg-yellow-400 text-black' : 'border-gray-700 bg-neutral-800 text-gray-200' }}"
					>
						Бажаний інтервал
					</button>
				</div>
				@if($service_mode === \App\Models\Order::SERVICE_MODE_PREFERRED_WINDOW)
					<p class="mt-2 text-xs text-gray-400">Бажаний інтервал — це пріоритетний час, але не абсолютна гарантія.</p>
				@endif
			</x-poof.section>
		</div>

		<div class="mb-6">
			<label class="text-sm text-gray-400 mb-2.5 block">Дата</label>

			<div
				x-data="{
					today: '{{ now()->toDateString() }}',
					tomorrow: '{{ now()->addDay()->toDateString() }}',
					selected: @js($scheduled_date),

					setDate(date) {
						this.selected = date
						$wire.set('scheduled_date', date)
					},

					isActive(date) {
						return this.selected === date
					},

					isCustom() {
						return this.selected
							&& this.selected !== this.today
							&& this.selected !== this.tomorrow
					},

					openPicker() {
						this.$refs.dateInput.showPicker?.()
						this.$refs.dateInput.click()
					},

					onPicked(e) {
						const val = e.target.value
						if (!val) return
						this.setDate(val)
					}
				}"
				class="grid grid-cols-3 gap-3"
			>
				{{-- Сьогодні --}}
				<button
					type="button"
					@click="setDate(today)"
					:class="isActive(today)
						? 'bg-gradient-to-b from-yellow-300 to-yellow-400 text-black shadow-lg'
						: 'bg-neutral-800 text-gray-200 border border-gray-700 shadow-sm'"
					class="py-2 rounded-2xl text-sm font-semibold transition-all duration-150 active:scale-95"
				>
					Сьогодні
				</button>

				{{-- Завтра --}}
				<button
					type="button"
					data-e2e="scheduled-date-tomorrow"
					@click="setDate(tomorrow)"
					:class="isActive(tomorrow)
						? 'bg-gradient-to-b from-yellow-300 to-yellow-400 text-black shadow-lg'
						: 'bg-neutral-800 text-gray-200 border border-gray-700 shadow-sm'"
					class="py-2 rounded-2xl text-sm font-semibold transition-all duration-150 active:scale-95"
				>
					Завтра
				</button>

				{{-- Інша дата --}}
				<button
					type="button"
					@click="openPicker()"
					:class="isCustom()
						? 'bg-gradient-to-b from-yellow-300 to-yellow-400 text-black shadow-lg'
						: 'bg-neutral-800 text-gray-200 border border-gray-700 shadow-sm'"
					class="py-2 rounded-2xl text-sm font-semibold transition-all duration-150 active:scale-95"
				>
					<template x-if="isCustom()">
						<span x-text="selected.split('-').slice(1).reverse().join('.')"></span>
					</template>

					<template x-if="!isCustom()">
						<span>Інша дата</span>
					</template>
				</button>

				{{-- hidden native picker --}}
				<input
					x-ref="dateInput"
					type="date"
					class="hidden"
					:min="today"
					@change="onPicked($event)"
				>
			</div>
		</div>

			{{-- Carousel --}}
			<div
				class="mb-8"
			x-ref="timeBlock"
			x-data="poofTimeCarousel({
				slots: {{ Js::from($timeSlots) }},
				model: @entangle('timeSlot'),
				scheduledDate: @entangle('scheduled_date'),
				today: '{{ now()->toDateString() }}',
				tomorrow: '{{ now()->addDay()->toDateString() }}'
			})"
		>
			<label class="text-sm text-gray-400 mb-2.5 block">Час</label>

			<div class="flex items-center justify-between mb-3">
				<span class="text-sm text-gray-300">Обраний інтервал</span>
				<span
					class="text-sm font-bold text-yellow-400"
					x-text="i !== null ? label() : '—'"
				></span>
			</div>

			<div class="relative">
				<div
					x-ref="track"
					class="flex gap-3 overflow-x-auto no-scrollbar snap-x snap-mandatory pb-2"
				>
					<template x-for="(slot, idx) in slots" :key="idx">
						<x-poof.time-slot
							@click="select(idx)"
							x-bind:disabled="!isAvailable(slot)"
							x-bind:class="{
								'bg-yellow-400 text-black shadow-lg scale-105': idx === i,
								'bg-neutral-800 text-white border border-gray-700': idx !== i && isAvailable(slot),
								'bg-neutral-800 text-gray-500 border border-gray-700 opacity-50': !isAvailable(slot)
							}"
						>
							<div class="text-base font-bold">
								<span x-text="slot.from"></span>–<span x-text="slot.to"></span>
							</div>
						</x-poof.time-slot>
					</template>
				</div>
			</div>

			{{-- No slots today --}}
			<template x-if="noSlotsToday">
				<div class="mt-4 text-center">
					<p class="text-sm text-gray-400 mb-3">
						На сьогодні вільних слотів немає
					</p>
					<button
						type="button"
						@click="pickTomorrow()"
						class="px-4 py-2 rounded-xl bg-yellow-400 text-black font-semibold"
					>
						Запланувати на завтра
					</button>
				</div>
				</template>
			</div>

				<div class="mb-5">
					<x-poof.section title="Як діяти, якщо курʼєра не знайдено">
						<div
							data-e2e="courier-not-found-hint"
							class="rounded-2xl border border-yellow-400/30 bg-yellow-400/5 p-3 sm:p-4"
						>
							<div class="mb-3 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-yellow-200/90">
								<span aria-hidden="true" class="inline-block h-2 w-2 rounded-full bg-yellow-300"></span>
								<span>Підказка</span>
							</div>
							<div class="space-y-2">
								<label class="flex items-start gap-2 text-sm text-gray-200">
								<input type="radio" class="accent-yellow-400" wire:model.live="client_wait_preference" value="{{ \App\Models\Order::WAIT_ALLOW_LATE_FULFILLMENT }}">
									<span>Чекати довше, якщо курʼєра не знайдено в бажаний час</span>
								</label>
								<label class="flex items-start gap-2 text-sm text-gray-200">
								<input type="radio" class="accent-yellow-400" wire:model.live="client_wait_preference" value="{{ \App\Models\Order::WAIT_AUTO_CANCEL_IF_NOT_FOUND }}">
									<span>Скасувати замовлення та повернути кошти, якщо курʼєра не буде знайдено вчасно</span>
								</label>
							</div>
							<label class="mt-3 flex items-start gap-2 text-xs text-gray-400">
								<input type="checkbox" class="mt-0.5 accent-yellow-400" wire:model="promise_consent">
								<span>Підтверджую, що ознайомився(лася) з умовами авто-скасування та можливого зсуву часу виконання.</span>
							</label>
						</div>
						@error('promise_consent')
							<div class="text-red-400 text-xs mt-1">{{ $message }}</div>
						@enderror
					</x-poof.section>
				</div>


	{{-- ================= DIVIDER ================= --}}
	<div class="my-8">
		<div class="
			h-1 w-full
			rounded-full
			bg-neutral-900
			shadow-[inset_0_2px_3px_rgba(0,0,0,0.8)]
		">
		</div>
	</div>

	{{-- ================= ORDER TYPE ================= --}}
	<div class="mb-6 md:mb-6">
		<x-poof.section title="Тип замовлення">
			<div class="grid grid-cols-2 gap-3 w-full">
				<div wire:click="selectRegularOrder">
					<x-poof.trial-option
						marker="order-type-regular"
						title="Разовий винос"
						badge="Разово"
						badge-class="bg-yellow-200 text-yellow-950 text-[10px] px-2 py-[2px]"
						icon="one-time"
						:active="$selected_subscription_plan_id === null"
						:compact="true"
						:hide-subtitle="true"
						container-class="h-[74px]"
						active-class="border-yellow-400 ring-1 ring-yellow-400/30 bg-neutral-900/90 text-white"
					/>
				</div>

				<div wire:click="openSubscriptionModal">
					<x-poof.trial-option
						marker="order-type-subscription"
						title="Підписка на місяць"
						badge="До 20% вигоди"
						badge-class="bg-emerald-500 text-emerald-950 border border-emerald-300 shadow-sm shadow-emerald-500/20 text-[10px] px-2 py-[2px]"
						icon="calendar"
						:active="$selected_subscription_plan_id !== null"
						:compact="true"
						:hide-subtitle="true"
						container-class="h-[74px]"
						active-class="border-yellow-400 ring-1 ring-yellow-400/30 bg-neutral-900/90 text-white"
					/>
				</div>
			</div>
		</x-poof.section>
	</div>

	{{-- ================= HANDOVER ================= --}}
	<div class="mb-6 md:mb-6">
		<x-poof.section title="Передача">
			<div class="grid grid-cols-2 gap-3">
				<x-poof.choice-card wire:model="handover_type" model="handover_type" value="door" :current="$handover_type" title="За дверима" subtitle="Без контакту" />
				<x-poof.choice-card wire:model="handover_type" model="handover_type" value="hand" :current="$handover_type" title="В руки" subtitle="Особисто" />
			</div>
		</x-poof.section>
	</div>

	{{-- ================= TRIAL ================= --}}
	<x-poof.trial-block :is-trial="$is_trial" :trial-days="$trial_days" :trial-used="$trial_used" />

	{{-- ================= BAGS ================= --}}
	<div class="mb-5">
		<x-poof.section title="Кількість мішків">
			<div class="flex gap-3">
				@foreach($pricing as $count => $bagPrice)
					<div class="flex-1">
						<x-poof.bag-option wire:click="selectBags({{ $count }})" :count="$count" :price="$bagPrice" :active="$selected_subscription_plan_id ? false : $bags_count === $count" :disabled="$selected_subscription_plan_id" />
					</div>
				@endforeach
			</div>
			<p class="text-xs text-gray-400 mt-2">До 6 кг у мішку</p>
			@if($selected_subscription_plan_id)
				<p class="text-xs text-yellow-300 mt-2">У підписку включено до 3 пакетів (18 кг) за один винос</p>
			@endif
		</x-poof.section>
	</div>

	{{-- ================= DIVIDER ================= --}}
	<div class="my-8">
		<div class="relative h-px w-full bg-neutral-800">
			<div class="
				absolute inset-0
				bg-gradient-to-r
				from-transparent
				via-yellow-400/30
				to-transparent
			"></div>
		</div>
	</div>
	{{-- ================= TOTAL + SUBMIT (STICKY) ================= --}}
	<div class="sticky bottom-[calc(5rem+env(safe-area-inset-bottom))] z-40 space-y-3 rounded-2xl bg-gray-950/95 backdrop-blur-sm">
		<x-poof.order-summary
			:price="$price"
			:is-trial="$is_trial"
		/>

		@if($selected_subscription_plan_id)
			<p class="text-center text-xs text-gray-300">
				Підписка: фінальна місячна ціна вже врахована у «До оплати».
			</p>
		@endif

		<x-poof.submit-button
			wire:click="submit"
			wire:loading.attr="disabled"
			data-e2e="client-order-submit"
			:disabled="!$scheduled_date || !$scheduled_time_from"
			:label="$is_trial ? 'Перший безкоштовний винос' : 'Зроби чисто POOF!'"
			class="{{ (!$scheduled_date || !$scheduled_time_from) ? 'opacity-60 cursor-not-allowed' : '' }}"
		/>

		@if(! $scheduled_date || ! $scheduled_time_from)
			<p class="mt-2 text-center text-xs text-gray-400">
				Оберіть дату та час, щоб оформити замовлення ✨
			</p>
		@endif
	</div>

	
	
</div>


	<x-poof.modal
		wire:model="showPaymentModal"
		maxWidth="max-w-md"
	>
		<div class="space-y-5">
			<x-poof.icons.success-check class="h-14 w-14" />

			<div class="space-y-2 text-center">
				<h3 class="text-xl font-semibold text-gray-100">
					Ваше замовлення #{{ $createdOrderId ?? '—' }} прийнято
				</h3>

				<p class="text-sm leading-relaxed text-gray-300">
					@if($is_trial)
						Замовлення оформлено як welcome-бонус. Курʼєра підберемо як для звичайного оплачуваного замовлення.
					@else
						Після оплати ми підберемо курʼєра та розпочнемо виконання вашого замовлення.
					@endif
				</p>
			</div>

			<div class="rounded-xl border border-green-400/20 bg-green-500/5 px-4 py-4 text-base leading-relaxed text-gray-200 sm:text-lg">
				<div class="space-y-2">
					<div>• Курʼєра зазвичай знаходимо протягом 20–30 хвилин.</div>
					<div>• Оплата захищена через WayForPay.</div>
					<div>• Можна оплатити зараз або пізніше в «Моїх замовленнях».</div>
				</div>
			</div>

			<div class="space-y-3">
				@if($is_trial)
					<a
						href="{{ route('client.orders') }}"
						class="block w-full rounded-2xl bg-green-500 px-4 py-3.5 text-center text-xl font-semibold text-white shadow-lg shadow-green-500/30 transition hover:bg-green-400"
					>
						Перейти до замовлень
					</a>
				@else
					<a
						href="{{ $createdOrderId ? route('client.payments.show', $createdOrderId) : route('client.orders') }}"
						class="block w-full rounded-2xl bg-green-500 px-4 py-3.5 text-center text-xl font-semibold text-white shadow-lg shadow-green-500/30 transition hover:bg-green-400"
					>
						Оплатити зараз {{ $price }} грн
					</a>
				@endif
				<a
					href="{{ route('client.orders') }}"
					class="block w-full rounded-2xl border border-yellow-400/40 bg-yellow-400/10 px-4 py-3.5 text-center text-sm font-semibold text-yellow-200 transition hover:bg-yellow-400/20"
				>
					{{ $is_trial ? 'Добре, зрозуміло' : 'Оплатити пізніше' }}
				</a>
			</div>
		</div>
	</x-poof.modal>

	<x-poof.modal
		wire:model="showSubscriptionModal"
		maxWidth="max-w-md"
		panelClass="w-full max-w-none rounded-none sm:max-w-md sm:rounded-2xl max-h-[100dvh] overflow-y-auto pb-[max(env(safe-area-inset-bottom),1rem)]"
		overlayPaddingClass="px-0 sm:px-4"
		overlayClass="items-end sm:items-center"
	>
		<div class="space-y-4">
			<div class="flex items-center justify-between gap-3">
				<h3 class="text-lg font-semibold text-white">Підписка POOF</h3>
				<button type="button" wire:click="$set('showSubscriptionModal', false)" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-neutral-700 text-gray-300 transition hover:border-neutral-500 hover:text-white" aria-label="Закрити" title="Закрити">×</button>
			</div>
			<p class="text-sm text-gray-300">Фіксована ціна на місяць. До 3 пакетів (18 кг) за один винос.</p>

			<div class="space-y-3">
				@foreach($subscriptionOptions as $option)
					<button
						type="button"
						wire:click="selectSubscriptionPlan({{ $option['id'] }})"
						class="w-full rounded-2xl border border-neutral-700 bg-neutral-800 px-4 py-3 text-left hover:border-yellow-400/50"
					>
						<div class="flex items-start justify-between gap-3">
							<div>
								<div class="text-sm font-semibold text-white">{{ $option['title'] }}</div>
								<div class="text-xs text-gray-400">{{ $option['description'] }}</div>
							</div>
							<div class="text-right">
								<div class="text-sm font-bold text-yellow-300">{{ $option['monthly_price'] }} грн / міс</div>
								<div class="text-[11px] text-green-400">Економія {{ $option['saving_percent'] }}% від разових (до {{ $option['max_bags'] }} пак.)</div>
							</div>
						</div>
						<div class="mt-2 grid grid-cols-2 gap-2 text-[11px] text-gray-300">
							<div>{{ $option['pickups_per_month'] }} виносів на місяць</div>
							<div class="text-right">≈ {{ $option['approx_price_per_pickup'] }} грн за винос</div>
						</div>
						<div class="mt-1 text-[11px] text-gray-400">
							До {{ $option['max_bags'] }} пакетів ({{ $option['max_weight_kg'] }} кг) за один винос
						</div>
					</button>
				@endforeach
			</div>
		</div>
	</x-poof.modal>

	<x-poof.modal
		wire:model="showSaveAddressConfirmModal"
		maxWidth="max-w-md"
	>
		<div class="text-2xl mb-3 text-center">📍</div>

		<h3 class="text-lg font-extrabold text-white text-center mb-2">
			Зберегти цю адресу в Мої адреси?
		</h3>

		<p class="text-sm text-gray-300 text-center mb-5">
			Це допоможе швидше оформлювати наступні замовлення.
		</p>

		<div class="flex gap-3 justify-end flex-wrap">
			<button
				type="button"
				wire:click="declineSaveAddressAndContinue"
				class="px-4 py-2 rounded-xl border border-neutral-700 text-gray-200 text-sm"
			>
				Не зараз
			</button>

			<button
				type="button"
				wire:click="confirmSaveAddressAndContinue"
				class="px-4 py-2 rounded-xl bg-yellow-400 text-black font-bold text-sm"
			>
				Так, зберегти
			</button>
		</div>
	</x-poof.modal>

	{{-- ================= TRIAL BLOCKED MODAL ================= --}}
		<x-poof.modal
			wire:model="showTrialBlockedModal"
		>
			<div class="text-4xl mb-3 text-center">🚫</div>

			<h3 class="text-lg font-extrabold text-white text-center mb-2">
				Пробний винос недоступний
			</h3>

			<p class="text-sm text-gray-400 text-center mb-5">
				Ви вже скористалися безкоштовним пробним виносом раніше.
			</p>

			<x-poof.button
				wire:click="$set('showTrialBlockedModal', false)"
				class="w-full"
			>
				Зрозуміло
			</x-poof.button>
		</x-poof.modal>

</div>
		{{-- ================= ADDRESS PICKER SHEET ================= --}}
		<div
			x-data
			x-on:close-address-book.window="$dispatch('sheet:close', { name: 'addressPicker' })"
		>
		<x-poof.ui.bottom-sheet name="addressPicker" title="Мої адреси">
			<div class="space-y-3">
				@forelse($addresses as $address)
				  <button
					type="button"
					wire:click="selectAddress({{ $address->id }})"
					data-e2e="address-picker-item"
					class="
						w-full text-left p-4 rounded-xl
						bg-neutral-800 hover:bg-neutral-700 transition
						border
						{{ $address->is_default ? 'border-yellow-400' : 'border-neutral-700' }}
					"
				>
					<div class="flex items-center justify-between gap-2 mb-1">
						<div class="flex items-center gap-2 min-w-0">
							<span class="font-semibold text-white truncate">
								{{ $address->label_title }}
							</span>

							@if($address->is_default)
								<span class="text-xs text-yellow-400 shrink-0">• основна</span>
							@endif
						</div>

						{{-- 📍 Статус точки --}}
						@if($address->lat && $address->lng)
							<span class="text-xs text-green-400 shrink-0">📍 ok</span>
						@else
							<span class="text-xs text-yellow-400 shrink-0">⚠ уточнити</span>
						@endif
					</div>

					<p class="text-sm text-gray-300">
						{{ $address->address_text ?? $address->full_address }}
					</p>
				</button>

				@empty
					<div class="text-center mt-6 space-y-3">
						<p class="text-sm text-gray-400 mb-4">
							Збережених адрес поки немає
						</p>

						<button
							type="button"
							x-data
							@click="window.dispatchEvent(new CustomEvent('use-current-location'))"
							class="inline-flex w-full sm:w-auto items-center justify-center gap-2 p-4 rounded-xl border border-neutral-700 hover:bg-neutral-800 text-white font-medium transition"
						>
							<span>📍</span>
							<span>Використати мою локацію</span>
						</button>

						<a
							href="/client/profile"
							class="inline-flex w-full sm:w-auto items-center justify-center gap-2 p-4 rounded-xl bg-yellow-500 hover:bg-yellow-400 text-black font-semibold transition"
						>
							<span>➕</span>
							<span>Додати адресу</span>
						</a>
					</div>
				@endforelse
			</div>

		</x-poof.ui.bottom-sheet>
		</div>

</div>
