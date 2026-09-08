<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | TrainHub Al Amin</title>

    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo file_exists(public_path('assets/css/dashboard.css')) ? filemtime(public_path('assets/css/dashboard.css')) : time(); ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo file_exists(public_path('assets/css/style.css')) ? filemtime(public_path('assets/css/style.css')) : time(); ?>">

</head>

<body class="trainhub-app page-dashboard">

@include('partials.topbar')

<div class="page">

    <div class="dashboard-header">
        <div>
            <h1>Training Dashboard</h1>
            <p>
                Welcome back, <?php echo h($staff_name); ?>!
                Here’s what’s happening with your training programs.
            </p>
        </div>
    </div>

    <div class="content-grid">

        <div class="left-content">

            <section class="stats-grid">

                <div class="stat-card">
                    <div class="stat-icon stat-blue">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                            <path d="M4 4v15.5A2.5 2.5 0 0 0 6.5 22H20V6a2 2 0 0 0-2-2H4Z"/>
                            <path d="M8 8h8"/>
                            <path d="M8 12h6"/>
                        </svg>
                    </div>

                    <div>
                        <span>Total Trainings</span>
                        <h2><?php echo (int)$total_training; ?></h2>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon stat-green">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="7" r="4"/>
                            <path d="M6 21v-2a6 6 0 0 1 12 0v2"/>
                        </svg>
                    </div>

                    <div>
                        <span>Total Trainers</span>
                        <h2><?php echo (int)$total_trainer; ?></h2>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon stat-purple">
                        <svg viewBox="0 0 24 24">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9.5" cy="7" r="4"/>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                        </svg>
                    </div>

                    <div>
                        <span>Total Teachers</span>
                        <h2><?php echo (int)$total_teacher; ?></h2>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon stat-orange">
                        <svg viewBox="0 0 24 24">
                            <path d="M3 21h18"/>
                            <path d="M5 21V7l7-4 7 4v14"/>
                            <path d="M9 21v-6h6v6"/>
                        </svg>
                    </div>

                    <div>
                        <span>Total Schools</span>
                        <h2><?php echo (int)$total_school; ?></h2>
                    </div>
                </div>

            </section>

            <section class="panel training-panel">
                <div class="panel-header">
                    <h2>Training List</h2>
                    <a href="course.php" class="dashboard-training-link">View all trainings →</a>
                </div>

                <div class="training-list-clean">
                    <div class="training-list-head">
                        <span>Training Name</span>
                        <span>Date</span>
                        <span>Trainer</span>
                        <span>Status</span>
                    </div>

                    <?php if ($trainingList && mysqli_num_rows($trainingList) > 0) { ?>
                        <?php while ($training = mysqli_fetch_assoc($trainingList)) { ?>
                            <div class="training-row-clean">

                                <div class="training-title-clean">
                                    <div class="training-icon-clean">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                            <path d="M4 4v15.5A2.5 2.5 0 0 0 6.5 22H20V6a2 2 0 0 0-2-2H4Z"/>
                                            <path d="M8 8h8"/>
                                            <path d="M8 12h6"/>
                                        </svg>
                                    </div>

                                    <div>
                                        <strong><?php echo h($training["courseName"]); ?></strong>
                                        <span><?php echo h($training["description"] ?? "No description"); ?></span>
                                    </div>
                                </div>

                                <div class="training-date-clean">
                                    <?php
                                    if (!empty($training["startDate"])) {
                                        echo date("d M Y", strtotime((string)$training["startDate"]));

                                        if (
                                            !empty($training["endDate"]) &&
                                            $training["endDate"] !== $training["startDate"]
                                        ) {
                                            echo " - " . date("d M Y", strtotime((string)$training["endDate"]));
                                        }
                                    } else {
                                        echo "-";
                                    }
                                    ?>
                                </div>

                                <div class="training-trainer-clean">
                                    <?php echo h($training["trainerNames"] ?? "Not assigned"); ?>
                                </div>

                                <div>
                                    <span class="status-badge status-<?php echo h(statusClass($training["status"] ?? "upcoming")); ?>">
                                        <?php echo h(displayStatus($training["status"] ?? "upcoming")); ?>
                                    </span>
                                </div>

                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="training-row-empty">
                            No training records found.
                        </div>
                    <?php } ?>
                </div>

            </section>

            <section class="panel audit-panel" id="audit">
                <div id="auditLogContent">
                    <?php echo renderAuditContent($conn, $auditType, $auditMonth, $auditPage); ?>
                </div>
            </section>

        </div>

        <aside class="right-content">

            <section class="side-panel credit-card">
                <div class="panel-header side-header credit-card-header">
                    <div>
                        <h2>Staff Training Hours</h2>
                        <span class="credit-year-caption"><?php echo (int)$currentCreditYear; ?> progress · resets to 0 every calendar year.</span>
                    </div>
                    <button type="button" class="credit-history-open-btn" onclick="openCreditHistoryModal()">View History</button>
                </div>

                <div class="credit-chart-wrap">
                    <div class="credit-ring" style="--credit-percent: <?php echo (float)$creditPercent; ?>%;" aria-label="<?php echo (int)$creditPercent; ?> percent of annual credit completed"></div>

                    <div class="credit-center">
                        <strong><?php echo (int)$creditPercent; ?>%</strong>
                        <span>Completed</span>
                    </div>
                </div>

                <div class="credit-details">
                    <div>
                        <span>Total Credit</span>
                        <strong><?php echo number_format((float)$achievedCredit, 2); ?> / <?php echo (int)$targetCredit; ?> hrs</strong>
                    </div>

                    <div>
                        <span>Training Credit</span>
                        <strong><?php echo number_format((float)$trainingCredit, 2); ?> / <?php echo (int)$trainingTargetCredit; ?> hrs</strong>
                    </div>

                    <div>
                        <span>Tarbiah Credit</span>
                        <strong><?php echo number_format((float)$tarbiahCredit, 2); ?> / <?php echo (int)$tarbiahTargetCredit; ?> hrs</strong>
                    </div>

                    <div>
                        <span>Sessions Attended</span>
                        <strong><?php echo (int)$staffTrainingSessionCount; ?> Sessions</strong>
                    </div>

                    <div>
                        <span>Latest Attendance</span>
                        <strong><?php echo h($latestTrainingDate); ?></strong>
                    </div>

                    <div>
                        <span>Status</span>
                        <strong class="status-pill">
                            <?php echo ($creditPercent >= 70) ? "On Track" : "Need Improvement"; ?>
                        </strong>
                    </div>
                </div>

                <div class="credit-card-footer-note">
                    <span>Current year:</span>
                    <strong><?php echo (int)$currentCreditYear; ?></strong>
                    <span>·</span>
                    <span><?php echo count($staffCreditCourses); ?> activity record(s)</span>
                </div>
            </section>

            <section class="side-panel">
                <div class="panel-header side-header">
                    <h2>Upcoming Sessions</h2>
                    <a href="course.php">View training</a>
                </div>

                <div class="upcoming-list">
                    <?php if ($upcomingTrainings && mysqli_num_rows($upcomingTrainings) > 0) { ?>
                        <?php while ($upcoming = mysqli_fetch_assoc($upcomingTrainings)) { ?>
                            <div class="upcoming-item">
                                <div class="date-box">
                                    <span><?php echo strtoupper(date("M", strtotime((string)$upcoming["sessionDate"]))); ?></span>
                                    <strong><?php echo date("d", strtotime((string)$upcoming["sessionDate"])); ?></strong>
                                </div>

                                <div class="upcoming-info">
                                    <strong><?php echo h($upcoming["courseName"]); ?></strong>
                                    <span>
                                        <?php
                                        if (!empty($upcoming["trainerNames"])) {
                                            echo h($upcoming["trainerNames"]);
                                        } elseif (!empty($upcoming["location"])) {
                                            echo h($upcoming["location"]);
                                        } else {
                                            echo "No trainer assigned";
                                        }
                                        ?>
                                    </span>
                                </div>

                                <div class="upcoming-time">
                                    <?php echo date("h:i A", strtotime((string)$upcoming["startTime"])); ?>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="empty-state">No upcoming session found.</div>
                    <?php } ?>
                </div>
            </section>

            <section class="side-panel calendar-card">
                <div class="calendar-top">
                    <h2>Calendar</h2>

                    <div class="calendar-controls">
                        <select id="calendarMonth"></select>
                        <select id="calendarYear"></select>
                    </div>
                </div>

                <div id="calendar"></div>
            </section>

        </aside>

    </div>

</div>

<div class="modal credit-history-modal" id="creditHistoryModal">
    <div class="modal-content credit-history-modal-content">
        <div class="modal-header">
            <div>
                <span>Staff Credit Hours</span>
                <h2>Credit Hours History</h2>
            </div>
            <button type="button" onclick="closeCreditHistoryModal()" aria-label="Close">×</button>
        </div>

        <div class="credit-history-modal-body">
            <form method="GET" class="credit-history-filter-form">
                <div class="credit-history-filter-field">
                    <label for="creditHistoryYear">Year</label>
                    <select name="credit_history_year" id="creditHistoryYear">
                        <?php foreach ($creditYears as $creditYear) { ?>
                            <option value="<?php echo (int)$creditYear; ?>" <?php echo $creditYear === $selectedHistoryYear ? 'selected' : ''; ?>>
                                <?php echo (int)$creditYear; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <button type="submit" class="filter-btn">Filter</button>
            </form>

            <div class="credit-history-summary">
                <div><span>Year</span><strong><?php echo (int)$selectedHistoryYear; ?></strong></div>
                <div><span>Activities</span><strong><?php echo count($staffCreditHistoryCourses); ?></strong></div>
                <div><span>Training</span><strong><?php echo number_format((float)$historyTrainingCredit, 2); ?> / 30 hrs</strong></div>
                <div><span>Tarbiah</span><strong><?php echo number_format((float)$historyTarbiahCredit, 2); ?> / 10 hrs</strong></div>
                <div><span>Total Credit</span><strong><?php echo number_format((float)$historyCreditTotal, 2); ?> / 40 hrs</strong></div>
            </div>

            <?php if (empty($staffCreditHistoryCourses)) { ?>
                <div class="credit-history-empty">No training or Tarbiah participation recorded for <?php echo (int)$selectedHistoryYear; ?>.</div>
            <?php } else { ?>
                <div class="credit-history-table-wrap">
                    <table class="credit-history-table">
                        <thead>
                            <tr>
                                <th>Activity</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Details</th>
                                <th>Base Hours</th>
                                <th>Credit Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffCreditHistoryCourses as $creditCourse) { ?>
                                <tr>
                                    <td><strong><?php echo h($creditCourse['activityName']); ?></strong></td>
                                    <td><span class="credit-source-badge credit-source-<?php echo h($creditCourse['sourceType']); ?>"><?php echo ($creditCourse['sourceType'] === 'tarbiah') ? 'Tarbiah' : 'Training'; ?></span></td>
                                    <td>
                                        <?php echo h(date('d M Y', strtotime((string)$creditCourse['firstDate']))); ?>
                                        <?php if ($creditCourse['lastDate'] !== $creditCourse['firstDate']) { ?>
                                            – <?php echo h(date('d M Y', strtotime((string)$creditCourse['lastDate']))); ?>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if (($creditCourse['sourceType'] ?? '') === 'tarbiah') { ?>
                                            <div class="credit-activity-details">
                                                <?php if (!empty($creditCourse['activitySchool'])) { ?><span><?php echo h($creditCourse['activitySchool']); ?></span><?php } ?>
                                                <?php if (!empty($creditCourse['activityStartTime']) && !empty($creditCourse['activityEndTime'])) { ?>
                                                    <span><?php echo h(date('g:i A', strtotime((string)$creditCourse['activityStartTime']))); ?> – <?php echo h(date('g:i A', strtotime((string)$creditCourse['activityEndTime']))); ?></span>
                                                <?php } ?>
                                                <?php if (!empty($creditCourse['activityLocation'])) { ?><span><?php echo h($creditCourse['activityLocation']); ?></span><?php } ?>
                                            </div>
                                        <?php } else { ?>
                                            <span><?php echo (int)($creditCourse['sessionsAttended'] ?? 0); ?> session(s)</span>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo number_format((float)$creditCourse['baseHours'], 2); ?> hrs</td>
                                    <td><strong><?php echo number_format((float)$creditCourse['creditHours'], 2); ?> hrs</strong></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="modal" id="trainingListModal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <span>Trainings on Selected Date</span>
                <h2 id="trainingListTitle">Trainings</h2>
            </div>
            <button type="button" onclick="closeTrainingListModal()">×</button>
        </div>

        <div class="training-list" id="trainingListContainer"></div>
    </div>
</div>

<div class="modal" id="trainingDetailModal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <span>Training Details</span>
                <h2 id="modalTitle">Training Name</h2>
            </div>
            <button type="button" onclick="closeTrainingDetailModal()">×</button>
        </div>

        <p class="modal-desc" id="modalDescription"></p>

        <div class="detail-grid">
            <div class="detail-box"><span>Date</span><strong id="modalDate"></strong></div>
            <div class="detail-box"><span>Time</span><strong id="modalTime"></strong></div>
            <div class="detail-box"><span>Location</span><strong id="modalLocation"></strong></div>
            <div class="detail-box"><span>Session</span><strong id="modalSessionName"></strong></div>
            <div class="detail-box"><span>Category</span><strong id="modalCategory"></strong></div>
            <div class="detail-box"><span>Type</span><strong id="modalTrainingType"></strong></div>
            <div class="detail-box"><span>Mode</span><strong id="modalMode"></strong></div>
            <div class="detail-box"><span>Status</span><strong id="modalStatus"></strong></div>
            <div class="detail-box"><span>Capacity / Price</span><strong id="modalCapacityPrice"></strong></div>
            <div class="detail-box"><span>Trainer</span><strong id="modalTrainer"></strong></div>
            <div class="detail-box"><span>Organiser</span><strong id="modalOrganiser"></strong></div>
        </div>
    </div>
</div>

<script>
const calendarEvents = <?php echo $events_json ?: "[]"; ?>;

function openCreditHistoryModal() {
    const modal = document.getElementById("creditHistoryModal");
    if (modal) {
        modal.style.display = "flex";
        document.body.classList.add("ui-modal-open");
    }
}

function closeCreditHistoryModal() {
    const modal = document.getElementById("creditHistoryModal");
    if (modal) {
        modal.style.display = "none";
        document.body.classList.remove("ui-modal-open");
    }
}

function closeTrainingListModal() {
    document.getElementById("trainingListModal").style.display = "none";
    window.trainhubSyncModalState?.();
}

function closeTrainingDetailModal() {
    document.getElementById("trainingDetailModal").style.display = "none";
    window.trainhubSyncModalState?.();
}

function openTrainingDetails(eventData) {
    const p = eventData.extendedProps || {};

    document.getElementById("modalTitle").innerText = eventData.title || "Training";
    document.getElementById("modalDescription").innerText = p.description || "No description provided.";
    document.getElementById("modalDate").innerText = p.date || "-";
    document.getElementById("modalTime").innerText = (p.startTime || "-") + " - " + (p.endTime || "-");
    document.getElementById("modalLocation").innerText = p.location || "-";
    document.getElementById("modalSessionName").innerText = p.sessionName || "-";
    document.getElementById("modalCategory").innerText = p.courseCategory || "-";
    document.getElementById("modalTrainingType").innerText = p.courseType || "-";
    document.getElementById("modalMode").innerText = p.mode || "-";
    document.getElementById("modalStatus").innerText = p.status || "-";
    document.getElementById("modalCapacityPrice").innerText = (p.capacity || "-") + " participants / RM " + (p.price || "0.00");
    document.getElementById("modalTrainer").innerText = p.trainerNames || "Not assigned";
    document.getElementById("modalOrganiser").innerText = p.organiserName || "-";

    document.getElementById("trainingDetailModal").style.display = "flex";
    window.trainhubSyncModalState?.();
}

function openTrainingListByDate(dateStr) {
    const selectedTrainings = calendarEvents.filter(event => event.dateOnly === dateStr);
    const container = document.getElementById("trainingListContainer");
    const title = document.getElementById("trainingListTitle");

    title.innerText = dateStr;
    container.innerHTML = "";

    if (selectedTrainings.length === 0) {
        const emptyItem = document.createElement("div");
        emptyItem.className = "training-list-item";

        const heading = document.createElement("h4");
        heading.textContent = "No trainings scheduled";

        const details = document.createElement("span");
        details.textContent = "There is no training session on this date.";

        emptyItem.appendChild(heading);
        emptyItem.appendChild(details);
        container.appendChild(emptyItem);
    } else {
        selectedTrainings.forEach((eventData) => {
            const p = eventData.extendedProps || {};

            const item = document.createElement("div");
            item.className = "training-list-item";

            const heading = document.createElement("h4");
            heading.textContent = eventData.title || "Untitled training";

            const details = document.createElement("span");
            details.textContent = `${p.startTime || "-"} - ${p.endTime || "-"} • ${p.location || "No location"}`;

            item.appendChild(heading);
            item.appendChild(details);

            item.addEventListener("click", function () {
                closeTrainingListModal();
                openTrainingDetails(eventData);
            });

            container.appendChild(item);
        });
    }

    document.getElementById("trainingListModal").style.display = "flex";
    window.trainhubSyncModalState?.();
}

function attachAuditAjax() {
    const auditContainer = document.getElementById("auditLogContent");
    const auditForm = document.getElementById("auditFilterForm");

    if (!auditContainer) return;

    auditContainer.querySelectorAll(".audit-ajax-link").forEach(function(link) {
        link.addEventListener("click", function(e) {
            e.preventDefault();

            const url = link.dataset.url;
            const normalUrl = link.getAttribute("href");

            fetchAudit(url, normalUrl);

            const type = link.dataset.type;
            const typeInput = document.getElementById("auditTypeInput");

            if (type && typeInput) {
                typeInput.value = type;
            }
        });
    });

    if (auditForm) {
        auditForm.addEventListener("submit", function(e) {
            e.preventDefault();

            const formData = new FormData(auditForm);
            const type = formData.get("audit_type") || "ALL";
            const month = formData.get("audit_month") || "<?php echo date('Y-m'); ?>";

            const ajaxUrl = `dashboard.php?ajax=audit&audit_type=${encodeURIComponent(type)}&audit_month=${encodeURIComponent(month)}&audit_page=1`;
            const normalUrl = `dashboard.php?audit_type=${encodeURIComponent(type)}&audit_month=${encodeURIComponent(month)}&audit_page=1#audit`;

            fetchAudit(ajaxUrl, normalUrl);
        });
    }
}

function fetchAudit(ajaxUrl, normalUrl) {
    const auditContainer = document.getElementById("auditLogContent");

    auditContainer.classList.add("is-loading");

    fetch(ajaxUrl)
        .then(response => response.text())
        .then(html => {
            auditContainer.innerHTML = html;
            auditContainer.classList.remove("is-loading");
            history.replaceState(null, "", normalUrl);
            attachAuditAjax();
        })
        .catch(() => {
            auditContainer.classList.remove("is-loading");
            window.location.href = normalUrl;
        });
}

function setupCalendar() {
    const calendarEl = document.getElementById("calendar");
    const monthSelect = document.getElementById("calendarMonth");
    const yearSelect = document.getElementById("calendarYear");

    if (!calendarEl || !monthSelect || !yearSelect) return;

    const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];
    const weekdayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    const today = new Date();
    let selectedMonth = today.getMonth();
    let selectedYear = today.getFullYear();

    const eventsByDate = new Map();
    calendarEvents.forEach(eventData => {
        const dateKey = eventData.dateOnly || eventData.extendedProps?.date || "";
        if (!dateKey) return;
        if (!eventsByDate.has(dateKey)) eventsByDate.set(dateKey, []);
        eventsByDate.get(dateKey).push(eventData);
    });

    function localDateKey(year, month, day) {
        return `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
    }

    function populateControls() {
        monthSelect.innerHTML = "";
        yearSelect.innerHTML = "";

        monthNames.forEach((month, index) => {
            const option = document.createElement("option");
            option.value = String(index);
            option.textContent = month;
            option.selected = index === selectedMonth;
            monthSelect.appendChild(option);
        });

        const currentYear = today.getFullYear();
        for (let year = currentYear - 5; year <= currentYear + 5; year++) {
            const option = document.createElement("option");
            option.value = String(year);
            option.textContent = String(year);
            option.selected = year === selectedYear;
            yearSelect.appendChild(option);
        }
    }

    function makeDayCell(year, month, day, isCurrentMonth) {
        const dateKey = localDateKey(year, month, day);
        const cell = document.createElement("button");
        cell.type = "button";
        cell.className = "native-calendar-day" + (isCurrentMonth ? "" : " is-outside");
        cell.dataset.date = dateKey;

        const number = document.createElement("span");
        number.className = "native-calendar-day-number";
        number.textContent = String(day);
        cell.appendChild(number);

        const date = new Date(year, month, day);
        if (
            date.getFullYear() === today.getFullYear() &&
            date.getMonth() === today.getMonth() &&
            date.getDate() === today.getDate()
        ) {
            cell.classList.add("is-today");
        }

        const dayEvents = eventsByDate.get(dateKey) || [];
        if (dayEvents.length > 0) {
            const markers = document.createElement("span");
            markers.className = "native-calendar-markers";

            const dot = document.createElement("span");
            dot.className = "calendar-dot";
            markers.appendChild(dot);

            if (dayEvents.length > 1) {
                const count = document.createElement("span");
                count.className = "native-calendar-count";
                count.textContent = String(dayEvents.length);
                markers.appendChild(count);
            }
            cell.appendChild(markers);
        }

        cell.addEventListener("click", () => openTrainingListByDate(dateKey));
        return cell;
    }

    function renderCalendar() {
        const fragment = document.createDocumentFragment();
        const shell = document.createElement("div");
        shell.className = "native-calendar";

        const weekdays = document.createElement("div");
        weekdays.className = "native-calendar-weekdays";
        weekdayNames.forEach(name => {
            const item = document.createElement("span");
            item.textContent = name;
            weekdays.appendChild(item);
        });
        shell.appendChild(weekdays);

        const grid = document.createElement("div");
        grid.className = "native-calendar-grid";

        const firstDay = new Date(selectedYear, selectedMonth, 1).getDay();
        const daysThisMonth = new Date(selectedYear, selectedMonth + 1, 0).getDate();
        const previousMonthDays = new Date(selectedYear, selectedMonth, 0).getDate();

        for (let index = 0; index < 42; index++) {
            const relativeDay = index - firstDay + 1;
            let cellYear = selectedYear;
            let cellMonth = selectedMonth;
            let cellDay = relativeDay;
            let isCurrentMonth = true;

            if (relativeDay < 1) {
                isCurrentMonth = false;
                cellMonth -= 1;
                if (cellMonth < 0) {
                    cellMonth = 11;
                    cellYear -= 1;
                }
                cellDay = previousMonthDays + relativeDay;
            } else if (relativeDay > daysThisMonth) {
                isCurrentMonth = false;
                cellMonth += 1;
                if (cellMonth > 11) {
                    cellMonth = 0;
                    cellYear += 1;
                }
                cellDay = relativeDay - daysThisMonth;
            }

            grid.appendChild(makeDayCell(cellYear, cellMonth, cellDay, isCurrentMonth));
        }

        shell.appendChild(grid);
        fragment.appendChild(shell);
        calendarEl.replaceChildren(fragment);
    }

    function goToSelectedMonthYear() {
        selectedMonth = Number.parseInt(monthSelect.value, 10);
        selectedYear = Number.parseInt(yearSelect.value, 10);
        if (!Number.isInteger(selectedMonth) || !Number.isInteger(selectedYear)) return;
        renderCalendar();
    }

    populateControls();
    renderCalendar();
    monthSelect.addEventListener("change", goToSelectedMonthYear);
    yearSelect.addEventListener("change", goToSelectedMonthYear);
}

document.addEventListener("DOMContentLoaded", function () {
    attachAuditAjax();
    setupCalendar();

    <?php if (isset($_GET['credit_history_year'])) { ?>
    openCreditHistoryModal();
    <?php } ?>
});

window.onclick = function(event) {
    const listModal = document.getElementById("trainingListModal");
    const detailModal = document.getElementById("trainingDetailModal");
    const creditModal = document.getElementById("creditHistoryModal");

    if (event.target === listModal) closeTrainingListModal();
    if (event.target === detailModal) closeTrainingDetailModal();
    if (event.target === creditModal) closeCreditHistoryModal();
};
</script>

</body>
</html>