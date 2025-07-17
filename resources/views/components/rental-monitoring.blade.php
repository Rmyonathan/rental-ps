<script>
// Global rental monitoring system - Available on all pages
(function() {
    // Global variables
    window.rentalMonitoring = {
        countdownIntervals: new Map(),
        activeSSEConnections: new Map(),
        isInitialized: false
    };

    // Initialize monitoring when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeGlobalRentalMonitoring();
    });

    function initializeGlobalRentalMonitoring() {
        if (window.rentalMonitoring.isInitialized) {
            console.log('🔄 Rental monitoring already initialized');
            return;
        }

        console.log('🚀 Initializing global rental monitoring...');
        
        // Clear any existing monitoring
        clearAllMonitoring();
        
        // Check for active rentals across the entire system
        fetchAndMonitorActiveRentals();
        
        // Set up periodic checks for new rentals
        setInterval(fetchAndMonitorActiveRentals, 30000); // Check every 30 seconds
        
        window.rentalMonitoring.isInitialized = true;
    }

    function fetchAndMonitorActiveRentals() {
        // Fetch active rentals from your Laravel backend
        fetch('/api/active-rentals', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.rentals) {
                console.log(`📊 Found ${data.rentals.length} active rentals`);
                
                // Clear existing monitoring
                clearAllMonitoring();
                
                // Set up monitoring for each active rental
                data.rentals.forEach(rental => {
                    setupRentalMonitoring(rental);
                });
                
                updateConnectionStatus(data.rentals.length > 0 ? 'active' : 'idle');
            }
        })
        .catch(error => {
            console.error('❌ Error fetching active rentals:', error);
            updateConnectionStatus('error');
        });
    }

    function setupRentalMonitoring(rental) {
        const rentalId = rental.id;
        const endTime = new Date(rental.end_time);
        
        console.log(`⏰ Setting up monitoring for rental ${rentalId}, ends at: ${endTime}`);
        
        // Start countdown monitoring
        startGlobalCountdown(rentalId, endTime, rental.tv_ip);
        
        // Setup SSE connection
        setupGlobalSSEConnection(rentalId);
    }

    function startGlobalCountdown(rentalId, endTime, tvIp) {
        // Clear existing interval if any
        if (window.rentalMonitoring.countdownIntervals.has(rentalId)) {
            clearInterval(window.rentalMonitoring.countdownIntervals.get(rentalId));
        }

        function updateTimer() {
            const now = new Date();
            const timeLeft = endTime - now;
            
            if (timeLeft <= 0) {
                console.log(`⏰ Rental ${rentalId} expired - triggering timeout globally`);
                
                // Trigger timeout video regardless of current page
                triggerGlobalTimeoutVideo(rentalId, tvIp);
                
                // Mark rental as completed in backend
                handleRentalExpiration(rentalId);
                
                // Clear interval
                if (window.rentalMonitoring.countdownIntervals.has(rentalId)) {
                    clearInterval(window.rentalMonitoring.countdownIntervals.get(rentalId));
                    window.rentalMonitoring.countdownIntervals.delete(rentalId);
                }
                
                // Close SSE connection
                if (window.rentalMonitoring.activeSSEConnections.has(rentalId)) {
                    window.rentalMonitoring.activeSSEConnections.get(rentalId).close();
                    window.rentalMonitoring.activeSSEConnections.delete(rentalId);
                }
                
                return;
            }
            
            // Update any visible countdown elements on current page
            updateVisibleCountdown(rentalId, timeLeft);
        }
        
        // Update immediately and then every second
        updateTimer();
        const interval = setInterval(updateTimer, 1000);
        window.rentalMonitoring.countdownIntervals.set(rentalId, interval);
    }

    function updateVisibleCountdown(rentalId, timeLeft) {
        // Only update if countdown element exists on current page
        const element = document.querySelector(`[data-rental-id="${rentalId}"]`);
        if (!element) return;
        
        // Calculate time components
        const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
        const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
        
        let timeDisplay = '';
        if (days > 0) {
            timeDisplay = `${days}d ${hours}h ${minutes}m ${seconds}s`;
        } else if (hours > 0) {
            timeDisplay = `${hours}h ${minutes}m ${seconds}s`;
        } else if (minutes > 0) {
            timeDisplay = `${minutes}m ${seconds}s`;
        } else {
            timeDisplay = `${seconds}s`;
        }
        
        let colorClass = 'text-success';
        if (timeLeft < 5 * 60 * 1000) {
            colorClass = 'text-danger';
        } else if (timeLeft < 15 * 60 * 1000) {
            colorClass = 'text-warning';
        }
        
        element.innerHTML = `<span class="${colorClass}"><strong>${timeDisplay}</strong></span>`;
    }

    function setupGlobalSSEConnection(rentalId) {
        // Close existing connection if any
        if (window.rentalMonitoring.activeSSEConnections.has(rentalId)) {
            window.rentalMonitoring.activeSSEConnections.get(rentalId).close();
        }
        
        console.log(`🔗 Setting up global SSE connection for rental ${rentalId}`);
        
        const eventSource = new EventSource(`http://localhost:3001/events/${rentalId}`);
        
        eventSource.onopen = function(event) {
            console.log(`✅ Global SSE connection opened for rental ${rentalId}`);
        };
        
        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                console.log(`📨 Global SSE message for rental ${rentalId}:`, data);
                
                if (data.type === 'timeout') {
                    handleGlobalTimeoutEvent(rentalId, data);
                } else if (data.type === 'status_update') {
                    handleGlobalStatusUpdate(rentalId, data);
                }
            } catch (error) {
                console.error(`❌ Error parsing global SSE data for rental ${rentalId}:`, error);
            }
        };
        
        eventSource.onerror = function(event) {
            console.error(`❌ Global SSE error for rental ${rentalId}:`, event);
        };
        
        window.rentalMonitoring.activeSSEConnections.set(rentalId, eventSource);
    }

    function triggerGlobalTimeoutVideo(rentalId, tvIp) {
        console.log(`🎬 Triggering global timeout video for rental ${rentalId} on TV ${tvIp}`);
        
        fetch('http://localhost:3001/play-timeout-video', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                tv_ip: tvIp,
                rental_id: rentalId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`✅ Global timeout video triggered successfully for rental ${rentalId}`);
                
                // Update rental status in backend
                updateGlobalRentalStatus(rentalId, 'completed');
                
                // Show notification if user is on a page that supports it
                showGlobalNotification(`Rental ${rentalId} has expired. Timeout video is playing.`, 'info');
            } else {
                console.error(`❌ Failed to trigger global timeout video for rental ${rentalId}:`, data.error);
                showGlobalNotification(`Failed to trigger timeout for rental ${rentalId}`, 'error');
            }
        })
        .catch(error => {
            console.error(`❌ Network error triggering global timeout for rental ${rentalId}:`, error);
            showGlobalNotification('Network error: Could not connect to TV control server', 'error');
        });
    }

    function updateGlobalRentalStatus(rentalId, status) {
        fetch(`/api/rentals/${rentalId}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ status: status })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`✅ Global rental status updated for ${rentalId}: ${status}`);
                
                // Update UI if rental card exists on current page
                updateVisibleRentalStatus(rentalId, status);
            }
        })
        .catch(error => {
            console.error(`❌ Failed to update global rental status:`, error);
        });
    }

    function updateVisibleRentalStatus(rentalId, status) {
        const rentalCard = document.getElementById(`rental-${rentalId}`);
        if (rentalCard) {
            const statusBadge = rentalCard.querySelector('.badge');
            if (statusBadge) {
                statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                statusBadge.className = 'badge ' + (
                    status === 'active' ? 'bg-success' :
                    status === 'completed' ? 'bg-secondary' :
                    status === 'cancelled' ? 'bg-danger' : 'bg-warning'
                );
            }
        }
    }

    function handleGlobalTimeoutEvent(rentalId, data) {
        console.log(`⏰ Handling global timeout event for rental ${rentalId}`);
        updateVisibleRentalStatus(rentalId, 'completed');
        showGlobalNotification(`Rental ${rentalId} completed automatically`, 'success');
        handleRentalExpiration(rentalId); // Ensure the rental is removed from DOM
    }

    function handleGlobalStatusUpdate(rentalId, data) {
        console.log(`📊 Handling global status update for rental ${rentalId}:`, data);
        if (data.status) {
            updateVisibleRentalStatus(rentalId, data.status);
        }
    }

    function showGlobalNotification(message, type = 'info') {
        // Only show notifications if we're not on a page that already handles them
        if (typeof showNotification === 'function') {
            showNotification(message, type);
            return;
        }
        
        // Create a simple notification system
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        notification.innerHTML = `
            <strong>PlayStation Rental:</strong> ${message}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    function updateConnectionStatus(status) {
        // Update connection status indicator if it exists
        const statusElement = document.getElementById('connectionStatus');
        if (statusElement) {
            const statusText = {
                'active': 'Connected to real-time monitoring',
                'idle': 'Monitoring ready (no active rentals)',
                'error': 'Connection error'
            };
            
            const statusClass = {
                'active': 'text-success',
                'idle': 'text-warning',
                'error': 'text-danger'
            };
            
            statusElement.innerHTML = `
                <span class="${statusClass[status]}">
                    <i class="bi bi-circle-fill"></i> ${statusText[status]}
                </span>
            `;
        }
    }

    function clearAllMonitoring() {
        // Clear all countdown intervals
        window.rentalMonitoring.countdownIntervals.forEach((interval, rentalId) => {
            clearInterval(interval);
            console.log(`🧹 Cleared global interval for rental ${rentalId}`);
        });
        window.rentalMonitoring.countdownIntervals.clear();
        
        // Close all SSE connections
        window.rentalMonitoring.activeSSEConnections.forEach((eventSource, rentalId) => {
            eventSource.close();
            console.log(`🧹 Closed global SSE connection for rental ${rentalId}`);
        });
        window.rentalMonitoring.activeSSEConnections.clear();
    }

    // Cleanup when page is about to unload
    window.addEventListener('beforeunload', function() {
        clearAllMonitoring();
    });

    // Refresh monitoring when page becomes visible again
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            console.log('🔄 Page became visible, refreshing global monitoring...');
            setTimeout(fetchAndMonitorActiveRentals, 1000);
        }
    });

    // Expose global functions for manual use
    window.globalRentalMonitoring = {
        refresh: fetchAndMonitorActiveRentals,
        clear: clearAllMonitoring,
        status: () => ({
            intervals: window.rentalMonitoring.countdownIntervals.size,
            connections: window.rentalMonitoring.activeSSEConnections.size,
            initialized: window.rentalMonitoring.isInitialized
        })
    };
    
    function handleRentalExpiration(rentalId) {
        // 1. Send AJAX request to backend to mark as completed
        fetch(`/rentals/${rentalId}/complete`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`✅ Rental ${rentalId} marked as completed in backend`);
                // 2. Remove the rental card from DOM if it exists
                const rentalCard = document.getElementById(`rental-${rentalId}`);
                if (rentalCard) {
                    rentalCard.style.transition = 'opacity 0.5s ease-out';
                    rentalCard.style.opacity = '0';
                    setTimeout(() => {
                        rentalCard.remove();
                        // 3. Update statistics counters in the UI if they exist
                        updateStatistics();
                    }, 500);
                } else {
                    console.log(`ℹ️ Rental card for ${rentalId} not found in DOM`);
                }
            } else {
                console.error(`❌ Failed to mark rental ${rentalId} as completed:`, data.error);
            }
        })
        .catch(error => {
            console.error(`❌ Error marking rental ${rentalId} as completed:`, error);
        });
    }

    function updateStatistics() {
        const activeCards = document.querySelectorAll('.rental-card');
        const activeCount = activeCards.length;
        
        const activeCountElement = document.getElementById('activeCount');
        if (activeCountElement) {
            activeCountElement.textContent = activeCount;
        }
        
        // Check if there are no rentals left and show a message if needed
        checkIfNoRentalsLeft();
    }

    function checkIfNoRentalsLeft() {
        const rentalsContainer = document.getElementById('rentalsContainer');
        if (!rentalsContainer) return;
        
        const activeCards = document.querySelectorAll('.rental-card');
        if (activeCards.length === 0) {
            rentalsContainer.style.display = 'none';
            
            const noRentalsHtml = `
                <div class="text-center py-5" id="noRentalsMessage">
                    <i class="bi bi-controller" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3 text-muted">No Active Rentals</h4>
                    <p class="text-muted">All PlayStation stations are available for rent.</p>
                    <a href="/rentals/create" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle"></i> Start New Rental
                    </a>
                </div>
            `;
            
            rentalsContainer.insertAdjacentHTML('afterend', noRentalsHtml);
        }
    }
})();
</script>
