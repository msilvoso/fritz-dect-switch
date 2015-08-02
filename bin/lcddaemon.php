#!/usr/bin/php5
<?php
if (!isset($argv[1]) || $argv[1] != '--debug') {
    $pid = pcntl_fork();

    if ($pid == -1) {
        exit("Could not fork child process");
    }

    if ($pid) {
        // parent
        exit(0);
    }

    if (posix_setsid() === -1) {
        exit("Could not become the session leader");
    }

    // create grand child 
    $pid = pcntl_fork();

    if ($pid == -1) {
        exit("Could not fork grandchild process");
    }

    if ($pid) {
        // child (parent)
        exit(0);
    }

    if (!fclose(STDIN)) { exit('Could not close STDIN'); }
    if (!fclose(STDERR)) { exit('Could not close STDERR'); }
    if (!fclose(STDOUT)) { exit('Could not close STDOUT'); }

    // recreate standard streams
    $STDIN  = fopen('/dev/null', 'r');
    $STDOUT = fopen('/dev/null', 'w');
    $STDERR = fopen('/var/log/lcddaemon.log', 'a');
}
// change directory to current
chdir(dirname(__FILE__));
require "fritz/fritz.php";
require "fritz/fritzconf.php";
require "lcd/lcdmenu.php";
// open pipe to lcd.py
$descriptorspec = array(
    0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
    1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
    2 => array("file", "/tmp/lcderror.txt", "w") // stderr is a file to write to
);
$handler = proc_open('/usr/local/lib/lcd/lcd.py', $descriptorspec, $pipes);
if ($handler === false) { exit('Could not open pipe to lcd.py'); }

// loop
$fritzbox   = new fritzbox($fritzAddr, $fritzUser, $fritzPass, false);
$lcdmenu    = new lcdmenu($fritzbox, $ain);
$stayInLoop = true;
$count      = 0;
$light      = true;
$showState  = false;
$pointer    = -1;
$return     = 0;
$lastbutton = 'NONE';

while($stayInLoop) {
    fwrite($pipes[0], "CHECK\n");
    $return = trim(fgets($pipes[1]));
    // main
    if ($return !== 'NONE' && $lastbutton !== $return) {
        if (!$light) { //show status
            $lcdmenu->refresh();
            if ($lcdmenu->getSwitchState() == '1') {
                fwrite($pipes[0], "State_:_ON@Power_".$lcdmenu->getSwitchPower()." GREEN\n");
            } else {
                fwrite($pipes[0], "State_:_OFF RED\n");
            }
            $showState = true;
        } elseif ($return == 'DOWN') {
            if (!$showState) {
                $lcdmenu->incPointer();
            }
            fwrite($pipes[0], $lcdmenu->getDisplay());
            $showState = false;
        } elseif ($return == 'UP') {
            if (!$showState) {
                $lcdmenu->decPointer();
            }
            fwrite($pipes[0], $lcdmenu->getDisplay());
            $showState = false;
        } elseif ($return == 'SELECT') {
            if (!$showState) {
                fwrite($pipes[0], $lcdmenu->execute()." WHITE\n");
            } else {
                fwrite($pipes[0], $lcdmenu->getDisplay());
                $showState = false;
            }
        }/*
        } elseif ($return == 'LEFT') {
        } elseif ($return == 'RIGHT') {*/
        $count = 0;
        $light = true;
    }
    $lastbutton = $return;
    // screensaver
    if ($light) {
        $count++;
        if ($count > 20) {
            fwrite($pipes[0], "OFF\n");
            $count = 0;
            $light = false;
            $showState = false;
        }
    }
    usleep(250000);
}
fclose($pipes[0]);
fclose($pipes[1]);
proc_close($handler);
