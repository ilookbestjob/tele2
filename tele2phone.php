<?php

require_once "tele2.class.php";
$tele2 = new tele2();

if (!isset($_GET["action"])) {
    echo "отсутвует обязательный параметр action";
    exit;
}


switch ($_GET["action"]) {

    case "get":
        $tele2->getStatistics(isset($_GET['start'])?$_GET['start']:"",isset($_GET['end'])?$_GET['end']:"");
        break;
    case  "updatetokens":
          if ($_GET["access"] != "") {

            $tele2->updatetoken($_GET["access"], "access");
        }

        if ($_GET["refresh"] != "") {
     
            $tele2->updatetoken($_GET["refresh"], "refresh");
        }


        break;
}
