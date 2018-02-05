<?php

namespace Muushop\Controller;

use Think\Controller;

class ApiController extends Controller {
	protected $product_model;
	protected $cart_model;
	protected $order_model;
	protected $order_logic;
	protected $user_address_model;
	protected $delivery_model;
	protected $user_coupon;
	protected $coupon_logic;

	function _initialize()
	{
		$this->product_model      = D('Muushop/MuushopProduct');
		$this->cart_model         = D('Muushop/MuushopCart');
		$this->order_model        = D('Muushop/MuushopOrder');
		$this->order_logic        = D('Muushop/MuushopOrder', 'Logic');
		$this->user_address_model = D('Muushop/MuushopUserAddress');
		$this->delivery_model     = D('Muushop/MuushopDelivery');
		$this->user_coupon        = D('Muushop/MuushopUserCoupon');
		$this->coupon_logic       = D('Muushop/MuushopCoupon', 'Logic');
	}

	
	/**
	 * 计算运费json接口
	 * @param int $id 运费模板ID
	 * @param int $areaid 地区ID代码 依赖ChinaCity插件
	 * @param int $quantity 购买的商品总是
	 * @param int express 运输方式 如：express\ems\self
	 * @return [json] [根据模板ID返回模板详细JSON字符串]
	 */
	public function delivery(){
		$id = I('get.id',0,'intval');
		$areaid = I('get.areaid',0,'intval');
		$quantity = I('get.quantity',0,'intval');
		$express = I('get.express','','text');

		if($id==0 || empty($id)){//id为空或为0
			$data['delivery_fee']=0;
		}else{
			$address = $this->user_address_model->get_user_address_by_id($areaid);
			if($express){
				$address['delivery'] = $express;
			}
			$delivery_fee = $this->order_logic->calc_delivery_fee($id,$address, $quantity);
			//组装DATA数据
			$data['delivery_fee']=$delivery_fee;
		}
		
		//组装JSON返回数据
		if(isset($delivery_fee)){
			$result['status']=1;
			$result['info'] = 'success';
			$result['data'] = $data;
		}else{
			$result['status']=0;
			$result['info'] = 'error';
		}
		$this->ajaxReturn($result,'JSON');
	}
	/**
	 * 用户收货地址列表json接口
	 * 
	 */
	public function address(){
		$map['user_id'] = is_login();
		list($list,$totalCount) = $this->user_address_model->get_user_address_list($map);
		$first = 0;
		foreach($list as &$val){
            $val['province'] = D('district')->where(array('id' => $val['province']))->getField('name');
            $val['city'] = D('district')->where(array('id' => $val['city']))->getField('name');
            $val['district'] = D('district')->where(array('id' => $val['district']))->getField('name');

            if($val['modify_time']>$first){
            	$first=$val['modify_time'];
            	$val['first']=1;
            }else{
            	unset($val['first']);
            }
		}
		unset($val);

		//组装JSON返回数据
		if(isset($list)){
			$result['status']=1;
			$result['info'] = 'success';
			$result['data'] = $list;
		}else{
			$result['status']=0;
			$result['info'] = 'error';
		}
		$this->ajaxReturn($result,'JSON');
	}
	/**
	 * 根据ID获取优惠卷信息
	 * @return json 优惠券的详细信息
	 */
	public function coupon(){
		$id = I('get.id',0,'intval');
		$ret = $this->user_coupon->get_user_coupon_by_id($id);
		$ret['info']['rule']['discount'] = sprintf("%01.2f", $ret['info']['rule']['discount']/100);//将金额单位分转成元
		//组装JSON返回数据
		if($ret){
			$result['status']=1;
			$result['info'] = 'success';
			$result['data'] = $ret;
		}else{
			$result['status']=0;
			$result['info'] = 'error';
		}
		$this->ajaxReturn($result,'JSON');
	}
}