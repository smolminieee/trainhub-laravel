<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Unified login handoff
    |--------------------------------------------------------------------------
    | Non-TrainHub systems consume a short-lived, one-time token from the
    | canonical shared `cache` table. This avoids trying to share Laravel file
    | sessions between independent applications.
    */
    'handoff_ttl' => (int) env('UNIFIED_HANDOFF_TTL', 90),
    'handoff_path' => env('UNIFIED_HANDOFF_PATH', '/unified-login/handoff'),

    /*
    |--------------------------------------------------------------------------
    | Connected systems
    |--------------------------------------------------------------------------
    | The displayed names intentionally follow the FYP system names supplied
    | by the project group. Aidid has two target keys because Staff EDU and
    | Trainer land in different local dashboards inside the same application.
    */
    'systems' => [
        'nureen' => [
            'owner' => 'Nureen',
            'name' => 'Training Course Administration System (TrainHub Al Amin)',
            'description' => 'Training course administration workspace.',
            'url' => env('SYSTEM_NUREEN_URL', ''),
        ],
        'aidid_staff' => [
            'owner' => 'Aidid',
            'name' => 'Training Management System (Trainer Perspective)',
            'description' => 'Staff EDU administration workspace in the Training Management System.',
            'url' => env('SYSTEM_AIDID_STAFF_URL') ?: env('SYSTEM_AIDID_URL', ''),
        ],
        'aidid_trainer' => [
            'owner' => 'Aidid',
            'name' => 'Training Management System (Trainer Perspective)',
            'description' => 'Trainer workspace in the Training Management System.',
            'url' => env('SYSTEM_AIDID_TRAINER_URL') ?: env('SYSTEM_AIDID_URL', ''),
        ],
        'syiqin' => [
            'owner' => 'Asyiqin',
            'name' => 'Teacher Employment Management System',
            'description' => 'Teacher employment and observation workspace.',
            'url' => env('SYSTEM_SYIQIN_URL', ''),
        ],
        'tya' => [
            'owner' => 'Athirah',
            'name' => 'Human Resource Management',
            'description' => 'Human resource management workspace.',
            'url' => env('SYSTEM_TYA_URL', ''),
        ],
        'izz' => [
            'owner' => 'Izz',
            'name' => 'Educator training Management system (EduTrain system)',
            'description' => 'Educator training management workspace.',
            'url' => env('SYSTEM_IZZ_URL', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role-to-system access
    |--------------------------------------------------------------------------
    | Role labels are intentionally short. A user only sees roles actually
    | discovered for their email. After choosing a role, only the systems
    | listed for that role are displayed and accepted server-side.
    |
    | Ownership supplied by the group:
    | - Nureen: Staff EDU
    | - Asyiqin: Guru Besar, New Teacher, Observer, External Observer, HR
    | - Izz: Teacher, New Teacher, Outsider, Guru Besar, Staff EDU
    | - Athirah: HR
    | - Aidid: Staff EDU, Trainer
    */
    'roles' => [
        'teacher' => [
            'label' => 'Teacher',
            'systems' => ['izz'],
        ],
        'new_teacher' => [
            'label' => 'New Teacher',
            'systems' => ['syiqin', 'izz'],
        ],
        'outsider' => [
            'label' => 'Outsider',
            'systems' => ['izz'],
        ],
        'principal' => [
            'label' => 'Guru Besar',
            'systems' => ['syiqin', 'izz'],
        ],
        'staff_edu' => [
            'label' => 'Staff EDU',
            'systems' => ['nureen', 'aidid_staff', 'izz'],
        ],
        'hr_administrator' => [
            'label' => 'HR',
            'systems' => ['syiqin', 'tya'],
        ],
        'trainer' => [
            'label' => 'Trainer',
            'systems' => ['aidid_trainer'],
        ],
        'observer' => [
            'label' => 'Observer',
            'systems' => ['syiqin'],
        ],
        'external_observer' => [
            'label' => 'External Observer',
            'systems' => ['syiqin'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Canonical credential sources
    |--------------------------------------------------------------------------
    | Same-email records are treated as one person for multi-role discovery
    | after one valid credential check.
    */
    'credential_sources' => [
        'users' => [
            'table' => 'users',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['id'],
            'name' => ['name'],
        ],
        'staff_edu' => [
            'table' => 'staff_edu',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['staffID'],
            'name' => ['staffName'],
            'status' => ['status'],
            'active_values' => ['active', 'aktif'],
        ],
        'teacher' => [
            'table' => 'teacher',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['teacherID'],
            'name' => ['teacherName'],
        ],
        'guru_new' => [
            'table' => 'guru_new',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['gn_id'],
            'name' => ['gn_name'],
            'status' => ['current_status'],
            'active_values' => ['active'],
        ],
        'principal' => [
            'table' => 'principal',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['principalID'],
            'name' => ['principalName'],
            'status' => ['status'],
            'active_values' => ['active', 'aktif'],
        ],
        'hr_administrator' => [
            'table' => 'hr_administrator',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['hrid'],
            'name' => ['username'],
        ],
        'trainer' => [
            'table' => 'trainer',
            'email' => ['trainerEmail'],
            'password' => ['trainerPassword'],
            'id' => ['trainerID'],
            'name' => ['trainerName'],
            'status' => ['status'],
            'active_values' => ['active', 'aktif'],
        ],
    ],
];
