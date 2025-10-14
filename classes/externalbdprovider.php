<?php
namespace local_autocourses;

defined('MOODLE_INTERNAL') || die();

/**
 * Провайдер для подключения к внешней БД (MSSQL) и выборки данных.
 */
class externaldbprovider {

    /** @var \PDO|null */
    private static $pdo = null;

    /**
     * Получить подключение PDO к MSSQL.
     * @return \PDO
     * @throws \Exception
     */
    private static function get_connection(): \PDO {
        global $AUT0COURSES_DB;

        if (self::$pdo !== null) {
            return self::$pdo;
        }

        if (empty($AUT0COURSES_DB) || !is_array($AUT0COURSES_DB)) {
            throw new \Exception('Конфигурация AUT0COURSES_DB не найдена');
        }

        $server   = $AUT0COURSES_DB['server']   ?? 'localhost';
        $port     = $AUT0COURSES_DB['port']     ?? 1433;
        $dbname   = $AUT0COURSES_DB['database'] ?? '';
        $user     = $AUT0COURSES_DB['user']     ?? '';
        $password = $AUT0COURSES_DB['password'] ?? '';

        $dsn = "sqlsrv:Server={$server},{$port};Database={$dbname}";

        try {
            self::$pdo = new \PDO($dsn, $user, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => 5
            ]);
        } catch (\PDOException $e) {
            throw new \Exception("Ошибка подключения к MSSQL: " . $e->getMessage());
        }

        return self::$pdo;
    }

    /**
     * Получить список групп.
     * @return array
     */
    public static function fetch_groups(): array {
        $pdo = self::get_connection();
        $sql = "SELECT distinct
                f.Факультет AS [Faculty],
                k.Название AS [Department],
                sp.Специальность AS [SpecialityCode],
                sp.Название_Спец AS [Speciality],
                g.Название AS [Group],
                JSON_QUERY(g.uplan, '$.plan') AS [Plan],
                g.uplan
                from Все_Группы as g 
                inner join Специальности AS sp on g.Код_Специальности = sp.Код
                inner join Кафедры AS k ON sp.Код_Кафедры = k.Код
                inner join Факультеты AS f on k.Код_Факультета = f.Код
                where g.УчебныйГод = '2025-2026' -- and g.Курс = 1 ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    }

}
