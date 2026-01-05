<?php
namespace local_autocourses;
require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();
admin_externalpage_setup('local_autocourses_fullimport');

use local_autocourses\coursegenerator;
use core_php_time_limit;

ignore_user_abort(true);
@set_time_limit(0);
core_php_time_limit::raise(600);
raise_memory_limit(MEMORY_HUGE);

// === Параметры ===
$year    = optional_param('year', '2025-2026', PARAM_TEXT);
$offset  = optional_param('offset', 0, PARAM_INT);
$limit   = optional_param('limit', 100, PARAM_INT);
$refresh = optional_param('refresh', 5, PARAM_INT);

$raw   = \local_autocourses\externaldbprovider::get_specialities($year);
$plans = \local_autocourses\planprovider::normalize($raw);
$tree  = \local_autocourses\planprovider::attach_disciplines($plans);
global $DB;

$created  = 0;
$skipped  = 0;
$errors   = 0;
$duration = 0.0;
$log      = [];

$tasks = [];
foreach ($tree as $faculty => $specs) {
    foreach ($specs as $codespec => $groups) {
        foreach ($groups as $group => $info) {
            foreach ($info['semesters'] ?? [] as $semester => $disciplines) {
                foreach ($disciplines as $discipline) {
                    $tasks[] = [
                        'faculty'    => $faculty,
                        'codespec'   => $codespec,
                        'group'      => $group,
                        'semester'   => $semester,
                        'discipline' => $discipline
                    ];
                }
            }
        }
    }
}
$total = count($tasks);

if ($offset < $total) {
    $slice = array_slice($tasks, $offset, $limit);
    $t0 = microtime(true);

    foreach ($slice as $t) {
        $disc = $t['discipline'];
        $discname = is_array($disc) ? ($disc['name'] ?? reset($disc)) : (string)$disc;

        $fullname  = "{$t['faculty']} {$t['group']} {$discname} (семестр {$t['semester']})";
        $shortname = "{$year}_{$t['group']}_{$discname}_{$t['semester']}";

        $log[] = ['status'=>'creating','name'=>$fullname,'short'=>$shortname];

        if ($DB->record_exists('course', ['shortname' => $shortname])) {
            $skipped++;
            $log[] = ['status'=>'skipped','name'=>$fullname,'short'=>$shortname];
            continue;
        }

        $chunk = [
            $t['faculty'] => [
                $t['codespec'] => [
                    $t['group'] => [
                        'semesters' => [
                            $t['semester'] => [
                                $disc
                            ]
                        ]
                    ]
                ]
            ]
        ];

        try {
            $res = coursegenerator::create_courses($chunk);
            $nowExists = $DB->record_exists('course', ['shortname' => $shortname]);

            if ($res && $nowExists) {
                $created++;
                $log[] = ['status'=>'created','name'=>$fullname,'short'=>$shortname];
            } else {
                $errors++;
                $log[] = ['status'=>'error','name'=>$fullname,'short'=>$shortname];
            }
        } catch (\Throwable $e) {
             $msg = $e->getMessage();
                if (strpos($msg, 'already exists') !== false || strpos($msg, 'duplicate') !== false) {
                    $skipped++;
                    $log[] = ['status'=>'skipped','name'=>$fullname,'short'=>$shortname,'msg'=>'уже существует'];
                } else {
                    $errors++;
                    $log[] = ['status'=>'error','name'=>$fullname,'short'=>$shortname,'msg'=>$msg];
                }
        }
    }

    $duration = round(microtime(true) - $t0, 2);
    $offset += $limit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading("Автоимпорт курсов");

// Прогресс-бар
$done = min($offset, $total);
$percent = $total > 0 ? round(($done / $total) * 100) : 0;
echo "<div style='width:100%;background:#eee;border:1px solid #ccc;height:20px;margin:8px 0;'>
        <div style='width:{$percent}%;background:green;height:100%;'></div>
      </div>";
echo "<p>Прогресс: {$done} / {$total} ({$percent}%)</p>";

if (!empty($log)) {
    echo "<p>Последняя порция: создано {$created}, пропущено {$skipped}, ошибок {$errors}, время {$duration} сек</p>";
    echo "<div style='max-height:260px;overflow:auto;border:1px solid #ddd;padding:6px;'>";
    foreach ($log as $r) {
        $status = $r['status'] ?? 'info';
        $name   = s((string)($r['name'] ?? ''));
        $short  = s((string)($r['short'] ?? ''));
        $msg    = isset($r['msg']) ? ' — '.s((string)$r['msg']) : '';

        // Цвета по статусу
        $color =
            $status === 'created'  ? 'green'  :
            ($status === 'skipped' ? 'gray'   :
            ($status === 'creating'? 'blue'   : 'red'));

        echo "<div style='color:{$color}'>" . s($status) . " — {$name} [{$short}]{$msg}</div>";
    }
    echo "</div>";
}

if ($done >= $total) {
    echo "<p style='color:green'>🎉 Импорт завершён: всего задач {$total}</p>";
} else {
    // Автообновление через $refresh секунд
    $refresh = max(1, (int)$refresh);
    $url = new \moodle_url('/local/autocourses/fullimport.php', [
        'offset'  => $offset,
        'year'    => $year,
        'limit'   => $limit,
        'refresh' => $refresh
    ]);
    echo "<meta http-equiv='refresh' content='{$refresh};url={$url}'>";
    echo "<p>Автообновление через {$refresh} сек.</p>";

    // Управляющая форма
    echo "<form method='get' style='margin-top:8px;display:flex;gap:8px;align-items:center;'>
        <input type='hidden' name='year' value='".s($year)."'>
        <label>Offset: <input type='number' name='offset' value='".(int)$offset."' min='0' style='width:120px;'></label>
        <label>Limit: <input type='number' name='limit' value='".(int)$limit."' min='10' max='500' step='10' style='width:120px;'></label>
        <label>Refresh (сек): <input type='number' name='refresh' value='".(int)$refresh."' min='1' max='60' style='width:120px;'></label>
        <button type='submit'>Применить</button>
        <a href='".$url."' class='btn btn-secondary'>Обновить сейчас</a>
    </form>";

    echo "<form method='post' style='margin-top:8px;'>
        <input type='hidden' name='offset' value='".(int)$offset."'>
        <input type='hidden' name='year' value='".s($year)."'>
        <input type='hidden' name='limit' value='".(int)$limit."'>
        <input type='hidden' name='refresh' value='".(int)$refresh."'>
        <input type='submit' value='Остановить автоимпорт'>
    </form>";
}

echo $OUTPUT->footer();
