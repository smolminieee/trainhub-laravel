<!DOCTYPE html>
<html>
<head>
    <title>Trainer Management | TrainHub Al Amin </title>
    <link rel="stylesheet" href="assets/css/trainer.css?v=<?php echo file_exists(public_path('assets/css/trainer.css')) ? filemtime(public_path('assets/css/trainer.css')) : time(); ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo file_exists(public_path('assets/css/style.css')) ? filemtime(public_path('assets/css/style.css')) : time(); ?>">
</head>

<body class="trainhub-app page-trainer">
<!-- TRAINER_BUILD_CHECK_TOP_RATED_ABOVE_LIST_2026_09_05 -->

@include('partials.topbar')

<div class="page">

    <?php if ($message !== '') { ?>
        <div class="flash-popup-backdrop" id="flashPopup">
            <div class="flash-popup flash-<?php echo e($messageType); ?>">
                <div class="flash-icon"><?php echo $messageType === 'success' ? '✓' : '!'; ?></div>
                <h3><?php echo $messageType === 'success' ? 'Successful' : 'Notice'; ?></h3>
                <p><?php echo e($message); ?></p>
                <button type="button" onclick="closeFlashPopup()">OK</button>
            </div>
        </div>
    <?php } ?>

    <div class="dashboard-header">
        <div>
            <h1>Trainer Management</h1>
            <p>Manage trainer profile, documents and ratings.</p>
        </div>

        <button type="button" class="primary-btn" onclick="openAddModal()">
            <span>＋</span>
            Add New Trainer
        </button>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon stat-purple">
                <svg viewBox="0 0 24 24">
                    <path d="M16 11C17.66 11 19 9.66 19 8C19 6.34 17.66 5 16 5"/>
                    <path d="M8 11C9.66 11 11 9.66 11 8C11 6.34 9.66 5 8 5C6.34 5 5 6.34 5 8C5 9.66 6.34 11 8 11Z"/>
                    <path d="M2.5 19C3.2 15.8 5.2 14 8 14C10.8 14 12.8 15.8 13.5 19"/>
                    <path d="M14.5 14.3C17 14.7 18.8 16.3 19.5 19"/>
                </svg>
            </div>
            <div>
                <span>Total Trainers</span>
                <h2><?php echo $totalTrainers; ?></h2>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-green">
                <svg viewBox="0 0 24 24">
                    <path d="M12 12C14.2 12 16 10.2 16 8C16 5.8 14.2 4 12 4C9.8 4 8 5.8 8 8C8 10.2 9.8 12 12 12Z"/>
                    <path d="M4 20C4.8 16.6 7.6 15 12 15C16.4 15 19.2 16.6 20 20"/>
                    <path d="M17 4L19 6L22 2"/>
                </svg>
            </div>
            <div>
                <span>Active Trainers</span>
                <h2><?php echo $activeTrainers; ?></h2>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-orange">
                <svg viewBox="0 0 24 24">
                    <path d="M12 3L14.8 8.7L21 9.6L16.5 14L17.6 20.2L12 17.3L6.4 20.2L7.5 14L3 9.6L9.2 8.7L12 3Z"/>
                </svg>
            </div>
            <div>
                <span>Rating</span>
                <h2><?php echo e(formatRating($avgRating)); ?></h2>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-blue">
                <svg viewBox="0 0 24 24">
                    <path d="M7 3V6"/>
                    <path d="M17 3V6"/>
                    <path d="M4 9H20"/>
                    <path d="M6 5H18C19.1 5 20 5.9 20 7V18C20 19.1 19.1 20 18 20H6C4.9 20 4 19.1 4 18V7C4 5.9 4.9 5 6 5Z"/>
                    <path d="M8 13H10"/>
                    <path d="M13 13H16"/>
                </svg>
            </div>
            <div>
                <span>Sessions Assigned</span>
                <h2><?php echo $sessionsAssigned; ?></h2>
            </div>
        </div>
    </div>

    <div class="trainer-content-grid">

        <div class="trainer-main-content">

            <div class="panel trainer-panel">
                <div class="panel-header trainer-panel-header">
                    <div class="panel-title">
                        <h2>All Trainers</h2>
                    </div>

                    <form method="GET" class="filter-form trainer-filters">
                        <div class="filter-search">
                            <input 
                                type="text" 
                                name="search" 
                                placeholder="Search trainers..."
                                value="<?php echo e($search); ?>"
                            >

                            <button type="submit" aria-label="Search trainers">
                                <svg viewBox="0 0 24 24">
                                    <path d="M21 21L16.65 16.65"/>
                                    <circle cx="11" cy="11" r="7"/>
                                </svg>
                            </button>
                        </div>

                        <select name="status">
                            <option value="">Status: All</option>
                            <option value="active" <?php if($statusFilter == 'active') echo 'selected'; ?>>Active</option>
                            <option value="inactive" <?php if($statusFilter == 'inactive') echo 'selected'; ?>>Inactive</option>
                        </select>

                        <select name="expertise">
                            <option value="">Expertise: All</option>
                            <?php while($expertiseRow = mysqli_fetch_assoc($expertiseOptions)) { ?>
                                <option 
                                    value="<?php echo e($expertiseRow['expertise']); ?>"
                                    <?php if($expertiseFilter == $expertiseRow['expertise']) echo 'selected'; ?>
                                >
                                    <?php echo e(shortText($expertiseRow['expertise'], 30)); ?>
                                </option>
                            <?php } ?>
                        </select>

                        <button type="submit" class="filter-submit-btn">Filter</button>
                        <a href="trainer.php" class="reset-filter">Reset</a>
                    </form>
                </div>

                <div class="table-wrap">
                    <table class="trainer-table compact-trainer-table trainer-payment-document-table">
                        <thead>
                            <tr>
                                <th>Trainer</th>
                                <th>Expertise</th>
                                <th>Contact</th>
                                <th>Rating</th>
                                <th>Sessions</th>
                                <th>Payment</th>
                                <th>Documents</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($totalRows == 0) { ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state"><?php echo $hasTrainerFilters ? 'No trainers match the selected search or filters.' : 'No trainers have been added yet.'; ?></div>
                                    </td>
                                </tr>
                            <?php } ?>

                            <?php foreach($trainerRows as $row) { 
                                $safeID = preg_replace('/[^A-Za-z0-9]/', '', $row['trainerID']);
                                $modalID = 'trainerDetail' . $safeID;
                                $editID = 'editTrainer' . $safeID;
                                $trainerDocuments = $documentsByTrainer[$row['trainerID']] ?? [];
                                $expertiseText = !empty($row['expertise']) ? $row['expertise'] : 'No expertise added.';
                                $expertiseJson = json_encode($expertiseText, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            ?>
                                <tr>
                                    <td>
                                        <div class="trainer-name-cell compact-trainer-name-cell">
                                            <div class="trainer-photo">
                                                <?php if (!empty($row['trainerPic'])) { ?>
                                                    <img src="<?php echo e($row['trainerPic']); ?>" alt="Trainer">
                                                <?php } else { ?>
                                                    <div class="trainer-photo-fallback">
                                                        <?php echo strtoupper(substr($row['trainerName'], 0, 1)); ?>
                                                    </div>
                                                <?php } ?>
                                            </div>

                                            <div>
                                                <strong><?php echo e($row['trainerName']); ?></strong>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <button type="button" class="expertise-open-btn" onclick='openTextModal("Expertise", <?php echo $expertiseJson; ?>)'>
                                            <span><?php echo e(shortText($expertiseText, 54)); ?></span>
                                            <small>Click to view full expertise</small>
                                        </button>
                                    </td>

                                    <td>
                                        <div class="contact-cell">
                                            <span><?php echo !empty($row['trainerEmail']) ? e($row['trainerEmail']) : '-'; ?></span>
                                            <small><?php echo !empty($row['trainerPhoneNo']) ? e($row['trainerPhoneNo']) : '-'; ?></small>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="rating-cell">
                                            <strong><?php echo e(formatRating($row['displayRating'])); ?></strong>
                                            <?php echo renderStars($row['displayRating']); ?>
                                        </div>
                                    </td>

                                    <td><strong><?php echo e($row['sessionCount']); ?></strong></td>

                                    <td>
                                        <span class="status-badge payment-<?php echo e(strtolower($row['paymentStatus'] ?? 'unpaid')); ?>">
                                            <?php echo e(paymentStatusLabel($row['paymentStatus'] ?? 'unpaid')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="trainer-document-count">
                                            <?php echo (int)($row['documentCount'] ?? 0); ?> document<?php echo ((int)($row['documentCount'] ?? 0) === 1) ? '' : 's'; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="action-group">
                                            <button type="button" class="action-btn" onclick="openDetailModal('<?php echo $modalID; ?>')" title="View trainer">
                                                <svg viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </button>
                                            <form method="POST" class="delete-form" data-delete-title="Delete Trainer" data-delete-message="Are you sure you want to delete this trainer?">
                                                <input type="hidden" name="action" value="delete_trainer">
                                                <input type="hidden" name="trainerID" value="<?php echo e($row['trainerID']); ?>">
                                                <button type="submit" class="action-btn danger-action" title="Delete trainer">
                                                    <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <span>
                        Showing <?php echo $totalRows == 0 ? 0 : $offset + 1; ?> to 
                        <?php echo min($offset + $limit, $totalRows); ?> of 
                        <?php echo $totalRows; ?> trainers
                    </span>

                    <div class="pagination">
                        <?php foreach (compactTrainerPaginationItems($page, $totalPages) as $pageItem) { ?>
                            <?php if ($pageItem === 'ellipsis') { ?>
                                <span class="pagination-ellipsis" aria-hidden="true">…</span>
                            <?php } else { ?>
                                <a
                                    class="<?php if((int)$pageItem === $page) echo 'active'; ?>"
                                    href="trainer.php?page=<?php echo (int)$pageItem; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&expertise=<?php echo urlencode($expertiseFilter); ?>"
                                ><?php echo (int)$pageItem; ?></a>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            </div>

        </div>

        <div class="top-rated-panel">
            <div class="side-panel-header">
                <div class="side-title">
                    <div class="side-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 3L14.8 8.7L21 9.6L16.5 14L17.6 20.2L12 17.3L6.4 20.2L7.5 14L3 9.6L9.2 8.7L12 3Z"/>
                        </svg>
                    </div>
                    <h3>Top Rated Trainers</h3>
                </div>
            </div>

            <div class="top-trainer-list">
                <?php if (!empty($topRatedRows)) { ?>
                    <?php foreach ($topRatedRows as $rankIndex => $top) {
                        $rank = $rankIndex + 1;
                        $topSafeID = preg_replace('/[^A-Za-z0-9]/', '', (string)$top['trainerID']);
                        $topModalID = 'trainerDetail' . $topSafeID;
                    ?>
                        <button type="button" class="top-trainer-item" onclick="openDetailModal('<?php echo e($topModalID); ?>')" aria-label="View <?php echo e($top['trainerName']); ?> details">
                            <div class="rank-badge rank-<?php echo $rank; ?>"><?php echo $rank; ?></div>
                            <div class="top-trainer-photo">
                                <?php if (!empty($top['trainerPic'])) { ?>
                                    <img src="<?php echo e($top['trainerPic']); ?>" alt="Trainer">
                                <?php } else { ?>
                                    <div class="top-trainer-avatar"><?php echo e(strtoupper(substr((string)$top['trainerName'], 0, 1))); ?></div>
                                <?php } ?>
                            </div>
                            <div class="top-trainer-info">
                                <strong><?php echo e($top['trainerName']); ?></strong>
                                <span><?php echo e(shortText($top['expertise'], 38)); ?></span>
                            </div>
                            <div class="top-rating">
                                <strong><?php echo e(formatRating($top['displayRating'])); ?></strong>
                                <?php echo renderStars($top['displayRating']); ?>
                            </div>
                        </button>
                    <?php } ?>
                <?php } else { ?>
                    <div class="top-rated-empty">No trainer rating data yet.</div>
                <?php } ?>
            </div>
        </div>

    </div>
</div>

<?php
$currentTrainerIds = array_fill_keys(array_map(static fn($trainerRow) => (string)$trainerRow['trainerID'], $trainerRows), true);
foreach ($topRatedRows as $top) {
    if (isset($currentTrainerIds[(string)$top['trainerID']])) continue;
    $topSafeID = preg_replace('/[^A-Za-z0-9]/', '', (string)$top['trainerID']);
    $topModalID = 'trainerDetail' . $topSafeID;
?>
<div class="modal" id="<?php echo e($topModalID); ?>" aria-hidden="true">
    <div class="modal-box trainer-detail-modal">
        <button type="button" class="close-btn" onclick="closeDetailModal('<?php echo e($topModalID); ?>')">×</button>
        <div class="trainer-modal-header">
            <div class="trainer-modal-photo">
                <?php if (!empty($top['trainerPic'])) { ?>
                    <img src="<?php echo e($top['trainerPic']); ?>" alt="Trainer">
                <?php } else { ?>
                    <div class="trainer-modal-avatar"><?php echo e(strtoupper(substr((string)$top['trainerName'], 0, 1))); ?></div>
                <?php } ?>
            </div>
            <div>
                <span>Trainer Details</span>
                <h2><?php echo e($top['trainerName']); ?></h2>
                <p><?php echo !empty($top['expertise']) ? e($top['expertise']) : 'No expertise added.'; ?></p>
            </div>
        </div>
        <div class="trainer-popup-body">
            <div class="detail-grid compact-detail-grid">
                <div class="detail-box"><span>Status</span><strong><?php echo e(ucfirst($top['status'] ?? 'active')); ?></strong></div>
                <div class="detail-box"><span>Payment Status</span><strong><?php echo e(paymentStatusLabel($top['paymentStatus'] ?? 'unpaid')); ?></strong></div>
                <div class="detail-box"><span>IC Number</span><strong><?php echo !empty($top['trainerIC']) ? e($top['trainerIC']) : '-'; ?></strong></div>
                <div class="detail-box"><span>Rating</span><strong><?php echo e(formatRating($top['displayRating'])); ?> / 5</strong></div>
                <div class="detail-box"><span>Email</span><strong><?php echo !empty($top['trainerEmail']) ? e($top['trainerEmail']) : '-'; ?></strong></div>
                <div class="detail-box"><span>Phone Number</span><strong><?php echo !empty($top['trainerPhoneNo']) ? e($top['trainerPhoneNo']) : '-'; ?></strong></div>
                <div class="detail-box detail-full"><span>Expertise</span><strong><?php echo !empty($top['expertise']) ? e($top['expertise']) : '-'; ?></strong></div>
            </div>
            <div class="modal-actions single-close-row">
                <button type="button" class="cancel-btn" onclick="closeDetailModal('<?php echo e($topModalID); ?>')">Close</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php foreach($trainerRows as $row) { 
    $safeID = preg_replace('/[^A-Za-z0-9]/', '', $row['trainerID']);
    $modalID = 'trainerDetail' . $safeID;
    $editID = 'editTrainer' . $safeID;
    $trainerDocuments = $documentsByTrainer[$row['trainerID']] ?? [];
?>
    <div class="modal" id="<?php echo $modalID; ?>">
        <div class="modal-box trainer-detail-modal">
            <button type="button" class="close-btn" onclick="closeDetailModal('<?php echo $modalID; ?>')">×</button>

            <div class="trainer-modal-header">
                <div class="trainer-modal-photo">
                    <?php if (!empty($row['trainerPic'])) { ?>
                        <img src="<?php echo e($row['trainerPic']); ?>" alt="Trainer">
                    <?php } else { ?>
                        <div class="trainer-modal-avatar">
                            <?php echo strtoupper(substr($row['trainerName'], 0, 1)); ?>
                        </div>
                    <?php } ?>
                </div>

                <div>
                    <span>Trainer Details</span>
                    <h2><?php echo e($row['trainerName']); ?></h2>
                    <p><?php echo !empty($row['expertise']) ? e($row['expertise']) : 'No expertise added.'; ?></p>
                </div>
            </div>

            <div class="trainer-popup-body">
                <div class="detail-grid compact-detail-grid">
                    <div class="detail-box">
                        <span>Status</span>
                        <strong><?php echo e(ucfirst($row['status'])); ?></strong>
                    </div>

                    <div class="detail-box">
                        <span>Payment Status</span>
                        <strong><?php echo e(paymentStatusLabel($row['paymentStatus'] ?? 'unpaid')); ?></strong>
                    </div>

                    <div class="detail-box">
                        <span>IC Number</span>
                        <strong><?php echo !empty($row['trainerIC']) ? e($row['trainerIC']) : '-'; ?></strong>
                    </div>

                    <div class="detail-box">
                        <span>Rating</span>
                        <strong><?php echo e(formatRating($row['displayRating'])); ?> / 5</strong>
                    </div>

                    <div class="detail-box">
                        <span>Email</span>
                        <strong><?php echo !empty($row['trainerEmail']) ? e($row['trainerEmail']) : '-'; ?></strong>
                    </div>

                    <div class="detail-box">
                        <span>Phone Number</span>
                        <strong><?php echo !empty($row['trainerPhoneNo']) ? e($row['trainerPhoneNo']) : '-'; ?></strong>
                    </div>

                    <div class="detail-box detail-full">
                        <span>Expertise</span>
                        <strong><?php echo !empty($row['expertise']) ? e($row['expertise']) : '-'; ?></strong>
                    </div>
                </div>

                <div class="session-card arranged-session-card">
                    <div class="session-card-header">
                        <div>
                            <h4>Assigned Sessions</h4>
                            <p>Sessions handled by this trainer.</p>
                        </div>
                        <strong><?php echo e($row['sessionCount']); ?></strong>
                    </div>

                    <?php if (!empty($row['sessionList'])) { ?>
                        <div class="trainer-session-grid">
                            <?php foreach (explode('||', $row['sessionList']) as $sessionText) { ?>
                                <?php if (trim($sessionText) !== '') { 
                                    $sessionParts = array_pad(array_map('trim', explode(' • ', $sessionText)), 5, '');
                                    $sessionName = $sessionParts[0] ?: 'Session';
                                    $trainingName = $sessionParts[1] ?: 'Training';
                                    $sessionDate = $sessionParts[2] ?: '-';
                                    $sessionTime = $sessionParts[3] ?: '-';
                                    $sessionLocation = $sessionParts[4] ?: '-';
                                ?>
                                    <div class="trainer-session-card">
                                        <div class="trainer-session-topline">
                                            <span>Session</span>
                                            <strong><?php echo e($sessionDate); ?></strong>
                                        </div>
                                        <h5><?php echo e($trainingName); ?></h5>
                                        <p><?php echo e($sessionName); ?></p>
                                        <div class="trainer-session-meta">
                                            <em><?php echo e($sessionTime); ?></em>
                                            <em><?php echo e($sessionLocation); ?></em>
                                        </div>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="empty-state session-empty">No session assigned yet.</div>
                    <?php } ?>
                </div>

                <div class="trainer-document-card">
                    <div class="trainer-document-card-head">
                        <div>
                            <span>Trainer Files</span>
                            <h4>Documents</h4>
                        </div>
                        <strong><?php echo count($trainerDocuments); ?> file<?php echo count($trainerDocuments) === 1 ? '' : 's'; ?></strong>
                    </div>

                    <?php if (empty($trainerDocuments)) { ?>
                        <div class="empty-state document-empty-note">No document has been uploaded for this trainer yet.</div>
                    <?php } else { ?>
                        <div class="trainer-document-list">
                            <?php foreach ($trainerDocuments as $document) { ?>
                                <div class="trainer-document-row">
                                    <div class="trainer-document-icon">📄</div>
                                    <div class="trainer-document-info">
                                        <strong><?php echo e($document['title']); ?></strong>
                                        <span><?php echo e(documentTypeLabel($document['document_type'] ?? '')); ?><?php if (!empty($document['sentByStaffName'])) { ?> · Sent by <?php echo e($document['sentByStaffName']); ?><?php } ?></span>
                                    </div>
                                    <?php if (!empty($document['file_path'])) { ?>
                                        <a href="<?php echo e($document['file_path']); ?>" target="_blank" class="file-btn">Open</a>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

                <div class="modal-actions trainer-detail-actions">
                    <button type="button" class="cancel-btn" onclick="closeDetailModal('<?php echo $modalID; ?>')">Cancel</button>

                    <button type="button" class="primary-btn" onclick="closeDetailModal('<?php echo $modalID; ?>'); openEditModal('<?php echo $editID; ?>');">
                        Update
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="<?php echo $editID; ?>">
        <div class="modal-box form-modal trainer-update-modal">
            <button type="button" class="close-btn" onclick="closeEditModal('<?php echo $editID; ?>')">×</button>

            <div class="form-modal-header">
                <span>Update Record</span>
                <h2>Update Trainer & Documents</h2>
                <p>Update trainer information and manage files shared with this trainer.</p>
            </div>

            <div class="update-modal-body">
                <section class="update-section">
                    <div class="update-section-header">
                        <div>
                            <span>Trainer Information</span>
                            <h3>Edit Trainer Details</h3>
                            <p>Use this section only for trainer profile information.</p>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="modal-form clean-update-form">
                        <input type="hidden" name="action" value="update_trainer">
                        <input type="hidden" name="trainerID" value="<?php echo e($row['trainerID']); ?>">
                        <input type="hidden" name="oldTrainerPic" value="<?php echo e($row['trainerPic']); ?>">

                        <div class="form-grid">
                            <div>
                                <label>Trainer ID</label>
                                <input type="text" value="<?php echo e($row['trainerID']); ?>" disabled>
                            </div>

                            <div>
                                <label>Trainer Name</label>
                                <input type="text" name="trainerName" value="<?php echo e($row['trainerName']); ?>" required>
                            </div>

                            <div>
                                <label>IC Number</label>
                                <input type="text" name="trainerIC" value="<?php echo e($row['trainerIC']); ?>">
                            </div>

                            <div>
                                <label>Email</label>
                                <input type="email" name="trainerEmail" value="<?php echo e($row['trainerEmail']); ?>">
                            </div>

                            <div>
                                <label>Phone Number</label>
                                <input type="text" name="trainerPhoneNo" value="<?php echo e($row['trainerPhoneNo']); ?>">
                            </div>

                            <div>
                                <label>Status</label>
                                <select name="status" required>
                                    <option value="active" <?php if($row['status'] == 'active') echo 'selected'; ?>>Active</option>
                                    <option value="inactive" <?php if($row['status'] == 'inactive') echo 'selected'; ?>>Inactive</option>
                                </select>
                            </div>

                            <div>
                                <label>Payment Status</label>
                                <select name="paymentStatus" required>
                                    <option value="unpaid" <?php if(($row['paymentStatus'] ?? 'unpaid') == 'unpaid') echo 'selected'; ?>>Unpaid</option>
                                    <option value="pending" <?php if(($row['paymentStatus'] ?? 'unpaid') == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="paid" <?php if(($row['paymentStatus'] ?? 'unpaid') == 'paid') echo 'selected'; ?>>Paid</option>
                                </select>
                            </div>

                            <div class="form-full">
                                <label>Trainer Picture</label>
                                <input type="file" name="trainerPic" accept=".jpg,.jpeg,.png">

                                <?php if (!empty($row['trainerPic'])) { ?>
                                    <a href="<?php echo e($row['trainerPic']); ?>" target="_blank" class="current-file-link">
                                        View current picture
                                    </a>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="form-full">
                            <label>Expertise</label>
                            <textarea name="expertise" rows="4"><?php echo e($row['expertise']); ?></textarea>
                        </div>

                        <div class="section-actions">
                            <button type="submit" class="primary-btn">Save Trainer Info</button>
                        </div>
                    </form>
                </section>

                <section class="update-section trainer-document-update-card">
                    <div class="update-section-header">
                        <div>
                            <span>Trainer Documents</span>
                            <h3>Manage Documents</h3>
                            <p>Upload invitation letters, supporting documents, certificates or other files for this trainer.</p>
                        </div>
                    </div>

                    <?php if (!empty($trainerDocuments)) { ?>
                        <div class="trainer-document-manager-list">
                            <?php foreach ($trainerDocuments as $document) { ?>
                                <article class="trainer-document-manager-item">
                                    <div class="trainer-document-manager-main">
                                        <strong><?php echo e($document['title']); ?></strong>
                                        <span><?php echo e(documentTypeLabel($document['document_type'] ?? '')); ?></span>
                                        <?php if (!empty($document['description'])) { ?><p><?php echo e($document['description']); ?></p><?php } ?>
                                    </div>
                                    <div class="trainer-document-manager-actions">
                                        <?php if (!empty($document['file_path'])) { ?><a href="<?php echo e($document['file_path']); ?>" target="_blank" class="file-btn">Open</a><?php } ?>
                                        <form method="POST" class="delete-form" data-delete-title="Delete Document" data-delete-message="Delete this trainer document?">
                                            <input type="hidden" name="action" value="delete_document">
                                            <input type="hidden" name="trainerID" value="<?php echo e($row['trainerID']); ?>">
                                            <input type="hidden" name="documentID" value="<?php echo (int)$document['document_id']; ?>">
                                            <button type="submit" class="danger-outline-btn">Delete</button>
                                        </form>
                                    </div>
                                </article>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="empty-state document-empty-note">No document has been uploaded for this trainer yet.</div>
                    <?php } ?>

                    <form method="POST" enctype="multipart/form-data" class="modal-form clean-update-form trainer-document-add-form">
                        <input type="hidden" name="action" value="insert_document">
                        <input type="hidden" name="trainerID" value="<?php echo e($row['trainerID']); ?>">
                        <div class="form-grid">
                            <div>
                                <label>Document Title <span class="required-star">*</span></label>
                                <input type="text" name="documentTitle" placeholder="e.g. Trainer Appointment Letter" required>
                            </div>
                            <div>
                                <label>Document Type</label>
                                <select name="documentType">
                                    <option value="invitation_letter">Invitation Letter</option>
                                    <option value="supporting_document">Supporting Document</option>
                                    <option value="certificate">Certificate</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-full">
                                <label>Description</label>
                                <textarea name="documentDescription" rows="3" placeholder="Optional description"></textarea>
                            </div>
                            <div class="form-full">
                                <label>Document File <span class="required-star">*</span></label>
                                <input type="file" name="documentFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            </div>
                        </div>
                        <div class="section-actions">
                            <button type="submit" class="primary-btn">Add Document</button>
                        </div>
                    </form>
                </section>

            <div class="modal-actions single-close-row">
                <button type="button" class="cancel-btn" onclick="closeEditModal('<?php echo $editID; ?>')">Close</button>
            </div>
        </div>
    </div>
</div>

    <?php } ?>

<div class="modal" id="addTrainerModal">
    <div class="modal-box form-modal">
        <button type="button" class="close-btn" onclick="closeAddModal()">×</button>

        <div class="form-modal-header">
            <span>New Trainer</span>
            <h2>Add New Trainer</h2>
            <p>Trainer ID is generated automatically. Rating will appear after participants submit the form.</p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="modal-form">
            <input type="hidden" name="action" value="add_trainer">
            <input type="hidden" name="status" value="active">
            <input type="hidden" name="paymentStatus" value="unpaid">

            <div class="form-grid">
                <div>
                    <label>Trainer Name</label>
                    <input type="text" name="trainerName" placeholder="Enter trainer name" required>
                </div>

                <div>
                    <label>IC Number</label>
                    <input type="text" name="trainerIC" placeholder="Enter IC number">
                </div>

                <div>
                    <label>Email</label>
                    <input type="email" name="trainerEmail" placeholder="trainer@email.com">
                </div>

                <div>
                    <label>Phone Number</label>
                    <input type="text" name="trainerPhoneNo" placeholder="0123456789">
                </div>


                <div>
                    <label>Trainer Picture</label>
                    <input type="file" name="trainerPic" accept=".jpg,.jpeg,.png">
                </div>
            </div>

            <div class="form-full">
                <label>Expertise</label>
                <textarea name="expertise" rows="4" placeholder="Example: Leadership, communication, project management..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="primary-btn">Save Trainer</button>
            </div>
        </form>
    </div>
</div>


<div class="modal text-view-modal" id="textViewModal">
    <div class="modal-box text-view-box">
        <button type="button" class="close-btn" onclick="closeTextModal()">×</button>
        <div class="form-modal-header">
            <span>Full Details</span>
            <h2 id="textModalTitle">Details</h2>
        </div>
        <div class="text-view-body" id="textModalBody"></div>
        <div class="modal-actions single-close-row">
            <button type="button" class="cancel-btn" onclick="closeTextModal()">Close</button>
        </div>
    </div>
</div>

<div class="delete-confirm-backdrop" id="deleteConfirmPopup">
    <div class="delete-confirm-box">
        <div class="delete-confirm-icon">!</div>
        <h3 id="deleteConfirmTitle">Confirm Delete</h3>
        <p id="deleteConfirmMessage">Are you sure?</p>
        <div class="delete-confirm-actions">
            <button type="button" class="cancel-btn" onclick="closeDeleteConfirm()">Cancel</button>
            <button type="button" class="danger-btn" onclick="confirmDeleteAction()">Yes</button>
        </div>
    </div>
</div>

<script>
function trainerModalElement(idOrElement) {
    return typeof idOrElement === 'string' ? document.getElementById(idOrElement) : idOrElement;
}

function refreshTrainerModalState() {
    const modalOpen = !!document.querySelector('.page-trainer .modal.is-open');
    const deleteOpen = document.getElementById('deleteConfirmPopup')?.classList.contains('is-open') || false;
    const flashOpen = document.getElementById('flashPopup') && window.getComputedStyle(document.getElementById('flashPopup')).display !== 'none';
    document.body.classList.toggle('modal-open', modalOpen || deleteOpen || flashOpen);
}

function setTrainerModal(idOrElement, open) {
    const modal = trainerModalElement(idOrElement);
    if (!modal) return;
    modal.hidden = !open;
    modal.classList.toggle('is-open', open);
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (open) {
        modal.scrollTop = 0;
        const box = modal.querySelector('.modal-box');
        if (box) box.scrollTop = 0;
    }
    refreshTrainerModalState();
}

function openAddModal() { setTrainerModal('addTrainerModal', true); }
function closeAddModal() { setTrainerModal('addTrainerModal', false); }
function openDetailModal(id) { setTrainerModal(id, true); }
function closeDetailModal(id) { setTrainerModal(id, false); }
function openEditModal(id) { setTrainerModal(id, true); }
function closeEditModal(id) { setTrainerModal(id, false); }

function closeFlashPopup() {
    const popup = document.getElementById('flashPopup');
    if (popup) popup.style.display = 'none';
    refreshTrainerModalState();
}

function openTextModal(title, text) {
    const titleEl = document.getElementById('textModalTitle');
    const bodyEl = document.getElementById('textModalBody');
    if (titleEl) titleEl.textContent = title || 'Details';
    if (bodyEl) bodyEl.textContent = text || '-';
    setTrainerModal('textViewModal', true);
}
function closeTextModal() { setTrainerModal('textViewModal', false); }

let pendingDeleteForm = null;
function openDeleteConfirm(form) {
    pendingDeleteForm = form;
    const popup = document.getElementById('deleteConfirmPopup');
    const title = document.getElementById('deleteConfirmTitle');
    const message = document.getElementById('deleteConfirmMessage');
    if (title) title.textContent = form.dataset.deleteTitle || 'Confirm Delete';
    if (message) message.textContent = form.dataset.deleteMessage || 'Are you sure you want to delete this record?';
    if (popup) {
        popup.classList.add('is-open');
        popup.setAttribute('aria-hidden', 'false');
    }
    refreshTrainerModalState();
}
function closeDeleteConfirm() {
    pendingDeleteForm = null;
    const popup = document.getElementById('deleteConfirmPopup');
    if (popup) {
        popup.classList.remove('is-open');
        popup.setAttribute('aria-hidden', 'true');
    }
    refreshTrainerModalState();
}
function confirmDeleteAction() {
    if (!pendingDeleteForm) return;
    const form = pendingDeleteForm;
    pendingDeleteForm = null;
    form.dataset.skipDeleteConfirm = '1';
    form.submit();
}

function setupActionConfirmations() {
    document.querySelectorAll('form.delete-form').forEach(function(form) {
        if (form.dataset.trainerConfirmReady === '1') return;
        form.dataset.trainerConfirmReady = '1';
        form.addEventListener('submit', function(event) {
            if (form.dataset.skipDeleteConfirm === '1') return;
            event.preventDefault();
            event.stopPropagation();
            openDeleteConfirm(form);
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Keep every trainer popup as a direct child of <body>. This prevents a
    // popup from being trapped by a table/card/update modal stacking context
    // and guarantees Add, Details, Expertise and Top Rated use the same layer.
    const trainerModals = Array.from(document.querySelectorAll('.page-trainer .modal'));
    trainerModals.forEach(function(modal) {
        if (modal.parentElement !== document.body) document.body.appendChild(modal);
        modal.hidden = true;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    });
    const deletePopup = document.getElementById('deleteConfirmPopup');
    if (deletePopup) {
        if (deletePopup.parentElement !== document.body) document.body.appendChild(deletePopup);
        deletePopup.classList.remove('is-open');
        deletePopup.setAttribute('aria-hidden', 'true');
    }
    setupActionConfirmations();
    refreshTrainerModalState();
});

document.addEventListener('click', function(event) {
    const modal = event.target.closest('.page-trainer .modal.is-open');
    if (modal && event.target === modal) setTrainerModal(modal, false);
    if (event.target.id === 'deleteConfirmPopup') closeDeleteConfirm();
});

document.addEventListener('keydown', function(event) {
    if (event.key !== 'Escape') return;
    const deletePopup = document.getElementById('deleteConfirmPopup');
    if (deletePopup?.classList.contains('is-open')) {
        closeDeleteConfirm();
        return;
    }
    document.querySelectorAll('.page-trainer .modal.is-open').forEach(function(modal) {
        setTrainerModal(modal, false);
    });
});
</script>

</body>
</html>
