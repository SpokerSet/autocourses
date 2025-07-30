<?php
namespace local_coursebuilder\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class UploadPlanForm extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'planfile', get_string('uploadplan', 'local_coursebuilder'));
        $mform->setType('planfile', PARAM_FILE);
        $this->add_action_buttons(true, get_string('uploadplan', 'local_coursebuilder'));
    }
}