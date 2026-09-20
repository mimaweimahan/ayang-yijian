<?php
declare (strict_types=1);

namespace app\middleware;

use think\facade\Cache;
use think\facade\Config;
use think\facade\Session;

class CheckAdmin
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
        $admin_info = Session::get('adminInfo');
        if (empty($admin_info)) {
            if ($request->isAjax()) {
                return json(['status' => 500, 'msg' => '登录超时或被下线']);
            } else {
                return redirect(url('admin/Login/index')->__toString());
            }
        }
        $url = '/admin/' . strtolower($request->controller()) . '/' . strtolower($request->action());
        $menu_list = $this->getMenu();

        if ($request->controller() != 'Index') {
            if (empty($menu_list[$url])) {

                return json(['status' => 403, 'msg' => '未知路由', 'url' => $url]);
            }
            $rule = $admin_info['rules'];
            if ($admin_info['id'] > 1) {
                if (!$rule)
                    $rule_list = [];
                else
                    $rule_list = explode(',', $rule);
                if (!in_array($menu_list[$url], $rule_list))
                    return json(['status' => 501, 'msg' => '无权限访问']);
            }
        }
        return $next($request);
    }

    private function getMenu()
    {
        $menu_list = [];
//        $menu_list = Cache::get('adminMenuList');
//        if (!$menu_list) {
        $menu_data = Config::get('system.admin_menu_list');
        foreach ($menu_data as $pk => $pv) {
            if (!empty($pv['url'])) {
                $url = strtolower($pv['url']);
                $menu_list[$url] = $pk;
            }
            if (!empty($pv['children'])) {
                foreach ($pv['children'] as $ck => $cv) {
                    if (!empty($cv['url'])) {
                        $url = strtolower($cv['url']);
                        $menu_list[$url] = $pk . '-' . $ck;
                    }
                    if (!empty($cv['children'])) {
                        foreach ($cv['children'] as $gck => $gcv) {
                            if (!empty($gcv['url'])) {
                                $url = strtolower($gcv['url']);
                                $menu_list[$url] = $pk . '-' . $ck . '-' . $gck;
                            }
                        }
                    }
                }
            }
        }
//        Cache::set('adminMenuList', $menu_list, 86400);
//        }
        return $menu_list;
    }
}
