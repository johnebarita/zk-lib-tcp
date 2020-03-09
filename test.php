<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 25/02/2020
 * Time: 4:44 PM
 */

$date = '2020/3/4';

intval('1');
var_dump(intval('12'));

include 'TCP.php';
$zk = new TCP('169.254.132.152');

$zk->connect();
$zk->disable_device();
$zk->read_all_user_id();
$zk->enable_realtime();
//
$tmp = '';
$mp = 4370;
//while (@socket_recvfrom($zk->_zkclient, $buffer, 4096, MSG_WAITALL, $tmp, $mp)) {
//    $i++;
//    $res = $this->decode($buffer);
//    if (isset($res['location'])) {
//        $res_details = $this->getDetails($res['location']);
//        if ($res_details[0] == true) {
//            $res['details'] = $res_details[1];
//        } else {
//            $res['details'] = false;
//        }
//    }
//    $result[] = $res;
//    var_dump($buffer);
//}
//while (true){
//        $zk->recv_event();
    //    $ev = $zk->get_last_event();
//    var_dump($ev);
//    sleep(5);
//}