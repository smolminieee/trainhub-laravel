<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Directory Pending | Al Amin Edu Oasis</title>
    <link rel="stylesheet" href="{{ asset('assets/css/unified-auth.css') }}">
</head>
<body class="unified-auth-page selection-page">
<header class="selection-topbar">
    <div class="selection-brand">
        <img src="{{ asset('assets/images/al-amin-edu-oasis-logo.png') }}" alt="Al Amin Edu Oasis">
        <div><strong>Al Amin Edu Oasis</strong></div>
    </div>
    <a class="topbar-link" href="{{ route('logout') }}">Log out</a>
</header>

<main class="placeholder-shell">
    <div class="placeholder-card">
        <div class="success-icon">
            <svg viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
        </div>
        <h1>{{ $system['name'] }}</h1>
        <p>Role: <strong>{{ $role['label'] }}</strong></p>
        <div class="pending-directory-box">
            The system directory is intentionally blank for now. Add its path later in <code>config/unified_access.php</code> or the matching <code>SYSTEM_*_URL</code> value in <code>.env</code>.
        </div>
        <div class="placeholder-actions">
            <a class="secondary-auth-btn" href="{{ route('auth.systems') }}">Choose another system</a>
            <a class="primary-auth-btn compact-btn" href="{{ route('auth.roles') }}">Change role</a>
        </div>
    </div>
</main>
</body>
</html>
