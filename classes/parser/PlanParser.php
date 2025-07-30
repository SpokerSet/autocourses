<?php
namespace local_coursebuilder\parser;

defined('MOODLE_INTERNAL') || die();

class PlanParser {
    /**
     * @param string $filepath абсолютный путь до PDF
     * @return array
     */
    public static function parse(string $filepath): array {
        // 1. Конвертация PDF → текст (через внешнюю библиотеку)
        // 2. Регулярки/парсер для извлечения блоков «Факультет», «Специальность», семестры, дисциплины
        // 3. Собрать вложенный массив:
        //    ['Факультет A' => ['СПО-01' => [1 => ['Математика', …], 2 => […]]]]
        return $structuredData;
    }
}