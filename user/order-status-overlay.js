// Order Status Overlay JavaScript

class OrderStatusOverlay {
    constructor() {
        this.wrapper = null;
        this.overlay = null;
        this.confirmation = null;
        this.autoRefreshInterval = null;
        this.currentOrder = null;
    }

    // Initialize and show overlay
    async init(autoRefresh = true) {
        if (!document.getElementById('orderStatusOverlayWrapper')) {
            this.createOverlay();
        }
        
        this.wrapper = document.getElementById('orderStatusOverlayWrapper');
        this.overlay = document.getElementById('orderStatusOverlay');
        this.confirmation = document.getElementById('orderStatusConfirmation');

        // Load latest order
        await this.loadLatestOrder();

        // Auto-refresh order status
        if (autoRefresh) {
            this.startAutoRefresh();
        }
    }

    // Create overlay HTML
    createOverlay() {
        const html = `
            <div id="orderStatusOverlayWrapper" class="order-status-overlay-wrapper">
                <div id="orderStatusOverlay" class="order-status-overlay">
                    <div class="order-status-header">
                        <h3>Order Status</h3>
                        <button class="order-status-close" onclick="orderStatusOverlay.close()">&times;</button>
                    </div>
                    <div class="order-status-content">
                        <div class="order-status-info">
                            <div class="order-status-info-item">
                                <span class="order-status-info-label">Order #</span>
                                <span class="order-status-info-value" id="overlayOrderId">-</span>
                            </div>
                            <div class="order-status-info-item">
                                <span class="order-status-info-label">Status</span>
                                <span id="overlayOrderStatus" class="order-status-badge pending">Pending</span>
                            </div>
                        </div>
                        
                        <div class="order-status-info">
                            <div class="order-status-info-item">
                                <span class="order-status-info-label">Ordered At</span>
                                <span class="order-status-info-value" id="overlayOrderTime">-</span>
                            </div>
                            <div class="order-status-info-item">
                                <span class="order-status-info-label">Type</span>
                                <span class="order-status-info-value" id="overlayOrderType">-</span>
                            </div>
                        </div>

                        <div class="order-status-items" id="overlayOrderItems">
                            <div style="text-align: center; color: rgba(220, 228, 207, 0.6);">Loading items...</div>
                        </div>

                        <div class="order-status-total">
                            <span class="order-status-total-label">Total</span>
                            <span class="order-status-total-amount" id="overlayOrderTotal">₱0.00</span>
                        </div>

                        <div class="order-status-actions" id="overlayActions">
                            <!-- Actions will be populated based on status -->
                        </div>
                    </div>
                </div>
            </div>

            <div id="orderStatusConfirmation" class="order-status-confirmation">
                <div class="cg-dialog" id="cgDialog">
                    <div class="cg-dialog-header">
                        <span class="cg-status-icon" id="cgStatusIcon">✓</span>
                        <span class="cg-header-text" id="cgHeaderText">Order Delivered</span>
                    </div>
                    <div class="cg-icon-section">
                        <div class="cg-large-icon" id="cgLargeIcon">✓</div>
                    </div>
                    <div class="cg-message-section">
                        <div class="cg-ornament">
                            <span class="cg-ornament-dot"></span>
                            <span class="cg-ornament-dot"></span>
                            <span class="cg-ornament-dot"></span>
                        </div>
                        <p class="cg-message" id="cgMessage">Your order has been delivered. Salamat for dining with Casa Gunita!</p>
                        <div class="cg-ornament">
                            <span class="cg-ornament-dot"></span>
                            <span class="cg-ornament-dot"></span>
                            <span class="cg-ornament-dot"></span>
                        </div>
                    </div>
                    <div class="cg-button-section">
                        <button class="cg-btn" onclick="orderStatusOverlay.cancelConfirmation()">Confirm</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', html);
    }

    // Load latest order
    async loadLatestOrder() {
        try {
            const response = await fetch('order-status-api.php?action=get_latest');
            const data = await response.json();

            if (data.success && data.order) {
                this.currentOrder = data.order;
                this.renderOverlay();
            }
        } catch (error) {
            console.error('Error loading order:', error);
        }
    }

    // Render overlay with order data
    renderOverlay() {
        if (!this.currentOrder) return;

        const order = this.currentOrder;

        // Update basic info
        document.getElementById('overlayOrderId').textContent = '#' + String(order.order_id).padStart(5, '0');
        document.getElementById('overlayOrderTime').textContent = order.formatted_time || '-';
        document.getElementById('overlayOrderType').textContent = order.order_type === 'takeout' ? 'Pick-Up' : order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1);
        document.getElementById('overlayOrderTotal').textContent = order.formatted_total || '₱0.00';

        // Update status badge
        const statusBadge = document.getElementById('overlayOrderStatus');
        statusBadge.textContent = order.status.toUpperCase();
        statusBadge.className = 'order-status-badge ' + order.status;

        // Render items
        const itemsContainer = document.getElementById('overlayOrderItems');
        if (order.items && order.items.length > 0) {
            const itemsHtml = order.items.map(item => `
                <div class="order-status-item">
                    <span class="order-status-item-name">${item.name}</span>
                    <span class="order-status-item-qty">x${item.quantity}</span>
                    <span class="order-status-item-price">${this.formatPrice(item.subtotal)}</span>
                </div>
            `).join('');
            itemsContainer.innerHTML = itemsHtml;
        }

        // Update actions based on status
        this.updateActions();
    }

    // Update action buttons based on order status
    updateActions() {
        const actionsContainer = document.getElementById('overlayActions');
        const status = this.currentOrder.status;

        if (status === 'completed' || status === 'cancelled') {
            actionsContainer.innerHTML = `
                <button class="order-status-btn order-status-btn-primary" onclick="orderStatusOverlay.close()">
                    Close
                </button>
            `;
        } else if (status === 'pending' || status === 'preparing' || status === 'ready') {
            actionsContainer.innerHTML = `
                <button class="order-status-btn order-status-btn-success" onclick="orderStatusOverlay.confirmCompletion()">
                    Mark as Received
                </button>
                <button class="order-status-btn order-status-btn-danger" onclick="orderStatusOverlay.confirmCancellation()">
                    Cancel Order
                </button>
            `;
        } else {
            actionsContainer.innerHTML = '';
        }
    }

    // Confirm completion
    confirmCompletion() {
        document.getElementById('orderStatusConfirmation').classList.add('active');
        this.pendingAction = () => this.updateOrderStatus('completed', 'Thank you! Your order has been marked as received.');
    }

    // Confirm cancellation
    confirmCancellation() {
        document.getElementById('orderStatusConfirmation').classList.add('active');
        this.pendingAction = () => this.updateOrderStatus('cancelled', 'Your order has been cancelled. Your payment will be returned to your account.');
    }

    // Confirm action
    async confirmAction() {
        if (this.pendingAction) {
            await this.pendingAction();
            this.cancelConfirmation();
        }
    }

    // Cancel confirmation
    cancelConfirmation() {
        document.getElementById('orderStatusConfirmation').classList.remove('active');
        this.pendingAction = null;
    }

    // Update order status
    async updateOrderStatus(newStatus, message) {
        try {
            const response = await fetch(`order-status-api.php?action=confirm_completion&order_id=${this.currentOrder.order_id}&new_status=${newStatus}`);
            const data = await response.json();

            if (data.success) {
                this.currentOrder.status = newStatus;
                this.renderOverlay();
                
                // Show Casa Gunita dialog with appropriate styling and message
                const cgDialog = document.getElementById('cgDialog');
                const cgHeaderText = document.getElementById('cgHeaderText');
                const cgStatusIcon = document.getElementById('cgStatusIcon');
                const cgMessage = document.getElementById('cgMessage');
                
                cgDialog.className = 'cg-dialog ' + newStatus;
                
                if (newStatus === 'completed') {
                    cgHeaderText.textContent = 'Order Delivered';
                    cgStatusIcon.textContent = '✓';
                    cgMessage.textContent = message || 'Your order has been delivered. Salamat for dining with Casa Gunita!';
                } else if (newStatus === 'cancelled') {
                    cgHeaderText.textContent = 'Order Cancelled';
                    cgStatusIcon.textContent = '✕';
                    cgMessage.textContent = message || 'Admin has cancelled your order. Your payment will be returned via e-transaction.';
                }
                
                document.getElementById('orderStatusConfirmation').classList.add('active');
                
                // Close overlay after showing message
                setTimeout(() => this.close(), 3000);
            } else {
                alert('Failed to update order status. Please try again.');
            }
        } catch (error) {
            console.error('Error updating order:', error);
            alert('Error updating order. Please try again.');
        }
    }

    // Close overlay
    close() {
        if (this.overlay) {
            this.overlay.classList.add('closing');
            setTimeout(() => {
                if (this.wrapper) {
                    this.wrapper.style.display = 'none';
                }
                this.stopAutoRefresh();
            }, 300);
        }
    }

    // Start auto-refresh
    startAutoRefresh() {
        this.autoRefreshInterval = setInterval(() => {
            if (document.querySelector('.order-status-overlay-wrapper:not([style*="display: none"])')) {
                this.loadLatestOrder();
            }
        }, 1500); // Refresh every 1.5 seconds for real-time updates
    }

    // Stop auto-refresh
    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    // Format price
    formatPrice(value) {
        return '₱' + parseFloat(value).toFixed(2);
    }
}

// Initialize global instance
let orderStatusOverlay = new OrderStatusOverlay();
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) hideOverlay();
        });
    }

    function showOrderStatus(orderId) {
        initializeOverlay();

        const overlay = document.getElementById('orderStatusOverlay');
        const idElem = document.getElementById('orderStatusId');
        const timeElem = document.getElementById('orderStatusTime');
        const badgeElem = document.getElementById('orderStatusBadge');
        const messageElem = document.getElementById('orderStatusMessage');
        const confirmBtn = document.getElementById('orderStatusConfirm');

        // Clear previous state
        messageElem.innerHTML = '';
        confirmBtn.style.display = 'none';

        // Show overlay
        overlay.classList.add('active');

        // Fetch order status
        pollOrderStatus(orderId, idElem, timeElem, badgeElem, messageElem, confirmBtn);
    }

    function pollOrderStatus(orderId, idElem, timeElem, badgeElem, messageElem, confirmBtn) {
        fetch('order-status-api.php?order_id=' + encodeURIComponent(orderId))
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    messageElem.innerHTML = '<p style="color: #b03030;">Unable to load order status.</p>';
                    confirmBtn.style.display = 'block';
                    return;
                }

                const order = data.order;
                idElem.textContent = 'Order #' + order.order_id;
                timeElem.textContent = new Date(order.order_date).toLocaleString('en-PH');

                const statusMap = {
                    'pending': { text: 'Pending', class: 'status-pending' },
                    'preparing': { text: 'Preparing', class: 'status-preparing' },
                    'ready': { text: 'Ready for Pickup', class: 'status-ready' },
                    'completed': { text: 'Completed', class: 'status-completed' },
                    'cancelled': { text: 'Cancelled', class: 'status-cancelled' }
                };

                const statusInfo = statusMap[order.status] || { text: order.status, class: 'status-pending' };
                badgeElem.textContent = statusInfo.text;
                badgeElem.className = 'order-status-badge ' + statusInfo.class;

                // Handle completion or cancellation
                if (order.status === 'completed') {
                    messageElem.innerHTML = '<p style="color: #4a7c4e; font-weight: 500;">✓ Your order has been completed! Thank you for your order.</p>';
                    confirmBtn.textContent = 'OK';
                    confirmBtn.style.display = 'block';
                    confirmBtn.onclick = hideOverlay;
                    return;
                } else if (order.status === 'cancelled') {
                    messageElem.innerHTML = '<p style="color: #b03030; font-weight: 500;">✗ Your order has been cancelled by the admin.</p><p style="color: #666; font-size: 13px; margin-top: 8px;">Your payment will be refunded to your account.</p>';
                    confirmBtn.textContent = 'OK';
                    confirmBtn.style.display = 'block';
                    confirmBtn.onclick = hideOverlay;
                    return;
                }

                // Poll again for pending/preparing/ready status
                setTimeout(() => {
                    if (document.getElementById('orderStatusOverlay').classList.contains('active')) {
                        pollOrderStatus(orderId, idElem, timeElem, badgeElem, messageElem, confirmBtn);
                    }
                }, 5000);
            })
            .catch(error => {
                console.error('Order status poll error:', error);
                messageElem.innerHTML = '<p style="color: #b03030;">Network error. Please try again.</p>';
                confirmBtn.textContent = 'Close';
                confirmBtn.style.display = 'block';
            });
    }

    function hideOverlay() {
        const overlay = document.getElementById('orderStatusOverlay');
        if (overlay) {
            overlay.classList.remove('active');
        }
    }

    // Export to global scope
    window.showOrderStatus = showOrderStatus;
    window.hideOrderStatusOverlay = hideOverlay;

    // Auto-initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeOverlay);
    } else {
        initializeOverlay();
    }
})();
