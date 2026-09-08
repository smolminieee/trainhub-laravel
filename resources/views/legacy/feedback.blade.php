<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | TrainHub Al Amin</title>
    <link rel="stylesheet" href="assets/css/feedback.css?v=<?= file_exists(public_path('assets/css/feedback.css')) ? filemtime(public_path('assets/css/feedback.css')) : time(); ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= file_exists(public_path('assets/css/style.css')) ? filemtime(public_path('assets/css/style.css')) : time(); ?>">
</head>
<body class="trainhub-app page-feedback">
@include('partials.topbar')

<main class="page">
    <section class="dashboard-header feedback-page-header">
        <div>
            <h1>Feedback Form</h1>
            <p>Manage participant feedback, coordinator review forms, response viewing and printable feedback results.</p>
        </div>

    </section>

    <?php if ($message): ?>
        <script>
        
document.addEventListener('click', function(event){
    const modal = event.target.classList && event.target.classList.contains('feedback-edit-modal') ? event.target : null;
    if (modal) closeFeedbackEditModal(modal.id);
});

document.addEventListener('DOMContentLoaded', function(){
    initRequiredToggles(document);
            if (typeof openGlobalNotice === 'function') {
                openGlobalNotice(<?= json_encode((string)$message) ?>, <?= json_encode($messageType === 'error' ? 'error' : 'success') ?>);
            }
        });
        </script>
    <?php endif; ?>

    <section class="stats-grid feedback-stats">
        <div class="stat-card"><div class="stat-icon stat-blue"><svg viewBox="0 0 24 24"><path d="M9 11h6M9 15h6M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path d="M14 3v5h5"/></svg></div><div><span>Total Forms</span><h2><?= e($totalForms) ?></h2></div></div>
        <div class="stat-card"><div class="stat-icon stat-green"><svg viewBox="0 0 24 24"><path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg></div><div><span>Total Responses</span><h2><?= e($totalResponses) ?></h2></div></div>
        <div class="stat-card"><div class="stat-icon stat-purple"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></div><div><span>Coordinator Forms</span><h2><?= e($totalCoordinatorForms) ?></h2></div></div>
        <div class="stat-card"><div class="stat-icon stat-orange"><svg viewBox="0 0 24 24"><path d="M12 17.3l-6.2 3.5 1.6-6.9-5.4-4.6 7.1-.6L12 2.2l2.9 6.5 7.1.6-5.4 4.6 1.6 6.9z"/></svg></div><div><span>Overall Rating</span><h2><?= e($overallRating ?: '0') ?></h2></div></div>
    </section>

    <?php if ($isResponseView && $responseForm): ?>
        <section class="panel response-view-panel">
            <div class="panel-header course-panel-header">
                <div class="panel-title">
                    <div class="panel-title-icon"><svg viewBox="0 0 24 24"><path d="M3 3h18v18H3z"/><path d="M8 12h8M8 16h5M8 8h8"/></svg></div>
                    <div><h2><?= e($responseForm['title']) ?></h2><p><?= e($responseForm['courseName'] ?: '-') ?> · <?= e($responseForm['sessionName'] ?: $responseForm['sessionID']) ?> · <?= e(feedbackTypeLabel((string)$responseForm['feedbackType'])) ?></p></div>
                </div>
                <div class="response-actions">
                    <?php if (strtolower((string)$responseForm['feedbackType']) === 'participant'): ?>
                        <a class="reset-filter <?= $privacyMode === 'anonymous' ? 'active' : '' ?>" href="feedback.php?responses=1&formID=<?= e(urlencode($responseForm['formID'])) ?>&privacy=anonymous">Anonymous</a>
                        <a class="reset-filter <?= $privacyMode === 'details' ? 'active' : '' ?>" href="feedback.php?responses=1&formID=<?= e(urlencode($responseForm['formID'])) ?>&privacy=details">Detailed View</a>
                    <?php endif; ?>
                    <a class="primary-btn compact-action" href="feedback.php?export_pdf=1&formID=<?= e(urlencode($responseForm['formID'])) ?>&privacy=<?= e($privacyMode) ?>" target="_blank">Export PDF</a>
                    <a class="reset-filter" href="feedback.php?tab=forms">Back</a>
                </div>
            </div>

            <div class="response-content-grid">
                <section class="response-summary-card">
                    <div class="mini-section-header"><span>Category Rating</span><h3>Average by Category</h3></div>
                    <div class="category-summary-list">
                        <?php foreach ($categorySummary as $summary): ?>
                            <div class="category-summary-item"><div><strong><?= e($summary['categoryName']) ?></strong><span><?= e($summary['ratingCount']) ?> rating answers</span></div><b><?= e($summary['averageRating'] ?: '-') ?></b></div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php if (strtolower((string)$responseForm['feedbackType']) === 'participant'): ?>
                <section class="participant-tracker-card">
                    <div class="mini-section-header"><span>Attendance & Feedback</span><h3>Participant Status</h3></div>
                    <div class="participant-response-list">
                        <?php foreach ($participantTracker as $tracker): ?>
                            <?php
                                $answered = (int)($tracker['totalAnswers'] ?? 0) > 0;
                                $approved = (bool)$tracker['attendanceApproved'];
                                $statusClass = $answered ? 'answered' : 'pending';
                                $statusText = $answered ? 'Answered' : 'Not Answered';
                                $participantStatusName = $privacyMode === 'details' ? ($tracker['participantName'] ?? $tracker['sourceLabel']) : $tracker['sourceLabel'];
                                $participantStatusSub = $privacyMode === 'details' ? (($tracker['sourceLabel'] ?? '') . ' · ' . ucwords(str_replace('_', ' ', $tracker['participantType']))) : ucwords(str_replace('_', ' ', $tracker['participantType']));
                            ?>
                            <div class="participant-response-item <?= e($statusClass) ?>">
                                <div class="participant-source-icon"><?= e(strtoupper(substr($tracker['participantType'], 0, 1))) ?></div>
                                <div><strong><?= e($participantStatusName) ?></strong><span><?= e($participantStatusSub) ?></span></div>
                                <em><?= e($statusText) ?></em>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php else: ?>
                <section class="participant-tracker-card">
                    <div class="mini-section-header"><span>Coordinator Review</span><h3>Coordinator Status</h3></div>
                    <div class="participant-response-list">
                        <?php foreach ($participantTracker as $staff): ?>
                            <?php $answered = (int)($staff['totalAnswers'] ?? 0) > 0; ?>
                            <div class="participant-response-item <?= $answered ? 'answered' : 'pending' ?>">
                                <div class="participant-source-icon"><?= e(strtoupper(substr($staff['staffName'], 0, 1))) ?></div>
                                <div><strong><?= e($staff['staffName']) ?></strong><span><?= e($staff['department'] ?: 'Course Coordinator') ?></span></div>
                                <em><?= $answered ? 'Answered' : 'Not Answered' ?></em>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <section class="anonymous-response-section compact-response-section">
                <div class="response-table-header"><div class="mini-section-header"><span>Response Details</span><h3><?= $privacyMode === 'details' ? 'Detailed Response View' : 'Anonymous Response Overview' ?></h3></div></div>

                <?php if (empty($responseGroups)): ?>
                    <div class="empty-state">No response has been submitted yet.</div>
                <?php else: ?>
                    <div class="compact-response-table-wrap">
                        <table class="compact-response-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th><?= $privacyMode === 'details' ? 'Respondent Details' : 'School / Department / Outsider' ?></th>
                                    <th>Type</th>
                                    <th>Category Rating</th>
                                    <th>Overall</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $responseNo = 1; foreach ($responseGroups as $key => $group): ?>
                                <?php
                                    $overall = count($group['ratings']) ? round(array_sum($group['ratings']) / count($group['ratings']), 2) : '-';
                                    $detailID = 'response-detail-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$key);
                                    $primaryName = $privacyMode === 'details' ? ($group['displayName'] ?: $group['respondentKey']) : $group['sourceLabel'];
                                    $secondaryName = $privacyMode === 'details'
                                        ? $group['sourceLabel']
                                        : 'Anonymous Response ' . $responseNo;
                                ?>
                                <tr class="response-main-row">
                                    <td><span class="row-number"><?= $responseNo ?></span></td>
                                    <td><div class="source-cell"><span class="source-avatar"><?= e(strtoupper(substr($group['respondentType'], 0, 1))) ?></span><div><strong><?= e($primaryName) ?></strong><small><?= e($secondaryName) ?></small></div></div></td>
                                    <td><span class="type-badge"><?= e(ucwords(str_replace('_', ' ', $group['respondentType']))) ?></span></td>
                                    <td><div class="category-score-pills"><?php foreach ($group['categories'] as $category): ?><?php $catAvg = count($category['ratings']) ? round(array_sum($category['ratings']) / count($category['ratings']), 2) : '-'; ?><span><?= e($category['categoryName']) ?><b><?= e($catAvg) ?></b></span><?php endforeach; ?></div></td>
                                    <td><span class="overall-mini-score"><?= e($overall) ?></span></td>
                                    <td><span class="submitted-date"><?= e(date('d M Y', strtotime($group['responseDate']))) ?></span><small class="submitted-time"><?= e(date('h:i A', strtotime($group['responseDate']))) ?></small></td>
                                    <td><button type="button" class="detail-toggle-btn" onclick="openResponseDetailModal('<?= e($detailID) ?>')">View details</button></td>
                                </tr>
                                <tr id="<?= e($detailID) ?>" class="response-detail-row">
                                    <td colspan="7"><div class="response-detail-panel">
                                        <?php foreach ($group['categories'] as $category): ?>
                                            <?php $catAvg = count($category['ratings']) ? round(array_sum($category['ratings']) / count($category['ratings']), 2) : '-'; ?>
                                            <div class="detail-category-block">
                                                <div class="detail-category-head"><strong><?= e($category['categoryName']) ?></strong><span>Average: <?= e($catAvg) ?></span></div>
                                                <div class="detail-question-list">
                                                    <?php foreach ($category['answers'] as $answer): ?>
                                                        <div class="detail-question-item">
                                                            <div><small><?= e($answer['questionType']) ?></small><p><?= e($answer['questionText']) ?></p><?php if (!empty($answer['questionImage'])): ?><img class="detail-question-image" src="<?= e($answer['questionImage']) ?>" alt="Question image"><?php endif; ?></div>
                                                            <div class="detail-answer-value">
                                                                <?php if ($answer['questionType'] === 'rating'): ?>
                                                                    <b><?= e($answer['rating']) ?> / 5</b>
                                                                <?php elseif ($answer['questionType'] === 'image' && isImagePath($answer['comment'] ?? '')): ?>
                                                                    <img class="uploaded-answer-image" src="<?= e($answer['comment']) ?>" alt="Uploaded feedback image">
                                                                <?php else: ?>
                                                                    <span><?= e($answer['comment'] ?: '-') ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div></td>
                                </tr>
                            <?php $responseNo++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    <?php else: ?>
        <section class="feedback-workspace" id="feedbackWorkspace">
            <div class="feedback-workspace-tabs" role="tablist">
                <button type="button" class="feedback-workspace-tab <?= $activeTab === 'participant' ? 'active' : '' ?>" data-feedback-tab="participant" onclick="switchFeedbackTab('participant')">Participant Form</button>
                <button type="button" class="feedback-workspace-tab <?= $activeTab === 'coordinator' ? 'active' : '' ?>" data-feedback-tab="coordinator" onclick="switchFeedbackTab('coordinator')">Coordinator Review</button>
                <button type="button" class="feedback-workspace-tab <?= $activeTab === 'forms' ? 'active' : '' ?>" data-feedback-tab="forms" onclick="switchFeedbackTab('forms')">Form List</button>
            </div>

            <div class="feedback-tab-panel <?= $activeTab === 'participant' ? 'active' : '' ?>" data-feedback-panel="participant">
                <section class="panel form-builder-panel"><div class="panel-header"><div class="panel-title"><div class="panel-title-icon"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></div><div><h2>Create Participant Feedback Form</h2></div></div></div><?php renderBuilder('participant', $sessions); ?></section>
            </div>

            <div class="feedback-tab-panel <?= $activeTab === 'coordinator' ? 'active' : '' ?>" data-feedback-panel="coordinator">
                <section class="panel form-builder-panel"><div class="panel-header"><div class="panel-title"><div class="panel-title-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div><div><h2>Create Coordinator Review Form</h2><p>Build a custom internal review form for the course coordinator.</p></div></div></div><?php renderBuilder('coordinator', $sessions); ?></section>
            </div>

            <div class="feedback-tab-panel <?= $activeTab === 'forms' ? 'active' : '' ?>" data-feedback-panel="forms">
                <section class="panel created-forms-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <div class="panel-title-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></div>
                            <div><h2>Feedback Form Library</h2><p>Participant feedback and coordinator reviews are separated below.</p></div>
                        </div>
                    </div>

                    <div class="form-list-toolbar simple-toolbar standardized-filter-row">
                        <div class="form-list-search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input type="search" id="feedbackFormSearch" placeholder="Search form, course, session or trainer..."></div>
                        <select id="feedbackStatusFilter" class="form-list-filter"><option value="all">All forms</option><option value="has_responses">Has responses</option><option value="no_responses">No responses</option></select>
                        <button type="button" class="list-filter-btn" id="feedbackFormApply">Filter</button>
                        <button type="button" class="list-reset-btn" id="feedbackFormReset">Reset</button>
                        <div class="form-list-result" id="feedbackFormResult">Showing <?= e(count($forms)) ?> forms</div>
                    </div>

                    <div class="created-form-sections" id="createdFormList">
                        <section class="created-form-section">
                            <div class="created-form-section-head">
                                <div><span>Participant Feedback</span><h3>Forms answered by course participants</h3></div>
                                <b><?= e(count($participantForms)) ?></b>
                            </div>
                            <div class="created-form-list grouped-form-list">
                                <?php if (empty($participantForms)): ?>
                                    <div class="empty-state">No participant feedback form created yet.</div>
                                <?php else: ?>
                                    <?php foreach ($participantForms as $form): ?>
                                        <?php renderCreatedFormCard($form, $sessions, $baseUrl, $questionGroupsByForm); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="created-form-section">
                            <div class="created-form-section-head coordinator-head">
                                <div><span>Coordinator Review</span><h3>Forms answered by course coordinators</h3></div>
                                <b><?= e(count($coordinatorForms)) ?></b>
                            </div>
                            <div class="created-form-list grouped-form-list">
                                <?php if (empty($coordinatorForms)): ?>
                                    <div class="empty-state">No coordinator review form created yet.</div>
                                <?php else: ?>
                                    <?php foreach ($coordinatorForms as $form): ?>
                                        <?php renderCreatedFormCard($form, $sessions, $baseUrl, $questionGroupsByForm); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>

                        <div class="empty-state form-list-filter-empty" id="feedbackFormFilterEmpty" hidden>No feedback form matches the selected search or filter.</div>
                    </div>
                </section>
            </div>

        </section>
    <?php endif; ?>
</main>

<div class="confirm-modal" id="confirmModal" hidden>
    <div class="confirm-box"><h3>Confirm Action</h3><p id="confirmText">Are you sure?</p><div><button type="button" class="cancel-modal-btn" onclick="closeConfirmModal()">Cancel</button><button type="button" class="confirm-modal-btn" id="confirmProceedBtn">Yes, Continue</button></div></div>
</div>

<div class="response-detail-modal" id="responseDetailModal" hidden>
    <div class="response-detail-modal-box">
        <button type="button" class="response-detail-close" onclick="closeResponseDetailModal()">×</button>
        <div class="response-detail-modal-head">
            <span>Response Details</span>
            <h3>Question-Level Answers</h3>
        </div>
        <div class="response-detail-modal-body" id="responseDetailModalBody"></div>
    </div>
</div>

<script>
let pendingForm = null;
let pendingSubmitter = null;
function switchFeedbackTab(tabName) {
    if (tabName === 'pic') tabName = 'coordinator';
    ['participant','coordinator','forms'].forEach(function(tab){
        document.querySelectorAll('[data-feedback-tab="'+tab+'"]').forEach(btn => btn.classList.toggle('active', tab === tabName));
        document.querySelectorAll('[data-feedback-panel="'+tab+'"]').forEach(panel => panel.classList.toggle('active', tab === tabName));
    });
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabName);
    url.searchParams.delete('responses');
    url.searchParams.delete('formID');
    window.history.replaceState({}, '', url.toString());
}
function openSubmitConfirm(event, message) {
    if (event.target.dataset.confirmed === '1') {
        delete event.target.dataset.confirmed;
        return true;
    }
    event.preventDefault();
    pendingForm = event.target;
    pendingSubmitter = event.submitter || null;
    document.getElementById('confirmText').textContent = message;
    document.getElementById('confirmModal').hidden = false;
    if (typeof window.trainhubSyncModalState === 'function') window.trainhubSyncModalState();
    return false;
}
function closeConfirmModal() {
    document.getElementById('confirmModal').hidden = true;
    pendingForm = null;
    pendingSubmitter = null;
    if (typeof window.trainhubSyncModalState === 'function') window.trainhubSyncModalState();
}
document.getElementById('confirmProceedBtn').addEventListener('click', function(){
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
            hidden.type = 'hidden';
            hidden.name = submitter.name;
            hidden.value = submitter.value || '1';
            form.appendChild(hidden);
        }
        form.submit();
    }
});
function copyLink(button) {
    const input = button.closest('.copy-link-box').querySelector('input');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(function(){ showToast('success', 'Feedback link copied successfully.'); });
}
function showToast(type, text) {
    let toast = document.createElement('div');
    toast.className = 'popup-message ' + type + ' live-toast';
    toast.textContent = text;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 20);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 260); }, 2600);
}
document.querySelectorAll('.popup-message[data-auto-show="1"]').forEach(function(el){ setTimeout(() => el.classList.add('show'), 30); setTimeout(() => el.classList.remove('show'), 3600); });
function openFeedbackEditModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    initRequiredToggles(modal);
    modal.querySelectorAll('select[name^="question_type"]').forEach(toggleImageUpload);
    if (typeof window.trainhubSyncModalState === 'function') window.trainhubSyncModalState();
}
function closeFeedbackEditModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    const anyOpen = Array.from(document.querySelectorAll('.feedback-edit-modal')).some(el => getComputedStyle(el).display !== 'none');
    document.body.classList.toggle('modal-open', anyOpen);
    if (typeof window.trainhubSyncModalState === 'function') window.trainhubSyncModalState();
}
function openResponseDetailModal(id) {
    const row = document.getElementById(id);
    const modal = document.getElementById('responseDetailModal');
    const body = document.getElementById('responseDetailModalBody');
    if (!row || !modal || !body) return;
    const panel = row.querySelector('.response-detail-panel');
    body.innerHTML = panel ? panel.innerHTML : '';
    modal.hidden = false;
    if (typeof window.trainhubSyncModalState === 'function') window.trainhubSyncModalState();
}
function closeResponseDetailModal() {
    const modal = document.getElementById('responseDetailModal');
    const body = document.getElementById('responseDetailModalBody');
    if (body) body.innerHTML = '';
    if (modal) modal.hidden = true;
    if (typeof window.trainhubSyncModalState === 'function') window.trainhubSyncModalState();
}

document.addEventListener('click', function(event){
    const modal = event.target.classList && event.target.classList.contains('feedback-edit-modal') ? event.target : null;
    if (modal) closeFeedbackEditModal(modal.id);
});

function addQuestion(button) {
    const category = button.closest('.category-builder-card');
    const holder = category.querySelector('.questions-holder');
    const catIndex = Array.from(category.closest('.category-container').querySelectorAll('.category-builder-card')).indexOf(category);
    const qIndex = holder.querySelectorAll('.question-builder-card').length;
    const card = document.createElement('div');
    card.className = 'question-builder-card';
    card.innerHTML = `<div class="question-builder-top"><strong>Question ${qIndex + 1}</strong><button type="button" class="remove-question-btn" onclick="removeQuestion(this)">Remove</button></div><div class="form-group"><label>Question Text</label><input type="text" name="question_text[${catIndex}][]" placeholder="Write your question here..." required></div><div class="question-options-grid"><div class="form-group"><label>Question Type</label><select name="question_type[${catIndex}][]" onchange="toggleImageUpload(this)"><option value="rating">Rating 1 - 5</option><option value="paragraph">Comment</option><option value="image">Image Upload Answer</option></select></div><div class="form-group required-column"><label>Required</label><label class="required-toggle"><input type="hidden" name="is_required[${catIndex}][${qIndex}]" value="0"><input type="checkbox" name="is_required[${catIndex}][${qIndex}]" value="1" checked><span></span><b>Required</b></label></div></div><div class="form-group image-upload-group" hidden><label>Reference Image</label><input type="file" name="question_image[${catIndex}][${qIndex}]" accept="image/*"></div>`;
    holder.appendChild(card);
    refreshBuilder(category.closest('.builder-root'));
}
function removeQuestion(button) {
    const category = button.closest('.category-builder-card');
    if (category.querySelectorAll('.question-builder-card').length <= 1) { showToast('error', 'At least one question is required.'); return; }
    button.closest('.question-builder-card').remove();
    refreshBuilder(category.closest('.builder-root'));
}
function addCategory(button) {
    const form = button.closest('.builder-root');
    const container = form.querySelector('.category-container');
    const isParticipant = form.dataset.builderType === 'participant';
    if (isParticipant) { showToast('error', 'Participant feedback uses the three fixed categories.'); return; }
    const card = document.createElement('div');
    card.className = 'category-builder-card';
    card.dataset.categoryRole = 'custom';
    const next = container.querySelectorAll('.category-builder-card').length;
    card.innerHTML = `<div class="category-builder-top"><div><strong>Category ${next + 1}</strong></div><button type="button" class="remove-category-btn" onclick="removeCategory(this)">Remove</button></div><div class="form-group"><label>Category Name</label><input type="text" name="category_name[]" placeholder="Example: Facilities Evaluation" required></div><div class="questions-holder"><div class="question-builder-card"><div class="question-builder-top"><strong>Question 1</strong><button type="button" class="remove-question-btn" onclick="removeQuestion(this)">Remove</button></div><div class="form-group"><label>Question Text</label><input type="text" name="question_text[${next}][]" placeholder="Write your question here..." required></div><div class="question-options-grid"><div class="form-group"><label>Question Type</label><select name="question_type[${next}][]" onchange="toggleImageUpload(this)"><option value="rating">Rating 1 - 5</option><option value="paragraph">Comment</option><option value="image">Image Upload Answer</option></select></div><div class="form-group required-column"><label>Required</label><label class="required-toggle"><input type="hidden" name="is_required[${next}][0]" value="0"><input type="checkbox" name="is_required[${next}][0]" value="1" checked><span></span><b>Required</b></label></div></div><div class="form-group image-upload-group" hidden><label>Reference Image</label><input type="file" name="question_image[${next}][0]" accept="image/*"></div></div></div><button type="button" class="add-question-btn" onclick="addQuestion(this)">Add Question</button>`;
    if (isParticipant) {
        const overall = container.querySelector('[data-category-role="overall"]');
        container.insertBefore(card, overall);
    } else {
        container.appendChild(card);
    }
    refreshBuilder(form);
    setTimeout(function(){ card.scrollIntoView({ behavior: 'smooth', block: 'center' }); card.classList.add('newly-added-category'); setTimeout(function(){ card.classList.remove('newly-added-category'); }, 1600); }, 80);
}
function removeCategory(button) {
    const form = button.closest('.builder-root');
    const category = button.closest('.category-builder-card');
    if (category.classList.contains('locked-category') || category.dataset.categoryRole === 'overall') { showToast('error', 'This category cannot be removed.'); return; }
    if (category.closest('.category-container').querySelectorAll('.category-builder-card').length <= 1) { showToast('error', 'At least one category is required.'); return; }
    category.remove();
    refreshBuilder(form);
}
function toggleImageUpload(select) {
    const card = select.closest('.question-builder-card');
    const upload = card ? card.querySelector('.image-upload-group') : null;
    if (upload) upload.hidden = select.value !== 'image';
}

function syncCoordinatorHint(select) {
    const form = select.closest('.builder-root');
    if (!form || form.dataset.builderType === 'participant') return;
    const box = form.querySelector('[data-coordinator-preview] .coordinator-placeholder');
    if (!box) return;
    const option = select.options[select.selectedIndex];
    const name = option?.dataset.coordinatorName || '';
    const department = option?.dataset.coordinatorDepartment || '';
    box.innerHTML = name
        ? '<strong>' + name + '</strong><span>' + (department || 'Course Coordinator') + '</span>'
        : 'No coordinator assigned yet. The form creator will be used as coordinator.';
}

function syncRequiredToggle(toggle) {
    const checkbox = toggle.querySelector('input[type="checkbox"]');
    const label = toggle.querySelector('b');
    if (!checkbox || !label) return;
    label.textContent = checkbox.checked ? 'Required' : 'Optional';
    toggle.classList.toggle('is-required', checkbox.checked);
}
function initRequiredToggles(root) {
    (root || document).querySelectorAll('.required-toggle').forEach(function(toggle){
        const checkbox = toggle.querySelector('input[type="checkbox"]');
        if (!checkbox) return;
        if (!toggle.dataset.requiredBound) {
            toggle.addEventListener('click', function(event){
                event.preventDefault();
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles:true }));
            });
            checkbox.addEventListener('change', function(){ syncRequiredToggle(toggle); });
            toggle.dataset.requiredBound = '1';
        }
        syncRequiredToggle(toggle);
    });
}

function refreshBuilder(form) {
    const categories = Array.from(form.querySelectorAll('.category-builder-card'));
    categories.forEach(function(category, cIndex){
        const catTitle = category.querySelector('.category-builder-top strong');
        if (catTitle) catTitle.textContent = 'Category ' + (cIndex + 1);
        const questions = Array.from(category.querySelectorAll('.question-builder-card'));
        questions.forEach(function(question, qIndex){
            const qTitle = question.querySelector('.question-builder-top strong');
            if (qTitle) qTitle.textContent = 'Question ' + (qIndex + 1);
            question.querySelectorAll('input, select, textarea').forEach(function(input){
                if (input.name.startsWith('question_text')) input.name = `question_text[${cIndex}][]`;
                if (input.name.startsWith('question_type')) input.name = `question_type[${cIndex}][]`;
                if (input.name.startsWith('is_required')) input.name = `is_required[${cIndex}][${qIndex}]`;
                if (input.name.startsWith('question_image')) input.name = `question_image[${cIndex}][${qIndex}]`;
                if (input.name.startsWith('existing_question_image')) input.name = `existing_question_image[${cIndex}][${qIndex}]`;
            });
        });
    });
    initRequiredToggles(form);
}
function applyFormFilters(){
    const q = (document.getElementById('feedbackFormSearch')?.value || '').toLowerCase().trim();
    const status = document.getElementById('feedbackStatusFilter')?.value || 'all';
    const cards = Array.from(document.querySelectorAll('.filterable-created-form'));
    let shown = 0;
    cards.forEach(card => {
        const matchQ = q === '' || (card.dataset.search || '').toLowerCase().includes(q);
        const responses = Number(card.dataset.responses || 0);
        const matchStatus = status === 'all' || (status === 'has_responses' ? responses > 0 : responses === 0);
        const show = matchQ && matchStatus;
        card.hidden = !show;
        if (show) shown++;
    });
    const result = document.getElementById('feedbackFormResult');
    if (result) result.textContent = `Showing ${shown} of ${cards.length} forms`;
    const empty = document.getElementById('feedbackFormFilterEmpty');
    if (empty) {
        const hasActiveFilter = q !== '' || status !== 'all';
        empty.hidden = !hasActiveFilter || shown !== 0 || cards.length === 0;
    }
}
document.addEventListener('DOMContentLoaded', function(){
    initRequiredToggles(document);
    document.querySelectorAll('select[name^="question_type"]').forEach(toggleImageUpload);
    document.querySelectorAll('select[name="sessionID"]').forEach(function(select){
        select.addEventListener('change', function(){ syncCoordinatorHint(select); });
        syncCoordinatorHint(select);
    });
    document.getElementById('feedbackFormApply')?.addEventListener('click', applyFormFilters);
    document.getElementById('feedbackFormReset')?.addEventListener('click', function(){
        const search = document.getElementById('feedbackFormSearch');
        const status = document.getElementById('feedbackStatusFilter');
        if (search) search.value = '';
        if (status) status.value = 'all';
        applyFormFilters();
    });
    document.getElementById('feedbackFormSearch')?.addEventListener('keydown', function(event){
        if (event.key === 'Enter') {
            event.preventDefault();
            applyFormFilters();
        }
    });
    applyFormFilters();
});
</script>
</body>
</html>
