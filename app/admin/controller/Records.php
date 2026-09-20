<?php

namespace app\admin\controller;

use app\common\model\AppRoll;
use app\common\model\Goods3  as Goods;
use app\common\model\Order;
use app\common\model\User;
use think\facade\View;

class Records extends Base
{
    //产品列表
    public function list()
    {   
        
        if ($this->request->isAjax()) {
            $price_min = $this->request->post('price_min', 0, 'intval');
            $price_max = $this->request->post('price_max', 0, 'intval');
            $page = $this->request->post('page', 1, 'intval');
            $list = Goods::whereBetween('price', "$price_min,$price_max")->field('id,name,price')->order('price asc')->page($page, $this->pageSize)->select()->toArray();
            if (!$list)
                $this->result(202, '到底了');
            else {
                if (count($list) < $this->pageSize) {
                    $this->result(201, '获取成功', $list);
                } else {
                    $this->result(200, '获取成功', $list);
                }
            }
        }
        $name = $this->request->get('name', '', 'trim');
        $start_time = $this->request->get('start_time', '', 'trim');
        $where = [];
        if ($name)
            $where[] = ['uid', '=', "$name"];
        if ($start_time)
            $where[] = ['create_time', '>', strtotime($start_time)];
        $list = Goods::where($where)->paginate($this->pageSize);
        View::assign(['list' => $list, 'name' => $name, 'start_time' => $start_time]);
        
        return View::fetch();
    }

    //产品添加
    public function add()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $id = $data['id'] ?? 0;
            unset($data['id']);
            if ($id)
                $res = Goods::update($data, ['id' => $id]);
            else
                $res = Goods::create($data);
               
            if ($res)
                $this->result(200, '操作成功');
            else
                $this->result(500, '操作失败');
        }
        $id = $this->request->get('id', 0);
        $info = [];
        if ($id) {
            $info = Goods::find($id);
        }
        View::assign(['info' => $info, 'id' => $id]);
        return View::fetch();
    }

    //产品删除
    public function del()
    {
        $id = $this->request->post('id', 0, 'intval');
        if (!$id)
            $this->result(404, '参数有误');
        $res = Goods::destroy($id);
        if ($res)
            $this->result(200, '删除成功');
        else
            $this->result(500, '删除失败');
    }

    //app滚动列表
    public function appList()
    {
        $name = $this->request->get('name', '', 'trim');
        $start_time = $this->request->get('start_time', '', 'trim');
        $where = [];
        if ($name)
            $where[] = ['name', 'like', "%$name%"];
        if ($start_time)
            $where[] = ['create_time', '>', strtotime($start_time)];
        $list = AppRoll::where($where)->paginate($this->pageSize);
        View::assign(['list' => $list, 'name' => $name, 'start_time' => $start_time]);
        return View::fetch();
    }

    //app添加
    public function appAdd()
    {
        if ($this->request->isAjax()) {
            $data = $this->request->post();
            $id = $data['id'] ?? 0;
            unset($data['id']);
            if ($id)
                $res = AppRoll::update($data, ['id' => $id]);
            else
                $res = AppRoll::create($data);
            if ($res)
                $this->result(200, '操作成功');
            else
                $this->result(500, '操作失败');
        }
        $id = $this->request->get('id', 0);
        $info = [];
        if ($id) {
            $info = AppRoll::find($id);
        }
        View::assign(['info' => $info, 'id' => $id]);
        return View::fetch();
    }

    //app删除
    public function appDel()
    {
        $ids = $this->request->post('ids', []);
        if (!$ids)
            $this->result(404, '参数有误');
        $res = AppRoll::where(['id' => $ids])->delete();
        if ($res)
            $this->result(200, '删除成功');
        else
            $this->result(500, '删除失败');
    }

    //订单列表
    public function orderList()
    {
        $order_no = $this->request->get('order_no', '', 'trim');
        $username = $this->request->get('username', '', 'trim');
        $status = $this->request->get('status', 0, 'intval');
        $start_time = $this->request->get('start_time', '', 'trim');
        $where = [];
        if ($this->agent_id)
            $where[] = ['u.agent_id', '=', $this->agent_id];
        if ($username)
            $where[] = ['u.username', 'like', '%' . $username . '%'];
        if ($order_no)
            $where[] = ['o.order_no', '=', $order_no];
        if ($status)
            $where[] = ['o.status', '=', $status];
        if ($start_time)
            $where[] = ['o.create_time', '>', strtotime($start_time)];
        $mod = new Order();
        $list = $mod->alias('o')->leftJoin('mod_user u', 'u.id=o.uid')->leftJoin('mod_goods g', 'g.id=o.goods_id')->where($where)
            ->field('o.*,u.username,u.balance,u.pid,u.nickname,g.price as g_price,g.name as g_name')->paginate($this->pageSize)->each(function ($item) {
                if ($item['status'] == 1)
                    $item['status_txt'] = '等待付款';
                else
                    $item['status_txt'] = '完成付款';
                if ($item['end_time'])
                    $item['end_time'] = date('Y-m-d H:i:s', $item['end_time']);
                else
                    $item['end_time'] = '暂无';
                return $item;
            });
        $list_arr = $list->getCollection()->toArray();
        $uid_arr = array_column($list_arr, 'pid');
        $parent = User::where(['id' => $uid_arr])->column('nickname', 'id');

        $stat['total_price'] = $mod->alias('o')->leftJoin('mod_user u', 'u.id=o.uid')->where($where)->sum('o.price');
        $stat['comm_price'] = $mod->alias('o')->leftJoin('mod_user u', 'u.id=o.uid')->where($where)->sum('o.commission');

        View::assign(['list' => $list, 'order_no' => $order_no, 'start_time' => $start_time, 'username' => $username, 'status' => $status, 'parent' => $parent, 'stat' => $stat]);
        return View::fetch();
    }

}
