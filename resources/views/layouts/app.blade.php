<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PlayStation Rental Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .rental-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .rental-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .rental-card.expired {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
        .rental-card.warning {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        .time-remaining {
            font-weight: bold;
            font-size: 1.1em;
        }
        .time-expired {
            color: #dc3545;
            font-weight: bold;
        }
        .time-warning {
            color: #ff6b35;
            font-weight: bold;
        }
        .time-good {
            color: #28a745;
            font-weight: bold;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .status-badge {
            font-size: 0.9em;
        }
        .auto-refresh {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
        }
        .cafe-item-card {
            transition: transform 0.2s ease;
            cursor: pointer;
        }
        .cafe-item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .cafe-item-card.selected {
            border: 2px solid #007bff;
            background-color: #f8f9ff;
        }
        .cart-summary {
            position: sticky;
            top: 20px;
        }
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .quantity-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }
        .quantity-btn:hover {
            background-color: #f8f9fa;
        }
        .category-tab {
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .category-tab.active {
            border-bottom-color: #007bff;
            background-color: #f8f9ff;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ route('rentals.index') }}">
                <i class="bi bi-controller"></i> PlayStation Rental Manager
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="{{ route('rentals.ip-list') }}">
                    <i class="bi bi-clock-history"></i> History Peminjaman
                </a>
                <a class="nav-link" href="{{ route('rentals.index') }}">
                    <i class="bi bi-list-ul"></i> Active Rentals
                </a>
                <a class="nav-link" href="{{ route('cafe.orders') }}">
                    <i class="bi bi-cup-hot"></i> Cafe Orders
                </a>
                <a class="nav-link" href="{{ route('cafe.stock.index') }}">
                    <i class="bi bi-box-seam"></i> Stock Management
                </a>
                <a class="nav-link {{ request()->routeIs('transactions.*') ? 'active' : '' }}" href="{{ route('transactions.index') }}">
                    <i class="bi bi-cash-stack"></i> Kas
                </a>
                @include('components.rental-monitoring')
                <div id="connectionStatus" class="position-fixed" style="bottom: 20px; right: 20px; z-index: 1000;">
                    <small class="text-muted">
                        <i class="bi bi-circle-fill"></i> Initializing monitoring...
                    </small>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh functionality
        let autoRefreshInterval;
        let autoRefreshEnabled = false;

        function toggleAutoRefresh() {
            const button = document.getElementById('autoRefreshBtn');
            const status = document.getElementById('refreshStatus');
            
            if (autoRefreshEnabled) {
                clearInterval(autoRefreshInterval);
                autoRefreshEnabled = false;
                button.innerHTML = '<i class="bi bi-play"></i> Start Auto Refresh';
                button.className = 'btn btn-success btn-sm';
                status.textContent = 'Auto refresh: OFF';
                status.className = 'text-muted small';
            } else {
                autoRefreshInterval = setInterval(() => {
                    window.location.reload();
                }, 30000); // Refresh every 30 seconds
                autoRefreshEnabled = true;
                button.innerHTML = '<i class="bi bi-pause"></i> Stop Auto Refresh';
                button.className = 'btn btn-warning btn-sm';
                status.textContent = 'Auto refresh: ON (30s)';
                status.className = 'text-success small';
            }
        }

        // Update countdown timers
        function updateCountdowns() {
            document.querySelectorAll('.countdown-timer').forEach(function(element) {
                const endTime = new Date(element.dataset.endtime);
                const now = new Date();
                const diff = endTime - now;

                if (diff <= 0) {
                    element.innerHTML = '<span class="time-expired">EXPIRED</span>';
                    element.closest('.rental-card').classList.add('expired');
                } else {
                    const minutes = Math.floor(diff / 60000);
                    const hours = Math.floor(minutes / 60);
                    const remainingMinutes = minutes % 60;

                    let timeString;
                    if (hours > 0) {
                        timeString = `${hours}h ${remainingMinutes}m`;
                    } else {
                        timeString = `${remainingMinutes}m`;
                    }

                    let className = 'time-good';
                    if (minutes <= 5) {
                        className = 'time-expired';
                        element.closest('.rental-card').classList.add('expired');
                    } else if (minutes <= 15) {
                        className = 'time-warning';
                        element.closest('.rental-card').classList.add('warning');
                    }

                    element.innerHTML = `<span class="${className}">${timeString} remaining</span>`;
                }
            });
        }

        // Update countdowns every minute
        setInterval(updateCountdowns, 60000);
        // Initial update
        updateCountdowns();

        // Cafe modal functionality
        function showCafeModal(tvIp, rentalId = null) {
            const modalHtml = `
                <div class="modal fade" id="cafeOrderModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-cup-hot"></i> Cafe Order
                                    ${rentalId ? ' - Integrated with Rental' : ' - Separate Order'}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-0">
                                <div class="text-center p-4">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading menu...</span>
                                    </div>
                                    <p class="mt-2">Loading cafe menu...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('cafeOrderModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add new modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('cafeOrderModal'));
            modal.show();
            
            // Load cafe menu
            const orderType = rentalId ? 'integrated' : 'separate';
            const url = `/cafe/menu?tv_ip=${tvIp}&rental_id=${rentalId || ''}&order_type=${orderType}`;
            
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    document.querySelector('#cafeOrderModal .modal-body').innerHTML = html;
                })
                .catch(error => {
                    document.querySelector('#cafeOrderModal .modal-body').innerHTML = `
                        <div class="alert alert-danger m-4">
                            <i class="bi bi-exclamation-triangle"></i>
                            Failed to load cafe menu. Please try again.
                        </div>
                    `;
                });
        }
    </script>
    @yield('scripts')
</body>
</html>
