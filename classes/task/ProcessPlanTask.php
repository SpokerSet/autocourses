<?php
namespace local_coursebuilder\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_coursebuilder\parser\PlanParser;
use local_coursebuilder\creator\CourseCreator;

class process_plan_task extends scheduled_task {
    public function get_name(): string {
        return get_string('processplans', 'local_coursebuilder');
    }

    public function execute() {
        global $DB, $CFG;
        $files = get_file_storage()->get_area_files(
            context_system::instance()->id,
            'local_coursebuilder',
            'plans',
            false,
            'timemodified',
            false
        );
        foreach ($files as $file) {
            $filepath = $CFG->dataroot . '/filedir/' . $file->get_pathnamehash();
            $plan     = PlanParser::parse($filepath);
            CourseCreator::build_from_plan($plan);
            
            $DB->insert_record('local_coursebuilder_log', [
                'planid' => $file->get_itemid(),
                'message'=> 'Processed '.$file->get_filename(),
                'time'   => time()
            ]);
            
        }
    }
}