class OrderStatusOverlay {
    constructor() {
        this.wrapper = null;
        this.autoRefreshInterval = null;
        this.currentOrder = null;
        this.pendingAction = null;
    }

    async init(autoRefresh = true) {
        if (!document.getElementById('cgOverlayWrapper')) {
            this.createOverlay();
        }
        this.wrapper = document.getElementById('cgOverlayWrapper');
        await this.loadLatestOrder();
        if (autoRefresh) this.startAutoRefresh();
    }

    createOverlay() {
        const html = `
        <div id="cgOverlayWrapper" class="order-status-overlay-wrapper">
            <div class="order-status-panel">

                <div class="order-status-header">
                    <span class="order-status-header-label">My order</span>
                    <button class="order-status-close" onclick="orderStatusOverlay.close()" aria-label="Close">&times;</button>
                </div>

                <div class="order-status-identity">
                    <div>
                        <p class="order-status-number" id="cgOrderNumber">#00000</p>
                        <p class="order-status-meta" id="cgOrderMeta">—</p>
                    </div>
                    <span class="order-status-badge pending" id="cgStatusBadge">
                        <span class="order-status-badge-dot"></span>
                        <span id="cgStatusText">Pending</span>
                    </span>
                </div>

                <div class="order-status-ornament">
                    <span class="order-status-ornament-dot"></span>
                    <span class="order-status-ornament-dot"></span>
                    <span class="order-status-ornament-dot"></span>
                    <span class="order-status-ornament-line"></span>
                </div>

                <div class="order-status-tabs">
                    <button class="order-status-tab-btn active" onclick="orderStatusOverlay.switchTab(event, 'Items')">Items</button>
                    <button class="order-status-tab-btn" onclick="orderStatusOverlay.switchTab(event, 'Details')">Details</button>
                </div>

                <div id="cgTabItems" class="order-status-tab-pane active">
                    <div class="order-status-items-list" id="cgItemsList">
                        <div class="order-status-item-row">
                            <div><p class="order-status-item-name">Loading...</p></div>
                        </div>
                    </div>
                    <div class="order-status-total-row">
                        <span class="order-status-total-label">Total</span>
                        <span class="order-status-total-amount" id="cgTotal">&#8369;0.00</span>
                    </div>
                </div>

                <div id="cgTabDetails" class="order-status-tab-pane">
                    <div class="order-status-details">
                        <div class="order-status-detail-cell">
                            <p class="order-status-detail-label">Order #</p>
                            <p class="order-status-detail-value" id="cgDetailId">—</p>
                        </div>
                        <div class="order-status-detail-cell">
                            <p class="order-status-detail-label">Type</p>
                            <p class="order-status-detail-value" id="cgDetailType">—</p>
                        </div>
                        <div class="order-status-detail-cell">
                            <p class="order-status-detail-label">Table</p>
                            <p class="order-status-detail-value" id="cgDetailTable">—</p>
                        </div>
                        <div class="order-status-detail-cell">
                            <p class="order-status-detail-label">Time</p>
                            <p class="order-status-detail-value" id="cgDetailTime">—</p>
                        </div>
                        <div class="order-status-detail-cell full">
                            <p class="order-status-detail-label">Payment</p>
                            <p class="order-status-detail-value" id="cgDetailPayment">—</p>
                        </div>
                    </div>
                </div>

                <div class="order-status-actions" id="cgActions"></div>
            </div>
        </div>

        <div id="cgDialogOverlay" class="cg-dialog-overlay">
            <div class="cg-dialog" id="cgDialog">
                <div class="cg-dialog-header">
                    <span class="cg-dialog-header-text" id="cgDialogTitle">Order cancelled</span>
                    <span class="cg-dialog-header-badge" id="cgDialogBadge">
                        <span class="cg-dialog-header-badge-dot"></span>
                        <span id="cgDialogBadgeText">Cancelled</span>
                    </span>
                </div>
                <div class="cg-dialog-icon-wrap">
                    <div class="cg-dialog-icon" id="cgDialogIcon">&times;</div>
                </div>
                <div class="cg-dialog-message-section">
                    <div class="cg-dialog-ornament">
                        <span></span><span></span><span></span>
                    </div>
                    <p class="cg-dialog-message" id="cgDialogMessage">Your order has been cancelled.</p>
                    <div class="cg-dialog-order-info" id="cgDialogOrderInfo"></div>
                    <div class="cg-dialog-ornament" style="margin-top:14px;margin-bottom:0;">
                        <span></span><span></span><span></span>
                    </div>
                </div>
                <div class="cg-dialog-btn-section">
                    <button class="cg-dialog-btn" onclick="orderStatusOverlay.closeDialog()">Confirm</button>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', html);
    }

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

    renderOverlay() {
        if (!this.currentOrder) return;
        const order = this.currentOrder;

        const typeLabel = order.order_type === 'takeout'
            ? 'Pick-up'
            : order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1);

        document.getElementById('cgOrderNumber').textContent = '#' + String(order.order_id).padStart(5, '0');
        document.getElementById('cgOrderMeta').textContent =
            typeLabel + (order.table_number ? ' \u00b7 Table ' + order.table_number : '') + ' \u00b7 ' + (order.formatted_time || '');

        const badge = document.getElementById('cgStatusBadge');
        badge.className = 'order-status-badge ' + order.status;
        document.getElementById('cgStatusText').textContent =
            order.status.charAt(0).toUpperCase() + order.status.slice(1);

        const list = document.getElementById('cgItemsList');
        if (order.items && order.items.length > 0) {
            list.innerHTML = order.items.map(item => `
                <div class="order-status-item-row">
                    <div>
                        <p class="order-status-item-name">${item.name}</p>
                        <p class="order-status-item-qty">x${item.quantity}</p>
                    </div>
                    <span class="order-status-item-price">${this.formatPrice(item.subtotal)}</span>
                </div>`).join('');
        }

        document.getElementById('cgTotal').textContent = order.formatted_total || '\u20b10.00';
        document.getElementById('cgDetailId').textContent = '#' + String(order.order_id).padStart(5, '0');
        document.getElementById('cgDetailType').textContent = typeLabel;
        document.getElementById('cgDetailTable').textContent = order.table_number ? 'Table ' + order.table_number : '—';
        document.getElementById('cgDetailTime').textContent = order.formatted_time || '—';
        document.getElementById('cgDetailPayment').textContent =
            (order.payment_method || '—') + ' \u00b7 ' + (order.formatted_total || '\u20b10.00');

        this.renderActions();
    }

    renderActions() {
        const container = document.getElementById('cgActions');
        const status = this.currentOrder.status;

        if (status === 'completed' || status === 'cancelled') {
            container.innerHTML = `
                <button class="order-status-btn order-status-btn-ghost" onclick="orderStatusOverlay.close()">Close</button>`;
        } else {
            container.innerHTML = `
                <button class="order-status-btn order-status-btn-success" onclick="orderStatusOverlay.confirmCompletion()">Mark as received</button>
                <button class="order-status-btn order-status-btn-danger" onclick="orderStatusOverlay.confirmCancellation()">Cancel order</button>`;
        }
    }

    switchTab(e, id) {
        document.querySelectorAll('.order-status-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.order-status-tab-pane').forEach(p => p.classList.remove('active'));
        e.target.classList.add('active');
        document.getElementById('cgTab' + id).classList.add('active');
    }

    confirmCompletion() {
        this.updateOrderStatus('completed', 'Your order has been delivered. Salamat for dining with Casa Gunita!');
    }

    confirmCancellation() {
        this.updateOrderStatus('cancelled', 'Your order has been cancelled. Your payment will be returned via e-transaction.');
    }

    async updateOrderStatus(newStatus, message) {
        try {
            const response = await fetch(
                'order-status-api.php?action=confirm_completion&order_id=' + this.currentOrder.order_id + '&new_status=' + newStatus
            );
            const data = await response.json();

            if (data.success) {
                this.currentOrder.status = newStatus;
                this.renderOverlay();
                this.showDialog(newStatus, message);
                setTimeout(() => this.close(), 4000);
            } else {
                alert('Failed to update order status. Please try again.');
            }
        } catch (error) {
            console.error('Error updating order:', error);
            alert('Error updating order. Please try again.');
        }
    }

    showDialog(status, message) {
        const dialog  = document.getElementById('cgDialog');
        const overlay = document.getElementById('cgDialogOverlay');
        const title   = document.getElementById('cgDialogTitle');
        const icon    = document.getElementById('cgDialogIcon');
        const msg     = document.getElementById('cgDialogMessage');
        const badge   = document.getElementById('cgDialogBadge');
        const badgeTxt = document.getElementById('cgDialogBadgeText');
        const orderInfo = document.getElementById('cgDialogOrderInfo');

        dialog.className = 'cg-dialog ' + status;

        if (status === 'completed') {
            title.textContent  = 'Order delivered';
            icon.textContent   = '\u2713';
            badgeTxt.textContent = 'Completed';
        } else {
            title.textContent  = 'Order cancelled';
            icon.textContent   = '\u00d7';
            badgeTxt.textContent = 'Cancelled';
        }

        msg.textContent = message;

        const orderId = '#' + String(this.currentOrder.order_id).padStart(5, '0');
        const total   = this.currentOrder.formatted_total || '\u20b10.00';
        const type    = this.currentOrder.order_type === 'takeout'
            ? 'Pick-up'
            : this.currentOrder.order_type.charAt(0).toUpperCase() + this.currentOrder.order_type.slice(1);

        orderInfo.innerHTML = `
            <div class="cg-dialog-order-row">
                <span class="cg-dialog-order-label">Order</span>
                <span class="cg-dialog-order-value">${orderId}</span>
            </div>
            <div class="cg-dialog-order-row">
                <span class="cg-dialog-order-label">Type</span>
                <span class="cg-dialog-order-value">${type}</span>
            </div>
            <div class="cg-dialog-order-row">
                <span class="cg-dialog-order-label">Total</span>
                <span class="cg-dialog-order-value amount">${total}</span>
            </div>`;

        overlay.classList.add('active');
    }

    closeDialog() {
        document.getElementById('cgDialogOverlay').classList.remove('active');
    }

    close() {
        this.closeDialog();
        if (this.wrapper) {
            this.wrapper.classList.add('closing');
            setTimeout(() => {
                this.wrapper.style.display = 'none';
                this.stopAutoRefresh();
            }, 300);
        }
    }

    startAutoRefresh() {
        this.autoRefreshInterval = setInterval(() => {
            if (this.wrapper && this.wrapper.style.display !== 'none') {
                this.loadLatestOrder();
            }
        }, 1500);
    }

    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    formatPrice(value) {
        return '\u20b1' + parseFloat(value).toFixed(2);
    }
}

const orderStatusOverlay = new OrderStatusOverlay();