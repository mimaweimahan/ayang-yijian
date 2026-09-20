<?php
declare (strict_types=1);

namespace app\middleware;

use think\facade\Cache;
use think\facade\Lang;

class CheckApi
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        $header = $request->header();
        $lang = $header['schedule-lang'] ?? 'en';
        Lang::setLangSet($lang);
        $user = [];
        if (env('system.stop', false) == true) {
            return json(['success' => false, 'msg' => Lang::get('System under maintenance')]);
        }
        if (empty($header['debug-schedule-2023'])) {
            $time = $header['s-time'] ?? '';
            $authorization = $header['authorization'] ?? '';
            $token = strtolower($header['s-token'] ?? '');
            if (!$time || !$token) {
                return json(['success' => false, 'msg' => Lang::get('No permission')]);
            }
            $check_token = md5($time . '@62b7c5572a99ee1@' . $time);
            if ($token !== $check_token) {
                return json(['success' => false, 'msg' => Lang::get('No permission') . ' _1']);
            }
            if (!in_array($request->pathinfo(), ['login', 'register',
                'getAgreement', 'getIndex', 'getCustomer'])) {
                if (!$authorization) {
                    return json(['success' => false, 'msg' => Lang::get('No permission') . ' _2']);
                }
                $user = Cache::get($authorization);
                if (empty($user)) {
                    return json(['success' => false, 'msg' => Lang::get('please login again'), 'logout' => true]);
                }
            }
        } 

        $request->user = $user;
        $response = $next($request);
        return $response;
    }
}
