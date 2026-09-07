<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Connected systems
    |--------------------------------------------------------------------------
    | Keep the URLs blank until the five systems are placed into their final
    | directories. Relative paths (e.g. /izz/public) or full URLs may be used.
    */
    'systems' => [
        'izz' => [
            'name' => 'Educator Training Management System',
            'owner' => 'Izz',
            'description' => 'Educator training management workspace.',
            'url' => env('SYSTEM_IZZ_URL', ''),
        ],
        'nureen' => [
            'name' => 'Training Course Management System',
            'owner' => 'Nureen',
            'description' => 'Training course management workspace.',
            'url' => env('SYSTEM_NUREEN_URL', ''),
        ],
        'tya' => [
            'name' => 'Human Resources Management',
            'owner' => 'Tya',
            'description' => 'Human resources management workspace.',
            'url' => env('SYSTEM_TYA_URL', ''),
        ],
        'aidid_staff' => [
            'name' => 'Training Management System',
            'owner' => 'Aidid',
            'description' => 'Training management staff workspace.',
            'url' => env('SYSTEM_AIDID_STAFF_URL', ''),
        ],
        'aidid_trainer' => [
            'name' => 'Training Management System',
            'owner' => 'Aidid',
            'description' => 'Training management trainer workspace.',
            'url' => env('SYSTEM_AIDID_TRAINER_URL', ''),
        ],
        'syiqin' => [
            'name' => 'Teacher Employment Management System',
            'owner' => 'Syiqin',
            'description' => 'Teacher employment management workspace.',
            'url' => env('SYSTEM_SYIQIN_URL', ''),
        ],
    ],

    'roles' => [
        'teacher' => [
            'label' => 'Teacher',
            'description' => 'Access training and learning functions as a teacher.',
            'systems' => ['izz'],
        ],
        'new_teacher' => [
            'label' => 'New Teacher',
            'description' => 'Access new-teacher learning and observation functions.',
            'systems' => ['izz', 'syiqin'],
        ],
        'outsider' => [
            'label' => 'Public',
            'description' => 'Access public training registration and participation.',
            'systems' => ['izz'],
        ],
        'principal' => [
            'label' => 'Guru Besar',
            'description' => 'Access school leadership and observation functions.',
            'systems' => ['izz', 'syiqin'],
        ],
        'staff_edu' => [
            'label' => 'Staff',
            'description' => 'Access staff systems.',
            'systems' => ['izz', 'nureen', 'aidid_staff'],
        ],
        'hr_administrator' => [
            'label' => 'HR',
            'description' => 'Access Human Resource administration functions.',
            'systems' => ['tya'],
        ],
        'trainer' => [
            'label' => 'Trainer',
            'description' => 'Access the trainer workspace and assigned training.',
            'systems' => ['aidid_trainer'],
        ],
        'observer' => [
            'label' => 'Observer',
            'description' => 'Access assigned new-teacher observation functions.',
            'systems' => ['syiqin'],
        ],
        'external_observer' => [
            'label' => 'External Observer',
            'description' => 'Access external observation functions.',
            'systems' => ['syiqin'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Login credential sources
    |--------------------------------------------------------------------------
    | The resolver checks only tables/columns that actually exist, making this
    | gateway tolerant while the five databases are still being reconciled.
    | Same-email records are treated as the same person for role discovery.
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
            'id' => ['staffID', 'staffid'],
            'name' => ['staffName', 'staffname'],
            'status' => ['status'],
            'active_values' => ['active', 'aktif'],
        ],
        'teacher' => [
            'table' => 'teacher',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['teacherID'],
            'name' => ['teacherName', 'teacher_name'],
            'status' => ['status'],
            'active_values' => ['active', 'aktif'],
        ],
        'guru_new' => [
            'table' => 'guru_new',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['gn_id'],
            'name' => ['gn_name'],
            'status' => ['current_status'],
            'active_values' => ['active', 'complete'],
        ],
        'applicant' => [
            'table' => 'applicant',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['applicant_id'],
            'name' => ['full_name'],
        ],
        'principal' => [
            'table' => 'principal',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['principalID'],
            'name' => ['principalName', 'principal_name'],
            'status' => ['status'],
            'active_values' => ['active', 'aktif'],
        ],
        'hr_administrator' => [
            'table' => 'hr_administrator',
            'email' => ['email'],
            'password' => ['password'],
            'id' => ['hrid', 'hrID'],
            'name' => ['username', 'hrname'],
        ],
        'trainer' => [
            'table' => 'trainer',
            'email' => ['trainerEmail', 'trainer_email', 'email'],
            'password' => ['trainerPassword', 'password'],
            'id' => ['trainerID', 'trainer_id'],
            'name' => ['trainerName', 'trainer_name'],
            'status' => ['status'],
            'active_values' => ['active', 'aktif'],
        ],
    ],
];
