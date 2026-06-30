<?php
require_once 'config.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

// Check if registration success message exists
$reg_success = flash('register_success');
if ($reg_success) {
    $success = $reg_success['message'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? ''); // Username or Email
    $password = $_POST['password'] ?? '';

    if (empty($identity) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        try {
            // Find user by Username or Email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$identity, $identity]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Password is correct, start session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];

                header("Location: dashboard.php");
                exit;
            } else {
                $error = 'Invalid username/email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Stock Portfolio Tracker</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <!-- Dark Mode Toggle -->
    <button class="btn theme-toggle-btn" id="themeToggle" type="button" aria-label="Toggle Theme">
        <i class="bi bi-moon-stars-fill"></i>
    </button>

    <div class="container d-flex align-items-center justify-content-center min-vh-100 py-5">
        <div class="row w-100 justify-content-center">
            <div class="col-md-8 col-lg-5 col-xl-4">
                <div class="card auth-card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="brand-logo mb-2">
                                <i class="bi bi-graph-up-arrow fs-1 text-primary"></i>
                            </div>
                            <h2 class="auth-title">Welcome Back</h2>
                            <p class="text-muted">Access your stock portfolio dashboard</p>
                        </div>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <div><?= sanitize($success) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <div><?= sanitize($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <form action="login.php" method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="identity" class="form-label">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="identity" name="identity" placeholder="Enter username or email" required value="<?= isset($_POST['identity']) ? sanitize($_POST['identity']) : '' ?>">
                                </div>
                                <div class="invalid-feedback">Please enter your username or email.</div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <label for="password" class="form-label mb-0">Password</label>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                                    <span class="input-group-text toggle-password" style="cursor: pointer;"><i class="bi bi-eye"></i></span>
                                </div>
                                <div class="invalid-feedback">Please enter your password.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 btn-auth">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Log In
                            </button>
                        </form>

                        <div class="text-center mt-4">
                            <p class="mb-0 text-muted">Don't have an account? <a href="register.php" class="auth-link">Register here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/app.js"></script>
    <script>
        // Inline form validation handler
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
</body>
</html>
