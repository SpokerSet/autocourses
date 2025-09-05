<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/autocourses/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_autocourses'));
$PAGE->set_heading(get_string('pluginname', 'local_autocourses'));

echo $OUTPUT->header();
echo html_writer::tag('h3', 'Модуль успешно подключен к Moodle 🚀');
echo html_writer::tag('p', 'Здесь позже появится форма загрузки учебного плана и кнопка запуска генерации курсов.');
echo $OUTPUT->footer();