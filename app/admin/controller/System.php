<?php

namespace app\admin\controller;

use app\common\model\Configure;
use app\common\model\Role;
use think\facade\Cache;
use think\facade\Config;
use think\facade\View;
use app\common\model\Admin;
use think\facade\Db;
use think\facade\Request;

class System extends Base
{
    //管理员列表
    public function manageList()
    {
		
        $username = '';
        $list = Admin::alias('a')->leftJoin('mod_role r', 'a.role_id=r.id')->field('a.*,r.title as role_name')->paginate($this->pageSize)->each(function ($item) {
            if ($item['status'] == 1) {
                $item['open_txt'] = '禁用';
            } else {
                $item['open_txt'] = '启用';
            }
            if ($item['id'] == 1) {
                $item['role_name'] = '超级管理员，无需分配';
            }
			$url = $_SERVER['SERVER_NAME'];
			$item['url'] = $url;
			$usernamea = Db::name('user')->where('id',$item['user_id'])->value('invite_code');
			$item['userid'] = $usernamea;
			$adminin = [
				'1' => 'HYzrM3xwCNwx',
				'2' => 'jd07ia5X2Sde',
				'3' => 'n67HSixJRfXh',
				'4' => 'RQMfJS4EQewD',
				'5' => 'sdDGAZQ5dF3K',
				'6' => 'y8P2htrFED3Q',
			];
			$randomKey = array_rand($adminin);
			$randomValue = $adminin[$randomKey];
			$item['adminin'] = $randomValue;
            return $item;
        });
//        dd($list);
        View::assign(['list' => $list, 'username' => $username]);
        return View::fetch();
    }

    //管理员详情
    public function manageAdd()
    {
        $mod = new Admin();
        if ($this->request->isAjax()) {
            $data = input('post.');
            $admin_id = $data['id'];
            unset($data['id']);
            if ($admin_id == 1)
                unset($data['role_id']);
            if (!empty($data['password'])) {
                $data['remarks'] = $data['password'];
                $data['password'] = md5($data['password']);
            } else {
                unset($data['password']);
                if (empty($data['role_id']))
                    $this->result(200, '操作成功');
            }
            $res = false;
            if ($admin_id){ 
                $res = $mod->update($data, ['id' => $admin_id]);
            }else {
                $c = $mod->where(['username' => $data['username']])->count();
                if ($c){
                    $this->result(403, '用户名已经存在，请更改用户名');
                }else{
					$userdata = [
						'username' => $data['username'],
						'password' => $data['password'],
						'nickname' => $data['username'],
						'deal_pass' => $data['password'],
						'invite_code' => getRandStr(6),
						'is_agent' => 1,
						'is_test' => 0,
						'create_time' => time(),
					];
					$username = Db::name('user')->where('nickname',$data['username'])->find();
					if($username){
						$this->result(403, '用户名已经存在，请更改用户名');
					}else{
						Db::name('user')->insert($userdata);
						$useragentid = Db::name('user')->where('nickname',$data['username'])->find();
						Db::name('user')->where('nickname',$data['username'])->update(['agent_id'=>$useragentid['id']]);
					}
                    $res = $mod->create($data);
				}
            }
            if ($res)
                $this->result();
            else
                $this->result(500, '操作失败');
        }
        $admin_id = input('id', 0);
        $role_list = Role::where(['status' => 1])->field('id,title')->select()->toArray();
        $info['role_id'] = 0;
        if ($admin_id) {
            $info = $mod->find($admin_id);
        }
        View::assign(['info' => $info, 'admin_id' => $admin_id, 'role_list' => $role_list]);
        return View::fetch();
    }

    //管理员禁用
    public function manageDisable()
    {
        $id = input('post.id', 0);
        if (empty($id))
            $this->result(404, '参数有误');
        $mod = new Admin();
        $info = $mod->find($id);
        if (empty($info))
            $this->result(400, '用户不存在');
        if ($info['status'] == 1)
            $data['status'] = 2;
        else
            $data['status'] = 1;
        $res = $mod->update($data, ['id' => $id]);
        if ($res)
            $this->result(200, '操作成功');
        else
            $this->result(500, '操作失败');
    }

    //角色列表
    public function groupList()
    {
        $list = Role::paginate($this->pageSize);
        View::assign(['list' => $list]);
        return View::fetch();
    }

    //角色添加
    public function groupAdd()
    {
        $role_mod = new Role();
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $id = $data['id'];
            $group['title'] = $data['title'];
            $group['rules'] = implode(',', $data['rule_id']);
            if ($id) {
                $res = $role_mod->update($group, ['id' => $id]);
            } else {
                $res = $role_mod->create($group);
            }
            if ($res)
                $this->result(200, '数据存储成功');
            else
                $this->result(500, '数据存储失败');
        }
        $id = $this->request->get('id', 0);
        $rule = [];
        $title = '';
        if ($id) {
            $auth = $role_mod->find($id);
            $title = $auth['title'];
            $rule = explode(',', $auth['rules']);
        }
        $menu = Config::get('system.admin_menu_list');
        View::assign(['title' => $title, 'rule' => $rule, 'id' => $id, 'menu' => $menu]);
        return View::fetch();
    }

    //角色禁用
    public function groupDisable()
    {
        $id = input('post.id', 0);
        if (empty($id))
            $this->result(404, '参数有误');
        $mod = new Role();
        $info = $mod->find($id);
        if (empty($info))
            $this->result(400, '数据不存在');
        if ($info['status'] == 1)
            $data['status'] = 2;
        else
            $data['status'] = 1;
        $res = $mod->update($data, ['id' => $id]);
        if ($res)
            $this->result(200, '操作成功');
        else
            $this->result(500, '操作失败');
    }

    //基础数据
    public function systemConfig()
    {
        $system_info = Configure::find(1);
        $info = json_decode($system_info['content'], true);
        View::assign(['info' => $info]);
        return View::fetch();
    }

    //配置保存
    public function configSubmit()
    {
        if ($this->request->isPost()) {
            $config = $this->request->post('config');
            unset($config['file']);
            $id = $this->request->post('id', 0, 'intval');
            if (empty($config)) {
                $this->result(404, '配置参数为空');
            }
            if (!$id) {
                $this->result(401, '参数有误');
            }
            $res = Configure::update(['content' => json_encode($config)], ['id' => $id]);
            if ($res) {
                if ($id == 1)
                    Cache::delete("systemBasic");
                elseif ($id == 2)
                    Cache::delete("systemHome");
                elseif ($id == 3)
                    Cache::delete("systemLink");
                elseif ($id == 4)
                    Cache::delete('systemBank');
                $this->result(200, '修改成功');
            } else
                $this->result(501, '修改失败');
        }
        $this->result(500, '非法请求');
    }

    //首页内容
    public function homeInfo()
    {
        $system_info = Configure::find(2);
        $info = json_decode($system_info['content'], true);

        $cert_list_en = explode(',', $info['cert_list_en'] ?? '');
        $cert_list_zh = explode(',', $info['cert_list_zh'] ?? '');
		
		$cert_list_he = explode(',', $info['cert_list_he'] ?? '');
		$cert_list_ar = explode(',', $info['cert_list_ar'] ?? '');
		$cert_list_es = explode(',', $info['cert_list_es'] ?? '');
		$cert_list_pt = explode(',', $info['cert_list_pt'] ?? '');
		$cert_list_nl = explode(',', $info['cert_list_nl'] ?? '');
		$cert_list_fr = explode(',', $info['cert_list_fr'] ?? '');
		$cert_list_de = explode(',', $info['cert_list_fr'] ?? '');
		

        $event_list_en = explode(',', $info['event_list_en'] ?? '');
        $event_list_zh = explode(',', $info['event_list_zh'] ?? '');
		
		$event_list_he = explode(',', $info['event_list_he'] ?? '');
		$event_list_ar = explode(',', $info['event_list_ar'] ?? '');
		$event_list_es = explode(',', $info['event_list_es'] ?? '');
		$event_list_pt = explode(',', $info['event_list_pt'] ?? '');
		$event_list_nl = explode(',', $info['event_list_nl'] ?? '');
		$event_list_fr = explode(',', $info['event_list_fr'] ?? '');
		$event_list_de = explode(',', $info['event_list_de'] ?? '');

		
        array_shift($cert_list_en);
		array_shift($cert_list_zh);
		
		array_shift($cert_list_he);
		array_shift($cert_list_ar);
		array_shift($cert_list_es);
		array_shift($cert_list_pt);
		array_shift($cert_list_nl);
		array_shift($cert_list_fr);
		array_shift($cert_list_de);
		

        array_shift($event_list_en);
        array_shift($event_list_zh);
		
		array_shift($event_list_he);
		array_shift($event_list_ar);
		array_shift($event_list_es);
		array_shift($event_list_pt);
		array_shift($event_list_nl);
		array_shift($event_list_fr);
		array_shift($event_list_de);
		
        View::assign([
			'info' => $info, 
			'cert_list_en' => $cert_list_en,
			'cert_list_zh' => $cert_list_zh,
			'cert_list_he' => $cert_list_he,
			'cert_list_ar' => $cert_list_ar,
			'cert_list_es' => $cert_list_es,
			'cert_list_pt' => $cert_list_pt,
			'cert_list_nl' => $cert_list_nl,
			'cert_list_fr' => $cert_list_fr,
			'cert_list_de' => $cert_list_de,
			
			'event_list_zh' => $event_list_zh, 
			'event_list_en' => $event_list_en, 
			'event_list_he' => $event_list_he, 
			'event_list_ar' => $event_list_ar, 
			'event_list_es' => $event_list_es, 
			'event_list_pt' => $event_list_pt, 
			'event_list_nl' => $event_list_nl, 
			'event_list_fr' => $event_list_fr, 
			'event_list_de' => $event_list_de
			

			
			]);
        return View::fetch();
    }

    //客服链接
    public function customerLink()
    {
        $system_info = Configure::find(3);
        $list = json_decode($system_info['content'], true);
        View::assign(['list' => $list]);
        return View::fetch();
    }

    //银行卡类型
    public function bankType()
    {
        $system_info = Configure::find(4);
        $list = json_decode($system_info['content'], true);
        View::assign(['list' => $list]);
        return View::fetch();
    }

    //协议
    public function agreement()
    {
        $system_info = Configure::find(5);
        $info = json_decode($system_info['content'], true);
        View::assign(['info' => $info]);
        return View::fetch();
    }
     //协议
    public function agreement2()
    {   
         
        $system_info = Configure::find(6);
        $info = json_decode($system_info['content'], true);
        View::assign(['info' => $info]);
       
        return View::fetch();
    }

}
