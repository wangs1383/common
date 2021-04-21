<?php

namespace Wangs1383;

/**
 * 微信相关方法
 */
class Wx
{
    // 微信接口域名
    private $domain = 'https://api.weixin.qq.com/';
    // 公众号id
    private $appId;
    // 公众号秘钥
    private $appSecret;
    // 保存token文件
    private $file;

    public function __construct($app_id, $app_secret, $dir = '')
    {
        $this->appId = $app_id;
        $this->appSecret = $app_secret;

        $dir = empty($dir) ? __DIR__ . '/wx_file' : rtrim($dir, '/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $this->file = $dir . '/' . $this->appId . '.php';
        if (!file_exists($this->file)) {
            file_put_contents($this->file, '');
        }
    }

    /**
     * 获取微信凭证
     *
     * @param string $name access_toekn jsapi_ticket api_ticket其中之一
     * @return string
     * @throws Exception
     */
    public function getToken($name)
    {
        if (isset($_SESSION[$this->appId][$name])) {
            return $_SESSION[$this->appId][$name];
        }

        $token = $this->getTokenFromFile($name);
        if ($token) {
            return $token;
        }

        return $this->getTokenFromWx($name);
    }

    private function getTokenFromFile($name)
    {
        $data = file_get_contents($this->file);
        $data = json_decode($data, true);

        if (isset($data[$name]) && $data[$name]['time'] > time() - 7000) {
            return $data[$name]['value'];
        }

        return '';
    }

    private function getTokenFromWx($name)
    {
        if ($name == 'access_token') {
            $url = $this->domain . "cgi-bin/token";
            $query = [
                'grant_type' => 'client_credential',
                'appid' => $this->appId,
                'secret' => $this->appSecret,
            ];
            $rtn_name = 'access_token';
        } elseif ($name == 'jsapi_ticket') {
            $url = $this->domain . "cgi-bin/ticket/getticket";
            $query = [
                'access_token' => $this->getToken('access_token'),
                'type' => 'jsapi',
            ];
            $rtn_name = 'ticket';
        } elseif ($name == 'api_ticket') {
            $url = $this->domain . "cgi-bin/ticket/getticket";
            $query = [
                'access_token' => $this->getToken('access_token'),
                'type' => 'wx_card',
            ];
            $rtn_name = 'ticket';
        } else {
            throw new \Exception('Name(' . $name . ')有误，', 101);
        }

        $res = send_request('GET', $url, $query);

        if (isset($res['errcode']) && $res['errcode'] != 0) {
            throw new \Exception('微信获取' . $name . '失败，' . json_encode($res), 102);
        }

        $this->setToken($name, $res[$rtn_name]);

        return $res[$rtn_name];
    }

    private function setToken($name, $value)
    {
        $data = file_get_contents($this->file);
        $data = json_decode($data, true);

        if (is_null($data)) {
            $data = [];
        }

        $data[$name] = [
            'value' => $value,
            'time' => time(),
        ];

        file_put_contents($this->file, json_encode($data));
    }

    /**
     * 获取jssdk参数
     *
     * @return array
     */
    public function getJssdk($debug = false)
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $jssdk = [
            'noncestr' => get_random(),
            'timestamp' => time(),
            'jsapi_ticket' => $this->getToken('jsapi_ticket'),
            'url' => $url,
        ];
        $jssdk['signature'] = $this->getSignature($jssdk);
        $jssdk['appId'] = $this->appId;
        $jssdk['debug'] = $debug;

        return $jssdk;
    }

    /**
     * 生成签名
     *
     * @param array $array
     * @param integer $type
     * @return string
     */
    private function getSignature($array, $type = 1)
    {
        if ($type == 1) {
            ksort($array);
        } elseif ($type == 2) {
            sort($array, SORT_STRING);
        }

        $string = '';
        foreach ($array as $k => $v) {
            if ($type == 1) {
                $string .= $k . '=' . $v . '&';
            } elseif ($type == 2) {
                $string .= $v;
            }
        }
        $string = rtrim($string, '&');

        return sha1($string);
    }

    /**
     * 获取code
     *
     * @param string $state
     * @param string $redirect_uri
     * @return 重定向
     */
    public function getOpenId($state = '', $redirect_uri = '')
    {
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appId}&response_type=code&redirect_uri={$redirect_uri}&state={$state}&scope=snsapi_userinfo#wechat_redirect";
        header("Location: " . $url);
        exit;
    }

    /**
     * 通过code获取openid
     *
     * @param string $code
     * @return array|Exception
     */
    public function getOpenIdRedirect($code)
    {
        if (empty($code)) {
            throw new \Exception('获取openid失败', 101);
        }
        $url = $this->domain . "sns/oauth2/access_token";
        $query = [
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code'
        ];
        $res = send_request('GET', $url, $query);

        $res = is_array($res) ? $res : json_decode($res, true);

        if (isset($res['errcode']) && $res['errcode'] != 0) {
            // 获取openid失败
            throw new \Exception('获取openid失败', 101);
        }

        return $res;
    }

    /**
     * 拉取用户信息
     *
     * @param string $openid
     * @param array $oauth2
     * @return array
     */
    public function getUserInfo($openid, $oauth2)
    {
        if (intval($oauth2['add_time']) + 7000 > time()) {
            // 网页授权access_token过期了
        }

        $url = $this->domain . 'sns/userinfo';
        $query = [
            'openid' => $openid,
            'access_token' => $oauth2['access_token'],
            'lang' => 'zh_CN',
        ];
        $res = send_request('GET', $url, $query);

        $res = is_array($res) ? $res : json_decode($res, true);

        if (isset($res['errcode']) && $res['errcode'] != 0) {
            // 获取openid失败
            throw new \Exception('获取userinfo失败', 101);
        }

        return $res;
    }

    /**
     * 获取微信素材
     *
     * @param string|integer $media_id
     * @return string 保存的文件名
     */
    public function getMedia($media_id)
    {
        if (empty($media_id)) {
            throw new \Exception('媒体文件ID有误', 101);
        }

        $url = $this->domain . 'cgi-bin/media/get';
        $query = [
            'access_token' => $this->getToken('access_token'),
            'media_id' => $media_id,
        ];
        $res = send_request('GET', $url, $query, [], true);

        if (!isset($res['header'])) {
            throw new \Exception('从微信端获取有误，请稍后再试', 102);
        }

        // 根据响应头部创建本地文件类型
        if ($res['header']['Content-Type'][0] == 'image/jpeg') {
            $fn = 'jpg';
        } elseif ($res['header']['Content-Type'][0] == 'image/gif') {
            $fn = 'gif';
        } elseif ($res['header']['Content-Type'][0] == 'image/png') {
            $fn = 'png';
        } else {
            throw new \Exception('不支持的文件类型：' . $res['header']['Content-Type'][0], 103);
        }

        // 保存到文件
        $file_name = './upload/' . date('Ym', time()) . '/' . date('dHis', time()) . rand(10000, 99999) . '.' . $fn;
        if (is_file($file_name)) {
            $file_name = './upload/' . date('Ym', time()) . '/' . date('dHis', time()) . rand(10000, 99999) . '.' . $fn;
        }
        file_put_contents($file_name, $res['body']);

        return $file_name;
    }

    /**
     * 创建自定义菜单
     *
     * @param array $data
     * @return string|true
     */
    public function createMenu(array $data)
    {
        $url = $this->domain . 'cgi-bin/menu/create?access_token=' . $this->getToken('access_token');

        $res = send_request('POST', $url, json_encode($data, JSON_UNESCAPED_UNICODE));
        $res = is_array($res) ? $res : json_decode($res, true);

        if (isset($res['errcode']) && $res['errcode'] != 0) {
            return '自定义菜单失败，' . $res['errmsg'] ?? '';
        }

        return true;
    }

    public function sendMessage($openid, $template_id, $data)
    {
        $access_token = $this->getToken('access_token');
        $url = $this->domain . 'cgi-bin/message/template/send?access_token=' . $access_token;

        $res = send_request('POST', $url, [
            'touser' => $openid,
            'template_id' => $template_id,
            'data' => $data,
        ], [], true);

        return $res;
    }
}
