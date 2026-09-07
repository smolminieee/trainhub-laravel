<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Form | TrainHub Al Amin</title>
    <link rel="stylesheet" href="assets/css/attendance.css?v=2">
</head>
<body class="attendance-public-page">
<div class="attendance-page">
    <div class="attendance-card">
        <div class="brand-block">
            <div class="brand-icon"><img src="assets/images/al-amin-edu-oasis-logo.png" alt="Al Amin Edu Oasis"></div>
            <div>
                <h1>TrainHub Al Amin</h1>
                <p>Training Course Attendance</p>
            </div>
        </div>

        <?php if (!$session) { ?>
            <div class="message error-message">Invalid attendance link. Session was not found.</div>
        <?php } else { ?>
            <div class="course-header">
                <span>Attendance Form</span>
                <h2><?php echo e($session['courseName']); ?></h2>
                <?php if (!empty($session['sessionName'])) { ?><p><?php echo e($session['sessionName']); ?></p><?php } ?>
            </div>

            <div class="session-info-grid">
                <div class="info-box"><small>Place</small><strong><?php echo !empty($session['location']) ? e($session['location']) : '-'; ?></strong></div>
                <div class="info-box"><small>Date</small><strong><?php echo date('d M Y', strtotime((string)$session['sessionDate'])); ?></strong></div>
                <div class="info-box"><small>Time</small><strong><?php echo date('h:i A', strtotime((string)$session['startTime'])); ?> - <?php echo date('h:i A', strtotime((string)$session['endTime'])); ?></strong></div>
            </div>

            <?php if ($isClosed && $messageType !== 'success') { ?>
                <div class="closed-box">Attendance for this session is closed.</div>
            <?php } elseif ($messageType !== 'success') { ?>
                <form method="POST" class="attendance-form" autocomplete="off" novalidate>
                    @csrf
                    <input type="hidden" name="sessionID" value="<?php echo e($session['sessionID']); ?>">
                    <div class="form-group" id="emailGroup">
                        <label for="attendanceEmail">Email Address <span class="required-star">*</span></label>
                        <input type="email" name="email" id="attendanceEmail" value="<?php echo e($email); ?>" placeholder="Enter the email used for this training" required autocomplete="email">
                        <small class="field-error" id="emailError"></small>
                    </div>
                    <button type="submit" class="submit-btn">Submit Attendance</button>
                </form>
            <?php } ?>
        <?php } ?>
    </div>
</div>

<?php if ($message !== '') { ?>
<div class="attendance-popup-backdrop show" id="attendancePopup" role="dialog" aria-modal="true" aria-labelledby="attendancePopupTitle">
    <div class="attendance-popup <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
        <div class="attendance-popup-icon"><?php echo $messageType === 'success' ? '✓' : '!'; ?></div>
        <h3 id="attendancePopupTitle"><?php echo $messageType === 'success' ? 'Successful' : 'Unable to Submit'; ?></h3>
        <p><?php echo e($message); ?></p>
        <button type="button" onclick="document.getElementById('attendancePopup').classList.remove('show')">OK</button>
    </div>
</div>
<?php } ?>

<script>
(function(){
    const form = document.querySelector('.attendance-form');
    const email = document.getElementById('attendanceEmail');
    const group = document.getElementById('emailGroup');
    const error = document.getElementById('emailError');
    if (!form || !email) return;

    function validateEmail(){
        const value = email.value.trim();
        let message = '';
        if (!value) message = 'Please fill in your email address.';
        else if (!email.validity.valid) message = 'Please enter a valid email address.';
        group?.classList.toggle('has-error', message !== '');
        if (error) error.textContent = message;
        return message === '';
    }

    email.addEventListener('input', validateEmail);
    form.addEventListener('submit', function(event){
        if (!validateEmail()) {
            event.preventDefault();
            email.focus();
        }
    });
})();
</script>
</body>
</html>
