<?php
require_once 'config.php';
requireLogin();

// Get the current page filename to determine active nav links
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Portfolio Tracker</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg sticky-top main-navbar shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <i class="bi bi-graph-up-arrow text-primary me-2 fs-3"></i>
                <span class="fw-bold tracking-brand">StockTracker</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page == 'portfolio.php') ? 'active' : '' ?>" href="portfolio.php">
                            <i class="bi bi-briefcase me-1"></i> Portfolio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page == 'watchlist.php') ? 'active' : '' ?>" href="watchlist.php">
                            <i class="bi bi-eye me-1"></i> Watchlist
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page == 'transactions.php') ? 'active' : '' ?>" href="transactions.php">
                            <i class="bi bi-journal-text me-1"></i> Transactions
                        </a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center gap-3">
                    <!-- Theme Toggle Button -->
                    <button class="btn theme-toggle-btn-nav" id="themeToggle" type="button" aria-label="Toggle Theme">
                        <i class="bi bi-moon-stars-fill"></i>
                    </button>

                    <!-- User Dropdown -->
                    <div class="dropdown">
                        <a class="dropdown-toggle user-profile-dropdown d-flex align-items-center gap-2 text-decoration-none" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar-circle">
                                <?= strtoupper(substr(sanitize($_SESSION['username']), 0, 1)) ?>
                            </div>
                            <span class="d-none d-sm-inline text-nav-user fw-medium"><?= sanitize($_SESSION['username']) ?></span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                            <li><h6 class="dropdown-header">Logged in as: <?= sanitize($_SESSION['email']) ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger d-flex align-items-center gap-2" href="logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="py-4">
        <div class="container">
