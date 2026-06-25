<?php
// local/autocourses/logs.php

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// 1. Проверка прав (администратор)
require_login();
admin_externalpage_setup('local_autocourses_logs'); // ← берёт настройки из settings.php

// 2. Заголовки
$PAGE->set_title('Логи автокурсов');
$PAGE->set_heading('Логи автокурсов');

echo $OUTPUT->header();

// 3. Вывод логов
try {
    $logs = $DB->get_records('local_autocourses_logs', null, 'timecreated DESC', '*', 0, 100);
} catch (dml_exception $e) {
    echo \html_writer::tag('div', 'Ошибка чтения логов: ' . $e->getMessage(), ['class' => 'alert alert-danger']);
    $logs = [];
}

if ($logs) {
    $table = new \html_table();
    $table->head = ['Время', 'Пользователь', 'Действие', 'Уровень', 'Сообщение', 'Контекст'];
    $table->data = [];

    foreach ($logs as $log) {
        $user = \core_user::get_user($log->userid);
        $username = $user ? fullname($user) : 'Система';

        // Обработка контекста (может быть JSON)
        $context = $log->contextdata ? htmlspecialchars($log->contextdata, ENT_QUOTES, 'UTF-8') : '';

        $table->data[] = [
            userdate($log->timecreated, get_string('strftimedatetime', 'core_langconfig')),
            $username,
            s($log->action),
            $log->loglevel,
            s($log->message),
            $context
        ];
    }

    echo \html_writer::table($table);
    echo \html_writer::tag('p', "Показаны последние $limit записей.");
} else {
    echo \html_writer::tag('p', 'Логи отсутствуют.');
}

// 4. Кнопка "Назад" (необязательно)
echo \html_writer::link(
    new \moodle_url('/local/autocourses/index.php'),
    '← На главную',
    ['class' => 'btn btn-secondary']
);

echo $OUTPUT->footer();