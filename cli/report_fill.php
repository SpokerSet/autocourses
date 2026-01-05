<?php
namespace local_autocourses\task;

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/clilib.php');

global $DB;

// Заголовок с датой
$now = date('Y-m-d H:i:s');
echo "Отчёт о заполненности курсов — $now\n\n";

// Заголовки таблицы
printf("%-6s %-50s %-6s %-7s %-6s %-9s %-6s %-14s\n",
    'ID', 'Название', 'Всего', 'Assign', 'Quiz', 'Resource', 'Other', 'Заполненность');
echo str_repeat('-', 110) . "\n";

// Получаем список курсов
$courses = $DB->get_records('course', ['visible' => 1]);

foreach ($courses as $course) {
    $modules = $DB->get_records('course_modules', ['course' => $course->id]);
    $total = count($modules);

    $assign = 0; $quiz = 0; $resource = 0; $other = 0;
    foreach ($modules as $m) {
        $modname = $DB->get_field('modules', 'name', ['id' => $m->module]);
        switch ($modname) {
            case 'assign': $assign++; break;
            case 'quiz': $quiz++; break;
            case 'resource': $resource++; break;
            default: $other++; break;
        }
    }

    $percent = $total > 0 ? round(($assign + $quiz + $resource) / $total * 100) : 0;

    // Вывод строки
    printf("%-6d %-50s %-6d %-7d %-6d %-9d %-6d %-13s\n",
        $course->id,
        mb_substr($course->fullname, 0, 50),
        $total, $assign, $quiz, $resource, $other,
        $percent . '%');
}