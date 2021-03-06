<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose 
 */
use \GatewayWorker\Lib\Gateway;

/******************数据库操作****************************/
//use \GatewayWorker\Lib\Mysqli;
//use \GatewayWorker\Lib\ConfMysqli;
/*******************数据库操作***************************/
class Events
{
    static $temp_int=0;
    static $manage_arr=array();
    static $redis;
    public static function onWorkerStart($businessWorker)
    {
    }

    /**
     * 链接
     * @DateTime:    [2018-05-26 15:18:05]
     */
    public static function onConnect($client_id)
    {
    }
    /**
     * gatewayworker 协议数据
     * @param  [type] $client_id [description]
     * @param  [type] $data      [description]
     * @return [type]            [description]
     */
    public static function onWebSocketConnect($client_id, $data)
    {   
    }

    public static function onWorkerStop($businessWorker)
    {
    }

   /**
    * 有消息时
    * @param int $client_id
    * @param mixed $message
    */
   public static function onMessage($client_id, $message)
   {    
        
        $conn=new Mysqli();
        /**
         * 根据需求修改这个（聊天）管理员列表
         * 也可以使用数据库谁查找填充 ，后台管理页面设置
         * @var array
         */
        $manage_arr=array(
            'xiao_ming',
            'manage_a',
        );


 
         $debug_state=1;
        if($debug_state==0){
            //echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:".json_encode($_SESSION)." onMessage:".$message."\n";
        }
        if($debug_state>0){
            $html="client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:".json_encode($_SESSION)." onMessage:".$message;
            $client_id=$client_id;
            $server_ip=$_SERVER['REMOTE_ADDR'];
            $onmessage=$message;
            $msg=$html;
        }
        $message_data = json_decode($message, true);
        if(!$message_data)
        {
            return ;
        }
        switch($_SERVER['GATEWAY_PORT']){
            /*****************************绑定操作*****************************************/
            case '7474':
                    Gateway::bindUid($client_id,$message_data['u_id']);
                break;
            /******************************绑定操作****************************************/
            /****************************************聊   天     层*********************************************************/
            case '7272':
                /**
                 * 选择业务类型（聊天）
                 * @DateTime:    [2018-06-08 17:11:20]
                 */
                switch($message_data['type'])
                {
                    case 'pong':
                        if($debug_state>0){
                            $insert_arr=array(
                                        'client_id'=>$client_id,
                                        'server_ip'=>$server_ip,
                                        'onmessage'=>$onmessage,
                                        'msg'=>$msg,
                                        'type'=>$message_data['type'],
                                        'client_name'=>$_SESSION['client_name'],
                                        'room_id'=>$_SESSION['room_id'],
                                        );

                        }
                        return;
                    case 'login':
                        if(!isset($message_data['room_id']))
                        {
                            throw new \Exception("\$message_data['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']} \$message:$message");
                        }
                        $room_id = $message_data['room_id'];
                        $client_name = htmlspecialchars($message_data['client_name']);
                        $_SESSION['room_id'] = $room_id;
                        $_SESSION['client_name'] = $client_name;
                        /************************如果client_name已经登陆则告诉客户端这个人已经登陆了，客户端发请求断开这个链接***************************/
                        $now_client=Gateway::getAllClientSessions();
                        if(count($now_client)>0){
                            foreach ($now_client as $key_client_list => $value_client_list) {
                                if(isset($value_client_list['client_name'])){
                                    if($value_client_list['client_name'] == $client_name && $key_client_list<$client_id){
                                        $new_message=array(
                                            'type'=>'login_out', 
                                            'client_id'=>$key_client_list, 
                                            'client_name'=>htmlspecialchars($client_name), 
                                            'time'=>date('Y-m-d H:i:s')
                                        );
                                        Gateway::sendToClient($key_client_list, json_encode($new_message));
                                        Gateway::closeClient($key_client_list);

                                    }
                                }
                            }
                        }
                        /*********************如果client_name已经登陆则告诉客户端这个人已经登陆了，客户端发请求断开这个链接******************************/

                        $clients_list = Gateway::getClientSessionsByGroup($room_id);
                        $temp_clients_list=$clients_list;
                        foreach($clients_list as $tmp_client_id=>$item)
                        {
                            $clients_list[$tmp_client_id] = $item['client_name'];
                        }
                        $clients_list[$client_id] = $client_name;
                        foreach ($clients_list as $key => $value) {
                            if(in_array($_SESSION['client_name'],$manage_arr)){
                                if(in_array($value,$manage_arr)){
                                     unset($clients_list[$key]);
                                }
                            }
                            else{
                                if(!in_array($value,$manage_arr)){
                                     unset($clients_list[$key]);
                                }
                            }
                        }
                        $new_message = array('type'=>$message_data['type'], 'client_id'=>$client_id, 'client_name'=>htmlspecialchars($client_name), 'time'=>date('Y-m-d H:i:s'));
                        /*****************************屏蔽全局聊天**************************************/
                        if(in_array($_SESSION['client_name'],$manage_arr)){
                            Gateway::sendToGroup($room_id, json_encode($new_message));
                        }
                        else{
                             $return_arr=array();

                             foreach ($temp_clients_list as $key => $value) {
                                 if(!in_array($value['client_name'],$manage_arr)){
                                     array_push($return_arr,$key);
                                 }
                             }
                             Gateway::sendToGroup($room_id, json_encode($new_message),$return_arr);
                             $return_arr=array();
                        }
                        /******************************屏蔽全局聊天*************************************/
                        Gateway::joinGroup($client_id, $room_id);
                        $new_message['client_list'] = $clients_list;
                        /******************************返回当前用户数据*************************************/
                        //$sql="select * from bm_message where to_client_name = '{$_SESSION['client_name']}' OR client_name = '{$_SESSION['client_name']}'";
                        //$my_message_data=$conn->get_Rows_Array($sql);
                        //$new_message['my_message_data']=$my_message_data;
                        /******************************返回当前用户数据*************************************/
                        Gateway::sendToCurrentClient(json_encode($new_message));
                        if($debug_state>0){
                            $insert_arr=array(
                                        'client_id'=>$client_id,
                                        'server_ip'=>$server_ip,
                                        'onmessage'=>$onmessage,
                                        'msg'=>$msg,
                                        'type'=>$message_data['type'],
                                        'client_name'=>$_SESSION['client_name'],
                                        'room_id'=>$_SESSION['room_id'],
                                        );
                            /**
                             * 登陆数据记录
                             */
                            //$res=$conn->arrayToInsertSql($insert_arr,'bm_message');

                        }
                        return;
                        
                    // 客户端发言 message: {type:say, to_client_id:xx, content:xx}
                    case 'say':
                        // 非法请求
                        if(!isset($_SESSION['room_id']))
                        {
                            throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
                        }
                        $room_id = $_SESSION['room_id'];
                        $client_name = $_SESSION['client_name'];
                        if($debug_state>0){
                            $insert_arr=array(
                                        'client_id'=>$client_id,
                                        'server_ip'=>$server_ip,
                                        'onmessage'=>$onmessage,
                                        'msg'=>$msg,
                                        'type'=>$message_data['type'],
                                        'client_name'=>$_SESSION['client_name'],
                                        'room_id'=>$_SESSION['room_id'],
                                        'to_client_name'=>$message_data['to_client_name']
                                        );
                            /**
                             * 聊天数据记录
                             */
                            //$res=$conn->arrayToInsertSql($insert_arr,'bm_message');

                        }
                        // 私聊
                        if($message_data['to_client_id'] != 'all')
                        {
                            $new_message = array(
                                'type'=>'say',
                                'from_client_id'=>$client_id, 
                                'from_client_name' =>$client_name,
                                'to_client_id'=>$message_data['to_client_id'],
                                'content'=>"<b>对你说: </b>".nl2br(htmlspecialchars($message_data['content'])),
                                'time'=>date('Y-m-d H:i:s'),
                            );
                            Gateway::sendToClient($message_data['to_client_id'], json_encode($new_message));
                            $new_message['content'] = "<b>你对".htmlspecialchars($message_data['to_client_name'])."说: </b>".nl2br(htmlspecialchars($message_data['content']));
                            return Gateway::sendToCurrentClient(json_encode($new_message));
                        }
                        
                        $new_message = array(
                            'type'=>'say', 
                            'from_client_id'=>$client_id,
                            'from_client_name' =>$client_name,
                            'to_client_id'=>'all',
                            'content'=>nl2br(htmlspecialchars($message_data['content'])),
                            'time'=>date('Y-m-d H:i:s'),
                        );
                        return Gateway::sendToGroup($room_id ,json_encode($new_message));
                }
                break;
            /********************************************聊   天     层*****************************************************/
            /****************************页面控制******************************************/
            case '7373':
                echo 7878;
                break;
            /*****************************页面控制*****************************************/
            /****************************有新的消息******************************************/
            case '7575':
                $return_message = array(
                    'type'=>'tip',
                    'from_client_id'=>$client_id, 
                    'from_client_name' =>'syaytem',
                    'to_client_uid'=>$get_messag_uid,
                    'time'=>date('Y-m-d H:i:s'),
                );
                Gateway::sendToUid($message_data['to_client_uid'], json_encode($return_message));
                break;
            /*****************************有新的消息*****************************************/
        }
   }
   
   /**
    * 当客户端断开连接时
    * @param integer $client_id 客户端id
    */
   public static function onClose($client_id)
   {
        /**
         * 根据需求修改这个（聊天）管理员列表
         * 也可以使用数据库谁查找填充 ，后台管理页面设置
         * @var array
         */
        $manage_arr=array(
            'xiao_ming',
            'manage_a',
        );

        $debug_state=0;
       if($debug_state==0){
           // echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";
       }
       if(isset($_SESSION['room_id']))
       {
           $room_id = $_SESSION['room_id'];
           $new_message = array('type'=>'logout', 'from_client_id'=>$client_id, 'from_client_name'=>$_SESSION['client_name'], 'time'=>date('Y-m-d H:i:s'));
            if(in_array($_SESSION['client_name'],$manage_arr)){
                Gateway::sendToGroup($room_id, json_encode($new_message));
            }
            else{
                 $return_arr=array();
                 $temp_clients_list=Gateway::getClientSessionsByGroup($room_id);
                 foreach ($temp_clients_list as $key => $value) {
                     if(!in_array($value['client_name'],$manage_arr)){
                         array_push($return_arr,$key);
                     }
                 }
                 Gateway::sendToGroup($room_id, json_encode($new_message),$return_arr);
            }
       }

   }
  
}
