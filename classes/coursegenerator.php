<?php
namespace local_autocourses;

defined('MOODLE_INTERNAL') || die();
define('COURSE_FULLNAME_MAXLEN', 80);
define('COURSE_SHORTNAME_MAXLENGTH', 20);

class coursegenerator {

    public static function create_courses(array $tree) {
        global $DB;

        foreach ($tree as $faculty => $codespecs) {
            $facultycatid = self::ensure_category($faculty, 0);

            if (!is_array($codespecs)) {
                continue;
            }

            foreach ($codespecs as $codespec => $groups) {
                $specid = self::ensure_category($codespec, $facultycatid);

                if (!is_array($groups)) {
                    continue;
                }

                foreach ($groups as $group => $info) {
                    $groupcatid = self::ensure_category($group, $specid);

                    if (isset($info['semesters']) && is_array($info['semesters'])) {
                        foreach ($info['semesters'] as $semester => $disciplines) {
                            $semcatid = self::ensure_category("Семестр {$semester}", $groupcatid);

                            foreach ($disciplines as $discipline) {
                                $fullname  = mb_substr($discipline['name'], 0, COURSE_FULLNAME_MAXLEN);
                                $shortname = self::generate_shortname($discipline['name'], $group, $semester);

                                self::ensure_course($fullname, $semcatid, $shortname, $discipline);
                            }
                        }
                    } elseif (isset($info[0]) && is_array($info[0])) {
                        foreach ($info as $discipline) {
                            $fullname  = mb_substr($discipline['name'], 0, COURSE_FULLNAME_MAXLEN);
                            $shortname = self::generate_shortname($discipline['name'], $group, $semester);

                            if ($DB->record_exists('course', ['shortname' => $shortname])) {
                                mtrace("Курс '{$shortname}' уже существует, пропускаем создание.");
                                continue;
                            }
                            self::ensure_course($fullname, $groupcatid, $shortname, $discipline);
                        }
                    }
                }
            }
        }
    }

    protected static function ensure_category(string $name, int $parentid): int {
        global $DB;

        if ($cat = $DB->get_record('course_categories', ['name' => $name, 'parent' => $parentid])) {
            return $cat->id;
        }

        $newcat = new \stdClass();
        $newcat->name   = $name;
        $newcat->parent = $parentid;
        $newcat->id     = $DB->insert_record('course_categories', $newcat);

        return $newcat->id;
    }

    protected static function ensure_course(
        string $fullname,
        int $categoryid,
        string $shortname,
        array $discipline = []
    ): int {
        global $DB, $CFG;

        require_once($CFG->dirroot.'/course/lib.php');

        if ($DB->record_exists('course', ['shortname' => $shortname])) {
            mtrace("Пропущен: {$fullname} / {$shortname} (уже существует в БД)");
            return 0;
        }

        $newcourse = new \stdClass();
        $newcourse->fullname   = $fullname;
        $newcourse->shortname  = $shortname;
        $newcourse->category   = $categoryid;

        if (!empty($discipline)) {
            $credits = $discipline['credits'] ?? '';
            $hours   = $discipline['hours'] ?? '';
            $types   = !empty($discipline['types']) ? implode(', ', $discipline['types']) : '';
            $newcourse->summary = trim("{$credits} кредита, {$hours} часов, форма контроля: {$types}");
            $newcourse->summaryformat = FORMAT_HTML;
        }

        try {
            $course = create_course($newcourse);
            mtrace("Создан: id={$course->id}, fullname='{$fullname}', shortname='{$shortname}'");
            return $course->id;
        } catch (\Throwable $e) {
            mtrace("Ошибка: fullname='{$fullname}', shortname='{$shortname}' — {$e->getMessage()}");
            return 0;
        }
    }

    protected static function generate_shortname(string $discipline, string $group, int $semester): string {
        global $DB;

        // Аббревиатура дисциплины: первые буквы слов
        $words = preg_split('/\s+/u', trim($discipline));
        $abbr  = implode('', array_map(fn($w) => mb_substr($w, 0, 1, 'UTF-8'), $words));
        $abbr  = mb_strtoupper($abbr, 'UTF-8');

        // Базовый shortname: группа + аббревиатура + семестр
        $base = preg_replace('/[^A-Za-zА-Яа-я0-9_-]/u', '_', "{$group}_{$abbr}_{$semester}");
        $base = mb_substr($base, 0, COURSE_SHORTNAME_MAXLENGTH, 'UTF-8');

        // Уникальность
        $shortname = $base;
        $i = 1;
        while ($DB->record_exists('course', ['shortname' => $shortname])) {
            $suffix = "_{$i}";
            $shortname = mb_substr($base, 0, COURSE_SHORTNAME_MAXLENGTH - mb_strlen($suffix, 'UTF-8'), 'UTF-8') . $suffix;
            $i++;
        }

        return $shortname;
    }
}
