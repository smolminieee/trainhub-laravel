<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Answer Feedback | TrainHub Al Amin</title>
    <link rel="stylesheet" href="assets/css/feedback.css?v=<?= file_exists(public_path('assets/css/feedback.css')) ? filemtime(public_path('assets/css/feedback.css')) : time(); ?>">
</head>
<body class="answer-body">
<main class="answer-shell">
    <?php if (!$answerForm): ?>
        <section class="answer-card empty-answer"><h1>Feedback form not found</h1><p>The feedback link is invalid or the form is no longer available.</p></section>
    <?php else: ?>
        <section class="answer-header">
            <div class="answer-top-line"></div>
            <div class="answer-brand-row">
                <div class="answer-logo"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
                <div><strong>TrainHub Al Amin</strong><span><?= e(isCoordinatorFeedbackType((string)$answerForm['feedbackType']) ? 'Coordinator Review Form' : 'Participant Feedback Form') ?></span></div>
            </div>
            <h1><?= e($answerForm['title']) ?></h1>
            <div class="answer-meta">
                <span><?= e($answerForm['courseName'] ?: '-') ?></span>
                <span><?= e($answerForm['sessionName'] ?: $answerForm['sessionID']) ?></span>
                <span><?= e($answerForm['trainerNames'] ?: 'Trainer not assigned') ?></span>
            </div>
        </section>

        <?php if ($message): ?>
            <div class="popup-message <?= e($messageType) ?>" data-auto-show="1"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($messageType !== 'success'): ?>
            <form method="POST" enctype="multipart/form-data" class="answer-form" onsubmit="return openSubmitConfirm(event, 'Submit this feedback?');">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="formID" value="<?= e($answerForm['formID']) ?>">

                <section class="answer-card verify-card">
                    <div class="question-heading clean">
                        <div class="question-number"><?= e(isCoordinatorFeedbackType((string)$answerForm['feedbackType']) ? 'C' : '@') ?></div>
                        <div><h2><?= e(isCoordinatorFeedbackType((string)$answerForm['feedbackType']) ? 'Coordinator Verification' : 'Participant Verification') ?></h2></div>
                    </div>
                    <div class="answer-grid-two">
                        <?php if (strtolower((string)$answerForm['feedbackType']) === 'participant'): ?>
                            <input type="hidden" name="respondentType" value="email">
                            <div class="form-group form-full-span">
                                <label>Registered Email</label>
                                <input type="email" name="respondentID" placeholder="Enter the email used for this course registration" required>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="respondentType" value="staff">
                            <div class="form-group form-full-span">
                                <label>Select Coordinator Name</label>
                                <div class="coordinator-choice-list">
                                    <?php foreach ($coordinatorAnswerOptions as $staff): ?>
                                        <label class="coordinator-choice-card">
                                            <input type="radio" name="respondentID" value="<?= e($staff['staffID']) ?>" required>
                                            <span class="source-avatar"><?= e(strtoupper(substr($staff['staffName'], 0, 1))) ?></span>
                                            <span>
                                                <strong><?= e($staff['staffName']) ?></strong>
                                                <small><?= e($staff['department'] ?: 'Course Coordinator') ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php $catNo = 1; foreach ($answerCategories as $category): ?>
                    <section class="answer-category-block">
                        <div class="category-banner"><span>Section <?= $catNo ?></span><h2><?= e($category['categoryName']) ?></h2></div>
                        <?php $qNo = 1; foreach ($category['questions'] as $question): ?>
                            <div class="answer-card question-card">
                                <div class="question-heading">
                                    <div class="question-number"><?= $qNo ?></div>
                                    <h3><?= e($question['questionText']) ?><?php if ((int)$question['isRequired'] === 1): ?><small>*</small><?php endif; ?></h3>
                                </div>

                                <?php if (!empty($question['questionImage'])): ?>
                                    <div class="question-image-preview"><img src="<?= e($question['questionImage']) ?>" alt="Question image"></div>
                                <?php endif; ?>

                                <?php if ($question['questionType'] === 'rating'): ?>
                                    <div class="rating-scale">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <label><input type="radio" name="answer[<?= e($question['questionID']) ?>]" value="<?= $i ?>" <?= (int)$question['isRequired'] === 1 ? 'required' : '' ?>><span><?= $i ?></span></label>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="scale-caption"><span>Strongly Disagree</span><span>Strongly Agree</span></div>
                                <?php elseif ($question['questionType'] === 'image'): ?>
                                    <label class="answer-image-upload">
                                        <input type="file" name="answer_image[<?= e($question['questionID']) ?>]" accept="image/*" <?= (int)$question['isRequired'] === 1 ? 'required' : '' ?>>
                                        <span class="answer-image-upload-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14a2 2 0 0 0 2-2v-3"/><path d="M3 15v3a2 2 0 0 0 2 2"/></svg>
                                        </span>
                                        <strong>Upload Image</strong>
                                        <small>Click to choose JPG, PNG, GIF or WEBP</small>
                                        <span class="answer-image-file">No image selected</span>
                                    </label>
                                <?php else: ?>
                                    <textarea class="answer-textarea" name="answer[<?= e($question['questionID']) ?>]" placeholder="Write your answer here..." <?= (int)$question['isRequired'] === 1 ? 'required' : '' ?>></textarea>
                                <?php endif; ?>
                            </div>
                        <?php $qNo++; endforeach; ?>
                    </section>
                <?php $catNo++; endforeach; ?>

                <button type="submit" name="submit_feedback" class="submit-answer-btn">Submit Feedback</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>
<div class="confirm-modal" id="confirmModal" hidden><div class="confirm-box"><h3 id="confirmTitle">Confirm Action</h3><p id="confirmText">Are you sure?</p><div><button type="button" class="cancel-modal-btn" onclick="closeConfirmModal()">Cancel</button><button type="button" class="confirm-modal-btn" id="confirmProceedBtn">Yes, Continue</button></div></div></div>
<script>
let pendingForm = null;
let pendingSubmitter = null;
function openSubmitConfirm(event, message){
    if (event.target.dataset.confirmed === '1') {
        delete event.target.dataset.confirmed;
        return true;
    }
    event.preventDefault();
    pendingForm = event.target;
    pendingSubmitter = event.submitter || null;
    document.getElementById('confirmText').textContent = message;
    document.getElementById('confirmModal').hidden = false;
    return false;
}
function closeConfirmModal(){document.getElementById('confirmModal').hidden=true;pendingForm=null;pendingSubmitter=null;}
document.getElementById('confirmProceedBtn').addEventListener('click',function(){
    if (!pendingForm) return;
    const form = pendingForm;
    const submitter = pendingSubmitter;
    closeConfirmModal();
    form.dataset.confirmed = '1';
    if (typeof form.requestSubmit === 'function') {
        submitter ? form.requestSubmit(submitter) : form.requestSubmit();
    } else {
        if (submitter && submitter.name) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden'; hidden.name = submitter.name; hidden.value = submitter.value || '1';
            form.appendChild(hidden);
        }
        form.submit();
    }
});
</script>
</body>
</html>
