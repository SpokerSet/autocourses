<?php
namespace local_autocourses;
require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/autocourses/lib.php');


require_login();
admin_externalpage_setup('local_autocourses_dryrun');

$PAGE->set_title('Dry‑run: проверка структуры курсов');
$PAGE->set_heading('Dry‑run: проверка структуры курсов');

echo $OUTPUT->header();
echo $OUTPUT->heading('Статистика дисциплин по семестрам');
$year    = optional_param('year', '2025-2026', PARAM_TEXT);

// Загружаем дерево планов (замени на свою функцию)
$raw   = \local_autocourses\externaldbprovider::get_specialities($year);
$plans = \local_autocourses\planprovider::normalize($raw);
$tree = \local_autocourses\planprovider::attach_disciplines($plans);


$totalGroups      = 0;
$totalSemesters   = 0;
$totalDisciplines = 0;
$problemGroups    = [];

foreach ($tree as $faculty => $codespecs) {
    foreach ($codespecs as $codespec => $groups) {
        foreach ($groups as $group => $info) {
            $totalGroups++;
            echo "<h3>Группа: {$group}</h3>";

            if (empty($info['semesters'])) {
                echo "<div style='color:red'>Нет семестров</div>";
                $problemGroups[$group] = "Нет семестров";
                continue;
            }

            foreach ($info['semesters'] as $sem => $disciplines) {
                $count = is_array($disciplines) ? count($disciplines) : 0;
                echo "<div>Семестр {$sem}: дисциплин {$count}</div>";

                if ($count === 0) {
                    $problemGroups[$group][] = "Семестр {$sem} пустой";
                }

                $totalDisciplines += $count;
                $totalSemesters++;
            }
        }
    }
}

echo "<hr>";
echo "<p><strong>Всего групп: {$totalGroups}</strong></p>";
echo "<p><strong>Всего семестров: {$totalSemesters}</strong></p>";
echo "<p><strong>Всего дисциплин: {$totalDisciplines}</strong></p>";

if (!empty($problemGroups)) {
    echo "<h2 style='color:red'>Проблемные группы</h2>";
    foreach ($problemGroups as $group => $issues) {
        echo "<div><strong>{$group}</strong>: ";
        if (is_array($issues)) {
            echo implode(', ', $issues);
        } else {
            echo $issues;
        }
        echo "</div>";
    }
}

echo $OUTPUT->footer();
