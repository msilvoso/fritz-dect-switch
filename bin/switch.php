#!/usr/bin/php
<?php
chdir(dirname(__FILE__));
require "fritz/fritz.php";
require "fritz/fritzconf.php";
$fritzbox = new fritzbox($fritzAddr, $fritzUser, $fritzPass);
if ($argc == 2) {
    switch($argv[1]) {
        case 'getswitchpower':
            echo $fritzbox->getSwitchPower($ain);
            break;
        case 'getswitchstate':
            echo $fritzbox->getSwitchState($ain);
            break;
        case 'setswitchoff':
            echo $fritzbox->setSwitchOff($ain);
            break;
        case 'setswitchon':
            echo $fritzbox->setSwitchOn($ain);
            break;
    }
}
$fritzbox->logout();
