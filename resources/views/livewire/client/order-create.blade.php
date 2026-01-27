<div>
<div
    id="order-create-root"
    class="bg-gray-950 rounded-2xl max-w-md mx-auto mt-8 px-4 py-6 text-white">
    {{-- TITLE --}}
    <h1 class="text-xl font-extrabold mb-4">
        🧹 Оформити замовлення
    </h1>
  <div class="mb-5">
    {{-- ================= MAP ================= --}}  	
	    <x-poof.map>
			Місце забору
		</x-poof.map>

    {{-- ================= ADDRESS ================= --}}
    <div class="mb-4">
       <x-poof.section title="Адреса">
			<x-poof.input-floating
				label="Вулиця, будинок"
				model="address_text"
			/>
			@error('address_text')
				<div class="text-red-400 text-xs mt-1">{{ $message }}</div>
			@enderror
		</x-poof.section>
    </div>


	{{-- DETAILS --}}
	<div class="flex gap-2 mb-4">
		<x-poof.input-floating label="Підʼїзд" model="entrance" center />
		<x-poof.input-floating label="Поверх" model="floor" center />
		<x-poof.input-floating label="Кв./офіс" model="apartment" center />
		<x-poof.input-floating label="Домофон" model="intercom" center />
	</div>

    {{-- COMMENT --}}
	<textarea
		wire:model.defer="comment"
		rows="3"
		placeholder="Коментар (Домофон, охорона, примітки)"
		class="w-full mb-4 poof-input resize-none"
	></textarea>

{{-- ================= DATE ================= --}}
<div class="mb-6">
    <label class="text-sm text-gray-400 mb-3 block">Дата</label>

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
    <label class="text-sm text-gray-400 mb-3 block">Час</label>

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

	{{-- ================= HANDOVER ================= --}}
	
		<x-poof.section title="Передача">
			<div class="flex gap-3">

				<x-poof.choice-card
					wire:model="handover_type"
					value="door"
					:current="$handover_type"
					title="За дверима"
					subtitle="Без контакту"
					icon="🚪"
				/>

				<x-poof.choice-card
					wire:model="handover_type"
					value="hand"
					:current="$handover_type"
					title="В руки"
					subtitle="Особисто"
					icon="🤝"
				/>

			</div>
		</x-poof.section>

	

	{{-- ================= BAGS ================= --}}
	<div class="mb-5">
		<x-poof.section title="Кількість мішків">
			<div class="flex gap-3">

				@foreach($pricing as $count => $bagPrice)
					<div
						wire:click="selectBags({{ $count }})"
						class="flex-1"
					>
						<x-poof.bag-option
							:count="$count"
							:price="$bagPrice"
							:active="$bags_count === $count"
							:disabled="$is_trial"
						/>
					</div>
				@endforeach
			</div>

			<p class="text-xs text-gray-400 mt-2">
				До 6 кг у мішку
			</p>
		</x-poof.section>

	</div>


	{{-- ================= TRIAL ================= --}}
	
		<x-poof.trial-block
			:is-trial="$is_trial"
			:trial-days="$trial_days"
			:trial-used="$trial_used"
		/>
	

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
	<div class="sticky bottom-4 z-40 space-y-3">
		<x-poof.order-summary
			:price="$price"
			:is-trial="$is_trial"
		/>

		<x-poof.submit-button
			wire:click="submit"
			wire:loading.attr="disabled"
			:disabled="!$scheduled_date || !$scheduled_time_from"
			:label="$is_trial ? 'Пробний POOF винос' : 'Зроби чисто POOF!'"
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
		<div class="text-2xl mb-3 text-center">✅</div>

		<h3 class="text-lg font-extrabold text-white text-center mb-2">
			Ваше замовлення прийнято
		</h3>

		<p class="text-sm text-gray-300 text-center mb-4">
			Після оплати ми підберемо курʼєра для виконання замовлення.
		</p>

		<div class="text-sm text-gray-400 leading-relaxed mb-5 space-y-1">
			<div>🕒 Курʼєр зазвичай знаходиться протягом 5–15 хвилин</div>
			<div>🛡 Оплата безпечна, замовлення можна скасувати</div>
			<div>🔄 Оплатити можна пізніше в історії замовлень</div>
		</div>

		<div class="flex gap-3 justify-end flex-wrap">
			<a
				href="{{ route('client.orders') }}"
				class="px-4 py-2 rounded-xl border border-neutral-700 text-gray-200 text-sm"
			>
				Оплатити пізніше
			</a>
			<a
				href="{{ url('/client/orders') }}"
				class="px-4 py-2 rounded-xl bg-yellow-400 text-black font-bold text-sm"
			>
				Оплатити зараз {{ $price }} грн
			</a>
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
@vite('resources/js/poof/order-create.js')
</div>

