<?php
require_once 'header.php';

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Watchlist Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
        $company_name = trim($_POST['company_name'] ?? '');
        $target_price = !empty($_POST['target_price']) ? (float)$_POST['target_price'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if (empty($symbol) || empty($company_name)) {
            $error = 'Stock Symbol and Company Name are required.';
        } else {
            try {
                // Check if symbol already on watchlist
                $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND symbol = ?");
                $stmt->execute([$userId, $symbol]);
                if ($stmt->fetch()) {
                    $error = "$symbol is already on your watchlist.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO watchlist (user_id, symbol, company_name, target_price, notes) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $symbol, $company_name, $target_price, $notes]);
                    $success = "$symbol added to watchlist!";
                }
            } catch (PDOException $e) {
                $error = 'Error adding to watchlist: ' . $e->getMessage();
            }
        }
    }

    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error = 'Invalid watchlist item ID.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT symbol FROM watchlist WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
                $item = $stmt->fetch();
                
                if ($item) {
                    $stmt = $pdo->prepare("DELETE FROM watchlist WHERE id = ? AND user_id = ?");
                    $stmt->execute([$id, $userId]);
                    $success = $item['symbol'] . " removed from watchlist.";
                } else {
                    $error = 'Watchlist item not found.';
                }
            } catch (PDOException $e) {
                $error = 'Error removing watchlist item: ' . $e->getMessage();
            }
        }
    }
}

// Search and fetch watchlist
$search = trim($_GET['search'] ?? '');
$query = "SELECT * FROM watchlist WHERE user_id = :user_id";
$params = [':user_id' => $userId];

if (!empty($search)) {
    $query .= " AND (symbol LIKE :search OR company_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$query .= " ORDER BY symbol ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $watchlist_items = $stmt->fetchAll();
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . sanitize($e->getMessage()) . '</div>';
    $watchlist_items = [];
}
?>

<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <h1 class="h2 mb-1 dashboard-header">My Watchlist</h1>
        <p class="text-muted mb-0">Track stocks of interest before buying them</p>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addWatchlistModal">
            <i class="bi bi-plus-lg me-1"></i> Add to Watchlist
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= sanitize($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= sanitize($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Search Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="watchlist.php" class="row g-2 align-items-center">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Search watchlist by symbol or company name..." value="<?= sanitize($search) ?>">
                </div>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-funnel me-1"></i> Search</button>
                <a href="watchlist.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Watchlist Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 custom-table">
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th>Company Name</th>
                        <th class="text-end">Target Price</th>
                        <th>Notes</th>
                        <th>Added Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($watchlist_items) > 0): ?>
                        <?php foreach ($watchlist_items as $item): ?>
                            <tr>
                                <td><span class="badge bg-secondary text-light fw-bold"><?= sanitize($item['symbol']) ?></span></td>
                                <td class="fw-semibold font-table-data"><?= sanitize($item['company_name']) ?></td>
                                <td class="text-end fw-semibold font-table-data text-primary">
                                    <?= $item['target_price'] ? '$' . number_format($item['target_price'], 2) : '<span class="text-muted fs-8">-</span>' ?>
                                </td>
                                <td><small class="text-muted"><?= sanitize($item['notes'] ?? '') ?></small></td>
                                <td class="fs-8 text-muted"><?= date('M d, Y', strtotime($item['created_at'])) ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <!-- Convert to Portfolio Position Button -->
                                        <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1"
                                                onclick="openBuyModal('<?= sanitize($item['symbol']) ?>', '<?= sanitize($item['company_name']) ?>')">
                                            <i class="bi bi-cart-plus"></i> Buy
                                        </button>
                                        <!-- Delete Button -->
                                        <form action="watchlist.php" method="POST" onsubmit="return confirm('Remove <?= sanitize($item['symbol']) ?> from your watchlist?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-eye-slash fs-2 mb-2 d-block"></i>
                                Watchlist is empty. Add stocks you want to follow.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Watchlist Modal -->
<div class="modal fade" id="addWatchlistModal" tabindex="-1" aria-labelledby="addWatchlistModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="addWatchlistModalLabel"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Add to Watchlist</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="watchlist.php" method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="symbol" class="form-label">Stock Symbol *</label>
                        <input type="text" class="form-control" id="symbol" name="symbol" placeholder="e.g. NVDA" required maxlength="10">
                        <div class="invalid-feedback">Please enter a stock symbol.</div>
                    </div>
                    <div class="mb-3">
                        <label for="company_name" class="form-label">Company Name *</label>
                        <input type="text" class="form-control" id="company_name" name="company_name" placeholder="e.g. NVIDIA Corporation" required>
                        <div class="invalid-feedback">Please enter the company name.</div>
                    </div>
                    <div class="mb-3">
                        <label for="target_price" class="form-label">Target Buy Price ($)</label>
                        <input type="number" step="0.01" class="form-control" id="target_price" name="target_price" placeholder="e.g. 400.00" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="e.g. AI sector leader, buy during next pullback."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add to Watchlist</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Convert / Buy Stock Modal (Indirectly routes to portfolio.php) -->
<div class="modal fade" id="buyFromWatchlistModal" tabindex="-1" aria-labelledby="buyFromWatchlistLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="buyFromWatchlistLabel"><i class="bi bi-cart-plus-fill text-success me-2"></i>Buy Stock Position</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="portfolio.php" method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="buy_symbol" class="form-label">Stock Symbol</label>
                            <input type="text" class="form-control" id="buy_symbol" name="symbol" readonly required>
                        </div>
                        <div class="col-md-6">
                            <label for="buy_company" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="buy_company" name="company_name" readonly required>
                        </div>
                        <div class="col-md-6">
                            <label for="buy_qty" class="form-label">Quantity *</label>
                            <input type="number" step="0.0001" class="form-control" id="buy_qty" name="quantity" placeholder="0.0000" min="0.0001" required>
                        </div>
                        <div class="col-md-6">
                            <label for="buy_price_val" class="form-label">Buy Price ($) *</label>
                            <input type="number" step="0.01" class="form-control" id="buy_price_val" name="buy_price" placeholder="0.00" min="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label for="buy_curr_price_val" class="form-label">Current Price ($) *</label>
                            <input type="number" step="0.01" class="form-control" id="buy_curr_price_val" name="current_price" placeholder="0.00" min="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label for="buy_date" class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" id="buy_date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label for="buy_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="buy_notes" name="notes" rows="2" placeholder="Position bought from watchlist."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Execute Buy Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openBuyModal(symbol, company) {
        document.getElementById('buy_symbol').value = symbol;
        document.getElementById('buy_company').value = company;
        document.getElementById('buy_qty').value = '';
        document.getElementById('buy_price_val').value = '';
        document.getElementById('buy_curr_price_val').value = '';
        
        var modal = new bootstrap.Modal(document.getElementById('buyFromWatchlistModal'));
        modal.show();
    }

    // Client-side form validation
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
</script>

<?php require_once 'footer.php'; ?>
