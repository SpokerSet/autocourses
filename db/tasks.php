<?php
$tasks = [
    [
        'classname' => 'local_autocourses\task\weekly_report',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*' 
    ],
    [
        'classname' => 'local_autocourses\task\import_task',
        'blocking' => 0, 
        'minute' => '0',
        'hour' => '2',  
        'day' => '*',   
        'dayofweek' => '*', 
        'month' => '*', 
        'disabled' => 0
    ],
];