<?php

namespace app\admin\controller;

use think\exception\HttpResponseException;
use think\Response;

final class PublicApi
{
    /**
     * @return int
     */
    public function index()
    {
        return 0;
    }

    /**
     * 根据ip获取地址
     * @return mixed
     */
    public function getIpAddress()
    {
        $data = request()->post();
        $ip = $data['ip'] ?? '';
        $address = '';
        if ($ip && $ip !== '127.0.0.1' && $ip !== 'localhost' && $ip !== '暂无') {
            $address = get_ip_address($ip);
        }
        $result = [
            'status' => 200,
            'msg' => '',
            'time' => time(),
            'data' => $address,
        ];
        ob_clean();
        ob_end_clean();
        $response = Response::create($result, 'json')->header([]);
        throw new HttpResponseException($response);

    }
}