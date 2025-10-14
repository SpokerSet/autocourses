<?php
namespace local_autocourses;

defined('MOODLE_INTERNAL') || die();

class planprovider {

    /**
     * Получить планы для конкретной группы (внешний API).
     *
     * @param string $group Формат: "25-ГРП" или аналогичный
     * @return array
     * @throws \Exception
     */
    public static function fetch_plans_for_group(string $group): array {
        // Базовый URL для получения планов
        $baseurl = get_config('local_autocourses', 'api_baseurl') ?: 'http://localhost:32123/education-plans/moodle-diciplians';
        $url = $baseurl . '?group=' . urlencode($group);

        // кеш директория и файл
        $cachedir = __DIR__ . '/../cache';
        if (!is_dir($cachedir) && !mkdir($cachedir, 0755, true) && !is_dir($cachedir)) {
            throw new \Exception('Не удалось создать каталог кеша: ' . $cachedir);
        }
        $safegroup = preg_replace('/[^a-zA-Z0-9_\-]/u', '_', $group);
        $cachefile = $cachedir . "/group_{$safegroup}.json";

        // cURL запрос
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerr = curl_errno($ch) ? curl_error($ch) : '';
        curl_close($ch);

        if ($response === false || $httpcode < 200 || $httpcode >= 300) {
            // попытка использовать кеш
            if (file_exists($cachefile)) {
                $response = file_get_contents($cachefile);
            } else {
                throw new \Exception('Ошибка запроса к API планов: ' . ($curlerr ?: 'HTTP ' . $httpcode));
            }
        } else {
            // записать свежий кеш
            file_put_contents($cachefile, $response, LOCK_EX);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \Exception('Неверный JSON от API планов');
        }

        // Маппинг полей ответа API в формат coursegenerator
        $plans = [];
        foreach ($data as $item) {
            $plans[] = [
                'fullname'    => $item['name'] ?? $item['fullname'] ?? 'Unnamed course',
                'shortname'   => $item['code'] ?? $item['shortname'] ?? 'SC-' . substr(md5(json_encode($item)), 0, 6),
                'categoryid'  => (int)($item['categoryid'] ?? get_config('local_autocourses', 'defaultcategory') ?: 1),
                'numsections' => (int)($item['sections'] ?? 5),
                'summary'     => $item['summary'] ?? ''
            ];
        }

        return $plans;
    }

    /**
     * Вернуть кешированные планы для группы, если нужно
     *
     * @param string $group
     * @return array
     */
    public static function get_cached_plans_for_group(string $group): array {
        $cachedir = __DIR__ . '/../cache';
        $safegroup = preg_replace('/[^a-zA-Z0-9_\-]/u', '_', $group);
        $cachefile = $cachedir . "/group_{$safegroup}.json";

        if (!file_exists($cachefile)) {
            return [];
        }

        $content = file_get_contents($cachefile);
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        $plans = [];
        foreach ($data as $item) {
            $plans[] = [
                'fullname'    => $item['name'] ?? $item['fullname'] ?? 'Unnamed course',
                'shortname'   => $item['code'] ?? $item['shortname'] ?? 'SC-' . substr(md5(json_encode($item)), 0, 6),
                'categoryid'  => (int)($item['categoryid'] ?? get_config('local_autocourses', 'defaultcategory') ?: 1),
                'numsections' => (int)($item['sections'] ?? 5),
                'summary'     => $item['summary'] ?? ''
            ];
        }

        return $plans;
    }

    /**
     * Получить список всех специальностей из API (с кешированием)
     *
     * @return array массив записей специальностей
     * @throws \Exception
     */
    public static function fetch_all_specialties(): array {
        $baseurl = get_config('local_autocourses', 'api_specialties_url') ?: 'http://localhost:32123/specialties/all';
        $cachedir = __DIR__ . '/../cache';
        if (!is_dir($cachedir) && !mkdir($cachedir, 0755, true) && !is_dir($cachedir)) {
            throw new \Exception('Не удалось создать каталог кеша: ' . $cachedir);
        }
        $cachefile = $cachedir . '/specialties_all.json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch) ? curl_error($ch) : '';
        curl_close($ch);

        if ($response === false || $http < 200 || $http >= 300) {
            if (file_exists($cachefile)) {
                $response = file_get_contents($cachefile);
            } else {
                throw new \Exception('Ошибка получения списка специальностей: ' . ($err ?: 'HTTP ' . $http));
            }
        } else {
            file_put_contents($cachefile, $response, LOCK_EX);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \Exception('Неверный JSON от specialties API');
        }

        return $data;
    }

        /**
     * Извлечь из массива записей специальностей все уникальные grp-значения.
     *
     * @param array $specialties
     * @return array массив строк grp (не нормализованных)
     */
    public static function extract_raw_groups_from_specialties(array $specialties): array {
        $groups = [];
        foreach ($specialties as $rec) {
            $cand = null;
            foreach (['grp','group','group_name','grp_code','grpname'] as $k) {
                if (!empty($rec[$k])) { $cand = $rec[$k]; break; }
            }
            if ($cand === null) { continue; }
            if (is_array($cand)) { $cand = implode(' ', $cand); }
            $cand = trim((string)$cand);
            if ($cand === '') { continue; }
            $groups[] = $cand;
        }
        $uniq = array_values(array_unique($groups));
        return $uniq;
    }

        /**
     * Нормализация grp в форму "{yearprefix}-{GRP}", очистка лишних символов.
     *
     * @param string $raw
     * @param string $yearprefix
     * @return string|null возвращает null если не удалось нормализовать
     */
    public static function normalize_group(string $raw, string $yearprefix = ''): ?string {
        if ($yearprefix === '') {
            $yearprefix = get_config('local_autocourses','default_yearprefix') ?: '25';
        }
        $raw = trim($raw);
        if ($raw === '') { return null; }
        // убрать разделители и лишние символы, сохранить кириллицу/латиницу/цифры и дефис
        $raw = preg_replace('/[;\/,]+/', ' ', $raw);
        $raw = preg_replace('/[^\p{Cyrillic}\p{Latin}0-9\s\-]/u', '', $raw);
        $raw = preg_replace('/\s+/', ' ', $raw);
        $raw = mb_strtoupper($raw, 'UTF-8');
        if ($raw === '') { return null; }
        return $yearprefix . '-' . $raw;
    }

    /**
     * Удобный метод: получить нормализованные уникальные группы напрямую из API.
     *
     * @param string $yearprefix
     * @return array массив нормализованных групп, например ['25-ИВТ','25-ЮР']
     */
    public static function fetch_groups_from_specialties(string $yearprefix = ''): array {
        $specialties = self::fetch_all_specialties();
        $raw = self::extract_raw_groups_from_specialties($specialties);
        $out = [];
        foreach ($raw as $r) {
            $n = self::normalize_group($r, $yearprefix);
            if ($n !== null) { $out[] = $n; }
        }
        return array_values(array_unique($out));
    }

}
