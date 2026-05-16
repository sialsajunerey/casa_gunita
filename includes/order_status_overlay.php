<?php
// Get current user's active order (if any)
$currentActiveOrder = null;
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $activeOrderStmt = mysqli_prepare($conn, 
        "SELECT order_id, status, created_at, total_amount 
         FROM orders 
         WHERE user_id = ? AND status IN ('pending', 'preparing', 'ready') 
         ORDER BY created_at DESC LIMIT 1");
    mysqli_stmt_bind_param($activeOrderStmt, 'i', $user_id);
    mysqli_stmt_execute($activeOrderStmt);
    $activeOrderResult = mysqli_stmt_get_result($activeOrderStmt);
    $currentActiveOrder = mysqli_fetch_assoc($activeOrderResult);
}
?>

<!-- Order Status Overlay Card (Top Right) -->
<?php if ($currentActiveOrder): ?>
<div class="order-status-overlay-card">
    <div class="order-status-header">
        <h3 class="order-status-title">Active Order</h3>
        <button class="order-status-close" onclick="toggleOrderStatusOverlay()">&times;</button>
    </div>
    <div class="order-status-content">
        <div class="order-status-number">
            <span class="label">Order #</span>
            <span class="value"><?= htmlspecialchars($currentActiveOrder['order_id']) ?></span>
        </div>
        <div class="order-status-status">
            <span class="label">Status</span>
            <span class="status-badge status-<?= htmlspecialchars($currentActiveOrder['status']) ?>">
                <?= ucfirst(htmlspecialchars($currentActiveOrder['status'])) ?>
            </span>
        </div>
        <div class="order-status-amount">
            <span class="label">Amount</span>
            <span class="value">₱<?= number_format((float)$currentActiveOrder['total_amount'], 2) ?></span>
        </div>
    </div>
    <div class="order-status-footer">
        <a href="order_status.php" class="order-status-link">View All Orders →</a>
    </div>
</div>

<style>
.order-status-overlay-card {
    position: fixed;
    top: 66px;
    right: 20px;
    width: 300px;
    background: rgba(33, 3, 3, 0.95);
    border: 1px solid rgba(232, 209, 145, 0.2);
    border-radius: 8px;
    padding: 16px;
    z-index: 100;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(4px);
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.order-status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    border-bottom: 1px solid rgba(232, 209, 145, 0.15);
    padding-bottom: 12px;
}

.order-status-title {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #e8d191;
}

.order-status-close {
    background: none;
    border: none;
    color: #e8d191;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s ease;
}

.order-status-close:hover {
    color: #dce4cf;
}

.order-status-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 12px;
}

.order-status-number,
.order-status-status,
.order-status-amount {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.order-status-number .label,
.order-status-status .label,
.order-status-amount .label {
    color: rgba(220, 228, 207, 0.6);
    font-weight: 500;
}

.order-status-number .value,
.order-status-amount .value {
    color: #dce4cf;
    font-weight: 600;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-pending {
    background: rgba(176, 48, 48, 0.2);
    color: #ff9999;
}

.status-preparing {
    background: rgba(232, 209, 145, 0.2);
    color: #e8d191;
}

.status-ready {
    background: rgba(100, 160, 80, 0.2);
    color: #90d47f;
}

.order-status-footer {
    padding-top: 12px;
    border-top: 1px solid rgba(232, 209, 145, 0.15);
    text-align: center;
}

.order-status-link {
    color: #e8d191;
    font-size: 0.85rem;
    text-decoration: none;
    transition: color 0.2s ease;
}

.order-status-link:hover {
    color: #dce4cf;
}

@media (max-width: 768px) {
    .order-status-overlay-card {
        width: 280px;
        right: 10px;
        top: 60px;
    }
}
</style>

<script>
function toggleOrderStatusOverlay() {
    const card = document.querySelector('.order-status-overlay-card');
    if (card) {
        card.style.display = card.style.display === 'none' ? 'block' : 'none';
    }
}

// Auto-refresh order status every 10 seconds
setInterval(function() {
    const card = document.querySelector('.order-status-overlay-card');
    if (!card) return;
    
    const orderNumber = card.querySelector('.order-status-number .value');
    if (!orderNumber) return;
    
    // Fetch the latest order status via AJAX
    fetch('../includes/get_order_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.order) {
                // Update status if changed
                const statusBadge = card.querySelector('.status-badge');
                const newStatus = data.order.status;
                const oldStatus = statusBadge ? statusBadge.textContent.toLowerCase() : '';
                
                if (statusBadge && oldStatus !== newStatus) {
                    statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    statusBadge.className = 'status-badge status-' + newStatus;
                    
                    // Show appropriate dialog based on status
                    if (newStatus === 'completed') {
                        showCompletedDialog();
                    } else if (newStatus === 'cancelled') {
                        showCancelledDialog();
                    }
                }
                
                // If order is completed or cancelled, hide the overlay after 5 seconds
                if (newStatus === 'completed' || newStatus === 'cancelled') {
                    setTimeout(function() {
                        card.style.display = 'none';
                    }, 5000);
                }
            }
        })
        .catch(err => console.log('Order status refresh:', err));
}, 10000);

function showCompletedDialog() {
    const modal = document.createElement('div');
    modal.className = 'order-status-modal-overlay';
    modal.innerHTML = `
        <div class="order-status-modal">
            <div class="modal-header completed">
                <h2>✓ Order Delivered</h2>
            </div>
            <div class="modal-body">
                <div class="modal-icon success-icon"></div>
                <p class="modal-message">Your order has been delivered. Thank you for your order!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="this.closest('.order-status-modal-overlay').remove()">Confirm</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.classList.add('active');
}

function showCancelledDialog() {
    const modal = document.createElement('div');
    modal.className = 'order-status-modal-overlay';
    modal.innerHTML = `
        <div class="order-status-modal">
            <div class="modal-header cancelled">
                <h2>Order Cancelled</h2>
            </div>
            <div class="modal-body">
                <div class="modal-icon warning-icon"></div>
                <p class="modal-message">Admin has cancelled your order. Your payment will be returned to you through e-transaction.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="this.closest('.order-status-modal-overlay').remove()">Confirm</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.classList.add('active');
}
</script>

<style>
.order-status-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 2000;
    align-items: center;
    justify-content: center;
}

.order-status-modal-overlay.active {
    display: flex;
}

.order-status-modal {
    background: rgba(33, 3, 3, 0.98);
    border: 1px solid rgba(232, 209, 145, 0.2);
    border-radius: 12px;
    max-width: 420px;
    width: 90%;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    animation: modalSlideUp 0.3s ease;
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.order-status-modal .modal-header {
    background: linear-gradient(135deg, rgba(139, 111, 71, 0.3), rgba(232, 209, 145, 0.1));
    border-bottom: 1px solid rgba(232, 209, 145, 0.2);
    padding: 24px;
}

.order-status-modal .modal-header.completed {
    background: linear-gradient(135deg, rgba(100, 160, 80, 0.3), rgba(144, 212, 127, 0.1));
    border-bottom-color: rgba(144, 212, 127, 0.3);
}

.order-status-modal .modal-header.cancelled {
    background: linear-gradient(135deg, rgba(176, 48, 48, 0.3), rgba(255, 153, 153, 0.1));
    border-bottom-color: rgba(255, 153, 153, 0.3);
}

.order-status-modal h2 {
    margin: 0;
    font-size: 1.3rem;
    color: #e8d191;
    font-weight: 600;
}

.order-status-modal .modal-header.completed h2 {
    color: #90d47f;
}

.order-status-modal .modal-header.cancelled h2 {
    color: #ff9999;
}

.order-status-modal .modal-body {
    padding: 32px 24px;
    text-align: center;
}

.order-status-modal .modal-icon {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
}

.order-status-modal .modal-message {
    color: #dce4cf;
    font-size: 1rem;
    margin: 0;
    line-height: 1.6;
}

.order-status-modal .modal-footer {
    display: flex;
    padding: 24px;
    border-top: 1px solid rgba(232, 209, 145, 0.1);
    justify-content: center;
}

.order-status-modal .btn {
    padding: 12px 32px;
    font-size: 0.9rem;
    text-align: center;
    border-radius: 6px;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
}

.order-status-modal .btn-primary {
    background: #8B6F47;
    color: #dce4cf;
}

.order-status-modal .btn-primary:hover {
    background: #9d8359;
}

@media (max-width: 480px) {
    .order-status-modal {
        max-width: 95%;
    }
}
</style>
<?php endif; ?>
