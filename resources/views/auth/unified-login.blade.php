<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Al Amin Edu Oasis</title>
    <link rel="stylesheet" href="{{ asset('assets/css/unified-auth.css') }}?v={{ file_exists(public_path('assets/css/unified-auth.css')) ? filemtime(public_path('assets/css/unified-auth.css')) : time() }}">
</head>
<body class="unified-auth-page">
<div class="unified-shell login-shell">
    <section class="auth-brand-panel auth-brand-simple">
        <div class="brand-simple-wrap">
            <img class="auth-logo" src="{{ asset('assets/images/al-amin-edu-oasis-logo.png') }}" alt="Al Amin Edu Oasis">
            <h1>Al Amin Edu Oasis</h1>
        </div>
    </section>

    <section class="auth-content-panel">
        <div class="auth-card login-card">
            <h2>Sign in</h2>
            <p class="card-copy"><strong>Welcome back.</strong> Please enter your email and password.</p>

            @if($error !== '')
                <div class="auth-alert auth-alert-error" role="alert">{{ $error }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" autocomplete="on">
                @csrf
                <div class="field-group">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ $email }}"
                        placeholder="Enter your email"
                        autocomplete="username"
                        maxlength="255"
                        required
                        autofocus
                    >
                </div>

                <div class="field-group">
                    <div class="field-label-row">
                        <label for="password">Password</label>
                        <a class="forgot-link" href="{{ route('password.forgot') }}">Forgot password?</a>
                    </div>
                    <div class="password-input-wrap">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            maxlength="255"
                            required
                        >
                        <button type="button" class="password-eye-btn" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                            <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A11.5 11.5 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3 3.8"/><path d="M6.2 6.2C3.5 8 2 12 2 12s3.5 7 10 7a10 10 0 0 0 3.8-.7"/></svg>
                        </button>
                    </div>
                </div>

                <button class="primary-auth-btn" type="submit">
                    Sign in
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>
        </div>
    </section>
</div>
<script>
document.querySelectorAll('[data-password-toggle]').forEach(function(button) {
    button.addEventListener('click', function() {
        const input = document.getElementById(button.dataset.passwordToggle || '');
        if (!input) return;
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.classList.toggle('is-visible', !showing);
        button.setAttribute('aria-pressed', !showing ? 'true' : 'false');
        button.setAttribute('aria-label', !showing ? 'Hide password' : 'Show password');
    });
});
</script>
</body>
</html>
