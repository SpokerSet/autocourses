<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_autocourses', get_string('pluginname', 'local_autocourses'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_autocourses/sampletext',
        get_string('sampletext', 'local_autocourses'),
        get_string('sampletext_desc', 'local_autocourses'),
        ''
    ));
}
