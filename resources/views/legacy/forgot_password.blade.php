<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | TrainHub Al Amin</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/forgot-password.css">
</head>
<body class="auth-page">

<div class="login-page">

    <section class="login-left">
        <div class="brand-section">
            <img class="auth-logo" src="assets/images/al-amin-edu-oasis-logo.png" alt="Al Amin Edu Oasis">
            <h1>TrainHub <span>Al Amin</span></h1>
            <p class="system-label">Training Management System</p>

            <p class="system-desc">
                Manage trainings, sessions, teachers, trainers, feedback,
                certificates and training records in one integrated platform.
            </p>
        </div>

        <div class="feature-list">
            <div class="feature-item">
                <strong>Training Management</strong>
                <span>Create, organize and monitor training programs.</span>
            </div>

            <div class="feature-item">
                <strong>Training Records</strong>
                <span>Track sessions, participants and certificates easily.</span>
            </div>

            <div class="feature-item">
                <strong>Administration Dashboard</strong>
                <span>View important data and activities in one place.</span>
            </div>
        </div>
    </section>

    <section class="login-right">
        <div class="login-card forgot-card">

            <?php if ($success !== ''): ?>
                <div class="step-badge">Password Updated</div>
                <h2>Reset Complete</h2>
                <p>Your account is ready. Use the new password when signing in.</p>

                <div class="success" role="status">
                    {{ $success }}
                </div>

                <a href="login.php" class="primary-link-button">Return to Login</a>
            <?php elseif ($isResetStep): ?>
                <div class="step-badge">Step 2 of 2</div>
                <h2>Create New Password</h2>
                <p>Choose a strong password for your verified staff account.</p>

                <?php if ($error !== ''): ?>
                    <div class="error" role="alert">
                        {{ $error }}
                    </div>
                <?php endif; ?>

                <div class="account-preview">
                    <span>Verified account</span>
                    <strong>{{ $resetStaffName }}</strong>
                    <small>{{ $resetStaffID }}</small>
                </div>

                <form method="POST" action="forgot_password.php" autocomplete="off">
                    <input type="hidden" name="action" value="reset_password">
                    @csrf

                    <div class="input-group">
                        <label for="new_password">New Password</label>
                        <div class="input-box">
                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                placeholder="Enter a new password"
                                autocomplete="new-password"
                                minlength="8"
                                maxlength="255"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-box">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Enter the new password again"
                                autocomplete="new-password"
                                minlength="8"
                                maxlength="255"
                                required
                            >
                        </div>
                    </div>

                    <div class="password-note">
                        Use at least 8 characters with uppercase, lowercase, and a number.
                    </div>

                    <button type="submit">Reset Password</button>
                </form>

                <div class="card-link-row">
                    <a href="forgot_password.php?restart=1">Verify another account</a>
                    <a href="login.php">Back to login</a>
                </div>
            <?php else: ?>
                <div class="step-badge">Step 1 of 2</div>
                <h2>Forgot Password?</h2>
                <p>Enter your registered account details to verify your identity.</p>

                <?php if ($error !== ''): ?>
                    <div class="error" role="alert">
                        {{ $error }}
                    </div>
                <?php endif; ?>

                <form method="POST" action="forgot_password.php" autocomplete="off">
                    <input type="hidden" name="action" value="verify_identity">
                    @csrf

                    <div class="input-group">
                        <label for="identifier">Email Address</label>
                        <div class="input-box">
                            <input
                                type="email"
                                id="identifier"
                                name="identifier"
                                value="{{ $identifier }}"
                                placeholder="Enter email address"
                                autocomplete="username"
                                maxlength="100"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="ic_number">IC Number</label>
                        <div class="input-box">
                            <input
                                type="text"
                                id="ic_number"
                                name="ic_number"
                                value="{{ $icNumber }}"
                                placeholder="Example: 900101-14-1234"
                                inputmode="numeric"
                                maxlength="20"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit">Verify Account</button>
                </form>

                <div class="card-link-row single-link">
                    <a href="login.php">Back to login</a>
                </div>
            <?php endif; ?>

            <div class="login-footer">
                &copy; <?php echo date('Y'); ?> TrainHub Al Amin. All rights reserved.
            </div>

        </div>
    </section>

</div>

</body>
</html>
