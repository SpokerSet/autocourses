<?php
namespace local_autocourses;

defined('MOODLE_INTERNAL') || die();

/**
 * Провайдер для подключения к внешней БД (MSSQL) и выборки данных.
 */
class externaldbprovider {


    public static function get_connection(){
        $dbconfig = include(__DIR__ . '/../config.php');
        $server   = $dbconfig['server'];
        $connectionOptions = [
            "Database" => $dbconfig['database'],
            "Uid" => $dbconfig['user'],
            "PWD" => $dbconfig['password'],
            "CharacterSet" => "UTF-8"
        ];
        $conn = sqlsrv_connect($server, $connectionOptions);
        if ($conn === false) {
            error_log("MSSQL connect error: ".print_r(sqlsrv_errors(), true));
            throw new \moodle_exception('dbconnect', 'local_autocourses');
        }
        return $conn;
    }

    public static function get_specialities($year) {
        try {
            error_log("Выполняется get_specialities() из: ".__FILE__);
            $conn = self::get_connection();
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
                    where g.УчебныйГод = '{$year}'-- and g.Курс = 1
                ";

            $stmt = sqlsrv_query($conn, $sql);
            if ($stmt === false) {
                throw new \moodle_exception('dbquery', 'local_autocourses', '', null, print_r(sqlsrv_errors(), true));
            }
            $rows = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
            return $rows;
        } catch (\Exception $e) {
            throw new \moodle_exception('dbconnect', 'local_autocourses', '', null, $e->getMessage());
        }

    }

    

}
