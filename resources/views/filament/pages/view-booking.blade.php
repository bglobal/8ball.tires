<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Booking Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Booking Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Booking ID:</span>
                    <span class="text-gray-900">{{ $this->record->id }}</span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Status:</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($this->record->status->value === 'completed') bg-green-100 text-green-800
                        @elseif($this->record->status->value === 'pending') bg-yellow-100 text-yellow-800
                        @elseif($this->record->status->value === 'cancelled') bg-red-100 text-red-800
                        @else bg-blue-100 text-blue-800
                        @endif">
                        {{ ucfirst($this->record->status->value) }}
                    </span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Customer:</span>
                    <span class="text-gray-900">{{ $this->record->customer_name }}</span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Email:</span>
                    <span class="text-gray-900">{{ $this->record->email }}</span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Phone:</span>
                    <span class="text-gray-900">{{ $this->record->phone }}</span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Seats:</span>
                    <span class="text-gray-900">{{ $this->record->seats }}</span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Start Time:</span>
                    <span class="text-gray-900">{{ $this->record->slot_start_utc->format('M j, Y g:i A') }}</span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">End Time:</span>
                    <span class="text-gray-900">{{ $this->record->slot_end_utc->format('M j, Y g:i A') }}</span>
                </div>
            </div>
        </div>

        <!-- Service & Location -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Service & Location</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Service:</span>
                    <span class="text-gray-900">{{ $this->record->service->title }}</span>
                </div>
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Location:</span>
                    <span class="text-gray-900">{{ $this->record->location->name }}</span>
                </div>
                @if($this->record->draft_order_id)
                <div class="flex">
                    <span class="font-medium text-gray-700 w-32">Draft Order ID:</span>
                    <span class="text-gray-900 font-mono text-sm">{{ $this->record->draft_order_id }}</span>
                </div>
                @endif
            </div>
        </div>

        <!-- Additional Notes -->
        @if($this->record->meta && !empty($this->record->meta))
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Additional Notes</h3>
            <div class="space-y-3">
                @foreach($this->record->meta as $key => $value)
                <div class="flex">
                    <span class="font-medium text-gray-700 w-48 capitalize">{{ str_replace('_', ' ', $key) }}:</span>
                    <span class="text-gray-900 flex-1">{{ $value }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</x-filament-panels::page>
