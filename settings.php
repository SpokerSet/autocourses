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
}
