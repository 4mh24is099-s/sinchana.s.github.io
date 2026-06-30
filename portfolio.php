<?php
require_once 'header.php';

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. ADD STOCK POSITION
    if ($action === 'add') {
        $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
        $company_name = trim($_POST['company_name'] ?? '');
        $quantity = (float)($_POST['quantity'] ?? 0);
        $buy_price = (float)($_POST['buy_price'] ?? 0);
        $current_price = (float)($_POST['current_price'] ?? 0);
        $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');

        // Validation
        if (empty($symbol) || empty($company_name) || $quantity <= 0 || $buy_price < 0 || $current_price < 0) {
            $error = 'Please fill in all required fields. Quantity must be greater than 0.';
        } else {
            try {
                // Check if symbol already exists for this user
                $stmt = $pdo->prepare("SELECT id, quantity, buy_price FROM stocks WHERE user_id = ? AND symbol = ?");
                $stmt->execute([$userId, $symbol]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $error = "You already hold $symbol. Please use the Edit or Buy/Sell options to update your position.";
                } else {
                    // Handle file upload
                    $logo_path = null;
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['logo']['tmp_name'];
                        $file_name = $_FILES['logo']['name'];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (in_array($file_ext, $allowed_exts)) {
                            $new_file_name = $userId . '_' . $symbol . '_' . time() . '.' . $file_ext;
                            $logo_path = 'uploads/' . $new_file_name;
                            move_uploaded_file($file_tmp, __DIR__ . '/' . $logo_path);
                        } else {
                            $error = 'Invalid image extension. Only JPG, PNG, GIF, and WEBP allowed.';
                        }
                    }

                    if (empty($error)) {
                        $pdo->beginTransaction();
                        // Insert stock
                        $stmt = $pdo->prepare("INSERT INTO stocks (user_id, symbol, company_name, quantity, buy_price, current_price, purchase_date, logo_path, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$userId, $symbol, $company_name, $quantity, $buy_price, $current_price, $purchase_date, $logo_path, $notes]);

                        // Insert BUY transaction
                        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, symbol, transaction_type, quantity, price, transaction_date) VALUES (?, ?, 'BUY', ?, ?, ?)");
                        $stmt->execute([$userId, $symbol, $quantity, $buy_price, $purchase_date . ' ' . date('H:i:s')]);

                        $pdo->commit();
                        $success = "$symbol holding added successfully!";
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Error adding stock: ' . $e->getMessage();
            }
        }
    }

    // 2. EDIT STOCK DETAILS
    elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $company_name = trim($_POST['company_name'] ?? '');
        $quantity = (float)($_POST['quantity'] ?? 0);
        $buy_price = (float)($_POST['buy_price'] ?? 0);
        $current_price = (float)($_POST['current_price'] ?? 0);
        $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');

        if ($id <= 0 || empty($company_name) || $quantity < 0 || $buy_price < 0 || $current_price < 0) {
            $error = 'Invalid data provided for editing.';
        } else {
            try {
                // Get existing logo and symbol
                $stmt = $pdo->prepare("SELECT symbol, logo_path FROM stocks WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
                $stock = $stmt->fetch();

                if (!$stock) {
                    $error = 'Stock record not found.';
                } else {
                    $symbol = $stock['symbol'];
                    $logo_path = $stock['logo_path'];

                    // Check if new logo uploaded
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['logo']['tmp_name'];
                        $file_name = $_FILES['logo']['name'];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (in_array($file_ext, $allowed_exts)) {
                            // Delete old logo if exists
                            if ($logo_path && file_exists(__DIR__ . '/' . $logo_path)) {
                                unlink(__DIR__ . '/' . $logo_path);
                            }
                            $new_file_name = $userId . '_' . $symbol . '_' . time() . '.' . $file_ext;
                            $logo_path = 'uploads/' . $new_file_name;
                            move_uploaded_file($file_tmp, __DIR__ . '/' . $logo_path);
                        } else {
                            $error = 'Invalid image extension.';
                        }
                    }

                    if (empty($error)) {
                        $stmt = $pdo->prepare("UPDATE stocks SET company_name = ?, quantity = ?, buy_price = ?, current_price = ?, purchase_date = ?, logo_path = ?, notes = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$company_name, $quantity, $buy_price, $current_price, $purchase_date, $logo_path, $notes, $id, $userId]);
                        $success = "Holding details updated successfully!";
                    }
                }
            } catch (PDOException $e) {
                $error = 'Error updating stock: ' . $e->getMessage();
            }
        }
    }

    // 3. DELETE STOCK POSITION
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error = 'Invalid request.';
        } else {
            try {
                // Get logo path to delete file
                $stmt = $pdo->prepare("SELECT logo_path, symbol FROM stocks WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
                $stock = $stmt->fetch();

                if ($stock) {
                    if ($stock['logo_path'] && file_exists(__DIR__ . '/' . $stock['logo_path'])) {
                        unlink(__DIR__ . '/' . $stock['logo_path']);
                    }

                    $stmt = $pdo->prepare("DELETE FROM stocks WHERE id = ? AND user_id = ?");
                    $stmt->execute([$id, $userId]);
                    $success = $stock['symbol'] . " holding removed successfully.";
                } else {
                    $error = 'Stock holding not found.';
                }
            } catch (PDOException $e) {
                $error = 'Error deleting stock: ' . $e->getMessage();
            }
        }
    }

    // 4. BUY/SELL SHARES
    elseif ($action === 'buy_sell') {
        $id = (int)($_POST['id'] ?? 0);
        $tx_type = strtoupper($_POST['tx_type'] ?? ''); // BUY or SELL
        $tx_qty = (float)($_POST['tx_quantity'] ?? 0);
        $tx_price = (float)($_POST['tx_price'] ?? 0);
        $tx_date = $_POST['tx_date'] ?? date('Y-m-d');

        if ($id <= 0 || ($tx_type !== 'BUY' && $tx_type !== 'SELL') || $tx_qty <= 0 || $tx_price < 0) {
            $error = 'Invalid input parameters for Buy/Sell.';
        } else {
            try {
                // Fetch stock
                $stmt = $pdo->prepare("SELECT * FROM stocks WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
                $stock = $stmt->fetch();

                if (!$stock) {
                    $error = 'Stock holding not found.';
                } else {
                    $symbol = $stock['symbol'];
                    $current_qty = (float)$stock['quantity'];
                    $current_buy_price = (float)$stock['buy_price'];

                    if ($tx_type === 'SELL' && $tx_qty > $current_qty) {
                        $error = "Insufficient shares. You only own $current_qty shares of $symbol.";
                    } else {
                        $pdo->beginTransaction();

                        if ($tx_type === 'BUY') {
                            // Calculate new average buy price and new quantity
                            $new_qty = $current_qty + $tx_qty;
                            $new_buy_price = (($current_qty * $current_buy_price) + ($tx_qty * $tx_price)) / $new_qty;
                            
                            $stmt = $pdo->prepare("UPDATE stocks SET quantity = ?, buy_price = ? WHERE id = ? AND user_id = ?");
                            $stmt->execute([$new_qty, $new_buy_price, $id, $userId]);
                        } else { // SELL
                            $new_qty = $current_qty - $tx_qty;
                            if ($new_qty <= 0) {
                                // If sold out, delete holding or set to 0. Let's delete holding.
                                $stmt = $pdo->prepare("DELETE FROM stocks WHERE id = ? AND user_id = ?");
                                $stmt->execute([$id, $userId]);
                                if ($stock['logo_path'] && file_exists(__DIR__ . '/' . $stock['logo_path'])) {
                                    unlink(__DIR__ . '/' . $stock['logo_path']);
                                }
                            } else {
                                $stmt = $pdo->prepare("UPDATE stocks SET quantity = ? WHERE id = ? AND user_id = ?");
                                $stmt->execute([$new_qty, $id, $userId]);
                            }
                        }

                        // Log Transaction
                        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, symbol, transaction_type, quantity, price, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$userId, $symbol, $tx_type, $tx_qty, $tx_price, $tx_date . ' ' . date('H:i:s')]);

                        $pdo->commit();
                        $success = "Successfully logged transaction and updated portfolio!";
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Transaction failed: ' . $e->getMessage();
            }
        }
    }
}

// Sorting, Filtering & Pagination Configurations
$search = trim($_GET['search'] ?? '');
$filter_perf = $_GET['filter_perf'] ?? 'all'; // all, profit, loss
$sort_by = $_GET['sort'] ?? 'symbol';
$sort_order = $_GET['order'] ?? 'ASC';
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Safe sorting whitelist
$allowed_sort = ['symbol', 'company_name', 'quantity', 'buy_price', 'current_price', 'purchase_date'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'symbol';
}
$sort_order = (strtoupper($sort_order) === 'DESC') ? 'DESC' : 'ASC';

// Build Query
$query = "SELECT * FROM stocks WHERE user_id = :user_id";
$params = [':user_id' => $userId];

if (!empty($search)) {
    $query .= " AND (symbol LIKE :search OR company_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($filter_perf === 'profit') {
    $query .= " AND (current_price > buy_price)";
} elseif ($filter_perf === 'loss') {
    $query .= " AND (current_price < buy_price)";
}

// Fetch all filtered rows for calculation and export
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_filtered_stocks = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_filtered_stocks = [];
}

// Total records for pagination
$total_records = count($all_filtered_stocks);
$total_pages = ceil($total_records / $limit);

// Append Sorting and Pagination to Query
$query .= " ORDER BY $sort_by $sort_order LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($query);
    // Bind limit and offset as integers for safety
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $stocks = $stmt->fetchAll();
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . sanitize($e->getMessage()) . '</div>';
    $stocks = [];
}
?>

<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <h1 class="h2 mb-1 dashboard-header">My Stock Portfolio</h1>
        <p class="text-muted mb-0">Track and manage your asset holdings</p>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0 d-flex justify-content-md-end gap-2 flex-wrap">
        <button class="btn btn-outline-secondary btn-sm" onclick="exportToExcel()"><i class="bi bi-file-earmark-excel me-1"></i> Excel</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStockModal"><i class="bi bi-plus-lg me-1"></i> Add Stock</button>
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

<!-- Filtering & Searching Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="portfolio.php" class="row g-3 align-items-center">
            <input type="hidden" name="sort" value="<?= sanitize($sort_by) ?>">
            <input type="hidden" name="order" value="<?= sanitize($sort_order) ?>">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Search by symbol or name..." value="<?= sanitize($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select class="form-select" name="filter_perf">
                    <option value="all" <?= ($filter_perf === 'all') ? 'selected' : '' ?>>All Holdings</option>
                    <option value="profit" <?= ($filter_perf === 'profit') ? 'selected' : '' ?>>Profitable Holdings Only</option>
                    <option value="loss" <?= ($filter_perf === 'loss') ? 'selected' : '' ?>>Losing Holdings Only</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="portfolio.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Portfolio Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 custom-table" id="portfolioTable">
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&filter_perf=<?= $filter_perf ?>&sort=symbol&order=<?= ($sort_by === 'symbol' && $sort_order === 'ASC') ? 'DESC' : 'ASC' ?>" class="text-decoration-none text-table-header">
                                Symbol <?= ($sort_by === 'symbol') ? ($sort_order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&filter_perf=<?= $filter_perf ?>&sort=company_name&order=<?= ($sort_by === 'company_name' && $sort_order === 'ASC') ? 'DESC' : 'ASC' ?>" class="text-decoration-none text-table-header">
                                Company <?= ($sort_by === 'company_name') ? ($sort_order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th class="text-end">
                            <a href="?search=<?= urlencode($search) ?>&filter_perf=<?= $filter_perf ?>&sort=quantity&order=<?= ($sort_by === 'quantity' && $sort_order === 'ASC') ? 'DESC' : 'ASC' ?>" class="text-decoration-none text-table-header">
                                Quantity <?= ($sort_by === 'quantity') ? ($sort_order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th class="text-end">Avg Buy Price</th>
                        <th class="text-end">Current Price</th>
                        <th class="text-end">Invested</th>
                        <th class="text-end">Current Value</th>
                        <th class="text-end">Total Return</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($stocks) > 0): ?>
                        <?php foreach ($stocks as $stock): 
                            $qty = (float)$stock['quantity'];
                            $buy = (float)$stock['buy_price'];
                            $curr = (float)$stock['current_price'];
                            
                            $invested = $qty * $buy;
                            $current = $qty * $curr;
                            $pl = $current - $invested;
                            $pl_pct = ($invested > 0) ? ($pl / $invested) * 100 : 0;
                            
                            $pl_class = ($pl >= 0) ? 'text-success' : 'text-danger';
                            $pl_badge = ($pl >= 0) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                        ?>
                            <tr>
                                <td>
                                    <?php if (!empty($stock['logo_path']) && file_exists(__DIR__ . '/' . $stock['logo_path'])): ?>
                                        <img src="<?= sanitize($stock['logo_path']) ?>" alt="" class="rounded bg-light p-1" style="width: 38px; height: 38px; object-fit: contain;">
                                    <?php else: ?>
                                        <div class="avatar-circle-sm bg-secondary-subtle text-secondary">
                                            <?= strtoupper(substr(sanitize($stock['symbol']), 0, 2)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary text-light fw-bold"><?= sanitize($stock['symbol']) ?></span></td>
                                <td>
                                    <div class="fw-semibold font-table-data"><?= sanitize($stock['company_name']) ?></div>
                                    <small class="text-muted text-truncate d-block" style="max-width: 140px;" title="<?= sanitize($stock['notes']) ?>"><?= sanitize($stock['notes']) ?></small>
                                </td>
                                <td class="text-end font-table-data"><?= number_format($qty, 4) ?></td>
                                <td class="text-end font-table-data">$<?= number_format($buy, 2) ?></td>
                                <td class="text-end font-table-data">$<?= number_format($curr, 2) ?></td>
                                <td class="text-end font-table-data">$<?= number_format($invested, 2) ?></td>
                                <td class="text-end font-table-data">$<?= number_format($current, 2) ?></td>
                                <td class="text-end font-table-data">
                                    <span class="fw-semibold <?= $pl_class ?>"><?= ($pl >= 0 ? '+' : '') . number_format($pl, 2) ?></span><br>
                                    <span class="badge <?= $pl_badge ?> rounded-pill fs-9"><?= ($pl >= 0 ? '+' : '') . number_format($pl_pct, 2) ?>%</span>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-link text-muted p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical fs-5"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                            <li>
                                                <button class="dropdown-item d-flex align-items-center gap-2" 
                                                        onclick="openBuySellModal(<?= $stock['id'] ?>, '<?= sanitize($stock['symbol']) ?>', <?= $qty ?>, <?= $buy ?>)">
                                                    <i class="bi bi-arrow-left-right text-info"></i> Buy / Sell Shares
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item d-flex align-items-center gap-2" 
                                                        onclick="openEditModal(<?= $stock['id'] ?>, '<?= sanitize($stock['symbol']) ?>', '<?= sanitize($stock['company_name']) ?>', <?= $qty ?>, <?= $buy ?>, <?= $curr ?>, '<?= $stock['purchase_date'] ?>', '<?= sanitize(addslashes($stock['notes'] ?? '')) ?>')">
                                                    <i class="bi bi-pencil text-warning"></i> Edit Details
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item d-flex align-items-center gap-2 text-danger" 
                                                        onclick="openDeleteModal(<?= $stock['id'] ?>, '<?= sanitize($stock['symbol']) ?>')">
                                                    <i class="bi bi-trash"></i> Delete Position
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-briefcase fs-2 mb-2 d-block"></i>
                                No stock positions found matching criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?search=<?= urlencode($search) ?>&filter_perf=<?= $filter_perf ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?search=<?= urlencode($search) ?>&filter_perf=<?= $filter_perf ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?search=<?= urlencode($search) ?>&filter_perf=<?= $filter_perf ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>&page=<?= $page + 1 ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- Hidden Full Data Table for Export to PDF/Excel -->
<table class="d-none" id="fullPortfolioTableForExport">
    <thead>
        <tr>
            <th>Symbol</th>
            <th>Company Name</th>
            <th>Quantity</th>
            <th>Buy Price ($)</th>
            <th>Current Price ($)</th>
            <th>Invested ($)</th>
            <th>Current Value ($)</th>
            <th>Profit/Loss ($)</th>
            <th>Profit/Loss (%)</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($all_filtered_stocks as $stock): 
            $qty = (float)$stock['quantity'];
            $buy = (float)$stock['buy_price'];
            $curr = (float)$stock['current_price'];
            $invested = $qty * $buy;
            $current = $qty * $curr;
            $pl = $current - $invested;
            $pl_pct = ($invested > 0) ? ($pl / $invested) * 100 : 0;
        ?>
            <tr>
                <td><?= sanitize($stock['symbol']) ?></td>
                <td><?= sanitize($stock['company_name']) ?></td>
                <td><?= $qty ?></td>
                <td><?= $buy ?></td>
                <td><?= $curr ?></td>
                <td><?= $invested ?></td>
                <td><?= $current ?></td>
                <td><?= $pl ?></td>
                <td><?= round($pl_pct, 2) ?></td>
                <td><?= sanitize($stock['notes']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- ================= MODALS ================= -->

<!-- 1. Add Stock Modal -->
<div class="modal fade" id="addStockModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="addStockModalLabel"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Add New Stock Position</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="portfolio.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="add_symbol" class="form-label">Stock Symbol *</label>
                            <input type="text" class="form-control" id="add_symbol" name="symbol" placeholder="e.g. AAPL" required maxencode="10">
                        </div>
                        <div class="col-md-6">
                            <label for="add_company" class="form-label">Company Name *</label>
                            <input type="text" class="form-control" id="add_company" name="company_name" placeholder="e.g. Apple Inc." required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_quantity" class="form-label">Quantity *</label>
                            <input type="number" step="0.0001" class="form-control" id="add_quantity" name="quantity" placeholder="0.0000" min="0.0001" required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_buy_price" class="form-label">Buy Price ($) *</label>
                            <input type="number" step="0.01" class="form-control" id="add_buy_price" name="buy_price" placeholder="0.00" min="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_curr_price" class="form-label">Current Price ($) *</label>
                            <input type="number" step="0.01" class="form-control" id="add_curr_price" name="current_price" placeholder="0.00" min="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_date" class="form-label">Purchase Date *</label>
                            <input type="date" class="form-control" id="add_date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label for="add_logo" class="form-label">Company Logo (Optional)</label>
                            <input type="file" class="form-control" id="add_logo" name="logo" accept="image/*">
                            <div class="form-text">JPG, PNG, GIF, WEBP. Max 2MB.</div>
                        </div>
                        <div class="col-12">
                            <label for="add_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="add_notes" name="notes" rows="2" placeholder="Add investment details..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Position</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Edit Stock Modal -->
<div class="modal fade" id="editStockModal" tabindex="-1" aria-labelledby="editStockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="editStockModalLabel"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Holding Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="portfolio.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="edit_id" name="id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Stock Symbol</label>
                            <input type="text" class="form-control" id="edit_symbol" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_company" class="form-label">Company Name *</label>
                            <input type="text" class="form-control" id="edit_company" name="company_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_quantity" class="form-label">Quantity (Shares) *</label>
                            <input type="number" step="0.0001" class="form-control" id="edit_quantity" name="quantity" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_buy_price" class="form-label">Avg Buy Price ($) *</label>
                            <input type="number" step="0.01" class="form-control" id="edit_buy_price" name="buy_price" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_curr_price" class="form-label">Current Price ($) *</label>
                            <input type="number" step="0.01" class="form-control" id="edit_curr_price" name="current_price" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_date" class="form-label">Purchase Date *</label>
                            <input type="date" class="form-control" id="edit_date" name="purchase_date" required>
                        </div>
                        <div class="col-12">
                            <label for="edit_logo" class="form-label">Replace Logo (Optional)</label>
                            <input type="file" class="form-control" id="edit_logo" name="logo" accept="image/*">
                            <div class="form-text">Leave blank to keep existing logo.</div>
                        </div>
                        <div class="col-12">
                            <label for="edit_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Buy/Sell Shares Modal -->
<div class="modal fade" id="buySellModal" tabindex="-1" aria-labelledby="buySellModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="buySellModalLabel"><i class="bi bi-arrow-left-right text-info me-2"></i>Buy / Sell Shares</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="portfolio.php" method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="buy_sell">
                    <input type="hidden" id="buysell_id" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Stock Symbol</label>
                        <input type="text" class="form-control" id="buysell_symbol" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Transaction Type</label>
                        <div class="d-flex gap-3">
                            <div class="form-check flex-fill">
                                <input class="form-check-input" type="radio" name="tx_type" id="tx_buy" value="BUY" checked>
                                <label class="form-check-label text-success fw-semibold" for="tx_buy">BUY</label>
                            </div>
                            <div class="form-check flex-fill">
                                <input class="form-check-input" type="radio" name="tx_type" id="tx_sell" value="SELL">
                                <label class="form-check-label text-danger fw-semibold" for="tx_sell">SELL</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="buysell_qty" class="form-label">Quantity</label>
                        <input type="number" step="0.0001" class="form-control" id="buysell_qty" name="tx_quantity" placeholder="0.0000" min="0.0001" required>
                    </div>

                    <div class="mb-3">
                        <label for="buysell_price" class="form-label">Price per Share ($)</label>
                        <input type="number" step="0.01" class="form-control" id="buysell_price" name="tx_price" placeholder="0.00" min="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label for="buysell_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="buysell_date" name="tx_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. Delete Confirm Modal -->
<div class="modal fade" id="deleteStockModal" tabindex="-1" aria-labelledby="deleteStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteStockModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Position</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="portfolio.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="delete_id" name="id">
                    <p>Are you sure you want to delete <span class="fw-bold text-danger" id="delete_symbol_text"></span> from your portfolio? This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts to prefill modal fields -->
<script>
    function openEditModal(id, symbol, company, qty, buy, curr, date, notes) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_symbol').value = symbol;
        document.getElementById('edit_company').value = company;
        document.getElementById('edit_quantity').value = qty;
        document.getElementById('edit_buy_price').value = buy;
        document.getElementById('edit_curr_price').value = curr;
        document.getElementById('edit_date').value = date;
        document.getElementById('edit_notes').value = notes;
        
        var modal = new bootstrap.Modal(document.getElementById('editStockModal'));
        modal.show();
    }

    function openBuySellModal(id, symbol, currentQty, buyPrice) {
        document.getElementById('buysell_id').value = id;
        document.getElementById('buysell_symbol').value = symbol;
        document.getElementById('buysell_qty').value = '';
        document.getElementById('buysell_price').value = '';
        
        var modal = new bootstrap.Modal(document.getElementById('buySellModal'));
        modal.show();
    }

    function openDeleteModal(id, symbol) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_symbol_text').textContent = symbol;
        
        var modal = new bootstrap.Modal(document.getElementById('deleteStockModal'));
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
