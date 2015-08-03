<?php

class lcdmenu
{
    const CRONFILE = '/etc/cron.d/switch';

    protected $fritzbox;
    protected $ain;
    protected $switchState;
    protected $switchPower;

    protected $menu;
    protected $main = [
                        [ 'Firewall_is_off@Switch_ON?', 'RED', 'switchOn', false ],
                        [ 'AutoOff_enabled@Disable?', 'BLUE', 'toggleCron', false ],
                        [ 'Switch_off_at@XXXX', 'TEAL', 'setCron', 'changeCron' ]
                    ];
    protected $alt = [
                        0 => [ 'Firewall_is_on@Switch_OFF?', 'GREEN', 'switchOff', false ],
                        1 => [ 'AutoOff_disabled@Enable?', 'VIOLET', 'toggleCron', false ]
                    ];
    protected $pointer = 0;

    protected $cronJob; // array of all lines in crontab
    protected $line; // number of the line containing our job
    protected $cronTime = [];
    protected $cronDisabled;

    public function __construct($fritzbox, $ain) 
    {
        $this->fritzbox = $fritzbox;
        $this->ain = $ain;
    }

    public function refresh() 
    {
        $this->menu = $this->main;
        
        // check if switch on or off
        $this->fritzbox->login();
        $this->switchState = trim($this->fritzbox->getSwitchState($this->ain));
        if ($this->switchState === '1') {
            $this->menu[0] = $this->alt[0];
        }
        $this->switchPower = trim($this->fritzbox->getSwitchPower($this->ain));
        $this->fritzbox->logout();
        
        // check cron job
        $cronJob          = file_get_contents(self::CRONFILE);
        $this->cronJob    = explode("\n", $cronJob);
        foreach($this->cronJob as $key => $line) {
            if (strstr($line, 'shutdownfw')) {
                $this->line = $key;
                break;
            }
        }
        if (substr($this->cronJob[$this->line],0,1) === '#') {
            $this->cronDisabled = true;
            $this->menu[1] = $this->alt[1];
        } else {
            $this->cronDisabled = false;
        }
        
        // check time to shutdown
        $explodedLine = explode(' ', str_replace("\t", ' ', $line));
        $explodedLine[0] = str_replace('#', '', $explodedLine[0]); // remove possible '#'
        if ((int) $explodedLine[0] <10) $explodedLine[0] = '0'.$explodedLine[0];
        $this->cronTime = [ 'hour' => $explodedLine[1], 'minute' => $explodedLine[0] ];
        $this->menu[2][0] = str_replace('XXXX', implode(':', $this->cronTime), $this->menu[2][0]);
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

    public function rlbuttons($button) // right and left buttons
    {
        if ($this->menu[$this->pointer][3] !== false && ($button === 'LEFT' || $button === 'RIGHT')) {
            return call_user_func([$this, $this->menu[$this->pointer][3]], $button);
        }
        return false;
    }

    /*
     * Methods called on every menu point
     */ 

    public function toggleCron() 
    {
        if (substr($this->cronJob[$this->line],0,1) == '#') {
            $this->cronJob[$this->line] = substr($this->cronJob[$this->line],1);
            $return = 'AutoOff_enabled';
        } else {
            $this->cronJob[$this->line] = '#'.$this->cronJob[$this->line];
            $return = 'AutoOff_disabled';
        }
        file_put_contents(self::CRONFILE, implode("\n", $this->cronJob));
        $this->cronReload();
        $this->refresh();
        return $return;
    }

    public function switchOn()
    {
        $this->fritzbox->login()->setSwitchOn($this->ain);
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
        return "Shutdown_now@OFF_in_5_min";   
    }

    protected function changeCron($button)
    {
        if ($button === 'RIGHT') {
            $this->cronTime['minute'] = ((int) $this->cronTime['minute'] + 10) % 60;
            if ((int) $this->cronTime['minute'] === 0) {
                $this->cronTime['hour'] = ((int) $this->cronTime['hour'] + 1) % 24;
                $this->cronTime['minute'] .= '0';
            }
        } else {
            if ((int) $this->cronTime['minute'] === 0) $this->cronTime['hour'] = ((int) $this->cronTime['hour'] + 23) % 24;
            $this->cronTime['minute'] = ((int) $this->cronTime['minute'] + 50) % 60;
            if ((int) $this->cronTime['minute'] === 0) $this->cronTime['minute'] .= '0';
        }
        echo str_replace('XXXX', implode(':', $this->cronTime), $this->main[2][0])." YELLOW\n";
        return str_replace('XXXX', implode(':', $this->cronTime), $this->main[2][0])." YELLOW\n";
    }

    protected function setCron()
    {
        $this->cronJob[$this->line]   = ($this->cronDisabled ? '#' : '').
                                        (int) $this->cronTime['minute'].' '.
                                        (int) $this->cronTime['hour'].
                                        preg_replace('/^#?\d*\s*\d*/', '', $this->cronJob[$this->line]);
        file_put_contents(self::CRONFILE, implode("\n", $this->cronJob));
        $this->cronReload();
        $this->refresh();
        return 'Shutdown_time@changed';
    }
}
