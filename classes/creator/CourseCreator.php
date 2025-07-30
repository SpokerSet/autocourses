<?php
namespace local_coursebuilder\creator;

defined('MOODLE_INTERNAL') || die();

use core_course;

class CourseCreator {

    /**
     * Создаёт категорию, если нет
     */
    public static function get_or_create_category(string $name, int $parent = 0): int {
        global $DB;
        if ($cat = $DB->get_record('course_categories', ['name' => $name, 'parent' => $parent])) {
            return $cat->id;
        }
        return core_course_category::create(['name' => $name, 'parent' => $parent])->id;
    }

    /**
     * Создаёт курс под семестр
     */
    public static function create_course(array $coursedata): int {
        return core_course_external::create_courses([$coursedata])[0]['id'];
    }

    /**
     * Основной метод: принимает структуру и порождает курс для каждого модуля
     */
    public static function build_from_plan(array $plan): void {
        foreach ($plan as $faculty => $specialties) {
            $faccat = self::get_or_create_category($faculty);
            foreach ($specialties as $spec => $semesters) {
                $speccat = self::get_or_create_category($spec, $faccat);
                foreach ($semesters as $sem => $modules) {
                    foreach ($modules as $modtitle) {
                        $coursedata = [
                            'fullname'  => "$spec. Семестр $sem: $modtitle",
                            'shortname' => substr($modtitle, 0, 20) . "_{$spec}_S{$sem}",
                            'categoryid'=> $speccat,
                        ];
                        self::create_course($coursedata);
                    }
                }
            }
        }
    }
}