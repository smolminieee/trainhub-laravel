<?php
/*
 * CERTIFICATE MANAGEMENT FULL UPDATE V3
 * Included features:
 * - selectable certificate content fields
 * - signature image, signatory name and signatory title
 * - select-all eligible participants
 * - clear examples and editable-field guidance
 * - collapsible course/session certificate history
 * - search/filter, persistent download status and ZIP downloads
 */
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!$conn->select_db(TRAINHUB_DATABASE_NAME)) {
    http_response_code(500);
    exit("Unable to select the configured TrainHub database.");
}
date_default_timezone_set("Asia/Kuala_Lumpur");

$staffID = $_SESSION["staffID"] ?? $_SESSION["staff_id"] ?? "";
if ($staffID === "") {
    header("Location: login.php");
    exit();
}

$setAuditStaff = $conn->prepare("SET @current_staff_id = ?");
$setAuditStaff->bind_param("s", $staffID);
$setAuditStaff->execute();
$setAuditStaff->close();

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}



function generateNextId($conn, $table, $column, $prefix, $pad = 4) {
    $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^A-Za-z0-9_]/', '', $column);
    $prefixLength = strlen($prefix);
    $suffixStart = $prefixLength + 1;

    // Avoid LIKE comparisons against generated prefixes. In the combined schema
    // a bound/escaped prefix may be treated as utf8mb4_bin while the ID column is
    // utf8mb4_unicode_ci, producing an Illegal mix of collations error.
    $stmt = $conn->prepare("
        SELECT `$safeColumn` AS latest_id
        FROM `$safeTable`
        WHERE LEFT(`$safeColumn`, ?) = ?
        ORDER BY CAST(SUBSTRING(`$safeColumn`, ?) AS UNSIGNED) DESC
        LIMIT 1
    ");
    $stmt->bind_param('isi', $prefixLength, $prefix, $suffixStart);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $num = 1;

    if ($row && !empty($row["latest_id"])) {
        $num = ((int)substr($row["latest_id"], $prefixLength)) + 1;
    }

    return $prefix . str_pad($num, $pad, "0", STR_PAD_LEFT);
}


function dbTableExists($conn, $tableName) {
    $safeTable = mysqli_real_escape_string($conn, (string)$tableName);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '{$safeTable}'");
    return $result && mysqli_num_rows($result) > 0;
}

function dbColumnExists($conn, $tableName, $columnName) {
    $safeTable = mysqli_real_escape_string($conn, (string)$tableName);
    $safeColumn = mysqli_real_escape_string($conn, (string)$columnName);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && mysqli_num_rows($result) > 0;
}

function buildAttendanceEligibilityCondition($conn, $sessionID, $prefix = "cp") {
    $sessionID = mysqli_real_escape_string($conn, (string)$sessionID);
    $clauses = [];

    if (dbTableExists($conn, "attendance")) {
        $clauses[] = "(
            LOWER({$prefix}.participantType) = 'teacher'
            AND {$prefix}.teacherID IS NOT NULL
            AND EXISTS (
                SELECT 1
                FROM attendance a
                WHERE a.teacher_id = {$prefix}.teacherID
                  AND a.session_id = '{$sessionID}'
                  AND a.attendance_status = 'approved'
            )
        )";
    }

    if (dbTableExists($conn, "attendance_staff")) {
        $clauses[] = "(
            LOWER({$prefix}.participantType) = 'staff'
            AND {$prefix}.staffID IS NOT NULL
            AND EXISTS (
                SELECT 1
                FROM attendance_staff ast
                WHERE ast.staffID = {$prefix}.staffID
                  AND ast.session_id = '{$sessionID}'
                  AND ast.attendance_status = 'approved'
            )
        )";
    }

    if (dbTableExists($conn, "attendance_guru_baru")) {
        $clauses[] = "(
            LOWER({$prefix}.participantType) = 'new_teacher'
            AND {$prefix}.gn_id IS NOT NULL
            AND EXISTS (
                SELECT 1
                FROM attendance_guru_baru ag
                WHERE ag.gn_id = {$prefix}.gn_id
                  AND ag.session_id = '{$sessionID}'
                  AND ag.attendance_status = 'approved'
            )
        )";
    }

    if (dbTableExists($conn, "attendance_outsider")) {
        $clauses[] = "(
            LOWER({$prefix}.participantType) = 'public'
            AND {$prefix}.outsider_id IS NOT NULL
            AND EXISTS (
                SELECT 1
                FROM attendance_outsider ao
                WHERE ao.outsider_id = {$prefix}.outsider_id
                  AND ao.session_id = '{$sessionID}'
                  AND ao.attendance_status = 'approved'
            )
        )";
    }

    if (empty($clauses)) {
        return "0 = 1";
    }

    return "(" . implode(" OR ", $clauses) . ")";
}

function ensureDir($path) {
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function hexToRgb($hex) {
    $hex = ltrim($hex, "#");
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    ];
}

function loadImageFromPath($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($ext === "png") {
        return imagecreatefrompng($path);
    }

    if ($ext === "jpg" || $ext === "jpeg") {
        return imagecreatefromjpeg($path);
    }

    return false;
}

function saveImageOutput($image, $path) {
    imagepng($image, $path, 9);
}

function getFontPath($fontFamily = "Arial") {
    $fontFamily = trim((string)$fontFamily);

    $map = [
        "Arial" => [
            __DIR__ . "/assets/fonts/arial.ttf",
            "C:/Windows/Fonts/arial.ttf",
            "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
        ],
        "Times New Roman" => [
            __DIR__ . "/assets/fonts/times.ttf",
            "C:/Windows/Fonts/times.ttf",
            "/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf"
        ],
        "Georgia" => [
            __DIR__ . "/assets/fonts/georgia.ttf",
            "C:/Windows/Fonts/georgia.ttf",
            "/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf"
        ],
        "Verdana" => [
            __DIR__ . "/assets/fonts/verdana.ttf",
            "C:/Windows/Fonts/verdana.ttf",
            "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
        ],
        "Tahoma" => [
            __DIR__ . "/assets/fonts/tahoma.ttf",
            "C:/Windows/Fonts/tahoma.ttf",
            "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
        ],
        "Calibri" => [
            __DIR__ . "/assets/fonts/calibri.ttf",
            "C:/Windows/Fonts/calibri.ttf",
            "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
        ]
    ];

    $candidates = $map[$fontFamily] ?? $map["Arial"];

    foreach ($candidates as $font) {
        if (file_exists($font)) {
            return $font;
        }
    }

    return null;
}

function drawCenteredText($image, $text, $centerX, $centerY, $fontSize, $fontColor, $fontFamily = "Arial") {
    $rgb = hexToRgb($fontColor ?: "#0f172a");
    $color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
    $font = getFontPath($fontFamily);

    $text = (string)$text;
    $fontSize = max(8, (int)$fontSize);

    if ($font && function_exists("imagettftext")) {
        $box = imagettfbbox($fontSize, 0, $font, $text);
        $textWidth = abs($box[4] - $box[0]);
        $textHeight = abs($box[5] - $box[1]);

        $x = (int)($centerX - ($textWidth / 2));
        $y = (int)($centerY + ($textHeight / 2));

        imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
    } else {
        $fontId = 5;
        $textWidth = imagefontwidth($fontId) * strlen($text);
        $textHeight = imagefontheight($fontId);
        imagestring($image, $fontId, (int)($centerX - $textWidth / 2), (int)($centerY - $textHeight / 2), $text, $color);
    }
}

function getTemplatePositions($conn, $templateID) {
    $positions = [];
    $stmt = mysqli_prepare($conn, "
        SELECT fieldName, positionX, positionY, fontSize, fontFamily, fontColor
        FROM certificate_position
        WHERE templateID = ?
    ");
    mysqli_stmt_bind_param($stmt, "s", $templateID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $positions[$row["fieldName"]] = $row;
    }

    return $positions;
}

function getEligibleParticipant($conn, $participantID, $courseID, $sessionID) {
    $participantID = mysqli_real_escape_string($conn, (string)$participantID);
    $courseID = mysqli_real_escape_string($conn, (string)$courseID);
    $attendanceCondition = buildAttendanceEligibilityCondition($conn, $sessionID, "cp");

    $sql = "
        SELECT
            cp.participantID,
            cp.participantName,
            cp.participantType,
            cp.organisationName,
            cp.courseID,
            cp.email,
            cp.teacherID,
            cp.gn_id,
            cp.outsider_id,
            cp.staffID
        FROM course_participant cp
        WHERE cp.participantID = '{$participantID}'
          AND cp.courseID = '{$courseID}'
          AND cp.isFeedbackCompleted = 1
          AND {$attendanceCondition}
        LIMIT 1
    ";

    $result = mysqli_query($conn, $sql);
    return $result ? (mysqli_fetch_assoc($result) ?: null) : null;
}

function certificateFieldDefinitions() {
    return [
        "participant_name" => [
            "label" => "Participant Name",
            "sample" => "Nur Aisyah Ahmad",
            "type" => "text",
            "entry" => "Automatic from the selected participant record.",
            "entryType" => "automatic",
            "x" => 50,
            "y" => 42,
            "size" => 28,
            "color" => "#0f172a",
            "default" => true
        ],
        "course_name" => [
            "label" => "Course Name",
            "sample" => "Professional Development Training",
            "type" => "text",
            "entry" => "Automatic from the selected course.",
            "entryType" => "automatic",
            "x" => 50,
            "y" => 57,
            "size" => 21,
            "color" => "#0f172a",
            "default" => true
        ],
        "session_date" => [
            "label" => "Session Date",
            "sample" => "30 Jul 2026",
            "type" => "text",
            "entry" => "Automatic from the selected course session.",
            "entryType" => "automatic",
            "x" => 50,
            "y" => 68,
            "size" => 16,
            "color" => "#334155",
            "default" => true
        ],
        "trainer_name" => [
            "label" => "Trainer Name",
            "sample" => "Ahmad Firdaus",
            "type" => "text",
            "entry" => "Automatic from the selected trainer.",
            "entryType" => "automatic",
            "x" => 30,
            "y" => 82,
            "size" => 16,
            "color" => "#334155",
            "default" => true
        ],
        "generated_date" => [
            "label" => "Generated Date",
            "sample" => "30 Jul 2026",
            "type" => "text",
            "entry" => "Automatic on the day the certificate is generated.",
            "entryType" => "automatic",
            "x" => 70,
            "y" => 82,
            "size" => 16,
            "color" => "#334155",
            "default" => true
        ],
        "custom_text_1" => [
            "label" => "Custom Text",
            "sample" => "For successfully completing the training",
            "type" => "text",
            "entry" => "Enter or edit this line before generating certificates.",
            "entryType" => "editable",
            "x" => 50,
            "y" => 34,
            "size" => 16,
            "color" => "#334155",
            "default" => false
        ],
        "signature_image" => [
            "label" => "Signature Image",
            "sample" => "Uploaded signature image",
            "type" => "image",
            "entry" => "Upload or replace the signature image before generating certificates.",
            "entryType" => "upload",
            "x" => 70,
            "y" => 76,
            "size" => 180,
            "color" => "#0f172a",
            "default" => false
        ],
        "signature_name" => [
            "label" => "Signatory Name",
            "sample" => "Dr. Ahmad Bin Ali",
            "type" => "text",
            "entry" => "Enter or edit the signatory name when adding the signature.",
            "entryType" => "editable",
            "x" => 70,
            "y" => 84,
            "size" => 14,
            "color" => "#0f172a",
            "default" => false
        ],
        "signature_title" => [
            "label" => "Signatory Position / Title",
            "sample" => "Programme Director",
            "type" => "text",
            "entry" => "Enter or edit the position or title for this certificate batch.",
            "entryType" => "editable",
            "x" => 70,
            "y" => 88,
            "size" => 12,
            "color" => "#334155",
            "default" => false
        ]
    ];
}

function drawCenteredImage($canvas, $sourcePath, $centerX, $centerY, $targetWidth) {
    if ($sourcePath === null || $sourcePath === "") {
        return;
    }

    $resolvedPath = $sourcePath;
    if (!file_exists($resolvedPath)) {
        $resolvedPath = __DIR__ . "/" . ltrim($sourcePath, "/");
    }

    if (!file_exists($resolvedPath)) {
        return;
    }

    $source = loadImageFromPath($resolvedPath);
    if (!$source) {
        return;
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    if ($sourceWidth < 1 || $sourceHeight < 1) {
        imagedestroy($source);
        return;
    }

    $targetWidth = max(40, min(600, (int)$targetWidth));
    $targetHeight = max(20, (int)round($targetWidth * ($sourceHeight / $sourceWidth)));
    $x = (int)round($centerX - ($targetWidth / 2));
    $y = (int)round($centerY - ($targetHeight / 2));

    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);
    imagecopyresampled(
        $canvas,
        $source,
        $x,
        $y,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $sourceWidth,
        $sourceHeight
    );

    imagedestroy($source);
}

function makeSignatureBackgroundTransparent($sourcePath, $outputPath) {
    $sourceData = file_get_contents($sourcePath);
    $source = $sourceData !== false ? imagecreatefromstring($sourceData) : false;
    if (!$source) {
        return false;
    }

    $width = imagesx($source);
    $height = imagesy($source);
    if ($width < 1 || $height < 1) {
        imagedestroy($source);
        return false;
    }

    $canvas = imagecreatetruecolor($width, $height);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
    imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgba = imagecolorat($source, $x, $y);
            $alpha = ($rgba & 0x7F000000) >> 24;
            $red = ($rgba >> 16) & 0xFF;
            $green = ($rgba >> 8) & 0xFF;
            $blue = $rgba & 0xFF;

            // Remove white or near-white background from scanned/signature images.
            if ($red >= 238 && $green >= 238 && $blue >= 238) {
                $alpha = 127;
            }

            $color = imagecolorallocatealpha($canvas, $red, $green, $blue, $alpha);
            imagesetpixel($canvas, $x, $y, $color);
        }
    }

    $saved = imagepng($canvas, $outputPath, 9);
    imagedestroy($source);
    imagedestroy($canvas);

    return $saved;
}

function uploadSignatureFile() {
    if (!isset($_FILES["signatureFile"]) || $_FILES["signatureFile"]["error"] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES["signatureFile"]["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("Signature upload failed.");
    }

    if ((int)$_FILES["signatureFile"]["size"] > 5 * 1024 * 1024) {
        throw new Exception("Signature image must not exceed 5 MB.");
    }

    $allowedExtensions = ["png", "jpg", "jpeg"];
    $allowedMimeTypes = ["image/png", "image/jpeg"];
    $extension = strtolower(pathinfo($_FILES["signatureFile"]["name"], PATHINFO_EXTENSION));

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES["signatureFile"]["tmp_name"]);

    if (!in_array($extension, $allowedExtensions, true) || !in_array($mimeType, $allowedMimeTypes, true)) {
        throw new Exception("Signature must be a PNG, JPG or JPEG image.");
    }

    ensureDir(__DIR__ . "/uploads/certificate_signatures");
    $relativePath = "uploads/certificate_signatures/signature_" . bin2hex(random_bytes(12)) . ".png";
    $absolutePath = __DIR__ . "/" . $relativePath;

    if (!makeSignatureBackgroundTransparent($_FILES["signatureFile"]["tmp_name"], $absolutePath)) {
        throw new Exception("Unable to save the transparent signature image.");
    }

    return $relativePath;
}

function safeDownloadName($value, $fallback = "file") {
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim((string)$value));
    $value = trim($value, "._-");
    return $value !== "" ? $value : $fallback;
}

function getCertificateDownloadLogPath() {
    ensureDir(__DIR__ . "/generated/downloads");
    return __DIR__ . "/generated/downloads/certificate_download_log.json";
}

function readCertificateDownloadLog() {
    $path = getCertificateDownloadLogPath();

    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === "") {
        return [];
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function markCertificatesDownloaded(array $certificateIDs, $staffID) {
    $certificateIDs = array_values(array_unique(array_filter(array_map("strval", $certificateIDs))));
    if (empty($certificateIDs)) {
        return;
    }

    $path = getCertificateDownloadLogPath();
    $log = readCertificateDownloadLog();
    $downloadedAt = date("Y-m-d H:i:s");

    foreach ($certificateIDs as $certificateID) {
        $log[$certificateID] = [
            "downloadedAt" => $downloadedAt,
            "downloadedByStaff" => (string)$staffID
        ];
    }

    $encoded = json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || file_put_contents($path, $encoded, LOCK_EX) === false) {
        throw new Exception("Unable to update the certificate download status.");
    }
}



function getCertificateEmailLogPath() {
    ensureDir(__DIR__ . "/generated/email_logs");
    return __DIR__ . "/generated/email_logs/certificate_email_log.json";
}

function readCertificateEmailLog() {
    $path = getCertificateEmailLogPath();

    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === "") {
        return [];
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function markCertificateEmailStatus($certificateID, $email, $status, $staffID, $message = "") {
    $path = getCertificateEmailLogPath();
    $log = readCertificateEmailLog();

    $log[(string)$certificateID] = [
        "status" => (string)$status,
        "email" => (string)$email,
        "sentAt" => $status === "sent" ? date("Y-m-d H:i:s") : ($log[(string)$certificateID]["sentAt"] ?? null),
        "lastAttemptAt" => date("Y-m-d H:i:s"),
        "sentByStaff" => (string)$staffID,
        "message" => (string)$message
    ];

    $encoded = json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || file_put_contents($path, $encoded, LOCK_EX) === false) {
        throw new Exception("Unable to update the certificate email status.");
    }
}

function resolveGeneratedCertificatePath($storedPath) {
    $relativePath = ltrim((string)$storedPath, "/\\");
    if ($relativePath === "") {
        return null;
    }

    $candidates = [
        __DIR__ . "/" . $relativePath,
    ];

    if (function_exists('public_path')) {
        $candidates[] = public_path($relativePath);
    }

    $allowedRoots = [realpath(__DIR__ . "/generated/certificates")];
    if (function_exists('public_path')) {
        $allowedRoots[] = realpath(public_path("generated/certificates"));
    }
    $allowedRoots = array_values(array_filter($allowedRoots));

    foreach ($candidates as $candidate) {
        $absolutePath = realpath($candidate);
        if (!$absolutePath || !is_file($absolutePath)) {
            continue;
        }

        foreach ($allowedRoots as $allowedRoot) {
            if ($absolutePath === $allowedRoot || str_starts_with($absolutePath, rtrim($allowedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                return $absolutePath;
            }
        }
    }

    return null;
}

function sendCertificateEmailWithAttachment(array $certificateRow) {
    $absolutePath = resolveGeneratedCertificatePath($certificateRow["generatedCertificate"] ?? "");

    if (!$absolutePath) {
        return [false, "Generated certificate file was not found."];
    }

    return app(\App\Services\CertificateMailer::class)->send($certificateRow, $absolutePath);
}

function generateCertificateImage($conn, $certificateID, $participant, $course, $session, $trainer, $template, $staffID, array $generationOptions = []) {
    $templatePath = $template["templateFile"];

    if (!file_exists($templatePath)) {
        $templatePath = __DIR__ . "/" . $templatePath;
    }

    if (!file_exists($templatePath)) {
        throw new Exception("Template file cannot be found.");
    }

    $image = loadImageFromPath($templatePath);

    if (!$image) {
        throw new Exception("Template must be PNG, JPG or JPEG for automatic generation.");
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $positions = getTemplatePositions($conn, $template["templateID"]);

    if (empty($positions)) {
        imagedestroy($image);
        throw new Exception("Please save field positions for this template before generating certificates.");
    }

    $definitions = certificateFieldDefinitions();
    $selectedFields = $generationOptions["selectedFields"] ?? array_keys($positions);
    if (!is_array($selectedFields)) {
        $selectedFields = [];
    }
    $selectedFields = array_values(array_intersect(array_keys($definitions), array_unique($selectedFields)));

    if (empty($selectedFields)) {
        imagedestroy($image);
        throw new Exception("Please choose at least one certificate field.");
    }

    $sessionDateText = !empty($session["sessionDate"])
        ? date("d M Y", strtotime($session["sessionDate"]))
        : "";
    $sessionTimeText = (!empty($session["startTime"]) && !empty($session["endTime"]))
        ? date("h:i A", strtotime($session["startTime"])) . " - " . date("h:i A", strtotime($session["endTime"]))
        : "";
    $generatedDateText = date("d M Y");

    $data = [
        "participant_name" => $participant["participantName"] ?? "",
        "course_name" => $course["courseName"] ?? "",
        "session_date" => $sessionDateText,
        "trainer_name" => $trainer["trainerName"] ?? "",
        "generated_date" => $generatedDateText,
        "custom_text_1" => trim((string)($generationOptions["customText1"] ?? "")),
        "signature_name" => trim((string)($generationOptions["signatureName"] ?? "")),
        "signature_title" => trim((string)($generationOptions["signatureTitle"] ?? ""))
    ];

    foreach ($selectedFields as $fieldName) {
        if (!isset($positions[$fieldName])) {
            continue;
        }

        $pos = $positions[$fieldName];
        $x = ((float)$pos["positionX"] / 100) * $width;
        $y = ((float)$pos["positionY"] / 100) * $height;

        if ($fieldName === "signature_image") {
            drawCenteredImage(
                $image,
                $generationOptions["signaturePath"] ?? null,
                $x,
                $y,
                (int)$pos["fontSize"]
            );
            continue;
        }

        $text = trim((string)($data[$fieldName] ?? ""));
        if ($text === "") {
            continue;
        }

        drawCenteredText(
            $image,
            $text,
            $x,
            $y,
            (int)$pos["fontSize"],
            $pos["fontColor"],
            $pos["fontFamily"]
        );
    }

    ensureDir(__DIR__ . "/generated/certificates");
ensureDir(__DIR__ . "/generated/email_logs");
ensureDir(__DIR__ . "/uploads/certificate_signatures");
ensureDir(__DIR__ . "/generated/downloads");
    $relativePath = "generated/certificates/" . $certificateID . "_" . preg_replace('/[^A-Za-z0-9_-]/', '', $participant["participantID"]) . ".png";
    $outputPath = __DIR__ . "/" . $relativePath;

    saveImageOutput($image, $outputPath);

    if (function_exists('public_path')) {
        $publicOutputPath = public_path($relativePath);
        ensureDir(dirname($publicOutputPath));
        if (realpath(dirname($publicOutputPath)) !== realpath(dirname($outputPath))) {
            saveImageOutput($image, $publicOutputPath);
        }
    }

    imagedestroy($image);

    return $relativePath;
}

$message = "";
$errorMessage = "";
$activeTab = $_GET["tab"] ?? "generate";
if (!in_array($activeTab, ["generate", "history"], true)) {
    $activeTab = "generate";
}

ensureDir(__DIR__ . "/uploads/certificate_templates");
ensureDir(__DIR__ . "/generated/certificates");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedToken = (string)($_POST["csrf_token"] ?? "");
    if ($postedToken === "" || !hash_equals($_SESSION["csrf_token"], $postedToken)) {
        $errorMessage = "Your form session has expired. Please refresh the page and try again.";
        $_POST = [];
    }
}



/* =========================
   SEND CERTIFICATES BY EMAIL
========================= */
if (isset($_POST["send_certificates"]) || isset($_POST["send_single_certificate"])) {
    $activeTab = "history";

    if (isset($_POST["send_single_certificate"])) {
        $certificateIDs = [trim((string)$_POST["send_single_certificate"])];
    } else {
        $certificateIDs = $_POST["certificateID"] ?? [];
        if (!is_array($certificateIDs)) {
            $certificateIDs = [$certificateIDs];
        }
    }

    $certificateIDs = array_values(array_unique(array_filter(array_map('trim', $certificateIDs))));

    if (empty($certificateIDs)) {
        $errorMessage = "Please select at least one generated certificate to send.";
    } else {
        try {
            $safeIDs = [];
            foreach ($certificateIDs as $certificateID) {
                $safeIDs[] = "'" . mysqli_real_escape_string($conn, $certificateID) . "'";
            }

            $result = mysqli_query($conn, "
                SELECT
                    cert.certificateID,
                    cert.generatedCertificate,
                    cert.generatedDate,
                    cert.participantID,
                    cert.courseID,
                    cert.sessionID,
                    cert.trainerID,
                    cp.participantName,
                    cp.email,
                    c.courseName,
                    cs.sessionName,
                    cs.sessionDate,
                    cs.startTime,
                    cs.endTime
                FROM certificate cert
                INNER JOIN course_participant cp ON cert.participantID = cp.participantID
                INNER JOIN course c ON cert.courseID = c.courseID
                INNER JOIN course_session cs ON cert.sessionID = cs.sessionID
                WHERE cert.certificateID IN (" . implode(',', $safeIDs) . ")
                  AND cert.status = 'generated'
                ORDER BY cp.participantName ASC
            ");

            $sentCount = 0;
            $failedCount = 0;
            $failureMessages = [];

            while ($row = $result ? mysqli_fetch_assoc($result) : null) {
                [$sent, $sendMessage] = sendCertificateEmailWithAttachment($row);

                if ($sent) {
                    $sentCount++;
                    markCertificateEmailStatus($row["certificateID"], $row["email"], "sent", $staffID, $sendMessage);
                } else {
                    $failedCount++;
                    $failureMessages[] = $sendMessage;
                    markCertificateEmailStatus($row["certificateID"], $row["email"] ?? "", "failed", $staffID, $sendMessage);
                }
            }

            if ($sentCount > 0) {
                $message = $sentCount . " certificate email(s) sent successfully.";
                if ($failedCount > 0) {
                    $message .= " " . $failedCount . " email(s) could not be sent.";
                }
            } else {
                $firstFailure = !empty($failureMessages) ? (string)$failureMessages[0] : "No generated certificate could be sent.";
                $errorMessage = "Certificate email was not sent. " . $firstFailure;
            }
        } catch (Throwable $e) {
            $errorMessage = "Certificate email failed: " . $e->getMessage();
        }
    }
}

/* =========================
   DOWNLOAD SELECTED CERTIFICATES
========================= */
if (isset($_POST["download_certificates"])) {
    $activeTab = "history";
    $certificateIDs = $_POST["certificateID"] ?? [];
    if (!is_array($certificateIDs)) {
        $certificateIDs = [$certificateIDs];
    }

    $certificateIDs = array_values(array_unique(array_filter(array_map('trim', $certificateIDs))));

    if (empty($certificateIDs)) {
        $errorMessage = "Please select at least one generated certificate to download.";
    } else {
        try {
            $safeIDs = [];
            foreach ($certificateIDs as $certificateID) {
                $safeIDs[] = "'" . mysqli_real_escape_string($conn, $certificateID) . "'";
            }

            $result = mysqli_query($conn, "
                SELECT
                    cert.certificateID,
                    cert.generatedCertificate,
                    cp.participantName,
                    c.courseName,
                    cs.sessionID,
                    cs.sessionName,
                    cs.sessionDate
                FROM certificate cert
                INNER JOIN course_participant cp ON cert.participantID = cp.participantID
                INNER JOIN course_session cs ON cert.sessionID = cs.sessionID
                INNER JOIN course c ON cs.courseID = c.courseID
                WHERE cert.certificateID IN (" . implode(',', $safeIDs) . ")
                ORDER BY c.courseName, cs.sessionDate, cp.participantName
            ");

            $downloadRows = [];
            while ($row = $result ? mysqli_fetch_assoc($result) : null) {
                $absolutePath = resolveGeneratedCertificatePath($row["generatedCertificate"] ?? "");

                if ($absolutePath) {
                    $row["absolutePath"] = $absolutePath;
                    $downloadRows[] = $row;
                }
            }

            if (empty($downloadRows)) {
                throw new Exception("The selected certificate files could not be found.");
            }

            if (count($downloadRows) === 1) {
                $row = $downloadRows[0];
                $extension = strtolower(pathinfo($row["absolutePath"], PATHINFO_EXTENSION)) ?: "png";
                $fileName = safeDownloadName($row["certificateID"] . "_" . $row["participantName"], "certificate") . "." . $extension;

                markCertificatesDownloaded([$row["certificateID"]], $staffID);

                $contentTypes = [
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'pdf' => 'application/pdf',
                ];
                header("Content-Type: " . ($contentTypes[$extension] ?? 'application/octet-stream'));
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
                header("Content-Length: " . filesize($row["absolutePath"]));
                readfile($row["absolutePath"]);
                exit;
            }

            if (!class_exists("ZipArchive")) {
                throw new Exception("The PHP ZIP extension is required to download multiple certificates together.");
            }

            $zipName = "certificates_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . ".zip";
            $zipPath = __DIR__ . "/generated/downloads/" . $zipName;
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception("Unable to prepare the certificate ZIP file.");
            }

            foreach ($downloadRows as $row) {
                $courseFolder = safeDownloadName($row["courseName"], "course");
                $sessionLabel = ($row["sessionName"] ?: $row["sessionID"]) . "_" . date("Y-m-d", strtotime($row["sessionDate"]));
                $sessionFolder = safeDownloadName($sessionLabel, "session");
                $extension = strtolower(pathinfo($row["absolutePath"], PATHINFO_EXTENSION)) ?: "png";
                $entryName = $courseFolder . "/" . $sessionFolder . "/" . safeDownloadName($row["certificateID"] . "_" . $row["participantName"], "certificate") . "." . $extension;
                $zip->addFile($row["absolutePath"], $entryName);
            }

            $zip->close();

            markCertificatesDownloaded(
                array_column($downloadRows, "certificateID"),
                $staffID
            );

            header("Content-Type: application/zip");
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            header("Content-Length: " . filesize($zipPath));
            readfile($zipPath);
            @unlink($zipPath);
            exit;
        } catch (Throwable $e) {
            $errorMessage = "Certificate download failed: " . $e->getMessage();
        }
    }
}

/* =========================
   UPLOAD TEMPLATE
========================= */
if (isset($_POST["upload_template"])) {
    $templateName = trim($_POST["templateName"] ?? "");

    if ($templateName === "") {
        $errorMessage = "Please enter template name.";
    } elseif (empty($_FILES["templateFile"]["name"])) {
        $errorMessage = "Please upload a certificate template image.";
    } else {
        $allowed = ["png", "jpg", "jpeg"];
        $ext = strtolower(pathinfo($_FILES["templateFile"]["name"], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $errorMessage = "Only PNG, JPG and JPEG templates can be used for auto generation.";
        } else {
            $templateID = generateNextId($conn, "certificate_template", "templateID", "CT", 4);
            $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $templateName);
            $filePath = "uploads/certificate_templates/" . $templateID . "_" . $safeName . "." . $ext;

            if (move_uploaded_file($_FILES["templateFile"]["tmp_name"], __DIR__ . "/" . $filePath)) {
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO certificate_template
                    (templateID, templateName, templateFile, uploadedByStaff, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                mysqli_stmt_bind_param($stmt, "ssss", $templateID, $templateName, $filePath, $staffID);
                mysqli_stmt_execute($stmt);

                $message = "Certificate template uploaded successfully.";
            } else {
                $errorMessage = "Template upload failed.";
            }
        }
    }
}

/* =========================
   SAVE POSITIONS + SELECTED CONTENT
========================= */
if (isset($_POST["save_positions"])) {
    $templateID = trim((string)($_POST["templateID"] ?? ""));
    $enabledFields = $_POST["enabledField"] ?? [];
    $positionX = $_POST["positionX"] ?? [];
    $positionY = $_POST["positionY"] ?? [];
    $fontSize = $_POST["fontSize"] ?? [];
    $fontColor = $_POST["fontColor"] ?? [];
    $fontFamily = $_POST["fontFamily"] ?? [];

    if (!is_array($enabledFields)) {
        $enabledFields = [$enabledFields];
    }

    $fieldDefinitions = certificateFieldDefinitions();
    $allowedFields = array_keys($fieldDefinitions);
    $enabledFields = array_values(array_unique(array_intersect($allowedFields, array_map('strval', $enabledFields))));

    if ($templateID === "") {
        $errorMessage = "Please select template first.";
    } elseif (empty($enabledFields)) {
        $errorMessage = "Please choose at least one field to place inside the certificate.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $delete = mysqli_prepare($conn, "DELETE FROM certificate_position WHERE templateID = ?");
            mysqli_stmt_bind_param($delete, "s", $templateID);
            mysqli_stmt_execute($delete);

            foreach ($enabledFields as $fieldName) {
                $definition = $fieldDefinitions[$fieldName];
                $x = isset($positionX[$fieldName]) ? (float)$positionX[$fieldName] : (float)$definition["x"];
                $y = isset($positionY[$fieldName]) ? (float)$positionY[$fieldName] : (float)$definition["y"];
                $x = max(0, min(100, $x));
                $y = max(0, min(100, $y));

                $size = isset($fontSize[$fieldName]) ? (int)$fontSize[$fieldName] : (int)$definition["size"];
                if ($definition["type"] === "image") {
                    $size = max(40, min(600, $size));
                } else {
                    $size = max(8, min(120, $size));
                }

                $color = trim((string)($fontColor[$fieldName] ?? $definition["color"]));
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                    $color = $definition["color"];
                }

                $family = trim((string)($fontFamily[$fieldName] ?? "Arial"));
                $allowedFonts = ["Arial", "Times New Roman", "Georgia", "Verdana", "Tahoma", "Calibri"];
                if (!in_array($family, $allowedFonts, true)) {
                    $family = "Arial";
                }

                $positionID = generateNextId($conn, "certificate_position", "positionID", "CPOS", 4);
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO certificate_position
                    (positionID, templateID, fieldName, positionX, positionY, fontSize, fontFamily, fontColor)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param($stmt, "sssddiss", $positionID, $templateID, $fieldName, $x, $y, $size, $family, $color);
                mysqli_stmt_execute($stmt);
            }

            mysqli_commit($conn);
            $message = "Certificate layout saved successfully.";
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $errorMessage = "Position cannot be saved: " . $e->getMessage();
        }
    }
}

/* =========================
   GENERATE CERTIFICATE
========================= */
if (isset($_POST["generate_certificate"])) {
    $courseID = trim((string)($_POST["courseID"] ?? ""));
    $sessionID = trim((string)($_POST["sessionID"] ?? ""));
    $trainerID = trim((string)($_POST["trainerID"] ?? ""));
    $templateID = trim((string)($_POST["templateID"] ?? ""));
    $participantIDs = $_POST["participantID"] ?? [];
    $selectedFields = $_POST["certificateField"] ?? [];
    $customText1 = trim((string)($_POST["customText1"] ?? ""));
    $signatureName = trim((string)($_POST["signatureName"] ?? ""));
    $signatureTitle = trim((string)($_POST["signatureTitle"] ?? ""));

    if (!is_array($participantIDs)) {
        $participantIDs = [$participantIDs];
    }
    if (!is_array($selectedFields)) {
        $selectedFields = [$selectedFields];
    }

    $fieldDefinitions = certificateFieldDefinitions();
    $selectedFields = array_values(array_unique(array_intersect(array_keys($fieldDefinitions), array_map('strval', $selectedFields))));
    $participantIDs = array_values(array_unique(array_filter(array_map('trim', $participantIDs))));

    if ($courseID === "" || $sessionID === "" || $trainerID === "" || $templateID === "" || empty($participantIDs)) {
        $errorMessage = "Please select course, session, trainer, template and at least one participant.";
    } elseif (empty($selectedFields)) {
        $errorMessage = "Please choose at least one item to print inside the certificate.";
    } else {
        $signaturePath = null;
        $transactionStarted = false;

        try {
            $signaturePath = uploadSignatureFile();

            if (in_array("signature_image", $selectedFields, true) && $signaturePath === null) {
                throw new Exception("Please upload a signature image or remove Signature Image from the selected certificate content.");
            }

            $stmt = mysqli_prepare($conn, "SELECT * FROM course WHERE courseID = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $courseID);
            mysqli_stmt_execute($stmt);
            $course = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            $stmt = mysqli_prepare($conn, "SELECT * FROM course_session WHERE sessionID = ? AND courseID = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "ss", $sessionID, $courseID);
            mysqli_stmt_execute($stmt);
            $session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            $stmt = mysqli_prepare($conn, "
                SELECT t.*
                FROM trainer t
                INNER JOIN session_trainer st ON st.trainerID = t.trainerID
                WHERE t.trainerID = ?
                  AND st.sessionID = ?
                LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt, "ss", $trainerID, $sessionID);
            mysqli_stmt_execute($stmt);
            $trainer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            $stmt = mysqli_prepare($conn, "SELECT * FROM certificate_template WHERE templateID = ? AND status = 'active' LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $templateID);
            mysqli_stmt_execute($stmt);
            $template = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$course || !$session || !$trainer || !$template) {
                throw new Exception("Selected course, session, trainer or template is invalid.");
            }

            $savedPositions = getTemplatePositions($conn, $templateID);
            $selectedFields = array_values(array_intersect($selectedFields, array_keys($savedPositions)));
            if (empty($selectedFields)) {
                throw new Exception("None of the selected certificate fields has a saved position for this template.");
            }

            mysqli_begin_transaction($conn);
            $transactionStarted = true;

            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $generationOptions = [
                "selectedFields" => $selectedFields,
                "customText1" => $customText1,
                "signaturePath" => $signaturePath,
                "signatureName" => $signatureName,
                "signatureTitle" => $signatureTitle
            ];

            foreach ($participantIDs as $participantID) {
                $participant = getEligibleParticipant($conn, $participantID, $courseID, $sessionID);

                if (!$participant) {
                    $skippedCount++;
                    continue;
                }

                $checkStmt = mysqli_prepare($conn, "
                    SELECT certificateID
                    FROM certificate
                    WHERE participantID = ?
                    AND courseID = ?
                    AND sessionID = ?
                    AND trainerID = ?
                    LIMIT 1
                ");
                mysqli_stmt_bind_param($checkStmt, "ssss", $participantID, $courseID, $sessionID, $trainerID);
                mysqli_stmt_execute($checkStmt);
                $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));

                if ($existing && !empty($existing["certificateID"])) {
                    $certificateID = $existing["certificateID"];
                    $generatedPath = generateCertificateImage(
                        $conn,
                        $certificateID,
                        $participant,
                        $course,
                        $session,
                        $trainer,
                        $template,
                        $staffID,
                        $generationOptions
                    );

                    $stmt = mysqli_prepare($conn, "
                        UPDATE certificate
                        SET courseID = ?,
                            templateID = ?,
                            generatedCertificate = ?,
                            generatedByStaff = ?,
                            generatedDate = CURRENT_TIMESTAMP,
                            status = 'generated'
                        WHERE certificateID = ?
                    ");
                    mysqli_stmt_bind_param($stmt, "sssss", $courseID, $templateID, $generatedPath, $staffID, $certificateID);
                    mysqli_stmt_execute($stmt);

                    $updatedCount++;
                } else {
                    $certificateID = generateNextId($conn, "certificate", "certificateID", "CERT", 4);
                    $generatedPath = generateCertificateImage(
                        $conn,
                        $certificateID,
                        $participant,
                        $course,
                        $session,
                        $trainer,
                        $template,
                        $staffID,
                        $generationOptions
                    );

                    $stmt = mysqli_prepare($conn, "
                        INSERT INTO certificate
                        (certificateID, participantID, courseID, sessionID, trainerID, templateID, generatedCertificate, generatedByStaff, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'generated')
                    ");
                    mysqli_stmt_bind_param($stmt, "ssssssss", $certificateID, $participantID, $courseID, $sessionID, $trainerID, $templateID, $generatedPath, $staffID);
                    mysqli_stmt_execute($stmt);

                    $createdCount++;
                }
            }

            mysqli_commit($conn);
            $transactionStarted = false;

            $totalGenerated = $createdCount + $updatedCount;

            if ($totalGenerated > 0) {
                $message = $createdCount . " new certificate(s) generated";
                if ($updatedCount > 0) {
                    $message .= " and " . $updatedCount . " existing certificate(s) regenerated";
                }
                if ($skippedCount > 0) {
                    $message .= ". " . $skippedCount . " participant(s) were skipped because they are no longer eligible";
                }
                $message .= ".";
            } else {
                $errorMessage = "No certificate was generated. Please check attendance, feedback completion and saved field positions.";
            }
        } catch (Throwable $e) {
            if ($transactionStarted) {
                mysqli_rollback($conn);
            }
            $errorMessage = "Certificate cannot be generated: " . $e->getMessage();
        }
    }
}

/* =========================
   PAGE DATA
========================= */
$selectedCourseID = $_GET["courseID"] ?? ($_POST["courseID"] ?? "");
$selectedSessionID = $_GET["sessionID"] ?? ($_POST["sessionID"] ?? "");
$selectedTemplateID = $_GET["templateID"] ?? ($_POST["templateID"] ?? "");
$selectedTrainerID = $_GET["trainerID"] ?? ($_POST["trainerID"] ?? "");

$courses = mysqli_query($conn, "
    SELECT courseID, courseName, status, courseCategory
    FROM course
    ORDER BY courseName ASC
");

$sessions = [];
if ($selectedCourseID !== "") {
    $stmt = mysqli_prepare($conn, "
        SELECT sessionID, sessionName, sessionDate, startTime, endTime, location
        FROM course_session
        WHERE courseID = ?
        ORDER BY sessionDate ASC, startTime ASC
    ");
    mysqli_stmt_bind_param($stmt, "s", $selectedCourseID);
    mysqli_stmt_execute($stmt);
    $sessionResult = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($sessionResult)) {
        $sessions[] = $row;
    }
}

$trainers = [];
if ($selectedSessionID !== "") {
    $stmt = mysqli_prepare($conn, "
        SELECT t.trainerID, t.trainerName, t.expertise
        FROM session_trainer st
        INNER JOIN trainer t ON st.trainerID = t.trainerID
        WHERE st.sessionID = ?
        AND t.status = 'active'
        ORDER BY t.trainerName ASC
    ");
    mysqli_stmt_bind_param($stmt, "s", $selectedSessionID);
    mysqli_stmt_execute($stmt);
    $trainerResult = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($trainerResult)) {
        $trainers[] = $row;
    }
} else {
    $trainerResult = mysqli_query($conn, "
        SELECT trainerID, trainerName, expertise
        FROM trainer
        WHERE status = 'active'
        ORDER BY trainerName ASC
    ");
    while ($row = mysqli_fetch_assoc($trainerResult)) {
        $trainers[] = $row;
    }
}

$templates = mysqli_query($conn, "
    SELECT templateID, templateName, templateFile
    FROM certificate_template
    WHERE status = 'active'
    ORDER BY uploadDate DESC
");

$selectedTemplate = null;
$positions = [];
if ($selectedTemplateID !== "") {
    $stmt = mysqli_prepare($conn, "
        SELECT *
        FROM certificate_template
        WHERE templateID = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "s", $selectedTemplateID);
    mysqli_stmt_execute($stmt);
    $selectedTemplate = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($selectedTemplate) {
        $positions = getTemplatePositions($conn, $selectedTemplateID);
    }
}

$eligibleParticipants = [];
if ($selectedCourseID !== "" && $selectedSessionID !== "") {
    $courseIDSafe = mysqli_real_escape_string($conn, $selectedCourseID);
    $attendanceCondition = buildAttendanceEligibilityCondition($conn, $selectedSessionID, "cp");

    $sql = "
        SELECT
            cp.participantID,
            cp.participantName,
            cp.participantType,
            cp.organisationName,
            cp.email,
            cp.isFeedbackCompleted,
            CASE WHEN {$attendanceCondition} THEN 1 ELSE 0 END AS hasAttendance
        FROM course_participant cp
        WHERE cp.courseID = '{$courseIDSafe}'
        ORDER BY cp.participantName ASC
    ";

    $result = mysqli_query($conn, $sql);

    while ($row = $result ? mysqli_fetch_assoc($result) : null) {
        if ((int)$row["hasAttendance"] === 1 && (int)$row["isFeedbackCompleted"] === 1) {
            $eligibleParticipants[] = $row;
        }
    }
}

$generatedCertificates = mysqli_query($conn, "
    SELECT
        cert.certificateID,
        cert.generatedCertificate,
        cert.generatedDate,
        cert.status,
        cp.participantID,
        cp.participantName,
        cp.email,
        c.courseID,
        c.courseName,
        cs.sessionID,
        cs.sessionName,
        cs.sessionDate,
        cs.startTime,
        cs.endTime,
        cs.location,
        t.trainerName,
        se.staffName
    FROM certificate cert
    INNER JOIN course_participant cp ON cert.participantID = cp.participantID
    INNER JOIN course c ON cert.courseID = c.courseID
    INNER JOIN course_session cs ON cert.sessionID = cs.sessionID AND cs.courseID = cert.courseID
    INNER JOIN trainer t ON cert.trainerID = t.trainerID
    INNER JOIN staff_edu se ON cert.generatedByStaff = se.staffID
    ORDER BY c.courseName ASC, cs.sessionDate DESC, cs.startTime ASC, cp.participantName ASC
");

$certificateDownloadLog = readCertificateDownloadLog();
$certificateEmailLog = readCertificateEmailLog();
$generatedCourseGroups = [];
$generatedSessionFilterOptions = [];
$totalGeneratedCertificateCount = 0;
$totalDownloadedCertificateCount = 0;
$totalSentCertificateCount = 0;

while ($certRow = $generatedCertificates ? mysqli_fetch_assoc($generatedCertificates) : null) {
    $courseKey = (string)$certRow["courseID"];
    $sessionKey = (string)$certRow["sessionID"];
    $downloadInfo = $certificateDownloadLog[$certRow["certificateID"]] ?? null;
    $emailInfo = $certificateEmailLog[$certRow["certificateID"]] ?? null;

    $certRow["isDownloaded"] = is_array($downloadInfo) && !empty($downloadInfo["downloadedAt"]);
    $certRow["downloadedAt"] = $certRow["isDownloaded"] ? $downloadInfo["downloadedAt"] : null;
    $certRow["emailStatus"] = is_array($emailInfo) ? ($emailInfo["status"] ?? "not_sent") : "not_sent";
    $certRow["emailSentAt"] = is_array($emailInfo) ? ($emailInfo["sentAt"] ?? null) : null;
    $certRow["emailLastAttemptAt"] = is_array($emailInfo) ? ($emailInfo["lastAttemptAt"] ?? null) : null;
    $certRow["emailMessage"] = is_array($emailInfo) ? ($emailInfo["message"] ?? "") : "";

    if ($certRow["isDownloaded"]) {
        $totalDownloadedCertificateCount++;
    }
    if ($certRow["emailStatus"] === "sent") {
        $totalSentCertificateCount++;
    }
    $totalGeneratedCertificateCount++;

    if (!isset($generatedCourseGroups[$courseKey])) {
        $generatedCourseGroups[$courseKey] = [
            "courseID" => $certRow["courseID"],
            "courseName" => $certRow["courseName"],
            "certificateCount" => 0,
            "downloadedCount" => 0,
            "sentCount" => 0,
            "sessions" => []
        ];
    }

    if (!isset($generatedCourseGroups[$courseKey]["sessions"][$sessionKey])) {
        $generatedCourseGroups[$courseKey]["sessions"][$sessionKey] = [
            "sessionID" => $certRow["sessionID"],
            "sessionName" => $certRow["sessionName"],
            "sessionDate" => $certRow["sessionDate"],
            "startTime" => $certRow["startTime"],
            "endTime" => $certRow["endTime"],
            "location" => $certRow["location"],
            "certificateCount" => 0,
            "downloadedCount" => 0,
            "sentCount" => 0,
            "certificates" => []
        ];

        $generatedSessionFilterOptions[$sessionKey] = [
            "sessionID" => $certRow["sessionID"],
            "sessionName" => $certRow["sessionName"],
            "courseName" => $certRow["courseName"],
            "sessionDate" => $certRow["sessionDate"]
        ];
    }

    $generatedCourseGroups[$courseKey]["certificateCount"]++;
    $generatedCourseGroups[$courseKey]["sessions"][$sessionKey]["certificateCount"]++;

    if ($certRow["isDownloaded"]) {
        $generatedCourseGroups[$courseKey]["downloadedCount"]++;
        $generatedCourseGroups[$courseKey]["sessions"][$sessionKey]["downloadedCount"]++;
    }

    if ($certRow["emailStatus"] === "sent") {
        $generatedCourseGroups[$courseKey]["sentCount"]++;
        $generatedCourseGroups[$courseKey]["sessions"][$sessionKey]["sentCount"]++;
    }

    $generatedCourseGroups[$courseKey]["sessions"][$sessionKey]["certificates"][] = $certRow;
}

$fontOptions = ["Arial", "Times New Roman", "Georgia", "Verdana", "Tahoma", "Calibri"];
$fieldDefinitions = certificateFieldDefinitions();
$fieldLabels = [];
$defaultPositions = [];
foreach ($fieldDefinitions as $fieldName => $definition) {
    $fieldLabels[$fieldName] = $definition["label"];
    $defaultPositions[$fieldName] = [
        "x" => $definition["x"],
        "y" => $definition["y"],
        "size" => $definition["size"],
        "color" => $definition["color"]
    ];
}

$enabledFieldNames = !empty($positions)
    ? array_keys($positions)
    : array_keys(array_filter($fieldDefinitions, function ($definition) {
        return !empty($definition["default"]);
    }));

if (isset($_POST["send_certificates"]) || isset($_POST["send_single_certificate"]) || isset($_POST["download_certificates"])) {
    $activeTab = "history";
}
