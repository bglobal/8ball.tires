<?php

namespace App\DTOs;

use Carbon\Carbon;

class BookingRequest
{
    public function __construct(
        public int $locationId,
        public int $serviceId,
        public Carbon $slotStartUtc,
        public int $seats,
        public string $customerName,
        public string $phone,
        public string $email
    ) {}

    public function toArray(): array
    {
        return [
            'locationId' => $this->locationId,
            'serviceId' => $this->serviceId,
            'slotStartUtc' => $this->slotStartUtc->toISOString(),
            'seats' => $this->seats,
            'customerName' => $this->customerName,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }
}
