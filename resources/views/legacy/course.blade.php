<!DOCTYPE html>
<html>
<head>
    <title>Training | TrainHub Al Amin</title>
    <!-- VERIFIED BUILD: avatar list + view-only actions + staff search + custom delete popup + session view/edit -->
    <link rel="stylesheet" href="assets/css/course.css?v=<?php echo file_exists(public_path('assets/css/course.css')) ? filemtime(public_path('assets/css/course.css')) : time(); ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo file_exists(public_path('assets/css/style.css')) ? filemtime(public_path('assets/css/style.css')) : time(); ?>">
</head>
<body class="trainhub-app page-course">

@include('partials.topbar')
<!-- COURSE_BUILD_CHECK: final-updated-course-page | view-delete-only | staff-search | no-browser-confirm | session-edit-toggle -->


<?php if (!empty($message)) { ?>
    <div class="flash-popup-backdrop" id="flashPopup">
        <div class="flash-popup flash-<?php echo h($messageType); ?>">
            <div class="flash-icon"><?php echo $messageType === "success" ? "✓" : "!"; ?></div>
            <h3><?php echo $messageType === "success" ? "Successful" : "Notice"; ?></h3>
            <p><?php echo h($message); ?></p>
            <button type="button" onclick="closeFlashPopup()">OK</button>
        </div>
    </div>
<?php } ?>

<div class="page">
    <div class="dashboard-header">
        <div>
            <h1>Training Management</h1>
            <p>Manage trainings, sessions, trainers, QR attendance and participants.</p>
        </div>
        <?php if ($activeMode === "") { ?>
            <button class="primary-btn" onclick="openModal('addTrainingModal')">+ Add Training</button>
        <?php } else { ?>
            <a class="back-link-btn" href="course.php">Back to Training List</a>
        <?php } ?>
    </div>

    <section class="stats-grid">
        <div class="stat-card"><div class="stat-icon stat-blue"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4v15.5A2.5 2.5 0 0 0 6.5 22H20V6a2 2 0 0 0-2-2H4Z"/></svg></div><div><span>Total Trainings</span><h2><?php echo $totalTrainings; ?></h2></div></div>
        <div class="stat-card"><div class="stat-icon stat-green"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div><div><span>Upcoming</span><h2><?php echo $totalUpcoming; ?></h2></div></div>
        <div class="stat-card"><div class="stat-icon stat-orange"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div><div><span>Ongoing</span><h2><?php echo $totalOngoing; ?></h2></div></div>
        <div class="stat-card"><div class="stat-icon stat-purple"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg></div><div><span>Total Sessions</span><h2><?php echo $totalSessions; ?></h2></div></div>
    </section>

    <?php if ($activeMode !== "" && !$activeCourse) { ?>
        <section class="panel"><div class="empty-state">Selected training record was not found.</div></section>
    <?php } elseif ($activeMode === "view") { ?>
        <?php $course = $activeCourse; $courseSessions = $sessionsByTraining[$course["courseID"]] ?? []; $approvedParticipants = $participantsByTraining[$course["courseID"]] ?? []; ?>
        <section class="panel page-panel detail-page-panel">
            <div class="page-panel-header">
                <div>
                    <span>Training Details</span>
                    <h2><?php echo h($course["courseName"]); ?></h2>
                    <p><?php echo h($course["description"] ?: "No description provided."); ?></p>
                </div>
                <div class="header-actions-inline">
                    <a class="secondary-action-btn" href="course.php?edit=<?php echo urlencode($course["courseID"]); ?>">Edit Training</a>
                    <a class="secondary-action-btn" href="course.php?sessions=<?php echo urlencode($course["courseID"]); ?>">Manage Session</a>
                </div>
            </div>

            <div class="page-panel-body">
                <div class="detail-grid course-detail-information-grid">
                    <div class="detail-box"><span>Category</span><strong><?php echo h($course["courseCategory"]); ?></strong></div>
                    <div class="detail-box"><span>Target Audience</span><strong><?php echo h(formatTargetAudience($course["targetAudience"], $course["otherTargetAudience"])); ?></strong></div>
                    <div class="detail-box"><span>Type</span><strong><?php echo h($course["courseType"]); ?></strong></div>
                    <div class="detail-box"><span>Mode</span><strong><?php echo h(ucfirst($course["mode"])); ?></strong></div>
                    <div class="detail-box"><span>Capacity</span><strong><?php echo h($course["capacity"]); ?></strong></div>
                    <div class="detail-box"><span>Price</span><strong>RM <?php echo h($course["price"]); ?></strong></div>
                    <div class="detail-box"><span>Status</span><strong><span class="status-badge <?php echo h(statusClass($course["status"])); ?>"><?php echo h(trainingStatusLabel($course["status"])); ?></span></strong></div>
                    <div class="detail-box"><span>Training Rating</span><strong><?php echo h(formatTrainingRating($course["displayTrainingRating"])); ?> / 5</strong></div>
                    <div class="detail-box"><span>Registration Closing Date</span><strong><?php echo h($course["closeDate"] ?: "-"); ?></strong></div>
                    <div class="detail-box"><span>Organiser</span><strong><?php echo h($course["organiserName"] ?: "-"); ?></strong></div>
                    <div class="detail-box"><span>Person In Charge</span><strong><?php echo h(renderStaffPICNames($staffOptions, $course["staffAttendeeIDs"] ?? "")); ?></strong></div>
                    <div class="detail-box"><span>Trainer</span><strong><?php echo h($course["trainerNames"] ?: "Not assigned"); ?></strong></div>
                    <div class="detail-box detail-full"><span>Online Link</span><strong class="detail-link-value"><?php echo h($course["onlineLink"] ?: "-"); ?></strong></div>
                    <div class="detail-box detail-full"><span>WhatsApp Group</span><strong class="detail-link-value"><?php echo h($course["whatsappGroup"] ?: "-"); ?></strong></div>
                    <div class="detail-box detail-full"><span>Poster</span><strong><?php if (!empty($course["poster"])) { ?><a href="<?php echo h($course["poster"]); ?>" target="_blank">View Poster</a><?php } else { echo "-"; } ?></strong></div>
                </div>

                <div class="participant-section compact-participant-section" data-participant-filter-root>
                    <div class="participant-header compact-heading">
                        <div>
                            <h3>Participants by Session</h3>
                            <p>Choose a session tab, filter participants and approve scanned QR attendance.</p>
                        </div>
                    </div>

                    <div class="participant-filter-toolbar compact-toolbar">
                        <select data-participant-type-filter aria-label="Filter participant type">
                            <option value="all">All participant types</option>
                            <option value="teacher">Teacher</option>
                            <option value="new_teacher">New Teacher</option>
                            <option value="staff">Staff</option>
                            <option value="public">Public</option>
                        </select>

                        <select data-participant-attendance-filter aria-label="Filter attendance">
                            <option value="all">All attendance</option>
                            <option value="scanned">Scanned QR</option>
                            <option value="not_scanned">Not scanned</option>
                            <option value="approved">Approved</option>
                            <option value="not_approved">Not Approved</option>
                        </select>

                        <button type="button" class="participant-filter-btn" data-participant-filter-apply>Filter</button>
                        <button type="button" class="participant-reset-btn" data-participant-filter-reset>Reset</button>
                    </div>

                    <?php if (count($courseSessions) > 0) { ?>
                        <div class="session-tabs" role="tablist">
                            <?php foreach ($courseSessions as $index => $session) { ?>
                                <button type="button" class="session-tab-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-session-tab="sessionPane_<?php echo h($session["sessionID"]); ?>">
                                    <?php echo h($session["sessionName"] ?: "Session " . ($index + 1)); ?>
                                    <small><?php echo h(date('d M Y', strtotime($session["sessionDate"]))); ?></small>
                                </button>
                            <?php } ?>
                        </div>

                        <div class="session-tab-panes">
                            <?php foreach ($courseSessions as $index => $session) { ?>
                                <section class="session-tab-pane <?php echo $index === 0 ? 'active' : ''; ?>" id="sessionPane_<?php echo h($session["sessionID"]); ?>" data-session-pane>
                                    <div class="session-pane-header">
                                        <div>
                                            <h4><?php echo h($session["sessionName"] ?: "Session"); ?></h4>
                                            <p><?php echo h(formatSessionTime($session["sessionDate"], $session["startTime"], $session["endTime"])); ?> • <?php echo h($session["location"] ?: "No location added"); ?></p>
                                        </div>
                                        <?php if (!empty($session["qrCode"])) { ?>
                                            <a href="<?php echo h($session["attendanceLink"] ?: $session["qrCode"]); ?>" target="_blank" class="session-qr-link">Open QR</a>
                                        <?php } ?>
                                    </div>

                                    <div class="participant-list compact-participant-list">
                                        <?php if (count($approvedParticipants) > 0) { ?>
                                            <?php foreach ($approvedParticipants as $participant) { ?>
                                                <?php
                                                $participantType = strtolower((string)$participant["participantType"]);
                                                $sourceID = (string)participantSourceID($participant);
                                                $lookupKey = $participantType . "|" . $sourceID;
                                                $attendance = $attendanceBySession[$session["sessionID"]][$lookupKey] ?? null;
                                                $hasScanned = $attendance !== null;
                                                $attendanceStatus = $hasScanned ? strtolower((string)$attendance["attendanceStatus"]) : "not_scanned";
                                                ?>
                                                <div class="participant-item compact-participant-item" data-participant-item data-participant-type="<?php echo h($participantType); ?>" data-attendance="<?php echo h(!$hasScanned ? "not_scanned" : ($attendanceStatus === "approved" ? "approved" : "not_approved")); ?>" data-scan="<?php echo h($hasScanned ? "scanned" : "not_scanned"); ?>">
                                                    <div class="participant-compact-main">
                                                        <strong><?php echo h($participant["participantName"]); ?></strong>
                                                        <span><?php echo h(participantTypeLabel($participantType)); ?> • <?php echo h($participant["organisationName"] ?: "-"); ?></span>
                                                    </div>
                                                    <div class="participant-attendance-actions">
                                                        <?php if (!$hasScanned) { ?>
                                                            <em class="attendance-pill not-scanned">Not scanned</em>
                                                            <em class="attendance-pill not-approved">Not Approved</em>
                                                            <button type="button" class="disabled-approve-btn" disabled>Cannot approve</button>
                                                        <?php } elseif ($attendanceStatus === "approved") { ?>
                                                            <em class="attendance-pill scanned">Scanned QR</em>
                                                            <em class="attendance-pill approved">Approved</em>
                                                            <button
                                                                type="button"
                                                                class="attendance-remark-btn<?php echo trim((string)($attendance['remarks'] ?? '')) !== '' ? ' has-remark' : ''; ?>"
                                                                data-course-id="<?php echo h($course["courseID"]); ?>"
                                                                data-participant-type="<?php echo h($participantType); ?>"
                                                                data-attendance-id="<?php echo h($attendance["attendanceID"]); ?>"
                                                                data-participant-name="<?php echo h($participant["participantName"]); ?>"
                                                                data-attendance-status="Approved"
                                                                data-remarks="<?php echo h($attendance['remarks'] ?? ''); ?>"
                                                                onclick="openAttendanceRemarkModal(this)"
                                                            ><span>Remarks</span><i class="remark-indicator" aria-hidden="true"></i></button>
                                                            <form method="POST" class="attendance-status-form">
                                                                <input type="hidden" name="action" value="unapprove_attendance">
                                                                <input type="hidden" name="courseID" value="<?php echo h($course["courseID"]); ?>">
                                                                <input type="hidden" name="participantType" value="<?php echo h($participantType); ?>">
                                                                <input type="hidden" name="attendanceID" value="<?php echo h($attendance["attendanceID"]); ?>">
                                                                <button type="submit" class="not-approve-btn">Set Not Approved</button>
                                                            </form>
                                                        <?php } else { ?>
                                                            <em class="attendance-pill scanned">Scanned QR</em>
                                                            <em class="attendance-pill not-approved">Not Approved</em>
                                                            <button
                                                                type="button"
                                                                class="attendance-remark-btn<?php echo trim((string)($attendance['remarks'] ?? '')) !== '' ? ' has-remark' : ''; ?>"
                                                                data-course-id="<?php echo h($course["courseID"]); ?>"
                                                                data-participant-type="<?php echo h($participantType); ?>"
                                                                data-attendance-id="<?php echo h($attendance["attendanceID"]); ?>"
                                                                data-participant-name="<?php echo h($participant["participantName"]); ?>"
                                                                data-attendance-status="Not Approved"
                                                                data-remarks="<?php echo h($attendance['remarks'] ?? ''); ?>"
                                                                onclick="openAttendanceRemarkModal(this)"
                                                            ><span>Remarks</span><i class="remark-indicator" aria-hidden="true"></i></button>
                                                            <form method="POST" class="attendance-status-form">
                                                                <input type="hidden" name="action" value="approve_attendance">
                                                                <input type="hidden" name="courseID" value="<?php echo h($course["courseID"]); ?>">
                                                                <input type="hidden" name="participantType" value="<?php echo h($participantType); ?>">
                                                                <input type="hidden" name="attendanceID" value="<?php echo h($attendance["attendanceID"]); ?>">
                                                                <button type="submit" class="approve-btn">Approve</button>
                                                            </form>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <div class="empty-state">No approved participant yet.</div>
                                        <?php } ?>
                                    </div>
                                    <div class="empty-state participant-session-filter-empty" data-session-filter-empty hidden>No participant matches the selected filter.</div>
                                </section>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="empty-state">No training session has been added yet.</div>
                    <?php } ?>
                </div>
            </div>
        </section>
    <?php } elseif ($activeMode === "edit") { ?>
        <?php $course = $activeCourse; ?>
        <section class="panel page-panel edit-page-panel">
            <div class="page-panel-header">
                <div>
                    <span>Edit Training</span>
                    <h2><?php echo h($course["courseName"]); ?></h2>
                    <p>Update training information. Session details can be edited in Manage Session.</p>
                </div>
                <a class="secondary-action-btn" href="course.php?sessions=<?php echo urlencode($course["courseID"]); ?>">Manage Session</a>
            </div>
            <form method="POST" enctype="multipart/form-data" class="modal-form page-form course-rule-form">
                <input type="hidden" name="action" value="update_course">
                <input type="hidden" name="courseID" value="<?php echo h($course["courseID"]); ?>">
                <input type="hidden" name="oldPoster" value="<?php echo h($course["poster"]); ?>">
                <?php renderTrainingFormFields($course, $staffOptions); ?>
                <div class="page-form-actions">
                    <a href="course.php?view=<?php echo urlencode($course["courseID"]); ?>" class="cancel-btn">Cancel</a>
                    <button type="submit" class="primary-btn">Save Training</button>
                </div>
            </form>
        </section>
    <?php } elseif ($activeMode === "sessions") { ?>
        <?php $course = $activeCourse; $courseSessions = $sessionsByTraining[$course["courseID"]] ?? []; ?>
        <section class="panel page-panel session-page-panel">
            <div class="page-panel-header">
                <div>
                    <span>Manage Session</span>
                    <h2><?php echo h($course["courseName"]); ?></h2>
                    <p>Edit session date, time, trainers and QR expiry.</p>
                </div>
                <a class="secondary-action-btn" href="course.php?view=<?php echo urlencode($course["courseID"]); ?>">Cancel</a>
            </div>
            <div class="page-panel-body">
                <div class="session-manage-list">
                    <?php if (count($courseSessions) > 0) { ?>
                        <?php foreach ($courseSessions as $session) { ?>
                            <?php $sessionKey = modalKey($session["sessionID"]); ?>
                            <div class="session-manage-card">
                                <div class="session-view-box">
                                    <div class="session-manage-header">
                                        <div>
                                            <strong><?php echo h($session["sessionName"] ?: "Session"); ?></strong>
                                            <span><?php echo h(formatSessionTime($session["sessionDate"], $session["startTime"], $session["endTime"])); ?></span>
                                            <small><?php echo h($session["location"] ?: "No location added"); ?></small>
                                        </div>
                                        <div class="session-view-actions">
                                            <?php if (!empty($session["qrCode"])) { ?>
                                                <a href="<?php echo h($session["attendanceLink"] ?: $session["qrCode"]); ?>" target="_blank" class="session-qr-link">Open QR</a>
                                            <?php } ?>
                                            <button type="button" class="secondary-action-btn" onclick="toggleSessionEdit('sessionEdit_<?php echo h($sessionKey); ?>', this)">Edit Session</button>
                                        </div>
                                    </div>

                                    <div class="session-view-grid">
                                        <div class="detail-box"><span>Session Date</span><strong><?php echo h(date('d M Y', strtotime($session["sessionDate"]))); ?></strong></div>
                                        <div class="detail-box"><span>Start Time</span><strong><?php echo h(date('h:i A', strtotime($session["startTime"]))); ?></strong></div>
                                        <div class="detail-box"><span>End Time</span><strong><?php echo h(date('h:i A', strtotime($session["endTime"]))); ?></strong></div>
                                        <div class="detail-box"><span>QR Expiry</span><strong><?php echo !empty($session["expiryTime"]) ? h(date('d M Y, h:i A', strtotime($session["expiryTime"]))) : '-'; ?></strong></div>
                                        <div class="detail-box detail-full"><span>Trainer</span><strong><?php echo h($session["trainerNames"] ?: "No trainer assigned"); ?></strong></div>
                                    </div>
                                </div>

                                <div class="session-edit-area" id="sessionEdit_<?php echo h($sessionKey); ?>" hidden>
                                    <form method="POST" class="session-edit-form" id="updateSessionForm_<?php echo h($sessionKey); ?>">
                                        <input type="hidden" name="action" value="update_session">
                                        <input type="hidden" name="courseID" value="<?php echo h($course["courseID"]); ?>">
                                        <input type="hidden" name="sessionID" value="<?php echo h($session["sessionID"]); ?>">

                                        <div class="form-grid session-edit-grid">
                                            <div><label>Session Name</label><input type="text" name="sessionName" value="<?php echo h($session["sessionName"]); ?>"></div>
                                            <div><label>Session Date</label><input type="date" name="sessionDate" min="<?php echo date('Y-m-d'); ?>" value="<?php echo h($session["sessionDate"]); ?>" required></div>
                                            <div><label>Start Time</label><input type="time" name="startTime" value="<?php echo h(substr($session["startTime"], 0, 5)); ?>" required></div>
                                            <div><label>End Time</label><input type="time" name="endTime" value="<?php echo h(substr($session["endTime"], 0, 5)); ?>" required><small class="field-error" data-time-error></small></div>
                                            <div><label>Location</label><input type="text" name="location" value="<?php echo h($session["location"]); ?>"></div>
                                            <div><label>QR Expiry Date & Time</label><input type="datetime-local" name="qrExpiry" value="<?php echo h(htmlDatetimeLocal($session["expiryTime"])); ?>"><small class="field-error" data-qr-error></small></div>
                                            <div class="form-full"><label>Assigned Trainer</label><div class="checkbox-grid trainer-checkbox-grid"><?php renderTrainerCheckboxes($trainerOptions, $session["trainerPairs"] ?? ""); ?></div></div>
                                        </div>
                                    </form>

                                    <div class="session-card-actions-inline">
                                        <button type="submit" form="updateSessionForm_<?php echo h($sessionKey); ?>" class="primary-btn">Update Session</button>
                                        <form method="POST" class="session-delete-form delete-form" data-confirm="Delete this session?">
                                            <input type="hidden" name="action" value="delete_session">
                                            <input type="hidden" name="courseID" value="<?php echo h($course["courseID"]); ?>">
                                            <input type="hidden" name="sessionID" value="<?php echo h($session["sessionID"]); ?>">
                                            <button type="submit" class="danger-outline-btn">Delete Session</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="empty-state">No session added yet.</div>
                    <?php } ?>
                </div>

                <div class="session-add-form">
                    <div class="section-mini-header">
                        <h3>Add New Session</h3>
                        <p>Create another session for this training.</p>
                    </div>
                    <form method="POST" class="modal-form clean-session-form">
                        <input type="hidden" name="action" value="add_session">
                        <input type="hidden" name="courseID" value="<?php echo h($course["courseID"]); ?>">
                        <div class="form-grid session-grid">
                            <div><label>Session Name</label><input type="text" name="sessionName" placeholder="Day 1 / Morning Session"></div>
                            <div><label>Session Date</label><input type="date" name="sessionDate" min="<?php echo date('Y-m-d'); ?>" required></div>
                            <div><label>Start Time</label><input type="time" name="startTime" required></div>
                            <div><label>End Time</label><input type="time" name="endTime" required><small class="field-error" data-time-error></small></div>
                            <div><label>Location</label><input type="text" name="location" placeholder="Hall / Google Meet"></div>
                            <div><label>QR Expiry Date & Time</label><input type="datetime-local" name="qrExpiry" min="<?php echo h($nowLocal); ?>"><small class="field-error" data-qr-error></small></div>
                            <div class="form-full"><label>Assign Trainer</label><div class="checkbox-grid trainer-checkbox-grid"><?php foreach ($trainerOptions as $trainer) { ?><label class="checkbox-pill trainer-check-pill"><input type="checkbox" name="trainerIDs[]" value="<?php echo h($trainer["trainerID"]); ?>"><span><?php echo h($trainer["trainerName"]); ?></span></label><?php } ?></div></div>
                        </div>
                        <div class="page-form-actions"><button type="submit" class="primary-btn">Add Session</button></div>
                    </form>
                </div>
            </div>
        </section>
    <?php } else { ?>
        <section class="panel training-list-panel">
            <div class="panel-header course-panel-header">
                <div class="panel-title">
                    <div class="panel-title-icon"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4v15.5A2.5 2.5 0 0 0 6.5 22H20V6a2 2 0 0 0-2-2H4Z"/></svg></div>
                    <h2>All Trainings</h2>
                </div>
            </div>

            <div class="course-filter-bar">
                <form method="GET" class="filter-form course-filters">
                    <div class="filter-search">
                        <span class="filter-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></span>
                        <input type="text" name="search" placeholder="Search training..." value="<?php echo h($search); ?>">
                    </div>

                    <select name="category">
                        <option value="">All Category</option>
                        <?php foreach ($categoryOptions as $categoryOption) { ?>
                            <option value="<?php echo h($categoryOption); ?>" <?php echo selected($categoryOption, $categoryFilter); ?>><?php echo h($categoryOption); ?></option>
                        <?php } ?>
                    </select>

                    <select name="status">
                        <option value="">All Status</option>
                        <option value="upcoming" <?php echo selected("upcoming", $statusFilter); ?>>Upcoming</option>
                        <option value="ongoing" <?php echo selected("ongoing", $statusFilter); ?>>Ongoing</option>
                        <option value="completed" <?php echo selected("completed", $statusFilter); ?>>Completed</option>
                        <option value="cancelled" <?php echo selected("cancelled", $statusFilter); ?>>Cancelled</option>
                    </select>

                    <button type="submit" class="filter-submit-btn">Filter</button>
                    <a href="course.php" class="reset-filter">Reset</a>
                </form>
            </div>

            <div class="table-wrap">
                <table class="course-table compact-training-table">
                    <thead>
                        <tr>
                            <th>Training</th>
                            <th>Type</th>
                            <th>Mode</th>
                            <th>Trainer</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($courseRows) > 0) { ?>
                            <?php foreach ($courseRows as $course) { ?>
                                <tr>
                                    <td>
                                        <div class="course-name-cell compact-course-name-cell">
                                            <div class="course-thumb">
                                                <?php
                                                $poster = $course["poster"] ?? "";
                                                $posterExt = strtolower(pathinfo($poster, PATHINFO_EXTENSION));
                                                ?>
                                                <?php if (!empty($poster) && in_array($posterExt, ["jpg", "jpeg", "png"], true)) { ?>
                                                    <img src="<?php echo h($poster); ?>" alt="Training poster">
                                                <?php } else { ?>
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                                        <path d="M4 4v15.5A2.5 2.5 0 0 0 6.5 22H20V6a2 2 0 0 0-2-2H4Z"/>
                                                    </svg>
                                                <?php } ?>
                                            </div>
                                            <div class="training-title-only"><strong><?php echo h($course["courseName"]); ?></strong></div>
                                        </div>
                                    </td>
                                    <td><span class="category-badge <?php echo categoryClass($course["courseCategory"]); ?>"><?php echo h($course["courseType"]); ?></span></td>
                                    <td><?php echo h(ucfirst($course["mode"])); ?></td>
                                    <td><span class="trainer-name"><?php echo h($course["trainerNames"] ?: "Not assigned"); ?></span></td>
                                    <td><div class="course-rating-cell"><strong><?php echo h(formatTrainingRating($course["displayTrainingRating"])); ?></strong><?php echo renderTrainingStars($course["displayTrainingRating"]); ?></div></td>
                                    <td><span class="status-badge <?php echo h(statusClass($course["status"])); ?>"><?php echo h(trainingStatusLabel($course["status"])); ?></span></td>
                                    <td>
                                        <div class="action-group">
                                            <a class="action-btn" href="course.php?view=<?php echo urlencode($course["courseID"]); ?>" title="View"><svg viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                            <form method="POST" class="delete-form" data-confirm="Are you sure you want to delete this training?">
                                                <input type="hidden" name="action" value="delete_course">
                                                <input type="hidden" name="courseID" value="<?php echo h($course["courseID"]); ?>">
                                                <button type="submit" class="action-btn danger-action" title="Delete"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="7"><div class="empty-state">No training records found.</div></td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="table-footer"><span>Showing <?php echo count($courseRows); ?> training records</span></div>
        </section>
    <?php } ?>
</div>

<div class="modal" id="addTrainingModal">
    <div class="modal-box form-modal course-form-modal">
        <button class="close-btn" onclick="closeModal('addTrainingModal')">×</button>
        <div class="form-modal-header">
            <span>New Training</span>
            <h2>Add Training</h2>
            <p>Create training details and add one or more sessions.</p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="modal-form course-rule-form">
            <input type="hidden" name="action" value="add_course">
            <?php renderTrainingFormFields(null, $staffOptions); ?>

            <div class="course-form-section">
                <div class="course-form-section-title"><h3>Add One or More Session</h3></div>
                <div id="addTrainingSessionList" class="session-repeater">
                    <div class="session-block" data-index="0">
                        <div class="session-block-header">
                            <strong>Session 1</strong>
                            <button type="button" class="session-remove-btn" onclick="removeTrainingSessionRow(this)" aria-label="Remove session">×</button>
                        </div>
                        <div class="form-grid session-grid">
                            <input type="hidden" name="sessionID[]" value="">
                            <div><label>Session Name</label><input type="text" name="sessionName[]" placeholder="Morning Session / Day 1"></div>
                            <div><label>Session Date</label><input type="date" name="sessionDate[]" min="<?php echo h($today); ?>" required></div>
                            <div><label>Start Time</label><input type="time" name="startTime[]" required></div>
                            <div><label>End Time</label><input type="time" name="endTime[]" required><small class="field-error" data-time-error></small></div>
                            <div><label>Location</label><input type="text" name="location[]" placeholder="Hall / Google Meet"></div>
                            <div><label>QR Expiry Date & Time</label><input type="datetime-local" name="qrExpiry[]" min="<?php echo h($nowLocal); ?>"><small class="field-error" data-qr-error></small></div>
                            <div class="form-full trainer-picker" data-index="0">
                                <label>Assign Trainer</label>
                                <div class="trainer-picker-row">
                                    <select class="trainer-picker-select">
                                        <option value="">Choose trainer</option>
                                        <?php foreach ($trainerOptions as $trainer) { ?>
                                            <option value="<?php echo h($trainer["trainerID"]); ?>"><?php echo h($trainer["trainerName"]); ?></option>
                                        <?php } ?>
                                    </select>
                                    <button type="button" class="trainer-assign-btn" onclick="addTrainerToSession(this)">Assign</button>
                                    <a href="trainer.php" class="trainer-add-page-btn"><span class="mini-plus">+</span> Add Trainer</a>
                                </div>
                                <div class="trainer-chip-list"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="add-session-btn" onclick="addTrainingSessionRow()"><span class="btn-plus-icon">+</span><span>Add Date/Time Session</span></button>
            </div>

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeModal('addTrainingModal')">Cancel</button>
                <button type="submit" class="primary-btn">Save Training</button>
            </div>
        </form>
    </div>
</div>

<div class="modal attendance-remark-modal" id="attendanceRemarkModal" aria-hidden="true">
    <div class="modal-box attendance-remark-modal-box" role="dialog" aria-modal="true" aria-labelledby="attendanceRemarkTitle">
        <button type="button" class="close-btn attendance-remark-close" onclick="closeAttendanceRemarkModal()" aria-label="Close remarks popup">×</button>
        <div class="attendance-remark-header">
            <span class="attendance-remark-kicker">Attendance Remark</span>
            <h3 id="attendanceRemarkTitle">Participant</h3>
            <p>View or edit the remark for this attendance record.</p>
        </div>
        <form method="POST" class="attendance-remark-form" id="attendanceRemarkForm">
            <input type="hidden" name="action" value="save_attendance_remark">
            <input type="hidden" name="courseID" id="remarkCourseID" value="">
            <input type="hidden" name="participantType" id="remarkParticipantType" value="">
            <input type="hidden" name="attendanceID" id="remarkAttendanceID" value="">

            <div class="attendance-remark-status-row">
                <span>Attendance Status</span>
                <em class="attendance-pill" id="remarkAttendanceStatus">Not Approved</em>
            </div>

            <div class="attendance-remark-field">
                <label for="attendanceRemarkInput">Remarks</label>
                <textarea id="attendanceRemarkInput" name="remarks" rows="4" maxlength="500" placeholder="Add a short attendance remark..."></textarea>
                <div class="attendance-remark-meta">
                    <span>Optional. You can return here anytime to view or edit it.</span>
                    <strong id="attendanceRemarkCounter">0 / 500</strong>
                </div>
            </div>

            <div class="attendance-remark-actions">
                <button type="button" class="cancel-btn" onclick="closeAttendanceRemarkModal()">Cancel</button>
                <button type="submit" class="attendance-remark-save-btn">Save Remark</button>
            </div>
        </form>
    </div>
</div>

<div class="confirm-popup-backdrop" id="deleteConfirmPopup" hidden>
    <div class="confirm-popup">
        <div class="confirm-icon">!</div>
        <h3>Are you sure?</h3>
        <p id="deleteConfirmMessage">This action cannot be undone.</p>
        <div class="confirm-actions">
            <button type="button" class="cancel-btn" id="deleteCancelBtn">Cancel</button>
            <button type="button" class="danger-outline-btn" id="deleteSureBtn">Sure</button>
        </div>
    </div>
</div>

<script>
const trainerOptions = <?php echo $trainerOptionsJson ?: '[]'; ?>;
let sessionCounter = 1;

function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) { modal.style.display = 'flex'; window.trainhubSyncModalState?.(); }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) { modal.style.display = 'none'; window.trainhubSyncModalState?.(); }
}

function closeFlashPopup() {
    const popup = document.getElementById('flashPopup');
    if (popup) popup.style.display = 'none';
}

function updateAttendanceRemarkCounter() {
    const input = document.getElementById('attendanceRemarkInput');
    const counter = document.getElementById('attendanceRemarkCounter');
    if (!input || !counter) return;
    counter.textContent = `${input.value.length} / 500`;
}

function openAttendanceRemarkModal(button) {
    const modal = document.getElementById('attendanceRemarkModal');
    const input = document.getElementById('attendanceRemarkInput');
    if (!modal || !button || !input) return;

    document.getElementById('remarkCourseID').value = button.dataset.courseId || '';
    document.getElementById('remarkParticipantType').value = button.dataset.participantType || '';
    document.getElementById('remarkAttendanceID').value = button.dataset.attendanceId || '';
    document.getElementById('attendanceRemarkTitle').textContent = button.dataset.participantName || 'Participant';
    input.value = button.dataset.remarks || '';

    const status = button.dataset.attendanceStatus || 'Not Approved';
    const statusPill = document.getElementById('remarkAttendanceStatus');
    if (statusPill) {
        statusPill.textContent = status;
        statusPill.className = 'attendance-pill ' + (status.toLowerCase() === 'approved' ? 'approved' : 'not-approved');
    }

    updateAttendanceRemarkCounter();
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    window.trainhubSyncModalState?.();
    window.setTimeout(() => input.focus(), 50);
}

function closeAttendanceRemarkModal() {
    const modal = document.getElementById('attendanceRemarkModal');
    if (!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    window.trainhubSyncModalState?.();
}

function showClientPopup(message, type = 'error') {
    let popup = document.getElementById('clientValidationPopup');
    if (!popup) {
        popup = document.createElement('div');
        popup.id = 'clientValidationPopup';
        popup.className = 'flash-popup-backdrop';
        popup.innerHTML = `
            <div class="flash-popup flash-error">
                <div class="flash-icon">!</div>
                <h3>Notice</h3>
                <p></p>
                <button type="button">OK</button>
            </div>`;
        document.body.appendChild(popup);
        popup.querySelector('button').addEventListener('click', function() {
            popup.style.display = 'none';
        });
    }
    popup.querySelector('p').textContent = message;
    popup.style.display = 'flex';
}

function setInlineError(input, message) {
    if (!input) return;
    let error = input.parentElement.querySelector('.field-error');
    if (!error) {
        error = document.createElement('small');
        error.className = 'field-error';
        input.insertAdjacentElement('afterend', error);
    }
    error.textContent = message || '';
    input.classList.toggle('is-invalid', Boolean(message));
    input.setCustomValidity(message || '');
}

function buildTrainerOptions() {
    return trainerOptions.map(function(trainer) {
        return `<option value="${escapeHtml(trainer.trainerID)}">${escapeHtml(trainer.trainerName)}</option>`;
    }).join('');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function addTrainingSessionRow() {
    const list = document.getElementById('addTrainingSessionList');
    const index = sessionCounter++;
    const div = document.createElement('div');
    div.className = 'session-block';
    div.dataset.index = index;
    div.innerHTML = `
        <div class="session-block-header">
            <strong>Session ${index + 1}</strong>
            <button type="button" class="session-remove-btn" onclick="removeTrainingSessionRow(this)" aria-label="Remove session">×</button>
        </div>
        <div class="form-grid session-grid">
            <input type="hidden" name="sessionID[]" value="">
            <div><label>Session Name</label><input type="text" name="sessionName[]" placeholder="Morning Session / Day 1"></div>
            <div><label>Session Date</label><input type="date" name="sessionDate[]" min="<?php echo h($today); ?>" required></div>
            <div><label>Start Time</label><input type="time" name="startTime[]" required></div>
            <div><label>End Time</label><input type="time" name="endTime[]" required><small class="field-error" data-time-error></small></div>
            <div><label>Location</label><input type="text" name="location[]" placeholder="Hall / Google Meet"></div>
            <div><label>QR Expiry Date & Time</label><input type="datetime-local" name="qrExpiry[]" min="<?php echo h($nowLocal); ?>"><small class="field-error" data-qr-error></small></div>
            <div class="form-full trainer-picker" data-index="${index}">
                <label>Assign Trainer</label>
                <div class="trainer-picker-row">
                    <select class="trainer-picker-select"><option value="">Choose trainer</option>${buildTrainerOptions()}</select>
                    <button type="button" class="trainer-assign-btn" onclick="addTrainerToSession(this)">Assign</button>
                    <a href="trainer.php" class="trainer-add-page-btn"><span class="mini-plus">+</span> Add Trainer</a>
                </div>
                <div class="trainer-chip-list"></div>
            </div>
        </div>`;
    list.appendChild(div);
    refreshSessionNumbers();
    setupDynamicValidation(div);
}

function removeTrainingSessionRow(button) {
    const blocks = document.querySelectorAll('#addTrainingSessionList .session-block');
    if (blocks.length <= 1) {
        showClientPopup('At least one session is required.', 'error');
        return;
    }
    button.closest('.session-block').remove();
    refreshSessionNumbers();
    refreshAllCloseDateLimits();
}

function refreshSessionNumbers() {
    document.querySelectorAll('#addTrainingSessionList .session-block').forEach(function(block, index) {
        const strong = block.querySelector('.session-block-header strong');
        const picker = block.querySelector('.trainer-picker');
        if (strong) strong.textContent = `Session ${index + 1}`;
        if (picker) picker.dataset.index = index;
        block.querySelectorAll('input[data-trainer-hidden="1"]').forEach(function(input) {
            input.name = `trainerIDs[${index}][]`;
        });
    });
}

function addTrainerToSession(button) {
    const picker = button.closest('.trainer-picker');
    const select = picker.querySelector('.trainer-picker-select');
    const list = picker.querySelector('.trainer-chip-list');
    const index = picker.dataset.index || 0;
    const value = select.value;
    const text = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : '';

    if (!value) return;
    if (list.querySelector(`input[value="${CSS.escape(value)}"]`)) {
        select.value = '';
        return;
    }

    const chip = document.createElement('span');
    chip.className = 'trainer-select-chip';
    chip.innerHTML = `${escapeHtml(text)} <button type="button" onclick="this.closest('.trainer-select-chip').remove()">×</button><input type="hidden" data-trainer-hidden="1" name="trainerIDs[${index}][]" value="${escapeHtml(value)}">`;
    list.appendChild(chip);
    select.value = '';
}

function setupTargetAudienceFields(root = document) {
    root.querySelectorAll('.course-rule-form').forEach(function(form) {
        const targetCheckboxes = form.querySelectorAll('input[name="targetAudience[]"]');
        const otherField = form.querySelector('.other-target-field');
        const typeInput = form.querySelector('.type-select');
        const typeHidden = form.querySelector('.type-hidden');

        function refresh() {
            const checkedValues = Array.from(targetCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
            if (otherField) otherField.style.display = checkedValues.includes('other') ? 'block' : 'none';
            const type = checkedValues.length === 1 && checkedValues[0] === 'new_teacher' ? 'LMS' : 'Simple';
            if (typeInput) typeInput.value = type;
            if (typeHidden) typeHidden.value = type;
        }

        targetCheckboxes.forEach(cb => cb.addEventListener('change', refresh));
        refresh();
    });
}

function setupModeFields(root = document) {
    root.querySelectorAll('.course-rule-form').forEach(function(form) {
        const modeSelect = form.querySelector('.mode-select');
        const onlineField = form.querySelector('.online-link-field');
        if (!modeSelect || !onlineField) return;
        function refresh() {
            onlineField.style.display = ['online', 'hybrid'].includes(modeSelect.value) ? 'block' : 'none';
        }
        modeSelect.addEventListener('change', refresh);
        refresh();
    });
}

function setupCapacitySync(root = document) {
    root.querySelectorAll('.capacity-field').forEach(function(field) {
        const visible = field.querySelector('.capacity-input');
        const hidden = field.querySelector('.capacity-hidden');
        if (!visible || !hidden) return;
        visible.addEventListener('input', function() {
            visible.value = visible.value.replace(/[^0-9]/g, '');
            hidden.value = visible.value;
        });
        hidden.value = visible.value;
    });
}

function setupDynamicValidation(root = document) {
    root.querySelectorAll('input[type="date"][name^="sessionDate"]').forEach(function(input) {
        input.min = '<?php echo h($today); ?>';
        input.addEventListener('change', function() {
            refreshAllCloseDateLimits();
            validateQRExpiryInContainer(input.closest('.form-grid') || input.closest('form'));
        });
    });

    root.querySelectorAll('input[type="time"][name^="startTime"]').forEach(function(startInput) {
        const container = startInput.closest('.form-grid') || startInput.closest('form');
        const endInput = container ? container.querySelector('input[type="time"][name^="endTime"]') : null;
        if (!endInput) return;

        function validateTimes() {
            if (startInput.value) endInput.min = startInput.value;
            if (startInput.value && endInput.value && endInput.value <= startInput.value) {
                setInlineError(endInput, 'End time must be after start time.');
                return false;
            }
            setInlineError(endInput, '');
            validateQRExpiryInContainer(container);
            return true;
        }

        startInput.addEventListener('change', validateTimes);
        startInput.addEventListener('input', validateTimes);
        endInput.addEventListener('change', validateTimes);
        endInput.addEventListener('input', validateTimes);
        validateTimes();
    });

    root.querySelectorAll('input[name^="qrExpiry"]').forEach(function(qrInput) {
        qrInput.addEventListener('change', function() {
            validateQRExpiryInContainer(qrInput.closest('.form-grid') || qrInput.closest('form'));
        });
        qrInput.addEventListener('input', function() {
            validateQRExpiryInContainer(qrInput.closest('.form-grid') || qrInput.closest('form'));
        });
    });
}

function validateQRExpiryInContainer(container) {
    if (!container) return true;
    const sessionDate = container.querySelector('input[name^="sessionDate"]');
    const startTime = container.querySelector('input[name^="startTime"]');
    const qrInput = container.querySelector('input[name^="qrExpiry"]');
    if (!qrInput) return true;

    if (!qrInput.value) {
        setInlineError(qrInput, '');
        return true;
    }

    if (sessionDate && startTime && sessionDate.value && startTime.value) {
        const sessionStart = `${sessionDate.value}T${startTime.value}`;
        qrInput.min = sessionStart;
        if (qrInput.value < sessionStart) {
            setInlineError(qrInput, 'QR expiry must be after the session start date and time.');
            return false;
        }
    }

    setInlineError(qrInput, '');
    return true;
}

function refreshAllCloseDateLimits() {
    document.querySelectorAll('.course-rule-form').forEach(function(form) {
        const closeInput = form.querySelector('.close-date-input');
        if (!closeInput) return;

        const dates = Array.from(form.querySelectorAll('input[name="sessionDate[]"]')).map(input => input.value).filter(Boolean).sort();
        if (dates.length > 0) {
            closeInput.max = dates[0];
        }

        if (closeInput.value && closeInput.max && closeInput.value > closeInput.max) {
            setInlineError(closeInput, 'Registration closing date must be before or on the first session date.');
        } else {
            setInlineError(closeInput, '');
        }
    });
}

function setupCloseDateValidation() {
    document.querySelectorAll('.course-rule-form').forEach(function(form) {
        const closeInput = form.querySelector('.close-date-input');
        if (!closeInput) return;
        closeInput.addEventListener('change', refreshAllCloseDateLimits);
        closeInput.addEventListener('input', refreshAllCloseDateLimits);
    });
    document.addEventListener('change', function(event) {
        if (event.target.matches('input[name="sessionDate[]"]')) refreshAllCloseDateLimits();
    });
    refreshAllCloseDateLimits();
}

function setupPriceValidation() {
    document.querySelectorAll('[data-price-input], input[name="price"]').forEach(function(input) {
        function validatePrice() {
            if (input.value !== '' && Number(input.value) < 0) {
                setInlineError(input, 'No negative number.');
                return false;
            }
            setInlineError(input, '');
            return true;
        }
        input.addEventListener('input', validatePrice);
        input.addEventListener('change', validatePrice);
        validatePrice();
    });
}

function setupOrganiserFields() {
    document.querySelectorAll('.organiser-field').forEach(function(field) {
        const otherCheckbox = field.querySelector('input[value="Other"]');
        const otherInput = field.querySelector('.other-organiser-input');
        if (!otherCheckbox || !otherInput) return;
        function refresh() {
            otherInput.style.display = otherCheckbox.checked ? 'block' : 'none';
            if (!otherCheckbox.checked) {
                otherInput.value = '';
                setInlineError(otherInput, '');
            }
        }
        otherCheckbox.addEventListener('change', refresh);
        otherInput.addEventListener('input', function() {
            if (otherCheckbox.checked && otherInput.value.trim() === '') {
                setInlineError(otherInput, 'Please fill in the other organiser name.');
            } else {
                setInlineError(otherInput, '');
            }
        });
        refresh();
    });
}

function setupStaffPICSearch() {
    document.querySelectorAll('.staff-pic-field').forEach(function(field) {
        const input = field.querySelector('.staff-pic-search');
        const rows = field.querySelectorAll('.staff-check-row');
        if (!input) return;
        input.addEventListener('input', function() {
            const keyword = input.value.trim().toLowerCase();
            rows.forEach(function(row) {
                const text = row.dataset.staffSearch || row.textContent.toLowerCase();
                row.hidden = keyword !== '' && !text.includes(keyword);
            });
        });
    });
}

function setupFormValidation() {
    document.querySelectorAll('.course-rule-form, .session-edit-form, .clean-session-form').forEach(function(form) {
        form.addEventListener('submit', function(event) {
            let ok = true;
            form.querySelectorAll('input[name^="endTime"]').forEach(function(endInput) {
                const container = endInput.closest('.form-grid') || form;
                const startInput = container.querySelector('input[name^="startTime"]');
                if (startInput && startInput.value && endInput.value && endInput.value <= startInput.value) {
                    setInlineError(endInput, 'End time must be after start time.');
                    ok = false;
                }
            });
            form.querySelectorAll('input[name^="qrExpiry"]').forEach(function(qrInput) {
                if (!validateQRExpiryInContainer(qrInput.closest('.form-grid') || form)) ok = false;
            });
            form.querySelectorAll('input[name="price"]').forEach(function(priceInput) {
                if (priceInput.value !== '' && Number(priceInput.value) < 0) {
                    setInlineError(priceInput, 'No negative number.');
                    ok = false;
                }
            });
            form.querySelectorAll('.organiser-field').forEach(function(field) {
                const otherCheckbox = field.querySelector('input[value="Other"]');
                const otherInput = field.querySelector('.other-organiser-input');
                if (otherCheckbox && otherInput && otherCheckbox.checked && otherInput.value.trim() === '') {
                    setInlineError(otherInput, 'Please fill in the other organiser name.');
                    ok = false;
                }
            });
            refreshAllCloseDateLimits();
            form.querySelectorAll('.is-invalid').forEach(function() { ok = false; });
            if (!ok) {
                event.preventDefault();
                showClientPopup('Please fix the red error message before saving.', 'error');
            }
        });
    });
}

function setupParticipantFilters() {
    document.querySelectorAll('[data-participant-filter-root]').forEach(function(root) {
        const typeFilter = root.querySelector('[data-participant-type-filter]');
        const attendanceFilter = root.querySelector('[data-participant-attendance-filter]');
        const applyBtn = root.querySelector('[data-participant-filter-apply]');
        const resetBtn = root.querySelector('[data-participant-filter-reset]');

        function applyFilters() {
            const typeValue = typeFilter ? typeFilter.value : 'all';
            const attendanceValue = attendanceFilter ? attendanceFilter.value : 'all';

            root.querySelectorAll('[data-session-pane]').forEach(function(pane) {
                let visible = 0;
                pane.querySelectorAll('[data-participant-item]').forEach(function(item) {
                    const typeMatch = typeValue === 'all' || item.dataset.participantType === typeValue;
                    let attendanceMatch = true;
                    if (attendanceValue === 'scanned') attendanceMatch = item.dataset.scan === 'scanned';
                    else if (attendanceValue === 'not_scanned') attendanceMatch = item.dataset.scan === 'not_scanned';
                    else if (attendanceValue !== 'all') attendanceMatch = item.dataset.attendance === attendanceValue;
                    const show = typeMatch && attendanceMatch;
                    item.hidden = !show;
                    if (show) visible++;
                });
                const empty = pane.querySelector('[data-session-filter-empty]');
                if (empty) empty.hidden = visible !== 0;
            });
        }

        if (applyBtn) applyBtn.addEventListener('click', applyFilters);
        if (resetBtn) resetBtn.addEventListener('click', function() {
            if (typeFilter) typeFilter.value = 'all';
            if (attendanceFilter) attendanceFilter.value = 'all';
            applyFilters();
        });
        applyFilters();
    });
}

function setupSessionTabs() {
    document.querySelectorAll('[data-session-tab]').forEach(function(button) {
        button.addEventListener('click', function() {
            const target = button.dataset.sessionTab;
            const wrapper = button.closest('.participant-section');
            wrapper.querySelectorAll('[data-session-tab]').forEach(btn => btn.classList.remove('active'));
            wrapper.querySelectorAll('[data-session-pane]').forEach(pane => pane.classList.remove('active'));
            button.classList.add('active');
            const pane = document.getElementById(target);
            if (pane) pane.classList.add('active');
        });
    });
}

function toggleSessionEdit(id, button) {
    const area = document.getElementById(id);
    if (!area) return;
    const willOpen = area.hidden;
    area.hidden = !willOpen;
    if (button) button.textContent = willOpen ? 'Close Edit' : 'Edit Session';
}

function setupDeletePopups() {
    const popup = document.getElementById('deleteConfirmPopup');
    const messageBox = document.getElementById('deleteConfirmMessage');
    const sureBtn = document.getElementById('deleteSureBtn');
    const cancelBtn = document.getElementById('deleteCancelBtn');
    let pendingForm = null;

    document.querySelectorAll('form.delete-form').forEach(function(form) {
        if (form.dataset.deleteReady === '1') return;
        form.dataset.deleteReady = '1';
        form.addEventListener('submit', function(event) {
            if (form.dataset.allowSubmit === '1') return;
            event.preventDefault();
            pendingForm = form;
            if (messageBox) messageBox.textContent = form.dataset.confirm || 'Are you sure you want to delete this record?';
            if (popup) popup.hidden = false;
        });
    });

    if (sureBtn && !sureBtn.dataset.ready) {
        sureBtn.dataset.ready = '1';
        sureBtn.addEventListener('click', function() {
            if (!pendingForm) return;
            pendingForm.dataset.allowSubmit = '1';
            pendingForm.submit();
        });
    }

    if (cancelBtn && !cancelBtn.dataset.ready) {
        cancelBtn.dataset.ready = '1';
        cancelBtn.addEventListener('click', function() {
            pendingForm = null;
            if (popup) popup.hidden = true;
        });
    }

    if (popup && !popup.dataset.ready) {
        popup.dataset.ready = '1';
        popup.addEventListener('click', function(event) {
            if (event.target === popup) {
                pendingForm = null;
                popup.hidden = true;
            }
        });
    }
}

window.addEventListener('click', function(event) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        if (event.target === modal) modal.style.display = 'none';
    });
});

document.addEventListener('DOMContentLoaded', function() {
    setupTargetAudienceFields();
    setupModeFields();
    setupCapacitySync();
    setupDynamicValidation();
    setupCloseDateValidation();
    setupPriceValidation();
    setupOrganiserFields();
    setupStaffPICSearch();
    setupFormValidation();
    setupParticipantFilters();
    setupSessionTabs();
    setupDeletePopups();

    const remarkInput = document.getElementById('attendanceRemarkInput');
    if (remarkInput) remarkInput.addEventListener('input', updateAttendanceRemarkCounter);

    const remarkModal = document.getElementById('attendanceRemarkModal');
    if (remarkModal) {
        remarkModal.addEventListener('click', function(event) {
            if (event.target === remarkModal) closeAttendanceRemarkModal();
        });
    }
});
</script>

</body>
</html>
