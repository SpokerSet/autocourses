<?php
namespace local_autocourses\log;

defined('MOODLE_INTERNAL') || die();

class logger {
    public const LEVEL_INFO    = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR   = 'ERROR';

    public static function write(string $action, string $message, string $level = self::LEVEL_INFO, ?array $context_data = null): void {
        global $DB, $USER;

        $log_record = new \stdClass();
        $log_record->userid      = !empty($USER->id) ? $USER->id : 0;
        $log_record->action      = mb_strtoupper($action, 'UTF-8');
        $log_record->loglevel    = $level;
        $log_record->message     = $message;
        $log_record->contextdata = !empty($context_data) ? json_encode($context_data, JSON_UNESCAPED_UNICODE) : null;
        $log_record->timecreated = time();

        try {
            $DB->insert_record('local_autocourses_logs', $log_record);
        } catch (\moodle_exception $e) {
            error_log("Autocourses Logger Critical Error: " . $e->getMessage());
        }
    }
}