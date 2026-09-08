<?php
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!mysqli_select_db($conn, TRAINHUB_DATABASE_NAME)) {
    throw new RuntimeException('Unable to select the configured TrainHub database.');
}

$staffID = $_SESSION["staffID"] ?? $_SESSION["staff_id"] ?? "";
$staffName = $_SESSION["staffName"] ?? $_SESSION["staff_name"] ?? "Admin";

if ($staffID === "") {
    header("Location: login.php");
    exit();
}

function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function selected($value, $current) {
    return (string)$value === (string)$current ? "selected" : "";
}

function checkedValue($value, $csv) {
    $values = array_filter(array_map('trim', explode(',', (string)$csv)));
    return in_array((string)$value, $values, true) ? "checked" : "";
}

function nullableText($value) {
    $value = trim((string)$value);
    return $value === "" ? null : $value;
}

function setCurrentStaff($conn, $staffID, $staffName = null) {
    $stmt = $conn->prepare("SET @current_staff_id = ?, @current_staff_name = ?");
    $name = $staffName ?: $staffID;
    $stmt->bind_param("ss", $staffID, $name);
    $stmt->execute();
}

function tableExists($conn, $tableName) {
    $safeTable = mysqli_real_escape_string($conn, $tableName);
    $result = mysqli_query($conn, "SHOW FULL TABLES LIKE '$safeTable'");
    return $result && mysqli_num_rows($result) > 0;
}

function columnExists($conn, $tableName, $columnName) {
    $safeTable = str_replace('`', '', (string)$tableName);
    $safeColumn = mysqli_real_escape_string($conn, (string)$columnName);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    return $result && mysqli_num_rows($result) > 0;
}

function procedureExists($conn, $procedureName) {
    $stmt = $conn->prepare("\n        SELECT COUNT(*) AS total\n        FROM information_schema.ROUTINES\n        WHERE ROUTINE_SCHEMA = DATABASE()\n          AND ROUTINE_TYPE = 'PROCEDURE'\n          AND ROUTINE_NAME = ?\n    ");
    $stmt->bind_param("s", $procedureName);
    $stmt->execute();
    return (int)($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function clearStoredProcedureResults($conn) {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}

function getCount($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : [];
    return (int)($row["total"] ?? 0);
}

function nextID($conn, $table, $column, $prefix) {
    $allowed = [
        "course.courseID",
        "course_session.sessionID",
        "qr_session.qrID"
    ];

    if (!in_array($table . "." . $column, $allowed, true)) {
        throw new InvalidArgumentException("Unsupported ID generator target.");
    }

    $prefixLength = strlen($prefix);
    // Avoid LIKE with a bound parameter: some combined-schema environments
    // bind the parameter with a binary collation and MySQL then raises an
    // "Illegal mix of collations" error during ID generation.
    $stmt = $conn->prepare("SELECT `$column` AS id FROM `$table` WHERE LEFT(`$column`, ?) = ?");
    $stmt->bind_param("is", $prefixLength, $prefix);
    $stmt->execute();
    $result = $stmt->get_result();

    $max = 0;
    while ($row = $result->fetch_assoc()) {
        if (preg_match('/(\d+)$/', (string)$row["id"], $match)) {
            $max = max($max, (int)$match[1]);
        }
    }

    return $prefix . str_pad((string)($max + 1), 4, "0", STR_PAD_LEFT);
}

function uploadPoster($oldPoster = null) {
    $oldPoster = nullableText($oldPoster);

    if (!isset($_FILES["poster"]) || $_FILES["poster"]["error"] === UPLOAD_ERR_NO_FILE) {
        return $oldPoster;
    }

    if ($_FILES["poster"]["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("Poster upload failed.");
    }

    if ((int)$_FILES["poster"]["size"] > 8 * 1024 * 1024) {
        throw new Exception("Poster file must not exceed 8 MB.");
    }

    $allowedExtensions = ["jpg", "jpeg", "png", "pdf"];
    $allowedMimeTypes = ["image/jpeg", "image/png", "application/pdf"];
    $extension = strtolower(pathinfo($_FILES["poster"]["name"], PATHINFO_EXTENSION));

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES["poster"]["tmp_name"]);

    if (!in_array($extension, $allowedExtensions, true) || !in_array($mimeType, $allowedMimeTypes, true)) {
        throw new Exception("Poster file must be JPG, JPEG, PNG, or PDF only.");
    }

    $folder = "uploads/course_posters";
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new Exception("Unable to create the poster upload folder.");
    }

    $fileName = bin2hex(random_bytes(16)) . "." . $extension;
    $path = $folder . "/" . $fileName;

    if (!move_uploaded_file($_FILES["poster"]["tmp_name"], $path)) {
        throw new Exception("Failed to save poster.");
    }

    return $path;
}

function buildAttendanceLink($sessionID) {
    $isHttps = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
    $protocol = $isHttps ? "https://" : "http://";
    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    $basePath = rtrim(str_replace("\\", "/", dirname($_SERVER["PHP_SELF"] ?? "/")), "/");

    return $protocol . $host . $basePath . "/attendance.php?sessionID=" . urlencode($sessionID);
}

function mysqlDatetimeFromLocal($value) {
    $value = trim((string)$value);
    if ($value === "") return null;

    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    if (!$date) {
        throw new Exception("QR expiry date and time is not valid.");
    }

    return $date->format('Y-m-d H:i:s');
}

function htmlDatetimeLocal($value) {
    if (empty($value)) return "";
    $time = strtotime((string)$value);
    return $time ? date('Y-m-d\TH:i', $time) : "";
}

function validateQRExpiryWindow($expiryTime, $sessionDate, $startTime, $endTime) {
    if ($expiryTime === null) return;

    $sessionStart = new DateTimeImmutable($sessionDate . ' ' . $startTime);
    $sessionEnd = new DateTimeImmutable($sessionDate . ' ' . $endTime);
    $expiry = new DateTimeImmutable($expiryTime);
    $now = new DateTimeImmutable('now');
    $minExpiry = $sessionStart->modify('-7 days');
    $maxExpiry = $sessionEnd->modify('+7 days');

    if ($expiry <= $now) {
        throw new Exception('QR expiry date and time must be in the future.');
    }
    if ($expiry < $minExpiry || $expiry > $maxExpiry) {
        throw new Exception('QR expiry must be within 7 days before or after the session date and time.');
    }
}

function saveSessionQR($conn, $sessionID, $sessionDate, $endTime, $expiryInput = null, $startTime = null) {
    $attendanceLink = buildAttendanceLink($sessionID);
    $qrCode = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($attendanceLink);
    $expiryTime = mysqlDatetimeFromLocal($expiryInput ?? "");

    // NULL intentionally means no expiry. AttendanceController already treats
    // a NULL expiry as available until Staff EDU explicitly sets one later.
    if ($expiryTime !== null && $startTime !== null && $startTime !== '') {
        validateQRExpiryWindow($expiryTime, $sessionDate, $startTime, $endTime);
    }

    $stmt = $conn->prepare("SELECT qrID FROM qr_session WHERE sessionID = ? LIMIT 1");
    $stmt->bind_param("s", $sessionID);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $stmt = $conn->prepare("UPDATE qr_session SET qrCode = ?, attendanceLink = ?, expiryTime = ? WHERE sessionID = ?");
        $stmt->bind_param("ssss", $qrCode, $attendanceLink, $expiryTime, $sessionID);
        $stmt->execute();
        return;
    }

    $qrID = nextID($conn, "qr_session", "qrID", "QR");
    $stmt = $conn->prepare("INSERT INTO qr_session (qrID, qrCode, attendanceLink, expiryTime, sessionID) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $qrID, $qrCode, $attendanceLink, $expiryTime, $sessionID);
    $stmt->execute();
}

function validateSessionDateAndTime($sessionDate, $startTime, $endTime) {
    if ($sessionDate === "" || $startTime === "" || $endTime === "") {
        throw new Exception("Please complete session date, start time and end time.");
    }

    if ($sessionDate < date('Y-m-d')) {
        throw new Exception("Session date cannot be before today.");
    }

    if ($endTime <= $startTime) {
        throw new Exception("Session end time must be later than start time.");
    }
}

function insertTrainingSession($conn, $courseID, array $sessionData) {
    $sessionID = trim((string)($sessionData["sessionID"] ?? ""));
    $sessionName = nullableText($sessionData["sessionName"] ?? null);
    $sessionDate = trim((string)($sessionData["sessionDate"] ?? ""));
    $startTime = trim((string)($sessionData["startTime"] ?? ""));
    $endTime = trim((string)($sessionData["endTime"] ?? ""));
    $location = nullableText($sessionData["location"] ?? null);
    $qrExpiry = nullableText($sessionData["qrExpiry"] ?? null);

    if ($sessionDate === "" && $startTime === "" && $endTime === "") {
        return "";
    }

    validateSessionDateAndTime($sessionDate, $startTime, $endTime);

    if ($sessionID === "") {
        $sessionID = nextID($conn, "course_session", "sessionID", "CS");
    }

    $stmt = $conn->prepare("INSERT INTO course_session (sessionID, sessionDate, sessionName, startTime, endTime, location, courseID) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $sessionID, $sessionDate, $sessionName, $startTime, $endTime, $location, $courseID);
    $stmt->execute();

    saveSessionQR($conn, $sessionID, $sessionDate, $endTime, $qrExpiry, $startTime);
    return $sessionID;
}

function syncSessionTrainers($conn, $sessionID, $trainerIDs) {
    if ($sessionID === "") return;

    if (!is_array($trainerIDs)) {
        $trainerIDs = [$trainerIDs];
    }

    $trainerIDs = array_values(array_unique(array_filter(array_map('trim', $trainerIDs))));

    foreach ($trainerIDs as $trainerID) {
        $stmt = $conn->prepare("\n            INSERT IGNORE INTO session_trainer (trainerID, sessionID)\n            SELECT trainerID, ?\n            FROM trainer\n            WHERE trainerID = ?\n              AND LOWER(COALESCE(status, 'active')) = 'active'\n        ");
        $stmt->bind_param("ss", $sessionID, $trainerID);
        $stmt->execute();
    }
}

function parseSessionRowsFromPost() {
    $rows = [];
    $dates = $_POST["sessionDate"] ?? [];

    if (!is_array($dates)) {
        return $rows;
    }

    foreach ($dates as $index => $date) {
        $rows[] = [
            "sessionID" => $_POST["sessionID"][$index] ?? "",
            "sessionName" => $_POST["sessionName"][$index] ?? "",
            "sessionDate" => $date,
            "startTime" => $_POST["startTime"][$index] ?? "",
            "endTime" => $_POST["endTime"][$index] ?? "",
            "location" => $_POST["location"][$index] ?? "",
            "qrExpiry" => $_POST["qrExpiry"][$index] ?? "",
            "trainerIDs" => $_POST["trainerIDs"][$index] ?? []
        ];
    }

    return $rows;
}

function getEarliestSessionDateFromRows(array $sessionRows) {
    $dates = [];
    foreach ($sessionRows as $row) {
        if (!empty($row["sessionDate"])) {
            $dates[] = $row["sessionDate"];
        }
    }
    sort($dates);
    return $dates[0] ?? null;
}

function getEarliestSessionDateFromDB($conn, $courseID) {
    $stmt = $conn->prepare("SELECT MIN(sessionDate) AS firstDate FROM course_session WHERE courseID = ?");
    $stmt->bind_param("s", $courseID);
    $stmt->execute();
    return nullableText($stmt->get_result()->fetch_assoc()["firstDate"] ?? null);
}

function validateCloseDate($closeDate, $firstSessionDate, $isNew = true) {
    if ($closeDate === null || $firstSessionDate === null) return;

    if ($isNew && $closeDate < date('Y-m-d')) {
        throw new Exception("Registration closing date cannot be before today.");
    }

    if ($closeDate > $firstSessionDate) {
        throw new Exception("Registration closing date must be before or on the first session date.");
    }
}

function formatSessionTime($date, $start, $end) {
    if (empty($date)) return "-";
    return date("d M Y", strtotime($date)) . " • " .
           date("h:i A", strtotime($start)) . " - " .
           date("h:i A", strtotime($end));
}

function formatTrainingRating($rating) {
    $rating = max(0, min(5, (float)$rating));
    if (floor($rating) == $rating) return number_format($rating, 0);
    return rtrim(rtrim(number_format($rating, 2, ".", ""), "0"), ".");
}

function renderTrainingStars($rating) {
    $rating = max(0, min(5, (float)$rating));
    $html = '<span class="course-stars fractional-course-stars" aria-label="' . h(formatTrainingRating($rating)) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $fillPercent = round(max(0, min(100, ($rating - ($i - 1)) * 100)), 2);
        $html .= '<span class="course-star-wrap">';
        $html .= '<span class="course-star-base">★</span>';
        $html .= '<span class="course-star-fill" style="width:' . $fillPercent . '%">★</span>';
        $html .= '</span>';
    }
    return $html . '</span>';
}

function participantTypeLabel($type) {
    $type = strtolower((string)$type);
    if ($type === "new_teacher") return "New Teacher";
    if ($type === "teacher") return "Teacher";
    if ($type === "public") return "Public";
    if ($type === "staff") return "Staff";
    return ucfirst(str_replace("_", " ", $type));
}

function participantSourceID($participant) {
    if (!empty($participant["teacherID"])) return $participant["teacherID"];
    if (!empty($participant["gn_id"])) return $participant["gn_id"];
    if (!empty($participant["staffID"])) return $participant["staffID"];
    if (!empty($participant["outsider_id"])) return (string)$participant["outsider_id"];
    return $participant["participantID"] ?? "-";
}

function normalizeTrainingStatus($value) {
    $value = strtolower(trim((string)$value));
    if ($value === "history") $value = "completed";
    return in_array($value, ["upcoming", "ongoing", "completed", "cancelled"], true) ? $value : "upcoming";
}

function trainingStatusLabel($status) {
    return ucwords(str_replace("_", " ", normalizeTrainingStatus($status)));
}

function statusClass($status) {
    return "status-" . normalizeTrainingStatus($status);
}

function calculateTrainingStatusFromSessionRange($firstStart, $lastEnd) {
    if (empty($firstStart) || empty($lastEnd)) return "upcoming";

    $now = new DateTimeImmutable("now");
    $firstStartDate = new DateTimeImmutable($firstStart);
    $lastEndDate = new DateTimeImmutable($lastEnd);

    if ($now < $firstStartDate) return "upcoming";
    if ($now <= $lastEndDate) return "ongoing";
    return "completed";
}

function refreshTrainingStatuses($conn, $courseID = null) {
    $where = "";
    if ($courseID !== null && trim((string)$courseID) !== "") {
        $safeCourseID = mysqli_real_escape_string($conn, $courseID);
        $where = "WHERE c.courseID = '$safeCourseID'";
    }

    $result = mysqli_query($conn, "\n        SELECT\n            c.courseID,\n            c.status AS currentStatus,\n            MIN(CONCAT(cs.sessionDate, ' ', cs.startTime)) AS firstStart,\n            MAX(CONCAT(cs.sessionDate, ' ', cs.endTime)) AS lastEnd\n        FROM course c\n        LEFT JOIN course_session cs ON cs.courseID = c.courseID\n        $where\n        GROUP BY c.courseID, c.status\n    ");

    while ($row = mysqli_fetch_assoc($result)) {
        if (strtolower((string)$row["currentStatus"]) === "cancelled") continue;

        $newStatus = calculateTrainingStatusFromSessionRange($row["firstStart"], $row["lastEnd"]);
        if ($newStatus !== strtolower((string)$row["currentStatus"])) {
            $stmt = $conn->prepare("UPDATE course SET status = ? WHERE courseID = ?");
            $stmt->bind_param("ss", $newStatus, $row["courseID"]);
            $stmt->execute();
        }
    }
}

function normalizeTrainingMode($value) {
    $value = strtolower(trim((string)$value));
    if (!in_array($value, ["online", "physical", "hybrid"], true)) {
        throw new Exception("Training mode must be online, physical, or hybrid.");
    }
    return $value;
}

function getValidCapacity($value) {
    if (is_array($value)) $value = end($value);
    $rawValue = trim((string)$value);
    if (str_starts_with($rawValue, '-')) {
        throw new Exception("Training capacity cannot be negative.");
    }
    $value = preg_replace('/[^0-9]/', '', $rawValue);
    if ($value === "" || (int)$value < 1) {
        throw new Exception("Training capacity must be a number more than 0.");
    }
    return (int)$value;
}

function getPostedCapacity() {
    if (isset($_POST["capacity"]) && trim((string)$_POST["capacity"]) !== "") {
        return getValidCapacity($_POST["capacity"]);
    }
    return getValidCapacity($_POST["capacityDisplay"] ?? "");
}

function getValidPrice($value) {
    $value = trim((string)$value);
    if ($value === "") return 0.00;
    if (!is_numeric($value) || (float)$value < 0) {
        throw new Exception("Price cannot be negative.");
    }
    return round((float)$value, 2);
}

function parseTargetAudiencesFromPost() {
    $allowed = ["teacher", "new_teacher", "staff", "public", "other"];
    $raw = $_POST["targetAudience"] ?? ["teacher"];
    if (!is_array($raw)) $raw = explode(',', (string)$raw);

    $values = [];
    foreach ($raw as $value) {
        $value = strtolower(trim((string)$value));
        if (in_array($value, $allowed, true) && !in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    if (count($values) === 0) {
        throw new Exception("Please select at least one target audience.");
    }

    return implode(',', $values);
}

function targetAudienceContains($targetAudience, $value) {
    $values = array_filter(array_map('trim', explode(',', (string)$targetAudience)));
    return in_array($value, $values, true);
}

function formatTargetAudience($targetAudience, $otherTargetAudience = null) {
    $labels = [
        "teacher" => "Teacher",
        "new_teacher" => "New Teacher",
        "staff" => "Staff",
        "public" => "Public",
        "other" => nullableText($otherTargetAudience) ?: "Other"
    ];

    $values = array_filter(array_map('trim', explode(',', (string)$targetAudience)));
    $result = [];
    foreach ($values as $value) {
        $result[] = $labels[$value] ?? ucwords(str_replace("_", " ", $value));
    }

    return count($result) > 0 ? implode(', ', $result) : "-";
}

function getTrainingTypeByTargetAudience($targetAudience) {
    $values = array_values(array_filter(array_map('trim', explode(',', (string)$targetAudience))));
    return count($values) === 1 && $values[0] === "new_teacher" ? "LMS" : "Simple";
}

function categoryClass($category) {
    $category = strtolower((string)$category);
    if (strpos($category, "tech") !== false) return "category-purple";
    if (strpos($category, "lead") !== false) return "category-blue";
    return "category-green";
}

function modalKey($id) {
    return preg_replace('/[^A-Za-z0-9_]/', '_', (string)$id);
}

function buildParticipantID($type, $sourceID, $courseID) {
    return "P" . strtoupper(substr(sha1($type . "|" . $sourceID . "|" . $courseID), 0, 19));
}

function callApprovedParticipantProcedure($conn, $participantID, $participantType, $sourceID, $courseID) {
    $stmt = $conn->prepare("CALL sp_add_approved_participant(?, ?, ?, ?)");
    $sourceID = (string)$sourceID;
    $stmt->bind_param("ssss", $participantID, $participantType, $sourceID, $courseID);
    $stmt->execute();
    $stmt->close();
    clearStoredProcedureResults($conn);
}

function staffIDsFromText($staffIDs) {
    $staffIDs = str_replace(' ', '', (string)$staffIDs);
    return array_values(array_unique(array_filter(array_map('trim', explode(',', $staffIDs)))));
}

function getStaffIDsFromPost() {
    $raw = $_POST["staffAttendeeIDs"] ?? [];
    if (!is_array($raw)) $raw = explode(',', (string)$raw);
    $ids = array_values(array_unique(array_filter(array_map('trim', $raw))));
    return count($ids) > 0 ? implode(',', $ids) : null;
}

function parseOrganiserNameFromPost() {
    $choices = $_POST["organiserChoice"] ?? [];
    if (!is_array($choices)) $choices = [$choices];

    $organisers = [];
    if (in_array("Al Amin Edu Oasis", $choices, true)) {
        $organisers[] = "Al Amin Edu Oasis";
    }

    if (in_array("Other", $choices, true)) {
        $other = nullableText($_POST["otherOrganiserName"] ?? null);
        if ($other === null) {
            throw new Exception("Please fill in the other organiser name.");
        }
        foreach (array_filter(array_map('trim', explode(',', $other))) as $name) {
            $organisers[] = $name;
        }
    }

    if (count($organisers) === 0) {
        $organisers[] = "Al Amin Edu Oasis";
    }

    return implode(', ', array_values(array_unique($organisers)));
}

function organiserHasAlAmin($organiserName) {
    return stripos((string)$organiserName, 'Al Amin Edu Oasis') !== false;
}

function organiserOtherText($organiserName) {
    $parts = array_filter(array_map('trim', explode(',', (string)$organiserName)));
    $others = [];
    foreach ($parts as $part) {
        if (strcasecmp($part, 'Al Amin Edu Oasis') !== 0) {
            $others[] = $part;
        }
    }
    return implode(', ', $others);
}

function syncApprovedTrainingParticipants($conn, $courseID = null) {
    if (!procedureExists($conn, "sp_add_approved_participant")) {
        throw new Exception("Stored procedure sp_add_approved_participant is missing. Import the canonical TrainHub database objects first.");
    }

    $courseFilterTeacher = "";
    $courseFilterNewTeacher = "";
    $courseFilterPublic = "";
    $courseFilterCourse = "";
    $courseFilterParticipant = "";

    if ($courseID !== null && trim((string)$courseID) !== "") {
        $safeCourseID = mysqli_real_escape_string($conn, $courseID);
        $courseFilterTeacher = " AND et.course_id = '$safeCourseID'";
        $courseFilterNewTeacher = " AND eg.course_id = '$safeCourseID'";
        $courseFilterPublic = " AND eo.course_id = '$safeCourseID'";
        $courseFilterCourse = " AND c.courseID = '$safeCourseID'";
        $courseFilterParticipant = " AND cp.courseID = '$safeCourseID'";
    }

    $teachers = mysqli_query($conn, "\n        SELECT et.teacherID AS sourceID, et.course_id AS courseID\n        FROM enroll_teacher et\n        LEFT JOIN course_participant cp\n          ON cp.courseID = et.course_id\n         AND cp.teacherID = et.teacherID\n         AND LOWER(cp.participantType) = 'teacher'\n        WHERE et.status = 'approved'\n          AND cp.participantID IS NULL\n          $courseFilterTeacher\n    ");
    while ($row = mysqli_fetch_assoc($teachers)) {
        callApprovedParticipantProcedure($conn, buildParticipantID("teacher", $row["sourceID"], $row["courseID"]), "teacher", $row["sourceID"], $row["courseID"]);
    }

    $newTeachers = mysqli_query($conn, "\n        SELECT eg.gn_id AS sourceID, eg.course_id AS courseID\n        FROM enroll_guru_baru eg\n        LEFT JOIN course_participant cp\n          ON cp.courseID = eg.course_id\n         AND cp.gn_id = eg.gn_id\n         AND LOWER(cp.participantType) = 'new_teacher'\n        WHERE eg.status IN ('approved', 'auto_enrolled')\n          AND cp.participantID IS NULL\n          $courseFilterNewTeacher\n    ");
    while ($row = mysqli_fetch_assoc($newTeachers)) {
        callApprovedParticipantProcedure($conn, buildParticipantID("new_teacher", $row["sourceID"], $row["courseID"]), "new_teacher", $row["sourceID"], $row["courseID"]);
    }

    $publicParticipants = mysqli_query($conn, "\n        SELECT eo.outsider_id AS sourceID, eo.course_id AS courseID\n        FROM enroll_outsider eo\n        LEFT JOIN course_participant cp\n          ON cp.courseID = eo.course_id\n         AND cp.outsider_id = eo.outsider_id\n         AND LOWER(cp.participantType) = 'public'\n        WHERE eo.status = 'approved'\n          AND cp.participantID IS NULL\n          $courseFilterPublic\n    ");
    while ($row = mysqli_fetch_assoc($publicParticipants)) {
        callApprovedParticipantProcedure($conn, buildParticipantID("public", $row["sourceID"], $row["courseID"]), "public", (string)$row["sourceID"], $row["courseID"]);
    }

    $staffCourses = mysqli_query($conn, "\n        SELECT c.courseID, c.staffAttendeeIDs\n        FROM course c\n        WHERE COALESCE(c.staffAttendeeIDs, '') <> ''\n          $courseFilterCourse\n    ");
    while ($course = mysqli_fetch_assoc($staffCourses)) {
        foreach (staffIDsFromText($course["staffAttendeeIDs"]) as $sourceID) {
            $stmt = $conn->prepare("\n                SELECT cp.participantID\n                FROM course_participant cp\n                WHERE cp.courseID = ?\n                  AND cp.staffID = ?\n                  AND LOWER(cp.participantType) = 'staff'\n                LIMIT 1\n            ");
            $stmt->bind_param("ss", $course["courseID"], $sourceID);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $participantID = buildParticipantID("staff", $sourceID, $course["courseID"]);
                if (procedureExists($conn, "sp_add_staff_pic_participant")) {
                    $picStmt = $conn->prepare("CALL sp_add_staff_pic_participant(?, ?, ?)");
                    $picStmt->bind_param("sss", $participantID, $sourceID, $course["courseID"]);
                    $picStmt->execute();
                    $picStmt->close();
                    clearStoredProcedureResults($conn);
                } else {
                    callApprovedParticipantProcedure($conn, $participantID, "staff", $sourceID, $course["courseID"]);
                }
            }
        }
    }

    mysqli_query($conn, "\n        DELETE cp\n        FROM course_participant cp\n        WHERE LOWER(cp.participantType) = 'teacher'\n          AND cp.teacherID IS NOT NULL\n          AND NOT EXISTS (\n              SELECT 1 FROM enroll_teacher et\n              WHERE et.teacherID = cp.teacherID\n                AND et.course_id = cp.courseID\n                AND et.status = 'approved'\n          )\n          $courseFilterParticipant\n    ");

    mysqli_query($conn, "\n        DELETE cp\n        FROM course_participant cp\n        WHERE LOWER(cp.participantType) = 'new_teacher'\n          AND cp.gn_id IS NOT NULL\n          AND NOT EXISTS (\n              SELECT 1 FROM enroll_guru_baru eg\n              WHERE eg.gn_id = cp.gn_id\n                AND eg.course_id = cp.courseID\n                AND eg.status IN ('approved', 'auto_enrolled')\n          )\n          $courseFilterParticipant\n    ");

    mysqli_query($conn, "\n        DELETE cp\n        FROM course_participant cp\n        WHERE LOWER(cp.participantType) = 'public'\n          AND cp.outsider_id IS NOT NULL\n          AND NOT EXISTS (\n              SELECT 1 FROM enroll_outsider eo\n              WHERE eo.outsider_id = cp.outsider_id\n                AND eo.course_id = cp.courseID\n                AND eo.status = 'approved'\n          )\n          $courseFilterParticipant\n    ");

    mysqli_query($conn, "\n        DELETE cp\n        FROM course_participant cp\n        JOIN course c ON c.courseID = cp.courseID\n        LEFT JOIN staff_edu se ON se.staffID = cp.staffID\n        WHERE LOWER(cp.participantType) = 'staff'\n          AND cp.staffID IS NOT NULL\n          AND (\n              FIND_IN_SET(cp.staffID, REPLACE(COALESCE(c.staffAttendeeIDs, ''), ' ', '')) = 0\n              OR LOWER(COALESCE(se.status, 'inactive')) <> 'active'\n          )\n          $courseFilterParticipant\n    ");
}

function setAttendanceVerificationStatus($conn, $participantType, $attendanceID, $staffID, $approvalStatus) {
    $participantType = strtolower(trim((string)$participantType));
    $attendanceID = (int)$attendanceID;
    $approvalStatus = strtolower(trim((string)$approvalStatus));

    if ($attendanceID <= 0) {
        throw new Exception("Attendance cannot be updated because the participant has not scanned the QR yet.");
    }

    if (!in_array($approvalStatus, ["approved", "rejected"], true)) {
        throw new Exception("Attendance approval status is invalid.");
    }

    if (procedureExists($conn, "sp_verify_attendance")) {
        $rejectionReason = $approvalStatus === "rejected" ? "Marked as not approved by Staff" : null;
        $stmt = $conn->prepare("CALL sp_verify_attendance(?, ?, ?, ?, ?)");
        $stmt->bind_param("sisss", $participantType, $attendanceID, $approvalStatus, $staffID, $rejectionReason);
        $stmt->execute();
        $stmt->close();
        clearStoredProcedureResults($conn);

        if ($participantType === 'staff' && procedureExists($conn, 'sp_update_staff_credit_hour')) {
            $creditStmt = $conn->prepare("SELECT staffID FROM attendance_staff WHERE attendanceStaff_id = ? LIMIT 1");
            $creditStmt->bind_param("i", $attendanceID);
            $creditStmt->execute();
            $creditRow = $creditStmt->get_result()->fetch_assoc();
            if (!empty($creditRow['staffID'])) {
                $recalcStmt = $conn->prepare("CALL sp_update_staff_credit_hour(?)");
                $recalcStmt->bind_param("s", $creditRow['staffID']);
                $recalcStmt->execute();
                $recalcStmt->close();
                clearStoredProcedureResults($conn);
            }
        }
        return;
    }

    $map = [
        "teacher" => ["attendance", "attendance_id"],
        "staff" => ["attendance_staff", "attendanceStaff_id"],
        "new_teacher" => ["attendance_guru_baru", "attendGuruBaru_id"],
        "public" => ["attendance_outsider", "attendOutsider_id"]
    ];

    if (!isset($map[$participantType])) {
        throw new Exception("Unsupported participant type.");
    }

    [$table, $column] = $map[$participantType];
    $approvedAtSql = $approvalStatus === "approved" ? "NOW()" : "NULL";
    $timestampStatus = $approvalStatus === "approved" ? 1.00 : 0.00;
    $stmt = $conn->prepare("UPDATE `$table` SET attendance_status = ?, approvedByStaff = ?, approved_at = $approvedAtSql, timestamp_status = ? WHERE `$column` = ?");
    $stmt->bind_param("ssdi", $approvalStatus, $staffID, $timestampStatus, $attendanceID);
    $stmt->execute();
}

function saveAttendanceRemark($conn, $participantType, $attendanceID, $remarks) {
    $participantType = strtolower(trim((string)$participantType));
    $attendanceID = (int)$attendanceID;
    $remarks = trim((string)$remarks);

    if ($attendanceID <= 0) {
        throw new Exception("Remarks cannot be saved because the participant has not scanned the QR yet.");
    }

    if (strlen($remarks) > 500) {
        throw new Exception("Attendance remarks cannot exceed 500 characters.");
    }

    $map = [
        "teacher" => ["attendance", "attendance_id"],
        "staff" => ["attendance_staff", "attendanceStaff_id"],
        "new_teacher" => ["attendance_guru_baru", "attendGuruBaru_id"],
        "public" => ["attendance_outsider", "attendOutsider_id"]
    ];

    if (!isset($map[$participantType])) {
        throw new Exception("Unsupported participant type.");
    }

    [$table, $column] = $map[$participantType];
    if (!columnExists($conn, $table, "remarks")) {
        throw new Exception("Attendance remarks are not available yet. Please apply the attendance remarks database patch first.");
    }

    $storedRemark = $remarks === "" ? null : $remarks;
    $stmt = $conn->prepare("UPDATE `$table` SET remarks = ? WHERE `$column` = ?");
    $stmt->bind_param("si", $storedRemark, $attendanceID);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $checkStmt = $conn->prepare("SELECT `$column` FROM `$table` WHERE `$column` = ? LIMIT 1");
        $checkStmt->bind_param("i", $attendanceID);
        $checkStmt->execute();
        if (!$checkStmt->get_result()->fetch_assoc()) {
            throw new Exception("Attendance record was not found.");
        }
    }
}

function approveAttendance($conn, $participantType, $attendanceID, $staffID) {
    setAttendanceVerificationStatus($conn, $participantType, $attendanceID, $staffID, "approved");
}

function markAttendanceNotApproved($conn, $participantType, $attendanceID, $staffID) {
    setAttendanceVerificationStatus($conn, $participantType, $attendanceID, $staffID, "rejected");
}

function getFeedbackFormCount($conn, $courseID) {
    $stmt = $conn->prepare("\n        SELECT COUNT(DISTINCT ff.formID) AS total\n        FROM feedback_form ff\n        LEFT JOIN course_session cs ON cs.sessionID = ff.sessionID\n        WHERE ff.courseID = ? OR cs.courseID = ?\n    ");
    $stmt->bind_param("ss", $courseID, $courseID);
    $stmt->execute();
    return (int)($stmt->get_result()->fetch_assoc()["total"] ?? 0);
}

function redirectTo($url) {
    header("Location: " . $url);
    exit();
}

function buildCourseAttendanceReturnUrl($courseID, $sessionID = "") {
    $url = "course.php?view=" . urlencode((string)$courseID);
    $sessionID = trim((string)$sessionID);
    if ($sessionID !== "") {
        $url .= "&attendanceSession=" . urlencode($sessionID);
    }
    return $url . "#attendanceParticipants";
}

setCurrentStaff($conn, $staffID, $staffName);

$message = $_SESSION["flash_message"] ?? "";
$messageType = $_SESSION["flash_type"] ?? "success";
unset($_SESSION["flash_message"], $_SESSION["flash_type"]);

try {
    refreshTrainingStatuses($conn);

    /* Participant synchronization used to scan every enrollment and every
       course_participant row on every GET. Only the detail page needs those
       participants immediately, so synchronize the course being viewed. */
    $viewCourseIDForSync = trim((string)($_GET["view"] ?? ""));
    if ($_SERVER["REQUEST_METHOD"] !== "POST" && $viewCourseIDForSync !== "") {
        syncApprovedTrainingParticipants($conn, $viewCourseIDForSync);
    }
} catch (Throwable $syncError) {
    if ($message === "") {
        $message = $syncError->getMessage();
        $messageType = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $inTransaction = false;

    try {
        setCurrentStaff($conn, $staffID, $staffName);

        if ($action === "add_course") {
            $sessionRows = parseSessionRowsFromPost();
            $firstSessionDate = getEarliestSessionDateFromRows($sessionRows);
            $closeDate = nullableText($_POST["closeDate"] ?? null);
            validateCloseDate($closeDate, $firstSessionDate, true);

            $courseID = nextID($conn, "course", "courseID", "C");
            $courseName = trim((string)($_POST["courseName"] ?? ""));
            $description = nullableText($_POST["description"] ?? null);
            $capacity = getPostedCapacity();
            $price = getValidPrice($_POST["price"] ?? "");
            $courseCategory = nullableText($_POST["courseCategory"] ?? null) ?: "General";
            $targetAudience = parseTargetAudiencesFromPost();
            $otherTargetAudience = targetAudienceContains($targetAudience, "other") ? nullableText($_POST["otherTargetAudience"] ?? null) : null;
            $staffAttendeeIDs = getStaffIDsFromPost();
            $staffAttendeeAssignedBy = $staffAttendeeIDs !== null ? $staffID : null;
            $staffAttendeeAssignedDate = $staffAttendeeIDs !== null ? date('Y-m-d') : null;
            $status = "upcoming";
            $mode = normalizeTrainingMode($_POST["mode"] ?? "physical");
            $onlineLink = in_array($mode, ["online", "hybrid"], true) ? nullableText($_POST["onlineLink"] ?? null) : null;
            $whatsappGroup = nullableText($_POST["whatsappGroup"] ?? null);
            $organiserName = parseOrganiserNameFromPost();
            $courseType = getTrainingTypeByTargetAudience($targetAudience);

            if ($courseName === "") throw new Exception("Training name is required.");
            if (targetAudienceContains($targetAudience, "other") && $otherTargetAudience === null) throw new Exception("Please type the other target audience.");
            if (targetAudienceContains($targetAudience, "staff") && $staffAttendeeIDs === null) throw new Exception("Please select at least one staff when Staff is selected as target audience.");
            if (in_array($mode, ["online", "hybrid"], true) && $onlineLink === null) throw new Exception("Online link is required for Online or Hybrid training.");

            $poster = uploadPoster();
            $courseRating = 0.00;
            $conn->begin_transaction();
            $inTransaction = true;

            $stmt = $conn->prepare("\n                INSERT INTO course (\n                    courseID, courseName, description, capacity, price,\n                    courseCategory, targetAudience, otherTargetAudience,\n                    staffAttendeeIDs, staffAttendeeAssignedBy, staffAttendeeAssignedDate,\n                    courseRating, status, poster, mode, onlineLink,\n                    whatsappGroup, closeDate, organiserName, courseType\n                )\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            $stmt->bind_param(
                "sssidssssssdssssssss",
                $courseID,
                $courseName,
                $description,
                $capacity,
                $price,
                $courseCategory,
                $targetAudience,
                $otherTargetAudience,
                $staffAttendeeIDs,
                $staffAttendeeAssignedBy,
                $staffAttendeeAssignedDate,
                $courseRating,
                $status,
                $poster,
                $mode,
                $onlineLink,
                $whatsappGroup,
                $closeDate,
                $organiserName,
                $courseType
            );
            $stmt->execute();

            $sessionCount = 0;
            foreach ($sessionRows as $sessionRow) {
                $sessionID = insertTrainingSession($conn, $courseID, $sessionRow);
                if ($sessionID !== "") {
                    syncSessionTrainers($conn, $sessionID, $sessionRow["trainerIDs"]);
                    $sessionCount++;
                }
            }

            if ($sessionCount === 0) throw new Exception("Please add at least one date/time session.");

            refreshTrainingStatuses($conn, $courseID);
            syncApprovedTrainingParticipants($conn, $courseID);
            $conn->commit();
            $inTransaction = false;

            $_SESSION["flash_message"] = "Training added successfully.";
            $_SESSION["flash_type"] = "success";
            redirectTo("course.php");
        }

        if ($action === "update_course") {
            $courseID = trim((string)($_POST["courseID"] ?? ""));
            if ($courseID === "") throw new Exception("Training ID is missing.");

            $firstSessionDate = getEarliestSessionDateFromDB($conn, $courseID);
            $closeDate = nullableText($_POST["closeDate"] ?? null);
            validateCloseDate($closeDate, $firstSessionDate, false);

            $courseName = trim((string)($_POST["courseName"] ?? ""));
            $description = nullableText($_POST["description"] ?? null);
            $capacity = getPostedCapacity();
            $price = getValidPrice($_POST["price"] ?? "");
            $courseCategory = nullableText($_POST["courseCategory"] ?? null) ?: "General";
            $targetAudience = parseTargetAudiencesFromPost();
            $otherTargetAudience = targetAudienceContains($targetAudience, "other") ? nullableText($_POST["otherTargetAudience"] ?? null) : null;
            $staffAttendeeIDs = getStaffIDsFromPost();
            $staffAttendeeAssignedBy = $staffAttendeeIDs !== null ? $staffID : null;
            $staffAttendeeAssignedDate = $staffAttendeeIDs !== null ? date('Y-m-d') : null;
            $mode = normalizeTrainingMode($_POST["mode"] ?? "physical");
            $onlineLink = in_array($mode, ["online", "hybrid"], true) ? nullableText($_POST["onlineLink"] ?? null) : null;
            $whatsappGroup = nullableText($_POST["whatsappGroup"] ?? null);
            $organiserName = parseOrganiserNameFromPost();
            $courseType = getTrainingTypeByTargetAudience($targetAudience);
            $poster = uploadPoster($_POST["oldPoster"] ?? null);

            if ($courseName === "") throw new Exception("Training name is required.");
            if (targetAudienceContains($targetAudience, "other") && $otherTargetAudience === null) throw new Exception("Please type the other target audience.");
            if (targetAudienceContains($targetAudience, "staff") && $staffAttendeeIDs === null) throw new Exception("Please select at least one staff when Staff is selected as target audience.");
            if (in_array($mode, ["online", "hybrid"], true) && $onlineLink === null) throw new Exception("Online link is required for Online or Hybrid training.");

            $conn->begin_transaction();
            $inTransaction = true;

            $stmt = $conn->prepare("\n                UPDATE course\n                SET courseName = ?, description = ?, capacity = ?, price = ?,\n                    courseCategory = ?, targetAudience = ?, otherTargetAudience = ?,\n                    staffAttendeeIDs = ?, staffAttendeeAssignedBy = ?, staffAttendeeAssignedDate = ?,\n                    poster = ?, mode = ?, onlineLink = ?, whatsappGroup = ?, closeDate = ?,\n                    organiserName = ?, courseType = ?\n                WHERE courseID = ?\n            ");
            $stmt->bind_param(
                "ssidssssssssssssss",
                $courseName,
                $description,
                $capacity,
                $price,
                $courseCategory,
                $targetAudience,
                $otherTargetAudience,
                $staffAttendeeIDs,
                $staffAttendeeAssignedBy,
                $staffAttendeeAssignedDate,
                $poster,
                $mode,
                $onlineLink,
                $whatsappGroup,
                $closeDate,
                $organiserName,
                $courseType,
                $courseID
            );
            $stmt->execute();

            refreshTrainingStatuses($conn, $courseID);
            syncApprovedTrainingParticipants($conn, $courseID);
            $conn->commit();
            $inTransaction = false;

            $_SESSION["flash_message"] = "Training updated successfully.";
            $_SESSION["flash_type"] = "success";
            redirectTo("course.php?view=" . urlencode($courseID));
        }

        if ($action === "delete_course") {
            $courseID = trim((string)($_POST["courseID"] ?? ""));
            if ($courseID === "") throw new Exception("Training ID is missing.");

            $feedbackTotal = getFeedbackFormCount($conn, $courseID);
            if ($feedbackTotal > 0) {
                throw new Exception("Cannot delete this training because it already has feedback form. Please delete the feedback form first before deleting the training.");
            }

            $conn->begin_transaction();
            $inTransaction = true;
            $stmt = $conn->prepare("DELETE FROM course WHERE courseID = ?");
            $stmt->bind_param("s", $courseID);
            $stmt->execute();
            if ($stmt->affected_rows === 0) throw new Exception("Training record was not found.");
            $conn->commit();
            $inTransaction = false;

            $_SESSION["flash_message"] = "Training deleted successfully.";
            $_SESSION["flash_type"] = "success";
            redirectTo("course.php");
        }

        if ($action === "add_session") {
            $courseID = trim((string)($_POST["courseID"] ?? ""));
            if ($courseID === "") throw new Exception("Training ID is missing.");
            $sessionRow = [
                "sessionID" => "",
                "sessionName" => $_POST["sessionName"] ?? "",
                "sessionDate" => $_POST["sessionDate"] ?? "",
                "startTime" => $_POST["startTime"] ?? "",
                "endTime" => $_POST["endTime"] ?? "",
                "location" => $_POST["location"] ?? "",
                "qrExpiry" => $_POST["qrExpiry"] ?? "",
                "trainerIDs" => $_POST["trainerIDs"] ?? []
            ];

            $conn->begin_transaction();
            $inTransaction = true;
            $sessionID = insertTrainingSession($conn, $courseID, $sessionRow);
            if ($sessionID === "") throw new Exception("Please complete the session details.");
            syncSessionTrainers($conn, $sessionID, $sessionRow["trainerIDs"]);
            refreshTrainingStatuses($conn, $courseID);
            $conn->commit();
            $inTransaction = false;

            $_SESSION["flash_message"] = "Session added successfully.";
            $_SESSION["flash_type"] = "success";
            redirectTo("course.php?sessions=" . urlencode($courseID));
        }

        if ($action === "update_session") {
            $sessionID = trim((string)($_POST["sessionID"] ?? ""));
            $sessionName = nullableText($_POST["sessionName"] ?? null);
            $sessionDate = trim((string)($_POST["sessionDate"] ?? ""));
            $startTime = trim((string)($_POST["startTime"] ?? ""));
            $endTime = trim((string)($_POST["endTime"] ?? ""));
            $location = nullableText($_POST["location"] ?? null);
            $qrExpiry = nullableText($_POST["qrExpiry"] ?? null);
            $trainerIDs = $_POST["trainerIDs"] ?? [];

            if ($sessionID === "") throw new Exception("Session ID is missing.");
            validateSessionDateAndTime($sessionDate, $startTime, $endTime);

            $conn->begin_transaction();
            $inTransaction = true;

            $stmt = $conn->prepare("SELECT courseID FROM course_session WHERE sessionID = ? LIMIT 1");
            $stmt->bind_param("s", $sessionID);
            $stmt->execute();
            $sessionCourse = $stmt->get_result()->fetch_assoc();
            if (!$sessionCourse) throw new Exception("Session record was not found.");

            $stmt = $conn->prepare("UPDATE course_session SET sessionName = ?, sessionDate = ?, startTime = ?, endTime = ?, location = ? WHERE sessionID = ?");
            $stmt->bind_param("ssssss", $sessionName, $sessionDate, $startTime, $endTime, $location, $sessionID);
            $stmt->execute();

            $stmt = $conn->prepare("DELETE FROM session_trainer WHERE sessionID = ?");
            $stmt->bind_param("s", $sessionID);
            $stmt->execute();
            syncSessionTrainers($conn, $sessionID, $trainerIDs);
            saveSessionQR($conn, $sessionID, $sessionDate, $endTime, $qrExpiry, $startTime);
            refreshTrainingStatuses($conn, $sessionCourse["courseID"]);

            $conn->commit();
            $inTransaction = false;

            $_SESSION["flash_message"] = "Session updated successfully.";
            $_SESSION["flash_type"] = "success";
            redirectTo("course.php?sessions=" . urlencode($sessionCourse["courseID"]));
        }

        if ($action === "delete_session") {
            $sessionID = trim((string)($_POST["sessionID"] ?? ""));
            if ($sessionID === "") throw new Exception("Session ID is missing.");

            $conn->begin_transaction();
            $inTransaction = true;

            $stmt = $conn->prepare("\n                SELECT\n                    cs.courseID,\n                    (SELECT COUNT(*) FROM attendance a WHERE a.session_id = cs.sessionID) +\n                    (SELECT COUNT(*) FROM attendance_staff ast WHERE ast.session_id = cs.sessionID) +\n                    (SELECT COUNT(*) FROM attendance_outsider ao WHERE ao.session_id = cs.sessionID) +\n                    (SELECT COUNT(*) FROM attendance_guru_baru ag WHERE ag.session_id = cs.sessionID) AS attendanceTotal,\n                    (SELECT COUNT(*) FROM feedback_form ff WHERE ff.sessionID = cs.sessionID) AS feedbackFormTotal\n                FROM course_session cs\n                WHERE cs.sessionID = ?\n                LIMIT 1\n            ");
            $stmt->bind_param("s", $sessionID);
            $stmt->execute();
            $check = $stmt->get_result()->fetch_assoc();
            if (!$check) throw new Exception("Session record was not found.");

            if ((int)$check["attendanceTotal"] > 0 || (int)$check["feedbackFormTotal"] > 0) {
                throw new Exception("This session has attendance or feedback records and cannot be deleted.");
            }

            $stmt = $conn->prepare("DELETE FROM course_session WHERE sessionID = ?");
            $stmt->bind_param("s", $sessionID);
            $stmt->execute();
            refreshTrainingStatuses($conn, $check["courseID"]);

            $conn->commit();
            $inTransaction = false;

            $_SESSION["flash_message"] = "Session deleted successfully.";
            $_SESSION["flash_type"] = "success";
            redirectTo("course.php?sessions=" . urlencode($check["courseID"]));
        }

        if ($action === "approve_attendance") {
            $courseID = trim((string)($_POST["courseID"] ?? ""));
            $attendanceSessionID = trim((string)($_POST["attendanceSessionID"] ?? ""));
            $participantType = $_POST["participantType"] ?? "";
            $attendanceID = $_POST["attendanceID"] ?? 0;
            approveAttendance($conn, $participantType, $attendanceID, $staffID);
            redirectTo(buildCourseAttendanceReturnUrl($courseID, $attendanceSessionID));
        }

        if ($action === "unapprove_attendance") {
            $courseID = trim((string)($_POST["courseID"] ?? ""));
            $attendanceSessionID = trim((string)($_POST["attendanceSessionID"] ?? ""));
            $participantType = $_POST["participantType"] ?? "";
            $attendanceID = $_POST["attendanceID"] ?? 0;
            markAttendanceNotApproved($conn, $participantType, $attendanceID, $staffID);
            redirectTo(buildCourseAttendanceReturnUrl($courseID, $attendanceSessionID));
        }

        if ($action === "save_attendance_remark") {
            $courseID = trim((string)($_POST["courseID"] ?? ""));
            $attendanceSessionID = trim((string)($_POST["attendanceSessionID"] ?? ""));
            $participantType = $_POST["participantType"] ?? "";
            $attendanceID = $_POST["attendanceID"] ?? 0;
            $remarks = $_POST["remarks"] ?? "";
            saveAttendanceRemark($conn, $participantType, $attendanceID, $remarks);
            $_SESSION["flash_message"] = "Attendance remarks saved successfully.";
            $_SESSION["flash_type"] = "success";
            redirectTo(buildCourseAttendanceReturnUrl($courseID, $attendanceSessionID));
        }

        throw new Exception("Unsupported training action.");
    } catch (Throwable $e) {
        if ($inTransaction) {
            $conn->rollback();
        }
        $_SESSION["flash_message"] = $e->getMessage();
        $_SESSION["flash_type"] = "error";

        $returnID = trim((string)($_POST["courseID"] ?? ""));
        if (in_array($action, ["update_course"], true) && $returnID !== "") {
            redirectTo("course.php?edit=" . urlencode($returnID));
        }
        if (in_array($action, ["add_session", "update_session", "delete_session"], true) && $returnID !== "") {
            redirectTo("course.php?sessions=" . urlencode($returnID));
        }
        if (in_array($action, ["approve_attendance", "unapprove_attendance", "save_attendance_remark"], true) && $returnID !== "") {
            $returnSessionID = trim((string)($_POST["attendanceSessionID"] ?? ""));
            redirectTo(buildCourseAttendanceReturnUrl($returnID, $returnSessionID));
        }
        redirectTo("course.php");
    }
}

$search = trim((string)($_GET["search"] ?? ""));
$categoryFilter = trim((string)($_GET["category"] ?? ""));
$statusFilter = isset($_GET["status"]) && trim((string)$_GET["status"]) !== "" ? normalizeTrainingStatus($_GET["status"]) : "";

$where = "WHERE 1=1";
if ($search !== "") {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $where .= " AND (c.courseName LIKE '%$safeSearch%' OR c.description LIKE '%$safeSearch%' OR c.organiserName LIKE '%$safeSearch%' OR c.courseCategory LIKE '%$safeSearch%' OR c.courseType LIKE '%$safeSearch%')";
}
if ($categoryFilter !== "") {
    $safeCategory = mysqli_real_escape_string($conn, $categoryFilter);
    $where .= " AND c.courseCategory = '$safeCategory'";
}
if ($statusFilter !== "") {
    $safeStatus = mysqli_real_escape_string($conn, $statusFilter);
    $where .= " AND c.status = '$safeStatus'";
}

$totalTrainings = getCount($conn, "SELECT COUNT(*) AS total FROM course");
$totalUpcoming = getCount($conn, "SELECT COUNT(*) AS total FROM course WHERE status = 'upcoming'");
$totalOngoing = getCount($conn, "SELECT COUNT(*) AS total FROM course WHERE status = 'ongoing'");
$totalSessions = getCount($conn, "SELECT COUNT(*) AS total FROM course_session");

$baseCourseSelect = "\n    SELECT\n        c.*,\n        COALESCE(c.courseRating, 0) AS displayTrainingRating,\n        COALESCE(s.sessionTotal, 0) AS sessionTotal,\n        COALESCE(p.participantTotal, 0) AS participantTotal,\n        COALESCE(tr.trainerNames, '') AS trainerNames,\n        s.firstSessionDate\n    FROM course c\n    LEFT JOIN (\n        SELECT courseID, COUNT(*) AS sessionTotal, MIN(sessionDate) AS firstSessionDate\n        FROM course_session\n        GROUP BY courseID\n    ) s ON s.courseID = c.courseID\n    LEFT JOIN (\n        SELECT courseID, COUNT(*) AS participantTotal\n        FROM course_participant\n        GROUP BY courseID\n    ) p ON p.courseID = c.courseID\n    LEFT JOIN (\n        SELECT cs.courseID, GROUP_CONCAT(DISTINCT t.trainerName ORDER BY t.trainerName SEPARATOR ', ') AS trainerNames\n        FROM course_session cs\n        LEFT JOIN session_trainer st ON st.sessionID = cs.sessionID\n        LEFT JOIN trainer t ON t.trainerID = st.trainerID\n        GROUP BY cs.courseID\n    ) tr ON tr.courseID = c.courseID\n";

$courses = mysqli_query($conn, $baseCourseSelect . " $where ORDER BY c.courseID DESC");
$courseRows = [];
while ($row = mysqli_fetch_assoc($courses)) {
    $courseRows[] = $row;
}

function fetchCourseByID($conn, $courseID, $baseCourseSelect) {
    $safeCourseID = mysqli_real_escape_string($conn, $courseID);
    $result = mysqli_query($conn, $baseCourseSelect . " WHERE c.courseID = '$safeCourseID' LIMIT 1");
    return mysqli_fetch_assoc($result) ?: null;
}

$trainerOptions = [];
$trainerQuery = mysqli_query($conn, "SELECT trainerID, trainerName FROM trainer WHERE LOWER(COALESCE(status, 'active')) = 'active' ORDER BY trainerName ASC");
while ($trainer = mysqli_fetch_assoc($trainerQuery)) {
    $trainerOptions[] = $trainer;
}

$staffOptions = [];
$staffQuery = mysqli_query($conn, "SELECT staffID, staffName, department FROM staff_edu WHERE LOWER(COALESCE(status, 'active')) = 'active' ORDER BY staffName ASC");
while ($staff = mysqli_fetch_assoc($staffQuery)) {
    $staffOptions[] = $staff;
}

$categoryOptions = [];
$categoryQuery = mysqli_query($conn, "SELECT DISTINCT courseCategory FROM course WHERE courseCategory IS NOT NULL AND courseCategory <> '' ORDER BY courseCategory ASC");
while ($category = mysqli_fetch_assoc($categoryQuery)) {
    $categoryOptions[] = $category["courseCategory"];
}

$activeMode = "";
$activeCourseID = "";
if (!empty($_GET["view"])) {
    $activeMode = "view";
    $activeCourseID = trim((string)$_GET["view"]);
} elseif (!empty($_GET["edit"])) {
    $activeMode = "edit";
    $activeCourseID = trim((string)$_GET["edit"]);
} elseif (!empty($_GET["sessions"])) {
    $activeMode = "sessions";
    $activeCourseID = trim((string)$_GET["sessions"]);
}

$safeActiveCourseID = $activeCourseID !== ''
    ? mysqli_real_escape_string($conn, $activeCourseID)
    : '';

/* Detail datasets are intentionally lazy. The previous build loaded every
   session, participant and attendance row for every course even on the list
   screen, which became very slow once the combined database grew. */
$sessionsByTraining = [];
if ($safeActiveCourseID !== '' && in_array($activeMode, ['view', 'sessions'], true)) {
    $sessionQuery = mysqli_query($conn, "
        SELECT
            cs.courseID,
            cs.sessionID,
            cs.sessionDate,
            cs.sessionName,
            cs.startTime,
            cs.endTime,
            cs.location,
            qr.qrID,
            qr.qrCode,
            qr.attendanceLink,
            qr.expiryTime,
            GROUP_CONCAT(DISTINCT t.trainerName ORDER BY t.trainerName SEPARATOR ', ') AS trainerNames,
            GROUP_CONCAT(DISTINCT CONCAT(t.trainerID, '::', t.trainerName) ORDER BY t.trainerName SEPARATOR '||') AS trainerPairs
        FROM course_session cs
        LEFT JOIN qr_session qr ON qr.sessionID = cs.sessionID
        LEFT JOIN session_trainer st ON st.sessionID = cs.sessionID
        LEFT JOIN trainer t ON t.trainerID = st.trainerID
        WHERE cs.courseID = '$safeActiveCourseID'
        GROUP BY cs.courseID, cs.sessionID, cs.sessionDate, cs.sessionName, cs.startTime, cs.endTime, cs.location, qr.qrID, qr.qrCode, qr.attendanceLink, qr.expiryTime
        ORDER BY cs.sessionDate ASC, cs.startTime ASC
    ");
    while ($session = mysqli_fetch_assoc($sessionQuery)) {
        $sessionsByTraining[$session["courseID"]][] = $session;
    }
}

$selectedAttendanceSessionID = trim((string)($_GET["attendanceSession"] ?? ""));
if ($safeActiveCourseID !== '' && $activeMode === 'view') {
    $availableAttendanceSessions = $sessionsByTraining[$activeCourseID] ?? [];
    $availableAttendanceSessionIDs = array_map(
        static fn(array $row): string => (string)($row["sessionID"] ?? ""),
        $availableAttendanceSessions
    );
    if ($selectedAttendanceSessionID === '' || !in_array($selectedAttendanceSessionID, $availableAttendanceSessionIDs, true)) {
        $selectedAttendanceSessionID = (string)($availableAttendanceSessionIDs[0] ?? '');
    }
}

$participantsByTraining = [];
$attendanceBySession = [];
if ($safeActiveCourseID !== '' && $activeMode === 'view') {
    $participantQuery = mysqli_query($conn, "
        SELECT
            cp.participantID,
            cp.participantType,
            cp.participantName,
            cp.courseID,
            c.courseName,
            CASE
                WHEN cp.participantType = 'teacher' THEN COALESCE(s.schoolName, 'School')
                WHEN cp.participantType = 'staff' THEN COALESCE(se.department, 'Staff')
                WHEN cp.participantType = 'new_teacher' THEN COALESCE(sg.schoolName, 'School')
                WHEN cp.participantType = 'public' THEN COALESCE(o.organization, 'Public')
                ELSE '-'
            END AS organisationName,
            cp.RSVPStatus,
            cp.isFeedbackCompleted,
            cp.replacementStatus,
            cp.gn_id,
            cp.outsider_id,
            cp.teacherID,
            cp.staffID
        FROM course_participant cp
        JOIN course c ON cp.courseID = c.courseID
        LEFT JOIN teacher t ON cp.teacherID = t.teacherID
        LEFT JOIN v_teacher_current_school teacher_school ON t.teacherID = teacher_school.teacherID
        LEFT JOIN school s ON teacher_school.schoolID = s.schoolID
        LEFT JOIN staff_edu se ON cp.staffID = se.staffID
        LEFT JOIN guru_new gn ON cp.gn_id = gn.gn_id
        LEFT JOIN school sg ON gn.schoolID = sg.schoolID
        LEFT JOIN outsider o ON cp.outsider_id = o.outsider_id
        WHERE cp.courseID = '$safeActiveCourseID'
        ORDER BY cp.participantType ASC, cp.participantName ASC
    ");
    while ($participant = mysqli_fetch_assoc($participantQuery)) {
        $participantsByTraining[$participant["courseID"]][] = $participant;
    }

    $attendanceQuery = mysqli_query($conn, "
        SELECT 'teacher' AS participantType, CAST(a.teacher_id AS CHAR) AS sourceID,
               a.session_id AS sessionID, a.attendance_id AS attendanceID,
               a.attendance_status AS attendanceStatus, a.scanned_at AS scannedAt,
               a.approved_at AS approvedAt, a.remarks AS remarks
        FROM attendance a
        JOIN course_session csf ON csf.sessionID = a.session_id
        WHERE csf.courseID = '$safeActiveCourseID'
        UNION ALL
        SELECT 'staff', CAST(ast.staffID AS CHAR), ast.session_id, ast.attendanceStaff_id,
               ast.attendance_status, ast.scanned_at, ast.approved_at, ast.remarks
        FROM attendance_staff ast
        JOIN course_session csf ON csf.sessionID = ast.session_id
        WHERE csf.courseID = '$safeActiveCourseID'
        UNION ALL
        SELECT 'new_teacher', CAST(ag.gn_id AS CHAR), ag.session_id, ag.attendGuruBaru_id,
               ag.attendance_status, ag.scanned_at, ag.approved_at, ag.remarks
        FROM attendance_guru_baru ag
        JOIN course_session csf ON csf.sessionID = ag.session_id
        WHERE csf.courseID = '$safeActiveCourseID'
        UNION ALL
        SELECT 'public', CAST(ao.outsider_id AS CHAR), ao.session_id, ao.attendOutsider_id,
               ao.attendance_status, ao.scanned_at, ao.approved_at, ao.remarks
        FROM attendance_outsider ao
        JOIN course_session csf ON csf.sessionID = ao.session_id
        WHERE csf.courseID = '$safeActiveCourseID'
    ");
    while ($attendanceRow = mysqli_fetch_assoc($attendanceQuery)) {
        $attendanceKey = strtolower((string)$attendanceRow["participantType"]) . "|" . (string)$attendanceRow["sourceID"];
        $attendanceBySession[$attendanceRow["sessionID"]][$attendanceKey] = $attendanceRow;
    }
}

$activeCourse = $activeCourseID !== "" ? fetchCourseByID($conn, $activeCourseID, $baseCourseSelect) : null;
$today = date('Y-m-d');
$nowLocal = date('Y-m-d\TH:i');
$trainerOptionsJson = json_encode($trainerOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

function renderTargetAudienceCheckboxes($course = null) {
    $targetAudience = $course["targetAudience"] ?? "teacher";
    $items = [
        "teacher" => "Teacher",
        "new_teacher" => "New Teacher",
        "staff" => "Staff",
        "public" => "Public",
        "other" => "Other"
    ];
    ?>
    <div class="form-full">
        <label>Target Audience</label>
        <div class="checkbox-grid target-checkbox-grid">
            <?php foreach ($items as $value => $label) { ?>
                <label class="checkbox-pill">
                    <input type="checkbox" name="targetAudience[]" value="<?php echo h($value); ?>" <?php echo checkedValue($value, $targetAudience); ?>>
                    <span><?php echo h($label); ?></span>
                </label>
            <?php } ?>
        </div>
    </div>
    <?php
}

function renderStaffPICCheckboxes($staffOptions, $selectedCSV = "") {
    ?>
    <div class="form-full staff-pic-field">
        <label>Person In Charge (Staff)</label>
        <div class="staff-pic-search-wrap">
            <input type="search" class="staff-pic-search" placeholder="Search staff name or department..." autocomplete="off">
        </div>
        <div class="checkbox-scroll-list staff-pic-list">
            <?php foreach ($staffOptions as $staff) { ?>
                <label class="staff-check-row" data-staff-search="<?php echo h(strtolower(($staff["staffName"] ?? "") . ' ' . ($staff["department"] ?? "") . ' ' . ($staff["staffID"] ?? ""))); ?>">
                    <input type="checkbox" name="staffAttendeeIDs[]" value="<?php echo h($staff["staffID"]); ?>" <?php echo checkedValue($staff["staffID"], $selectedCSV); ?>>
                    <span>
                        <strong><?php echo h($staff["staffName"]); ?></strong>
                        <small><?php echo h($staff["department"] ?: "Staff"); ?></small>
                    </span>
                </label>
            <?php } ?>
        </div>
        <small class="form-help">You can search and tick more than one staff.</small>
    </div>
    <?php
}

function renderOrganiserField($course = null) {
    $organiser = $course["organiserName"] ?? "Al Amin Edu Oasis";
    $hasAlAmin = organiserHasAlAmin($organiser) || trim((string)$organiser) === "";
    $other = organiserOtherText($organiser);
    ?>
    <div class="form-full organiser-field">
        <label>Organiser Name</label>
        <div class="checkbox-grid organiser-options">
            <label class="checkbox-pill">
                <input type="checkbox" name="organiserChoice[]" value="Al Amin Edu Oasis" <?php echo $hasAlAmin ? "checked" : ""; ?>>
                <span>Al Amin Edu Oasis</span>
            </label>
            <label class="checkbox-pill">
                <input type="checkbox" name="organiserChoice[]" value="Other" <?php echo $other !== "" ? "checked" : ""; ?>>
                <span>Other</span>
            </label>
        </div>
        <input type="text" name="otherOrganiserName" class="other-organiser-input" value="<?php echo h($other); ?>" placeholder="Type other organiser name. Use comma for multiple organisers.">
    </div>
    <?php
}

function renderStaffPICNames($staffOptions, $selectedCSV) {
    $selected = staffIDsFromText($selectedCSV);
    if (count($selected) === 0) return "-";
    $names = [];
    foreach ($staffOptions as $staff) {
        if (in_array($staff["staffID"], $selected, true)) {
            $names[] = $staff["staffName"];
        }
    }
    return count($names) ? implode(', ', $names) : implode(', ', $selected);
}

function renderTrainingFormFields($course = null, $staffOptions = []) {
    $isEdit = is_array($course);
    $targetAudience = $course["targetAudience"] ?? "teacher";
    $courseType = $isEdit ? getTrainingTypeByTargetAudience($targetAudience) : "Simple";
    $organiser = $course["organiserName"] ?? "Al Amin Edu Oasis";
    ?>
    <div class="course-form-section">
        <div class="course-form-section-title">
            <h3>Training Details</h3>
        </div>

        <div class="form-grid add-course-grid">
            <div class="form-wide">
                <label>Training Name</label>
                <input type="text" name="courseName" value="<?php echo h($course["courseName"] ?? ""); ?>" required>
            </div>

            <div>
                <label>Training Category</label>
                <input type="text" name="courseCategory" value="<?php echo h($course["courseCategory"] ?? ""); ?>" placeholder="Technology / Leadership / Tarbiah" required>
            </div>

            <div>
                <label>Training Type</label>
                <input type="hidden" name="courseType" class="type-hidden" value="<?php echo h($courseType); ?>">
                <input type="text" class="type-select" value="<?php echo h($courseType); ?>" disabled>
            </div>

            <?php renderTargetAudienceCheckboxes($course); ?>

            <div class="form-wide other-target-field">
                <label>Other Target Audience</label>
                <input type="text" name="otherTargetAudience" value="<?php echo h($course["otherTargetAudience"] ?? ""); ?>" placeholder="Please specify other audience">
            </div>

            <?php renderStaffPICCheckboxes($staffOptions, $course["staffAttendeeIDs"] ?? ""); ?>

            <div class="capacity-field">
                <label>Capacity</label>
                <input type="text" name="capacityDisplay" class="capacity-input numeric-only" value="<?php echo h($course["capacity"] ?? "1"); ?>" inputmode="numeric" pattern="[0-9]+" required>
                <input type="hidden" name="capacity" class="capacity-hidden" value="<?php echo h($course["capacity"] ?? "1"); ?>">
            </div>

            <div>
                <label>Price</label>
                <input type="number" name="price" step="0.01" min="0" value="<?php echo h($course["price"] ?? "0.00"); ?>" data-price-input>
                <small class="field-error" data-price-error></small>
            </div>

            <div>
                <label>Registration Closing Date</label>
                <input
                    type="date"
                    name="closeDate"
                    class="close-date-input"
                    value="<?php echo h($course["closeDate"] ?? ""); ?>"
                    <?php if (!$isEdit) { ?>min="<?php echo date('Y-m-d'); ?>"<?php } ?>
                    <?php if ($isEdit && !empty($course["firstSessionDate"])) { ?>max="<?php echo h($course["firstSessionDate"]); ?>"<?php } ?>
                >
                <small class="form-help">Must be before or on the first session date.</small>
                <small class="field-error" data-close-date-error></small>
            </div>

            <div>
                <label>Mode</label>
                <select name="mode" class="mode-select" required>
                    <option value="physical" <?php echo selected("physical", $course["mode"] ?? "physical"); ?>>Physical</option>
                    <option value="online" <?php echo selected("online", $course["mode"] ?? ""); ?>>Online</option>
                    <option value="hybrid" <?php echo selected("hybrid", $course["mode"] ?? ""); ?>>Hybrid</option>
                </select>
            </div>

            <div class="form-wide online-link-field">
                <label>Online Link</label>
                <input type="url" name="onlineLink" value="<?php echo h($course["onlineLink"] ?? ""); ?>" placeholder="Paste meeting link for online/hybrid training">
            </div>

            <div class="form-wide">
                <label>WhatsApp Group</label>
                <input type="url" name="whatsappGroup" value="<?php echo h($course["whatsappGroup"] ?? ""); ?>" placeholder="Paste WhatsApp group link">
            </div>

            <?php renderOrganiserField($course ?: ["organiserName" => $organiser]); ?>

            <div>
                <label>Poster</label>
                <input type="file" name="poster" accept=".jpg,.jpeg,.png,.pdf">
                <?php if (!empty($course["poster"])) { ?>
                    <a href="<?php echo h($course["poster"]); ?>" target="_blank" class="current-file-link">View current poster</a>
                <?php } ?>
            </div>
        </div>

        <div class="form-full">
            <label>Description</label>
            <textarea name="description" rows="4"><?php echo h($course["description"] ?? ""); ?></textarea>
        </div>
    </div>
    <?php
}

function renderTrainerCheckboxes($trainerOptions, $selectedPairs = "") {
    $selectedIDs = [];
    foreach (explode('||', (string)$selectedPairs) as $pair) {
        if (strpos($pair, '::') !== false) {
            [$id] = explode('::', $pair, 2);
            $selectedIDs[] = $id;
        }
    }
    foreach ($trainerOptions as $trainer) { ?>
        <label class="checkbox-pill trainer-check-pill">
            <input type="checkbox" name="trainerIDs[]" value="<?php echo h($trainer["trainerID"]); ?>" <?php echo in_array($trainer["trainerID"], $selectedIDs, true) ? "checked" : ""; ?>>
            <span><?php echo h($trainer["trainerName"]); ?></span>
        </label>
    <?php }
}
