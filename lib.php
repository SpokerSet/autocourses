<?php
defined('MOODLE_INTERNAL') || die();

function local_coursebuilder_extend_navigation(global_navigation $nav) {
    if (has_capability('local/coursebuilder:manage', context_system::instance())) {
        $nav->add(
            get_string('pluginname', 'local_coursebuilder'),
            new moodle_url('/local/coursebuilder/index.php')
        );
    }
}