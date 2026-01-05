<?php
namespace local_autocourses;

defined('MOODLE_INTERNAL') || die();

class coursestats {

    // Возвращает статистику по типам модулей
    public static function get_course_stats(int $courseid): array {
        global $DB;
        $sql = "SELECT m.name AS modtype, COUNT(cm.id) AS count
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND cm.deletioninprogress = 0
              GROUP BY m.name";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        $stats = [];
        foreach ($records as $rec) {
            $stats[$rec->modtype] = $rec->count;
        }
        return $stats;
    }

    // Возвращает общую заполненность
    public static function get_course_fill_level(int $courseid): array {
        $details = self::get_course_stats($courseid);
        $total = array_sum($details);
        return ['total' => $total, 'details' => $details];
    }

    // Возвращает статус (например, пустой/средне/заполнен)
    public static function get_course_status(int $courseid): string {
        $total = self::get_course_fill_level($courseid)['total'];
        if ($total >= 10) return 'Заполнен';
        if ($total >= 3) return 'Средне';
        return 'Пустой';
    }
}
