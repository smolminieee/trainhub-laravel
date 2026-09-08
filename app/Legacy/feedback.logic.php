<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!$conn->select_db(TRAINHUB_DATABASE_NAME)) {
        throw new RuntimeException('Unable to select the configured TrainHub database.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database connection error.');
}

date_default_timezone_set('Asia/Kuala_Lumpur');

$isAnswerRequest = isset($_GET['answer']) && $_GET['answer'] == '1';
$isResponseView = isset($_GET['responses']) && $_GET['responses'] == '1';
$isPdfView = isset($_GET['export_pdf']) && $_GET['export_pdf'] == '1';
$selectedFormID = trim((string)($_GET['formID'] ?? $_GET['form_id'] ?? ''));
$privacyMode = strtolower(trim((string)($_GET['privacy'] ?? 'anonymous')));
$privacyMode = in_array($privacyMode, ['anonymous', 'details'], true) ? $privacyMode : 'anonymous';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


function getCurrentStaffID(): string {
    return (string)($_SESSION['staffID'] ?? $_SESSION['staff_id'] ?? '');
}

function getCurrentStaffName(mysqli $conn, string $staffID): string {
    if ($staffID === '') {
        return '';
    }

    $stmt = mysqli_prepare($conn, "SELECT staffName FROM staff_edu WHERE staffID = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $staffID);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return (string)($row['staffName'] ?? $staffID);
}

function normalizeFeedbackType(string $type): string {
    $type = strtolower(trim($type));
    return $type === 'participant' ? 'participant' : 'staff_edu';
}

function isCoordinatorFeedbackType(string $type): bool {
    return in_array(strtolower(trim($type)), ['staff_edu', 'pic', 'coordinator'], true);
}

function feedbackTypeLabel(string $type): string {
    return strtolower(trim($type)) === 'participant' ? 'Participant Feedback' : 'Coordinator Review';
}

function feedbackTypeBadge(string $type): string {
    return strtolower(trim($type)) === 'participant' ? 'Participant' : 'Coordinator';
}

function responseTypeCondition(string $alias = 'fr'): string {
    return "LOWER(COALESCE($alias.responseType, 'participant')) IN ('staff_edu','pic','coordinator')";
}

function setFeedbackFlash(string $type, string $text): void {
    $_SESSION['feedback_flash'] = [
        'type' => $type,
        'text' => $text
    ];
}

function redirectFeedback(string $url = 'feedback.php'): void {
    header('Location: ' . $url);
    exit;
}

function getBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/'));
    $dir = rtrim($dir, '/');
    return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
}

function tableExists(mysqli $conn, string $tableName): bool {
    static $cache = [];
    $key = strtolower($tableName);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $safe = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$safe'");
    return $cache[$key] = (bool)($result && mysqli_num_rows($result) > 0);
}

/**
 * Older TrainHub database builds used chk_feedback_question_1 to require questionImage
 * whenever questionType = image. TrainHub now uses the image type for a
 * responder-upload answer, so a prompt/reference image is optional.
 *
 * This performs a one-time compatibility update when the old CHECK still
 * exists. The standalone SQL patch in database_patches/ can also be used.
 */
function ensureFeedbackImageAnswerSchema(mysqli $conn): void {
    $checkStmt = mysqli_prepare($conn, "
        SELECT CONSTRAINT_NAME
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'feedback_question'
          AND CONSTRAINT_TYPE = 'CHECK'
          AND CONSTRAINT_NAME = 'chk_feedback_question_1'
        LIMIT 1
    ");
    mysqli_stmt_execute($checkStmt);
    $constraint = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));

    if (!$constraint) {
        return;
    }

    $dropErrors = [];
    foreach ([
        "ALTER TABLE feedback_question DROP CONSTRAINT chk_feedback_question_1",
        "ALTER TABLE feedback_question DROP CHECK chk_feedback_question_1"
    ] as $sql) {
        try {
            mysqli_query($conn, $sql);
            return;
        } catch (Throwable $e) {
            $dropErrors[] = $e->getMessage();
        }
    }

    throw new RuntimeException(
        'The feedback image-answer database rule is outdated. Run database_patches/fix_feedback_image_answer_constraint.sql once in phpMyAdmin.'
    );
}

function nextId(mysqli $conn, string $table, string $column, string $prefix, int $digits = 4): string {
    $safeTable = '`' . str_replace('`', '``', $table) . '`';
    $safeColumn = '`' . str_replace('`', '``', $column) . '`';
    $start = strlen($prefix) + 1;

    // These TrainHub ID columns use one prefix per table. Ordering the numeric
    // suffix avoids REGEXP/LIKE comparisons with bound parameters, which can
    // otherwise trigger collation errors in the combined database.
    $prefixLength = strlen($prefix);
    $stmt = mysqli_prepare($conn, "
        SELECT $safeColumn AS latestID
        FROM $safeTable
        WHERE BINARY LEFT($safeColumn, ?) = BINARY ?
        ORDER BY CAST(SUBSTRING($safeColumn, ?) AS UNSIGNED) DESC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'isi', $prefixLength, $prefix, $start);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $number = 1;
    if ($row && !empty($row['latestID']) && str_starts_with((string)$row['latestID'], $prefix)) {
        $number = ((int)substr((string)$row['latestID'], strlen($prefix))) + 1;
    }

    return $prefix . str_pad((string)$number, $digits, '0', STR_PAD_LEFT);
}

function countFormResponses(mysqli $conn, string $formID): int {
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM feedback_response fr
        JOIN feedback_question fq ON fr.questionID = fq.questionID
        JOIN feedback_category fc ON fq.categoryID = fc.categoryID
        WHERE fc.formID = ?
    ");
    mysqli_stmt_bind_param($stmt, 's', $formID);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return (int)($row['total'] ?? 0);
}

function loadFormInfo(mysqli $conn, string $formID): ?array {
    if ($formID === '') {
        return null;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT
            ff.formID,
            ff.title,
            ff.createdDate,
            ff.sessionID,
            ff.courseID,
            ff.feedbackType,
            ff.generatedPdf,
            ff.createdByStaff,
            c.staffAttendeeAssignedBy,
            coordinator.staffName AS coordinatorName,
            coordinator.department AS coordinatorDepartment,
            coordinator.email AS coordinatorEmail,
            creator.staffName AS creatorName,
            creator.department AS creatorDepartment,
            creator.email AS creatorEmail,
            cs.sessionName,
            cs.sessionDate,
            cs.startTime,
            cs.endTime,
            COALESCE(ff.courseID, cs.courseID) AS resolvedCourseID,
            c.courseName,
            GROUP_CONCAT(DISTINCT t.trainerName ORDER BY t.trainerName SEPARATOR ', ') AS trainerNames
        FROM feedback_form ff
        LEFT JOIN course_session cs ON ff.sessionID = cs.sessionID
        LEFT JOIN course c ON COALESCE(ff.courseID, cs.courseID) = c.courseID
        LEFT JOIN staff_edu coordinator ON c.staffAttendeeAssignedBy = coordinator.staffID
        LEFT JOIN staff_edu creator ON ff.createdByStaff = creator.staffID
        LEFT JOIN session_trainer st ON ff.sessionID = st.sessionID
        LEFT JOIN trainer t ON st.trainerID = t.trainerID
        WHERE ff.formID = ?
        GROUP BY
            ff.formID, ff.title, ff.createdDate, ff.sessionID, ff.courseID, ff.feedbackType,
            ff.generatedPdf, ff.createdByStaff, c.staffAttendeeAssignedBy,
            coordinator.staffName, coordinator.department, coordinator.email,
            creator.staffName, creator.department, creator.email,
            cs.sessionName, cs.sessionDate, cs.startTime, cs.endTime,
            resolvedCourseID, c.courseName
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 's', $formID);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return $row ?: null;
}

function loadQuestionGroups(mysqli $conn, string $formID): array {
    $groups = [];
    $stmt = mysqli_prepare($conn, "
        SELECT
            fc.categoryID,
            fc.categoryName,
            fc.categoryOrder,
            fq.questionID,
            fq.questionText,
            fq.questionType,
            fq.questionImage,
            fq.isRequired,
            fq.questionOrder
        FROM feedback_category fc
        JOIN feedback_question fq ON fc.categoryID = fq.categoryID
        WHERE fc.formID = ?
        ORDER BY fc.categoryOrder ASC, fq.questionOrder ASC
    ");
    mysqli_stmt_bind_param($stmt, 's', $formID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $categoryID = $row['categoryID'];
        if (!isset($groups[$categoryID])) {
            $groups[$categoryID] = [
                'categoryID' => $row['categoryID'],
                'categoryName' => $row['categoryName'],
                'categoryOrder' => $row['categoryOrder'],
                'questions' => []
            ];
        }
        $groups[$categoryID]['questions'][] = $row;
    }

    return $groups;
}

function saveQuestionImage(string $questionID, int $categoryIndex, int $questionIndex): ?string {
    if (empty($_FILES['question_image']['name'][$categoryIndex][$questionIndex])) {
        return null;
    }

    $error = $_FILES['question_image']['error'][$categoryIndex][$questionIndex] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmpName = $_FILES['question_image']['tmp_name'][$categoryIndex][$questionIndex] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return null;
    }

    $originalName = $_FILES['question_image']['name'][$categoryIndex][$questionIndex] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        return null;
    }

    $dir = public_path('uploads/feedback_questions');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $fileName = $questionID . '_' . time() . '.' . $extension;
    $target = $dir . '/' . $fileName;

    if (!move_uploaded_file($tmpName, $target)) {
        return null;
    }

    return 'uploads/feedback_questions/' . $fileName;
}

function hasResponseImageUpload(string $questionID): bool {
    $error = $_FILES['answer_image']['error'][$questionID] ?? UPLOAD_ERR_NO_FILE;
    return $error === UPLOAD_ERR_OK && !empty($_FILES['answer_image']['tmp_name'][$questionID]);
}

function saveResponseImage(string $questionID, string $respondentKey): ?string {
    if (!hasResponseImageUpload($questionID)) {
        return null;
    }

    $tmpName = $_FILES['answer_image']['tmp_name'][$questionID] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return null;
    }

    $originalName = $_FILES['answer_image']['name'][$questionID] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('Image upload must be JPG, PNG, GIF, or WEBP.');
    }

    $dir = public_path('uploads/feedback_answers');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $safeRespondent = preg_replace('/[^A-Za-z0-9_-]/', '', $respondentKey) ?: 'respondent';
    $fileName = $questionID . '_' . $safeRespondent . '_' . time() . '.' . $extension;
    $target = $dir . '/' . $fileName;

    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('Unable to upload feedback image.');
    }

    return 'uploads/feedback_answers/' . $fileName;
}

function isImagePath(?string $value): bool {
    if (!$value) {
        return false;
    }
    return (bool)preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $value);
}

function resolveParticipant(mysqli $conn, array $form, string $respondentType, string $respondentID): ?array {
    $courseID = (string)($form['resolvedCourseID'] ?? $form['courseID'] ?? '');
    $respondentType = strtolower(trim($respondentType));
    $respondentID = trim($respondentID);

    if ($courseID === '' || $respondentID === '') {
        return null;
    }

    if ($respondentType === 'teacher') {
        $sql = "SELECT * FROM course_participant WHERE teacherID = ? AND courseID = ? AND LOWER(participantType) = 'teacher' LIMIT 1";
    } elseif ($respondentType === 'staff') {
        $sql = "SELECT * FROM course_participant WHERE staffID = ? AND courseID = ? AND LOWER(participantType) = 'staff' LIMIT 1";
    } elseif ($respondentType === 'new_teacher') {
        $sql = "SELECT * FROM course_participant WHERE gn_id = ? AND courseID = ? AND LOWER(participantType) = 'new_teacher' LIMIT 1";
    } elseif ($respondentType === 'public') {
        $sql = "SELECT * FROM course_participant WHERE CAST(outsider_id AS CHAR) = ? AND courseID = ? AND LOWER(participantType) = 'public' LIMIT 1";
    } else {
        return null;
    }

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $respondentID, $courseID);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return $row ?: null;
}

function hasApprovedAttendance(mysqli $conn, array $participant, string $sessionID): bool {
    if ($sessionID === '') {
        return false;
    }

    $type = strtolower((string)($participant['participantType'] ?? ''));

    if ($type === 'teacher') {
        $teacherID = (string)($participant['teacherID'] ?? '');
        if ($teacherID === '') return false;
        $stmt = mysqli_prepare($conn, "SELECT 1 FROM attendance WHERE teacher_id = ? AND session_id = ? AND attendance_status = 'approved' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ss', $teacherID, $sessionID);
    } elseif ($type === 'staff') {
        if (!tableExists($conn, 'attendance_staff')) {
            return false;
        }
        $staffID = (string)($participant['staffID'] ?? '');
        if ($staffID === '') return false;
        $stmt = mysqli_prepare($conn, "SELECT 1 FROM attendance_staff WHERE staffID = ? AND session_id = ? AND attendance_status = 'approved' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ss', $staffID, $sessionID);
    } elseif ($type === 'new_teacher') {
        $gnID = (string)($participant['gn_id'] ?? '');
        if ($gnID === '') return false;
        $stmt = mysqli_prepare($conn, "SELECT 1 FROM attendance_guru_baru WHERE gn_id = ? AND session_id = ? AND attendance_status = 'approved' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ss', $gnID, $sessionID);
    } elseif ($type === 'public') {
        $outsiderID = (string)($participant['outsider_id'] ?? '');
        if ($outsiderID === '') return false;
        $stmt = mysqli_prepare($conn, "SELECT 1 FROM attendance_outsider WHERE CAST(outsider_id AS CHAR) = ? AND session_id = ? AND attendance_status = 'approved' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ss', $outsiderID, $sessionID);
    } else {
        return false;
    }

    mysqli_stmt_execute($stmt);
    return (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function participantSourceLabel(array $row): string {
    $type = strtolower((string)($row['participantType'] ?? ''));
    if ($type === 'teacher' || $type === 'new_teacher') {
        return (string)($row['schoolName'] ?? $row['organisationName'] ?? 'School not available');
    }
    if ($type === 'staff') {
        return (string)($row['department'] ?? $row['organisationName'] ?? 'Department not available');
    }
    if ($type === 'public') {
        return (string)($row['outsiderOrganization'] ?? $row['organisationName'] ?? 'Public participant');
    }
    return (string)($row['organisationName'] ?? 'Not available');
}

function resolveParticipantByEmail(mysqli $conn, array $form, string $email): ?array {
    $courseID = (string)($form['resolvedCourseID'] ?? $form['courseID'] ?? '');
    $email = strtolower(trim($email));

    if ($courseID === '' || $email === '') {
        return null;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT *
        FROM course_participant
        WHERE LOWER(email) = ?
          AND courseID = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'ss', $email, $courseID);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return $row ?: null;
}

function loadCoordinatorOptions(mysqli $conn, array $form): array {
    $ids = [];
    foreach (['staffAttendeeAssignedBy', 'createdByStaff'] as $key) {
        $value = trim((string)($form[$key] ?? ''));
        if ($value !== '') {
            $ids[$value] = $value;
        }
    }

    if (empty($ids)) {
        $result = mysqli_query($conn, "
            SELECT staffID, staffName, department, email
            FROM staff_edu
            WHERE LOWER(COALESCE(status, 'active')) = 'active'
            ORDER BY staffName ASC
            LIMIT 20
        ");
    } else {
        $escapedIDs = array_map(fn($id) => "'" . mysqli_real_escape_string($conn, $id) . "'", array_values($ids));
        $idList = implode(',', $escapedIDs);
        $result = mysqli_query($conn, "
            SELECT staffID, staffName, department, email
            FROM staff_edu
            WHERE staffID IN ($idList)
            ORDER BY FIELD(staffID, $idList), staffName ASC
        ");
    }

    $options = [];
    while ($row = $result ? mysqli_fetch_assoc($result) : null) {
        $options[] = $row;
    }

    return $options;
}

function loadCoordinatorStatus(mysqli $conn, array $form, string $formID): array {
    $coordinators = loadCoordinatorOptions($conn, $form);
    $status = [];
    foreach ($coordinators as $staff) {
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(fr.responseID) AS totalAnswers, ROUND(AVG(fr.rating), 2) AS averageRating, MAX(fr.responseDate) AS submittedDate
            FROM feedback_response fr
            JOIN feedback_question fq ON fr.questionID = fq.questionID
            JOIN feedback_category fc ON fq.categoryID = fc.categoryID
            WHERE fc.formID = ?
              AND fr.staffID = ?
              AND LOWER(COALESCE(fr.responseType, 'participant')) IN ('staff_edu','pic','coordinator')
        ");
        mysqli_stmt_bind_param($stmt, 'ss', $formID, $staff['staffID']);
        mysqli_stmt_execute($stmt);
        $answer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $staff['totalAnswers'] = (int)($answer['totalAnswers'] ?? 0);
        $staff['averageRating'] = $answer['averageRating'] ?? null;
        $staff['submittedDate'] = $answer['submittedDate'] ?? null;
        $status[] = $staff;
    }
    return $status;
}

function loadCourseParticipants(mysqli $conn, string $courseID, string $sessionID, string $formID = ''): array {
    if ($courseID === '') {
        return [];
    }

    $sql = "
        SELECT
            cp.*,
            s_teacher.schoolName AS teacherSchoolName,
            s_gn.schoolName AS gnSchoolName,
            se.department,
            o.organization AS outsiderOrganization,
            ans.totalAnswers,
            ans.averageRating,
            ans.submittedDate
        FROM course_participant cp
        LEFT JOIN teacher teacher_data ON cp.teacherID = teacher_data.teacherID
        LEFT JOIN v_teacher_current_school teacher_school ON teacher_data.teacherID = teacher_school.teacherID
        LEFT JOIN school s_teacher ON teacher_school.schoolID = s_teacher.schoolID
        LEFT JOIN guru_new gn ON cp.gn_id = gn.gn_id
        LEFT JOIN school s_gn ON gn.schoolID = s_gn.schoolID
        LEFT JOIN staff_edu se ON cp.staffID = se.staffID
        LEFT JOIN outsider o ON cp.outsider_id = o.outsider_id
        LEFT JOIN (
            SELECT fr.participantID, COUNT(fr.responseID) AS totalAnswers, ROUND(AVG(fr.rating), 2) AS averageRating, MAX(fr.responseDate) AS submittedDate
            FROM feedback_response fr
            JOIN feedback_question fq ON fr.questionID = fq.questionID
            JOIN feedback_category fc ON fq.categoryID = fc.categoryID
            WHERE fc.formID = ?
            GROUP BY fr.participantID
        ) ans ON cp.participantID = ans.participantID
        WHERE cp.courseID = ?
        ORDER BY cp.participantType, cp.participantName
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $formID, $courseID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $participants = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['schoolName'] = $row['teacherSchoolName'] ?: $row['gnSchoolName'];
        $row['sourceLabel'] = participantSourceLabel($row);
        $row['attendanceApproved'] = hasApprovedAttendance($conn, $row, $sessionID);
        $participants[] = $row;
    }

    return $participants;
}

function validateFeedbackStructure(string $feedbackType, array $categoryNames, array $questionTexts): ?string {
    $validCategories = array_values(array_filter(array_map('trim', $categoryNames), fn($value) => $value !== ''));
    if (count($validCategories) === 0) {
        return 'Please add at least one category.';
    }

    if ($feedbackType === 'participant') {
        if (count($validCategories) < 3) {
            return 'Participant feedback must have Trainer Evaluation, Course Evaluation and Overall Comment categories.';
        }
        if (stripos($validCategories[0], 'trainer') === false) {
            return 'The first category must be Trainer Evaluation.';
        }
        if (stripos($validCategories[1], 'course') === false) {
            return 'The second category must be Course Evaluation.';
        }
        if (stripos(end($validCategories), 'overall') === false && stripos(end($validCategories), 'comment') === false) {
            return 'The last category must be Overall Comment.';
        }
    }

    $hasQuestion = false;
    foreach ($questionTexts as $categoryQuestions) {
        foreach ((array)$categoryQuestions as $questionText) {
            if (trim((string)$questionText) !== '') {
                $hasQuestion = true;
                break 2;
            }
        }
    }

    return $hasQuestion ? null : 'Please add at least one question.';
}

function insertFeedbackStructure(mysqli $conn, string $formID, array $categoryNames, array $questionTexts, array $questionTypes, array $requiredInputs, array $existingImages = []): void {
    // Image questions no longer use reference images. If this project is run
    // against an older TrainHub schema, remove the old questionImage CHECK
    // before inserting an image-answer question.
    $hasImageQuestion = false;
    foreach ($questionTypes as $types) {
        foreach ((array)$types as $type) {
            if ((string)$type === 'image') { $hasImageQuestion = true; break 2; }
        }
    }
    if ($hasImageQuestion) {
        ensureFeedbackImageAnswerSchema($conn);
    }

    foreach ($categoryNames as $catIndex => $categoryName) {
        $categoryName = trim((string)$categoryName);
        if ($categoryName === '') {
            continue;
        }

        $categoryID = nextId($conn, 'feedback_category', 'categoryID', 'FC');
        $categoryOrder = ((int)$catIndex) + 1;
        $catStmt = mysqli_prepare($conn, "INSERT INTO feedback_category (categoryID, categoryName, categoryOrder, formID) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($catStmt, 'ssis', $categoryID, $categoryName, $categoryOrder, $formID);
        mysqli_stmt_execute($catStmt);

        $questionsForCategory = (array)($questionTexts[$catIndex] ?? []);
        $typesForCategory = (array)($questionTypes[$catIndex] ?? []);
        $requiredForCategory = (array)($requiredInputs[$catIndex] ?? []);

        foreach ($questionsForCategory as $qIndex => $questionText) {
            $questionText = trim((string)$questionText);
            if ($questionText === '') {
                continue;
            }

            $questionType = (string)($typesForCategory[$qIndex] ?? 'rating');
            if (!in_array($questionType, ['rating', 'paragraph', 'image'], true)) {
                $questionType = 'rating';
            }

            $isRequired = ((string)($requiredForCategory[$qIndex] ?? '0') === '1') ? 1 : 0;
            $questionOrder = ((int)$qIndex) + 1;
            $questionID = nextId($conn, 'feedback_question', 'questionID', 'FQ');
            // Image questions are responder-upload questions. There is no
            // reference/prompt image in the builder anymore.
            $questionImage = null;

            $qStmt = mysqli_prepare($conn, "
                INSERT INTO feedback_question
                (questionID, questionText, questionType, questionImage, isRequired, questionOrder, categoryID)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($qStmt, 'ssssiis', $questionID, $questionText, $questionType, $questionImage, $isRequired, $questionOrder, $categoryID);
            mysqli_stmt_execute($qStmt);
        }
    }
}

function updateTrainerAndCourseRating(mysqli $conn, string $formID): void {
    $form = loadFormInfo($conn, $formID);
    if (!$form || strtolower((string)$form['feedbackType']) !== 'participant') {
        return;
    }

    $sessionID = (string)($form['sessionID'] ?? '');
    $courseID = (string)($form['resolvedCourseID'] ?? '');

    if ($sessionID !== '') {
        $trainerStmt = mysqli_prepare($conn, "SELECT trainerID FROM session_trainer WHERE sessionID = ?");
        mysqli_stmt_bind_param($trainerStmt, 's', $sessionID);
        mysqli_stmt_execute($trainerStmt);
        $trainerResult = mysqli_stmt_get_result($trainerStmt);

        while ($trainer = mysqli_fetch_assoc($trainerResult)) {
            $trainerID = $trainer['trainerID'];
            $avgSql = "
                SELECT ROUND(AVG(fr.rating), 2) AS avgRating
                FROM feedback_response fr
                JOIN feedback_question fq ON fr.questionID = fq.questionID
                JOIN feedback_category fc ON fq.categoryID = fc.categoryID
                JOIN feedback_form ff ON fc.formID = ff.formID
                JOIN course_session cs ON ff.sessionID = cs.sessionID
                JOIN session_trainer st ON cs.sessionID = st.sessionID
                WHERE st.trainerID = ?
                  AND ff.feedbackType = 'participant'
                  AND LOWER(fc.categoryName) LIKE '%trainer%'
                  AND fq.questionType = 'rating'
                  AND fr.rating IS NOT NULL
            ";
            $avgStmt = mysqli_prepare($conn, $avgSql);
            mysqli_stmt_bind_param($avgStmt, 's', $trainerID);
            mysqli_stmt_execute($avgStmt);
            $avgRow = mysqli_fetch_assoc(mysqli_stmt_get_result($avgStmt));
            if ($avgRow && $avgRow['avgRating'] !== null) {
                $rating = (float)$avgRow['avgRating'];
                $update = mysqli_prepare($conn, "UPDATE trainer SET averageRating = ? WHERE trainerID = ?");
                mysqli_stmt_bind_param($update, 'ds', $rating, $trainerID);
                mysqli_stmt_execute($update);
            }
        }
    }

    if ($courseID !== '') {
        $courseAvgSql = "
            SELECT ROUND(AVG(fr.rating), 2) AS avgRating
            FROM feedback_response fr
            JOIN feedback_question fq ON fr.questionID = fq.questionID
            JOIN feedback_category fc ON fq.categoryID = fc.categoryID
            JOIN feedback_form ff ON fc.formID = ff.formID
            LEFT JOIN course_session cs ON ff.sessionID = cs.sessionID
            WHERE COALESCE(ff.courseID, cs.courseID) = ?
              AND ff.feedbackType = 'participant'
              AND LOWER(fc.categoryName) LIKE '%course%'
              AND fq.questionType = 'rating'
              AND fr.rating IS NOT NULL
        ";
        $courseStmt = mysqli_prepare($conn, $courseAvgSql);
        mysqli_stmt_bind_param($courseStmt, 's', $courseID);
        mysqli_stmt_execute($courseStmt);
        $courseAvg = mysqli_fetch_assoc(mysqli_stmt_get_result($courseStmt));

        if ($courseAvg && $courseAvg['avgRating'] !== null) {
            $rating = (float)$courseAvg['avgRating'];
            $updateCourse = mysqli_prepare($conn, "UPDATE course SET courseRating = ? WHERE courseID = ?");
            mysqli_stmt_bind_param($updateCourse, 'ds', $rating, $courseID);
            mysqli_stmt_execute($updateCourse);
        }
    }
}

function loadResponseData(mysqli $conn, string $formID): array {
    $form = loadFormInfo($conn, $formID);
    if (!$form) {
        return [null, [], [], [], []];
    }

    $summary = [];
    $summaryStmt = mysqli_prepare($conn, "
        SELECT
            fc.categoryID,
            fc.categoryName,
            fc.categoryOrder,
            ROUND(AVG(fr.rating), 2) AS averageRating,
            COUNT(fr.rating) AS ratingCount
        FROM feedback_category fc
        LEFT JOIN feedback_question fq ON fc.categoryID = fq.categoryID
        LEFT JOIN feedback_response fr ON fq.questionID = fr.questionID
        WHERE fc.formID = ?
        GROUP BY fc.categoryID, fc.categoryName, fc.categoryOrder
        ORDER BY fc.categoryOrder ASC
    ");
    mysqli_stmt_bind_param($summaryStmt, 's', $formID);
    mysqli_stmt_execute($summaryStmt);
    $summaryResult = mysqli_stmt_get_result($summaryStmt);
    while ($row = mysqli_fetch_assoc($summaryResult)) {
        $summary[] = $row;
    }

    $courseID = (string)($form['resolvedCourseID'] ?? '');
    $sessionID = (string)($form['sessionID'] ?? '');
    $tracker = strtolower((string)$form['feedbackType']) === 'participant'
        ? loadCourseParticipants($conn, $courseID, $sessionID, $formID)
        : loadCoordinatorStatus($conn, $form, $formID);

    $groups = [];

    if (isCoordinatorFeedbackType((string)$form['feedbackType'])) {
        $detailSql = "
            SELECT
                fr.staffID AS respondentID,
                se.staffName,
                se.department,
                se.email AS staffEmail,
                fc.categoryID,
                fc.categoryName,
                fc.categoryOrder,
                fq.questionID,
                fq.questionText,
                fq.questionType,
                fq.questionImage,
                fq.questionOrder,
                fr.rating,
                fr.comment,
                fr.responseDate
            FROM feedback_response fr
            JOIN feedback_question fq ON fr.questionID = fq.questionID
            JOIN feedback_category fc ON fq.categoryID = fc.categoryID
            LEFT JOIN staff_edu se ON fr.staffID = se.staffID
            WHERE fc.formID = ?
              AND LOWER(COALESCE(fr.responseType, 'participant')) IN ('staff_edu','pic','coordinator')
            ORDER BY fr.responseDate DESC, fr.staffID, fc.categoryOrder ASC, fq.questionOrder ASC
        ";
        $detailStmt = mysqli_prepare($conn, $detailSql);
        mysqli_stmt_bind_param($detailStmt, 's', $formID);
        mysqli_stmt_execute($detailStmt);
        $detailResult = mysqli_stmt_get_result($detailStmt);

        while ($row = mysqli_fetch_assoc($detailResult)) {
            $key = (string)($row['respondentID'] ?? 'Coordinator');
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'respondentKey' => $key,
                    'respondentType' => 'coordinator',
                    'sourceLabel' => $row['department'] ?: 'Person in charge',
                    'displayName' => $row['staffName'] ?: $key,
                    'displayID' => $key,
                    'displayEmail' => $row['staffEmail'] ?? '',
                    'responseDate' => $row['responseDate'],
                    'categories' => [],
                    'ratings' => []
                ];
            }

            $catID = $row['categoryID'];
            if (!isset($groups[$key]['categories'][$catID])) {
                $groups[$key]['categories'][$catID] = [
                    'categoryName' => $row['categoryName'],
                    'answers' => [],
                    'ratings' => []
                ];
            }

            if ($row['rating'] !== null) {
                $groups[$key]['categories'][$catID]['ratings'][] = (float)$row['rating'];
                $groups[$key]['ratings'][] = (float)$row['rating'];
            }
            $groups[$key]['categories'][$catID]['answers'][] = $row;
        }
    } else {
        $detailSql = "
            SELECT
                fr.participantID AS respondentID,
                cp.participantType,
                cp.participantName,
                cp.organisationName,
                cp.email AS participantEmail,
                cp.teacherID,
                cp.staffID AS participantStaffID,
                cp.gn_id,
                cp.outsider_id,
                COALESCE(s_teacher.schoolName, s_gn.schoolName) AS schoolName,
                se.department,
                o.organization AS outsiderOrganization,
                fc.categoryID,
                fc.categoryName,
                fc.categoryOrder,
                fq.questionID,
                fq.questionText,
                fq.questionType,
                fq.questionImage,
                fq.questionOrder,
                fr.rating,
                fr.comment,
                fr.responseDate
            FROM feedback_response fr
            JOIN feedback_question fq ON fr.questionID = fq.questionID
            JOIN feedback_category fc ON fq.categoryID = fc.categoryID
            JOIN course_participant cp ON fr.participantID = cp.participantID
            LEFT JOIN teacher teacher_data ON cp.teacherID = teacher_data.teacherID
            LEFT JOIN v_teacher_current_school teacher_school ON teacher_data.teacherID = teacher_school.teacherID
            LEFT JOIN school s_teacher ON teacher_school.schoolID = s_teacher.schoolID
            LEFT JOIN guru_new gn ON cp.gn_id = gn.gn_id
            LEFT JOIN school s_gn ON gn.schoolID = s_gn.schoolID
            LEFT JOIN staff_edu se ON cp.staffID = se.staffID
            LEFT JOIN outsider o ON cp.outsider_id = o.outsider_id
            WHERE fc.formID = ?
              AND fr.responseType = 'participant'
            ORDER BY fr.responseDate DESC, fr.participantID, fc.categoryOrder ASC, fq.questionOrder ASC
        ";
        $detailStmt = mysqli_prepare($conn, $detailSql);
        mysqli_stmt_bind_param($detailStmt, 's', $formID);
        mysqli_stmt_execute($detailStmt);
        $detailResult = mysqli_stmt_get_result($detailStmt);

        while ($row = mysqli_fetch_assoc($detailResult)) {
            $key = (string)$row['respondentID'];
            $sourceLabel = participantSourceLabel($row);

            if (!isset($groups[$key])) {
                $externalID = $row['teacherID'] ?: ($row['participantStaffID'] ?: ($row['gn_id'] ?: ($row['outsider_id'] ?: $key)));
                $groups[$key] = [
                    'respondentKey' => $key,
                    'respondentType' => $row['participantType'],
                    'sourceLabel' => $sourceLabel,
                    'displayName' => $row['participantName'] ?? '',
                    'displayID' => $externalID,
                    'participantID' => $key,
                    'displayEmail' => $row['participantEmail'] ?? '',
                    'responseDate' => $row['responseDate'],
                    'categories' => [],
                    'ratings' => []
                ];
            }

            $catID = $row['categoryID'];
            if (!isset($groups[$key]['categories'][$catID])) {
                $groups[$key]['categories'][$catID] = [
                    'categoryName' => $row['categoryName'],
                    'answers' => [],
                    'ratings' => []
                ];
            }

            if ($row['rating'] !== null) {
                $groups[$key]['categories'][$catID]['ratings'][] = (float)$row['rating'];
                $groups[$key]['ratings'][] = (float)$row['rating'];
            }
            $groups[$key]['categories'][$catID]['answers'][] = $row;
        }
    }

    return [$form, $summary, $tracker, $groups, loadQuestionGroups($conn, $formID)];
}

$currentStaffID = getCurrentStaffID();
$currentStaffName = getCurrentStaffName($conn, $currentStaffID);

if (!$isAnswerRequest && $currentStaffID === '') {
    header('Location: login.php');
    exit;
}

if ($currentStaffID !== '') {
    $setStaff = mysqli_prepare($conn, "SET @current_staff_id = ?, @current_staff_name = ?");
    mysqli_stmt_bind_param($setStaff, 'ss', $currentStaffID, $currentStaffName);
    mysqli_stmt_execute($setStaff);
}

$message = '';
$messageType = '';
if (isset($_SESSION['feedback_flash'])) {
    $message = (string)($_SESSION['feedback_flash']['text'] ?? '');
    $messageType = (string)($_SESSION['feedback_flash']['type'] ?? 'success');
    unset($_SESSION['feedback_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if ($postedToken === '' || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $message = 'Your form session has expired. Please refresh the page and try again.';
        $messageType = 'error';
        $_POST = [];
    }
}

/* =========================
   CREATE FEEDBACK FORM
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_feedback_form'])) {
    $feedbackType = normalizeFeedbackType((string)($_POST['feedbackType'] ?? 'participant'));
    $sessionID = trim((string)($_POST['sessionID'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $categoryNames = (array)($_POST['category_name'] ?? []);
    $questionTexts = (array)($_POST['question_text'] ?? []);
    $questionTypes = (array)($_POST['question_type'] ?? []);
    $requiredInputs = (array)($_POST['is_required'] ?? []);

    if ($currentStaffID === '') {
        $message = 'Staff session not found. Please login again.';
        $messageType = 'error';
    } elseif ($sessionID === '' || $title === '') {
        $message = 'Please select a course session and enter a form title.';
        $messageType = 'error';
    } else {
        $structureError = validateFeedbackStructure($feedbackType, $categoryNames, $questionTexts);
        if ($structureError) {
            $message = $structureError;
            $messageType = 'error';
        } else {
            $sessionStmt = mysqli_prepare($conn, "SELECT courseID FROM course_session WHERE sessionID = ? LIMIT 1");
            mysqli_stmt_bind_param($sessionStmt, 's', $sessionID);
            mysqli_stmt_execute($sessionStmt);
            $sessionRow = mysqli_fetch_assoc(mysqli_stmt_get_result($sessionStmt));

            if (!$sessionRow) {
                $message = 'Selected course session was not found.';
                $messageType = 'error';
            } else {
                try {
                    ensureFeedbackImageAnswerSchema($conn);
                } catch (Throwable $e) {
                    $message = 'Failed to prepare feedback image upload: ' . $e->getMessage();
                    $messageType = 'error';
                }

                if ($messageType !== 'error') {
                    mysqli_begin_transaction($conn);
                    try {
                        $courseID = (string)$sessionRow['courseID'];
                    $formID = nextId($conn, 'feedback_form', 'formID', 'FF');
                    $formStmt = mysqli_prepare($conn, "
                        INSERT INTO feedback_form
                        (formID, title, createdByStaff, createdDate, sessionID, courseID, feedbackType)
                        VALUES (?, ?, ?, NOW(), ?, ?, ?)
                    ");
                    mysqli_stmt_bind_param($formStmt, 'ssssss', $formID, $title, $currentStaffID, $sessionID, $courseID, $feedbackType);
                    mysqli_stmt_execute($formStmt);

                    insertFeedbackStructure($conn, $formID, $categoryNames, $questionTexts, $questionTypes, $requiredInputs);

                    mysqli_commit($conn);
                    setFeedbackFlash('success', 'Feedback form created successfully. The generated link is ready to share.');
                    redirectFeedback('feedback.php?tab=forms&created=' . urlencode($formID));
                    } catch (Throwable $e) {
                        mysqli_rollback($conn);
                        $message = 'Failed to create feedback form: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
            }
        }
    }
}

/* =========================
   UPDATE FEEDBACK FORM
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_feedback_form'])) {
    $formID = trim((string)($_POST['formID'] ?? ''));
    $sessionID = trim((string)($_POST['sessionID'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $categoryNames = (array)($_POST['category_name'] ?? []);
    $questionTexts = (array)($_POST['question_text'] ?? []);
    $questionTypes = (array)($_POST['question_type'] ?? []);
    $requiredInputs = (array)($_POST['is_required'] ?? []);

    if ($formID === '' || $sessionID === '' || $title === '') {
        $message = 'Feedback form title and course session are required.';
        $messageType = 'error';
    } else {
        try {
            $sourceForm = loadFormInfo($conn, $formID);
            if (!$sourceForm) {
                throw new RuntimeException('Feedback form was not found.');
            }

            if (countFormResponses($conn, $formID) > 0) {
                throw new RuntimeException('This form is locked because it already has responses. Duplicate it to create a new version.');
            }

            $feedbackType = normalizeFeedbackType((string)($sourceForm['feedbackType'] ?? 'participant'));
            $structureError = validateFeedbackStructure($feedbackType, $categoryNames, $questionTexts);
            if ($structureError) {
                throw new RuntimeException($structureError);
            }

            $sessionStmt = mysqli_prepare($conn, "SELECT courseID FROM course_session WHERE sessionID = ? LIMIT 1");
            mysqli_stmt_bind_param($sessionStmt, 's', $sessionID);
            mysqli_stmt_execute($sessionStmt);
            $sessionRow = mysqli_fetch_assoc(mysqli_stmt_get_result($sessionStmt));
            if (!$sessionRow) {
                throw new RuntimeException('Selected course session was not found.');
            }
            $courseID = (string)$sessionRow['courseID'];

            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, "UPDATE feedback_form SET title = ?, sessionID = ?, courseID = ? WHERE formID = ?");
                mysqli_stmt_bind_param($stmt, 'ssss', $title, $sessionID, $courseID, $formID);
                mysqli_stmt_execute($stmt);

                /* No responses exist, so the structure can be safely rebuilt in full. */
                $deleteQuestions = mysqli_prepare($conn, "
                    DELETE fq FROM feedback_question fq
                    INNER JOIN feedback_category fc ON fq.categoryID = fc.categoryID
                    WHERE fc.formID = ?
                ");
                mysqli_stmt_bind_param($deleteQuestions, 's', $formID);
                mysqli_stmt_execute($deleteQuestions);

                $deleteCategories = mysqli_prepare($conn, "DELETE FROM feedback_category WHERE formID = ?");
                mysqli_stmt_bind_param($deleteCategories, 's', $formID);
                mysqli_stmt_execute($deleteCategories);

                insertFeedbackStructure($conn, $formID, $categoryNames, $questionTexts, $questionTypes, $requiredInputs);

                mysqli_commit($conn);
            } catch (Throwable $inner) {
                mysqli_rollback($conn);
                throw $inner;
            }

            setFeedbackFlash('success', 'Feedback form updated successfully.');
            redirectFeedback('feedback.php?tab=forms');
        } catch (Throwable $e) {
            $message = 'Failed to update feedback form: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

/* =========================
   DUPLICATE FORM AS NEW VERSION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicate_feedback_form'])) {
    $sourceFormID = trim((string)($_POST['formID'] ?? ''));
    $sourceForm = loadFormInfo($conn, $sourceFormID);

    if (!$sourceForm) {
        $message = 'Feedback form not found.';
        $messageType = 'error';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $newFormID = nextId($conn, 'feedback_form', 'formID', 'FF');
            $newTitle = $sourceForm['title'] . ' - New Version';
            $insertForm = mysqli_prepare($conn, "
                INSERT INTO feedback_form
                (formID, title, createdByStaff, createdDate, sessionID, courseID, feedbackType)
                VALUES (?, ?, ?, NOW(), ?, ?, ?)
            ");
            mysqli_stmt_bind_param(
                $insertForm,
                'ssssss',
                $newFormID,
                $newTitle,
                $currentStaffID,
                $sourceForm['sessionID'],
                $sourceForm['resolvedCourseID'],
                $sourceForm['feedbackType']
            );
            mysqli_stmt_execute($insertForm);

            $groups = loadQuestionGroups($conn, $sourceFormID);
            foreach ($groups as $category) {
                $newCategoryID = nextId($conn, 'feedback_category', 'categoryID', 'FC');
                $catStmt = mysqli_prepare($conn, "INSERT INTO feedback_category (categoryID, categoryName, categoryOrder, formID) VALUES (?, ?, ?, ?)");
                $newCategoryName = (string)$category['categoryName'];
                $newCategoryOrder = (int)$category['categoryOrder'];
                mysqli_stmt_bind_param($catStmt, 'ssis', $newCategoryID, $newCategoryName, $newCategoryOrder, $newFormID);
                mysqli_stmt_execute($catStmt);

                foreach ($category['questions'] as $question) {
                    $newQuestionID = nextId($conn, 'feedback_question', 'questionID', 'FQ');
                    $qStmt = mysqli_prepare($conn, "
                        INSERT INTO feedback_question
                        (questionID, questionText, questionType, questionImage, isRequired, questionOrder, categoryID)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $copyQuestionText = (string)$question['questionText'];
                    $copyQuestionType = (string)$question['questionType'];
                    $copyQuestionImage = null;
                    $copyIsRequired = (int)$question['isRequired'];
                    $copyQuestionOrder = (int)$question['questionOrder'];
                    mysqli_stmt_bind_param(
                        $qStmt,
                        'ssssiis',
                        $newQuestionID,
                        $copyQuestionText,
                        $copyQuestionType,
                        $copyQuestionImage,
                        $copyIsRequired,
                        $copyQuestionOrder,
                        $newCategoryID
                    );
                    mysqli_stmt_execute($qStmt);
                }
            }

            mysqli_commit($conn);
            setFeedbackFlash('success', 'Feedback form duplicated as a new version successfully.');
            redirectFeedback('feedback.php?tab=forms');
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $message = 'Failed to duplicate feedback form: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

/* =========================
   DELETE FORM
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback_form'])) {
    $formID = trim((string)($_POST['formID'] ?? ''));

    if ($formID === '') {
        $message = 'Feedback form not found.';
        $messageType = 'error';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $check = mysqli_prepare($conn, "SELECT formID FROM feedback_form WHERE formID = ? LIMIT 1");
            mysqli_stmt_bind_param($check, 's', $formID);
            mysqli_stmt_execute($check);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
                throw new RuntimeException('Feedback form was not found.');
            }

            /* Explicit child deletes also work when an imported schema is missing CASCADE rules. */
            $deleteResponses = mysqli_prepare($conn, "
                DELETE fr FROM feedback_response fr
                INNER JOIN feedback_question fq ON fr.questionID = fq.questionID
                INNER JOIN feedback_category fc ON fq.categoryID = fc.categoryID
                WHERE fc.formID = ?
            ");
            mysqli_stmt_bind_param($deleteResponses, 's', $formID);
            mysqli_stmt_execute($deleteResponses);

            $deleteQuestions = mysqli_prepare($conn, "
                DELETE fq FROM feedback_question fq
                INNER JOIN feedback_category fc ON fq.categoryID = fc.categoryID
                WHERE fc.formID = ?
            ");
            mysqli_stmt_bind_param($deleteQuestions, 's', $formID);
            mysqli_stmt_execute($deleteQuestions);

            $deleteCategories = mysqli_prepare($conn, "DELETE FROM feedback_category WHERE formID = ?");
            mysqli_stmt_bind_param($deleteCategories, 's', $formID);
            mysqli_stmt_execute($deleteCategories);

            $stmt = mysqli_prepare($conn, "DELETE FROM feedback_form WHERE formID = ?");
            mysqli_stmt_bind_param($stmt, 's', $formID);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_affected_rows($stmt) === 0) {
                throw new RuntimeException('Feedback form could not be deleted.');
            }

            mysqli_commit($conn);
            setFeedbackFlash('success', 'Feedback form deleted successfully.');
            redirectFeedback('feedback.php?tab=forms');
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $message = 'Failed to delete feedback form: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

/* =========================
   SUBMIT ANSWER
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $formID = trim((string)($_POST['formID'] ?? ''));
    $respondentID = trim((string)($_POST['respondentID'] ?? ''));
    $answers = (array)($_POST['answer'] ?? []);
    $form = loadFormInfo($conn, $formID);

    if (!$form) {
        $message = 'Feedback form not found.';
        $messageType = 'error';
    } elseif ($respondentID === '') {
        $message = 'Please enter your email or select your name.';
        $messageType = 'error';
    } else {
        $feedbackType = strtolower((string)$form['feedbackType']);
        $participantID = null;
        $staffID = null;
        $transactionStarted = false;

        try {
            if ($feedbackType === 'participant') {
                if (!filter_var($respondentID, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Please enter the email registered for this course.');
                }

                $participant = resolveParticipantByEmail($conn, $form, $respondentID);
                if (!$participant) {
                    throw new RuntimeException('This email is not registered as a participant for this course.');
                }

                // Feedback submission is verified by course registration/email.
                // Attendance remains a separate certificate-eligibility rule and
                // must not prevent a valid feedback response from being stored.
                $participantID = (string)$participant['participantID'];
            } else {
                $staffID = $respondentID;
                $staffStmt = mysqli_prepare($conn, "SELECT staffID FROM staff_edu WHERE staffID = ? AND LOWER(COALESCE(status, 'active')) = 'active' LIMIT 1");
                mysqli_stmt_bind_param($staffStmt, 's', $staffID);
                mysqli_stmt_execute($staffStmt);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($staffStmt))) {
                    throw new RuntimeException('Please select a valid coordinator name.');
                }
            }

            $questionGroups = loadQuestionGroups($conn, $formID);
            $questionRows = [];
            foreach ($questionGroups as $group) {
                foreach ($group['questions'] as $question) {
                    $questionRows[] = $question;
                }
            }
            if (empty($questionRows)) {
                throw new RuntimeException('This feedback form has no questions to answer.');
            }

            mysqli_begin_transaction($conn);
            $transactionStarted = true;

            // Lock the respondent record for this submission. This makes the
            // one-submission-per-form check reliable even if the submit button
            // is double-clicked or two requests arrive almost together.
            if ($feedbackType === 'participant') {
                $lockStmt = mysqli_prepare($conn, "SELECT participantID FROM course_participant WHERE participantID = ? FOR UPDATE");
                mysqli_stmt_bind_param($lockStmt, 's', $participantID);
                mysqli_stmt_execute($lockStmt);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($lockStmt))) {
                    throw new RuntimeException('Participant record was not found.');
                }

                $dupStmt = mysqli_prepare($conn, "
                    SELECT fr.responseID
                    FROM feedback_response fr
                    INNER JOIN feedback_question fq ON fr.questionID = fq.questionID
                    INNER JOIN feedback_category fc ON fq.categoryID = fc.categoryID
                    WHERE fr.participantID = ?
                      AND fc.formID = ?
                      AND fr.responseType = 'participant'
                    LIMIT 1 FOR UPDATE
                ");
                mysqli_stmt_bind_param($dupStmt, 'ss', $participantID, $formID);
            } else {
                $lockStmt = mysqli_prepare($conn, "SELECT staffID FROM staff_edu WHERE staffID = ? FOR UPDATE");
                mysqli_stmt_bind_param($lockStmt, 's', $staffID);
                mysqli_stmt_execute($lockStmt);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($lockStmt))) {
                    throw new RuntimeException('Coordinator record was not found.');
                }

                $dupStmt = mysqli_prepare($conn, "
                    SELECT fr.responseID
                    FROM feedback_response fr
                    INNER JOIN feedback_question fq ON fr.questionID = fq.questionID
                    INNER JOIN feedback_category fc ON fq.categoryID = fc.categoryID
                    WHERE fr.staffID = ?
                      AND fc.formID = ?
                      AND LOWER(COALESCE(fr.responseType, 'participant')) IN ('staff_edu','pic','coordinator')
                    LIMIT 1 FOR UPDATE
                ");
                mysqli_stmt_bind_param($dupStmt, 'ss', $staffID, $formID);
            }

            mysqli_stmt_execute($dupStmt);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($dupStmt))) {
                throw new RuntimeException('You have already submitted this feedback form.');
            }

            $insert = mysqli_prepare($conn, "
                INSERT INTO feedback_response
                    (responseID, rating, comment, responseDate, participantID, questionID, staffID, responseType)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
            ");

            foreach ($questionRows as $question) {
                $questionID = (string)$question['questionID'];
                $questionType = strtolower((string)$question['questionType']);
                $isRequired = (int)$question['isRequired'];
                $rawAnswer = trim((string)($answers[$questionID] ?? ''));

                if ($isRequired === 1 && $rawAnswer === '' && $questionType !== 'image') {
                    throw new RuntimeException('Please answer all required questions.');
                }

                $rating = null;
                $comment = null;

                if ($questionType === 'rating') {
                    $rating = $rawAnswer !== '' ? (int)$rawAnswer : null;
                    if ($rating !== null && ($rating < 1 || $rating > 5)) {
                        throw new RuntimeException('Rating must be between 1 and 5.');
                    }
                } elseif ($questionType === 'image') {
                    $respondentKey = $participantID ?: ($staffID ?: 'respondent');
                    $imagePath = saveResponseImage($questionID, $respondentKey);
                    if ($isRequired === 1 && $imagePath === null) {
                        throw new RuntimeException('Please upload all required images.');
                    }
                    $comment = $imagePath;
                } else {
                    $comment = $rawAnswer !== '' ? $rawAnswer : null;
                }

                $responseID = nextId($conn, 'feedback_response', 'responseID', 'FR');
                $responseType = $feedbackType === 'participant' ? 'participant' : 'staff_edu';
                mysqli_stmt_bind_param($insert, 'sisssss', $responseID, $rating, $comment, $participantID, $questionID, $staffID, $responseType);
                mysqli_stmt_execute($insert);
                if (mysqli_stmt_affected_rows($insert) !== 1) {
                    throw new RuntimeException('A feedback answer could not be saved. Please try again.');
                }
            }

            mysqli_commit($conn);
            $transactionStarted = false;

            // Keep a short same-browser marker for a cleaner post-submit state.
            // The database check above is authoritative and blocks repeat
            // submissions even from a different browser/device.
            if ($participantID !== null) {
                $_SESSION['submitted_feedback_forms'][(string)$formID] = (string)$participantID;
            }

            if ($feedbackType === 'participant' && $participantID !== null) {
                try {
                    $proc = mysqli_prepare($conn, "CALL sp_update_feedback_completed(?)");
                    mysqli_stmt_bind_param($proc, 's', $participantID);
                    mysqli_stmt_execute($proc);
                    mysqli_stmt_close($proc);
                    while (mysqli_more_results($conn) && mysqli_next_result($conn)) {
                        $extraResult = mysqli_store_result($conn);
                        if ($extraResult) mysqli_free_result($extraResult);
                    }
                } catch (Throwable $completionError) {
                    error_log('Feedback completion refresh failed: ' . $completionError->getMessage());
                }
            }

            try {
                updateTrainerAndCourseRating($conn, $formID);
            } catch (Throwable $ratingError) {
                error_log('Feedback rating refresh failed: ' . $ratingError->getMessage());
            }

            setFeedbackFlash('success', 'Feedback submitted successfully.');
            redirectFeedback('feedback.php?answer=1&formID=' . urlencode($formID) . '&submitted=1');
        } catch (Throwable $e) {
            if ($transactionStarted) {
                try { mysqli_rollback($conn); } catch (Throwable $ignore) {}
            }
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

/* =========================
   COMMON DATA
========================= */
$baseUrl = getBaseUrl();
$requestedTab = strtolower(trim((string)($_GET['tab'] ?? 'participant')));
if ($requestedTab === 'pic') {
    $requestedTab = 'coordinator';
}
$activeTab = in_array($requestedTab, ['participant', 'coordinator', 'forms'], true) ? $requestedTab : 'participant';

$sessions = [];
$sessionSql = "
    SELECT
        cs.sessionID,
        cs.sessionName,
        cs.sessionDate,
        cs.startTime,
        cs.endTime,
        c.courseID,
        c.courseName,
        c.staffAttendeeAssignedBy,
        coordinator.staffName AS coordinatorName,
        coordinator.department AS coordinatorDepartment,
        coordinator.email AS coordinatorEmail,
        GROUP_CONCAT(DISTINCT t.trainerName ORDER BY t.trainerName SEPARATOR ', ') AS trainerNames
    FROM course_session cs
    JOIN course c ON cs.courseID = c.courseID
    LEFT JOIN staff_edu coordinator ON c.staffAttendeeAssignedBy = coordinator.staffID
    LEFT JOIN session_trainer st ON cs.sessionID = st.sessionID
    LEFT JOIN trainer t ON st.trainerID = t.trainerID
    GROUP BY cs.sessionID, cs.sessionName, cs.sessionDate, cs.startTime, cs.endTime, c.courseID, c.courseName, c.staffAttendeeAssignedBy, coordinator.staffName, coordinator.department, coordinator.email
    ORDER BY cs.sessionDate DESC, cs.startTime ASC
";
$sessionResult = mysqli_query($conn, $sessionSql);
while ($row = $sessionResult ? mysqli_fetch_assoc($sessionResult) : null) {
    $sessions[] = $row;
}

$forms = [];
// Summarize response data once per form. The summary is reused by the statistics
// query and the paginated form-list query so we never load the full form history
// merely to calculate totals.
$responseSummarySql = "
    SELECT
        fc.formID,
        COUNT(DISTINCT fr.participantID) AS participantResponses,
        COUNT(DISTINCT fr.staffID) AS staffResponses,
        COUNT(fr.responseID) AS totalAnswerRows,
        ROUND(AVG(fr.rating), 2) AS averageRating,
        COALESCE(SUM(fr.rating), 0) AS ratingSum,
        COUNT(fr.rating) AS ratingCount
    FROM feedback_category fc
    LEFT JOIN feedback_question fq ON fq.categoryID = fc.categoryID
    LEFT JOIN feedback_response fr ON fr.questionID = fq.questionID
    GROUP BY fc.formID
";

$formStatsSql = "
    SELECT
        COUNT(ff.formID) AS totalForms,
        SUM(CASE WHEN LOWER(COALESCE(ff.feedbackType, 'participant')) = 'participant' THEN 1 ELSE 0 END) AS totalParticipantForms,
        SUM(CASE WHEN LOWER(COALESCE(ff.feedbackType, 'participant')) <> 'participant' THEN 1 ELSE 0 END) AS totalCoordinatorForms,
        SUM(
            CASE
                WHEN LOWER(COALESCE(ff.feedbackType, 'participant')) = 'participant'
                    THEN COALESCE(rs.participantResponses, 0)
                ELSE COALESCE(rs.staffResponses, 0)
            END
        ) AS totalResponses,
        COALESCE(SUM(rs.ratingSum), 0) AS ratingSum,
        COALESCE(SUM(rs.ratingCount), 0) AS ratingCount
    FROM feedback_form ff
    LEFT JOIN (" . $responseSummarySql . ") rs ON rs.formID = ff.formID
";

$totalForms = 0;
$totalParticipantForms = 0;
$totalCoordinatorForms = 0;
$totalResponses = 0;
$totalRatingSum = 0.0;
$totalRatingCount = 0;
$statsResult = mysqli_query($conn, $formStatsSql);
if ($statsResult && ($statsRow = mysqli_fetch_assoc($statsResult))) {
    $totalForms = (int)($statsRow['totalForms'] ?? 0);
    $totalParticipantForms = (int)($statsRow['totalParticipantForms'] ?? 0);
    $totalCoordinatorForms = (int)($statsRow['totalCoordinatorForms'] ?? 0);
    $totalResponses = (int)($statsRow['totalResponses'] ?? 0);
    $totalRatingSum = (float)($statsRow['ratingSum'] ?? 0);
    $totalRatingCount = (int)($statsRow['ratingCount'] ?? 0);
}
$overallRating = $totalRatingCount > 0 ? round($totalRatingSum / $totalRatingCount, 2) : 0;

// Keep the form library lightweight even after years of data. Search/status
// filtering and pagination are performed by MySQL so the browser only receives
// the rows it is actually displaying.
$formsPerPage = 10;
$formListSearch = trim((string)($_GET['form_search'] ?? ''));
$formListStatus = strtolower(trim((string)($_GET['form_status'] ?? 'all')));
if (!in_array($formListStatus, ['all', 'has_responses', 'no_responses'], true)) {
    $formListStatus = 'all';
}

$trainerSummarySql = "
    SELECT
        st.sessionID,
        GROUP_CONCAT(DISTINCT t.trainerName ORDER BY t.trainerName SEPARATOR ', ') AS trainerNames
    FROM session_trainer st
    JOIN trainer t ON t.trainerID = st.trainerID
    GROUP BY st.sessionID
";

$responseCountExpr = "(CASE
    WHEN LOWER(COALESCE(ff.feedbackType, 'participant')) = 'participant'
        THEN COALESCE(rs.participantResponses, 0)
    ELSE COALESCE(rs.staffResponses, 0)
END)";

$formListWhereParts = [];
if ($formListSearch !== '') {
    $safeFormSearch = mysqli_real_escape_string($conn, $formListSearch);
    $formListWhereParts[] = "(
        ff.title LIKE '%{$safeFormSearch}%'
        OR COALESCE(c.courseName, '') LIKE '%{$safeFormSearch}%'
        OR COALESCE(cs.sessionName, '') LIKE '%{$safeFormSearch}%'
        OR COALESCE(ts.trainerNames, '') LIKE '%{$safeFormSearch}%'
    )";
}
if ($formListStatus === 'has_responses') {
    $formListWhereParts[] = $responseCountExpr . ' > 0';
} elseif ($formListStatus === 'no_responses') {
    $formListWhereParts[] = $responseCountExpr . ' = 0';
}
$formListWhereSql = $formListWhereParts !== [] ? ' WHERE ' . implode(' AND ', $formListWhereParts) : '';

$formListCountSql = "
    SELECT COUNT(ff.formID) AS total
    FROM feedback_form ff
    LEFT JOIN course_session cs ON ff.sessionID = cs.sessionID
    LEFT JOIN course c ON COALESCE(ff.courseID, cs.courseID) = c.courseID
    LEFT JOIN (" . $trainerSummarySql . ") ts ON ts.sessionID = ff.sessionID
    LEFT JOIN (" . $responseSummarySql . ") rs ON rs.formID = ff.formID
    " . $formListWhereSql;
$formListCountResult = mysqli_query($conn, $formListCountSql);
$formListTotal = (int)(($formListCountResult ? mysqli_fetch_assoc($formListCountResult) : [])['total'] ?? 0);

$formPage = max(1, (int)($_GET['form_page'] ?? 1));
$formTotalPages = max(1, (int)ceil($formListTotal / $formsPerPage));
$formPage = min($formPage, $formTotalPages);
$formOffset = ($formPage - 1) * $formsPerPage;

$formSql = "
    SELECT
        ff.formID,
        ff.title,
        ff.createdDate,
        ff.sessionID,
        ff.courseID,
        ff.feedbackType,
        ff.generatedPdf,
        cs.sessionName,
        cs.sessionDate,
        c.courseName,
        ts.trainerNames,
        " . $responseCountExpr . " AS totalResponses,
        COALESCE(rs.totalAnswerRows, 0) AS totalAnswerRows,
        rs.averageRating,
        COALESCE(rs.ratingSum, 0) AS ratingSum,
        COALESCE(rs.ratingCount, 0) AS ratingCount
    FROM feedback_form ff
    LEFT JOIN course_session cs ON ff.sessionID = cs.sessionID
    LEFT JOIN course c ON COALESCE(ff.courseID, cs.courseID) = c.courseID
    LEFT JOIN (" . $trainerSummarySql . ") ts ON ts.sessionID = ff.sessionID
    LEFT JOIN (" . $responseSummarySql . ") rs ON rs.formID = ff.formID
    " . $formListWhereSql . "
    ORDER BY ff.createdDate DESC, ff.formID DESC
    LIMIT " . (int)$formsPerPage . " OFFSET " . (int)$formOffset;
$formResult = mysqli_query($conn, $formSql);
while ($row = $formResult ? mysqli_fetch_assoc($formResult) : null) {
    $forms[] = $row;
}

$formShowingStart = $formListTotal > 0 ? $formOffset + 1 : 0;
$formShowingEnd = min($formOffset + count($forms), $formListTotal);

$participantForms = [];
$coordinatorForms = [];
foreach ($forms as $formRow) {
    if (strtolower((string)($formRow['feedbackType'] ?? 'participant')) === 'participant') {
        $participantForms[] = $formRow;
    } else {
        $coordinatorForms[] = $formRow;
    }
}

// Preload editable form questions in one query instead of one query per card (N+1).
$questionGroupsByForm = [];
$editableFormIDs = [];
foreach ($forms as $formRow) {
    if ((int)($formRow['totalAnswerRows'] ?? 0) === 0) {
        $editableFormIDs[] = (string)$formRow['formID'];
    }
}
if ($editableFormIDs !== []) {
    $escapedFormIDs = array_map(static fn(string $id): string => "'" . mysqli_real_escape_string($conn, $id) . "'", $editableFormIDs);
    $questionSql = "
        SELECT
            fc.formID,
            fc.categoryID,
            fc.categoryName,
            fc.categoryOrder,
            fq.questionID,
            fq.questionText,
            fq.questionType,
            fq.questionImage,
            fq.isRequired,
            fq.questionOrder
        FROM feedback_category fc
        JOIN feedback_question fq ON fq.categoryID = fc.categoryID
        WHERE fc.formID IN (" . implode(',', $escapedFormIDs) . ")
        ORDER BY fc.formID, fc.categoryOrder ASC, fq.questionOrder ASC
    ";
    $questionResult = mysqli_query($conn, $questionSql);
    while ($questionRow = $questionResult ? mysqli_fetch_assoc($questionResult) : null) {
        $formID = (string)$questionRow['formID'];
        $categoryID = (string)$questionRow['categoryID'];
        if (!isset($questionGroupsByForm[$formID][$categoryID])) {
            $questionGroupsByForm[$formID][$categoryID] = [
                'categoryID' => $questionRow['categoryID'],
                'categoryName' => $questionRow['categoryName'],
                'categoryOrder' => $questionRow['categoryOrder'],
                'questions' => [],
            ];
        }
        $questionGroupsByForm[$formID][$categoryID]['questions'][] = $questionRow;
    }
}

function feedbackPaginationItems(int $current, int $total): array {
    if ($total <= 7) {
        return range(1, $total);
    }

    $items = [1];
    $start = max(2, $current - 1);
    $end = min($total - 1, $current + 1);

    if ($start > 2) {
        $items[] = 'ellipsis';
    }
    for ($page = $start; $page <= $end; $page++) {
        $items[] = $page;
    }
    if ($end < $total - 1) {
        $items[] = 'ellipsis';
    }
    $items[] = $total;

    return $items;
}

function feedbackFormPageUrl(int $page, string $search, string $status): string {
    $params = [
        'tab' => 'forms',
        'form_page' => max(1, $page),
    ];
    if ($search !== '') {
        $params['form_search'] = $search;
    }
    if ($status !== '' && $status !== 'all') {
        $params['form_status'] = $status;
    }
    return 'feedback.php?' . http_build_query($params);
}

$answerForm = null;
$answerCategories = [];
$coordinatorAnswerOptions = [];
if ($isAnswerRequest && $selectedFormID !== '') {
    $answerForm = loadFormInfo($conn, $selectedFormID);
    if ($answerForm) {
        $answerCategories = loadQuestionGroups($conn, $selectedFormID);
        if (isCoordinatorFeedbackType((string)$answerForm['feedbackType'])) {
            $coordinatorAnswerOptions = loadCoordinatorOptions($conn, $answerForm);
        }
    }
}

[$responseForm, $categorySummary, $participantTracker, $responseGroups, $responseQuestions] = [null, [], [], [], []];
if (($isResponseView || $isPdfView) && $selectedFormID !== '') {
    [$responseForm, $categorySummary, $participantTracker, $responseGroups, $responseQuestions] = loadResponseData($conn, $selectedFormID);
}

function renderBuilder(string $builderType, array $sessions): void {
    $isParticipant = $builderType === 'participant';
    $titlePlaceholder = $isParticipant ? 'Example: Course and Trainer Feedback Form' : 'Example: Coordinator Training Review';
    ?>
    <form method="POST" enctype="multipart/form-data" class="builder-form builder-root" data-builder-type="<?= e($builderType) ?>" onsubmit="return openSubmitConfirm(event, '<?= $isParticipant ? 'Create participant feedback form?' : 'Create coordinator review form?' ?>');">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="feedbackType" value="<?= e($builderType === 'participant' ? 'participant' : 'staff_edu') ?>">
        <input type="hidden" name="create_feedback_form" value="1">

        <div class="builder-section-card">
            <div class="section-mini-label">Form Setup</div>
            <div class="form-grid">
                <div class="form-group form-full-span">
                    <label>Feedback Form Title</label>
                    <input type="text" name="title" placeholder="<?= e($titlePlaceholder) ?>" required>
                </div>
                <div class="form-group form-full-span">
                    <label>Course Session</label>
                    <select name="sessionID" required>
                        <option value="">Select course session</option>
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?= e($session['sessionID']) ?>" data-coordinator-id="<?= e($session['staffAttendeeAssignedBy'] ?? '') ?>" data-coordinator-name="<?= e($session['coordinatorName'] ?? '') ?>" data-coordinator-department="<?= e($session['coordinatorDepartment'] ?? '') ?>">
                                <?= e($session['courseName']) ?> · <?= e($session['sessionName'] ?: $session['sessionID']) ?> · <?= e(date('d M Y', strtotime($session['sessionDate']))) ?> · Trainer: <?= e($session['trainerNames'] ?: '-') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!$isParticipant): ?>
                    <div class="form-group form-full-span coordinator-auto-box" data-coordinator-preview>
                        <label>Course Coordinator</label>
                        <div class="coordinator-placeholder">Select a course session to display the coordinator name.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="question-canvas-header">
            <div>
                <div class="section-mini-label">Question Builder</div>
                <h3><?= $isParticipant ? 'Participant Feedback Categories' : 'Coordinator Review Questions' ?></h3>
            </div>
            <button type="button" class="secondary-btn" onclick="addCategory(this)">Add Category</button>
        </div>

        <div class="category-container">
            <?php if ($isParticipant): ?>
                <div class="category-builder-card locked-category" data-category-role="trainer">
                    <div class="category-builder-top">
                        <div><strong>Category 1</strong><span>Trainer rating</span></div>
                        
                    </div>
                    <div class="form-group">
                        <label>Category Name</label>
                        <input type="text" name="category_name[]" value="Trainer Evaluation" readonly required>
                    </div>
                    <div class="questions-holder">
                        <?php
                        $defaults = [
                            'The trainer explained the topic clearly.',
                            'The trainer was well prepared and responsive.'
                        ];
                        foreach ($defaults as $i => $default): ?>
                            <div class="question-builder-card">
                                <div class="question-builder-top"><strong>Question <?= $i + 1 ?></strong><button type="button" class="remove-question-btn" onclick="removeQuestion(this)">Remove</button></div>
                                <div class="form-group"><label>Question Text</label><input type="text" name="question_text[0][]" value="<?= e($default) ?>" required></div>
                                <div class="question-options-grid">
                                    <div class="form-group"><label>Question Type</label><select name="question_type[0][]" onchange="toggleImageUpload(this)"><option value="rating" selected>Rating 1 - 5</option><option value="paragraph">Comment</option><option value="image">Image Upload Answer</option></select></div>
                                    <div class="form-group required-column"><label>Required</label><label class="required-toggle"><input type="hidden" name="is_required[0][<?= $i ?>]" value="0"><input type="checkbox" name="is_required[0][<?= $i ?>]" value="1" checked><span></span><b>Required</b></label></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-question-btn" onclick="addQuestion(this)">Add Trainer Question</button>
                </div>

                <div class="category-builder-card locked-category" data-category-role="course">
                    <div class="category-builder-top">
                        <div><strong>Category 2</strong><span>Course rating</span></div>
                        
                    </div>
                    <div class="form-group">
                        <label>Category Name</label>
                        <input type="text" name="category_name[]" value="Course Evaluation" readonly required>
                    </div>
                    <div class="questions-holder">
                        <?php
                        $defaults = [
                            'The course content was useful and relevant.',
                            'The course activities and materials supported learning.'
                        ];
                        foreach ($defaults as $i => $default): ?>
                            <div class="question-builder-card">
                                <div class="question-builder-top"><strong>Question <?= $i + 1 ?></strong><button type="button" class="remove-question-btn" onclick="removeQuestion(this)">Remove</button></div>
                                <div class="form-group"><label>Question Text</label><input type="text" name="question_text[1][]" value="<?= e($default) ?>" required></div>
                                <div class="question-options-grid">
                                    <div class="form-group"><label>Question Type</label><select name="question_type[1][]" onchange="toggleImageUpload(this)"><option value="rating" selected>Rating 1 - 5</option><option value="paragraph">Comment</option><option value="image">Image Upload Answer</option></select></div>
                                    <div class="form-group required-column"><label>Required</label><label class="required-toggle"><input type="hidden" name="is_required[1][<?= $i ?>]" value="0"><input type="checkbox" name="is_required[1][<?= $i ?>]" value="1" checked><span></span><b>Required</b></label></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-question-btn" onclick="addQuestion(this)">Add Course Question</button>
                </div>

                <div class="category-builder-card overall-category" data-category-role="overall">
                    <div class="category-builder-top">
                        <div><strong>Category 3</strong><span>Overall comment</span></div>
                        
                    </div>
                    <div class="form-group">
                        <label>Category Name</label>
                        <input type="text" name="category_name[]" value="Overall Comment" readonly required>
                    </div>
                    <div class="questions-holder">
                        <div class="question-builder-card">
                            <div class="question-builder-top"><strong>Question 1</strong><button type="button" class="remove-question-btn" onclick="removeQuestion(this)">Remove</button></div>
                            <div class="form-group"><label>Question Text</label><input type="text" name="question_text[2][]" value="Please share your suggestions for improvement." required></div>
                            <div class="question-options-grid">
                                <div class="form-group"><label>Question Type</label><select name="question_type[2][]" onchange="toggleImageUpload(this)"><option value="rating">Rating 1 - 5</option><option value="paragraph" selected>Comment</option><option value="image">Image Upload Answer</option></select></div>
                                <div class="form-group required-column"><label>Required</label><label class="required-toggle"><input type="hidden" name="is_required[2][0]" value="0"><input type="checkbox" name="is_required[2][0]" value="1" checked><span></span><b>Required</b></label></div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="add-question-btn" onclick="addQuestion(this)">Add Comment Question</button>
                </div>
            <?php else: ?>
                <div class="category-builder-card" data-category-role="custom">
                    <div class="category-builder-top">
                        <div><strong>Category 1</strong></div>
                        <button type="button" class="remove-category-btn" onclick="removeCategory(this)">Remove</button>
                    </div>
                    <div class="form-group">
                        <label>Category Name</label>
                        <input type="text" name="category_name[]" value="Person in Charge Review" required>
                    </div>
                    <div class="questions-holder">
                        <div class="question-builder-card">
                            <div class="question-builder-top"><strong>Question 1</strong><button type="button" class="remove-question-btn" onclick="removeQuestion(this)">Remove</button></div>
                            <div class="form-group"><label>Question Text</label><input type="text" name="question_text[0][]" placeholder="Write your question here..." required></div>
                            <div class="question-options-grid">
                                <div class="form-group"><label>Question Type</label><select name="question_type[0][]" onchange="toggleImageUpload(this)"><option value="rating">Rating 1 - 5</option><option value="paragraph">Comment</option><option value="image">Image Upload Answer</option></select></div>
                                <div class="form-group required-column"><label>Required</label><label class="required-toggle"><input type="hidden" name="is_required[0][0]" value="0"><input type="checkbox" name="is_required[0][0]" value="1" checked><span></span><b>Required</b></label></div>
                            </div>
                            
                        </div>
                    </div>
                    <button type="button" class="add-question-btn" onclick="addQuestion(this)">Add Question</button>
                </div>
            <?php endif; ?>
        </div>

        <div class="save-bar">
            <button type="submit" class="primary-btn save-feedback-btn">
                <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                Save Feedback Form
            </button>
        </div>
    </form>
    <?php
}


function renderCreatedFormCard(array $form, array $sessions, string $baseUrl, array $questionGroupsByForm = []): void {

    $answerLink = $baseUrl . '/feedback.php?answer=1&formID=' . urlencode((string)$form['formID']);
    $isParticipantForm = strtolower((string)($form['feedbackType'] ?? 'participant')) === 'participant';
    $detailsResponseLink = 'feedback.php?responses=1&formID=' . urlencode((string)$form['formID']) . '&privacy=details';
    $anonymousResponseLink = 'feedback.php?responses=1&formID=' . urlencode((string)$form['formID']) . '&privacy=anonymous';
    $pdfLink = 'feedback.php?export_pdf=1&formID=' . urlencode((string)$form['formID']) . '&privacy=details';
    $hasResponses = (int)($form['totalAnswerRows'] ?? 0) > 0;
    $searchText = implode(' ', [
        $form['formID'] ?? '', $form['title'] ?? '', $form['courseName'] ?? '',
        $form['sessionName'] ?? '', $form['trainerNames'] ?? '',
        feedbackTypeLabel((string)($form['feedbackType'] ?? 'participant'))
    ]);
    $modalID = 'editFormModal' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$form['formID']);
    $groups = $hasResponses ? [] : ($questionGroupsByForm[(string)$form['formID']] ?? []);
    ?>
    <article class="created-form-card filterable-created-form" data-search="<?= e($searchText) ?>" data-type="<?= e($isParticipantForm ? 'participant' : 'staff_edu') ?>" data-responses="<?= e((int)($form['totalResponses'] ?? 0)) ?>">
        <div class="created-form-main">
            <div class="created-form-avatar"><svg viewBox="0 0 24 24"><path d="M9 11h6M9 15h6M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path d="M14 3v5h5"/></svg></div>
            <div><h3><?= e($form['title']) ?></h3><p><?= e($form['courseName'] ?: '-') ?></p><span><?= e($form['sessionName'] ?: $form['sessionID']) ?> · <?= e($form['trainerNames'] ?: 'Trainer not assigned') ?></span></div>
        </div>
        <div class="created-form-badges">
            <span><?= e(feedbackTypeBadge((string)$form['feedbackType'])) ?></span>
            <span><?= e($form['totalResponses']) ?> responses</span>
            <span>Avg <?= e($form['averageRating'] ?: '-') ?></span>
            <?php if ($hasResponses): ?><span class="locked-form-badge">Locked</span><?php else: ?><span class="editable-form-badge">Editable</span><?php endif; ?>
        </div>
        <div class="copy-link-box"><input type="text" value="<?= e($answerLink) ?>" readonly><button type="button" onclick="copyLink(this)">Copy</button></div>
        <div class="created-form-actions primary-actions-row">
            <a class="library-preview-btn" href="<?= e($answerLink) ?>" target="_blank">Preview</a>
            <a class="library-response-btn" href="<?= e($detailsResponseLink) ?>">View Responses</a>
            <?php if ($isParticipantForm): ?><a class="library-anonymous-btn" href="<?= e($anonymousResponseLink) ?>">Anonymous</a><?php endif; ?>
            <a class="library-pdf-btn" href="<?= e($pdfLink) ?>" target="_blank">PDF</a>
        </div>
        <div class="created-form-actions management-actions-row">
            <?php if (!$hasResponses): ?><button type="button" class="inline-action-btn library-edit-btn" onclick="openFeedbackEditModal('<?= e($modalID) ?>')">Edit</button><?php endif; ?>
            <form method="POST" onsubmit="return openSubmitConfirm(event, 'Duplicate this form as a new version?');">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="formID" value="<?= e($form['formID']) ?>">
                <input type="hidden" name="duplicate_feedback_form" value="1">
                <button type="submit" class="inline-action-btn duplicate-inline-btn">Duplicate</button>
            </form>
            <form method="POST" class="delete-form" onsubmit="return openSubmitConfirm(event, 'Are you sure you want to delete this feedback form?');">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="formID" value="<?= e($form['formID']) ?>">
                <input type="hidden" name="delete_feedback_form" value="1">
                <button type="submit" class="inline-action-btn danger-inline-btn">Delete</button>
            </form>
        </div>
    </article>

    <?php if (!$hasResponses): ?>
    <div class="modal feedback-edit-modal" id="<?= e($modalID) ?>" style="display:none" aria-hidden="true">
        <div class="feedback-edit-modal-box">
            <button type="button" class="feedback-edit-close" onclick="closeFeedbackEditModal('<?= e($modalID) ?>')">×</button>
            <div class="feedback-edit-head"><span>Edit Feedback Form</span><h2><?= e($form['title']) ?></h2><p>Edit the form setup, categories and questions.</p></div>
            <form method="POST" enctype="multipart/form-data" class="builder-form builder-root feedback-edit-form" data-builder-type="<?= $isParticipantForm ? 'participant' : 'coordinator' ?>" onsubmit="return openSubmitConfirm(event, 'Update this feedback form?');">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="formID" value="<?= e($form['formID']) ?>">
                <input type="hidden" name="update_feedback_form" value="1">
                <div class="builder-section-card">
                    <div class="section-mini-label">Form Setup</div>
                    <div class="form-grid">
                        <div class="form-group form-full-span"><label>Feedback Form Title</label><input type="text" name="title" value="<?= e($form['title']) ?>" required></div>
                        <div class="form-group form-full-span"><label>Course Session</label><select name="sessionID" required><?php foreach ($sessions as $session): ?><option value="<?= e($session['sessionID']) ?>" <?= $session['sessionID'] === $form['sessionID'] ? 'selected' : '' ?>><?= e($session['courseName']) ?> · <?= e($session['sessionName'] ?: $session['sessionID']) ?> · <?= e(date('d M Y', strtotime($session['sessionDate']))) ?></option><?php endforeach; ?></select></div>
                    </div>
                </div>
                <div class="question-canvas-header"><div><div class="section-mini-label">Question Builder</div><h3><?= $isParticipantForm ? 'Participant Feedback Categories' : 'Coordinator Review Questions' ?></h3></div><button type="button" class="secondary-btn" onclick="addCategory(this)">Add Category</button></div>
                <div class="category-container">
                    <?php $catIndex = 0; $groupCount = count($groups); foreach ($groups as $category): ?>
                        <?php
                            if ($isParticipantForm) {
                                $role = $catIndex === 0 ? 'trainer' : ($catIndex === 1 ? 'course' : ($catIndex === $groupCount - 1 ? 'overall' : 'custom'));
                            } else {
                                $role = 'custom';
                            }
                            $isFixedParticipantCategory = $isParticipantForm && in_array($role, ['trainer', 'course', 'overall'], true);
                        ?>
                        <div class="category-builder-card <?= $isFixedParticipantCategory ? ($role === 'overall' ? 'overall-category' : 'locked-category') : '' ?>" data-category-role="<?= e($role) ?>">
                            <div class="category-builder-top"><div><strong>Category <?= $catIndex + 1 ?></strong></div><?php if (!$isFixedParticipantCategory): ?><button type="button" class="remove-category-btn" onclick="removeCategory(this)">Remove</button><?php endif; ?></div>
                            <div class="form-group"><label>Category Name</label><input type="text" name="category_name[]" value="<?= e($category['categoryName']) ?>" <?= $isFixedParticipantCategory ? 'readonly' : '' ?> required></div>
                            <div class="questions-holder">
                                <?php foreach ($category['questions'] as $qIndex => $question): ?>
                                <div class="question-builder-card">
                                    <div class="question-builder-top"><strong>Question <?= $qIndex + 1 ?></strong><button type="button" class="remove-question-btn" onclick="removeQuestion(this)">Remove</button></div>
                                    <div class="form-group"><label>Question Text</label><input type="text" name="question_text[<?= $catIndex ?>][]" value="<?= e($question['questionText']) ?>" required></div>
                                    <div class="question-options-grid">
                                        <div class="form-group"><label>Question Type</label><select name="question_type[<?= $catIndex ?>][]" onchange="toggleImageUpload(this)"><?php foreach (['rating'=>'Rating 1 - 5','paragraph'=>'Comment','image'=>'Image Upload Answer'] as $type=>$label): ?><option value="<?= e($type) ?>" <?= $question['questionType'] === $type ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                                        <div class="form-group required-column"><label>Required</label><label class="required-toggle"><input type="hidden" name="is_required[<?= $catIndex ?>][<?= $qIndex ?>]" value="0"><input type="checkbox" name="is_required[<?= $catIndex ?>][<?= $qIndex ?>]" value="1" <?= (int)$question['isRequired'] === 1 ? 'checked' : '' ?>><span></span><b><?= (int)$question['isRequired'] === 1 ? 'Required' : 'Optional' ?></b></label></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="add-question-btn" onclick="addQuestion(this)">Add Question</button>
                        </div>
                    <?php $catIndex++; endforeach; ?>
                </div>
                <div class="feedback-edit-actions"><button type="button" class="cancel-btn" onclick="closeFeedbackEditModal('<?= e($modalID) ?>')">Cancel</button><button type="submit" class="primary-btn">Save Changes</button></div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php
}
