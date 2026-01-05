<?php
namespace local_autocourses;

defined('MOODLE_INTERNAL') || die();

class planprovider {
    public static function normalize(array $rows): array {
        $tree = [];

        foreach ($rows as $row) {
            $faculty = $row['Faculty'];
            $code    = $row['SpecialityCode'];
            $spec    = $row['Speciality'];
            $group   = $row['Group'];
            $plan    = $row['Plan'];
            $uplan   = $row['uplan'];
            $codeSpec = $code . ' ' . $spec;

            if (!isset($tree[$faculty][$codeSpec][$group])) {
                $tree[$faculty][$codeSpec][$group] = [
                    'semesters'   => [],
                    'disciplines' => [],
                    '_meta' => [
                        'plan'  => $plan ?: null,
                        'uplan' => $uplan ?: null
                    ]
                ];
            }
        }
        return $tree;
    }

    public static function attach_disciplines(array $tree): array {
        foreach ($tree as $faculty => &$codespecs) {
            foreach ($codespecs as $codespec => &$groups) {
                foreach ($groups as $group => &$info) {
                    $semesters = self::fetch_disciplines_by_group($group);
                    $info['semesters'] = [];

                    foreach ($semesters as $sem) {
                        $semnum = $sem['semester'] ?? '?';
                        $count  = is_array($sem['disciplines']) ? count($sem['disciplines']) : 0;
                        debugging("📘 Группа {$group}, семестр {$semnum}: дисциплин {$count}", DEBUG_DEVELOPER);

                        if (!empty($sem['semester']) && !empty($sem['disciplines']) && is_array($sem['disciplines'])) {
                            $info['semesters'][$sem['semester']] = $sem['disciplines'];
                        } else {
                            debugging("⚠️ Пропущен семестр {$semnum} для группы {$group} — нет дисциплин или неверный формат", DEBUG_DEVELOPER);
                        }
                    }

                    debugging("✅ Группа {$group} → семестров: " . count($info['semesters']), DEBUG_DEVELOPER);
                }
            }
        }
        return $tree;
    }

   protected static function fetch_disciplines_by_group(string $group): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $url = 'http://localhost:32123/education-plans/moodle-disciplines?group=' . urlencode($group);

        $curl = new \curl();
        $response = $curl->get($url, [], ['CURLOPT_RETURNTRANSFER' => true]);

        if ($response === false || empty($response)) {
            debugging("API вернул пустой ответ для группы {$group}", DEBUG_DEVELOPER);
            return [];
        }

        $data = json_decode($response, true);
        debugging("RAW response for {$group}: " . substr($response, 0, 300), DEBUG_DEVELOPER);
        debugging("DECODED for {$group}: " . json_encode($data, JSON_UNESCAPED_UNICODE), DEBUG_DEVELOPER);
        
        if (!isset($data['data']) || !is_array($data['data'])) {
            debugging("Нет ключа 'data' в ответе API для группы {$group}", DEBUG_DEVELOPER);
            return [];
        }

        $result = [];
        foreach ($data['data'] as $entry) {
            if (!empty($entry['semester']) && !empty($entry['disciplines']) && is_array($entry['disciplines'])) {
                $result[] = [
                    'semester'   => (int)$entry['semester'],
                    'disciplines'=> $entry['disciplines']
                ];
            } else {
                debugging("⚠️ Пропущен семестр без дисциплин или с некорректной структурой: " . json_encode($entry, JSON_UNESCAPED_UNICODE), DEBUG_DEVELOPER);
            }
        }

        return $result;
    }

}
