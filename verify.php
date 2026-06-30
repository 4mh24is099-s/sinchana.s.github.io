<?php
/**
 * Diagnostic Verification Script
 * Checks configuration, PDO connection, directory permissions, etc.
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Diagnostics & Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; color: #212529; }
        .card { border-radius: 16px; border: none; }
        .badge { font-size: 0.9rem; }
    </style>
</head>
<body class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm p-4">
                    <h2 class="mb-4 text-center fw-bold text-primary">System Verification</h2>
                    
                    <div class="list-group">
                        
                        <!-- 1. PHP Version -->
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h5 class="mb-1 fw-semibold">PHP Version</h5>
                                <p class="mb-0 text-muted fs-7">Requires PHP >= 7.4. Running: <strong><?= PHP_VERSION ?></strong></p>
                            </div>
                            <?php if (version_compare(PHP_VERSION, '7.4.0', '>=')): ?>
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">Pass</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Fail (Upgrade PHP)</span>
                            <?php endif; ?>
                        </div>

                        <!-- 2. PDO Support -->
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h5 class="mb-1 fw-semibold">PDO Support & Drivers</h5>
                                <p class="mb-0 text-muted fs-7">Requires pdo_mysql extension.</p>
                            </div>
                            <?php 
                            $pdo_supported = extension_loaded('pdo') && in_array('mysql', PDO::getAvailableDrivers());
                            if ($pdo_supported): ?>
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">Pass</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Fail (Install pdo_mysql)</span>
                            <?php endif; ?>
                        </div>

                        <!-- 3. Config & Database connection -->
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h5 class="mb-1 fw-semibold">Database Connection (config.php)</h5>
                                <p class="mb-0 text-muted fs-7">Verifies connection to database `stock_portfolio`.</p>
                            </div>
                            <?php
                            $db_connected = false;
                            $db_error = '';
                            if (file_exists('config.php')) {
                                try {
                                    ob_start();
                                    include 'config.php';
                                    ob_end_clean();
                                    if (isset($pdo)) {
                                        $db_connected = true;
                                    } else {
                                        $db_error = 'PDO variable not set in config.php';
                                    }
                                } catch (Exception $e) {
                                    $db_error = $e->getMessage();
                                }
                            } else {
                                $db_error = 'config.php not found';
                            }
                            
                            if ($db_connected): ?>
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">Pass</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3" title="<?= htmlspecialchars($db_error) ?>">Fail (Check config)</span>
                            <?php endif; ?>
                        </div>

                        <!-- 4. Upload Directory Permissions -->
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h5 class="mb-1 fw-semibold">Uploads Directory Permissions</h5>
                                <p class="mb-0 text-muted fs-7">Requires `uploads` folder to be writeable.</p>
                            </div>
                            <?php
                            $uploads_writeable = false;
                            if (file_exists('uploads') && is_writable('uploads')) {
                                $uploads_writeable = true;
                            }
                            if ($uploads_writeable): ?>
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">Pass</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Fail (Check permissions)</span>
                            <?php endif; ?>
                        </div>

                    </div>

                    <?php if ($pdo_supported && $db_connected && $uploads_writeable): ?>
                        <div class="mt-4 text-center">
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i> All checks passed! The application is ready to run.
                            </div>
                            <a href="login.php" class="btn btn-primary btn-lg mt-2">Go to Login Page</a>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 text-center">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> Some checks failed. Please fix them to run the application.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
