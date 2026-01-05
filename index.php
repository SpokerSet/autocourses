<?php

namespace local_autocourses;
require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/course/lib.php');

require_login();
admin_externalpage_setup('local_autocourses_index');

$PAGE->set_title('Управление курсами');
$PAGE->set_heading('Управление курсами');

echo $OUTPUT->header();
echo $OUTPUT->heading('Операции с курсами');

// === Удаление по дисциплине ===
if (!empty($_POST['purge']) && !empty($_POST['pattern'])) {
    $pattern = trim($_POST['pattern']);
    if (!empty($_POST['strict'])) {
        $sql = "SELECT id, fullname FROM {course} WHERE fullname = ?";
        $courses = $DB->get_records_sql($sql, [$pattern]);
    } else {
        $sql = "SELECT id, fullname FROM {course} WHERE fullname LIKE ?";
        $courses = $DB->get_records_sql($sql, ["%{$DB->sql_like_escape($pattern)}%"]);
    }

    echo "<div style='border:1px solid #ccc;padding:10px;margin-top:10px;'>";
    echo "<strong>Найдено ".count($courses)." курсов по запросу '{$pattern}':</strong><br>";

    if (!empty($_POST['dryrun'])) {
        foreach ($courses as $c) {
            echo "<div style='color:blue'>Будет удалён: ".s($c->fullname)." (id {$c->id})</div>";
        }
    } else {
        foreach ($courses as $c) {
            delete_course($c->id, false);
            echo "<div style='color:red'>Удалён: ".s($c->fullname)." (id {$c->id})</div>";
        }
    }
    echo "</div>";
}

// === Удаление дублей по fullname (глобально) ===
if (!empty($_POST['dedupe_global'])) {
    $sql = "SELECT id, fullname FROM {course} ORDER BY fullname, id";
    $courses = $DB->get_records_sql($sql);

    $seen = [];
    $deleted = 0;

    echo "<div style='border:1px solid #ccc;padding:10px;margin-top:10px;'>";
    foreach ($courses as $c) {
        $key = trim($c->fullname);
        if (isset($seen[$key])) {
            if (!empty($_POST['dryrun'])) {
                echo "<div style='color:blue'>Будет удалён дубликат: ".s($c->fullname)." (id {$c->id})</div>";
            } else {
                delete_course($c->id, false);
                echo "<div style='color:red'>Удалён дубликат: ".s($c->fullname)." (id {$c->id})</div>";
                $deleted++;
            }
        } else {
            $seen[$key] = $c->id;
        }
    }
    echo "<p><strong>Удалено дублей по дереву: {$deleted}</strong></p>";
    echo "</div>";
}

// === Удаление дублей внутри семестров ===
if (!empty($_POST['dedupe_semester'])) {
    $sql = "SELECT c.id, c.fullname, cat.name AS categoryname
            FROM {course} c
            JOIN {course_categories} cat ON cat.id = c.category
            WHERE cat.name LIKE 'Семестр %'
            ORDER BY cat.id, c.fullname, c.id";
    $courses = $DB->get_records_sql($sql);

    $seen = [];
    $deleted = 0;

    echo "<div style='border:1px solid #ccc;padding:10px;margin-top:10px;'>";
    foreach ($courses as $c) {
        $key = $c->categoryname . '|' . trim($c->fullname);
        if (isset($seen[$key])) {
            if (!empty($_POST['dryrun'])) {
                echo "<div style='color:blue'>Будет удалён дубликат: ".s($c->fullname)." (id {$c->id}) в {$c->categoryname}</div>";
            } else {
                delete_course($c->id, false);
                echo "<div style='color:red'>Удалён дубликат: ".s($c->fullname)." (id {$c->id}) в {$c->categoryname}</div>";
                $deleted++;
            }
        } else {
            $seen[$key] = $c->id;
        }
    }
    echo "<p><strong>Удалено дублей внутри семестров: {$deleted}</strong></p>";
    echo "</div>";
}

// === Формы управления ===
echo "<form method='post' style='margin-top:20px;'>";
echo "<h3>Удаление по дисциплине</h3>";
echo "<label>Название дисциплины: <input type='text' name='pattern' style='width:300px;'></label><br><br>";
echo "<label><input type='checkbox' name='strict' value='1'> Строгое совпадение</label><br>";
echo "<label><input type='checkbox' name='dryrun' value='1'> Только показать (dry‑run)</label><br><br>";
echo "<input type='submit' name='purge' value='Выполнить'>";
echo "</form>";

echo "<form method='post' style='margin-top:20px;'>";
echo "<h3>Удаление дублей по fullname (глобально)</h3>";
echo "<label><input type='checkbox' name='dryrun' value='1'> Только показать (dry‑run)</label><br><br>";
echo "<input type='submit' name='dedupe_global' value='Удалить глобальные дубликаты'>";
echo "</form>";

echo "<form method='post' style='margin-top:20px;'>";
echo "<h3>Удаление дублей внутри семестров</h3>";
echo "<label><input type='checkbox' name='dryrun' value='1'> Только показать (dry‑run)</label><br><br>";
echo "<input type='submit' name='dedupe_semester' value='Удалить дубликаты в семестрах'>";
echo "</form>";

echo "<form method='get' action='{$CFG->wwwroot}/local/autocourses/fullimport.php' style='margin-top:20px;'>";
echo "<h3>Автоимпорт курсов</h3>";
echo "<label>Учебный год: <input type='text' name='year' value='2025-2026' style='width:200px;'></label><br>";
echo "<label>Offset: <input type='number' name='offset' value='0' style='width:100px;'></label><br>";
echo "<label>Limit: <input type='number' name='limit' value='100' style='width:100px;'></label><br>";
echo "<label>Refresh (сек): <input type='number' name='refresh' value='5' style='width:100px;'></label><br><br>";
echo "<input type='submit' value='Запустить автоимпорт'>";
echo "</form>";

echo "<form method='get' action='{$CFG->wwwroot}/local/autocourses/dryrun.php' style='margin-top:20px;'>";
echo "<h3>Dry‑run: структура курсов</h3>";
echo "<label>Учебный год: <input type='text' name='year' value='2025-2026' style='width:200px;'></label><br><br>";
echo "<input type='submit' value='Показать статистику'>";
echo "</form>";


// === Отчёт по заполненности курсов ===
echo $OUTPUT->heading('Статистика заполненности курсов');

if ($DB->get_manager()->table_exists('local_autocourses_fillstats')) {
    $courses = $DB->get_records('local_autocourses_fillstats', null, 'percent DESC');

    $table = new \html_table();
    $table->head = ['ID курса', 'Название', 'Элементов', 'Заполненность'];

    foreach ($courses as $c) {
        $course = $DB->get_record('course', ['id' => $c->courseid], 'id, fullname');

        // Цвет прогресс-бара
        $color = ($c->percent < 30) ? 'red' : (($c->percent < 70) ? 'orange' : 'green');
        $bar = \html_writer::div(
            \html_writer::div('', 'bar-fill', [
                'style' => "width:{$c->percent}%;background-color:{$color};height:100%;"
            ]),
            'bar-container',
            ['style' => 'width:150px;border:1px solid #ccc;height:15px;display:inline-block;margin-right:5px;']
        );

        $row = [
            $course->id,
            format_string($course->fullname),
            $c->total,
            $bar . " {$c->percent}%"
        ];
        $table->data[] = $row;
    }

    echo \html_writer::table($table);

    // Сводная статистика
    $low = $DB->count_records_select('local_autocourses_fillstats', 'percent < 30');
    $mid = $DB->count_records_select('local_autocourses_fillstats', 'percent >= 30 AND percent < 70');
    $high = $DB->count_records_select('local_autocourses_fillstats', 'percent >= 70');

    echo \html_writer::tag('h3', 'Распределение курсов по заполненности');
    echo \html_writer::tag('p', "Менее 30%: {$low} курсов");
    echo \html_writer::tag('p', "30–70%: {$mid} курсов");
    echo \html_writer::tag('p', "Более 70%: {$high} курсов");

} else {
    echo $OUTPUT->notification('Таблица статистики не найдена. Запустите cron для пересчёта.', 'notifyproblem');
}



echo $OUTPUT->footer();
