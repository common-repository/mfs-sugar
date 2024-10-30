<?php

/**
 * Class to manage configuration data
 */
class Config {

    static $data = array(
        "site_url" => '',
        "service_url" => '/service/v4/rest.php',
        "username" => '',
        "password" => ''
    );

    /**
     * 
     * @param type $key
     * @param type $value
     */
    static function set($key, $value) {
        self::$data[$key] = $value;
    }

    /**
     * 
     * @param type $key
     * @return type
     * @throws Exception
     */
    static function get($key) {
        if (self::$data[$key])
            return self::$data[$key];
        throw new Exception('Configuration paramter not found');
    }

    /**
     * 
     * @return type
     */
    static function getURL() {
        return self::$data["site_url"] . self::$data['service_url'];
    }

}

?>