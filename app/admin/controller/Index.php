<?php


namespace app\admin\controller;

use app\common\model\Goods;
use app\common\model\Order;
use app\common\model\Recharge;
use app\common\model\User;
use app\common\model\Withdraw;
use think\facade\App;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class Index extends Base
{
    public function index()
    {
        
        $rules = Session::get("adminInfo.rules");
		$adminusername = Session::get("adminInfo.username");
		$aincode = Db::name('admin')->where('username',$adminusername)->find();
		// 超管 user_id 可能为 0/空：避免对 null 取 invite_code 触发 500
		$bincode = [];
		if (!empty($aincode['user_id'])) {
			$bincode = Db::name('user')->where('id', $aincode['user_id'])->find() ?: [];
		}
		// dump($adminusername);die;
        if (!$rules)
            $rules = [];
        else
            $rules = explode(',', $rules);
        View::assign([
            'menu' => Config::get('system.admin_menu_list'),
            'admin_id' => Session::get("adminInfo.id"),
            'admin_name' => Session::get("adminInfo.username"),
			'incode' => $bincode['invite_code'] ?? '',
            'rule_config' => $rules,
        ]);
        return View::fetch();
    }

    public function welcome()
    {
        $date = $this->request->post('date', '', 'trim');
        if ($date) {
            $date = explode(' - ', $date);
            $date[0] = strtotime($date[0]);
            $date[1] = strtotime($date[1]);
            $is_ajax = true;
        } else {
            $date = strtotime(date('Y-m-d'));
            $is_ajax = false;
        }
        $user_mod = new User();
        $user_where['is_test'] = 0;
        $day_where = [];
        if ($this->agent_id) {
            $user_where['agent_id'] = $this->agent_id;
            $day_where['agent_id'] = $this->agent_id;
        }
        $data['first_recharge_people'] = 0;
        $data['first_recharge_price'] = 0;
        if ($is_ajax) {
            $user_where['is_test'] = 1;
            if ($date[0] == $date[1]) {
                $day_where['day'] = $user_where['deal_date'] = date('Ymd', $date[0]);
                $data['task_people'] = $user_mod->where($user_where)->count();//任务人数
                $day_data = Db::name('DayFill')->where($day_where)->select();//首充数据
                $between = $date[0] . ',' . ($date[0] + 86399);
            } else {
                $between = date('Ymd', $date[0]) . ',' . date('Ymd', $date[1]);
                $data['task_people'] = $user_mod->where($user_where)->whereBetween('deal_date', $between)->count();//任务人数
                $day_data = Db::name('DayFill')->where($day_where)->whereBetween('day', $between)->select();//首充数据
                $between = $date[0] . ',' . $date[1];
            }
            $user_where['is_test'] = 0;
            $data['register_people'] = $user_mod->where($user_where)->whereBetween('create_time', $between)->count();//注册人数
        } else {
            $data['user_total'] = $user_mod->where($user_where)->count();//用户总数
            $data['goods_total'] = Goods::count();//商品数
            $order_mod = new Order();
            $order_where['u.is_test'] = 0;
            if ($this->agent_id)
                $order_where['u.agent_id'] = $this->agent_id;
            $data['order_total'] = $order_mod->alias('o')->leftJoin('mod_user u', 'u.id=o.uid')->where($order_where)->count();//订单总数
            $order_where['o.status'] = 2;
            $data['order_price_total'] = $order_mod->alias('o')->leftJoin('mod_user u', 'u.id=o.uid')->where($order_where)->sum('price');//交易完成金额
            $data['register_people'] = $user_mod->where($user_where)->where('create_time', '>', $date)->count();//注册人数
            $user_where['is_test'] = 1;
            $day_where['day'] = $user_where['deal_date'] = date('Ymd');
            $data['task_people'] = $user_mod->where($user_where)->count();//任务人数
            $day_data = Db::name('DayFill')->where($day_where)->select();//首充数据
        }
        foreach ($day_data as $val) {
            $data['first_recharge_people'] += $val['people'];
            $data['first_recharge_price'] += $val['price'];
        }
        $recharge_mod = new Recharge();
        $recharge_where['u.is_test'] = 0;
        $with_mod = new Withdraw();
        $with_where['u.is_test'] = 0;
        if ($this->agent_id) {
            $with_where['u.agent_id'] = $this->agent_id;
            $order_where['u.agent_id'] = $this->agent_id;
            $recharge_where['u.agent_id'] = $this->agent_id;
        }
        $with_where['w.status'] = 2;
        if ($is_ajax) {
            if ($date[0] == $date[1]) {
                $between = $date[0] . ',' . ($date[0] + 86399);
            } else {
                $between = $date[0] . ',' . $date[1];
            }
            $recharge_where['r.status'] = 1;
            $recharge_up = $recharge_mod->alias('r')->leftJoin('mod_user u', 'u.id=r.uid')->where($recharge_where)
                ->whereBetween('r.create_time', $between)->sum('r.price');//上分
            $recharge_where['r.status'] = 2;
            $recharge_down = $recharge_mod->alias('r')->leftJoin('mod_user u', 'u.id=r.uid')->where($recharge_where)
                ->whereBetween('r.create_time', $between)->sum('r.price');//下分
            $data['with_total'] = $with_mod->alias('w')->leftJoin('mod_user u', 'u.id=w.uid')->where($with_where)->whereBetween('w.create_time', $between)->sum('w.price');//提现总
        } else {
            $recharge_where['r.status'] = 1;
            $recharge_up = $recharge_mod->alias('r')->leftJoin('mod_user u', 'u.id=r.uid')->where($recharge_where)
                ->where('r.create_time', '>', $date)->sum('r.price');
            $recharge_where['r.status'] = 2;
            $recharge_down = $recharge_mod->alias('r')->leftJoin('mod_user u', 'u.id=r.uid')->where($recharge_where)
                ->where('r.create_time', '>', $date)->sum('r.price');
            $data['with_total'] = $with_mod->alias('w')->leftJoin('mod_user u', 'u.id=w.uid')->where($with_where)->where('w.create_time', '>', $date)->sum('w.price');//提现总
        }
        $data['recharge_total'] = $recharge_up - $recharge_down;//充值金额
        $data['diff_price'] = round($data['recharge_total'] - $data['with_total'], 2);
        if ($this->request->isAjax()) {
            $this->result(200, '获取成功', $data);
        } else {
            View::assign($data);
            return View::fetch();
        }
    }

    //文件上传
    public function upload()
    {
        $file = $this->request->file('file');
        if (empty($file))
            $this->result(404, '文件数据为空');
        try {
            validate(['image' => 'fileSize:10485760|fileExt:jpg,png,gif,jpeg'])
                ->check(['image' => $file]);
//            //获取文件基本信息
//            $file_info = pathinfo($file);
//            //获取文件后缀
//            $extension = strtolower($file->getOriginalExtension());
//            //获取文件地址和名称
//            $file_path = $file_info['dirname'] . '/' . $file_info['basename'];
//            //文件地址转文件类
//            $file = new File($file_path);
            $file_path = '/uploads/' . date('Y/m/');
            $save_path = root_path() . 'public' . $file_path;
            $file_name = $file->md5() . '.' . strtolower($file->getOriginalExtension());
            // 上传到本地服务器
            $info = $file->move($save_path, $file_name);
            if ($info) {
                $this->result(200, '上传成功', ['file' => $file_path . $file_name]);
            } else {
                $this->result(500, '上传失败');
            }
        } catch (\think\exception\ValidateException $e) {
            $this->result(500, '验证-' . $e->getMessage());
        }
    }

    //编辑器上传图片
    public function uploadEdit()
    {
        $file = $this->request->file('file');
        if (empty($file))
            $this->result(404, '文件数据为空');
        try {
            validate(['image' => 'fileSize:10485760|fileExt:jpg,png,gif,jpeg'])
                ->check(['image' => $file]);
            $file_path = '/uploads/' . date('Y/m/');
            $save_path = root_path() . 'public' . $file_path;
            $file_name = $file->md5() . '.' . strtolower($file->getOriginalExtension());
            // 上传到本地服务器
            $info = $file->move($save_path, $file_name);
            if ($info) {
                return json(['code' => 0, 'msg' => 'success', 'data' => [
                    'src' =>$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME']. $file_path . $file_name,
                ]]);
            } else {
                return json(['code' => 500, 'msg' => lang('上传失败')]);
            }
        } catch (\think\exception\ValidateException $e) {
            return json(['code' => 500, 'msg' => $e->getMessage(), 'data' => []]);
        }
    }

    //清除runtime
    public function clearCache()
    {
        $path = App::getRootPath() . 'runtime';
        $del_list = $this->delDir($path);
        if ($del_list) {
            $result['msg'] = '清除缓存成功!';
            $result['status'] = 200;
            $result['list'] = $del_list;
        } else {
            $result['msg'] = '清除缓存失败!';
            $result['status'] = 500;
        }
        return json($result);
    }

    private function delDir($dir)
    {
        $dir_list = [];
        $handle = opendir($dir);
        //遍历文件夹
        while (($item = readdir($handle)) !== false) {
            // log目录不可以删除
            if ($item != '.' && $item != '..') {
                $cur_file = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($cur_file)) {
                    $dir_list = array_merge($dir_list, $this->delDir($cur_file));
                    if (count(scandir($cur_file)) == 2) {
                        //目录为空,=2是因为. 和 ..存在
                        rmdir($cur_file);
                        $dir_list[] = $cur_file;
                    }
                } else {
                    if ($item != '.gitignore') {
                        if (!unlink($cur_file)) {
                            return false;
                        } else {
                            $dir_list[] = $cur_file;
                        }
                    }
                }
            }
        }
        closedir($handle);
        return $dir_list;
    }

    public function getWithCount()
    {
        $where['w.status'] = 1;
        $agentid = $this->agent_id;
        if ($agentid){
            $where[] = ['u.agent_id', '=', strval($agentid)];
            $count = Withdraw::alias('w')->leftJoin('mod_user u', 'w.uid=u.id')->where('w.status',1)->where('u.agent_id',$agentid)->count('w.id');
        }else{
            $count = Withdraw::where('status',1)->count('id');
        }
            
        
        $this->result(200, '获取成功', ['with_count' => $count]);
    }
    public function getRechange()
    {
        $where['w.status'] = 4;
        $agentid = $this->agent_id;
        if ($agentid){
            $where[] = ['u.agent_id', '=', $this->agent_id];
            $count = Db::name('recharge')->alias('w')->leftJoin('mod_user u', 'w.uid=u.id')->where('w.status',4)->where('u.agent_id',$agentid)->count('w.id');
        }else{
            $count = Db::name('recharge')->alias('w')->leftJoin('mod_user u', 'w.uid=u.id')->where('w.status',4)->count('w.id');
        }

        $this->result(200, '获取成功', ['rech_count' => $count]);
    }
	public function grabOrders()
	{
	    $where['w.status'] = 2;
		$where['u.order_total'] = 0;
		$where['u.user_order'] = 0;
		$agentid = $this->agent_id;
	    if ($agentid){
            $where[] = ['u.agent_id', '=', $this->agent_id];
            $count = Db::name('graborders')->alias('w')->leftJoin('mod_user u', 'w.uid=u.id')
        	    ->where('w.status',2)
        	    ->where('u.agent_id',$agentid)
        	   // ->where('u.order_total',0)
        	   // ->where('u.user_order',0)
        	    ->count('w.id');
	    }else{
            $count = Db::name('graborders')->alias('w')->leftJoin('mod_user u', 'w.uid=u.id')
        	    ->where('w.status',2)
        	   // ->where('u.order_total',0)
        	   // ->where('u.user_order',0)
        	    ->count('w.id');   
	    }
	        

	    $this->result(200, '获取成功', ['rech_count' => $count]);
	}
}
