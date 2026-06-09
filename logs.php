<?php
// 1. Включаем отображение ошибок на самый максимум
// @ini_set('display_errors', '1');
// @ini_set('display_startup_errors', '1');
// @error_reporting(E_ALL);

// 2. Абсолютно надежный поиск и подключение config.php без использования DIR
$dir = dirname(__FILE__);
while ($dir !== '/' && $dir !== '\\' && !file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
}

if (file_exists($dir . '/config.php')) {
    require_once($dir . '/config.php');
} else {
    die("Критическая ошибка: Не удалось динамически найти файл config.php в корне СДО Moodle.");
}

// 3. Инициализация глобальных объектов Moodle
global $DB, $PAGE, $OUTPUT, $USER;

// 4. Жесткая проверка авторизации
try {
    require_login();
    if (!is_siteadmin()) {
        die('Доступ к журналу логов разрешен только администраторам сайта.');
    }
} catch (\Throwable $e) {
    die("Ошибка сессии Moodle: " . $e->getMessage());
}

// 5. Базовые параметры пагинации
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

// 6. Конфигурация контекста отображения страницы
$PAGE->set_url(new \moodle_url('/local/autocourses/logs.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title('Журнал аудита Autocourses');
$PAGE->set_heading('Журнал аудита Autocourses');

echo $OUTPUT->header();
echo $OUTPUT->heading('Журнал аудита событий системы');

// 7. Построение объектной таблицы
$table = new \html_table();
$table->head = ['Дата', 'Уровень', 'Действие', 'Сообщение', 'Инициатор'];
$table->attributes['class'] = 'generaltable logtable m-y-1';

try {
    // Проверяем физическое существование таблицы в базе данных
    if ($DB->get_manager()->table_exists('local_autocourses_logs')) {
        $totalcount = $DB->count_records('local_autocourses_logs');
        $logs = $DB->get_records('local_autocourses_logs', null, 'id DESC', '*', $page * $perpage, $perpage);

        if (!empty($logs)) {
            foreach ($logs as $log) {
                // Определение имени инициатора
                $username = 'Система (Cron)';
                if (!empty($log->userid)) {
                    $user = $DB->get_record('user', ['id' => $log->userid], 'firstname, lastname');
                    $username = $user ? fullname($user) : 'Удаленный пользователь';
                }
                
                // Цветовая стилизация уровней логов (Bootstrap Badges)
                $badge = 'badge-info';
                if ($log->loglevel === 'ERROR') { $badge = 'badge-danger'; }
                if ($log->loglevel === 'WARNING') { $badge = 'badge-warning'; }
                
                $table->data[] = [
                    userdate($log->timecreated, '%d.%m.%Y %H:%M:%S'),
                    \html_writer::span($log->loglevel, 'badge ' . $badge),
                    s($log->action),
                    s($log->message),
                    $username
                ];
            }
            echo \html_writer::table($table);
            echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);
        } else {
            echo $OUTPUT->notification('Таблица логов пуста. События еще не фиксировались.', 'info');
        }
    } else {
        echo $OUTPUT->notification('Таблица local_autocourses_logs не найдена в текущей БД. Перейдите в раздел "Администрирование", чтобы Moodle создал её структуру автоматически.', 'warning');
    }
} catch (\Throwable $e) {
    echo $OUTPUT->notification('Ошибка выполнения SQL-запроса: ' . $e->getMessage(), 'error');
}

echo $OUTPUT->footer();