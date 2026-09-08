<?php
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/db.php";

/* =========================
   DATABASE CONNECTION
========================= */
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit("Database connection is not available.");
}

/* config/db.php already connects to DB_DATABASE from Laravel's .env. */
if (!mysqli_select_db($conn, TRAINHUB_DATABASE_NAME)) {
    error_log("Unable to select database " . TRAINHUB_DATABASE_NAME . ": " . mysqli_error($conn));
    http_response_code(500);
    exit("Unable to select the application database.");
}

/* =========================
   HELPER FUNCTIONS
========================= */
function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function getCount($conn, $sql) {
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row["total"] ?? 0;
    }

    return 0;
}

function displayStatus($status) {
    $status = strtolower(trim((string)($status ?? "upcoming")));

    if ($status === "completed") {
        return "Completed";
    }

    if ($status === "ongoing") {
        return "Ongoing";
    }

    if ($status === "cancelled") {
        return "Cancelled";
    }

    return "Upcoming";
}

function statusClass($status) {
    $status = strtolower(trim((string)($status ?? "upcoming")));

    return in_array($status, ["upcoming", "ongoing", "completed", "cancelled"], true)
        ? $status
        : "upcoming";
}


function humanizeAuditLabel($label) {
    $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', (string)$label);
    $label = str_replace(['_', '-'], ' ', $label);
    $label = preg_replace('/\s+/', ' ', trim($label));
    return $label === '' ? 'Record' : ucwords(strtolower($label));
}

function auditKeyIsIdentifier($key) {
    $compact = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$key));
    return $compact === 'id' || str_ends_with($compact, 'id') || strpos($compact, 'identifier') !== false;
}

function cleanAuditValue($value) {
    if ($value === null || $value === '') {
        return 'Not set';
    }
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }
    if (is_array($value) || is_object($value)) {
        return 'Updated';
    }
    $value = str_replace('_', ' ', trim((string)$value));
    $value = preg_replace('/\b[A-Za-z][A-Za-z ]*ID\s*[:=]?\s*[A-Za-z0-9-]+\b/i', '', $value);
    $value = preg_replace('/\s+/', ' ', trim($value));
    if (preg_match('/^[A-Z]{1,12}[0-9]{2,}$/', $value)) {
        return 'Updated';
    }
    return $value === '' ? 'Updated' : $value;
}

function formatAuditDetails($actionType, $tableName, $rawValue) {
    $action = strtoupper(trim((string)$actionType));
    if ($action === 'LOGIN') return 'Signed in to the system.';
    if ($action === 'LOGOUT') return 'Signed out of the system.';

    $raw = trim((string)$rawValue);
    $items = [];

    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                if (auditKeyIsIdentifier($key)) continue;
                if (is_array($value) && array_key_exists('new', $value)) {
                    $value = $value['new'];
                }
                $items[] = humanizeAuditLabel($key) . ': ' . cleanAuditValue($value);
            }
        } else {
            $parts = preg_split('/\s*[;,|]\s*/', $raw);
            foreach ($parts as $part) {
                if ($part === '') continue;
                if (preg_match('/^\s*([^:=]+)\s*[:=]\s*(.*)$/', $part, $m)) {
                    $key = trim($m[1]);
                    if (auditKeyIsIdentifier($key)) continue;
                    $items[] = humanizeAuditLabel($key) . ': ' . cleanAuditValue($m[2]);
                }
            }
        }
    }

    $verb = match ($action) {
        'INSERT' => 'Added',
        'UPDATE' => 'Updated',
        'DELETE' => 'Deleted',
        default => 'Changed'
    };
    $subject = strtolower(humanizeAuditLabel($tableName));

    if (empty($items)) {
        return $verb . ' ' . $subject . ' record.';
    }

    return $verb . ' ' . $subject . ': ' . implode('; ', array_slice($items, 0, 6)) . '.';
}

function auditUrl($type, $month, $page = 1) {
    return "dashboard.php?audit_type=" . urlencode($type) .
           "&audit_month=" . urlencode($month) .
           "&audit_page=" . urlencode((string)$page) .
           "#audit";
}

function compactPaginationItems($currentPage, $totalPages, $radius = 2) {
    $currentPage = max(1, (int)$currentPage);
    $totalPages = max(1, (int)$totalPages);
    if ($totalPages <= 7) return range(1, $totalPages);

    $pages = [1, $totalPages];
    for ($page = max(2, $currentPage - $radius); $page <= min($totalPages - 1, $currentPage + $radius); $page++) {
        $pages[] = $page;
    }
    sort($pages);
    $pages = array_values(array_unique($pages));

    $items = [];
    $previous = null;
    foreach ($pages as $page) {
        if ($previous !== null && $page - $previous > 1) $items[] = null;
        $items[] = $page;
        $previous = $page;
    }
    return $items;
}

function renderAuditContent($conn, $auditType, $auditMonth, $auditPage) {
    $allowedTypes = ["ALL", "LOGIN", "LOGOUT", "INSERT", "UPDATE", "DELETE"];

    if (!in_array($auditType, $allowedTypes, true)) {
        $auditType = "ALL";
    }

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)$auditMonth)) {
        $auditMonth = date("Y-m");
    }

    $auditLimit = 6;

    if ($auditPage < 1) {
        $auditPage = 1;
    }

    $auditOffset = ($auditPage - 1) * $auditLimit;

    /* Use a range instead of DATE_FORMAT(actionDate). This keeps the
       idx_audit_log_action_date index usable when the log becomes large. */
    $monthStart = $auditMonth . '-01 00:00:00';
    $monthEnd = (new DateTimeImmutable($auditMonth . '-01'))->modify('+1 month')->format('Y-m-d H:i:s');
    $monthStartSafe = mysqli_real_escape_string($conn, $monthStart);
    $monthEndSafe = mysqli_real_escape_string($conn, $monthEnd);
    $whereAudit = "WHERE a.actionDate >= '$monthStartSafe' AND a.actionDate < '$monthEndSafe'";

    if ($auditType !== "ALL") {
        $typeSafe = mysqli_real_escape_string($conn, $auditType);
        $whereAudit .= " AND a.actionType = '$typeSafe'";
    }

    $totalAudit = getCount($conn, "
        SELECT COUNT(*) AS total 
        FROM audit_log a
        $whereAudit
    ");

    $totalAuditPages = max((int)ceil($totalAudit / $auditLimit), 1);

    if ($auditPage > $totalAuditPages) {
        $auditPage = $totalAuditPages;
        $auditOffset = ($auditPage - 1) * $auditLimit;
    }

    /*
     * Canonical audit_log structure:
     * logID, staffID, userName, actionType, tableName, newValue, actionDate.
     * oldValue is no longer used.
     */
    $auditLog = mysqli_query($conn, "
        SELECT 
            a.logID,
            a.staffID,
            a.userName,
            a.actionType,
            a.tableName,
            a.actionDate,
            a.newValue,
            COALESCE(a.userName, s.staffName, a.staffID, 'System') AS staffName
        FROM audit_log a
        LEFT JOIN staff_edu s ON s.staffID = a.staffID
        $whereAudit
        ORDER BY a.actionDate DESC
        LIMIT $auditLimit OFFSET $auditOffset
    ");

    ob_start();
    ?>

    <div class="audit-header">
        <h2>Audit Log</h2>

        <form method="GET" class="audit-filter" id="auditFilterForm">
            <input type="hidden" name="audit_type" id="auditTypeInput" value="<?php echo h($auditType); ?>">
            <input type="month" name="audit_month" value="<?php echo h($auditMonth); ?>">
            <button type="submit">Filter</button>
        </form>
    </div>

    <div class="audit-tabs">
        <?php foreach ($allowedTypes as $type) { ?>
            <a
                class="audit-ajax-link <?php echo $auditType === $type ? 'active' : ''; ?>"
                href="<?php echo h(auditUrl($type, $auditMonth, 1)); ?>"
                data-type="<?php echo h($type); ?>"
                data-url="dashboard.php?ajax=audit&audit_type=<?php echo h($type); ?>&audit_month=<?php echo h($auditMonth); ?>&audit_page=1"
            >
                <?php echo $type === "ALL" ? "All" : ucfirst(strtolower($type)); ?>
            </a>
        <?php } ?>
    </div>

    <div class="audit-table-wrap">
        <table class="audit-table">
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Date & Time</th>
                    <th>Details</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($auditLog && mysqli_num_rows($auditLog) > 0) { ?>
                    <?php while ($log = mysqli_fetch_assoc($auditLog)) { ?>
                        <?php
                        $actionClass = strtolower((string)$log["actionType"]);

                        if ($actionClass === "login" || $actionClass === "logout") {
                            $actionClass = "update";
                        }
                        ?>

                        <tr>
                            <td><?php echo h($log["staffName"]); ?></td>

                            <td>
                                <span class="audit-badge <?php echo h($actionClass); ?>">
                                    <?php echo h($log["actionType"]); ?>
                                </span>
                            </td>

                            <td><?php echo h(humanizeAuditLabel($log["tableName"])); ?></td>

                            <td>
                                <?php echo date("d M Y, h:i A", strtotime((string)$log["actionDate"])); ?>
                            </td>

                            <td>
                                <?php echo h(formatAuditDetails($log["actionType"], $log["tableName"], $log["newValue"] ?? "")); ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">No audit records found for this filter.</div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="audit-footer">
        <span class="audit-count-pill">
            Showing <?php echo (int)$totalAudit; ?> audit record(s)
        </span>

        <div class="pagination">
            <?php if ($auditPage > 1) { ?>
                <a
                    class="audit-ajax-link"
                    href="<?php echo h(auditUrl($auditType, $auditMonth, $auditPage - 1)); ?>"
                    data-url="dashboard.php?ajax=audit&audit_type=<?php echo h($auditType); ?>&audit_month=<?php echo h($auditMonth); ?>&audit_page=<?php echo $auditPage - 1; ?>"
                >‹</a>
            <?php } ?>

            <?php foreach (compactPaginationItems($auditPage, $totalAuditPages) as $pageItem) { ?>
                <?php if ($pageItem === null) { ?>
                    <span class="pagination-ellipsis" aria-hidden="true">…</span>
                <?php } elseif ($pageItem === $auditPage) { ?>
                    <span class="active"><?php echo $pageItem; ?></span>
                <?php } else { ?>
                    <a
                        class="audit-ajax-link"
                        href="<?php echo h(auditUrl($auditType, $auditMonth, $pageItem)); ?>"
                        data-url="dashboard.php?ajax=audit&audit_type=<?php echo h($auditType); ?>&audit_month=<?php echo h($auditMonth); ?>&audit_page=<?php echo $pageItem; ?>"
                    ><?php echo $pageItem; ?></a>
                <?php } ?>
            <?php } ?>

            <?php if ($auditPage < $totalAuditPages) { ?>
                <a
                    class="audit-ajax-link"
                    href="<?php echo h(auditUrl($auditType, $auditMonth, $auditPage + 1)); ?>"
                    data-url="dashboard.php?ajax=audit&audit_type=<?php echo h($auditType); ?>&audit_month=<?php echo h($auditMonth); ?>&audit_page=<?php echo $auditPage + 1; ?>"
                >›</a>
            <?php } ?>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

/* =========================
   SESSION DATA
========================= */
$staffID = $_SESSION["staffID"] ?? $_SESSION["staff_id"] ?? "";
$staff_name = $_SESSION["staffName"] ?? $_SESSION["staff_name"] ?? "Admin";

if (empty($staffID)) {
    header("Location: login.php");
    exit();
}

/* Make sure topbar.php can read the same session format. */
$_SESSION["staffID"] = $staffID;
$_SESSION["staff_id"] = $staffID;
$_SESSION["staffName"] = $staff_name;
$_SESSION["staff_name"] = $staff_name;

$safeStaffID = mysqli_real_escape_string($conn, (string)$staffID);

/* Used by audit triggers on this database connection. */
mysqli_query($conn, "SET @current_staff_id = '$safeStaffID'");

/* Credit totals are calculated read-only on dashboard load.
 * The stored staff_edu.credit_hour cache is refreshed only by real attendance changes,
 * so opening or cancelling a page does not create false UPDATE audit records. */

/* =========================
   AUDIT AJAX
========================= */
$auditType = isset($_GET["audit_type"]) ? strtoupper((string)$_GET["audit_type"]) : "ALL";
$auditMonth = isset($_GET["audit_month"]) ? (string)$_GET["audit_month"] : date("Y-m");
$auditPage = isset($_GET["audit_page"]) ? (int)$_GET["audit_page"] : 1;

if (isset($_GET["ajax"]) && $_GET["ajax"] === "audit") {
    echo renderAuditContent($conn, $auditType, $auditMonth, $auditPage);
    exit();
}

/* =========================
   TOTAL DATA
========================= */
$total_teacher = getCount($conn, "SELECT COUNT(*) AS total FROM teacher");
$total_school = getCount($conn, "SELECT COUNT(*) AS total FROM school");
$total_trainer = getCount($conn, "SELECT COUNT(*) AS total FROM trainer");
$total_training = getCount($conn, "SELECT COUNT(*) AS total FROM course");

/* =========================
   STAFF TRAINING CREDIT - YEARLY
   Annual target: 40 hours = up to 10 Tarbiah + up to 30 other training.
========================= */
$targetCredit = 40;
$tarbiahTargetCredit = 10;
$trainingTargetCredit = 30;
$currentCreditYear = (int)date('Y');
$selectedHistoryYear = isset($_GET['credit_history_year']) ? (int)$_GET['credit_history_year'] : $currentCreditYear;
if ($selectedHistoryYear < 2000 || $selectedHistoryYear > ($currentCreditYear + 1)) {
    $selectedHistoryYear = $currentCreditYear;
}

$creditYears = [$currentCreditYear];
$creditYearResult = mysqli_query($conn, "
    SELECT creditYear
    FROM (
        SELECT DISTINCT YEAR(cs.sessionDate) AS creditYear
        FROM attendance_staff ast
        JOIN course_session cs ON cs.sessionID = ast.session_id
        WHERE ast.staffID = '$safeStaffID'
          AND ast.attendance_status = 'approved'
          AND cs.sessionDate IS NOT NULL
        UNION
        SELECT DISTINCT YEAR(t.session_date) AS creditYear
        FROM staff_tarbiah_attendance ta
        JOIN tarbiah t ON t.tarbiah_id = ta.tarbiah_id
        WHERE ta.staffID = '$safeStaffID'
          AND ta.attendance_status = 'approved'
          AND t.session_date IS NOT NULL
    ) years
    WHERE creditYear IS NOT NULL
    ORDER BY creditYear DESC
");
if ($creditYearResult) {
    while ($yearRow = mysqli_fetch_assoc($creditYearResult)) {
        $year = (int)($yearRow['creditYear'] ?? 0);
        if ($year > 0) $creditYears[] = $year;
    }
}
$creditYears = array_values(array_unique($creditYears));
rsort($creditYears);
if (!in_array($selectedHistoryYear, $creditYears, true)) {
    $creditYears[] = $selectedHistoryYear;
    rsort($creditYears);
}

/* Read the staff IC once. The previous dashboard called fn_calculate_credit_hour()
   once per attendance row; that function performs its own SELECTs, which becomes
   very expensive as attendance grows. */
$staffNormalizedIC = '';
$staffProfileResult = mysqli_query($conn, "
    SELECT REPLACE(REPLACE(TRIM(COALESCE(ICNumber, '')), '-', ''), ' ', '') AS normalizedIC
    FROM staff_edu
    WHERE staffID = '$safeStaffID'
    LIMIT 1
");
if ($staffProfileResult && ($staffProfile = mysqli_fetch_assoc($staffProfileResult))) {
    $staffNormalizedIC = trim((string)($staffProfile['normalizedIC'] ?? ''));
}
$safeStaffIC = mysqli_real_escape_string($conn, $staffNormalizedIC);

function fetchStaffTrainingCreditRows($conn, $safeStaffID, $safeStaffIC, $year) {
    $rows = [];
    $year = (int)$year;
    $yearStart = mysqli_real_escape_string($conn, sprintf('%04d-01-01', $year));
    $nextYearStart = mysqli_real_escape_string($conn, sprintf('%04d-01-01', $year + 1));

    $trainerCourseJoin = '';
    $trainerMatchExpression = '0';
    if ($safeStaffIC !== '') {
        $trainerCourseJoin = "
            LEFT JOIN (
                SELECT DISTINCT cs2.courseID
                FROM trainer tr
                JOIN session_trainer st2 ON st2.trainerID = tr.trainerID
                JOIN course_session cs2 ON cs2.sessionID = st2.sessionID
                WHERE REPLACE(REPLACE(TRIM(COALESCE(tr.trainerIC, '')), '-', ''), ' ', '') = '$safeStaffIC'
            ) trainer_course ON trainer_course.courseID = c.courseID
        ";
        $trainerMatchExpression = "MAX(CASE WHEN trainer_course.courseID IS NOT NULL THEN 1 ELSE 0 END)";
    }

    $baseHoursExpression = "CASE WHEN cs.endTime > cs.startTime
        THEN TIME_TO_SEC(TIMEDIFF(cs.endTime, cs.startTime)) / 3600
        ELSE COALESCE(ast.hours_ladap, 0)
    END";

    $result = mysqli_query($conn, "
        SELECT
            'training' AS sourceType,
            c.courseID AS recordID,
            c.courseName AS activityName,
            MIN(cs.sessionDate) AS firstDate,
            MAX(cs.sessionDate) AS lastDate,
            COUNT(*) AS sessionsAttended,
            ROUND(SUM($baseHoursExpression), 2) AS baseHours,
            ROUND(
                SUM($baseHoursExpression) *
                CASE
                    WHEN $trainerMatchExpression = 1 THEN 1.5
                    WHEN LOWER(COALESCE(c.organiserName, '')) LIKE '%al amin edu oasis%' THEN 1.0
                    ELSE 0.5
                END,
                2
            ) AS creditHours,
            CASE
                WHEN $trainerMatchExpression = 1 THEN 'Trainer ×1.5'
                WHEN LOWER(COALESCE(c.organiserName, '')) LIKE '%al amin edu oasis%' THEN 'Al Amin ×1.0'
                ELSE 'External ×0.5'
            END AS creditRule,
            NULL AS activityLocation,
            NULL AS activityStartTime,
            NULL AS activityEndTime,
            NULL AS activitySchool
        FROM attendance_staff ast
        JOIN course_session cs ON cs.sessionID = ast.session_id
        JOIN course c ON c.courseID = cs.courseID
        $trainerCourseJoin
        WHERE ast.staffID = '$safeStaffID'
          AND ast.attendance_status = 'approved'
          AND cs.sessionDate >= '$yearStart'
          AND cs.sessionDate < '$nextYearStart'
        GROUP BY c.courseID, c.courseName, c.organiserName, ast.staffID
        ORDER BY lastDate DESC, activityName ASC
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    }
    return $rows;
}

function fetchStaffTarbiahCreditRows($conn, $safeStaffID, $year) {
    $rows = [];
    $year = (int)$year;
    $yearStart = mysqli_real_escape_string($conn, sprintf('%04d-01-01', $year));
    $nextYearStart = mysqli_real_escape_string($conn, sprintf('%04d-01-01', $year + 1));
    $result = mysqli_query($conn, "
        SELECT
            'tarbiah' AS sourceType,
            CONCAT('T', t.tarbiah_id) AS recordID,
            t.title AS activityName,
            t.session_date AS firstDate,
            t.session_date AS lastDate,
            1 AS sessionsAttended,
            ROUND(CASE WHEN t.end_time > t.start_time
                THEN TIME_TO_SEC(TIMEDIFF(t.end_time, t.start_time)) / 3600
                ELSE 0 END, 2) AS baseHours,
            ROUND(CASE WHEN t.end_time > t.start_time
                THEN TIME_TO_SEC(TIMEDIFF(t.end_time, t.start_time)) / 3600
                ELSE 0 END, 2) AS creditHours,
            'Tarbiah ×1.0' AS creditRule,
            t.location AS activityLocation,
            t.start_time AS activityStartTime,
            t.end_time AS activityEndTime,
            s.schoolName AS activitySchool
        FROM staff_tarbiah_attendance ta
        JOIN tarbiah t ON t.tarbiah_id = ta.tarbiah_id
        LEFT JOIN school s ON s.schoolID = t.schoolID
        WHERE ta.staffID = '$safeStaffID'
          AND ta.attendance_status = 'approved'
          AND t.session_date >= '$yearStart'
          AND t.session_date < '$nextYearStart'
        ORDER BY t.session_date DESC, t.title ASC
    ");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    }
    return $rows;
}

$currentTrainingCreditRows = fetchStaffTrainingCreditRows($conn, $safeStaffID, $safeStaffIC, $currentCreditYear);
$currentTarbiahCreditRows = fetchStaffTarbiahCreditRows($conn, $safeStaffID, $currentCreditYear);

$rawTrainingCredit = array_sum(array_map(fn($row) => (float)($row['creditHours'] ?? 0), $currentTrainingCreditRows));
$rawTarbiahCredit = array_sum(array_map(fn($row) => (float)($row['creditHours'] ?? 0), $currentTarbiahCreditRows));
$trainingCredit = min($trainingTargetCredit, $rawTrainingCredit);
$tarbiahCredit = min($tarbiahTargetCredit, $rawTarbiahCredit);
$achievedCredit = min($targetCredit, $trainingCredit + $tarbiahCredit);
$remainingCredit = max($targetCredit - $achievedCredit, 0);
$creditPercent = $targetCredit > 0 ? min(100, round(($achievedCredit / $targetCredit) * 100)) : 0;

$staffTrainingSessionCount = array_sum(array_map(fn($row) => (int)($row['sessionsAttended'] ?? 0), $currentTrainingCreditRows)) + count($currentTarbiahCreditRows);
$allCurrentCreditRows = array_merge($currentTrainingCreditRows, $currentTarbiahCreditRows);
usort($allCurrentCreditRows, fn($a, $b) => strcmp((string)($b['lastDate'] ?? ''), (string)($a['lastDate'] ?? '')));
$latestTrainingDate = !empty($allCurrentCreditRows[0]['lastDate']) ? date('d M Y', strtotime((string)$allCurrentCreditRows[0]['lastDate'])) : 'N/A';
$staffCreditCourses = $allCurrentCreditRows;

/* The current year is the default history year, so reuse rows already loaded
   instead of running the same two database queries a second time. */
if ($selectedHistoryYear === $currentCreditYear) {
    $staffCreditHistoryCourses = $allCurrentCreditRows;
} else {
    $staffCreditHistoryCourses = array_merge(
        fetchStaffTrainingCreditRows($conn, $safeStaffID, $safeStaffIC, $selectedHistoryYear),
        fetchStaffTarbiahCreditRows($conn, $safeStaffID, $selectedHistoryYear)
    );
    usort($staffCreditHistoryCourses, fn($a, $b) => strcmp((string)($b['lastDate'] ?? ''), (string)($a['lastDate'] ?? '')));
}

$historyTrainingRaw = 0.0;
$historyTarbiahRaw = 0.0;
foreach ($staffCreditHistoryCourses as $row) {
    if (($row['sourceType'] ?? '') === 'tarbiah') $historyTarbiahRaw += (float)($row['creditHours'] ?? 0);
    else $historyTrainingRaw += (float)($row['creditHours'] ?? 0);
}
$historyTrainingCredit = min($trainingTargetCredit, $historyTrainingRaw);
$historyTarbiahCredit = min($tarbiahTargetCredit, $historyTarbiahRaw);
$historyCreditTotal = min($targetCredit, $historyTrainingCredit + $historyTarbiahCredit);

/* =========================
   CALENDAR EVENTS
========================= */
$calendarEvents = [];

/* The calendar selector only exposes five years either side of the current
   year, so do not serialize every historical/future session into the page. */
$calendarStart = mysqli_real_escape_string($conn, sprintf('%04d-01-01', max(2000, $currentCreditYear - 5)));
$calendarEnd = mysqli_real_escape_string($conn, sprintf('%04d-01-01', $currentCreditYear + 6));

$calendarQuery = mysqli_query($conn, "
    SELECT
        c.courseID,
        c.courseName,
        c.courseCategory,
        c.courseType,
        c.status AS courseStatus,
        cs.sessionID,
        cs.sessionDate,
        cs.sessionName,
        cs.startTime,
        cs.endTime,
        cs.location,
        GROUP_CONCAT(DISTINCT t.trainerName ORDER BY t.trainerName SEPARATOR ', ') AS trainerNames,
        c.description,
        c.courseRating,
        c.capacity,
        c.price,
        c.organiserName,
        c.mode,
        c.onlineLink,
        c.whatsappGroup
    FROM course_session cs
    JOIN course c ON c.courseID = cs.courseID
    LEFT JOIN session_trainer st ON st.sessionID = cs.sessionID
    LEFT JOIN trainer t ON t.trainerID = st.trainerID
    WHERE cs.sessionDate >= '$calendarStart'
      AND cs.sessionDate < '$calendarEnd'
    GROUP BY
        c.courseID, c.courseName, c.courseCategory, c.courseType, c.status,
        cs.sessionID, cs.sessionDate, cs.sessionName, cs.startTime, cs.endTime, cs.location,
        c.description, c.courseRating, c.capacity, c.price, c.organiserName,
        c.mode, c.onlineLink, c.whatsappGroup
    ORDER BY cs.sessionDate ASC, cs.startTime ASC
");

if ($calendarQuery) {
    while ($row = mysqli_fetch_assoc($calendarQuery)) {
        $calendarEvents[] = [
            "id" => $row["sessionID"],
            "title" => $row["courseName"],
            "start" => $row["sessionDate"] . "T" . $row["startTime"],
            "end" => $row["sessionDate"] . "T" . $row["endTime"],
            "dateOnly" => $row["sessionDate"],
            "extendedProps" => [
                "courseID" => $row["courseID"],
                "sessionID" => $row["sessionID"],
                "sessionName" => $row["sessionName"],
                "description" => $row["description"],
                "courseCategory" => $row["courseCategory"],
                "courseType" => $row["courseType"],
                "courseRating" => $row["courseRating"],
                "status" => displayStatus($row["courseStatus"]),
                "capacity" => $row["capacity"],
                "price" => $row["price"],
                "organiserName" => $row["organiserName"],
                "mode" => $row["mode"],
                "onlineLink" => $row["onlineLink"],
                "whatsappGroup" => $row["whatsappGroup"],
                "trainerNames" => $row["trainerNames"],
                "location" => $row["location"],
                "date" => $row["sessionDate"],
                "startTime" => $row["startTime"],
                "endTime" => $row["endTime"]
            ]
        ];
    }
}

$events_json = json_encode(
    $calendarEvents,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

/* =========================
   TRAINING LIST
========================= */
$trainingList = mysqli_query($conn, "
    SELECT
        c.courseID,
        c.courseName,
        c.description,
        c.courseCategory,
        c.status,
        c.mode,
        c.courseRating,
        s.startDate,
        s.endDate,
        COALESCE(tr.trainerNames, '') AS trainerNames
    FROM course c
    LEFT JOIN (
        SELECT courseID, MIN(sessionDate) AS startDate, MAX(sessionDate) AS endDate
        FROM course_session
        GROUP BY courseID
    ) s ON s.courseID = c.courseID
    LEFT JOIN (
        SELECT
            cs.courseID,
            GROUP_CONCAT(DISTINCT t.trainerName ORDER BY t.trainerName SEPARATOR ', ') AS trainerNames
        FROM course_session cs
        LEFT JOIN session_trainer st ON st.sessionID = cs.sessionID
        LEFT JOIN trainer t ON t.trainerID = st.trainerID
        GROUP BY cs.courseID
    ) tr ON tr.courseID = c.courseID
    ORDER BY COALESCE(s.startDate, '9999-12-31') ASC, c.courseName ASC
    LIMIT 20
");

/* =========================
   UPCOMING SESSIONS
========================= */
$upcomingTrainings = mysqli_query($conn, "
    SELECT
        c.courseName,
        c.courseCategory,
        c.courseType,
        c.status AS courseStatus,
        cs.sessionID,
        cs.sessionDate,
        cs.sessionName,
        cs.startTime,
        cs.endTime,
        cs.location,
        GROUP_CONCAT(DISTINCT t.trainerName ORDER BY t.trainerName SEPARATOR ', ') AS trainerNames
    FROM course_session cs
    JOIN course c ON c.courseID = cs.courseID
    LEFT JOIN session_trainer st ON st.sessionID = cs.sessionID
    LEFT JOIN trainer t ON t.trainerID = st.trainerID
    WHERE cs.sessionDate >= CURDATE()
    GROUP BY
        c.courseName, c.courseCategory, c.courseType, c.status,
        cs.sessionID, cs.sessionDate, cs.sessionName, cs.startTime, cs.endTime, cs.location
    ORDER BY cs.sessionDate ASC, cs.startTime ASC
    LIMIT 4
");
