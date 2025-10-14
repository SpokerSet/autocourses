<?php
// local/autocourses/index.php
require_once(__DIR__ . '/../../config.php');
defined('MOODLE_INTERNAL') || die();

use local_autocourses\planprovider;
use local_autocourses\coursegenerator;

require_login();
$context = context_system::instance();
require_capability('local/autocourses:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/autocourses/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_autocourses'));
$PAGE->set_heading(get_string('pluginname', 'local_autocourses'));

echo $OUTPUT->header();

// Параметры
$group = optional_param('group', '', PARAM_RAW_TRIMMED);
$spec  = optional_param('spec', '', PARAM_RAW_TRIMMED);
$year  = optional_param('year', '', PARAM_RAW_TRIMMED);
$mode  = optional_param('mode', '', PARAM_ALPHANUMEXT); // 'all' для полного прохода по всем специальностям

$create_courses_from_plans = function(array $plans, string $source) {
    global $OUTPUT;
    if (empty($plans)) {
        echo $OUTPUT->notification("Нет планов для обработки (источник: {$source})", 'notifymessage');
        return;
    }
    foreach ($plans as $plan) {
        try {
            $courseid = coursegenerator::create_course_from_plan($plan);
            echo html_writer::tag('div', "✅ Курс " . s($plan['fullname']) . " создан (ID: {$courseid}) — источник: {$source}");
        } catch (Exception $e) {
            echo html_writer::tag('div', "⚠️ Ошибка при создании курса " . s($plan['fullname']) . ": " . s($e->getMessage()), array('class' => 'notifyproblem'));
        }
    }
};

// Режим: конкретная группа
if ($group !== '') {
    try {
        if (!preg_match('/^\d{2}[-].+$/u', $group)) {
            throw new Exception("Неверный формат группы: " . $group . ". Ожидается формат '25-XXX'.");
        }
        $plans = planprovider::fetch_plans_for_group($group);
        $create_courses_from_plans($plans, "group {$group}");
    } catch (Exception $e) {
        try {
            $plans = planprovider::get_cached_plans_for_group($group);
            if (!empty($plans)) {
                echo $OUTPUT->notification('Ошибка получения актуальных планов: ' . s($e->getMessage()) . '. Использую кеш.', 'notifywarning');
                $create_courses_from_plans($plans, "cached group {$group}");
            } else {
                echo $OUTPUT->notification('Ошибка получения планов для группы: ' . s($e->getMessage()), 'notifyproblem');
            }
        } catch (Exception $ignored) {
            echo $OUTPUT->notification('Ошибка при попытке использовать кеш: ' . s($ignored->getMessage()), 'notifyproblem');
        }
    }

    echo $OUTPUT->footer();
    exit;
}

// Режим: одна специальность
if ($spec !== '') {
    try {
        $yearprefix = $year ?: get_config('local_autocourses', 'default_yearprefix') ?: '25';
        if (!preg_match('/^\d{2}$/', $yearprefix)) {
            throw new Exception('Неверный префикс года: ' . $yearprefix);
        }

        $groups = planprovider::get_groups_for_specialty($spec, $yearprefix);
        if (empty($groups)) {
            throw new Exception('По специальности не найдено ни одной группы.');
        }

        foreach ($groups as $g) {
            try {
                $plans = planprovider::fetch_plans_for_group($g);
                $create_courses_from_plans($plans, "spec {$spec} -> group {$g}");
            } catch (Exception $e) {
                $cached = planprovider::get_cached_plans_for_group($g);
                if (!empty($cached)) {
                    echo $OUTPUT->notification('Ошибка получения планов для ' . s($g) . ': ' . s($e->getMessage()) . '. Использую кеш.', 'notifywarning');
                    $create_courses_from_plans($cached, "cached group {$g}");
                } else {
                    echo $OUTPUT->notification('Ошибка для группы ' . s($g) . ': ' . s($e->getMessage()), 'notifyproblem');
                }
            }
        }
    } catch (Exception $e) {
        echo $OUTPUT->notification('Ошибка получения групп по специальности: ' . s($e->getMessage()), 'notifyproblem');
    }

    echo $OUTPUT->footer();
    exit;
}

// Режим: полный проход по всем специальностям (mode=all)
if ($mode === 'all') {
    $yearprefix = $year ?: get_config('local_autocourses', 'default_yearprefix') ?: '25';
    if (!preg_match('/^\d{2}$/', $yearprefix)) {
        echo $OUTPUT->notification('Неверный префикс года: ' . s($yearprefix), 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }

    try {
        $specialties = planprovider::fetch_all_specialties();
    } catch (Exception $e) {
        echo $OUTPUT->notification('Ошибка получения списка специальностей: ' . s($e->getMessage()), 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }

    foreach ($specialties as $specrecord) {
        $group = planprovider::get_group_from_specialty_record($specrecord, $yearprefix);
        if ($group === null) {
            echo html_writer::tag('div', "Пропущена специальность " . s($specrecord['id'] ?? 'unknown') . " — нет grp");
            continue;
        }

        try {
            $plans = planprovider::fetch_plans_for_group($group);
            $create_courses_from_plans($plans, "all-spec -> group {$group}");
        } catch (Exception $e) {
            echo html_writer::tag('div', "Ошибка получения планов для " . s($group) . ": " . s($e->getMessage()), array('class' => 'notifywarning'));
            $cached = planprovider::get_cached_plans_for_group($group);
            if (!empty($cached)) {
                echo $OUTPUT->notification('Использую кеш для ' . s($group), 'notifymessage');
                $create_courses_from_plans($cached, "cached group {$group}");
            }
        }
        
        usleep(100000);
    }

    echo $OUTPUT->footer();
    exit;
}

// UI: форма запуска
$formurl = new moodle_url('/local/autocourses/index.php');
echo html_writer::start_tag('form', array('method' => 'get', 'action' => $formurl->out(false)));
echo html_writer::tag('div', get_string('runbygroup', 'local_autocourses'));
echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'group', 'placeholder' => '25-ГРП', 'value' => ''));
echo html_writer::empty_tag('br');
echo html_writer::tag('div', get_string('runbyspec', 'local_autocourses'));
echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'spec', 'placeholder' => 'spec id', 'value' => ''));
echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'year', 'placeholder' => '25 (optional)', 'value' => ''));
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('run', 'local_autocourses')));
echo html_writer::end_tag('form');

// Кнопка полного прохода
$allurl = new moodle_url('/local/autocourses/index.php', ['mode' => 'all']);
echo html_writer::tag('div', html_writer::link($allurl, get_string('runall', 'local_autocourses')));
echo $OUTPUT->footer();
