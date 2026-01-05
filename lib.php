<?php
defined('MOODLE_INTERNAL') || die();

class helper {

    public static function log(string $level, string $message): void {
        $logdir = __DIR__ . '/../logs';
        if (!is_dir($logdir)) { @mkdir($logdir, 0755, true); }
        $line = date('c') . " [{$level}] " . $message . PHP_EOL;
        file_put_contents($logdir . '/autocourses.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function get_category_by_idnumber(string $idnumber): ?stdClass {
        global $DB;
        if (trim($idnumber) === '') {
            return null;
        }
        return $DB->get_record('course_categories', ['idnumber' => $idnumber], '*', IGNORE_MISSING) ?: null;
    }

    
    public static function get_category_by_name_parent(string $name, int $parentid = 0): ?stdClass {
        global $DB;
        $name = trim($name);
        if ($name === '') { return null; }
        return $DB->get_record('course_categories', ['name' => $name, 'parent' => $parentid], '*', IGNORE_MISSING) ?: null;
    }

    
    public static function create_category(string $name, string $idnumber = '', int $parentid = 0): int {
        global $CFG;
        require_once($CFG->libdir . '/coursecatlib.php');
        $new = new stdClass();
        $new->name = $name;
        $new->description = '';
        $new->parent = $parentid;
        $new->visible = 1;
        $new->idnumber = $idnumber;
        $catobj = \coursecat::create($new);
        return (int)$catobj->id;
    }

    public static function update_category_idnumber(int $catid, string $idnumber): void {
        global $DB;
        $idnumber = trim($idnumber);
        if ($idnumber === '') { return; }
        $existing = $DB->get_record('course_categories', ['id' => $catid], '*', MUST_EXIST);
        $conflict = $DB->get_record('course_categories', ['idnumber' => $idnumber], '*', IGNORE_MISSING);
        if ($conflict && $conflict->id != $catid) {
            throw new \Exception("idnumber {$idnumber} already used by category id {$conflict->id}");
        }
        $existing->idnumber = $idnumber;
        $DB->update_record('course_categories', $existing);
    }

    public static function find_category(string $name, string $idnumber = '', int $parentid = 0): ?stdClass {
        if (trim($idnumber) !== '') {
            $byid = self::get_category_by_idnumber($idnumber);
            if ($byid) { return $byid; }
        }
        return self::get_category_by_name_parent($name, $parentid);
    }

    // Оркестратор: гарантия существования — вызывает get или create; возвращает id
    public static function ensure_category(string $name, string $idnumber = '', int $parentid = 0, bool $dryrun = false): int {
        $found = self::find_category($name, $idnumber, $parentid);
        if ($found) {
            // если найденная категория не имеет idnumber, а пришёл idnumber — можно обновить отдельно
            if ($found->idnumber === '' && trim($idnumber) !== '') {
                if (!$dryrun) {
                    self::update_category_idnumber((int)$found->id, $idnumber);
                } else {
                    // логирование dryrun можно добавить
                }
            }
            return (int)$found->id;
        }
        
        if ($dryrun) {
            return 0;
        }
        return self::create_category($name, $idnumber, $parentid);
    }
}
