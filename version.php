<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_coursebuilder';
$plugin->version   = 2025073000;      // Дата и версия
$plugin->requires  = 2020110900;      // Минимальная версия Moodle (3.10)
$plugin->cron      = 600;             // Запускать каждые 10 минут