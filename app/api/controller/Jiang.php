<?php

namespace app\api\controller;

use app\common\model\Order;
use app\common\model\Goods2;
use app\common\model\Goods3;
use app\common\model\User;
use think\facade\Db;
use think\facade\Log;

class Jiang extends Base
{
    //获取列表
    public function getList()
    {   
       
        $res=(new Goods2)->select()->toArray();
        foreach ($res as $k=>$v){
            $res[$k]['prizeId']=$v['id'];
             $res[$k]['prizeImage']=$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].'/'.$v['prizeImage'];
        }
        // var_dump($res);die;
        if ($res) {
           $this->result(true, '操作成功', $res);
            // $this->result(true, '成功', $res);
        } else{
            $this->result(false, '操作失败');
            
        }
    }

    //开镜
    public function kaiJiang()
    {
        
        $uid = $this->request->user['id'];
        if (!$uid) $this->result(false, '参数有误');
        $field = 'jiang';
        
        $info = User::field($field)->find($uid)->toArray();
        if($info['jiang']!=''&&!is_null($info['jiang'])){
            $ee=explode(',',$info['jiang']);
            $in=(int) $ee[0];
            if(count($ee)>1){ 
                unset($ee[0]);
                $ee=implode(',',$ee);
            }else{
                $ee='';
            }
            User::where('id',$uid)->update(['jiang'=>$ee]);
            
        }else{
            //获取奖项列表
             $res=(new Goods2)->order('prizeWeight','desc')->select()->toArray();
             $max=$res[0]['prizeWeight'];
             //获取随机数
             $sameIndex=mt_rand(1,$max);
             $arr=[];
             foreach ($res  as  $k=>$v){
                 if($v['prizeWeight']>=$sameIndex){
                     array_push($arr,$v);
                 }
             }
             usort($arr, function($a, $b) {
              $al = $a['prizeWeight'];
              $bl = $b['prizeWeight'];
              if ($al == $bl) return 0;
                
              return ($al > $bl) ? -1 : 1;
            });
            if(count($arr)==1) {  $in=$arr[0]['id'];}
            else{
                $in=$arr[count($arr)-1]['id'];
            }
             
        }
        $ree=(new Goods2)->find($in)->toArray();
        unset($ree['id']);
        $ree['uid']=$uid;
        (new Goods3)->save($ree);
         
        User::where('id',$uid)->update(['jiangk'=>Db::raw('jiangk-1')]);
        
        $this->result(true, '操作成功',['id'=>$in]);
    }

    //订单列表
    public function getOrderList()
    {
        $uid = $this->request->user['id'];
        $web_time = $this->request->post('web_time', '', 'trim');
        if ($web_time)
            $diff_time = time() - strtotime($web_time);
        else
            $diff_time = 0;
        $page = $this->request->post('page', 1, 'intval');
        $status = $this->request->post('status', 0, 'intval');
        $domain = $this->request->domain();
        $where['o.uid'] = $uid;
        if ($status)
            $where['o.status'] = $status;
        $list = Db::name('Order')->alias('o')->leftJoin('mod_goods g', 'o.goods_id=g.id')->where($where)
            ->field('o.*,g.name as goods_name,g.img as goods_img')->order('o.id desc')->paginate(['list_rows' => 10, 'page' => $page])->toArray();
        foreach ($list['data'] as &$item) {
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time'] - $diff_time);
            if ($item['end_time'])
                $item['end_time'] = date('Y-m-d H:i:s', $item['end_time'] - $diff_time);
            if ($item['goods_img'])
                $item['goods_img'] = $domain . $item['goods_img'];
        }
        $this->result(true, '操作成功', $list['data'], ['total' => $list['total'], 'last_page' => $list['last_page']]);
    }

    //订单详情
    public function getOrderInfo()
    {
        $web_time = $this->request->post('web_time', '', 'trim');
        if ($web_time)
            $diff_time = time() - strtotime($web_time);
        else
            $diff_time = 0;
        $domain = $this->request->domain();
        $order_id = $this->request->post('order_id', 0, 'intval');
        if (!$order_id)
            $this->result(false, '参数有误');
        $order_info = Db::name('Order')->alias('o')->leftJoin('mod_goods g', 'o.goods_id=g.id')->where(['o.id' => $order_id])->field('o.*,g.name as goods_name,g.img as goods_img')->find();
        if (!$order_info)
            $this->result(false, '数据不存在');
        $order_info['create_time'] = date('Y-m-d H:i:s', $order_info['create_time'] - $diff_time);
        if ($order_info['end_time'])
            $order_info['end_time'] = date('Y-m-d H:i:s', $order_info['end_time'] - $diff_time);
        if ($order_info['goods_img'])
            $order_info['goods_img'] = $domain . $order_info['goods_img'];
        $this->result(true, '操作成功', $order_info);
    }

}
