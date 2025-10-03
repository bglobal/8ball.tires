<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Calendar Preview - 8Ball Tires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/heroicons@2.0.18/24/outline/index.css" rel="stylesheet">
    <style>
        .slot-card {
            transition: all 0.3s ease;
        }
        .slot-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-available { background-color: #10b981; }
        .status-unavailable { background-color: #ef4444; }
        .loading {
            display: none;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Calendar Preview</h1>
                    <a href="{{ route('filament.admin.pages.dashboard') }}" class="btn btn-outline-secondary">
                        <i class="heroicon-outline-arrow-left"></i> Back to Admin
                    </a>
                </div>

                <!-- Sync Status -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">Shopify Sync Status</h5>
                                <p class="card-text text-muted mb-0">
                                    Last sync: {{ $lastSyncTime ? \Carbon\Carbon::parse($lastSyncTime)->diffForHumans() : 'Never' }}
                                </p>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="syncShopify()">
                                <i class="heroicon-outline-arrow-path"></i> Sync Shopify
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="{{ route('calendar-preview') }}" id="calendarForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="location_id" class="form-label">Location</label>
                                    <select name="location_id" id="location_id" class="form-select" required>
                                        <option value="">Select a location</option>
                                        @foreach($locations as $id => $name)
                                            <option value="{{ $id }}" {{ $selectedLocation == $id ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="service_id" class="form-label">Service</label>
                                    <select name="service_id" id="service_id" class="form-select" required>
                                        <option value="">Select a service</option>
                                        @foreach($services as $id => $name)
                                            <option value="{{ $id }}" {{ $selectedService == $id ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="date" class="form-label">Date</label>
                                    <input type="date" name="date" id="date" class="form-control" 
                                           value="{{ $selectedDate }}" required>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="heroicon-outline-magnifying-glass"></i> Load Slots
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="refreshSlots()">
                                    <i class="heroicon-outline-arrow-path"></i> Refresh
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Calendar Slots -->
                @if(count($slots) > 0)
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">
                                Available Slots for <strong>{{ $services[$selectedService] ?? 'Selected Service' }}</strong>
                                at {{ $locations[$selectedLocation] ?? 'Selected Location' }}
                                on {{ \Carbon\Carbon::parse($selectedDate)->format('M j, Y') }}
                            </h5>
                            
                            <div class="row">
                                @foreach($slots as $slot)
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card slot-card h-100 {{ $slot['available'] ? 'border-success' : 'border-danger' }}">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0">Time Slot</h6>
                                                    <span class="badge {{ $slot['available'] ? 'bg-success' : 'bg-danger' }}">
                                                        {{ $slot['available'] ? 'Available' : 'Unavailable' }}
                                                    </span>
                                                </div>
                                                
                                                <p class="card-text text-muted small mb-2">
                                                    <i class="heroicon-outline-clock"></i>
                                                    {{ \Carbon\Carbon::parse($slot['slot_start'])->format('g:i A') }} - 
                                                    {{ \Carbon\Carbon::parse($slot['slot_end'])->format('g:i A') }}
                                                </p>
                                                
                                                <div class="d-flex flex-wrap gap-2">
                                                    <span class="badge {{ $slot['inventory_ok'] ? 'bg-success' : 'bg-danger' }}">
                                                        <span class="status-indicator status-{{ $slot['inventory_ok'] ? 'available' : 'unavailable' }}"></span>
                                                        Inventory: {{ $slot['inventory_ok'] ? 'Available' : 'Insufficient' }}
                                                    </span>
                                                    <span class="badge {{ $slot['seats_left'] > 0 ? 'bg-success' : 'bg-danger' }}">
                                                        <span class="status-indicator status-{{ $slot['seats_left'] > 0 ? 'available' : 'unavailable' }}"></span>
                                                        Seats: {{ $slot['seats_left'] }} left
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @elseif($selectedLocation && $selectedDate && $selectedService)
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <div class="mb-3">
                                <i class="heroicon-outline-calendar" style="font-size: 3rem; color: #6c757d;"></i>
                            </div>
                            <h5 class="card-title">No slots available</h5>
                            <p class="card-text text-muted">
                                No available time slots found for <strong>{{ $services[$selectedService] ?? 'the selected service' }}</strong> 
                                at {{ $locations[$selectedLocation] ?? 'the selected location' }} 
                                on {{ \Carbon\Carbon::parse($selectedDate)->format('M j, Y') }}.
                            </p>
                        </div>
                    </div>
                @elseif($selectedLocation && $selectedDate)
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <div class="mb-3">
                                <i class="heroicon-outline-exclamation-triangle" style="font-size: 3rem; color: #f59e0b;"></i>
                            </div>
                            <h5 class="card-title">Please select a service</h5>
                            <p class="card-text text-muted">
                                Choose a service from the dropdown above to view available time slots.
                            </p>
                        </div>
                    </div>
                @endif

                <!-- Loading Indicator -->
                <div class="loading text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading slots...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = 'alert-' + Date.now();
            
            const alertHtml = `
                <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            alertContainer.insertAdjacentHTML('beforeend', alertHtml);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                const alert = document.getElementById(alertId);
                if (alert) {
                    alert.remove();
                }
            }, 5000);
        }

        function syncShopify() {
            const button = event.target;
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="heroicon-outline-arrow-path"></i> Syncing...';
            button.disabled = true;
            
            fetch('{{ route("admin.shopify.sync") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Shopify sync started successfully', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert(data.message || 'Failed to start Shopify sync', 'danger');
                }
            })
            .catch(error => {
                showAlert('Failed to start Shopify sync', 'danger');
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        function refreshSlots() {
            document.getElementById('calendarForm').submit();
        }

        // Auto-submit form when location, service, or date changes
        document.getElementById('location_id').addEventListener('change', function() {
            if (this.value && document.getElementById('date').value && document.getElementById('service_id').value) {
                document.getElementById('calendarForm').submit();
            }
        });

        document.getElementById('service_id').addEventListener('change', function() {
            if (this.value && document.getElementById('date').value && document.getElementById('location_id').value) {
                document.getElementById('calendarForm').submit();
            }
        });

        document.getElementById('date').addEventListener('change', function() {
            if (this.value && document.getElementById('location_id').value && document.getElementById('service_id').value) {
                document.getElementById('calendarForm').submit();
            }
        });

        // Show loading indicator on form submit
        document.getElementById('calendarForm').addEventListener('submit', function() {
            document.querySelector('.loading').style.display = 'block';
        });

        // Show alerts from server
        @if(session('error'))
            showAlert('{{ session('error') }}', 'danger');
        @endif

        @if(session('success'))
            showAlert('{{ session('success') }}', 'success');
        @endif
    </script>
</body>
</html>
