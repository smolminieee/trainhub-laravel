<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | TrainHub Al Amin</title>
    <link rel="stylesheet" href="assets/css/settings.css?v=<?php echo file_exists(public_path('assets/css/settings.css')) ? filemtime(public_path('assets/css/settings.css')) : time(); ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo file_exists(public_path('assets/css/style.css')) ? filemtime(public_path('assets/css/style.css')) : time(); ?>">
</head>
<body class="trainhub-app page-settings">

@include('partials.topbar')

<div class="page">

    <div class="settings-header">
        <div>
            <h1>Account Settings</h1>
            <p>Manage your profile information and password.</p>
        </div>
    </div>

    @if ($message !== '')
        <div class="alert {{ $messageType }}" role="alert">
            {{ $message }}
        </div>
    @endif

    <div class="settings-grid">

        <aside class="profile-panel">
            <div class="profile-large-avatar" aria-hidden="true">
                {{ strtoupper($avatarCharacter) }}
            </div>

            <div class="profile-identity">
                <h2>{{ $staff['staffName'] }}</h2>
                <p>{{ $staff['role'] ?: 'STAFF_EDU' }}</p>
                <div class="profile-status {{ $statusClass }}">
                    {{ ucfirst($statusClass) }}
                </div>
            </div>

            <div class="summary-list">
                <div>
                    <span>Department</span>
                    <strong>{{ $staff['department'] ?: '-' }}</strong>
                </div>

                <div>
                    <span>Age</span>
                    <strong>{{ $displayAge }}</strong>
                </div>

                <div>
                    <span>Service Duration</span>
                    <strong>{{ $displayServiceDuration }}</strong>
                </div>

                <div>
                    <span>Credit Hours ({{ $staff['credit_year'] ?? date('Y') }})</span>
                    <strong>{{ number_format((float)($staff['credit_hour'] ?? 0), 2) }} / 40</strong>
                    <small>{{ number_format((float)($staff['training_credit_hour'] ?? 0), 2) }}/30 training · {{ number_format((float)($staff['tarbiah_credit_hour'] ?? 0), 2) }}/10 Tarbiah</small>
                </div>
            </div>
        </aside>

        <main class="settings-main">

            <section class="panel">
                <div class="panel-header">
                    <div class="settings-section-title">
                        <span class="settings-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c.8-4 3.5-6 8-6s7.2 2 8 6"/></svg>
                        </span>
                        <div>
                            <h2>Profile Information</h2>
                            <p>Update your contact and personal information.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" class="settings-form" autocomplete="on">
                    @csrf
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-grid">
                        <div class="field">
                            <label for="staffName">Name</label>
                            <input
                                id="staffName"
                                class="readonly-field"
                                type="text"
                                value="{{ $staff['staffName'] ?: '-' }}"
                                disabled
                            >
                        </div>

                        <div class="field">
                            <label for="ICNumber">IC Number</label>
                            <input
                                id="ICNumber"
                                class="readonly-field"
                                type="text"
                                value="{{ $staff['ICNumber'] ?: '-' }}"
                                disabled
                            >
                        </div>

                        <div class="field">
                            <label for="email">Email</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                maxlength="100"
                                autocomplete="email"
                                value="{{ old('email', $staff['email']) }}"
                            >
                        </div>

                        <div class="field">
                            <label for="phoneNumber">Phone Number</label>
                            <input
                                id="phoneNumber"
                                type="tel"
                                name="phoneNumber"
                                maxlength="20"
                                autocomplete="tel"
                                value="{{ old('phoneNumber', $staff['phoneNumber']) }}"
                            >
                        </div>

                        <div class="field">
                            <label for="department">Department</label>
                            <input
                                id="department"
                                class="readonly-field"
                                type="text"
                                value="{{ $staff['department'] ?: '-' }}"
                                disabled
                            >
                        </div>

                        <div class="field">
                            <label for="gender">Gender</label>
                            <input
                                id="gender"
                                class="readonly-field"
                                type="text"
                                value="{{ $staff['gender'] ?: '-' }}"
                                disabled
                            >
                        </div>

                        <div class="field">
                            <label for="maritalStatus">Marital Status</label>
                            <select id="maritalStatus" name="maritalStatus">
                                <option value="">Select status</option>
                                <option value="Single" {{ old('maritalStatus', $staff['maritalStatus']) === 'Single' ? 'selected' : '' }}>Single</option>
                                <option value="Married" {{ old('maritalStatus', $staff['maritalStatus']) === 'Married' ? 'selected' : '' }}>Married</option>
                                <option value="Divorced" {{ old('maritalStatus', $staff['maritalStatus']) === 'Divorced' ? 'selected' : '' }}>Divorced</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="race">Race</label>
                            <input
                                id="race"
                                class="readonly-field"
                                type="text"
                                value="{{ $staff['race'] ?: '-' }}"
                                disabled
                            >
                        </div>

                        <div class="field">
                            <label for="appointedDate">Appointed Date</label>
                            <input
                                id="appointedDate"
                                class="readonly-field"
                                type="text"
                                value="{{ $staff['appointedDate'] ?: '-' }}"
                                disabled
                            >
                        </div>

                        <div class="field">
                            <label for="pensionDate">Pension Date</label>
                            <input
                                id="pensionDate"
                                class="readonly-field"
                                type="text"
                                value="{{ $staff['pensionDate'] ?: '-' }}"
                                disabled
                            >
                        </div>

                        <div class="field full">
                            <label for="address">Address</label>
                            <textarea
                                id="address"
                                name="address"
                                rows="4"
                                maxlength="255"
                                autocomplete="street-address"
                            >{{ old('address', $staff['address']) }}</textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="primary-btn">Save</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div class="settings-section-title">
                        <span class="settings-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v2"/></svg>
                        </span>
                        <div>
                            <h2>Change Password</h2>
                            <p>Use a strong password to keep your account safe.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" class="settings-form" autocomplete="off">
                    @csrf
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-grid">
                        <div class="field">
                            <label for="currentPassword">Current Password</label>
                            <div class="settings-password-wrap">
                                <input id="currentPassword" type="password" name="currentPassword" autocomplete="current-password" required>
                                <button type="button" class="settings-eye-btn" data-password-toggle="currentPassword" aria-label="Show current password">
                                    <svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="field">
                            <label for="newPassword">New Password</label>
                            <div class="settings-password-wrap">
                                <input id="newPassword" type="password" name="newPassword" minlength="8" maxlength="72" autocomplete="new-password" required>
                                <button type="button" class="settings-eye-btn" data-password-toggle="newPassword" aria-label="Show new password">
                                    <svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="field">
                            <label for="confirmPassword">Confirm New Password</label>
                            <div class="settings-password-wrap">
                                <input id="confirmPassword" type="password" name="confirmPassword" minlength="8" maxlength="72" autocomplete="new-password" required>
                                <button type="button" class="settings-eye-btn" data-password-toggle="confirmPassword" aria-label="Show confirmation password">
                                    <svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="primary-btn">Update</button>
                    </div>
                </form>
            </section>

        </main>

    </div>

</div>

<script>
document.querySelectorAll('[data-password-toggle]').forEach(function(button){
    button.addEventListener('click', function(){
        const input=document.getElementById(button.dataset.passwordToggle||'');
        if(!input) return;
        const show=input.type==='password';
        input.type=show?'text':'password';
        button.classList.toggle('is-visible',show);
        button.setAttribute('aria-label',show?'Hide password':'Show password');
    });
});
</script>

</body>
</html>
