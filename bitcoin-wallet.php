<?php
session_start();
require_once 'config/database.php';
require_once 'classes/BitcoinService.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$bitcoin = new BitcoinService($db);

$wallet = $bitcoin->getUserWallet($_SESSION['user_id']);
$transactions = $bitcoin->getTransactionHistory($_SESSION['user_id'], 20);
$btc_price = $bitcoin->getBitcoinPrice();

include 'views/header.php';
?>

<style>
.wallet-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.wallet-header {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 40px rgba(245, 158, 11, 0.3);
}

.wallet-balance {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 2rem;
}

.balance-main {
    flex: 1;
}

.balance-btc {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.balance-usd {
    font-size: 1.2rem;
    opacity: 0.9;
}

.wallet-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.wallet-btn {
    padding: 0.875rem 1.5rem;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-deposit {
    background: white;
    color: #d97706;
}

.btn-send {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid white;
}

.btn-deposit:hover,
.btn-send:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    transition: all 0.3s;
}

.stat-card:hover {
    border-color: var(--primary-blue);
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.stat-label {
    color: var(--text-gray);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-white);
}

.transactions-section {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
}

.transaction-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.2s;
}

.transaction-item:last-child {
    border-bottom: none;
}

.transaction-item:hover {
    background: rgba(66, 103, 245, 0.05);
}

.transaction-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-right: 1rem;
}

.transaction-icon.deposit {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-green);
}

.transaction-icon.withdrawal {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-red);
}

.transaction-icon.transfer {
    background: rgba(66, 103, 245, 0.1);
    color: var(--primary-blue);
}

.transaction-details {
    flex: 1;
}

.transaction-type {
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 0.25rem;
}

.transaction-date {
    font-size: 0.85rem;
    color: var(--text-gray);
}

.transaction-amount {
    text-align: right;
}

.amount-btc {
    font-weight: 700;
    font-size: 1.1rem;
}

.amount-usd {
    font-size: 0.85rem;
    color: var(--text-gray);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.5rem;
}

.status-pending {
    background: rgba(245, 158, 11, 0.2);
    color: var(--warning-orange);
}

.status-confirmed {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success-green);
}

.status-failed {
    background: rgba(239, 68, 68, 0.2);
    color: var(--danger-red);
}

@media (max-width: 768px) {
    .wallet-balance {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .balance-btc {
        font-size: 2rem;
    }
    
    .wallet-actions {
        width: 100%;
    }
    
    .wallet-btn {
        flex: 1;
    }
}
</style>

<div class="wallet-container">
    
    <!-- Wallet Header -->
    <div class="wallet-header">
        <div class="wallet-balance">
            <div class="balance-main">
                <h1 style="margin: 0 0 1rem; font-size: 1.5rem;">
                    <i class="bi bi-wallet2"></i> Bitcoin Wallet
                </h1>
                <div class="balance-btc">
                    <i class="bi bi-currency-bitcoin"></i>
                    <?php echo number_format($wallet['balance'], 8); ?> BTC
                </div>
                <div class="balance-usd">
                    ≈ $<?php echo number_format($bitcoin->btcToUsd($wallet['balance']), 2); ?> USD
                </div>
            </div>
            <div class="wallet-actions">
                <button class="wallet-btn btn-deposit" onclick="openDepositModal()">
                    <i class="bi bi-plus-circle-fill"></i> Deposit
                </button>
                <button class="wallet-btn btn-send" onclick="openSendModal()">
                    <i class="bi bi-send-fill"></i> Send
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">
                <i class="bi bi-arrow-down-circle-fill text-success"></i> Total Received
            </div>
            <div class="stat-value">
                <i class="bi bi-currency-bitcoin"></i> <?php echo number_format($wallet['total_received'], 8); ?>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">
                <i class="bi bi-arrow-up-circle-fill text-danger"></i> Total Sent
            </div>
            <div class="stat-value">
                <i class="bi bi-currency-bitcoin"></i> <?php echo number_format($wallet['total_sent'], 8); ?>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">
                <i class="bi bi-graph-up-arrow"></i> BTC Price (USD)
            </div>
            <div class="stat-value">
                $<?php echo number_format($btc_price, 2); ?>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="transactions-section">
        <h2 class="mb-4">
            <i class="bi bi-clock-history"></i> Recent Transactions
        </h2>
        
        <?php if(empty($transactions)): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox" style="font-size: 4rem; opacity: 0.3;"></i>
            <p class="text-muted mt-3">No transactions yet</p>
        </div>
        <?php else: ?>
            <?php foreach($transactions as $tx): ?>
            <div class="transaction-item">
                <div class="d-flex align-items-center flex-grow-1">
                    <div class="transaction-icon <?php echo strtolower($tx['transaction_type']); ?>">
                        <?php if($tx['transaction_type'] === 'deposit'): ?>
                            <i class="bi bi-arrow-down-circle-fill"></i>
                        <?php elseif($tx['transaction_type'] === 'withdrawal'): ?>
                            <i class="bi bi-arrow-up-circle-fill"></i>
                        <?php else: ?>
                            <i class="bi bi-arrow-left-right"></i>
                        <?php endif; ?>
                    </div>
                    <div class="transaction-details">
                        <div class="transaction-type">
                            <?php echo ucfirst($tx['transaction_type']); ?>
                            <?php if($tx['to_username'] && $tx['transaction_type'] === 'transfer'): ?>
                                <span class="text-muted">to <?php echo htmlspecialchars($tx['to_username']); ?></span>
                            <?php endif; ?>
                            <?php if($tx['from_username'] && $tx['transaction_type'] === 'transfer'): ?>
                                <span class="text-muted">from <?php echo htmlspecialchars($tx['from_username']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="transaction-date">
                            <?php echo date('M j, Y g:i A', strtotime($tx['created_at'])); ?>
                        </div>
                        <?php if($tx['description']): ?>
                        <div class="text-muted" style="font-size: 0.85rem; margin-top: 0.25rem;">
                            <?php echo htmlspecialchars($tx['description']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="transaction-amount">
                    <div class="amount-btc <?php echo ($tx['transaction_type'] === 'deposit' || ($tx['transaction_type'] === 'transfer' && $tx['to_user_id'] == $_SESSION['user_id'])) ? 'text-success' : 'text-danger'; ?>">
                        <?php echo ($tx['transaction_type'] === 'deposit' || ($tx['transaction_type'] === 'transfer' && $tx['to_user_id'] == $_SESSION['user_id'])) ? '+' : '-'; ?>
                        <i class="bi bi-currency-bitcoin"></i> <?php echo number_format($tx['amount'], 8); ?>
                    </div>
                    <div class="amount-usd">
                        <?php if($tx['usd_amount']): ?>
                            ≈ $<?php echo number_format($tx['usd_amount'], 2); ?>
                        <?php endif; ?>
                    </div>
                    <span class="status-badge status-<?php echo strtolower($tx['status']); ?>">
                        <?php echo ucfirst($tx['status']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Deposit Modal -->
<div class="modal fade" id="depositModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle-fill"></i> Deposit Bitcoin
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill"></i> 
                    <strong>Note:</strong> Bitcoin deposits are currently being implemented. Please check back soon!
                </div>
                <p class="text-muted">To deposit Bitcoin, you'll be able to send BTC to your unique wallet address. Deposits require 3 confirmations.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Send Modal -->
<div class="modal fade" id="sendModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-send-fill"></i> Send Bitcoin
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="sendBitcoinForm">
                    <div class="mb-3">
                        <label class="form-label">Recipient Username</label>
                        <input type="text" class="form-control" id="recipientUsername" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (BTC)</label>
                        <input type="number" step="0.00000001" class="form-control" id="sendAmount" required>
                        <div class="form-text">Available: <?php echo number_format($wallet['balance'], 8); ?> BTC</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="sendDescription" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendBitcoin()">
                    <i class="bi bi-send-fill"></i> Send Bitcoin
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openDepositModal() {
    new bootstrap.Modal(document.getElementById('depositModal')).show();
}

function openSendModal() {
    new bootstrap.Modal(document.getElementById('sendModal')).show();
}

function sendBitcoin() {
    alert('Bitcoin send functionality coming soon!');
}

// Real-time price updates
setInterval(() => {
    fetch('/api/bitcoin.php?action=get_price')
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                // Update price display if needed
                console.log('BTC Price updated:', data.price_usd);
            }
        });
}, 60000); // Update every minute
</script>

<?php include 'views/footer.php'; ?>