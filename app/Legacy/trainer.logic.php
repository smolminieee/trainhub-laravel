<?php
include __DIR__ . '/config/session.php';
include __DIR__ . '/config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* Always use the TrainHub schema configured by Laravel DB_DATABASE. */
if (!mysqli_select_db($conn, TRAINHUB_DATABASE_NAME)) {
    throw new RuntimeException('Unable to select the configured TrainHub database.');
}


function nullableValue($value) {
    if ($value === null) {
        return null;
    }

    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function tableExists($conn, $tableName) {
    static $cache = [];
    $key = strtolower((string)$tableName);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$safeTable'");
    return $cache[$key] = (bool)($result && mysqli_num_rows($result) > 0);
}

function columnExists($conn, $tableName, $columnName) {
    static $cache = [];
    $key = strtolower((string)$tableName . '.' . (string)$columnName);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $safeTable = str_replace('`', '', $tableName);
    $safeColumn = mysqli_real_escape_string($conn, $columnName);

    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    return $cache[$key] = (bool)($result && mysqli_num_rows($result) > 0);
}

function setAuditStaff($conn) {
    $staffID = $_SESSION['staffID'] ?? $_SESSION['staff_id'] ?? '';
    $staffName = $_SESSION['staffName'] ?? $_SESSION['staff_name'] ?? $staffID;

    if ($staffID === '') {
        header('Location: login.php');
        exit();
    }

    $stmt = mysqli_prepare($conn, "SET @current_staff_id = ?, @current_staff_name = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $staffID, $staffName);
    mysqli_stmt_execute($stmt);
}

function generateNextID($conn, $tableName, $columnName, $prefix) {
    $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', (string)$tableName);
    $safeColumn = preg_replace('/[^A-Za-z0-9_]/', '', (string)$columnName);
    $prefixLength = strlen($prefix);

    $stmt = mysqli_prepare($conn, "
        SELECT `$safeColumn` AS current_id
        FROM `$safeTable`
        WHERE LEFT(`$safeColumn`, ?) = ?
    ");
    mysqli_stmt_bind_param($stmt, 'is', $prefixLength, $prefix);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $maxNo = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        if (preg_match('/(\d+)$/', (string)$row['current_id'], $match)) {
            $maxNo = max($maxNo, (int)$match[1]);
        }
    }

    return $prefix . str_pad($maxNo + 1, 4, '0', STR_PAD_LEFT);
}

function resolvePublicFilePath($relativePath) {
    $relativePath = nullableValue($relativePath);
    if ($relativePath === null) return null;

    $clean = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (function_exists('public_path')) {
        return public_path($clean);
    }

    return $clean;
}

function uploadFile($inputName, $folder, $allowedExt, $oldFile = null) {
    $oldFile = nullableValue($oldFile);

    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $oldFile;
    }

    if ($_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed. Please try again.');
    }

    $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt, true)) {
        throw new Exception('Invalid file type. Allowed file type: ' . implode(', ', $allowedExt));
    }

    // Store browser-accessible uploads under Laravel's public directory while
    // keeping the relative path in the database (uploads/...).
    $relativeFolder = trim(str_replace('\\', '/', $folder), '/');
    $diskFolder = function_exists('public_path') ? public_path($relativeFolder) : $relativeFolder;

    if (!is_dir($diskFolder) && !mkdir($diskFolder, 0777, true) && !is_dir($diskFolder)) {
        throw new Exception('Unable to create the upload folder.');
    }

    /*
       Important for CHECK constraints:
       - trainer.trainerPic must end with .jpg, .jpeg or .png
       - trainer_document file uploads allow pdf, doc, docx and common image formats
    */
    $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($_FILES[$inputName]['name'], PATHINFO_FILENAME));
    $fileName = $inputName . '_' . time() . '_' . uniqid() . '_' . $safeBaseName . '.' . $ext;
    $relativeFile = $relativeFolder . '/' . $fileName;
    $targetFile = rtrim($diskFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $targetFile)) {
        throw new Exception('Failed to save uploaded file.');
    }

    // Delete the replaced file only when it belongs to this application's public uploads.
    if ($oldFile !== null && $oldFile !== $relativeFile) {
        $oldDiskFile = resolvePublicFilePath($oldFile);
        if ($oldDiskFile && is_file($oldDiskFile)) {
            @unlink($oldDiskFile);
        }
    }

    return $relativeFile;
}

function formatRating($rating) {
    $rating = max(0, min(5, (float)$rating));

    if (abs($rating - round($rating)) < 0.001) {
        return number_format($rating, 0);
    }

    if (abs(($rating * 10) - round($rating * 10)) < 0.001) {
        return number_format($rating, 1);
    }

    return number_format($rating, 2);
}

function renderStars($rating) {
    $rating = max(0, min(5, (float)$rating));
    $ratingText = formatRating($rating);

    $html = '<span class="stars fractional-stars" aria-label="' . e($ratingText) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $fillPercent = max(0, min(100, ($rating - ($i - 1)) * 100));
        $fillPercent = round($fillPercent, 2);
        $html .= '<span class="rating-star" style="--star-fill:' . $fillPercent . '%" aria-hidden="true">★</span>';
    }
    $html .= '</span>';
    return $html;
}

function shortText($text, $length = 45) {
    $text = trim((string)$text);

    if ($text === '') {
        return '-';
    }

    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length) . '...';
}

function documentTypeLabel($type) {
    $type = trim((string)$type);
    if ($type === '') return 'Document';
    return ucwords(str_replace(['_', '-'], ' ', $type));
}


function normalizeTrainerStatus($status) {
    $status = strtolower(trim((string)$status));
    return in_array($status, ['active', 'inactive'], true) ? $status : 'active';
}

function normalizePaymentStatus($status) {
    $status = strtolower(trim((string)$status));
    return in_array($status, ['unpaid', 'pending', 'paid'], true) ? $status : 'unpaid';
}

function paymentStatusLabel($status) {
    return ucfirst(normalizePaymentStatus($status));
}

function compactTrainerPaginationItems(int $currentPage, int $totalPages, int $radius = 2): array {
    if ($totalPages <= 1) {
        return [1];
    }

    $pages = [1, $totalPages];
    for ($page = max(1, $currentPage - $radius); $page <= min($totalPages, $currentPage + $radius); $page++) {
        $pages[] = $page;
    }

    $pages = array_values(array_unique($pages));
    sort($pages);

    $items = [];
    $previous = null;
    foreach ($pages as $page) {
        if ($previous !== null && $page > $previous + 1) {
            $items[] = 'ellipsis';
        }
        $items[] = $page;
        $previous = $page;
    }
    return $items;
}

function displayFileName($filePath) {
    $filePath = nullableValue($filePath);

    if ($filePath === null) {
        return 'No file';
    }

    return basename($filePath);
}

$trainerPicDir = 'uploads/trainer';
$trainerDocumentDir = 'uploads/trainer_documents';

$trainerPicDiskDir = function_exists('public_path') ? public_path($trainerPicDir) : $trainerPicDir;
$trainerDocumentDiskDir = function_exists('public_path') ? public_path($trainerDocumentDir) : $trainerDocumentDir;

if (!is_dir($trainerPicDiskDir)) {
    @mkdir($trainerPicDiskDir, 0777, true);
}

if (!is_dir($trainerDocumentDiskDir)) {
    @mkdir($trainerDocumentDiskDir, 0777, true);
}

$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'success';

unset($_SESSION['flash_message']);
unset($_SESSION['flash_type']);

/* =========================
   POST ACTIONS
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $inTransaction = false;

    try {
        setAuditStaff($conn);

        /* ADD TRAINER */
        if ($action === 'add_trainer') {
            $trainerID = generateNextID($conn, 'trainer', 'trainerID', 'TR');
            $trainerName = trim($_POST['trainerName'] ?? '');
            $trainerIC = nullableValue($_POST['trainerIC'] ?? null);
            $trainerEmail = nullableValue($_POST['trainerEmail'] ?? null);
            $trainerPhoneNo = nullableValue($_POST['trainerPhoneNo'] ?? null);
            $expertise = nullableValue($_POST['expertise'] ?? null);
            $status = 'active';
            $paymentStatus = 'unpaid';
            $averageRating = 0.00;

            if ($trainerName === '') {
                throw new Exception('Trainer name is required.');
            }

            $trainerPic = uploadFile('trainerPic', $trainerPicDir, ['jpg', 'jpeg', 'png'], null);

            $stmt = mysqli_prepare($conn, "
                INSERT INTO trainer
                (
                    trainerID,
                    trainerName,
                    trainerIC,
                    trainerEmail,
                    trainerPhoneNo,
                    expertise,
                    trainerPic,
                    averageRating,
                    status,
                    paymentStatus
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            mysqli_stmt_bind_param(
                $stmt,
                'sssssssdss',
                $trainerID,
                $trainerName,
                $trainerIC,
                $trainerEmail,
                $trainerPhoneNo,
                $expertise,
                $trainerPic,
                $averageRating,
                $status,
                $paymentStatus
            );

            mysqli_stmt_execute($stmt);

            $_SESSION['flash_message'] = 'Trainer added successfully.';
            $_SESSION['flash_type'] = 'success';

            header('Location: trainer.php');
            exit();
        }

        /* UPDATE TRAINER */
        if ($action === 'update_trainer') {
            $trainerID = $_POST['trainerID'] ?? '';
            $trainerName = trim($_POST['trainerName'] ?? '');
            $trainerIC = nullableValue($_POST['trainerIC'] ?? null);
            $trainerEmail = nullableValue($_POST['trainerEmail'] ?? null);
            $trainerPhoneNo = nullableValue($_POST['trainerPhoneNo'] ?? null);
            $expertise = nullableValue($_POST['expertise'] ?? null);
            $status = normalizeTrainerStatus($_POST['status'] ?? 'active');
            $paymentStatus = normalizePaymentStatus($_POST['paymentStatus'] ?? 'unpaid');
            $oldPic = nullableValue($_POST['oldTrainerPic'] ?? null);

            if ($trainerID === '') {
                throw new Exception('Trainer ID is missing.');
            }

            if ($trainerName === '') {
                throw new Exception('Trainer name is required.');
            }

            $trainerPic = uploadFile('trainerPic', $trainerPicDir, ['jpg', 'jpeg', 'png'], $oldPic);

            $stmt = mysqli_prepare($conn, "
                UPDATE trainer
                SET
                    trainerName = ?,
                    trainerIC = ?,
                    trainerEmail = ?,
                    trainerPhoneNo = ?,
                    expertise = ?,
                    trainerPic = ?,
                    status = ?,
                    paymentStatus = ?
                WHERE trainerID = ?
            ");

            mysqli_stmt_bind_param(
                $stmt,
                'sssssssss',
                $trainerName,
                $trainerIC,
                $trainerEmail,
                $trainerPhoneNo,
                $expertise,
                $trainerPic,
                $status,
                $paymentStatus,
                $trainerID
            );

            mysqli_stmt_execute($stmt);

            $_SESSION['flash_message'] = 'Trainer updated successfully.';
            $_SESSION['flash_type'] = 'success';

            header('Location: trainer.php');
            exit();
        }

        /* DELETE TRAINER */
        if ($action === 'delete_trainer') {
            $trainerID = trim($_POST['trainerID'] ?? '');

            if ($trainerID === '') {
                throw new Exception('Trainer ID is missing.');
            }

            mysqli_begin_transaction($conn);
            $inTransaction = true;

            $stmt = mysqli_prepare($conn, "SELECT trainerID FROM trainer WHERE trainerID = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $trainerID);
            mysqli_stmt_execute($stmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                throw new Exception('Trainer record was not found.');
            }

            /*
             * Preserve a trainer row when certificates or submitted feedback depend on
             * the trainer historically. Removing the active assignments still makes
             * the Delete action disappear from the normal active list.
             */
            $certificateTotal = 0;
            if (tableExists($conn, 'certificate')) {
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM certificate WHERE trainerID = ?");
                mysqli_stmt_bind_param($stmt, 's', $trainerID);
                mysqli_stmt_execute($stmt);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                $certificateTotal = (int)($row['total'] ?? 0);
            }

            $feedbackTotal = 0;
            $stmt = mysqli_prepare($conn, "
                SELECT COUNT(DISTINCT fr.responseID) AS total
                FROM session_trainer st
                INNER JOIN feedback_form ff ON ff.sessionID = st.sessionID
                INNER JOIN feedback_category fc ON fc.formID = ff.formID
                INNER JOIN feedback_question fq ON fq.categoryID = fc.categoryID
                INNER JOIN feedback_response fr ON fr.questionID = fq.questionID
                WHERE st.trainerID = ?
            ");
            mysqli_stmt_bind_param($stmt, 's', $trainerID);
            mysqli_stmt_execute($stmt);
            $feedbackRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            $feedbackTotal = (int)($feedbackRow['total'] ?? 0);

            $mustArchive = ($certificateTotal > 0 || $feedbackTotal > 0);

            if ($mustArchive) {
                $stmt = mysqli_prepare($conn, "UPDATE trainer SET status = 'inactive' WHERE trainerID = ?");
                mysqli_stmt_bind_param($stmt, 's', $trainerID);
                mysqli_stmt_execute($stmt);

                /* Active assignments are no longer shown for an archived trainer. */
                $stmt = mysqli_prepare($conn, 'DELETE FROM session_trainer WHERE trainerID = ?');
                mysqli_stmt_bind_param($stmt, 's', $trainerID);
                mysqli_stmt_execute($stmt);

                $_SESSION['flash_message'] = 'Trainer removed from the active list. Historical certificate or feedback records were preserved.';
                $_SESSION['flash_type'] = 'success';
            } else {
                /* Capture files before deleting the rows so their physical copies can also be removed. */
                $documentFiles = [];
                $stmt = mysqli_prepare($conn, 'SELECT file_path FROM trainer_document WHERE trainerID = ?');
                mysqli_stmt_bind_param($stmt, 's', $trainerID);
                mysqli_stmt_execute($stmt);
                $docResult = mysqli_stmt_get_result($stmt);
                while ($doc = mysqli_fetch_assoc($docResult)) {
                    if (!empty($doc['file_path'])) {
                        $documentFiles[] = (string)$doc['file_path'];
                    }
                }

                $stmt = mysqli_prepare($conn, 'DELETE FROM session_trainer WHERE trainerID = ?');
                mysqli_stmt_bind_param($stmt, 's', $trainerID);
                mysqli_stmt_execute($stmt);

                $stmt = mysqli_prepare($conn, 'DELETE FROM trainer_document WHERE trainerID = ?');
                mysqli_stmt_bind_param($stmt, 's', $trainerID);
                mysqli_stmt_execute($stmt);

                try {
                    $stmt = mysqli_prepare($conn, 'DELETE FROM trainer WHERE trainerID = ?');
                    mysqli_stmt_bind_param($stmt, 's', $trainerID);
                    mysqli_stmt_execute($stmt);

                    if (mysqli_stmt_affected_rows($stmt) === 0) {
                        throw new Exception('Trainer record was not found.');
                    }

                    foreach ($documentFiles as $filePath) {
                        $diskFile = resolvePublicFilePath($filePath);
                        if ($diskFile && is_file($diskFile)) {
                            @unlink($diskFile);
                        }
                    }

                    $_SESSION['flash_message'] = 'Trainer deleted successfully.';
                    $_SESSION['flash_type'] = 'success';
                } catch (mysqli_sql_exception $deleteError) {
                    // The combined database can gain new historical references
                    // from the other systems. If a FK prevents hard deletion,
                    // keep history safely and remove the trainer from active use.
                    $stmt = mysqli_prepare($conn, "UPDATE trainer SET status = 'inactive' WHERE trainerID = ?");
                    mysqli_stmt_bind_param($stmt, 's', $trainerID);
                    mysqli_stmt_execute($stmt);
                    $_SESSION['flash_message'] = 'Trainer removed from the active list. Historical linked records were preserved.';
                    $_SESSION['flash_type'] = 'success';
                }
            }

            mysqli_commit($conn);
            $inTransaction = false;

            header('Location: trainer.php');
            exit();
        }

        /* ADD TRAINER DOCUMENT */
        if ($action === 'insert_document') {
            $trainerID = trim((string)($_POST['trainerID'] ?? ''));
            $title = trim((string)($_POST['documentTitle'] ?? ''));
            $documentType = nullableValue($_POST['documentType'] ?? null);
            $description = nullableValue($_POST['documentDescription'] ?? null);

            if ($trainerID === '') throw new Exception('Trainer ID is missing.');
            if ($title === '') throw new Exception('Document title is required.');

            $filePath = uploadFile('documentFile', $trainerDocumentDir, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], null);
            if ($filePath === null) throw new Exception('Please upload a document file.');

            $fileName = basename($filePath);
            $sentByStaffID = $_SESSION['staffID'] ?? $_SESSION['staff_id'] ?? '';
            if ($sentByStaffID === '') throw new Exception('Staff session is missing. Please log in again.');

            $stmt = mysqli_prepare($conn, "
                INSERT INTO trainer_document
                    (trainerID, title, document_type, description, file_path, file_name, sentByStaffID, is_read, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
            ");
            mysqli_stmt_bind_param($stmt, 'sssssss', $trainerID, $title, $documentType, $description, $filePath, $fileName, $sentByStaffID);
            mysqli_stmt_execute($stmt);

            $_SESSION['flash_message'] = 'Trainer document added successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: trainer.php');
            exit();
        }

        /* UPDATE TRAINER DOCUMENT */
        if ($action === 'update_document') {
            $documentID = (int)($_POST['documentID'] ?? 0);
            $trainerID = trim((string)($_POST['trainerID'] ?? ''));
            $title = trim((string)($_POST['documentTitle'] ?? ''));
            $documentType = nullableValue($_POST['documentType'] ?? null);
            $description = nullableValue($_POST['documentDescription'] ?? null);
            $oldFile = nullableValue($_POST['oldDocumentFile'] ?? null);

            if ($documentID <= 0) throw new Exception('Document record is missing.');
            if ($trainerID === '') throw new Exception('Trainer ID is missing.');
            if ($title === '') throw new Exception('Document title is required.');

            $filePath = uploadFile('documentFile', $trainerDocumentDir, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], $oldFile);
            $fileName = $filePath ? basename($filePath) : null;

            $stmt = mysqli_prepare($conn, "
                UPDATE trainer_document
                   SET title = ?, document_type = ?, description = ?, file_path = ?, file_name = ?, updated_at = NOW()
                 WHERE document_id = ? AND trainerID = ?
            ");
            mysqli_stmt_bind_param($stmt, 'sssssis', $title, $documentType, $description, $filePath, $fileName, $documentID, $trainerID);
            mysqli_stmt_execute($stmt);

            $_SESSION['flash_message'] = 'Trainer document updated successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: trainer.php');
            exit();
        }

        /* DELETE TRAINER DOCUMENT */
        if ($action === 'delete_document') {
            $documentID = (int)($_POST['documentID'] ?? 0);
            $trainerID = trim((string)($_POST['trainerID'] ?? ''));
            if ($documentID <= 0 || $trainerID === '') throw new Exception('Document record is missing.');

            $stmt = mysqli_prepare($conn, "SELECT file_path FROM trainer_document WHERE document_id = ? AND trainerID = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'is', $documentID, $trainerID);
            mysqli_stmt_execute($stmt);
            $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$existing) {
                throw new Exception('Trainer document was not found.');
            }

            $stmt = mysqli_prepare($conn, "DELETE FROM trainer_document WHERE document_id = ? AND trainerID = ?");
            mysqli_stmt_bind_param($stmt, 'is', $documentID, $trainerID);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_affected_rows($stmt) === 0) {
                throw new Exception('Trainer document could not be deleted.');
            }

            if (!empty($existing['file_path'])) {
                $diskFile = resolvePublicFilePath($existing['file_path']);
                if ($diskFile && is_file($diskFile)) {
                    @unlink($diskFile);
                }
            }

            $_SESSION['flash_message'] = 'Trainer document deleted successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: trainer.php');
            exit();
        }

    } catch (Exception $e) {
        if ($inTransaction) {
            mysqli_rollback($conn);
        }

        $_SESSION['flash_message'] = $e->getMessage();
        $_SESSION['flash_type'] = 'error';

        header('Location: trainer.php');
        exit();
    }
}

/* =========================
   FILTER + PAGING
========================= */

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$expertiseFilter = trim($_GET['expertise'] ?? '');
$hasTrainerFilters = ($search !== '' || $statusFilter !== '' || $expertiseFilter !== '');

$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $limit;
$where = ($statusFilter === '') ? "WHERE COALESCE(t.status, 'active') <> 'inactive'" : 'WHERE 1';

if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);

    $where .= " AND (
        t.trainerName LIKE '%$safeSearch%' OR
        t.trainerEmail LIKE '%$safeSearch%' OR
        t.trainerPhoneNo LIKE '%$safeSearch%' OR
        t.expertise LIKE '%$safeSearch%'
    )";
}

if ($statusFilter !== '') {
    $safeStatus = mysqli_real_escape_string($conn, $statusFilter);
    $where .= " AND t.status = '$safeStatus'";
}

if ($expertiseFilter !== '') {
    $safeExpertise = mysqli_real_escape_string($conn, $expertiseFilter);
    $where .= " AND t.expertise LIKE '%$safeExpertise%'";
}

/* trainer.averageRating is the canonical cached rating. feedback.logic.php
   refreshes it whenever participant feedback is submitted, so the trainer
   list does not need to rebuild the feedback joins on every page load. */
$displayRatingSelect = "COALESCE(t.averageRating, 0) AS displayRating";
$feedbackRatingJoin = "";

$countQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM trainer t $where");
$totalRows = (int)mysqli_fetch_assoc($countQuery)['total'];
$totalPages = max((int)ceil($totalRows / $limit), 1);

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$trainersResult = mysqli_query($conn, "
    SELECT
        t.*,
        $displayRatingSelect,
        COALESCE(s.sessionCount, 0) AS sessionCount,
        COALESCE(sl.sessionList, '') AS sessionList,
        COALESCE(doc.documentCount, 0) AS documentCount
    FROM trainer t


    $feedbackRatingJoin

    LEFT JOIN (
        SELECT trainerID, COUNT(*) AS sessionCount
        FROM session_trainer
        GROUP BY trainerID
    ) s ON s.trainerID = t.trainerID

    LEFT JOIN (
        SELECT
            st.trainerID,
            GROUP_CONCAT(
                CONCAT(
                    COALESCE(NULLIF(cs.sessionName, ''), 'Session'),
                    ' • ', COALESCE(c.courseName, 'No training'),
                    ' • ', DATE_FORMAT(cs.sessionDate, '%d %b %Y'),
                    ' • ', TIME_FORMAT(cs.startTime, '%h:%i %p'),
                    ' - ', TIME_FORMAT(cs.endTime, '%h:%i %p'),
                    IF(cs.location IS NULL OR cs.location = '', '', CONCAT(' • ', cs.location))
                )
                ORDER BY cs.sessionDate ASC, cs.startTime ASC
                SEPARATOR '||'
            ) AS sessionList
        FROM session_trainer st
        JOIN course_session cs ON cs.sessionID = st.sessionID
        LEFT JOIN course c ON c.courseID = cs.courseID
        GROUP BY st.trainerID
    ) sl ON sl.trainerID = t.trainerID

    LEFT JOIN (
        SELECT trainerID, COUNT(*) AS documentCount
        FROM trainer_document
        GROUP BY trainerID
    ) doc ON doc.trainerID = t.trainerID

    $where
    ORDER BY t.trainerID DESC
    LIMIT $limit OFFSET $offset
");

$trainerRows = [];

while ($row = mysqli_fetch_assoc($trainersResult)) {
    $trainerRows[] = $row;
}

$documentsByTrainer = [];
if (!empty($trainerRows)) {
    $trainerIDsForDocuments = array_values(array_filter(array_map(
        static fn($row) => trim((string)($row['trainerID'] ?? '')),
        $trainerRows
    )));

    if (!empty($trainerIDsForDocuments)) {
        $escapedTrainerIDs = array_map(
            static fn($id) => "'" . mysqli_real_escape_string($conn, $id) . "'",
            $trainerIDsForDocuments
        );
        $trainerIDList = implode(',', $escapedTrainerIDs);

        $documentResult = mysqli_query($conn, "
            SELECT td.*, se.staffName AS sentByStaffName
            FROM trainer_document td
            LEFT JOIN staff_edu se ON se.staffID = td.sentByStaffID
            WHERE td.trainerID IN ($trainerIDList)
            ORDER BY td.created_at DESC, td.document_id DESC
        ");
        while ($document = mysqli_fetch_assoc($documentResult)) {
            $documentsByTrainer[$document['trainerID']][] = $document;
        }
    }
}

/* =========================
   TOTAL CARDS
========================= */

$totalTrainers = (int)mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM trainer
"))['total'];

$activeTrainers = (int)mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM trainer WHERE status = 'active'
"))['total'];

$avgRating = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(AVG(averageRating), 0) AS avgRating
    FROM trainer
"))['avgRating'];

$sessionsAssigned = (int)mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM session_trainer
"))['total'];

$expertiseOptions = mysqli_query($conn, "
    SELECT DISTINCT expertise
    FROM trainer
    WHERE expertise IS NOT NULL AND expertise <> ''
    ORDER BY expertise ASC
    LIMIT 50
");

$topRatedResult = mysqli_query($conn, "
    SELECT
        t.trainerID,
        t.trainerName,
        t.trainerPic,
        t.trainerIC,
        t.trainerEmail,
        t.trainerPhoneNo,
        t.expertise,
        t.status,
        t.paymentStatus,
        $displayRatingSelect,
        COALESCE(s.sessionCount, 0) AS sessionCount
    FROM trainer t
    $feedbackRatingJoin
    LEFT JOIN (
        SELECT trainerID, COUNT(*) AS sessionCount
        FROM session_trainer
        GROUP BY trainerID
    ) s ON s.trainerID = t.trainerID
    WHERE COALESCE(t.status, 'active') <> 'inactive'
    ORDER BY displayRating DESC, t.trainerName ASC
    LIMIT 3
");
$topRatedRows = [];
while ($topRatedRow = $topRatedResult ? mysqli_fetch_assoc($topRatedResult) : null) {
    $topRatedRows[] = $topRatedRow;
}
