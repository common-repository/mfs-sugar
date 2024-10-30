<?php

/**
 * class to handle Curl calls
 */
class REST {

    /**
     * 
     * @param type $method
     * @param type $data
     * @param type $url
     * @param type $curlOpt
     * @return type
     */
    static function doCall($method, $data, $url = '', $curlOpt = array()) {
        if (empty($url))
            $url = Config::getURL();

        ob_start();
        $postData = array(
            'method' => $method,
            'input_type' => 'json',
            'response_type' => 'json',
            'rest_data' => json_encode($data)
        );

        $result = self::curlCall($postData, $url, $curlOpt);
        $result = explode("\r\n\r\n", $result, 2);
        $response = json_decode($result[1]);
        ob_end_flush();
        return $response;
    }

    /**
     * 
     * @param type $postData
     * @param type $url
     * @param type $curlOpt
     * @return type
     */
    static function curlCall($postData, $url, $curlOpt = array()) {
        global $wp_logs;
        $ch = curl_init();
        $headers = (function_exists('getallheaders')) ? getallheaders() : array();
        $_headers = array();
        foreach ($headers as $k => $v) {
            $_headers[strtolower($k)] = $v;
        }
        // set URL and other appropriate options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $_headers);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        foreach ($curlOpt as $key => $value) {
            curl_setopt($ch, $key, $value);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $result = curl_exec($ch);
        if (curl_error($ch)) {
            $error = curl_error($ch);
            $wp_logs::add('Curl Exception', $error." : [".  json_encode($postData)."]", 0, 'error');
            curl_close($ch);
            throw new Exception($error);
        }
        curl_close($ch);

        return $result;
    }

}

?>
