<?php
defined('MOODLE_INTERNAL') || die();

class local_autocourses_coursegenerator {

    
    public static function find_course_by_shortname(string $shortname) {
        global $DB;
        $shortname = trim($shortname);
        if ($shortname === '') { return null; }
        return $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING) ?: null;
    }

    
    public static function create_course(array $data, int $categoryid, bool $dryrun = false): int {
        // $data ожидает: fullname, shortname, summary, numsections, format
        if ($dryrun) {
            local_autocourses_helper::log('INFO', "DRYRUN create course {$data['shortname']} in category {$categoryid}");
            return 0;
        }

        $new = new stdClass();
        $new->category = $categoryid;
        $new->fullname = $data['fullname'];
        $new->shortname = $data['shortname'];
        $new->summary = $data['summary'] ?? '';
        $new->numsections = (int)($data['numsections'] ?? 10);
        $new->visible = 1;
        $new->format = $data['format'] ?? 'topics';

        $course = create_course($new);
        local_autocourses_helper::log('INFO', "Created course {$course->shortname} id {$course->id} in category {$categoryid}");
        return (int)$course->id;
    }

    
    public static function update_course(int $courseid, array $data, int $categoryid, bool $dryrun = false): int {
        global $DB;
        $existing = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        $upd = new stdClass();
        $upd->id = $existing->id;
        $upd->fullname = $data['fullname'] ?? $existing->fullname;
        $upd->summary = $data['summary'] ?? $existing->summary;
        $upd->category = $categoryid;
        $upd->numsections = (int)($data['numsections'] ?? $existing->numsections);

        if ($dryrun) {
            local_autocourses_helper::log('INFO', "DRYRUN update course {$existing->shortname} id {$existing->id} in category {$categoryid}");
            return $existing->id;
        }

        $DB->update_record('course', $upd);
        local_autocourses_helper::log('INFO', "Updated course {$existing->shortname} id {$existing->id} in category {$categoryid}");
        return (int)$existing->id;
    }

    
    public static function ensure_course_from_plan(array $plan, int $categoryid, bool $dryrun = false): int {
        // Нормализация входа
        $code = trim($plan['code'] ?? '');
        $name = trim($plan['name'] ?? ($plan['fullname'] ?? 'Unnamed course'));
        $semester = isset($plan['semester']) ? intval($plan['semester']) : 0;

        // fullname шаблон
        $fullname = $code !== '' ? "{$code} — {$name}" : $name;
        if ($semester > 0) {
            $fullname .= " (сем. {$semester})";
        }

        // shortname: предпочитаем код, иначе deterministic hash
        if ($code !== '') {
            $short = preg_replace('/\s+/', '', mb_strtoupper($code, 'UTF-8'));
        } else {
            $short = 'AC-' . strtoupper(substr(md5($name), 0, 6));
        }

        // Убедиться в уникальности shortname: если уже занят другим course и fullname не совпадает — расширить
        $existing = self::find_course_by_shortname($short);
        if ($existing && $existing->fullname !== $fullname) {
            // добавляем суффикс семестра или хэш, чтобы избежать конфликта
            $suffix = $semester ? "-S{$semester}" : "-" . substr(md5($name), 0,4);
            $short = $short . $suffix;
        }

        // summary: добавим семестр и источник
        $summaryParts = [];
        if (!empty($plan['summary'])) { $summaryParts[] = $plan['summary']; }
        if ($semester > 0) { $summaryParts[] = "Semester: {$semester}"; }
        if (!empty($plan['source'])) { $summaryParts[] = "Source: " . $plan['source']; }
        $summary = implode("\n", $summaryParts);

        $data = [
            'fullname' => $fullname,
            'shortname' => $short,
            'summary' => $summary,
            'numsections' => (int)($plan['numsections'] ?? 10),
            'format' => $plan['format'] ?? 'topics'
        ];

        if ($existing) {
            // Если найден курс с тем же shortname — обновляем
            return self::update_course((int)$existing->id, $data, $categoryid, $dryrun);
        } else {
            return self::create_course($data, $categoryid, $dryrun);
        }
    }
}
