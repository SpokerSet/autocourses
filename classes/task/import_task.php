<?php
namespace local_autocourses\task;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/autocourses/classes/coursegenerator.php');

class import_task extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_import_plans', 'local_autocourses');
    }

    public function execute(): void {
        global $DB;

        $customdata = $this->get_custom_data();
        
        if (!isset($customdata->tree_data) || !is_array($customdata->tree_data)) {
            mtrace("Критическая ошибка: отсутствуют или повреждены данные дерева планов в payload задачи.");
            return;
        }

        mtrace("Старт фонового процесса развертывания инфраструктуры курсов...");
        $start_time = microtime(true);

        try {
            \local_autocourses\coursegenerator::create_courses($customdata->tree_data);
            
            $execution_time = round(microtime(true) - $start_time, 2);
            mtrace("Фоновая задача успешно выполнена. Время обработки: {$execution_time} сек.");

        } catch (\Throwable $e) {
            mtrace("Произошел критический сбой фонового импорта: " . $e->getMessage());
            throw $e; 
        }
    }
}
