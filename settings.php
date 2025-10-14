<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_autocourses', get_string('pluginname', 'local_autocourses'));

    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_autocourses/api_specialties_url',
        get_string('api_specialties_url', 'local_autocourses'),
        get_string('api_specialties_url_desc', 'local_autocourses'),
        'http://localhost:32123/education-plans/moodle-specialities',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_autocourses/api_baseurl',
        get_string('api_baseurl', 'local_autocourses'),
        get_string('api_baseurl_desc', 'local_autocourses'),
        'http://localhost:32123/education-plans/moodle-diciplians',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_autocourses/default_yearprefix',
        get_string('default_yearprefix', 'local_autocourses'),
        get_string('default_yearprefix_desc', 'local_autocourses'),
        '25',
        PARAM_ALPHANUM
    ));
}
