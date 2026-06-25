<?php
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', \context_system::instance());

$sql = "SELECT c.fullname AS coursename,
               COUNT(DISTINCT f.id) AS filecount,
               ROUND(COUNT(DISTINCT CASE WHEN f.id IS NOT NULL THEN cm.id END) / COUNT(DISTINCT cm.id) * 100) AS fillpercent
          FROM {course} c
     LEFT JOIN {course_modules} cm ON cm.course = c.id
     LEFT JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = 70
     LEFT JOIN {files} f ON f.contextid = ctx.id AND f.filename <> '.'
         WHERE c.fullname <> '/ PSU'
      GROUP BY c.id, c.fullname
      ORDER BY fillpercent DESC";

$rows = $DB->get_records_sql($sql);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="course_fill_report.csv"');

$output = fopen('php://output', 'w');
fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM для Excel
fputcsv($output, ['Название курса', 'Количество файлов', 'Заполненность (%)'], ';');

foreach ($rows as $row) {
    fputcsv($output, [$row->coursename, $row->filecount, $row->fillpercent . '%'], ';');
}
fclose($output);
exit;