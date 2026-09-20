<?php
// 应用公共文件
use app\common\model\AdminLog;


$stream_opts = [
    "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false,
    ]
    
];
stream_context_get_default($stream_opts);


/**
 * 生成随机字符串
 * @param int $num 字符个数
 * @return string
 */
function getRandStr($num = 6)
{
    $char = 'qwertyuiopasdfghjklzxcvbnm1234567890QAZXSWEDCVFRTGBNHYUJMKIOP';
    $str = '';
    $char_length = strlen($char) - 1;
    for ($i = 0; $i < $num; $i++) {
        $str .= $char[mt_rand(0, $char_length)];
    }
    return $str;
}

//生成订单号
function getSn($head = '')
{
    $order_id_main = date('YmdHi') . mt_rand(1000, 9999);
    //唯一订单号码（YYMMDDHHIISSNNN）
    return $head . substr($order_id_main, 2); //生成订单号
}

/**
 * 写后台日志
 * @param string $action
 * @param mixed $content
 * @return int|string
 */
function admin_log($action = '', $content = '')
{
    if (is_array($content) || is_object($content)) {
        $content = json_encode($content, JSON_UNESCAPED_UNICODE);
    }
    return AdminLog::write($action, $content);
}

/**
 *$ip  string  必传
 *获取ip归属地
 *demo 四川省成都市 电信
 */
function get_ip_address($ip)
{
    $cache_key = "ip_address_$ip";
    if (cache($cache_key)) {
        return cache($cache_key);
    }

    $context = stream_context_create([
        'http' => ['timeout' => 2],  // 设置 2 秒超时
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);

    $res0 = @file_get_contents("https://searchplugin.csdn.net/api/v1/ip/get?ip=$ip", false, $context);

    if (!$res0) {
        return '';  // 如果接口超时或失败，避免继续向下执行
    }

    $res0 = json_decode($res0, true);
    $address = $res0['data']['address'] ?? '';

    if ($address) {
        cache($cache_key, $address);
    }

    return $address;
}


/**
 * @param $url
 * @param $header
 * @return bool|string
 */
function get_curl($url, $header = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);  // 设置3秒超时

    $output = curl_exec($ch);
    if (curl_errno($ch)) {
        $output = false;
    }
    curl_close($ch);
    return $output;
}

/**

 * 把jsonp转为php数组
 * @param string $jsonp jsonp字符串
 * @param boolean $assoc 当该参数为true时，将返回array而非object
 * @return array
 */
function jsonp_decode($jsonp, $assoc = false)
{
    $jsonp = trim($jsonp);
    if(isset($jsonp[0]) && $jsonp[0] !== '[' && $jsonp[0] !== '{') {
        $begin = strpos($jsonp, '(');
        if(false !== $begin)
        {
            $end = strrpos($jsonp, ')');
            if(false !== $end)
            {
                $jsonp = substr($jsonp, $begin + 1, $end - $begin - 1);
            }
        }
    }
    return json_decode($jsonp, $assoc);
}