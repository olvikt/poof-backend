<?php

namespace App\Livewire\Client\OrderCreate\Concerns;

use Carbon\Carbon;
use Livewire\Attributes\On;

trait HandlesScheduleSlots
{
    protected function firstAvailableSlotIndex(): int
    {
        $now = now();

        $selectedDate = $this->scheduled_date
            ? Carbon::parse($this->scheduled_date)
            : Carbon::today();

        $isToday = $selectedDate->isSameDay($now);

        foreach ($this->timeSlots as $idx => $slot) {
            if (! ($slot['enabled'] ?? true)) {
                continue;
            }

            if (! $isToday) {
                return (int) $idx;
            }

            $from = Carbon::createFromFormat('H:i', (string) $slot['from'])->setDate(
                $now->year,
                $now->month,
                $now->day
            );

            if ($from->greaterThan($now)) {
                return (int) $idx;
            }
        }

        return 0;
    }

    protected function updateIsCustomDate(): void
    {
        if (! $this->scheduled_date) {
            $this->isCustomDate = false;
            return;
        }

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $this->isCustomDate = ! in_array($this->scheduled_date, [$today, $tomorrow], true);
    }

    public function applyTimeSlot(int $idx): void
    {
        $count = count($this->timeSlots);

        if ($count === 0) {
            $this->timeSlot = 0;
            $this->scheduled_time_from = null;
            $this->scheduled_time_to = null;
            return;
        }

        $idx = max(0, min($idx, $count - 1));

        if (! ($this->timeSlots[$idx]['enabled'] ?? true)) {
            $found = null;

            for ($j = $idx; $j < $count; $j++) {
                if (($this->timeSlots[$j]['enabled'] ?? true) === true) {
                    $found = $j;
                    break;
                }
            }

            if ($found === null) {
                for ($j = $idx; $j >= 0; $j--) {
                    if (($this->timeSlots[$j]['enabled'] ?? true) === true) {
                        $found = $j;
                        break;
                    }
                }
            }

            $idx = $found ?? 0;
        }

        $this->timeSlot = (int) $idx;
        $this->scheduled_time_from = $this->timeSlots[$idx]['from'] ?? null;
        $this->scheduled_time_to = $this->timeSlots[$idx]['to'] ?? null;
    }

    public function updatedScheduledDate(): void
    {
        $this->updateIsCustomDate();
        $this->applyTimeSlot($this->firstAvailableSlotIndex());
        $this->recalculatePrice();
    }

    public function selectTimeSlot(string $from, string $to): void
    {
        $this->scheduled_time_from = $from;
        $this->scheduled_time_to = $to;

        foreach ($this->timeSlots as $idx => $slot) {
            if (($slot['from'] ?? null) === $from && ($slot['to'] ?? null) === $to) {
                if (($slot['enabled'] ?? true) === true) {
                    $this->timeSlot = (int) $idx;
                }

                break;
            }
        }
    }

    public function getIsCustomDateProperty(): bool
    {
        return $this->isCustomDate;
    }

    #[On('set-scheduled-date')]
    public function setScheduledDate(string $date): void
    {
        $this->scheduled_date = $date;
        $this->updatedScheduledDate();
    }

    #[On('set-time-slot')]
    public function setTimeSlot(int $index): void
    {
        $this->applyTimeSlot($index);
    }
}
