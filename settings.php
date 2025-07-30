<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_coursebuilder', get_string('pluginname', 'local_coursebuilder'));

    $settings->add(new admin_setting_configtext(
        'local_coursebuilder/storagepath',
        get_string('path', 'local_coursebuilder'),
        '',
        '/var/moodledata/courseplans'
    ));

    $ADMIN->add('localplugins', $settings);
}