<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(__DIR__.'/classes/form/UploadPlanForm.php');

admin_externalpage_setup('local_coursebuilder');

$mform = new \local_coursebuilder\form\UploadPlanForm();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/settings.php', ['section' => 'local_coursebuilder']));
}

if ($data = $mform->get_data()) {
    $fs = get_file_storage();
    $context = context_system::instance();
    $fileinfo = [
        'contextid'    => $context->id,
        'component'    => 'local_coursebuilder',
        'filearea'     => 'plans',
        'itemid'       => 0,
        'filepath'     => '/',
        'filename'     => $data->planfile['name']
    ];
    $fs->create_file_from_pathname($fileinfo, $data->planfile['tmp_name']);
    redirect(new moodle_url('/admin/index.php'), get_string('uploadplan', 'local_coursebuilder').' — OK');
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();