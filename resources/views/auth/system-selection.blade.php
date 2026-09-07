<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose System | Al Amin Edu Oasis</title>
    <link rel="stylesheet" href="{{ asset('assets/css/unified-auth.css') }}?v={{ file_exists(public_path('assets/css/unified-auth.css')) ? filemtime(public_path('assets/css/unified-auth.css')) : time() }}">
</head>
<body class="unified-auth-page selection-page">
<header class="selection-topbar">
    <div class="selection-brand">
        <img src="{{ asset('assets/images/al-amin-edu-oasis-logo.png') }}" alt="Al Amin Edu Oasis">
        <div><strong>Al Amin Edu Oasis</strong></div>
    </div>
    <a class="topbar-link logout-visible-link" href="{{ route('logout') }}">Log out</a>
</header>

<main class="selection-shell">
    <a class="back-choice-link" href="{{ route('auth.roles') }}">← Change role</a>

    <div class="selection-heading">
        <h1>Choose a system</h1>
        <p>Role: <strong>{{ $role['label'] }}</strong></p>
    </div>

    @if($error !== '')
        <div class="auth-alert auth-alert-error selection-alert" role="alert">{{ $error }}</div>
    @endif
    <div class="auth-alert auth-alert-error selection-alert" id="systemClientError" role="alert" hidden>Please select one of the available systems.</div>

    <form method="POST" action="{{ route('auth.systems') }}" id="systemChoiceForm" novalidate>
        @csrf
        <div class="choice-grid system-grid">
            @foreach($systems as $systemKey => $system)
                <label class="choice-card system-card compact-system-card">
                    <input type="radio" name="system" value="{{ $systemKey }}">
                    <span class="choice-radio"></span>
                    <span class="choice-icon system-icon system-avatar-icon">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 14h3M8 17h6"/></svg>
                    </span>
                    <span class="choice-content"><strong>{{ $system['name'] }}</strong></span>
                </label>
            @endforeach
        </div>

        <div class="selection-actions">
            <button class="primary-auth-btn compact-btn" type="submit">Open system<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
        </div>
    </form>
</main>
<script>
(function(){
    const form = document.getElementById('systemChoiceForm');
    const error = document.getElementById('systemClientError');
    if (!form) return;
    form.querySelectorAll('input[type="radio"][name="system"]').forEach(function(radio){
        let wasChecked = false;
        radio.addEventListener('pointerdown', function(){ wasChecked = radio.checked; });
        radio.addEventListener('click', function(){
            if (wasChecked) radio.checked = false;
            if (error) error.hidden = true;
        });
    });
    form.addEventListener('submit', function(event){
        if (!form.querySelector('input[name="system"]:checked')) {
            event.preventDefault();
            if (error) { error.hidden = false; error.scrollIntoView({behavior:'smooth', block:'center'}); }
        }
    });
})();
</script>
</body>
</html>
