<?php

namespace Muushop\Controller;

use Think\Controller;
use Com\TPWechat;
use Com\WechatAuth;
class UserController extends BaseController {
	protected $product_model;
	protected $cart_model;
	protected $order_model;
	protected $order_logic;
	protected $user_address_model;

function _initialize()
	{
		parent::_initialize();
		$this->init_user();
		$this->product_model      = D('Muushop/MuushopProduct');
		$this->cart_model         = D('Muushop/MuushopCart');
		$this->order_model        = D('Muushop/MuushopOrder');
		$this->order_logic        = D('Muushop/MuushopOrder', 'Logic');
		$this->user_address       = D('Muushop/MuushopUserAddress');
		$this->user_coupon        = D('Muushop/MuushopUserCoupon');

	}
	/*商城用户中心
	*/
	public function index()
	{
		$su = query_user(array('avatar32', 'nickname', 'mobile'), get_uid());
		$map['user_id'] = get_uid();
		$map['status'] = 1;
		$order_count_group_by_status = $this->order_model->where($map)->getfield('status,count(1) as count');
		$this->assign('su', $su);
		$this->assign('order_count_group_by_status', $order_count_group_by_status);
		$this->display();
	}

	/*
	我的订单
	*/
	public function orders($action = 'list')
	{
		switch($action)
		{
			case 'list':
				$page = I('get.page',1,'intval');
				$option['page'] = $page;
				$option['r'] = 20;
				$option['user_id'] = $this->user_id;
				$order_list = $this->order_model->get_order_list($option);
				$order_list['list'] = empty($order_list['list'])?array(): $order_list['list'];
				array_walk($order_list['list'],function(&$a)
				{
					empty($a['products']) ||
					array_walk($a['products'],function(&$b)
					{
						$b['main_img'] = (empty($b['main_img'])?'':pic($b['main_img']));
					});
				});
				foreach($order_list['list'] as &$val){
					$val['paid_fee'] = sprintf("%01.2f", $val['paid_fee']/100);//将金额单位分转成元
					foreach($val['products'] as &$products){
						$products['temporary'] = explode(';',$products['sku_id']);
						$products['id'] = $products['temporary'][0];
						unset($products['temporary'][0]);//删除临时sku_id数组的ID
						$products['temporary'] = array_values($products['temporary']);
						$products['sku'] =(empty($products['temporary'])?'':$products['temporary']);
						unset($products['temporary']);//删除临时sku_id数组
					}
					unset($products);
				};
				unset($val);
				$this->assign('order_list',$order_list);
				$this->assign('option', $option);
				$this->display('User/orders');
			break;
			case 'detail':
				$id = I('get.id',1,'intval');
				$order_no = I('get.order_no',1,'intval');

				$order = $this->order_model->get_order_by_id($id);
					$order['create_time'] =(empty($order['create_time'])?'':date('Y-m-d H:i:s',$order['create_time']));
					$order['paid_time'] =(empty($order['paid_time'])?'未支付':date('Y-m-d H:i:s',$order['paid_time']));
					$order['send_time'] = (empty($order['send_time'])?'未发货':date('Y-m-d H:i:s',$order['send_time']));
					$order['recv_time'] = (empty($order['recv_time'])?'未收货':date('Y-m-d H:i:s',$order['recv_time']));

					$order['user_info'] = query_user('nickname',$order['user_id']);

					$order['address']["province"] = D('district')->where(array('id' => $order['address']["province"]))->getField('name');
				    $order['address']["city"] = D('district')->where(array('id' => $order['address']["city"]))->getField('name');
				    $order['address']["district"] = D('district')->where(array('id' => $order['address']["district"]))->getField('name');

				    //设置支付类型
				    switch ($order['pay_type']){
				    	case 'balance':
				    		$order['pay_type_cn']="余额支付";
				    	break;
				    	case 'delivery':
				    		$order['pay_type_cn']="货到付款";
				    	break;
				    	case 'onlinepay':
				    		$order['pay_type_cn']="在线支付";
				    	break;
				    	default:
				    		$order['pay_type_cn']="未设置";
				    }
				    
					$order['paid_fee']='¥ '.sprintf("%01.2f", $order['paid_fee']/100);
					$order['delivery_fee']='¥ '.sprintf("%01.2f", $order['delivery_fee']/100);
					$order['discount_fee']='- ¥ '.sprintf("%01.2f", $order['discount_fee']/100);

					if(!empty($order['products'])){
						foreach($order['products'] as &$val){
							//商品列表价格单位转为元
							$val['paid_price']='¥ '.sprintf("%01.2f", $val['paid_price']/100);
							//sku_id转为数组
							$val['sku'] = explode(';',$val['sku_id']);
							unset($val['sku'][0]);
						}
					}
					unset($val);
					//dump($order);exit;
					$this->assign('order',$order);
					$this->display('User/order_detail');
			break;
		}
	}
	/*
	我的优惠卷
	 */
	public function coupon()
	{
		$option['user_id'] = get_uid(); 
	    $option['available'] = 1;
	    $coupon = $this->user_coupon->get_user_coupon_list($option);
	    foreach($enable_coupon['list'] as &$val){
	            $val['info']['rule']['min_price'] = sprintf("%01.2f", $val['info']['rule']['min_price']/100);//将金额单位分转成元
	            $val['info']['rule']['discount'] = sprintf("%01.2f", $val['info']['rule']['discount']/100);
	    }
	    unset($val);
	    //dump($coupon);exit;
	    $this->assign('coupon', $coupon);
		$this->display();
	}

	/*
	我的地址
	 */
	public function address($action='')
	{
		switch($action)
		{
			case 'edit'://编辑添加地址
				if (IS_POST){
					$data['id'] = I('post.id',0,'intval');
					$data['name'] = I('post.name','','text');
					$data['phone'] = I('post.phone',0,'intval');
					$data['province'] = I('post.province',0,'intval');
					$data['city'] = I('post.city',0,'intval');
					$data['district'] = I('post.district',0,'intval');
					$data['address'] = I('post.address','','text');
					$data['user_id'] = get_uid();
					//dump($data);exit;
					if(!$this->user_address->create($data)){
						$this->error('操作失败！'.$this->user_address->getError());
					}else{
						$map['user_id'] = get_uid();
						list($list,$totalCount) = $this->user_address->get_user_address_list($map);
						if($totalCount<=20){
							$ret = $this->user_address->add_or_edit_user_address($data);
						}else{
							$this->error('最多只能添加20条收货地址。');
						}
						if ($ret){
							$this->success('操作成功。', U('user/address'));
						}else{
							$this->error('操作失败。');
						}
					}
				}else{
					$id = I('id','','intval');
					$address = $this->user_address->get_user_address_by_id($id);
					$this->assign('address', $address);
					$this->display('address_edit');
				}
			break;
			case 'del'://删除地址
				if (IS_POST){
					$ids = I('post.id',0,'intval');
					$ret = $this->user_address->delete_user_address($ids);
					if ($ret){
						$this->success('操作成功。', U('user/address'));
					}else{
						$this->error('操作失败。');
					}
				}else{
					$id = I('id','','intval');
					$this->assign('id',$id);
					$this->display('address_del');
				}
			break;
			case 'first'://设为首选地址
				$id = I('id','','intval');
				$map['id'] = $id;
				$map['modify_time'] = time();

				$ret = $this->user_address->add_or_edit_user_address($map);
				if ($ret){
					$this->success('操作成功。', U('user/address'));
				}else{
					$this->error('操作失败。');
				}
			break;
			default:
				$map['user_id'] = get_uid();
				list($list,$totalCount) = $this->user_address->get_user_address_list($map);
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
				$this->assign('list', $list);
				$this->assign('totalCount',$totalCount);
				$this->display();
		}
	}

	/*
	 * 订单评论
	 */
	public function comment()
	{
		if(IS_POST)
		{
			$product_comments = I('product_comment');
			foreach($product_comments as &$product_comment)
			{
				$product_comment['user_id'] = $this->user_id;
				$product_comment['product_id'] = explode(';',$product_comment['product_id'])[0];
				if(!($product_comment =  $this->product_comment_model->create($product_comment)))
				{
					$this->error($this->product_comment_model->geterror());
				}


			}
			$ret = $this->order_logic->add_product_comment($product_comments);
			if(!$ret )
			{
				$this->error('评论失败，'.$this->order_logic->error_str);
			}
			if($ret )
			{
				$this->success('评论成功');
			}
		}
		else
		{
			$id = I('id','','intval');
			$order = $this->order_model->get_order_by_id($id);
			$this->assign('order', $order);
			$this->assign('products', $order['products']);
			$this->display();
		}

	}

}