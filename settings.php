<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_autocourses', get_string('pluginname', 'local_autocourses'));


    $ADMIN->add('localplugins', $settings);
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_autocourses_fullimport',
        get_string('fullimport', 'local_autocourses'),
        new \moodle_url('/local/autocourses/fullimport.php')
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_autocourses_index',
        'Очистка курсов по дисциплине',
        $CFG->wwwroot.'/local/autocourses/index.php'
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_autocourses_dryrun',
        'Dry‑run: структура курсов',
        $CFG->wwwroot.'/local/autocourses/dryrun.php'
    ));
    $logspage = new admin_externalpage(
        'local_autocourses_logs', // Идентификатор (совпадает с admin_externalpage_setup)
        get_string('logs_menu', 'local_autocourses'), // Название пункта в меню
        new moodle_url('/local/autocourses/logs.php'), // URL к нашему файлу
        'moodle/site:config' // Права доступа (только для главных администраторов)
    );

    // Добавляем страницу в раздел "localplugins" (Локальные плагины)
    $ADMIN->add('localplugins', $logspage);
}
