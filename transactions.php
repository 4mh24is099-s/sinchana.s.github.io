<?php
require_once 'header.php';

$userId = $_SESSION['user_id'];

// Filtering options
$search = trim($_GET['search'] ?? '');
$filter_type = $_GET['filter_type'] ?? 'all'; // all, buy, sell

$query = "SELECT * FROM transactions WHERE user_id = :user_id";
$params = [':user_id' => $userId];

if (!empty($search)) {
    $query .= " AND symbol LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

if ($filter_type === 'buy') {
    $query .= " AND transaction_type = 'BUY'";
} elseif ($filter_type === 'sell') {
    $query .= " AND transaction_type = 'SELL'";
}

$query .= " ORDER BY transaction_date DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . sanitize($e->getMessage()) . '</div>';
    $transactions = [];
}
?>

<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <h1 class="h2 mb-1 dashboard-header">Transaction History</h1>
        <p class="text-muted mb-0">Audit log of your portfolio buys and sells</p>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="transactions.php" class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Search by stock symbol..." value="<?= sanitize($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select class="form-select" name="filter_type">
                    <option value="all" <?= ($filter_type === 'all') ? 'selected' : '' ?>>All Transaction Types</option>
                    <option value="buy" <?= ($filter_type === 'buy') ? 'selected' : '' ?>>BUY Orders Only</option>
                    <option value="sell" <?= ($filter_type === 'sell') ? 'selected' : '' ?>>SELL Orders Only</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="transactions.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 custom-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Symbol</th>
                        <th>Type</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $tx): 
                            $type = $tx['transaction_type'];
                            $badge_class = ($type === 'BUY') ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                            $qty = (float)$tx['quantity'];
                            $price = (float)$tx['price'];
                            $total = $qty * $price;
                            $date_formatted = date('M d, Y H:i:s', strtotime($tx['transaction_date']));
                        ?>
                            <tr>
                                <td class="fs-8 text-muted"><?= $date_formatted ?></td>
                                <td><span class="badge bg-secondary text-light fw-bold"><?= sanitize($tx['symbol']) ?></span></td>
                                <td>
                                    <span class="badge <?= $badge_class ?> rounded-pill px-2 py-1 fs-9 fw-semibold">
                                        <?= $type ?>
                                    </span>
                                </td>
                                <td class="text-end font-table-data"><?= number_format($qty, 4) ?></td>
                                <td class="text-end font-table-data">$<?= number_format($price, 2) ?></td>
                                <td class="text-end font-table-data fw-semibold">$<?= number_format($total, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-text fs-2 mb-2 d-block"></i>
                                No transactions found matching criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
