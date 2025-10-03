<?php

namespace App\DTOs;

use Carbon\Carbon;

class AvailabilitySlot
{
    public function __construct(
        public Carbon $slotStart,
        public Carbon $slotEnd,
        public int $seatsLeft,
        public bool $inventoryOk
    ) {}

    public function toArray(): array
    {
        return [
            'slotStart' => $this->slotStart->toISOString(),
            'slotEnd' => $this->slotEnd->toISOString(),
            'seatsLeft' => $this->seatsLeft,
            'inventoryOk' => $this->inventoryOk,
        ];
    }
}
