<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TrainHub Al Amin</title>
    <link rel="stylesheet" href="assets/css/login.css">
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
        <div class="login-card">

            <h2>Sign In</h2>
            <p>Welcome back. Please enter your email and password.</p>

            <?php if ($error !== ''): ?>
                <div class="error" role="alert">
                    {{ $error }}
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="on">
                @csrf

                <div class="input-group">
                    <label for="email">Email Address</label>
                    <div class="input-box">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ $email }}"
                            placeholder="Enter email address"
                            autocomplete="username"
                            maxlength="100"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-box">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter password"
                            autocomplete="current-password"
                            maxlength="255"
                            required
                        >
                    </div>
                </div>

                <div class="form-options">
                    <span></span>
                    <a href="forgot_password.php">Forgot password?</a>
                </div>

                <button type="submit">Log In</button>
            </form>

            <div class="login-footer">
                &copy; <?php echo date('Y'); ?> TrainHub Al Amin. All rights reserved.
            </div>

        </div>
    </section>

</div>

</body>
</html>
