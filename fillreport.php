<?php
namespace local_autocourses;
require('../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$PAGE->set_url(new \moodle_url('/local/autocourses/report.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title('Отчёт о заполненности курсов');
$PAGE->set_heading('Отчёт о заполненности курсов');

echo $OUTPUT->header();

// Параметры фильтра и пагинации
$minfill = optional_param('minfill', 0, PARAM_INT);
$page    = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 50;

// Форма фильтра (минимальная заполненность)
echo '<form method="get" style="margin-bottom:10px">';
echo \html_writer::label('Минимальная заполненность: ', 'minfill');
echo '<input type="number" name="minfill" value="' . $minfill . '" min="0" max="100" step="1" style="margin-right:8px">';
echo '<input type="hidden" name="page" value="0">';
echo '<input type="submit" value="Фильтровать" class="btn btn-secondary">';
echo '</form>';

// Заголовок с временем
$now = date('Y-m-d H:i:s');
echo \html_writer::tag('h3', "Отчёт о заполненности курсов — $now");

// Вспомогательные функции
function get_category_path($categoryid): string {
    global $DB;
    $names = [];
    while ($categoryid) {
        $cat = $DB->get_record('course_categories', ['id' => $categoryid], 'id, name, parent');
        if (!$cat) break;
        array_unshift($names, $cat->name);
        $categoryid = $cat->parent;
    }

    if (count($names) > 1) {
        array_shift($names);
    }

    return implode(' / ', $names);
}

// Подготовка таблицы
$table = new \html_table();
$table->head = ['Название курса', 'Всего модулей', 'Файлов всего', 'Заполненных модулей', 'Заполненность'];
$table->align = ['left', 'center', 'center', 'center', 'center'];
$table->width = '100%';

// Получаем количество всех видимых курсов (для пагинации)
$totalcourses = $DB->count_records('course', ['visible' => 1]);

// Получаем курсы только для текущей страницы
$courses = $DB->get_records('course', ['visible' => 1], 'id ASC', 'id, category, fullname', $page * $perpage, $perpage);

foreach ($courses as $course) {
    // Получаем модули курса (только id и module/instance, чтобы не тянуть лишнее)
    $modules = $DB->get_records('course_modules', ['course' => $course->id], '', 'id, module, instance');
    $totalmodules = count($modules);

    if ($totalmodules === 0) {
        // Курс пустой — сразу добавляем строку
        $fullname = get_category_path($course->category) . ' / ' . format_string($course->fullname);
        $rowclass = 'table-danger';
        $rowdata  = [$fullname, 0, 0, 0, '0%'];
        $table->data[] = new \html_table_row($rowdata, $rowclass);
        continue;
    }

    // Предварительно собираем contextid для всех модулей курса (в одном проходе)
    $contextids = [];
    $cmidbycontext = [];
    foreach ($modules as $m) {
        $context = \context_module::instance($m->id);
        $contextids[] = $context->id;
        $cmidbycontext[$context->id] = $m->id;
    }

    // Считаем файлы по всем contextid одним SQL-запросом
    // filename <> '.' — исключаем каталоги; filesize > 0 — реальные файлы
    list($inctxsql, $params) = $DB->get_in_or_equal($contextids, \SQL_PARAMS_QM);
    $filesql = "
        SELECT f.contextid, COUNT(*) AS cnt
          FROM {files} f
         WHERE f.contextid $inctxsql
           AND f.filename <> '.'
           AND f.filesize > 0
         GROUP BY f.contextid
    ";
    $filecounts = $DB->get_records_sql($filesql, $params);

    // Аггрегируем: сколько файлов в целом и сколько модулей с файлами
    $totalfiles = 0;
    $filledmodules = 0;

    foreach ($filecounts as $fc) {
        $totalfiles += (int)$fc->cnt;
        if ($fc->cnt > 0) {
            $filledmodules++;
        }
    }

    // Заполненность как доля модулей с файлами
    $percent = $totalmodules > 0 ? (int)round($filledmodules / $totalmodules * 100) : 0;

    // Применяем пороговый фильтр (минимальная заполненность)
    if ($percent < $minfill) {
        continue;
    }

    // Полное имя курса
    $fullname = get_category_path($course->category) . ' / ' . format_string($course->fullname);

    // Цветовая подсветка
    $rowclass = ($percent >= 70) ? 'table-success' : (($percent >= 40) ? 'table-warning' : 'table-danger');

    // Добавляем строку сразу (не копим в памяти весь массив)
    $rowdata = [$fullname, $totalmodules, $totalfiles, $filledmodules, $percent . '%'];
    $table->data[] = new \html_table_row($rowdata, $rowclass);
}

// Рендер таблицы
echo \html_writer::table($table);

// Пагинация (Prev / Next)
$baseurl = new \moodle_url('/local/autocourses/report.php', ['minfill' => $minfill]);
$prevurl = clone $baseurl; $prevurl->param('page', max(0, $page - 1));
$nexturl = clone $baseurl; $nexturl->param('page', ($page + 1));

// Кнопки навигации
echo '<div class="paging" style="margin-top:10px">';
if ($page > 0) {
    echo \html_writer::link($prevurl, '← Предыдущая страница', ['class' => 'btn btn-secondary', 'style' => 'margin-right:8px']);
}
$maxpage = (int)floor(($totalcourses - 1) / $perpage);
if ($page < $maxpage) {
    echo \html_writer::link($nexturl, 'Следующая страница →', ['class' => 'btn btn-secondary']);
}
echo '</div>';

echo $OUTPUT->footer();
