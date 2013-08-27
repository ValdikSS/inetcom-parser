<?php

/**
 * Class InetcomException
 */
class InetcomException extends Exception
{
}


/**
 * Class Inetcom
 *
 * Get InetCom playlist with or without SID
 *
 */
class Inetcom
{
    protected $debug = FALSE, $logged = FALSE;
    protected $login, $password;
    protected $curl;
    public $ssid, $jsonlist = 'tvchannels.json';

    const loginurl = 'http://inetcom.tv/auth/login';
    const siteurl = 'http://inetcom.tv/';
    const baseurl = 'http://inetcom.tv';
    const packageurl = 'http://inetcom.tv/tarif/index/i/';


    /**
     * This function prints debug messages if enabled.
     * @param $message
     */
    protected function inetcom_debug($message)
    {
        if ($this->debug) {
            echo $message . PHP_EOL;
        }
    }


    /**
     * Inetcom Authorization
     *
     * @param bool $login
     * @param bool $password
     * @throws InetcomException
     */
    public function login($login = FALSE, $password = FALSE)
    {
        if (!($this->login && $this->password)) {
            $this->login = $login;
            $this->password = $password;
        }
        if (!($this->login && $this->password)) {
            throw new InetcomException("No login or password supplied.");
        }

        $loginpostdata = urlencode('LoginForm[username]') .
            '=' . urlencode($this->login) . '&' .
            urlencode('LoginForm[password]') . '=' . urlencode($this->password);

        # We need to get PHPSESSID first
        curl_setopt($this->curl, CURLOPT_URL, self::siteurl);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($this->curl, CURLOPT_COOKIEFILE, '');
        curl_setopt($this->curl, CURLOPT_HEADER, FALSE);
        curl_exec($this->curl);

        $this->inetcom_debug("Trying to log in...");
        curl_setopt($this->curl, CURLOPT_URL, self::loginurl);
        curl_setopt($this->curl, CURLOPT_POST, TRUE);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $loginpostdata);
        $response = curl_exec($this->curl);

        if ($response) {
            throw new InetcomException("Login error!");
        }
        $this->logged = TRUE;
        $this->inetcom_debug("Logged in!");
    }


    /**
     * Get Inetcom playlist without SID
     * Packages:
     * 6 = Эфирный+
     * 2 = Базовый
     * 7 = HD каналы
     *
     * @param int $packageid
     * @throws InetcomException
     * @return array Array of TV channels and URLs
     */
    public function getchanlist($packageid = 6)
    {
        if (!$this->logged) {
            throw new InetcomException("You should be logged in first!");
        }

        curl_setopt($this->curl, CURLOPT_URL, self::packageurl . $packageid);
        curl_setopt($this->curl, CURLOPT_POST, FALSE);
        $response = curl_exec($this->curl);

        $html = phpQuery::newDocumentHTML($response);
        $elements = $html->find('.col02 > a');

        $tvchannels = array();

        # Get channels list, strip ssid and save to json file
        if (!file_exists($this->jsonlist)) {
            foreach ($elements as $el) {
                curl_setopt($this->curl, CURLOPT_URL, self::baseurl . pq($el)->attr('href'));
                $tvchan = curl_exec($this->curl);
                $tvtitle = pq($el)->text();
                $url = preg_match("/lnks = \[\'(.+)\/\?/", $tvchan, $matches);
                $tvchannels[$tvtitle] = $matches[1] . "/";
                $this->inetcom_debug("Added $tvtitle $matches[1]");
            }
            file_put_contents($this->jsonlist, json_encode($tvchannels));
            $this->inetcom_debug("Json list saved.");
        }
        else {
            $tvchannels = json_decode(file_get_contents('tvchannels.json'), true);
            $this->inetcom_debug("Json list loaded.");

            # Getting ssid
            curl_setopt($this->curl, CURLOPT_URL, self::baseurl . pq($elements[0])->attr('href'));
            $tvchan = curl_exec($this->curl);
        }

        preg_match('/\?sid\=(.+)\'/', $tvchan, $matches);
        $this->ssid = $matches[1];

        $this->inetcom_debug("Got ssid ".$this->ssid);

        return $tvchannels;
    }


    /**
     * Get Inetcom playlist with SID
     *
     * @param int $packageid
     * @throws InetcomException
     * @return array Array of TV channels with SID
     */
    public function getfulllist($packageid = 6)
    {
        $list = $this->getchanlist($packageid);
        $callback = function($value) {
            return $value."?sid=".$this->ssid;
        };

        return array_map($callback, $list);
    }

    public function __construct($login = FALSE, $password = FALSE, $debug = FALSE)
    {
        $this->debug = $debug;
        $this->login = $login;
        $this->password = $password;
        $this->inetcom_debug("Inetcom parser initialized.");
        $this->curl = curl_init();
        require_once("phpQuery-onefile.php");
    }
}