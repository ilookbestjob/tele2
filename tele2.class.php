<?php

use PhpParser\Node\Expr\Array_;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once "../../classes/Debug/debug.class.php";
require_once "../../classes/Servers/servers_class.php";
require_once "../../classes/JSONTOSQL/jsontosql.class.php";



class tele2

{

    private $debug;
    private $servers;
    private $connection;
    private $jsontosql;
    private $table;
    private $fields;
    private $conversion;
    private $uniquefields;


    function __construct()
    {
        $this->debug = new Debug("Отладка модуля tele2");
        $this->servers = new Servers();
        $this->connection = $this->servers->__get("servers")["zod1"]->connection;
        $this->jsontosql = new jsontosql();

        ///////Настройки выгрузки

        //таблица в которую грузим
        $this->table = "zodCdr";
        //соответвие полей json полям sql в формате [поле_sql1=>поле_json1,поле_sql2=>поле_json2,...]
        $this->fields = ["datetime" => "date", "phone" => "destinationNumber", "phone2" => "callerNumber", "duration" => "callDuration", "src" => "callerNumber", "dst" => "destinationNumber", "zodCdrOperator_id" => "", "zodCdrCallType_id" => "callType", "zodCdrStatus_id" => "callStatus", "zod" => ""];
        //call-back функция для форматирования данных в формате [поле_sql1=>[класс, функция]]
        $this->conversion = ["datetime" => [$this, "converttime"], "zodCdrOperator_id" => [$this, "zodCdrOperator_id"], "zodCdrCallType_id" => [$this, "calltype"], "zodCdrStatus_id" => [$this, "callstatus"], "zod" => [$this, "zod"]];
        //массив полейsql для проверки уникальности
        $this->uniquefields = ["datetime", "phone", "phone2"];
    }
    //----------------------------------
    function call($source = "", $destination = "")
    {
        $ch = curl_init();        
        $request = "https://ats2.tele2.ru/crm/openapi/call/outgoing?destination=".$destination."&source=".$source;
        
        $this->debug->addlog("Запрос: " . print_r($request, true));

        curl_setopt($ch, CURLOPT_URL, $request);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $headers = [
            'Accept: application/json',
            'Authorization: ' . $this->gettoken()
        ];

        $this->debug->addlog("Сформированы заголовки: " . print_r($headers, true));

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $server_output = curl_exec($ch);
        curl_close($ch);
        $this->debug->addlog("Ответ: " . print_r($server_output, true));
        $result = json_decode($server_output);        
    }    
    //----------------------------------
    function getStatistics($start = "", $end = "")
    {
        $ch = curl_init();

        if (!$start) $start = date('Y-m-d');
        if (!$end) $end = $start;

        echo $start . "<br>";
        echo $end . "<br>";


        //curl_setopt($ch, CURLOPT_URL,"https://ats2.tele2.ru/crm/openapi/statistics/common?start=2023-12-18T10%3A15%3A30%2B03%3A00&end=2023-12-19T10%3A15%3A30%2B03%3A00&number=79027706580");
        //curl_setopt($ch, CURLOPT_URL,"https://ats2.tele2.ru/crm/openapi/monitoring/calls/pending");

        $request = "https://ats2.tele2.ru/crm/openapi/call-records/info?size=1000&start=$start" . "T00%3A00%3A00%2B03%3A00&end=$end" . "T23%3A59%3A59%2B03%3A00";

        curl_setopt($ch, CURLOPT_URL, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $headers = [
            'Accept: application/json',
            'Authorization: ' . $this->gettoken()
        ];

        $this->debug->addlog("Сформированы заголовки: " . print_r($headers, true));

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $server_output = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($server_output);


        $this->debug->addlog("получен ответ от завпроса $request: " .  $server_output);
        $this->debug->addlog("ответ содержит следующие поля: " . $this->prepareheader($result));

        if (isset($result->message)) {

            if ($result->message == "Forbidden") {

                if (!$this->refreshtokens()) {
                    $this->debug->addlog("ошибка получения новых ключей");
                    echo $this->tokensdialog();
                }
            }



            return false;
        }
        $this->jsontosql->load(
            json_decode($server_output),
            $this->connection,
            $this->table,
            $this->fields,
            $this->conversion,
            $this->uniquefields
        );
        echo __NAMESPACE__ . get_class();
        return json_decode($server_output);
    }
    //------------------------------------
    function tokensdialog()
    {

        $result = '<html><head><link rel="stylesheet" href="css/tokendialog.css" /> <script
        src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
        crossorigin="anonymous"></script><script src="js/tokendialog.js" ></script></head> <body>';
        $result .= '<div class="message">Требуется обновление ключей. Обновите ключи в <a href="https://ats2.tele2.ru">личном кабинете</a> и нажмите записать</div>';
        $result .= '
       <div class="field">
        <div class="titile">access-token</div>
        <textarea id="access-token"></textarea>
      
       </div>';

        $result .= '
       <div class="field">
        <div class="titile">refresh-token</div>
        <textarea id="refresh-token"></textarea>
   
       </div>';

        $result .= '<div class="footer"><div class="button">записать</div></div>';
        $result .= '</body></html>';

        return $result;
    }
    //------------------------------------
    function updatetoken($token, $type = "access")
    {
        file_put_contents($type . ".token.php", "/*" . $token . "*/");
    }
    //------------------------------------
    function gettoken($type = "access")
    {
        return str_replace("/*", "", str_replace("*/", '', file_get_contents($type . ".token.php")));
    }





    //------------------------------------
    function refreshtokens()
    {

        $ch = curl_init();

        $request = "https://ats2.tele2.ru/crm/openapi/authorization/refresh/token";

        curl_setopt($ch, CURLOPT_URL, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $this->gettoken("refresh")

        ];

        $this->debug->addlog("Сформированы заголовки: " . print_r($headers, true));

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $server_output = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($server_output);

        $this->debug->addlog("получен ответ от завпроса $request: " .  print_r($result, true));
        if (isset($result->accessToken) && isset($result->refreshToken)) {
            $this->updatetoken($result->accessToken);
            $this->updatetoken($result->refreshToken, "refresh");


            return true;
        }
        return false;
    }
    //------------------------------------
    function converttime($value)
    {

        $return = substr($value, 0, 10) . " " . substr($value, 11, 8);
        echo "$value converted to $return";
        return $return;
    }
    //------------------------------------
    function zodCdrOperator_id($value = "")
    {

        return 1;
    }
    //------------------------------------
    function prepareheader($json)
    {

        $fields = [];
        $table = "<table><tr>";
        foreach ($json as $jsondata) {
            foreach ($jsondata as $jsonfields => $jsonfieldsdata) {

                if (!in_array($jsonfields, $fields)) $fields[] = $jsonfields;
            }
        }

        foreach ($fields as $header) {
            $table .= "<td>$header</td>";
        }
        $table .= "</tr>";

        foreach ($json as $jsondata) {
            $tempfields = array(count($fields));
            for ($t = 0; $t < count($fields); $t++) $tempfields[$t] = "";
            foreach ($jsondata as $jsonfields => $jsonfieldsdata) {
                $tempfields[array_search($jsonfields, $fields)] = $jsonfieldsdata;
            }
            $table .= "<tr>";

            foreach ($tempfields as $tempfield) {
                $table .= "<td>" . ($tempfield != '' ? $tempfield : "&nbsp") . "</td>";
            }
            $table .= "</tr>";
        }

        $table .= "</table>";
        return $table;
    }

    //------------------------------------
    function calltype($value = "")
    {

        return strpos($value, "OUTGOING") === false ? 1: 3;
    }


    //------------------------------------
    function callstatus($value = "")
    {

        echo "Значение: $value <br> ";

        echo "NOT_ANSWERED_ :" . (strpos($value, "NOT_ANSWERED_")) . "<br>";
        echo "CANCELLED_ :" . (strpos($value, "CANCELLED_")) . "<br>";

        echo "ANSWERED :" . strpos($value, "ANSWERED_") . "<br>";
        echo "BUSY :" . strpos($value, "BUSY") . "<br>";
        echo "DELETED :" . strpos($value, "DELETED_") . "<br>";

        if (strpos($value, "NOT_ANSWERED_") !== false) {
            return 4;
        }
        if (strpos($value, "CANCELLED_") !== false) {
            return 4;
        }
        if (strpos($value, "ANSWERED_") !== false) {
            return 1;
        }
        if (strpos($value, "BUSY") !== false) {
            return 2;
        }
        if (strpos($value, "DENIED_") !== false) {
            return 3;
        }
    }

    //------------------------------------
    function zod($value="")
    {
        return 1;
    }
}
