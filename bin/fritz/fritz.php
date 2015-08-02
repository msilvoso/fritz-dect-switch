<?php

class fritzbox
{
    const LOGIN="login_sid.lua";
    const WEBSERVICES="webservices/homeautoswitch.lua?switchcmd=";

    protected $username;
    protected $password;
    protected $fritzboxUrl;
    protected $ssl=false;
    protected $sessionId=false;

    public function __construct($url, $username, $password, $login = true) 
    {
        $this->fritzboxUrl = $url;
        $this->username = $username;
        $this->password = $password;
        if ($login) {
            $this->SID = $this->getSessionID();
        }
    }

    public function login()
    {
        $this->SID = $this->getSessionID();
        return $this;
    }

    public function getSwitchList() 
    {
        $url = $this->buildUrl()."/".fritzbox::WEBSERVICES."getswitchlist&sid=".$this->SID;
        return $this->doGetRequest($url);
    }

    public function getSwitchState($ain) 
    {
        $url = $this->buildUrl()."/".fritzbox::WEBSERVICES."getswitchstate&ain=$ain&sid=".$this->SID;
        return $this->doGetRequest($url);
    }

    public function setSwitchOff($ain) 
    {
        $url = $this->buildUrl()."/".fritzbox::WEBSERVICES."setswitchoff&ain=$ain&sid=".$this->SID;
        return $this->doGetRequest($url);
    }

    public function setSwitchOn($ain) 
    {
        $url = $this->buildUrl()."/".fritzbox::WEBSERVICES."setswitchon&ain=$ain&sid=".$this->SID;
        return $this->doGetRequest($url);
    }

    public function getSwitchPower($ain) 
    {
        $url = $this->buildUrl()."/".fritzbox::WEBSERVICES."getswitchpower&ain=$ain&sid=".$this->SID;
        return $this->doGetRequest($url);
    }

    public function logout() 
    {
        $url = $this->buildUrl()."/".fritzbox::LOGIN."?logout=1&sid=".$this->SID;
        return $this->doGetRequest($url);
    }

    public function getChallenge() 
    {
        $url = $this->buildUrl()."/".fritzbox::LOGIN;
        $content = $this->doGetRequest($url);
        return $this->getValueFromXML('CHALLENGE',$content);
    }

    public function getSessionID() 
    {
        $challenge = $this->getChallenge();
        $response = "$challenge-".md5(mb_convert_encoding($challenge . '-' . $this->password, "UCS-2LE", "UTF-8"));
        $url = $this->buildUrl()."/".fritzbox::LOGIN."?username=".$this->username."&response=".$response;
        $content = $this->doGetRequest($url);
        return $this->getValueFromXML('SID',$content);
    }

    protected function buildUrl() 
    {
        if ($this->ssl) {
            $url="https://".$this->fritzboxUrl;
        } else {
            $url="http://".$this->fritzboxUrl;
        }
        return $url;
    }

    protected function getValueFromXML($name, $content) 
    {
        $parser = xml_parser_create();
        $values = array();
        $index = array();
        xml_parse_into_struct($parser, $content, $values, $index);
        xml_parser_free($parser);
        return $values[$index[$name][0]]['value'];
    }

    protected function doGetRequest($url) 
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        if ( $this->ssl ) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        //print_r(curl_getinfo($ch)); //DEBUG
        curl_close($ch);
        return $output;
    }
}
