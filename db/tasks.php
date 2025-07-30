<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname'  => 'local_coursebuilder\task\process_plan_task',
        'blocking'   => 0,
        'minute'     => '*/10',
        'hour'       => '*',
        'day'        => '*',
        'month'      => '*',
        'dayofweek'  => '*'
    ]
];