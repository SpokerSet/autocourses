<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/autocourses:manage' => [
        'riskbitmask' => RISK_CONFIG,
        'captype'     => 'write',
        'contextlevel'=> CONTEXT_SYSTEM,
        'archetypes'  => [
            'manager' => CAP_ALLOW
        ]
    ]
];