<?php
require_once 'header.php';

$userId = $_SESSION['user_id'];

// Fetch all stocks for calculations
try {
    $stmt = $pdo->prepare("SELECT * FROM stocks WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stocks = $stmt->fetchAll();
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error fetching stocks: ' . sanitize($e->getMessage()) . '</div>';
    $stocks = [];
}

// Calculate totals
$total_investment = 0;
$current_value = 0;
$num_stocks = count($stocks);
$chart_labels = [];
$chart_holdings_value = [];
$chart_pl_value = [];

foreach ($stocks as $stock) {
    $qty = (float)$stock['quantity'];
    $buy = (float)$stock['buy_price'];
    $curr = (float)$stock['current_price'];
    
    $invested = $qty * $buy;
    $current = $qty * $curr;
    $pl = $current - $invested;
    
    $total_investment += $invested;
    $current_value += $current;
    
    // Data for charts
    $chart_labels[] = sanitize($stock['symbol']);
    $chart_holdings_value[] = round($current, 2);
    $chart_pl_value[] = round($pl, 2);
}

$profit_loss = $current_value - $total_investment;
$percentage_gain = ($total_investment > 0) ? ($profit_loss / $total_investment) * 100 : 0;

// Format values for display
$total_investment_formatted = '$' . number_format($total_investment, 2);
$current_value_formatted = '$' . number_format($current_value, 2);
$profit_loss_formatted = ($profit_loss >= 0 ? '+' : '') . '$' . number_format($profit_loss, 2);
$percentage_gain_formatted = ($profit_loss >= 0 ? '+' : '') . number_format($percentage_gain, 2) . '%';
$pl_class = ($profit_loss >= 0) ? 'text-success' : 'text-danger';
$pl_badge_class = ($profit_loss >= 0) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';

// Fetch recent 5 transactions for display
try {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recent_transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $recent_transactions = [];
}
?>

<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <h1 class="h2 mb-1 dashboard-header">Dashboard</h1>
        <p class="text-muted mb-0">Overview of your investments and performance</p>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <a href="portfolio.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-1"></i> Manage Portfolio</a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Investment Card -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="icon-circle bg-primary-subtle text-primary me-3">
                    <i class="bi bi-cash-stack fs-4"></i>
                </div>
                <div>
                    <span class="text-muted fs-7 d-block mb-1">Total Invested</span>
                    <span class="fs-4 fw-bold val-text"><?= $total_investment_formatted ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Portfolio Value Card -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="icon-circle bg-info-subtle text-info me-3">
                    <i class="bi bi-wallet2 fs-4"></i>
                </div>
                <div>
                    <span class="text-muted fs-7 d-block mb-1">Current Value</span>
                    <span class="fs-4 fw-bold val-text"><?= $current_value_formatted ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Profit/Loss Card -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="icon-circle <?= $profit_loss >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> me-3">
                    <i class="bi <?= $profit_loss >= 0 ? 'bi-graph-up' : 'bi-graph-down' ?> fs-4"></i>
                </div>
                <div>
                    <span class="text-muted fs-7 d-block mb-1">Total Return</span>
                    <span class="fs-4 fw-bold <?= $pl_class ?>"><?= $profit_loss_formatted ?></span>
                    <span class="badge <?= $pl_badge_class ?> rounded-pill fs-9 ms-1"><?= $percentage_gain_formatted ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Number of Stocks Card -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="icon-circle bg-warning-subtle text-warning me-3">
                    <i class="bi bi-pie-chart fs-4"></i>
                </div>
                <div>
                    <span class="text-muted fs-7 d-block mb-1">Total Holdings</span>
                    <span class="fs-4 fw-bold val-text"><?= $num_stocks ?> <span class="fs-7 fw-normal text-muted">Stock(s)</span></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Portfolio Distribution Chart -->
    <div class="col-lg-5">
        <div class="card chart-card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">Portfolio Distribution</h5>
                <span class="text-muted fs-8">Current Value Weight</span>
            </div>
            <div class="card-body px-4 pb-4 d-flex flex-column align-items-center justify-content-center" style="position: relative; min-height: 300px;">
                <?php if ($num_stocks > 0): ?>
                    <canvas id="distributionChart" style="max-height: 250px; max-width: 250px;"></canvas>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-info-circle fs-2 mb-2 d-block"></i>
                        No assets to visualize. Add stocks to your portfolio.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Profit Analysis Chart -->
    <div class="col-lg-7">
        <div class="card chart-card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">Profit & Loss Analysis</h5>
                <span class="text-muted fs-8">Per Stock Returns ($)</span>
            </div>
            <div class="card-body px-4 pb-4 d-flex flex-column justify-content-center" style="position: relative; min-height: 300px;">
                <?php if ($num_stocks > 0): ?>
                    <canvas id="profitAnalysisChart" style="max-height: 250px;"></canvas>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-info-circle fs-2 mb-2 d-block"></i>
                        No assets to visualize. Add stocks to your portfolio.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Top Performing Stock Holdings -->
    <div class="col-lg-6">
        <div class="card list-card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Current Holdings</h5>
                <a href="portfolio.php" class="fs-8 text-primary text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if ($num_stocks > 0): ?>
                    <div class="table-responsive">
                        <table class="table align-middle custom-table">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th class="text-end">Investment</th>
                                    <th class="text-end">Current Value</th>
                                    <th class="text-end">Gain / Loss</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Sort stocks by current value for summary
                                usort($stocks, function($a, $b) {
                                    return ($b['quantity'] * $b['current_price']) <=> ($a['quantity'] * $a['current_price']);
                                });
                                $limit = min($num_stocks, 5);
                                for ($i = 0; $i < $limit; $i++): 
                                    $s = $stocks[$i];
                                    $s_invested = $s['quantity'] * $s['buy_price'];
                                    $s_current = $s['quantity'] * $s['current_price'];
                                    $s_pl = $s_current - $s_invested;
                                    $s_pl_pct = ($s_invested > 0) ? ($s_pl / $s_invested) * 100 : 0;
                                    $s_pl_class = ($s_pl >= 0) ? 'text-success' : 'text-danger';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($s['logo_path']) && file_exists(__DIR__ . '/' . $s['logo_path'])): ?>
                                                    <img src="<?= sanitize($s['logo_path']) ?>" alt="" class="rounded-circle me-2 bg-light p-1" style="width: 32px; height: 32px; object-fit: contain;">
                                                <?php else: ?>
                                                    <div class="avatar-circle-sm bg-secondary-subtle text-secondary me-2">
                                                        <?= strtoupper(substr(sanitize($s['symbol']), 0, 2)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold fs-7"><?= sanitize($s['symbol']) ?></div>
                                                    <div class="text-muted fs-9 text-truncate" style="max-width: 120px;"><?= sanitize($s['company_name']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end fs-7">$<?= number_format($s_invested, 2) ?></td>
                                        <td class="text-end fs-7">$<?= number_format($s_current, 2) ?></td>
                                        <td class="text-end fw-semibold fs-7 <?= $s_pl_class ?>">
                                            <?= ($s_pl >= 0 ? '+' : '') . number_format($s_pl, 2) ?><br>
                                            <span class="fs-9 font-normal"><?= ($s_pl >= 0 ? '+' : '') . number_format($s_pl_pct, 2) ?>%</span>
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-briefcase fs-3 mb-2 d-block"></i>
                        No assets in your portfolio yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity / Transactions -->
    <div class="col-lg-6">
        <div class="card list-card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Transactions</h5>
                <a href="transactions.php" class="fs-8 text-primary text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (count($recent_transactions) > 0): ?>
                    <div class="list-group list-group-flush border-0">
                        <?php foreach ($recent_transactions as $tx): 
                            $badge_class = ($tx['transaction_type'] === 'BUY') ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                            $symbol = sanitize($tx['symbol']);
                            $date = date('M d, Y', strtotime($tx['transaction_date']));
                            $total = $tx['quantity'] * $tx['price'];
                        ?>
                            <div class="list-group-item px-0 py-3 border-bottom d-flex align-items-center justify-content-between bg-transparent">
                                <div class="d-flex align-items-center">
                                    <span class="badge <?= $badge_class ?> rounded-pill me-3 px-2 py-1 fs-9 fw-semibold" style="width: 50px; display: inline-block; text-align: center;">
                                        <?= $tx['transaction_type'] ?>
                                    </span>
                                    <div>
                                        <div class="fw-bold fs-7"><?= $symbol ?></div>
                                        <div class="text-muted fs-9"><?= $date ?></div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold fs-7">$<?= number_format($total, 2) ?></div>
                                    <div class="text-muted fs-9"><?= number_format($tx['quantity'], 2) ?> Shares @ $<?= number_format($tx['price'], 2) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-journal-text fs-3 mb-2 d-block"></i>
                        No transactions recorded yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Write local variables for Chart.js rendering -->
<script>
    window.chartData = {
        labels: <?= json_encode($chart_labels) ?>,
        holdings: <?= json_encode($chart_holdings_value) ?>,
        profits: <?= json_encode($chart_pl_value) ?>
    };
</script>

<?php require_once 'footer.php'; ?>
