<!DOCTYPE html>
<html>
<head>
    <title>Teacher Management | TrainHub Al Amin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/teacher.css?v=<?php echo file_exists(public_path('assets/css/teacher.css')) ? filemtime(public_path('assets/css/teacher.css')) : time(); ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo file_exists(public_path('assets/css/style.css')) ? filemtime(public_path('assets/css/style.css')) : time(); ?>">
</head>

<body class="trainhub-app page-teacher">

@include('partials.topbar')

<div class="page">

    <div class="dashboard-header">
        <div>
            <h1>Teacher Management</h1>
            <p>View schools by category, open a school to see its guru list and new teachers, or manage all new teacher assignments.</p>
        </div>
    </div>

    <?php if (!empty($errorMessage)) { ?>
        <div class="alert alert-danger"><?php echo e($errorMessage); ?></div>
    <?php } ?>

    <?php if (isset($_GET['assigned'])) { ?>
        <div class="alert alert-success">Observer assignment saved successfully.</div>
    <?php } ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon stat-blue">
                <svg viewBox="0 0 24 24"><path d="M4 20V9L12 4L20 9V20" /><path d="M9 20V13H15V20" /></svg>
            </div>
            <div>
                <span>Total Schools</span>
                <h2><?php echo e($totalSchools); ?></h2>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-green">
                <svg viewBox="0 0 24 24"><path d="M12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12Z" /><path d="M4.5 20C5.3 16.8 8 15 12 15C16 15 18.7 16.8 19.5 20" /></svg>
            </div>
            <div>
                <span>Total Teachers</span>
                <h2><?php echo e($totalTeacherAll); ?></h2>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-purple">
                <svg viewBox="0 0 24 24"><path d="M12 20V10" /><path d="M12 10C8.5 10 6 7.5 6 4C9.5 4 12 6.5 12 10Z" /><path d="M12 12C15.5 12 18 9.5 18 6C14.5 6 12 8.5 12 12Z" /></svg>
            </div>
            <div>
                <span>New Teachers</span>
                <h2><?php echo e($totalGuruNewAll); ?></h2>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-orange">
                <svg viewBox="0 0 24 24"><path d="M20 6L9 17L4 12" /><path d="M5 20H19" /></svg>
            </div>
            <div>
                <span>Active Assignments</span>
                <h2><?php echo e($totalActiveAssignment); ?></h2>
            </div>
        </div>
    </div>

    <div class="teacher-tabs" role="tablist" aria-label="Teacher page tabs">
        <button type="button" class="teacher-tab-button <?php echo $activeTab === 'secondary' ? 'active' : ''; ?>" data-tab="secondary">Secondary School</button>
        <button type="button" class="teacher-tab-button <?php echo $activeTab === 'primary' ? 'active' : ''; ?>" data-tab="primary">Primary School</button>
        <button type="button" class="teacher-tab-button <?php echo $activeTab === 'preschool' ? 'active' : ''; ?>" data-tab="preschool">Preschool</button>
        <button type="button" class="teacher-tab-button <?php echo $activeTab === 'others' ? 'active' : ''; ?>" data-tab="others">Others</button>
        <button type="button" class="teacher-tab-button <?php echo $activeTab === 'new_teacher' ? 'active' : ''; ?>" data-tab="new_teacher">New Teacher</button>
    </div>

    <div class="tab-content-wrap">
        <?php foreach (['secondary', 'primary', 'preschool', 'others'] as $categoryKey) { ?>
            <?php
                $showSelectedSchool = $selectedSchool && $selectedSchoolCategory === $categoryKey;
                $panelTitle = $tabLabels[$categoryKey];
            ?>
            <section class="teacher-tab-panel <?php echo $activeTab === $categoryKey ? 'active' : ''; ?>" data-panel="<?php echo e($categoryKey); ?>">
                <?php if ($showSelectedSchool) { ?>
                    <?php
                        $schoolTeachers = $teachersBySchool[$selectedSchoolID] ?? [];
                        $schoolNewTeachers = $newTeachersBySchool[$selectedSchoolID] ?? [];
                    ?>

                    <div class="selected-school-bar">
                        <div class="selected-school-left">
                            <div class="selected-school-icon"><?php echo e(strtoupper(substr((string)$selectedSchool['schoolName'], 0, 1))); ?></div>
                            <div>
                                <span><?php echo e($panelTitle); ?></span>
                                <h2><?php echo e($selectedSchool['schoolName']); ?></h2>
                                <p><?php echo e($selectedSchool['schoolID']); ?><?php echo !empty($selectedSchool['schoolAddress']) ? ' • ' . e($selectedSchool['schoolAddress']) : ''; ?></p>
                            </div>
                        </div>
                        <a href="teacher.php?tab=<?php echo e($categoryKey); ?>" class="secondary-back-btn">
                            <svg viewBox="0 0 24 24"><path d="M19 12H5" /><path d="M12 19L5 12L12 5" /></svg>
                            Back to School List
                        </a>
                    </div>

                    <div class="school-detail-grid">
                        <div class="panel">
                            <div class="panel-header">
                                <div class="panel-title">
                                    <div class="panel-title-icon">
                                        <svg viewBox="0 0 24 24"><path d="M12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12Z" /><path d="M4.5 20C5.3 16.8 8 15 12 15C16 15 18.7 16.8 19.5 20" /></svg>
                                    </div>
                                    <div>
                                        <h2>Teacher / Guru List</h2>
                                        <p>Existing teachers under this selected school.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="teacher-list detail-list">
                                <?php renderTeacherRows($schoolTeachers); ?>
                            </div>
                        </div>

                        <div class="panel">
                            <div class="panel-header">
                                <div class="panel-title">
                                    <div class="panel-title-icon">
                                        <svg viewBox="0 0 24 24"><path d="M12 20V10" /><path d="M12 10C8.5 10 6 7.5 6 4C9.5 4 12 6.5 12 10Z" /><path d="M12 12C15.5 12 18 9.5 18 6C14.5 6 12 8.5 12 12Z" /></svg>
                                    </div>
                                    <div>
                                        <h2>New Teacher in This School</h2>
                                        <p>New teacher list for this selected school. Assignment is managed in the New Teacher tab.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="new-teacher-list school-new-teacher-list">
                                <?php renderSchoolNewTeacherRows($schoolNewTeachers); ?>
                            </div>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="panel school-panel">
                        <div class="panel-header course-panel-header">
                            <div class="panel-title">
                                <div class="panel-title-icon">
                                    <svg viewBox="0 0 24 24"><path d="M4 20V9L12 4L20 9V20" /><path d="M9 20V13H15V20" /></svg>
                                </div>
                                <div>
                                    <h2><?php echo e($panelTitle); ?></h2>
                                    <p>Choose a school to view its teachers and new teachers.</p>
                                </div>
                            </div>

                            <div class="course-filters school-search-form">
                                <div class="filter-search">
                                    <input type="search" class="school-tab-search" data-target-panel="<?php echo e($categoryKey); ?>" placeholder="Search school name, code or address...">
                                </div>
                                <button type="button" class="filter-btn school-tab-search-btn" data-target-panel="<?php echo e($categoryKey); ?>">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path d="M20 20L16.65 16.65" /></svg>
                                    Filter
                                </button>
                                <button type="button" class="reset-filter school-tab-reset-btn" data-target-panel="<?php echo e($categoryKey); ?>">Reset</button>
                            </div>
                        </div>

                        <div class="school-card-grid category-school-card-grid">
                            <?php if (!empty($schoolsByCategory[$categoryKey])) { ?>
                                <?php foreach ($schoolsByCategory[$categoryKey] as $school) {
                                    $schoolSearchText = implode(' ', [$school['schoolID'] ?? '', $school['schoolName'] ?? '', $school['schoolAddress'] ?? '']);
                                ?>
                                    <a
                                        href="teacher.php?tab=<?php echo e($categoryKey); ?>&schoolID=<?php echo urlencode((string)$school['schoolID']); ?>"
                                        class="school-modern-card category-school-card filterable-school-card"
                                        data-panel="<?php echo e($categoryKey); ?>"
                                        data-search="<?php echo e($schoolSearchText); ?>"
                                    >
                                        <div class="school-card-topline">
                                            <div class="school-avatar"><?php echo e(strtoupper(substr((string)$school['schoolName'], 0, 1))); ?></div>
                                            <span><?php echo e($school['schoolID']); ?></span>
                                        </div>

                                        <h3><?php echo e($school['schoolName']); ?></h3>
                                        <p><?php echo !empty($school['schoolAddress']) ? e($school['schoolAddress']) : 'No address added.'; ?></p>

                                        <div class="school-mini-stats">
                                            <div>
                                                <small>Teachers</small>
                                                <strong><?php echo e($school['teacher_total']); ?></strong>
                                            </div>
                                            <div>
                                                <small>New Teachers</small>
                                                <strong><?php echo e($school['new_teacher_total']); ?></strong>
                                            </div>
                                        </div>

                                        <div class="school-card-action">View Teachers & New Teachers →</div>
                                    </a>
                                <?php } ?>
                                <div class="empty-state list-filter-empty school-search-empty" data-panel="<?php echo e($categoryKey); ?>" hidden>No school matches your search.</div>
                            <?php } else { ?>
                                <div class="empty-state">No school found in this category.</div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </section>
        <?php } ?>

        <section class="teacher-tab-panel <?php echo $activeTab === 'new_teacher' ? 'active' : ''; ?>" data-panel="new_teacher">
            <div class="panel">
                <div class="panel-header course-panel-header">
                    <div class="panel-title">
                        <div class="panel-title-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 20V10" /><path d="M12 10C8.5 10 6 7.5 6 4C9.5 4 12 6.5 12 10Z" /><path d="M12 12C15.5 12 18 9.5 18 6C14.5 6 12 8.5 12 12Z" /></svg>
                        </div>
                        <div>
                            <h2>All New Teacher List</h2>
                            <p>This tab stays outside the school category tabs for easier assignment management.</p>
                        </div>
                    </div>
                </div>

                <div class="list-toolbar" aria-label="New teacher list search and filter">
                    <div class="list-search-box">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20L16.65 16.65" /></svg>
                        <input type="search" id="newTeacherListSearch" placeholder="Search name, ID, school, observer or email..." autocomplete="off">
                    </div>

                    <select id="newTeacherListFilter" class="list-filter-select" aria-label="Filter new teachers">
                        <option value="all">All new teachers</option>
                        <option value="assigned">Assigned</option>
                        <option value="pending">Pending assignment</option>
                        <option value="observer">Has observer</option>
                        <option value="external">Has external observer</option>
                    </select>

                    <button type="button" class="list-filter-btn" id="newTeacherListApply">Filter</button>
                    <button type="button" class="list-reset-btn" id="newTeacherListReset">Reset</button>
                </div>

                <div class="new-teacher-list" id="newTeacherList">
                    <?php renderNewTeacherList($newTeachers, $eligibleTeacherList, 'All', 'new_teacher', ''); ?>
                    <div class="empty-state list-filter-empty" id="newTeacherFilterEmpty" hidden>No new teacher matches the selected search or filter.</div>
                </div>
            </div>
        </section>
    </div>

</div>

<script>
const teacherTabButtons = document.querySelectorAll('.teacher-tab-button');
const teacherTabPanels = document.querySelectorAll('.teacher-tab-panel');

teacherTabButtons.forEach(function(button) {
    button.addEventListener('click', function() {
        const tab = button.dataset.tab;
        window.location.href = 'teacher.php?tab=' + encodeURIComponent(tab);
    });
});

function runSchoolSearch(panelName) {
    const input = document.querySelector('.school-tab-search[data-target-panel="' + panelName + '"]');
    const cards = document.querySelectorAll('.filterable-school-card[data-panel="' + panelName + '"]');
    const emptyState = document.querySelector('.school-search-empty[data-panel="' + panelName + '"]');
    const query = input ? input.value.trim().toLowerCase() : '';
    let visibleCount = 0;

    cards.forEach(function(card) {
        const searchText = (card.dataset.search || '').toLowerCase();
        const show = query === '' || searchText.includes(query);
        card.hidden = !show;
        if (show) visibleCount++;
    });

    if (emptyState) {
        emptyState.hidden = query === '' || visibleCount !== 0;
    }
}

document.querySelectorAll('.school-tab-search-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        runSchoolSearch(button.dataset.targetPanel);
    });
});

document.querySelectorAll('.school-tab-reset-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        const panel = button.dataset.targetPanel;
        const input = document.querySelector('.school-tab-search[data-target-panel="' + panel + '"]');
        if (input) input.value = '';
        runSchoolSearch(panel);
    });
});

document.querySelectorAll('.school-tab-search').forEach(function(input) {
    input.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            runSchoolSearch(input.dataset.targetPanel);
        }
    });
    input.addEventListener('search', function() {
        runSchoolSearch(input.dataset.targetPanel);
    });
});

function setupListFilter(options) {
    const searchInput = document.getElementById(options.searchInputId);
    const filterSelect = document.getElementById(options.filterSelectId);
    const applyButton = document.getElementById(options.applyButtonId);
    const resetButton = document.getElementById(options.resetButtonId);
    const list = document.getElementById(options.listId);
    const emptyState = document.getElementById(options.emptyStateId);

    if (!searchInput || !filterSelect || !applyButton || !resetButton || !list) {
        return;
    }

    const items = Array.from(list.querySelectorAll(options.itemSelector));

    function applyFilter() {
        const query = searchInput.value.trim().toLowerCase();
        const filterValue = filterSelect.value;
        let visibleCount = 0;

        items.forEach(function(item) {
            const searchableText = (item.dataset.search || '').toLowerCase();
            const matchesSearch = query === '' || searchableText.includes(query);
            const matchesFilter = options.matchesFilter(item, filterValue);
            const shouldShow = matchesSearch && matchesFilter;

            item.hidden = !shouldShow;
            if (shouldShow) visibleCount++;
        });

        if (emptyState) {
            emptyState.hidden = visibleCount !== 0;
        }
    }

    function resetFilter() {
        searchInput.value = '';
        filterSelect.value = 'all';
        applyFilter();
        searchInput.focus();
    }

    applyButton.addEventListener('click', applyFilter);
    resetButton.addEventListener('click', resetFilter);
    filterSelect.addEventListener('change', applyFilter);
    searchInput.addEventListener('search', applyFilter);
    searchInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyFilter();
        }
    });
}

setupListFilter({
    searchInputId: 'newTeacherListSearch',
    filterSelectId: 'newTeacherListFilter',
    applyButtonId: 'newTeacherListApply',
    resetButtonId: 'newTeacherListReset',
    listId: 'newTeacherList',
    emptyStateId: 'newTeacherFilterEmpty',
    itemSelector: '.filterable-new-teacher-card',
    matchesFilter: function(item, filterValue) {
        if (filterValue === 'assigned') return item.dataset.assignment === 'assigned';
        if (filterValue === 'pending') return item.dataset.assignment === 'pending';
        if (filterValue === 'observer') return item.dataset.hasObserver === '1';
        if (filterValue === 'external') return item.dataset.hasExternal === '1';
        return true;
    }
});

function openAssignmentModal(targetId) {
    const form = document.getElementById(targetId);
    if (!form) return;
    form.classList.add('show-assignment-form');
    document.body.classList.add('modal-open');
}

function closeAssignmentModal(targetId) {
    const form = document.getElementById(targetId);
    if (!form) return;
    form.classList.remove('show-assignment-form');
    if (!document.querySelector('.assignment-form.show-assignment-form')) {
        document.body.classList.remove('modal-open');
    }
}

document.querySelectorAll('.assign-toggle-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        openAssignmentModal(button.dataset.target);
    });
});

document.querySelectorAll('[data-close-target]').forEach(function(button) {
    button.addEventListener('click', function() {
        closeAssignmentModal(button.dataset.closeTarget);
    });
});

document.querySelectorAll('.assignment-form').forEach(function(form) {
    form.addEventListener('click', function(event) {
        if (event.target === form) {
            closeAssignmentModal(form.id);
        }
    });
});

document.addEventListener('keydown', function(event) {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.assignment-form.show-assignment-form').forEach(function(form) {
        closeAssignmentModal(form.id);
    });
});

document.querySelectorAll('.observer-assignment-form').forEach(function(form) {
    const observerSelect = form.querySelector('.observer-select');
    const externalSelect = form.querySelector('.external-select');
    const observerStartDate = form.querySelector('.observer-start-date');
    const externalStartDate = form.querySelector('.external-start-date');

    if (!observerSelect || !externalSelect || !observerStartDate || !externalStartDate) return;

    function filterSameTeacher() {
        const observerTeacherId = observerSelect.options[observerSelect.selectedIndex]?.dataset.teacherId || '';
        const externalTeacherId = externalSelect.options[externalSelect.selectedIndex]?.dataset.teacherId || '';

        Array.from(externalSelect.options).forEach(function(option) {
            if (!option.dataset.teacherId) return;
            option.hidden = option.dataset.teacherId === observerTeacherId;
        });

        Array.from(observerSelect.options).forEach(function(option) {
            if (!option.dataset.teacherId) return;
            option.hidden = option.dataset.teacherId === externalTeacherId;
        });

        if (externalSelect.options[externalSelect.selectedIndex]?.hidden) externalSelect.value = '';
        if (observerSelect.options[observerSelect.selectedIndex]?.hidden) observerSelect.value = '';
    }

    function syncRequiredDate() {
        if (observerSelect.value) {
            observerStartDate.required = true;
            if (!observerStartDate.value) observerStartDate.value = new Date().toISOString().slice(0, 10);
        } else {
            observerStartDate.required = false;
        }

        if (externalSelect.value) {
            externalStartDate.required = true;
            if (!externalStartDate.value) externalStartDate.value = new Date().toISOString().slice(0, 10);
        } else {
            externalStartDate.required = false;
        }
    }

    observerSelect.addEventListener('change', function() {
        filterSameTeacher();
        syncRequiredDate();
    });

    externalSelect.addEventListener('change', function() {
        filterSameTeacher();
        syncRequiredDate();
    });

    filterSameTeacher();
    syncRequiredDate();
});

</script>

</body>
</html>
