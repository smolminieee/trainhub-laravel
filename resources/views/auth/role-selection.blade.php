<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Role | Al Amin Edu Oasis</title>
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
    <div class="selection-heading">
        <h1>Choose your role</h1>
        <p>{{ $user['email'] }}</p>
    </div>

    @if($error !== '')
        <div class="auth-alert auth-alert-error selection-alert" role="alert">{{ $error }}</div>
    @endif
    <div class="auth-alert auth-alert-error selection-alert" id="roleClientError" role="alert" hidden>Please select one of the available roles.</div>

    <form method="POST" action="{{ route('auth.roles') }}" id="roleChoiceForm" novalidate>
        @csrf
        <div class="choice-grid role-grid">
            @foreach($roles as $roleKey => $role)
                <label class="choice-card compact-choice-card">
                    <input type="radio" name="role" value="{{ $roleKey }}">
                    <span class="choice-radio"></span>
                    <span class="choice-icon">
                        @if($roleKey === 'staff_edu')
                            <svg viewBox="0 0 24 24"><path d="M12 3v18M3 12h18"/></svg>
                        @elseif($roleKey === 'trainer')
                            <svg viewBox="0 0 24 24"><path d="M4 19V5h16v14zM8 9h8M8 13h5"/></svg>
                        @elseif(in_array($roleKey, ['observer','external_observer'], true))
                            <svg viewBox="0 0 24 24"><path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/></svg>
                        @elseif($roleKey === 'principal')
                            <svg viewBox="0 0 24 24"><path d="M4 20h16M6 20V9l6-5 6 5v11M9 13h6"/></svg>
                        @elseif($roleKey === 'hr_administrator')
                            <svg viewBox="0 0 24 24"><path d="M4 20h16V8H4zM8 8V5h8v3M8 12h8M8 16h5"/></svg>
                        @else
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-5 4-7 8-7s7 2 8 7"/></svg>
                        @endif
                    </span>
                    <span class="choice-content"><strong>{{ $role['label'] }}</strong></span>
                </label>
            @endforeach
        </div>

        <div class="selection-actions">
            <button class="primary-auth-btn compact-btn" type="submit">Continue<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
        </div>
    </form>
</main>
<script>
(function(){
    const form = document.getElementById('roleChoiceForm');
    const error = document.getElementById('roleClientError');
    if (!form) return;
    form.querySelectorAll('input[type="radio"][name="role"]').forEach(function(radio){
        let wasChecked = false;
        radio.addEventListener('pointerdown', function(){ wasChecked = radio.checked; });
        radio.addEventListener('click', function(){
            if (wasChecked) radio.checked = false;
            if (error) error.hidden = true;
        });
    });
    form.addEventListener('submit', function(event){
        if (!form.querySelector('input[name="role"]:checked')) {
            event.preventDefault();
            if (error) { error.hidden = false; error.scrollIntoView({behavior:'smooth', block:'center'}); }
        }
    });
})();
</script>
</body>
</html>
