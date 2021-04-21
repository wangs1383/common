<?php

use GuzzleHttp\Client as HttpClient;

if (!function_exists('get_random')) {
    /**
     * 获取随机字符串
     * @param integer $length 长度
     * @param string $type 0不包含0的数字 1字母 2符号 3数字+字母 4数字+字母+符号
     * @return string 随机字符串
     */
    function get_random($length = 6, $type = 0)
    {
        $config = [
            'number' => [
                "0", "1", "2", "3", "4",
                "5", "6", "7", "8", "9",
            ],
            'letter' => [
                "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
                "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
                "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
                "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
                "S", "T", "U", "V", "W", "X", "Y", "Z",
            ],
            'symbol' => [
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", ".",
            ],
        ];

        if ($type == 0) {
            // 不包括0的数字
            $string = $config['number'];
            unset($string[0]);
        } elseif ($type == 1) {
            // 字母
            $string = $config['letter'];
        } elseif ($type == 2) {
            // 符号
            $string = $config['symbol'];
        } elseif ($type == 3) {
            // 数字+字母
            $string = array_merge($config['number'], $config['letter']);
        } elseif ($type == 4) {
            // 数字+字母+符号
            $string = array_merge($config['number'], $config['letter'], $config['symbol']);
        } else {
            $string = $config['number'];
        }
        //打乱数组顺序
        shuffle($string);

        $str = '';
        $strlen = count($string) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $string[mt_rand(0, $strlen)];
        }

        return $str;
    }
}

if (!function_exists('send_request')) {
    /**
     * 发送请求
     *
     * @param string $method GET或POST
     * @param string $url 请求地址
     * @param array $data 发送数据
     * @param array $options 请求选项
     * @param boolean $is_json 是否为json请求
     * @param boolean $resFormat 返回 格式是否处理
     * @return array
     */
    function send_request($method, $url, $data = [], $options = [], $is_json = false, $resFormat = true)
    {
        if ($is_json) {
            $options['json'] = $data;
        } else {
            $options['form_params'] = $data;
        }
        $options['verify'] = false;

        $client = new HttpClient();
        $response = $client->request($method, $url, $options);

        $res = $response->getBody()->getContents();

        if ($resFormat && is_string($res)) {
            $res = json_decode($res, true);
        }

        return $res;
    }
}
