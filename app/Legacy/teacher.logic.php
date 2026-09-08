<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!$conn->select_db(TRAINHUB_DATABASE_NAME)) {
    http_response_code(500);
    exit('Unable to select the configured TrainHub database.');
}
date_default_timezone_set('Asia/Kuala_Lumpur');

$staffID = $_SESSION['staffID'] ?? $_SESSION['staff_id'] ?? '';
if ($staffID === '') {
    header('Location: login.php');
    exit();
}

$setAuditStaff = $conn->prepare('SET @current_staff_id = ?');
$setAuditStaff->bind_param('s', $staffID);
$setAuditStaff->execute();
$setAuditStaff->close();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verifyCsrfToken(): void {
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        throw new RuntimeException('Your form session has expired. Please refresh the page and try again.');
    }
}


function generateNextId($conn, $table, $column, $prefix, $pad = 4) {
    $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^A-Za-z0-9_]/', '', $column);
    $safePrefix = mysqli_real_escape_string($conn, $prefix);

    $query = "
        SELECT $safeColumn AS latest_id
        FROM $safeTable
        WHERE $safeColumn LIKE '{$safePrefix}%'
        ORDER BY CAST(SUBSTRING($safeColumn, " . (strlen($prefix) + 1) . ") AS UNSIGNED) DESC
        LIMIT 1
    ";

    $result = mysqli_query($conn, $query);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    $num = 1;

    if ($row && !empty($row['latest_id'])) {
        $num = ((int)substr($row['latest_id'], strlen($prefix))) + 1;
    }

    return $prefix . str_pad($num, $pad, '0', STR_PAD_LEFT);
}

function classifySchool(?string $schoolID, ?string $schoolName): string {
    $id = strtoupper((string)$schoolID);
    $name = strtoupper((string)$schoolName);
    $combined = $id . ' ' . $name;

    if (
        str_contains($combined, 'MENENGAH') ||
        str_contains($combined, 'SECONDARY') ||
        str_contains($combined, ' SEC') ||
        str_ends_with($id, 'SEC') ||
        str_starts_with($id, 'SMI')
    ) {
        return 'secondary';
    }

    if (
        str_contains($combined, 'RENDAH') ||
        str_contains($combined, 'PRIMARY') ||
        str_contains($combined, '(PRI)') ||
        str_contains($combined, ' PRI') ||
        str_starts_with($id, 'SRI')
    ) {
        return 'primary';
    }

    if (
        str_contains($combined, 'PRESCHOOL') ||
        str_contains($combined, 'TADIKA') ||
        str_contains($combined, 'EARLY CHILDHOOD') ||
        str_contains($combined, 'AATECC') ||
        str_starts_with($id, 'AHP')
    ) {
        return 'preschool';
    }

    return 'others';
}

function serviceYears($date): ?int {
    if (empty($date)) return null;
    try {
        return date_diff(date_create($date), date_create(date('Y-m-d')))->y;
    } catch (Throwable $e) {
        return null;
    }
}

function findObserverIDByTeacher($conn, $teacherID) {
    if (empty($teacherID)) return null;

    $stmt = mysqli_prepare($conn, "
        SELECT observerID
        FROM observer
        WHERE teacherID = ?
        ORDER BY observerID DESC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "s", $teacherID);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return $row['observerID'] ?? null;
}

function findExternalObserverIDByTeacher($conn, $teacherID) {
    if (empty($teacherID)) return null;

    $stmt = mysqli_prepare($conn, "
        SELECT externalObserverID
        FROM external_observer
        WHERE teacherID = ?
        ORDER BY externalObserverID DESC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "s", $teacherID);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return $row['externalObserverID'] ?? null;
}

function saveObserverRecord($conn, $currentObserverID, $teacherID, $startDate, $endDate, $status) {
    if (empty($teacherID)) return null;

    if (!empty($currentObserverID)) {
        $stmt = mysqli_prepare($conn, "
            UPDATE observer
            SET teacherID = ?, startDate = ?, endDate = ?, status = ?
            WHERE observerID = ?
        ");
        mysqli_stmt_bind_param($stmt, "sssss", $teacherID, $startDate, $endDate, $status, $currentObserverID);
        mysqli_stmt_execute($stmt);

        return $currentObserverID;
    }

    $existingID = findObserverIDByTeacher($conn, $teacherID);
    if (!empty($existingID)) {
        $stmt = mysqli_prepare($conn, "
            UPDATE observer
            SET startDate = ?, endDate = ?, status = ?
            WHERE observerID = ?
        ");
        mysqli_stmt_bind_param($stmt, "ssss", $startDate, $endDate, $status, $existingID);
        mysqli_stmt_execute($stmt);

        return $existingID;
    }

    $observerID = generateNextId($conn, 'observer', 'observerID', 'OBS', 4);

    $stmt = mysqli_prepare($conn, "
        INSERT INTO observer (observerID, teacherID, startDate, endDate, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, "sssss", $observerID, $teacherID, $startDate, $endDate, $status);
    mysqli_stmt_execute($stmt);

    return $observerID;
}

function saveExternalObserverRecord($conn, $currentExternalObserverID, $teacherID, $startDate, $endDate, $status) {
    if (empty($teacherID)) return null;

    if (!empty($currentExternalObserverID)) {
        $stmt = mysqli_prepare($conn, "
            UPDATE external_observer
            SET teacherID = ?, startDate = ?, endDate = ?, status = ?
            WHERE externalObserverID = ?
        ");
        mysqli_stmt_bind_param($stmt, "sssss", $teacherID, $startDate, $endDate, $status, $currentExternalObserverID);
        mysqli_stmt_execute($stmt);

        return $currentExternalObserverID;
    }

    $existingID = findExternalObserverIDByTeacher($conn, $teacherID);
    if (!empty($existingID)) {
        $stmt = mysqli_prepare($conn, "
            UPDATE external_observer
            SET startDate = ?, endDate = ?, status = ?
            WHERE externalObserverID = ?
        ");
        mysqli_stmt_bind_param($stmt, "ssss", $startDate, $endDate, $status, $existingID);
        mysqli_stmt_execute($stmt);

        return $existingID;
    }

    $externalObserverID = generateNextId($conn, 'external_observer', 'externalObserverID', 'EXT', 4);

    $stmt = mysqli_prepare($conn, "
        INSERT INTO external_observer (externalObserverID, teacherID, startDate, endDate, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, "sssss", $externalObserverID, $teacherID, $startDate, $endDate, $status);
    mysqli_stmt_execute($stmt);

    return $externalObserverID;
}

$errorMessage = '';
$allowedTabs = ['secondary', 'primary', 'preschool', 'others', 'new_teacher'];

/* =========================
   SAVE OBSERVER ASSIGNMENT
========================= */

if (isset($_POST['assign_observer'])) {
    $returnTab = $_POST['return_tab'] ?? 'new_teacher';
    if (!in_array($returnTab, $allowedTabs, true)) {
        $returnTab = 'new_teacher';
    }
    $returnSchoolID = trim((string)($_POST['return_school_id'] ?? ''));

    try {
        verifyCsrfToken();

        $observerAssignmentID = $_POST['observerAssignmentID'] ?? '';
        $externalAssignmentID = $_POST['externalAssignmentID'] ?? '';
        $gn_id = $_POST['gn_id'] ?? '';
        $schoolID = $_POST['schoolID'] ?? '';

        $currentObserverID = $_POST['currentObserverID'] ?? '';
        $currentExternalObserverID = $_POST['currentExternalObserverID'] ?? '';

        $observerTeacherID = !empty($_POST['observer_teacher_id']) ? $_POST['observer_teacher_id'] : null;
        $observerStartDate = !empty($_POST['observerStartDate']) ? $_POST['observerStartDate'] : null;
        $observerEndDate = !empty($_POST['observerEndDate']) ? $_POST['observerEndDate'] : null;
        $observerStatus = $_POST['observerStatus'] ?? 'active';

        $externalTeacherID = !empty($_POST['external_teacher_id']) ? $_POST['external_teacher_id'] : null;
        $externalStartDate = !empty($_POST['externalStartDate']) ? $_POST['externalStartDate'] : null;
        $externalEndDate = !empty($_POST['externalEndDate']) ? $_POST['externalEndDate'] : null;
        $externalStatus = $_POST['externalStatus'] ?? 'active';

        $observerAssignedDate = !empty($_POST['observerAssignedDate']) ? $_POST['observerAssignedDate'] : $observerStartDate;
        $observerAssignmentEndDate = !empty($_POST['observerAssignmentEndDate']) ? $_POST['observerAssignmentEndDate'] : $observerEndDate;
        $observerAssignmentStatus = $_POST['observerAssignmentStatus'] ?? $observerStatus;

        $externalAssignedDate = !empty($_POST['externalAssignedDate']) ? $_POST['externalAssignedDate'] : $externalStartDate;
        $externalAssignmentEndDate = !empty($_POST['externalAssignmentEndDate']) ? $_POST['externalAssignmentEndDate'] : $externalEndDate;
        $externalAssignmentStatus = $_POST['externalAssignmentStatus'] ?? $externalStatus;

        $hasObserverNow = !empty($observerTeacherID);
        $hasExternalNow = !empty($externalTeacherID);

        if (!$hasObserverNow && !$hasExternalNow) {
            throw new RuntimeException('Please select at least one observer or external observer.');
        }
        if ($hasObserverNow && empty($observerStartDate)) {
            throw new RuntimeException('Please enter observer start date.');
        }
        if ($hasObserverNow && empty($observerAssignedDate)) {
            throw new RuntimeException('Please enter observer assignment date.');
        }
        if ($hasExternalNow && empty($externalStartDate)) {
            throw new RuntimeException('Please enter external observer start date.');
        }
        if ($hasExternalNow && empty($externalAssignedDate)) {
            throw new RuntimeException('Please enter external observer assignment date.');
        }

        if ($hasObserverNow && !empty($observerEndDate) && $observerEndDate <= $observerStartDate) {
            throw new RuntimeException('Observer end date must be after observer start date.');
        }
        if ($hasObserverNow && !empty($observerAssignmentEndDate) && $observerAssignmentEndDate <= $observerAssignedDate) {
            throw new RuntimeException('Observer assignment end date must be after assignment date.');
        }
        if ($hasExternalNow && !empty($externalEndDate) && $externalEndDate <= $externalStartDate) {
            throw new RuntimeException('External observer end date must be after external observer start date.');
        }
        if ($hasExternalNow && !empty($externalAssignmentEndDate) && $externalAssignmentEndDate <= $externalAssignedDate) {
            throw new RuntimeException('External assignment end date must be after assignment date.');
        }
        if ($hasObserverNow && $hasExternalNow && $observerTeacherID === $externalTeacherID) {
            throw new RuntimeException('Observer and external observer must be two different teachers.');
        }

        mysqli_begin_transaction($conn);

        if ($hasObserverNow) {
            $observerID = saveObserverRecord(
                $conn,
                $currentObserverID,
                $observerTeacherID,
                $observerStartDate,
                $observerEndDate,
                $observerStatus
            );

            $nullExternalObserverID = null;

            if (!empty($observerAssignmentID)) {
                $stmt = mysqli_prepare($conn, "
                    UPDATE observer_assignment
                    SET observerID = ?,
                        externalObserverID = ?,
                        assignedDate = ?,
                        endDate = ?,
                        status = ?
                    WHERE assignmentID = ?
                ");
                mysqli_stmt_bind_param($stmt, "ssssss", $observerID, $nullExternalObserverID, $observerAssignedDate, $observerAssignmentEndDate, $observerAssignmentStatus, $observerAssignmentID);
                mysqli_stmt_execute($stmt);
            } else {
                $newObserverAssignmentID = generateNextId($conn, 'observer_assignment', 'assignmentID', 'ASG', 4);

                $stmt = mysqli_prepare($conn, "
                    INSERT INTO observer_assignment
                    (assignmentID, gn_id, observerID, externalObserverID, assignedDate, endDate, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param($stmt, "sssssss", $newObserverAssignmentID, $gn_id, $observerID, $nullExternalObserverID, $observerAssignedDate, $observerAssignmentEndDate, $observerAssignmentStatus);
                mysqli_stmt_execute($stmt);
            }
        }

        if ($hasExternalNow) {
            $externalObserverID = saveExternalObserverRecord(
                $conn,
                $currentExternalObserverID,
                $externalTeacherID,
                $externalStartDate,
                $externalEndDate,
                $externalStatus
            );

            $nullObserverID = null;

            if (!empty($externalAssignmentID)) {
                $stmt = mysqli_prepare($conn, "
                    UPDATE observer_assignment
                    SET observerID = ?,
                        externalObserverID = ?,
                        assignedDate = ?,
                        endDate = ?,
                        status = ?
                    WHERE assignmentID = ?
                ");
                mysqli_stmt_bind_param($stmt, "ssssss", $nullObserverID, $externalObserverID, $externalAssignedDate, $externalAssignmentEndDate, $externalAssignmentStatus, $externalAssignmentID);
                mysqli_stmt_execute($stmt);
            } else {
                $newExternalAssignmentID = generateNextId($conn, 'observer_assignment', 'assignmentID', 'ASG', 4);

                $stmt = mysqli_prepare($conn, "
                    INSERT INTO observer_assignment
                    (assignmentID, gn_id, observerID, externalObserverID, assignedDate, endDate, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param($stmt, "sssssss", $newExternalAssignmentID, $gn_id, $nullObserverID, $externalObserverID, $externalAssignedDate, $externalAssignmentEndDate, $externalAssignmentStatus);
                mysqli_stmt_execute($stmt);
            }
        }

        mysqli_commit($conn);

        $params = ['tab' => $returnTab, 'assigned' => 1];
        if ($returnSchoolID !== '' && $returnTab !== 'new_teacher') {
            $params['schoolID'] = $returnSchoolID;
        }

        header('Location: teacher.php?' . http_build_query($params));
        exit();
    } catch (Throwable $e) {
        try { mysqli_rollback($conn); } catch (Throwable $ignored) {}
        $errorMessage = 'Assignment cannot be saved: ' . $e->getMessage();
    }
}

/* =========================
   DATA FOR TABS
========================= */

$activeTab = $_GET['tab'] ?? 'secondary';
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'secondary';
}

$selectedSchoolID = trim((string)($_GET['schoolID'] ?? ''));

$tabLabels = [
    'secondary' => 'Secondary School',
    'primary' => 'Primary School',
    'preschool' => 'Preschool',
    'others' => 'Others',
    'new_teacher' => 'New Teacher'
];

$schoolsByCategory = [
    'secondary' => [],
    'primary' => [],
    'preschool' => [],
    'others' => []
];

$schoolsByID = [];
$teachersBySchool = [];
$newTeachers = [];
$newTeachersBySchool = [];
$eligibleTeacherList = [];

$totalSchools = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM school"))['total'] ?? 0);
$totalTeacherAll = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM teacher"))['total'] ?? 0);
$totalGuruNewAll = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM guru_new"))['total'] ?? 0);
$totalActiveAssignment = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM observer_assignment WHERE status='active'"))['total'] ?? 0);

$schoolResult = mysqli_query($conn, "
    SELECT
        s.schoolID,
        s.schoolName,
        s.schoolAddress,
        s.totalTeacher,
        s.phoneNumber,
        COALESCE(tc.teacher_total, 0) AS teacher_total,
        COALESCE(g.new_teacher_total, 0) AS new_teacher_total
    FROM school s
    LEFT JOIN (
        SELECT schoolID, COUNT(DISTINCT teacherID) AS teacher_total
        FROM assign
        WHERE status IN ('Aktif', 'active')
        GROUP BY schoolID
    ) tc ON tc.schoolID = s.schoolID
    LEFT JOIN (
        SELECT schoolID, COUNT(*) AS new_teacher_total
        FROM guru_new
        GROUP BY schoolID
    ) g ON g.schoolID = s.schoolID
    ORDER BY s.schoolName ASC
");

while ($school = mysqli_fetch_assoc($schoolResult)) {
    $category = classifySchool($school['schoolID'] ?? '', $school['schoolName'] ?? '');
    $school['category'] = $category;
    $schoolsByCategory[$category][] = $school;
    $schoolsByID[$school['schoolID']] = $school;
}

$selectedSchool = null;
$selectedSchoolCategory = '';
if ($selectedSchoolID !== '' && isset($schoolsByID[$selectedSchoolID])) {
    $selectedSchool = $schoolsByID[$selectedSchoolID];
    $selectedSchoolCategory = $selectedSchool['category'];
    if ($activeTab !== $selectedSchoolCategory && $activeTab !== 'new_teacher') {
        $activeTab = $selectedSchoolCategory;
    }
}

/* Only load teacher/new-teacher detail rows when the user opens a school or
   the New Teacher tab. The initial school list uses the stored school counts. */
if ($selectedSchoolID !== '') {
    $safeSelectedSchoolID = mysqli_real_escape_string($conn, $selectedSchoolID);
    $teacherResult = mysqli_query($conn, "
        SELECT
            t.teacherID,
            t.teacherName,
            t.ICNumber,
            t.phoneNumber,
            t.email,
            t.appointedDate,
            t.serviceDate,
            tcs.schoolID,
            t.resignation_request_status,
            s.schoolName
        FROM teacher t
        INNER JOIN v_teacher_current_school tcs ON t.teacherID = tcs.teacherID
        LEFT JOIN school s ON tcs.schoolID = s.schoolID
        WHERE tcs.schoolID = '$safeSelectedSchoolID'
        ORDER BY t.teacherName ASC
    ");

    while ($teacher = mysqli_fetch_assoc($teacherResult)) {
        $teachersBySchool[$selectedSchoolID][] = $teacher;
    }
}

$needsNewTeacherDetails = ($selectedSchoolID !== '' || $activeTab === 'new_teacher');
if ($needsNewTeacherDetails) {
    $eligibleResult = mysqli_query($conn, "
        SELECT
            t.teacherID,
            t.teacherName,
            tcs.schoolID,
            s.schoolName,
            t.appointedDate,
            TIMESTAMPDIFF(YEAR, t.appointedDate, CURDATE()) AS serviceYears
        FROM teacher t
        LEFT JOIN v_teacher_current_school tcs ON t.teacherID = tcs.teacherID
        LEFT JOIN school s ON tcs.schoolID = s.schoolID
        WHERE t.appointedDate IS NOT NULL
          AND t.appointedDate < DATE_SUB(CURDATE(), INTERVAL 10 YEAR)
        ORDER BY t.teacherName ASC
    ");

    while ($row = mysqli_fetch_assoc($eligibleResult)) {
        $eligibleTeacherList[] = $row;
    }

    $newTeacherWhere = '';
    if ($selectedSchoolID !== '' && $activeTab !== 'new_teacher') {
        $safeSelectedSchoolID = mysqli_real_escape_string($conn, $selectedSchoolID);
        $newTeacherWhere = "WHERE g.schoolID = '$safeSelectedSchoolID'";
    }

    $newTeacherResult = mysqli_query($conn, "
        SELECT
            g.gn_id,
            g.gn_name,
            g.email,
            g.phone_number,
            g.ic_number,
            g.gender,
            g.appointed_date,
            g.current_status,
            g.schoolID,
            s.schoolName,

            oaObs.assignmentID AS observerAssignmentID,
            oaObs.assignedDate AS observerAssignedDate,
            oaObs.endDate AS observerAssignmentEndDate,
            oaObs.status AS observerAssignmentStatus,

            o.observerID,
            o.startDate AS observerStartDate,
            o.endDate AS observerEndDate,
            o.status AS observerStatus,
            obsT.teacherID AS observerTeacherID,
            obsT.teacherName AS observerName,
            obsSchool.schoolID AS observerSchoolID,

            oaExt.assignmentID AS externalAssignmentID,
            oaExt.assignedDate AS externalAssignedDate,
            oaExt.endDate AS externalAssignmentEndDate,
            oaExt.status AS externalAssignmentStatus,

            eo.externalObserverID,
            eo.startDate AS externalStartDate,
            eo.endDate AS externalEndDate,
            eo.status AS externalStatus,
            extT.teacherID AS externalTeacherID,
            extT.teacherName AS externalObserverName,
            extSchool.schoolID AS externalObserverSchoolID
        FROM guru_new g
        LEFT JOIN school s ON g.schoolID = s.schoolID

        LEFT JOIN observer_assignment oaObs
            ON oaObs.assignmentID = (
                SELECT oa1.assignmentID
                FROM observer_assignment oa1
                WHERE oa1.gn_id = g.gn_id
                  AND oa1.observerID IS NOT NULL
                  AND oa1.status = 'active'
                ORDER BY oa1.assignedDate DESC, oa1.assignmentID DESC
                LIMIT 1
            )
        LEFT JOIN observer o ON oaObs.observerID = o.observerID
        LEFT JOIN teacher obsT ON o.teacherID = obsT.teacherID
        LEFT JOIN v_teacher_current_school obsSchool ON obsT.teacherID = obsSchool.teacherID

        LEFT JOIN observer_assignment oaExt
            ON oaExt.assignmentID = (
                SELECT oa2.assignmentID
                FROM observer_assignment oa2
                WHERE oa2.gn_id = g.gn_id
                  AND oa2.externalObserverID IS NOT NULL
                  AND oa2.status = 'active'
                ORDER BY oa2.assignedDate DESC, oa2.assignmentID DESC
                LIMIT 1
            )
        LEFT JOIN external_observer eo ON oaExt.externalObserverID = eo.externalObserverID
        LEFT JOIN teacher extT ON eo.teacherID = extT.teacherID
        LEFT JOIN v_teacher_current_school extSchool ON extT.teacherID = extSchool.teacherID

        $newTeacherWhere
        ORDER BY g.gn_name ASC
    ");

    while ($row = mysqli_fetch_assoc($newTeacherResult)) {
        $newTeachers[] = $row;
        $newTeachersBySchool[(string)($row['schoolID'] ?? '')][] = $row;
    }
}

function tabCount(array $schoolsByCategory, string $category): int {
    return count($schoolsByCategory[$category] ?? []);
}

function teacherStatusClass(array $teacher): string {
    $resignationStatus = strtolower((string)($teacher['resignation_request_status'] ?? ''));
    return in_array($resignationStatus, ['approved', 'resigned', 'inactive'], true) ? 'inactive' : 'active';
}

function renderTeacherRows(array $teachers): void {
    if (empty($teachers)) {
        echo '<div class="empty-state">No teacher found in this school.</div>';
        return;
    }

    foreach ($teachers as $t) {
        $teacherServiceYears = serviceYears($t['appointedDate'] ?? null);
        $teacherStatus = teacherStatusClass($t);
        ?>
        <div class="teacher-row compact-teacher-row">
            <div class="teacher-avatar"><?php echo e(strtoupper(substr((string)$t['teacherName'], 0, 1))); ?></div>
            <div>
                <strong><?php echo e($t['teacherName']); ?></strong>
                <span><?php echo e($t['teacherID']); ?> • <?php echo e($t['email']); ?></span>
                <small>
                    Appointed: <?php echo e($t['appointedDate'] ?: '-'); ?>
                    <?php if ($teacherServiceYears !== null) { ?> • <?php echo e($teacherServiceYears); ?> years service<?php } ?>
                </small>
            </div>
            <em class="teacher-status-pill <?php echo $teacherStatus === 'active' ? 'active' : 'inactive'; ?>"><?php echo e(ucfirst($teacherStatus)); ?></em>
        </div>
        <?php
    }
}

function renderSchoolNewTeacherRows(array $newTeachers): void {
    if (empty($newTeachers)) {
        echo '<div class="empty-state">No new teacher found in this school.</div>';
        return;
    }

    foreach ($newTeachers as $g) {
        $status = strtolower((string)($g['current_status'] ?? 'Inactive'));
        $statusClass = $status === 'active' ? 'active' : ($status === 'complete' ? 'complete' : 'inactive');
        ?>
        <div class="teacher-row compact-teacher-row school-new-teacher-row">
            <div class="teacher-avatar"><?php echo e(strtoupper(substr((string)$g['gn_name'], 0, 1))); ?></div>
            <div>
                <strong><?php echo e($g['gn_name']); ?></strong>
                <span><?php echo e($g['gn_id']); ?> • <?php echo e($g['email']); ?></span>
                <small>
                    Phone: <?php echo e($g['phone_number'] ?: '-'); ?>
                    <?php if (!empty($g['hire_date'])) { ?> • Appointed: <?php echo e($g['hire_date']); ?><?php } ?>
                </small>
            </div>
            <em class="teacher-status-pill <?php echo e($statusClass); ?>"><?php echo e(ucfirst($status ?: 'Inactive')); ?></em>
        </div>
        <?php
    }
}

function renderNewTeacherCard(array $g, array $eligibleTeacherList, string $contextPrefix, string $returnTab, string $returnSchoolID = ''): void {
    $hasAnyAssignment = !empty($g['observerAssignmentID']) || !empty($g['externalAssignmentID']) || !empty($g['observerName']) || !empty($g['externalObserverName']);
    $safeId = preg_replace('/[^A-Za-z0-9]/', '', (string)$g['gn_id']);
    $formId = 'assignForm' . $contextPrefix . $safeId;
    $newTeacherSearchText = implode(' ', [
        $g['gn_name'] ?? '',
        $g['gn_id'] ?? '',
        $g['email'] ?? '',
        $g['phone_number'] ?? '',
        $g['schoolName'] ?? '',
        $g['schoolID'] ?? '',
        $g['observerName'] ?? '',
        $g['externalObserverName'] ?? ''
    ]);
    ?>
    <div
        class="new-teacher-card filterable-new-teacher-card"
        data-has-assignment="<?php echo $hasAnyAssignment ? '1' : '0'; ?>"
        data-assignment="<?php echo $hasAnyAssignment ? 'assigned' : 'pending'; ?>"
        data-has-observer="<?php echo !empty($g['observerName']) ? '1' : '0'; ?>"
        data-has-external="<?php echo !empty($g['externalObserverName']) ? '1' : '0'; ?>"
        data-search="<?php echo e($newTeacherSearchText); ?>"
    >
        <div class="new-teacher-head list-mode-head">
            <div class="teacher-avatar"><?php echo e(strtoupper(substr((string)$g['gn_name'], 0, 1))); ?></div>

            <div>
                <strong><?php echo e($g['gn_name']); ?></strong>
                <span><?php echo e($g['gn_id']); ?> • <?php echo e($g['schoolName'] ?: $g['schoolID']); ?></span>
                <small>
                    Observer: <?php echo !empty($g['observerName']) ? e($g['observerName']) : 'Not assigned'; ?>
                    • External: <?php echo !empty($g['externalObserverName']) ? e($g['externalObserverName']) : 'Not assigned'; ?>
                </small>
            </div>

            <div class="new-teacher-actions">
                <?php if ($hasAnyAssignment) { ?>
                    <em class="original-badge">Assigned</em>
                <?php } else { ?>
                    <em class="replace-badge">Pending</em>
                <?php } ?>

                <button type="button" class="assign-toggle-btn" data-mode="<?php echo $hasAnyAssignment ? 'edit' : 'assign'; ?>" data-target="<?php echo e($formId); ?>">
                    <?php echo $hasAnyAssignment ? 'Edit' : 'Assign'; ?>
                </button>
            </div>
        </div>

        <form method="POST" class="assignment-form assignment-modal observer-assignment-form" id="<?php echo e($formId); ?>">
            <div class="assignment-modal-shell">
                <div class="assignment-modal-header">
                    <div>
                        <span>Observer Assignment</span>
                        <h3><?php echo e($g['gn_name']); ?></h3>
                        <p>Choose an observer, external observer, or both. The list only shows eligible teachers from other schools.</p>
                    </div>
                    <button type="button" class="assignment-modal-close" data-close-target="<?php echo e($formId); ?>" aria-label="Close assignment form">×</button>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="observerAssignmentID" value="<?php echo e($g['observerAssignmentID'] ?? ''); ?>">
                <input type="hidden" name="externalAssignmentID" value="<?php echo e($g['externalAssignmentID'] ?? ''); ?>">
                <input type="hidden" name="gn_id" value="<?php echo e($g['gn_id']); ?>">
                <input type="hidden" name="schoolID" value="<?php echo e($g['schoolID']); ?>">
                <input type="hidden" name="return_tab" value="<?php echo e($returnTab); ?>">
                <input type="hidden" name="return_school_id" value="<?php echo e($returnSchoolID); ?>">
                <input type="hidden" name="currentObserverID" value="<?php echo e($g['observerID'] ?? ''); ?>">
                <input type="hidden" name="currentExternalObserverID" value="<?php echo e($g['externalObserverID'] ?? ''); ?>">

                <div class="assignment-role-grid">
                    <div class="role-card observer-role-card">
                        <div class="role-card-header">
                            <div>
                                <span class="role-kicker">Internal Role</span>
                                <h4>Observer</h4>
                            </div>
                            <em>Optional</em>
                        </div>

                        <div class="table-field-box observer-table-box">
                            <div class="field-box-title"><strong>Observer Details</strong></div>

                            <div class="form-grid-small">
                                <div>
                                    <label>Observer Teacher</label>
                                    <select name="observer_teacher_id" class="observer-select">
                                        <option value="">Select observer / optional</option>
                                        <?php foreach ($eligibleTeacherList as $o) { ?>
                                            <?php if (($o['schoolID'] ?? '') === ($g['schoolID'] ?? '')) continue; ?>
                                            <option value="<?php echo e($o['teacherID']); ?>" data-teacher-id="<?php echo e($o['teacherID']); ?>" <?php echo (($g['observerTeacherID'] ?? '') == $o['teacherID']) ? 'selected' : ''; ?>>
                                                <?php echo e($o['teacherName']); ?> - <?php echo e($o['schoolName'] ?: $o['schoolID']); ?> (<?php echo e($o['serviceYears']); ?> yrs)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div>
                                    <label>Observer Start Date</label>
                                    <input type="date" name="observerStartDate" class="observer-start-date" value="<?php echo e($g['observerStartDate'] ?? ''); ?>">
                                </div>

                                <div>
                                    <label>Observer End Date</label>
                                    <input type="date" name="observerEndDate" value="<?php echo e($g['observerEndDate'] ?? ''); ?>">
                                </div>

                                <input type="hidden" name="observerStatus" value="<?php echo e($g['observerStatus'] ?? 'active'); ?>">
                            </div>
                        </div>

                        <div class="table-field-box assignment-table-box">
                            <div class="field-box-title"><strong>Observer Assignment Details</strong></div>
                            <div class="form-grid-small">
                                <div>
                                    <label>Assignment Date</label>
                                    <input type="date" name="observerAssignedDate" value="<?php echo e($g['observerAssignedDate'] ?? $g['observerStartDate'] ?? date('Y-m-d')); ?>">
                                </div>
                                <div>
                                    <label>Assignment End Date</label>
                                    <input type="date" name="observerAssignmentEndDate" value="<?php echo e($g['observerAssignmentEndDate'] ?? $g['observerEndDate'] ?? ''); ?>">
                                </div>
                                <input type="hidden" name="observerAssignmentStatus" value="<?php echo e($g['observerAssignmentStatus'] ?? 'active'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="role-card external-role-card">
                        <div class="role-card-header">
                            <div>
                                <span class="role-kicker">External Role</span>
                                <h4>External Observer</h4>
                            </div>
                            <em>Optional</em>
                        </div>

                        <div class="table-field-box external-table-box">
                            <div class="field-box-title"><strong>External Observer Details</strong></div>

                            <div class="form-grid-small">
                                <div>
                                    <label>External Observer Teacher</label>
                                    <select name="external_teacher_id" class="external-select">
                                        <option value="">Select external observer / optional</option>
                                        <?php foreach ($eligibleTeacherList as $eo) { ?>
                                            <?php if (($eo['schoolID'] ?? '') === ($g['schoolID'] ?? '')) continue; ?>
                                            <option value="<?php echo e($eo['teacherID']); ?>" data-teacher-id="<?php echo e($eo['teacherID']); ?>" <?php echo (($g['externalTeacherID'] ?? '') == $eo['teacherID']) ? 'selected' : ''; ?>>
                                                <?php echo e($eo['teacherName']); ?> - <?php echo e($eo['schoolName'] ?: $eo['schoolID']); ?> (<?php echo e($eo['serviceYears']); ?> yrs)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div>
                                    <label>External Start Date</label>
                                    <input type="date" name="externalStartDate" class="external-start-date" value="<?php echo e($g['externalStartDate'] ?? ''); ?>">
                                </div>

                                <div>
                                    <label>External End Date</label>
                                    <input type="date" name="externalEndDate" value="<?php echo e($g['externalEndDate'] ?? ''); ?>">
                                </div>

                                <input type="hidden" name="externalStatus" value="<?php echo e($g['externalStatus'] ?? 'active'); ?>">
                            </div>
                        </div>

                        <div class="table-field-box assignment-table-box">
                            <div class="field-box-title"><strong>External Assignment Details</strong></div>
                            <div class="form-grid-small">
                                <div>
                                    <label>Assignment Date</label>
                                    <input type="date" name="externalAssignedDate" value="<?php echo e($g['externalAssignedDate'] ?? $g['externalStartDate'] ?? date('Y-m-d')); ?>">
                                </div>
                                <div>
                                    <label>Assignment End Date</label>
                                    <input type="date" name="externalAssignmentEndDate" value="<?php echo e($g['externalAssignmentEndDate'] ?? $g['externalEndDate'] ?? ''); ?>">
                                </div>
                                <input type="hidden" name="externalAssignmentStatus" value="<?php echo e($g['externalAssignmentStatus'] ?? 'active'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="assignment-modal-actions">
                    <button type="button" class="modal-cancel-btn" data-close-target="<?php echo e($formId); ?>">Cancel</button>
                    <button type="submit" name="assign_observer" class="primary-btn save-assignment-btn">Save Assignment</button>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function renderNewTeacherList(array $newTeachers, array $eligibleTeacherList, string $contextPrefix, string $returnTab, string $returnSchoolID = ''): void {
    if (empty($newTeachers)) {
        echo '<div class="empty-state">No new teacher found.</div>';
        return;
    }

    foreach ($newTeachers as $g) {
        renderNewTeacherCard($g, $eligibleTeacherList, $contextPrefix, $returnTab, $returnSchoolID);
    }
}
