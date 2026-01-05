<?php
// Настройки подключения к базе Moodle
$host = '127.0.0.1';
$dbname = 'moodle';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
} catch (PDOException $e) {
    die("Ошибка подключения к базе: " . $e->getMessage());
}

// SQL-запрос: название курса, количество файлов, процент заполненности
$sql = "
SELECT c.fullname AS coursename,
       COUNT(DISTINCT f.id) AS filecount,
       COUNT(DISTINCT cm.id) AS totalmodules,
       ROUND(COUNT(DISTINCT CASE WHEN f.id IS NOT NULL THEN cm.id END) / COUNT(DISTINCT cm.id) * 100) AS fillpercent
        FROM mdl_course c
        LEFT JOIN mdl_course_modules cm ON cm.course = c.id
        LEFT JOIN mdl_context ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = 70
        LEFT JOIN mdl_files f ON f.contextid = ctx.id AND f.filename <> '.'
        WHERE c.fullname <> '/ PSU'
        GROUP BY c.id, c.fullname
        ORDER BY fillpercent DESC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Создание CSV-файла
$filename = __DIR__ . '/external_course_fill_report.csv';
$fp = fopen($filename, 'w');

// BOM для Excel
fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Заголовки
fputcsv($fp, ['Название курса', 'Количество файлов', 'Заполненность (%)'], ';');

// Запись строк
foreach ($rows as $row) {
    fputcsv($fp, [
        $row['coursename'],
        $row['filecount'],
        $row['fillpercent'] . '%'
    ], ';');
}

fclose($fp);
echo "Отчёт сохранён: $filename\n";
