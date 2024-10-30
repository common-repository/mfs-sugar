<?php

/**
 * class to handle user login and session data
 */
class User {

    private static $_cache = false;

    /**
     * 
     * @return type
     */
    static function doLogin() {
        if (!empty(self::$_cache)) {
            return self::$_cache->id;
        }

        $result = REST::doCall('login', array(
                    'user_auth' => array(
                        'user_name' => Config::get("username"),
                        'password' => md5(Config::get("password")),
                        'version' => '.01'
                    ),
                    'application_name' => 'RestClient',
                    'name_value_list' => array(
                        array(
                            'name' => 'notifyonsave',
                            'value' => 'false'
                        )
                    )
                ));
        if ($result->name == "Invalid Login")
            throw new Exception($result->description);
        self::$_cache = $result;
        return $result->id;
    }

    /**
     * 
     * @return type
     */
    static function getSessionId() {
        return self::$_cache->id;
    }

}

?>
