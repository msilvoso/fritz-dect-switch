<?php

require_once "fritz/fritz.php";

class lcdmenu
{
    const CRONFILE = '/etc/cron.d/switch';
    protected $fritzConf;
    protected $switchState;
    protected $switchPower;
    protected $menu;
    protected $main = [
                        [ 'Firewall_is_off@Switch_ON?', 'RED', 'switchOn', false ],
                        [ 'AutoOff_enabled@Disable?', 'BLUE', 'toggleCron', false ]
                    ];
    protected $alt = [
                        0 => [ 'Firewall_is_on@Switch_OFF?', 'GREEN', 'switchOff', false ],
                        1 => [ 'AutoOff_disabled@Enable?', 'VIOLET', 'toggleCron', false ]
                    ];
    protected $pointer = 0;

    protected $cronjob; // array of all lines in crontab
    protected $line; // number of the line containing our job

    public function __construct() 
    {
        require "fritz/fritzconf.php";
        $this->fritzConf = [ 'ain' => $ain, 'fritzAddr' => $fritzAddr, 'fritzUser' => $fritzUser, 'fritzPass' => $fritzPass ];
    }

    public function refresh() 
    {
        $this->menu = $this->main;
        // check if switch on or off
        $fritzbox = new fritzbox($this->fritzConf['fritzAddr'], $this->fritzConf['fritzUser'], $this->fritzConf['fritzPass']);
        $this->switchState = trim($fritzbox->getSwitchState($this->fritzConf['ain']));
        if ($this->switchState === '1') {
            $this->menu[0] = $this->alt[0];
        }
        $this->switchPower = trim($fritzbox->getSwitchPower($this->fritzConf['ain']));
        $fritzbox->logout();
        // check cron job
        $cronjob          = file_get_contents(self::CRONFILE);
        $this->cronjob    = explode("\n", $cronjob);
        foreach($this->cronjob as $key => $line) {
            if (strstr($line, 'shutdownfw')) {
                $this->line = $key;
                break;
            }
        }
        if (substr($this->cronjob[$this->line],0,1) == '#') {
            $this->menu[1] = $this->alt[1];
        }
    }

    public function getSwitchState() 
    {
        return $this->switchState;
    }
    
    public function getSwitchPower() 
    {
        return $this->switchPower;
    }

    protected function cronReload() 
    {
        exec('/etc/init.d/cron reload');
    }

    public function incPointer() 
    {
        $this->pointer = ($this->pointer + 1) % count($this->menu);
        return $this->pointer;
    }

    public function decPointer() 
    {
        $this->pointer = ($this->pointer - 1 + count($this->menu)) % count($this->menu);
        return $this->pointer;
    }

    public function getDisplay() 
    {
        return $this->menu[$this->pointer][0].' '.$this->menu[$this->pointer][1]."\n";
    }

    public function execute() 
    {
        return call_user_func([$this, $this->menu[$this->pointer][2]]);
    }

    public function toggleCron() 
    {
        if (substr($this->cronjob[$this->line],0,1) == '#') {
            $this->cronjob[$this->line] = substr($this->cronjob[$this->line],1);
            $return = 'AutoOff_enabled';
        } else {
            $this->cronjob[$this->line] = '#'.$this->cronjob[$this->line];
            $return = 'AutoOff_disabled';
        }
        file_put_contents(self::CRONFILE, implode("\n", $this->cronjob));
        $this->cronReload();
        $this->refresh();
        return $return;
    }

    public function switchOn()
    {
        $fritzbox = new fritzbox($this->fritzConf['fritzAddr'], $this->fritzConf['fritzUser'], $this->fritzConf['fritzPass']);
        $fritzbox->setSwitchOn($this->fritzConf['ain']);
        $fritzbox->logout();
        sleep(2);
        $this->refresh();
        return "Starting_up";   
    }

    public function switchOff()
    {
        $pid = pcntl_fork();
        if ($pid !== -1) {
            if ( $pid === 0 ) {
                pcntl_exec('/usr/local/bin/shutdownfw');//ssh halt + switch off
                exit(0);
            }
        } else {
            return "Problem_executing";
        }
        return "Shutdown_called@OFF_in_2_min";   
    }
}
