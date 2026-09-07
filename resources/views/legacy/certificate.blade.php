<!DOCTYPE html>
<html>
<head>
    <title>Certificate | TrainHub Al Amin</title>
    <!-- Try common CSS locations. Last matching file wins. -->



    <link rel="stylesheet" href="assets/css/certificate.css?v=<?php echo file_exists(public_path('assets/css/certificate.css')) ? filemtime(public_path('assets/css/certificate.css')) : time(); ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo file_exists(public_path('assets/css/style.css')) ? filemtime(public_path('assets/css/style.css')) : time(); ?>">
</head>
<body class="trainhub-app page-certificate">

@include('partials.topbar')

<div id="generationOverlay" class="generation-overlay" aria-hidden="true">
    <div class="generation-box simple-generation-box">
        <h3>Generating certificate</h3>
        <p id="generationStatus">Please wait while the certificate is being generated.</p>

        <div class="generation-progress-row">
            <div class="generation-progress-track">
                <div id="generationProgressFill" class="generation-progress-fill"></div>
            </div>
            <strong id="generationPercent">0%</strong>
        </div>
    </div>
</div>

<div id="appModalOverlay" class="app-modal-overlay" aria-hidden="true">
    <div class="app-modal-box" role="dialog" aria-modal="true" aria-labelledby="appModalTitle">
        <div class="app-modal-icon" id="appModalIcon">!</div>
        <h3 id="appModalTitle">Please confirm</h3>
        <p id="appModalMessage">Are you sure?</p>
        <div class="app-modal-actions" id="appModalActions">
            <button type="button" class="secondary-btn" id="appModalCancel">Cancel</button>
            <button type="button" class="primary-btn" id="appModalConfirm">Yes, continue</button>
        </div>
    </div>
</div>

<div id="certificateListModal" class="certificate-list-modal-overlay" aria-hidden="true">
    <div class="certificate-list-modal-box" role="dialog" aria-modal="true" aria-labelledby="certificateListModalTitle">
        <div class="certificate-list-modal-head">
            <div class="course-modal-avatar" id="certificateListModalAvatar">C</div>
            <div>
                <span>Generated Certificates</span>
                <h3 id="certificateListModalTitle">Course certificates</h3>
            </div>
            <button type="button" class="certificate-list-modal-close" id="certificateListModalClose" aria-label="Close certificate list">&times;</button>
        </div>
        <div id="certificateListModalBody" class="certificate-list-modal-body"></div>
    </div>
</div>

<div class="cert-page">
    <div class="cert-header">
        <div>
            <h1>Certificate Management</h1>
            <p>Upload template, drag certificate fields and generate certificates for eligible participants.</p>
        </div>
    </div>

    <div class="cert-workspace-tabs" role="tablist" aria-label="Certificate workspace">
        <button type="button" class="cert-workspace-tab <?php echo $activeTab === 'generate' ? 'active' : ''; ?>" data-cert-tab="generate">Generate Certificate</button>
        <button type="button" class="cert-workspace-tab <?php echo $activeTab === 'history' ? 'active' : ''; ?>" data-cert-tab="history">Generated Certificate List</button>
    </div>

    <div class="cert-tab-panel <?php echo $activeTab === 'generate' ? 'active' : ''; ?>" id="certTabGenerate" data-cert-tab-panel="generate">
    <div class="cert-grid">
        <section class="cert-panel">
            <div class="panel-title">
                <h2>Upload Template</h2>
                <span>certificate_template</span>
            </div>

            <form method="POST" enctype="multipart/form-data" class="cert-form confirm-form" data-confirm="Upload this certificate template?">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <label>Template Name</label>
                <input type="text" name="templateName" placeholder="Example: Official Participation Template">

                <label>Template Image</label>
                <input type="file" name="templateFile" accept=".png,.jpg,.jpeg">

                <button type="submit" name="upload_template" class="primary-btn">Upload Template</button>
            </form>
        </section>

        <section class="cert-panel">
            <div class="panel-title">
                <h2>Select Course Details</h2>
                <span>course + session + trainer</span>
            </div>

            <form method="GET" class="cert-form">
                <input type="hidden" name="tab" value="generate">
                <label>Course</label>
                <select name="courseID" onchange="this.form.submit()">
                    <option value="">Select course</option>
                    <?php while ($courseRow = mysqli_fetch_assoc($courses)) { ?>
                        <option value="<?php echo e($courseRow["courseID"]); ?>" <?php echo $selectedCourseID === $courseRow["courseID"] ? "selected" : ""; ?>>
                            <?php echo e($courseRow["courseName"]); ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Session</label>
                <select name="sessionID" onchange="this.form.submit()">
                    <option value="">Select session</option>
                    <?php foreach ($sessions as $sessionRow) { ?>
                        <option value="<?php echo e($sessionRow["sessionID"]); ?>" <?php echo $selectedSessionID === $sessionRow["sessionID"] ? "selected" : ""; ?>>
                            <?php echo e($sessionRow["sessionName"] ?: $sessionRow["sessionID"]); ?>
                            - <?php echo e(date("d M Y", strtotime($sessionRow["sessionDate"]))); ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Trainer</label>
                <select name="trainerID">
                    <option value="">Select trainer</option>
                    <?php foreach ($trainers as $trainerRow) { ?>
                        <option value="<?php echo e($trainerRow["trainerID"]); ?>" <?php echo $selectedTrainerID === $trainerRow["trainerID"] ? "selected" : ""; ?>>
                            <?php echo e($trainerRow["trainerName"]); ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Template</label>
                <select name="templateID" onchange="this.form.submit()">
                    <option value="">Select template</option>
                    <?php
                    mysqli_data_seek($templates, 0);
                    while ($templateRow = mysqli_fetch_assoc($templates)) { ?>
                        <option value="<?php echo e($templateRow["templateID"]); ?>" <?php echo $selectedTemplateID === $templateRow["templateID"] ? "selected" : ""; ?>>
                            <?php echo e($templateRow["templateName"]); ?>
                        </option>
                    <?php } ?>
                </select>

                <button type="submit" class="secondary-btn">Apply Selection</button>
            </form>
        </section>
    </div>

    <section class="cert-panel full-panel">
        <div class="panel-title">
            <div>
                <h2>Choose Certificate Content & Positions</h2>
            </div>
            <span>certificate_position</span>
        </div>

        <?php if ($selectedTemplate) { ?>
            <form method="POST" id="positionForm" class="confirm-form" data-confirm="Save the selected certificate content and positions?">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="templateID" value="<?php echo e($selectedTemplateID); ?>">

                <div class="field-choice-section">
                    <div class="field-choice-header">
                        <div>
                            <h3>Certificate Fields</h3>
                        </div>
                        <div class="field-choice-actions">
                            <button type="button" class="mini-secondary-btn" id="enableAllFields">Select All</button>
                            <button type="button" class="mini-secondary-btn" id="clearOptionalFields">Clear All</button>
                        </div>
                    </div>

                    <div class="field-choice-grid">
                        <?php foreach ($fieldDefinitions as $fieldName => $definition) { ?>
                            <?php $isEnabled = in_array($fieldName, $enabledFieldNames, true); ?>
                            <label class="field-choice-card <?php echo $isEnabled ? 'enabled' : ''; ?>" data-field-choice="<?php echo e($fieldName); ?>">
                                <input
                                    type="checkbox"
                                    name="enabledField[]"
                                    value="<?php echo e($fieldName); ?>"
                                    class="field-enable-toggle"
                                    data-field="<?php echo e($fieldName); ?>"
                                    <?php echo $isEnabled ? 'checked' : ''; ?>
                                >
                                <span>
                                    <strong><?php echo e($definition["label"]); ?></strong>
                                </span>
                            </label>
                        <?php } ?>
                    </div>
                </div>

                <div class="editor-layout">
                    <div class="template-editor" id="templateEditor">
                        <img src="<?php echo e($selectedTemplate["templateFile"]); ?>" alt="Certificate Template" id="templateImage">

                        <?php foreach ($fieldDefinitions as $fieldName => $definition) {
                            $pos = $positions[$fieldName] ?? null;
                            $x = $pos ? (float)$pos["positionX"] : (float)$definition["x"];
                            $y = $pos ? (float)$pos["positionY"] : (float)$definition["y"];
                            $size = $pos ? (int)$pos["fontSize"] : (int)$definition["size"];
                            $color = $pos ? $pos["fontColor"] : $definition["color"];
                            $family = $pos ? ($pos["fontFamily"] ?: "Arial") : "Arial";
                            $isEnabled = in_array($fieldName, $enabledFieldNames, true);
                            $sizeStyle = $definition["type"] === "image"
                                ? "width: " . e($size) . "px;"
                                : "font-size: " . e($size) . "px;";
                        ?>
                            <div
                                class="drag-field <?php echo $definition["type"] === "image" ? 'image-drag-field' : ''; ?> <?php echo $isEnabled ? '' : 'field-disabled'; ?>"
                                data-field="<?php echo e($fieldName); ?>"
                                data-field-type="<?php echo e($definition["type"]); ?>"
                                style="left: <?php echo e($x); ?>%; top: <?php echo e($y); ?>%; <?php echo $sizeStyle; ?> color: <?php echo e($color); ?>; font-family: <?php echo e($family); ?>;"
                            >
                                <?php echo e($definition["sample"]); ?>
                            </div>

                            <input type="hidden" name="positionX[<?php echo e($fieldName); ?>]" id="<?php echo e($fieldName); ?>_x" value="<?php echo e($x); ?>">
                            <input type="hidden" name="positionY[<?php echo e($fieldName); ?>]" id="<?php echo e($fieldName); ?>_y" value="<?php echo e($y); ?>">
                        <?php } ?>
                    </div>

                    <div class="field-settings">
                        <h3>Field Style</h3>

                        <?php foreach ($fieldDefinitions as $fieldName => $definition) {
                            $pos = $positions[$fieldName] ?? null;
                            $size = $pos ? (int)$pos["fontSize"] : (int)$definition["size"];
                            $color = $pos ? $pos["fontColor"] : $definition["color"];
                            $family = $pos ? ($pos["fontFamily"] ?: "Arial") : "Arial";
                            $isEnabled = in_array($fieldName, $enabledFieldNames, true);
                            $isImage = $definition["type"] === "image";
                        ?>
                            <div class="setting-row <?php echo $isImage ? 'image-setting-row' : ''; ?> <?php echo $isEnabled ? '' : 'field-setting-disabled'; ?>" data-field-setting="<?php echo e($fieldName); ?>">
                                <div class="setting-name">
                                    <strong><?php echo e($definition["label"]); ?></strong>
                                    <small><?php echo $isImage ? 'Image width' : 'Text style'; ?></small>
                                </div>
                                <input
                                    type="number"
                                    name="fontSize[<?php echo e($fieldName); ?>]"
                                    value="<?php echo e($size); ?>"
                                    min="<?php echo $isImage ? '40' : '8'; ?>"
                                    max="<?php echo $isImage ? '600' : '120'; ?>"
                                    data-style-field="<?php echo e($fieldName); ?>"
                                    data-style-type="size"
                                    <?php echo $isEnabled ? '' : 'disabled'; ?>
                                >

                                <?php if ($isImage) { ?>
                                    <input type="hidden" name="fontFamily[<?php echo e($fieldName); ?>]" value="Arial">
                                    <input type="hidden" name="fontColor[<?php echo e($fieldName); ?>]" value="#0f172a">
                                <?php } else { ?>
                                    <select
                                        name="fontFamily[<?php echo e($fieldName); ?>]"
                                        data-style-field="<?php echo e($fieldName); ?>"
                                        data-style-type="font"
                                        <?php echo $isEnabled ? '' : 'disabled'; ?>
                                    >
                                        <?php foreach ($fontOptions as $fontName) { ?>
                                            <option value="<?php echo e($fontName); ?>" <?php echo $family === $fontName ? "selected" : ""; ?>><?php echo e($fontName); ?></option>
                                        <?php } ?>
                                    </select>
                                    <input
                                        type="color"
                                        name="fontColor[<?php echo e($fieldName); ?>]"
                                        value="<?php echo e($color); ?>"
                                        data-style-field="<?php echo e($fieldName); ?>"
                                        data-style-type="color"
                                        <?php echo $isEnabled ? '' : 'disabled'; ?>
                                    >
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <button type="submit" name="save_positions" class="primary-btn save-position-btn">Save Selected Content & Positions</button>
                    </div>
                </div>
            </form>
        <?php } else { ?>
            <div class="empty-state">Select a template first to choose certificate content and arrange positions.</div>
        <?php } ?>
    </section>


    <section class="cert-panel full-panel">
        <div class="panel-title">
            <div>
                <h2>Generate Certificate</h2>
            </div>
            <span>certificate</span>
        </div>


        <form method="POST" enctype="multipart/form-data" class="confirm-form certificate-generate-form" data-confirm="Generate certificate for selected participant(s)?">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="courseID" value="<?php echo e($selectedCourseID); ?>">
            <input type="hidden" name="sessionID" value="<?php echo e($selectedSessionID); ?>">
            <input type="hidden" name="trainerID" value="<?php echo e($selectedTrainerID); ?>">
            <input type="hidden" name="templateID" value="<?php echo e($selectedTemplateID); ?>">

            <?php if (!empty($positions)) { ?>
                <?php foreach ($positions as $fieldName => $position) {
                    if (!isset($fieldDefinitions[$fieldName])) continue;
                ?>
                    <input type="hidden" name="certificateField[]" value="<?php echo e($fieldName); ?>">
                <?php } ?>

                <?php
                    $hasSignatureOptions = isset($positions["signature_image"]) || isset($positions["signature_name"]) || isset($positions["signature_title"]);
                    $hasCustomTextOptions = isset($positions["custom_text_1"]);
                ?>
                <?php if ($hasSignatureOptions || $hasCustomTextOptions) { ?>
                    <div class="generation-extra-grid compact-generation-grid">
                        <?php if ($hasSignatureOptions) { ?>
                            <div class="generation-extra-card signature-upload-card">
                                <h3>Signature</h3>

                                <?php if (isset($positions["signature_image"])) { ?>
                                    <div class="generation-input-block" data-generation-input="signature_image">
                                        <label>Signature Image</label>
                                        <input type="file" name="signatureFile" accept=".png,.jpg,.jpeg">
                                        <small>White background will be removed automatically.</small>
                                    </div>
                                <?php } ?>

                                <?php if (isset($positions["signature_name"])) { ?>
                                    <div class="generation-input-block" data-generation-input="signature_name">
                                        <label>Signatory Name</label>
                                        <input type="text" name="signatureName" placeholder="Example: Dr. Ahmad Bin Ali">

                                    </div>
                                <?php } ?>

                                <?php if (isset($positions["signature_title"])) { ?>
                                    <div class="generation-input-block" data-generation-input="signature_title">
                                        <label>Signatory Position / Title</label>
                                        <input type="text" name="signatureTitle" placeholder="Example: Programme Director">

                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <?php if ($hasCustomTextOptions) { ?>
                            <div class="generation-extra-card custom-text-card">
                                <h3>Custom Text</h3>

                                <?php if (isset($positions["custom_text_1"])) { ?>
                                    <div class="generation-input-block" data-generation-input="custom_text_1">
                                        <label>Custom Text Line 1</label>
                                        <input type="text" name="customText1" placeholder="Example: For successfully completing the training">

                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="empty-state">Save at least one certificate field position before generating certificates.</div>
            <?php } ?>

            <?php if (!empty($eligibleParticipants)) { ?>
                <div class="participant-selection-section">
                    <div class="participant-select-toolbar">
                        <label class="select-all-control">
                            <input type="checkbox" id="selectAllParticipants">
                            <span>Select all eligible participants</span>
                        </label>
                        <strong id="selectedParticipantCount">0 selected</strong>
                    </div>

                    <div class="eligible-list">
                        <?php foreach ($eligibleParticipants as $participant) { ?>
                            <label class="eligible-card">
                                <input class="participant-checkbox" type="checkbox" name="participantID[]" value="<?php echo e($participant["participantID"]); ?>">
                                <div>
                                    <strong><?php echo e($participant["participantName"]); ?></strong>
                                </div>
                            </label>
                        <?php } ?>
                    </div>
                </div>

                <button type="submit" name="generate_certificate" class="primary-btn generate-btn" <?php echo empty($positions) ? 'disabled' : ''; ?>>Generate Selected Certificates</button>
            <?php } else { ?>
                <div class="empty-state">No eligible participant yet. Participant or PIC must scan the QR attendance, attendance must be approved by staff, and feedback must be completed before the certificate becomes eligible.</div>
            <?php } ?>
        </form>
    </section>


    </div>

    <div class="cert-tab-panel <?php echo $activeTab === 'history' ? 'active' : ''; ?>" id="certTabHistory" data-cert-tab-panel="history">
    <section class="cert-panel full-panel generated-history-panel">
        <div class="panel-title">
            <div>
                <h2>Generated Certificate List</h2>
                <p class="panel-subtitle">Courses stay collapsed to keep the page short. Click a course to view its sessions and certificates.</p>
            </div>
            <span><?php echo e($totalGeneratedCertificateCount); ?> certificate(s)</span>
        </div>

        <?php if (!empty($generatedCourseGroups)) { ?>
            <div class="certificate-history-filter">
                <div class="certificate-filter-grid">
                    <div class="certificate-search-field">
                        <label for="certificateHistorySearch">Search</label>
                        <input type="search" id="certificateHistorySearch" placeholder="Example: CERT0001 or Nur Aisyah">
                    </div>

                    <div>
                        <label for="certificateCourseFilter">Course</label>
                        <select id="certificateCourseFilter">
                            <option value="">All courses</option>
                            <?php foreach ($generatedCourseGroups as $courseGroup) { ?>
                                <option value="<?php echo e($courseGroup["courseID"]); ?>">
                                    <?php echo e($courseGroup["courseName"]); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label for="certificateSessionFilter">Course Session</label>
                        <select id="certificateSessionFilter">
                            <option value="">All sessions</option>
                            <?php foreach ($generatedSessionFilterOptions as $sessionOption) { ?>
                                <option value="<?php echo e($sessionOption["sessionID"]); ?>">
                                    <?php echo e($sessionOption["courseName"]); ?> ·
                                    <?php echo e($sessionOption["sessionName"] ?: $sessionOption["sessionID"]); ?> ·
                                    <?php echo e(date("d M Y", strtotime($sessionOption["sessionDate"]))); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label for="certificateDownloadFilter">Download Status</label>
                        <select id="certificateDownloadFilter">
                            <option value="">All download statuses</option>
                            <option value="downloaded">Downloaded</option>
                            <option value="not_downloaded">Not downloaded</option>
                        </select>
                    </div>

                    <div>
                        <label for="certificateEmailFilter">Email Status</label>
                        <select id="certificateEmailFilter">
                            <option value="">All email statuses</option>
                            <option value="sent">Sent</option>
                            <option value="not_sent">Not sent</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>

                    <div class="certificate-filter-actions">
                        <button type="button" class="primary-btn" id="applyCertificateFilter">Filter</button>
                        <button type="button" class="secondary-btn" id="resetCertificateFilter">Reset</button>
                    </div>
                </div>
            </div>

            <div id="certificateFilterEmpty" class="empty-state certificate-filter-empty" hidden>
                No generated certificate matches the current search or filter.
            </div>

            <div class="certificate-course-list">
                <?php foreach ($generatedCourseGroups as $courseKey => $courseGroup) { ?>
                    <?php
                        $courseDomID = 'certificate-course-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$courseKey);
                        $courseSearchText = strtolower(
                            $courseGroup["courseID"] . " " .
                            $courseGroup["courseName"]
                        );
                    ?>
                    <section
                        class="certificate-course-accordion"
                        data-certificate-course
                        data-course-id="<?php echo e($courseGroup["courseID"]); ?>"
                        data-search="<?php echo e($courseSearchText); ?>"
                    >
                        <button
                            type="button"
                            class="certificate-course-toggle"
                            data-course-toggle="<?php echo e($courseDomID); ?>"
                            data-course-name="<?php echo e($courseGroup["courseName"]); ?>"
                            aria-expanded="false"
                            aria-controls="<?php echo e($courseDomID); ?>"
                        >
                            <span class="course-accordion-icon">
                                <?php echo e(strtoupper(substr($courseGroup["courseName"], 0, 1))); ?>
                            </span>

                            <span class="course-accordion-main">
                                <strong><?php echo e($courseGroup["courseName"]); ?></strong>
                            </span>

                            <span class="course-accordion-status">
                                <span>View certificates</span>
                            </span>
                        </button>

                        <div class="certificate-course-body certificate-course-template" id="<?php echo e($courseDomID); ?>" hidden>
                            <form
                                method="POST"
                                class="certificate-download-form course-certificate-download-form confirm-form" data-confirm="Continue with the selected certificate action?"
                                data-course-download-form
                            >
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">

                                <div class="course-download-toolbar">
                                    <label class="select-all-control">
                                        <input type="checkbox" class="course-download-select">
                                        <span>Select all certificates in this course</span>
                                    </label>

                                    <div class="download-toolbar-actions">
                                        <strong class="course-selected-download-count">0 selected</strong>
                                        <button type="submit" name="download_certificates" class="primary-btn download-selected-btn">
                                            Download Certificates
                                        </button>
                                        <button type="submit" name="send_certificates" class="secondary-btn send-selected-btn" data-confirm="Send selected certificate(s) to participant email?">
                                            Email Certificates
                                        </button>
                                    </div>
                                </div>

                                <div class="course-session-list">
                                    <?php foreach ($courseGroup["sessions"] as $sessionKey => $sessionGroup) { ?>
                                        <?php
                                            $sessionDomID = $courseDomID . '-session-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$sessionKey);
                                            $sessionSearchText = strtolower(
                                                $sessionGroup["sessionID"] . " " .
                                                ($sessionGroup["sessionName"] ?: "") . " " .
                                                ($sessionGroup["location"] ?: "")
                                            );
                                        ?>
                                        <section
                                            class="certificate-session-block"
                                            data-certificate-session
                                            data-session-id="<?php echo e($sessionGroup["sessionID"]); ?>"
                                            data-search="<?php echo e($sessionSearchText); ?>"
                                        >
                                            <div class="certificate-session-header">
                                                <div>
                                                    <span class="session-code-pill"><?php echo e($sessionGroup["sessionID"]); ?></span>
                                                    <h4><?php echo e($sessionGroup["sessionName"] ?: $sessionGroup["sessionID"]); ?></h4>
                                                    <p>
                                                        <?php echo e(date("d M Y", strtotime($sessionGroup["sessionDate"]))); ?>
                                                        <?php if (!empty($sessionGroup["startTime"]) && !empty($sessionGroup["endTime"])) { ?>
                                                            · <?php echo e(date("h:i A", strtotime($sessionGroup["startTime"]))); ?>
                                                            - <?php echo e(date("h:i A", strtotime($sessionGroup["endTime"]))); ?>
                                                        <?php } ?>
                                                        <?php if (!empty($sessionGroup["location"])) { ?>
                                                            · <?php echo e($sessionGroup["location"]); ?>
                                                        <?php } ?>
                                                    </p>
                                                </div>

                                                <label class="group-select-control">
                                                    <input
                                                        type="checkbox"
                                                        class="session-download-select"
                                                        data-session-target="<?php echo e($sessionDomID); ?>"
                                                    >
                                                    <span>Select all in session</span>
                                                    <strong><?php echo e($sessionGroup["certificateCount"]); ?></strong>
                                                </label>
                                            </div>

                                            <div class="table-wrap certificate-session-table-wrap">
                                                <table class="cert-table grouped-cert-table">
                                                    <thead>
                                                        <tr>
                                                            <th class="checkbox-column">Select</th>
                                                            <th>Participant</th>
                                                            <th>Trainer</th>
                                                            <th>Generated By</th>
                                                            <th>Generated Date</th>
                                                            <th class="status-center">Download Status</th>
                                                            <th class="status-center">Email Status</th>
                                                            <th class="status-center">Action</th>
                                                            <th class="status-center">File</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="<?php echo e($sessionDomID); ?>">
                                                        <?php foreach ($sessionGroup["certificates"] as $cert) { ?>
                                                            <?php
                                                                $rowSearchText = strtolower(
                                                                    $cert["certificateID"] . " " .
                                                                    $cert["participantID"] . " " .
                                                                    $cert["participantName"] . " " .
                                                                    $cert["courseID"] . " " .
                                                                    $cert["courseName"] . " " .
                                                                    $cert["sessionID"] . " " .
                                                                    ($cert["sessionName"] ?: "") . " " .
                                                                    $cert["trainerName"] . " " .
                                                                    $cert["staffName"]
                                                                );
                                                                $downloadStatusValue = $cert["isDownloaded"] ? "downloaded" : "not_downloaded";
                                                                $emailStatusValue = $cert["emailStatus"] ?: "not_sent";
                                                                $emailStatusLabel = $emailStatusValue === "sent" ? "Sent" : ($emailStatusValue === "failed" ? "Failed" : "Not sent");
                                                                $emailStatusClass = $emailStatusValue === "sent" ? "sent" : ($emailStatusValue === "failed" ? "failed" : "not-sent");
                                                            ?>
                                                            <tr
                                                                data-certificate-row
                                                                data-search="<?php echo e($rowSearchText); ?>"
                                                                data-course-id="<?php echo e($cert["courseID"]); ?>"
                                                                data-session-id="<?php echo e($cert["sessionID"]); ?>"
                                                                data-download-status="<?php echo e($downloadStatusValue); ?>"
                                                                data-email-status="<?php echo e($emailStatusValue); ?>"
                                                            >
                                                                <td class="checkbox-column">
                                                                    <input
                                                                        class="download-certificate-checkbox"
                                                                        type="checkbox"
                                                                        name="certificateID[]"
                                                                        value="<?php echo e($cert["certificateID"]); ?>"
                                                                    >
                                                                </td>
                                                                <td>
                                                                    <strong><?php echo e($cert["participantName"]); ?></strong>
                                                                </td>
                                                                <td><?php echo e($cert["trainerName"]); ?></td>
                                                                <td><?php echo e($cert["staffName"]); ?></td>
                                                                <td>
                                                                    <?php echo e(date("d M Y", strtotime($cert["generatedDate"]))); ?>
                                                                    <span><?php echo e(date("h:i A", strtotime($cert["generatedDate"]))); ?></span>
                                                                </td>
                                                                <td class="status-center download-status-cell">
                                                                    <span class="download-status-pill <?php echo $cert["isDownloaded"] ? 'downloaded' : 'not-downloaded'; ?>">
                                                                        <?php echo $cert["isDownloaded"] ? 'Downloaded' : 'Not downloaded'; ?>
                                                                    </span>
                                                                    <small class="download-status-date">
                                                                        <?php echo $cert["isDownloaded"] && $cert["downloadedAt"]
                                                                            ? e(date("d M Y, h:i A", strtotime($cert["downloadedAt"])))
                                                                            : ''; ?>
                                                                    </small>
                                                                </td>
                                                                <td class="status-center email-status-cell">
                                                                    <span class="email-status-pill <?php echo e($emailStatusClass); ?>">
                                                                        <?php echo e($emailStatusLabel); ?>
                                                                    </span>
                                                                    <small class="email-status-date">
                                                                        <?php echo $cert["emailStatus"] === "sent" && $cert["emailSentAt"]
                                                                            ? e(date("d M Y, h:i A", strtotime($cert["emailSentAt"])))
                                                                            : (!empty($cert["emailMessage"]) ? e($cert["emailMessage"]) : ''); ?>
                                                                    </small>
                                                                </td>
                                                                <td class="status-center action-cell">
                                                                    <button
                                                                        type="submit"
                                                                        name="send_single_certificate"
                                                                        value="<?php echo e($cert["certificateID"]); ?>"
                                                                        class="mini-secondary-btn resend-cert-btn"
                                                                        data-confirm="<?php echo $cert["emailStatus"] === "sent" ? 'Resend this certificate to the participant email?' : 'Send this certificate to the participant email?'; ?>"
                                                                    >
                                                                        <?php echo $cert["emailStatus"] === "sent" ? 'Resend' : 'Send'; ?>
                                                                    </button>
                                                                </td>
                                                                <td class="status-center">
                                                                    <a href="<?php echo e($cert["generatedCertificate"]); ?>" target="_blank" class="file-link">Open</a>
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </section>
                                    <?php } ?>
                                </div>
                            </form>
                        </div>
                    </section>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="empty-state">No generated certificate yet.</div>
        <?php } ?>
    </section>
    </div>

</div>

<script>

document.querySelectorAll('[data-cert-tab]').forEach(function(tabButton) {
    tabButton.addEventListener('click', function() {
        const target = tabButton.dataset.certTab;

        document.querySelectorAll('[data-cert-tab]').forEach(function(button) {
            button.classList.toggle('active', button === tabButton);
        });

        document.querySelectorAll('[data-cert-tab-panel]').forEach(function(panel) {
            panel.classList.toggle('active', panel.dataset.certTabPanel === target);
        });

        const url = new URL(window.location.href);
        url.searchParams.set('tab', target);
        window.history.replaceState({}, '', url.toString());
    });
});

const editor = document.getElementById("templateEditor");
let activeField = null;
let offsetX = 0;
let offsetY = 0;

function setFieldEnabled(fieldName, enabled) {
    const field = document.querySelector('.drag-field[data-field="' + fieldName + '"]');
    const setting = document.querySelector('[data-field-setting="' + fieldName + '"]');
    const choice = document.querySelector('[data-field-choice="' + fieldName + '"]');

    if (field) {
        field.classList.toggle('field-disabled', !enabled);
    }

    if (setting) {
        setting.classList.toggle('field-setting-disabled', !enabled);
        const fieldType = field ? field.dataset.fieldType : 'text';
        setting.querySelectorAll('input, select').forEach(function(input) {
            if (!enabled) {
                input.disabled = true;
                return;
            }

            if (fieldType === 'image' && (input.dataset.styleType === 'font' || input.dataset.styleType === 'color')) {
                input.disabled = true;
            } else {
                input.disabled = false;
            }
        });
    }

    if (choice) {
        choice.classList.toggle('enabled', enabled);
    }
}

document.querySelectorAll('.field-enable-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        setFieldEnabled(toggle.dataset.field, toggle.checked);
    });
});

const enableAllFieldsButton = document.getElementById('enableAllFields');
if (enableAllFieldsButton) {
    enableAllFieldsButton.addEventListener('click', function() {
        document.querySelectorAll('.field-enable-toggle').forEach(function(toggle) {
            toggle.checked = true;
            setFieldEnabled(toggle.dataset.field, true);
        });
    });
}

const clearOptionalFieldsButton = document.getElementById('clearOptionalFields');
if (clearOptionalFieldsButton) {
    clearOptionalFieldsButton.addEventListener('click', function() {
        document.querySelectorAll('.field-enable-toggle').forEach(function(toggle) {
            toggle.checked = false;
            setFieldEnabled(toggle.dataset.field, false);
        });
    });
}

if (editor) {
    document.querySelectorAll('.drag-field').forEach(function(field) {
        field.addEventListener('pointerdown', function(event) {
            if (field.classList.contains('field-disabled')) return;

            activeField = field;
            const rect = field.getBoundingClientRect();
            offsetX = event.clientX - rect.left;
            offsetY = event.clientY - rect.top;
            field.classList.add('dragging');
            field.setPointerCapture?.(event.pointerId);
            event.preventDefault();
        });
    });

    document.addEventListener('pointermove', function(event) {
        if (!activeField) return;

        const rect = editor.getBoundingClientRect();
        let x = ((event.clientX - rect.left - offsetX + activeField.offsetWidth / 2) / rect.width) * 100;
        let y = ((event.clientY - rect.top - offsetY + activeField.offsetHeight / 2) / rect.height) * 100;

        x = Math.max(0, Math.min(100, x));
        y = Math.max(0, Math.min(100, y));

        activeField.style.left = x + '%';
        activeField.style.top = y + '%';

        const fieldName = activeField.dataset.field;
        const xInput = document.getElementById(fieldName + '_x');
        const yInput = document.getElementById(fieldName + '_y');
        if (xInput) xInput.value = x.toFixed(2);
        if (yInput) yInput.value = y.toFixed(2);
    });

    document.addEventListener('pointerup', function() {
        if (activeField) {
            activeField.classList.remove('dragging');
        }
        activeField = null;
    });

    document.querySelectorAll('[data-style-field]').forEach(function(input) {
        input.addEventListener('input', function() {
            const fieldName = input.dataset.styleField;
            const type = input.dataset.styleType;
            const field = document.querySelector('.drag-field[data-field="' + fieldName + '"]');

            if (!field) return;

            if (type === 'size') {
                if (field.dataset.fieldType === 'image') {
                    field.style.width = input.value + 'px';
                } else {
                    field.style.fontSize = input.value + 'px';
                }
            }

            if (type === 'color') {
                field.style.color = input.value;
            }

            if (type === 'font') {
                field.style.fontFamily = input.value;
            }
        });
    });
}

function syncToggleAllButton(button, checkboxes) {
    if (!button || checkboxes.length === 0) return;
    const allChecked = checkboxes.every(function(checkbox) { return checkbox.checked; });
    button.textContent = allChecked ? 'Clear All' : 'Select All';
}

const generationFieldButton = document.getElementById('toggleAllGenerationFields');
const generationFieldCheckboxes = Array.from(document.querySelectorAll('.generation-field-checkbox'));

function updateGenerationInputVisibility(fieldName, enabled) {
    document.querySelectorAll('[data-generation-input="' + fieldName + '"]').forEach(function(block) {
        block.hidden = !enabled;
        block.querySelectorAll('input, select, textarea').forEach(function(input) {
            input.disabled = !enabled;
        });
    });
}

function refreshGenerationFieldInputs() {
    generationFieldCheckboxes.forEach(function(checkbox) {
        updateGenerationInputVisibility(checkbox.dataset.field || checkbox.value, checkbox.checked);
    });
}

if (generationFieldButton && generationFieldCheckboxes.length > 0) {
    generationFieldButton.addEventListener('click', function() {
        const shouldCheck = !generationFieldCheckboxes.every(function(checkbox) { return checkbox.checked; });
        generationFieldCheckboxes.forEach(function(checkbox) {
            checkbox.checked = shouldCheck;
        });
        refreshGenerationFieldInputs();
        syncToggleAllButton(generationFieldButton, generationFieldCheckboxes);
    });

    generationFieldCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            updateGenerationInputVisibility(checkbox.dataset.field || checkbox.value, checkbox.checked);
            syncToggleAllButton(generationFieldButton, generationFieldCheckboxes);
        });
    });

    refreshGenerationFieldInputs();
    syncToggleAllButton(generationFieldButton, generationFieldCheckboxes);
}

const participantCheckboxes = Array.from(document.querySelectorAll('.participant-checkbox'));
const selectAllParticipants = document.getElementById('selectAllParticipants');
const selectedParticipantCount = document.getElementById('selectedParticipantCount');

function updateParticipantSelection() {
    const checkedCount = participantCheckboxes.filter(function(checkbox) { return checkbox.checked; }).length;
    if (selectedParticipantCount) {
        selectedParticipantCount.textContent = checkedCount + ' selected';
    }
    if (selectAllParticipants) {
        selectAllParticipants.checked = participantCheckboxes.length > 0 && checkedCount === participantCheckboxes.length;
        selectAllParticipants.indeterminate = checkedCount > 0 && checkedCount < participantCheckboxes.length;
    }
}

if (selectAllParticipants) {
    selectAllParticipants.addEventListener('change', function() {
        participantCheckboxes.forEach(function(checkbox) {
            checkbox.checked = selectAllParticipants.checked;
        });
        updateParticipantSelection();
    });
}
participantCheckboxes.forEach(function(checkbox) {
    checkbox.addEventListener('change', updateParticipantSelection);
});
updateParticipantSelection();

/* =========================
   COLLAPSIBLE GENERATED CERTIFICATES
========================= */
const certificateListModal = document.getElementById('certificateListModal');
const certificateListModalBody = document.getElementById('certificateListModalBody');
const certificateListModalTitle = document.getElementById('certificateListModalTitle');
const certificateListModalAvatar = document.getElementById('certificateListModalAvatar');
const certificateListModalClose = document.getElementById('certificateListModalClose');

function closeCertificateListModal() {
    if (!certificateListModal) return;
    certificateListModal.classList.remove('show');
    certificateListModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('certificate-modal-lock');
}

function openCertificateListModal(toggle) {
    const body = document.getElementById(toggle.dataset.courseToggle);
    if (!body || !certificateListModal || !certificateListModalBody) return;

    const courseName = toggle.dataset.courseName || 'Course certificates';
    certificateListModalTitle.textContent = courseName;
    certificateListModalAvatar.textContent = courseName.trim().charAt(0).toUpperCase() || 'C';
    certificateListModalBody.innerHTML = body.innerHTML;
    certificateListModalBody.scrollTop = 0;
    requestAnimationFrame(function() { certificateListModalBody.scrollTop = 0; });

    initializeCertificateDownloadControls(certificateListModalBody);
    initConfirmForms(certificateListModalBody);

    certificateListModal.classList.add('show');
    certificateListModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('certificate-modal-lock');
}

if (certificateListModalClose) {
    certificateListModalClose.addEventListener('click', closeCertificateListModal);
}

if (certificateListModal) {
    certificateListModal.addEventListener('click', function(event) {
        if (event.target === certificateListModal) closeCertificateListModal();
    });
}


document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && certificateListModal && certificateListModal.classList.contains('show')) {
        closeCertificateListModal();
    }
});

document.querySelectorAll('[data-course-toggle]').forEach(function(toggle) {
    toggle.addEventListener('click', function() {
        openCertificateListModal(toggle);
    });
});

function getVisibleCourseCheckboxes(form) {
    return Array.from(form.querySelectorAll('.download-certificate-checkbox')).filter(function(checkbox) {
        const row = checkbox.closest('[data-certificate-row]');
        return row && !row.hidden;
    });
}

function updateCourseDownloadSelection(form) {
    const allCheckboxes = Array.from(form.querySelectorAll('.download-certificate-checkbox'));
    const checkedCount = allCheckboxes.filter(function(checkbox) { return checkbox.checked; }).length;
    const countLabel = form.querySelector('.course-selected-download-count');
    const courseSelector = form.querySelector('.course-download-select');

    if (countLabel) {
        countLabel.textContent = checkedCount + ' selected';
    }

    if (courseSelector) {
        courseSelector.checked = allCheckboxes.length > 0 && checkedCount === allCheckboxes.length;
        courseSelector.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
    }

    form.querySelectorAll('.session-download-select').forEach(function(sessionSelector) {
        let target = null;
        if (window.CSS && CSS.escape) {
            target = form.querySelector('#' + CSS.escape(sessionSelector.dataset.sessionTarget));
        }
        if (!target) {
            target = form.querySelector('[id="' + sessionSelector.dataset.sessionTarget.replace(/"/g, '\"') + '"]') || document.getElementById(sessionSelector.dataset.sessionTarget);
        }
        const sessionCheckboxes = target ? Array.from(target.querySelectorAll('.download-certificate-checkbox')) : [];
        const sessionChecked = sessionCheckboxes.filter(function(checkbox) { return checkbox.checked; }).length;

        sessionSelector.checked = sessionCheckboxes.length > 0 && sessionChecked === sessionCheckboxes.length;
        sessionSelector.indeterminate = sessionChecked > 0 && sessionChecked < sessionCheckboxes.length;
    });
}

function markSelectedRowsDownloaded(form) {
    const nowText = 'Downloaded just now';

    form.querySelectorAll('.download-certificate-checkbox:checked').forEach(function(checkbox) {
        const row = checkbox.closest('[data-certificate-row]');
        if (!row) return;

        row.dataset.downloadStatus = 'downloaded';

        const status = row.querySelector('.download-status-pill');
        const statusDate = row.querySelector('.download-status-date');

        if (status) {
            status.textContent = 'Downloaded';
            status.classList.remove('not-downloaded');
            status.classList.add('downloaded');
        }

        if (statusDate) {
            statusDate.textContent = nowText;
        }
    });
}

function initCourseDownloadForm(form) {
    if (!form || form.dataset.downloadReady === '1') return;
    form.dataset.downloadReady = '1';

    const courseSelector = form.querySelector('.course-download-select');

    if (courseSelector) {
        courseSelector.addEventListener('change', function() {
            getVisibleCourseCheckboxes(form).forEach(function(checkbox) {
                checkbox.checked = courseSelector.checked;
            });
            updateCourseDownloadSelection(form);
        });
    }

    form.querySelectorAll('.session-download-select').forEach(function(sessionSelector) {
        sessionSelector.addEventListener('change', function() {
            let target = null;
            if (window.CSS && CSS.escape) {
                target = form.querySelector('#' + CSS.escape(sessionSelector.dataset.sessionTarget));
            }
            if (!target) {
                target = form.querySelector('[id="' + sessionSelector.dataset.sessionTarget.replace(/"/g, '\"') + '"]') || document.getElementById(sessionSelector.dataset.sessionTarget);
            }
            if (!target) return;

            target.querySelectorAll('.download-certificate-checkbox').forEach(function(checkbox) {
                const row = checkbox.closest('[data-certificate-row]');
                if (row && !row.hidden) {
                    checkbox.checked = sessionSelector.checked;
                }
            });

            updateCourseDownloadSelection(form);
        });
    });

    form.querySelectorAll('.download-certificate-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            updateCourseDownloadSelection(form);
        });
    });

    updateCourseDownloadSelection(form);
}

function initializeCertificateDownloadControls(root) {
    (root || document).querySelectorAll('[data-course-download-form]').forEach(initCourseDownloadForm);
}

initializeCertificateDownloadControls(document);

/* =========================
   GENERATED CERTIFICATE SEARCH + FILTER
========================= */
const certificateHistorySearch = document.getElementById('certificateHistorySearch');
const certificateCourseFilter = document.getElementById('certificateCourseFilter');
const certificateSessionFilter = document.getElementById('certificateSessionFilter');
const certificateDownloadFilter = document.getElementById('certificateDownloadFilter');
const certificateEmailFilter = document.getElementById('certificateEmailFilter');
const applyCertificateFilterButton = document.getElementById('applyCertificateFilter');
const resetCertificateFilterButton = document.getElementById('resetCertificateFilter');
const certificateFilterCount = document.getElementById('certificateFilterCount');
const certificateFilterEmpty = document.getElementById('certificateFilterEmpty');

function applyCertificateHistoryFilter() {
    const searchValue = (certificateHistorySearch?.value || '').trim().toLowerCase();
    const courseValue = certificateCourseFilter?.value || '';
    const sessionValue = certificateSessionFilter?.value || '';
    const downloadValue = certificateDownloadFilter?.value || '';
    const emailValue = certificateEmailFilter?.value || '';
    const hasActiveFilter = searchValue !== '' || courseValue !== '' || sessionValue !== '' || downloadValue !== '' || emailValue !== '';

    let visibleCertificateCount = 0;
    let visibleCourseCount = 0;

    document.querySelectorAll('[data-certificate-course]').forEach(function(courseCard) {
        const courseID = courseCard.dataset.courseId || '';
        let courseHasMatch = false;

        courseCard.querySelectorAll('[data-certificate-session]').forEach(function(sessionBlock) {
            const sessionID = sessionBlock.dataset.sessionId || '';
            let sessionHasMatch = false;

            sessionBlock.querySelectorAll('[data-certificate-row]').forEach(function(row) {
                const matchesSearch = searchValue === '' || (row.dataset.search || '').includes(searchValue);
                const matchesCourse = courseValue === '' || courseID === courseValue;
                const matchesSession = sessionValue === '' || sessionID === sessionValue;
                const matchesDownload = downloadValue === '' || row.dataset.downloadStatus === downloadValue;
                const matchesEmail = emailValue === '' || row.dataset.emailStatus === emailValue;
                const matches = matchesSearch && matchesCourse && matchesSession && matchesDownload && matchesEmail;

                row.hidden = !matches;

                if (matches) {
                    sessionHasMatch = true;
                    courseHasMatch = true;
                    visibleCertificateCount++;
                } else {
                    const checkbox = row.querySelector('.download-certificate-checkbox');
                    if (checkbox) checkbox.checked = false;
                }
            });

            sessionBlock.hidden = !sessionHasMatch;
        });

        courseCard.hidden = !courseHasMatch;

        if (courseHasMatch) {
            visibleCourseCount++;
        }

        const form = courseCard.querySelector('[data-course-download-form]');
        if (form) updateCourseDownloadSelection(form);
    });


    if (certificateFilterEmpty) {
        certificateFilterEmpty.hidden = !hasActiveFilter || visibleCertificateCount !== 0;
    }
}

if (applyCertificateFilterButton) {
    applyCertificateFilterButton.addEventListener('click', applyCertificateHistoryFilter);
}

if (certificateHistorySearch) {
    certificateHistorySearch.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyCertificateHistoryFilter();
        }
    });
}

if (resetCertificateFilterButton) {
    resetCertificateFilterButton.addEventListener('click', function() {
        if (certificateHistorySearch) certificateHistorySearch.value = '';
        if (certificateCourseFilter) certificateCourseFilter.value = '';
        if (certificateSessionFilter) certificateSessionFilter.value = '';
        if (certificateDownloadFilter) certificateDownloadFilter.value = '';
        if (certificateEmailFilter) certificateEmailFilter.value = '';

        document.querySelectorAll('[data-certificate-course]').forEach(function(courseCard) {
            courseCard.hidden = false;

            courseCard.querySelectorAll('[data-certificate-session], [data-certificate-row]').forEach(function(item) {
                item.hidden = false;
            });

            const form = courseCard.querySelector('[data-course-download-form]');
            if (form) {
                form.querySelectorAll('.download-certificate-checkbox').forEach(function(checkbox) {
                    checkbox.checked = false;
                });
                updateCourseDownloadSelection(form);
            }
        });

        applyCertificateHistoryFilter();
    });
}

applyCertificateHistoryFilter();

const generationOverlay = document.getElementById('generationOverlay');
const generationFill = document.getElementById('generationProgressFill');
const generationPercent = document.getElementById('generationPercent');
const generationStatus = document.getElementById('generationStatus');
let generationTimer = null;

function updateGenerationProgress(value) {
    const safeValue = Math.max(0, Math.min(100, Math.round(value)));
    if (generationFill) generationFill.style.width = safeValue + '%';
    if (generationPercent) generationPercent.textContent = safeValue + '%';
}

function showGenerationProgress(totalSelected) {
    if (!generationOverlay) return;

    generationOverlay.classList.add('show');
    generationOverlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('generation-lock');

    let progress = 8;
    updateGenerationProgress(progress);

    if (generationStatus) {
        generationStatus.textContent = 'Generating ' + totalSelected + ' certificate(s)...';
    }

    clearInterval(generationTimer);
    generationTimer = setInterval(function() {
        progress = Math.min(progress + (progress < 70 ? 5 : 2), 100);
        updateGenerationProgress(progress);
    }, 420);
}


const appModalOverlay = document.getElementById('appModalOverlay');
const appModalTitle = document.getElementById('appModalTitle');
const appModalMessage = document.getElementById('appModalMessage');
const appModalIcon = document.getElementById('appModalIcon');
const appModalActions = document.getElementById('appModalActions');
const appModalCancel = document.getElementById('appModalCancel');
const appModalConfirm = document.getElementById('appModalConfirm');
let modalResolver = null;

function openAppNotice(message, type) {
    if (!appModalOverlay) return;

    appModalTitle.textContent = type === 'success' ? 'Successful' : (type === 'error' ? 'Action needed' : 'Notice');
    appModalMessage.textContent = message;
    appModalIcon.textContent = type === 'success' ? '✓' : (type === 'error' ? '!' : 'i');
    appModalOverlay.className = 'app-modal-overlay show ' + (type || 'info');
    appModalOverlay.setAttribute('aria-hidden', 'false');
    appModalActions.classList.add('notice-only');
    appModalCancel.hidden = true;
    appModalConfirm.textContent = 'OK';

    modalResolver = function() {
        closeAppModal();
    };
}

function closeAppModal() {
    if (!appModalOverlay) return;
    appModalOverlay.classList.remove('show');
    appModalOverlay.setAttribute('aria-hidden', 'true');
    appModalCancel.hidden = false;
    appModalActions.classList.remove('notice-only');
    appModalConfirm.textContent = 'Yes, continue';
    modalResolver = null;
}

function requestAppConfirm(message) {
    return new Promise(function(resolve) {
        if (!appModalOverlay) {
            resolve(true);
            return;
        }

        appModalTitle.textContent = 'Please confirm';
        appModalMessage.textContent = message;
        appModalIcon.textContent = '?';
        appModalOverlay.className = 'app-modal-overlay show confirm';
        appModalOverlay.setAttribute('aria-hidden', 'false');
        appModalCancel.hidden = false;
        appModalConfirm.textContent = 'Yes, continue';

        modalResolver = function(answer) {
            closeAppModal();
            resolve(Boolean(answer));
        };
    });
}

if (appModalCancel) {
    appModalCancel.addEventListener('click', function() {
        if (modalResolver) modalResolver(false);
        else closeAppModal();
    });
}

if (appModalConfirm) {
    appModalConfirm.addEventListener('click', function() {
        if (modalResolver) modalResolver(true);
        else closeAppModal();
    });
}

if (appModalOverlay) {
    appModalOverlay.addEventListener('click', function(event) {
        if (event.target === appModalOverlay) {
            if (modalResolver) modalResolver(false);
            else closeAppModal();
        }
    });
}

function initConfirmForms(root) {
    (root || document).querySelectorAll('.confirm-form').forEach(function(form) {
        if (!form || form.dataset.confirmReady === '1') return;
        form.dataset.confirmReady = '1';
        form.addEventListener('submit', function(event) {
        if (form.dataset.customConfirmed === '1') {
            delete form.dataset.customConfirmed;
            return;
        }

        const submitter = event.submitter || document.activeElement;
        const isGenerationForm = form.classList.contains('certificate-generate-form') || (submitter && submitter.name === 'generate_certificate');
        const isDownloadAction = submitter && submitter.name === 'download_certificates';
        const isSendAction = submitter && (submitter.name === 'send_certificates' || submitter.name === 'send_single_certificate');
        let selectedCount = 0;

        if (isGenerationForm) {
            selectedCount = form.querySelectorAll('input[name="participantID[]"]:checked').length;
            const selectedFields = form.querySelectorAll('input[name="certificateField[]"]:checked, input[type="hidden"][name="certificateField[]"]').length;

            if (selectedCount === 0) {
                event.preventDefault();
                openAppNotice('Please select at least one eligible participant before generating certificates.', 'error');
                return;
            }

            if (selectedFields === 0) {
                event.preventDefault();
                openAppNotice('Please choose at least one item to place inside the certificate.', 'error');
                return;
            }

            const signatureField = form.querySelector('input[name="certificateField[]"][value="signature_image"]:checked, input[type="hidden"][name="certificateField[]"][value="signature_image"]');
            const signatureFile = form.querySelector('input[name="signatureFile"]');
            if (signatureField && signatureFile && signatureFile.files.length === 0) {
                event.preventDefault();
                openAppNotice('Please upload a signature image or uncheck Signature Image.', 'error');
                return;
            }
        }

        if ((isDownloadAction || isSendAction) && (!submitter || submitter.name !== 'send_single_certificate')) {
            selectedCount = form.querySelectorAll('input[name="certificateID[]"]:checked').length;
            if (selectedCount === 0) {
                event.preventDefault();
                openAppNotice('Please select at least one generated certificate first.', 'error');
                return;
            }
        }

        event.preventDefault();

        const message = submitter?.dataset?.confirm || form.dataset.confirm || 'Are you sure you want to continue?';
        requestAppConfirm(message).then(function(confirmed) {
            if (!confirmed) return;

            if (submitter && submitter.name) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitter.name;
                hidden.value = submitter.value || '1';
                form.appendChild(hidden);
            }

            if (isDownloadAction) {
                markSelectedRowsDownloaded(form);
                setTimeout(function() {
                    applyCertificateHistoryFilter();
                }, 50);
            }

            if (isGenerationForm) {
                showGenerationProgress(selectedCount);
            }

            form.dataset.customConfirmed = '1';
            form.submit();
        });
    });
    });
}

initConfirmForms(document);

<?php if ($message !== "") { ?>
window.addEventListener('load', function() {
    openAppNotice(<?php echo json_encode($message); ?>, 'success');
});
<?php } elseif ($errorMessage !== "") { ?>
window.addEventListener('load', function() {
    openAppNotice(<?php echo json_encode($errorMessage); ?>, 'error');
});
<?php } ?>

</script>

</body>
</html>
