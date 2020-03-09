<?php

/**
 * Created by PhpStorm.
 * User: User
 * Date: 25/02/2020
 * Time: 4:45 PM
 */
class TCP
{
    public $_ip;
    public $_port;
    public $_zkclient;

    public $_data_recv = '';
    public $_session_id = 0;
    public $_section = '';
    public $last_event_code = 0;


    public $reply_number = 0;

    const START_TAG = array(0x50, 0x50, 0x82, 0x7D);
    const CMD_CONNECT = 0x03e8;
    const CMD_OPTIONS_WRQ = 0x000c;
    const CMD_REFRESH_OPTION = 0x03f6;
    const CMD_ENABLE_DEVICE = 0x03ea;
    const CMD_DISABLE_DEVICE = 0x03eb;
    const CMD_REG_EVENT = 0x01f4;
    const CMD_DATA_WRRQ = 0x05df;
    const CMD_DATA = 0x05dd;
    const CMD_PREPARE_DATA = 0x05dc;
    //reply codes
    const CMD_ACK_OK = 0x07d0;

    public function __construct($ip, $port = 4370)
    {
        $this->_ip = $ip;
        $this->_port = $port;
        $this->_zkclient = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        echo "<pre>";
    }

    public function prints()
    {
        $pckt = array();
        array_push($pckt, pack('H*', '50'));
        array_push($pckt, pack('H*', '50'));
        array_push($pckt, pack('H*', '82'));
        array_push($pckt, pack('H*', '7D'));
        array_push($pckt, pack('H*', '00'));
        array_push($pckt, pack('H*', '00'));
        array_push($pckt, pack('H*', '00'));
        array_push($pckt, pack('H*', '00'));
        array_push($pckt, pack('H*', 'e8'));
        array_push($pckt, pack('H*', '03'));
        array_push($pckt, pack('H*', '00'));
        array_push($pckt, pack('H*', '00'));
        array_push($pckt, pack('H*', '00'));
        array_push($pckt, pack('H*', '00'));
        array_push($pckt, pack('H*', '00'));
        array_push($pckt, pack('H*', '00'));


//
//        $packet = pack('H*', '5050827D');//start tag
//        $packet .= pack('H*', '0000');//size of payload
//        $packet .= pack('H*', '0000');//fixed zeros
//        $packet .= pack('H*', 'e803');//cmd_code
//        $packet .= pack('H*', '0000');//checksum field
//        $packet .= pack('H*', '0000');//session_id
//        $packet .= pack('H*', '0000');//reply_id

        echo "<pre>";

        //write size field
        $sf = unpack('H2h1/H2h2', pack('I*', sizeof($pckt) - 8));
        $pckt[4] = pack('H*', $sf['h1']);
        $pckt[5] = pack('H*', $sf['h2']);

        //write checksum
        $cksm = $this->checksum(array_slice($pckt, 8, sizeof($pckt)));
        $cs = unpack('H2h1/H2h2', pack('I*', $cksm));
        $pckt[10] = pack('H*', $cs['h1']);
        $pckt[11] = pack('H*', $cs['h2']);


    }

    public function connect()
    {
        echo "<pre>";

        socket_connect($this->_zkclient, $this->_ip, $this->_port);
        $this->send_command(TCP::CMD_CONNECT);
        $this->recv_reply();
        $this->_session_id = $this->last_session_code;
        $this->set_device_info('SDKBuild', '1');
        $this->connected_flg = $this->recvd_ack();
        return $this->connected_flg;
    }

    public function send_command($command, $data = null)
    {
        $this->send_packet($this->create_packet($command, $data));
    }

    public function create_packet($command, $data = null, $reply_number = null, $session_id = null)
    {
        $pckt = array();
        foreach (TCP::START_TAG as $b) {
            array_push($pckt, pack('I*', $b)); //1-4
        }
        //size of payload
        array_push($pckt, pack('H*', '00')); //5
        array_push($pckt, pack('H*', '00')); //6

        //fixed zeros
        array_push($pckt, pack('H*', '00')); //7
        array_push($pckt, pack('H*', '00')); //8

        //cmd_code/ repy
        $u = unpack('H2h1/H2h2', pack('I*', $command));
        array_push($pckt, pack('H*', $u['h1'])); //9
        array_push($pckt, pack('H*', $u['h2'])); //10

        //checksum field
        array_push($pckt, pack('H*', '00')); //11
        array_push($pckt, pack('H*', '00')); //12

        //append session_id
        if ($session_id == null) {
            $sid = $this->_session_id;
        } else {
            $sid = $session_id;
        }

        $s = unpack('H2h1/H2h2', pack('I*', $sid));
        $pckt[12] = pack('H*', $s['h1']);
        $pckt[13] = pack('H*', $s['h2']);

        //append reply number
        if ($reply_number == null) {
            $rn = $this->reply_number;
        } else {
            $rn = $reply_number;
        }

        $r = unpack('H2h1/H2h2', pack('I*', $rn));
        $pckt[14] = pack('H*', $r['h1']);
        $pckt[15] = pack('H*', $r['h2']);

        //append data
        if ($data !== null) {
            $pckt = array_merge_recursive($pckt, $data);
        }


        //write size field
        $sf = unpack('H2h1/H2h2', pack('I*', sizeof($pckt) - 8));
        $pckt[4] = pack('H*', $sf['h1']);
        $pckt[5] = pack('H*', $sf['h2']);

        //write checksum
        $cksm = $this->checksum(array_slice($pckt, 8, sizeof($pckt)));
        $cs = unpack('H2h1/H2h2', pack('I*', $cksm));
        $pckt[10] = pack('H*', $cs['h1']);
        $pckt[11] = pack('H*', $cs['h2']);

        $ret = '';

        foreach ($pckt as $pk) {
            $u = unpack("H2", $pk);
            $ret .= $u[1];
        }
        var_dump("create packet " . $ret . " command " . $command);
        $ret = pack("H*", $ret);
        return $ret;
    }

    public function send_packet($packet)
    {
        $sent = socket_send($this->_zkclient, $packet, strlen($packet), 0);
//        echo "Packet Sent " . $sent . "</br></br>";

    }

    public function checksum($payload)
    {
        $j = 1;
        $chk_32b = 0;
        if (sizeof($payload) % 2 == 1) {
            array_push($payload, pack('H*', '00'));
        }
        while ($j < sizeof($payload)) {
            $u = unpack('H2', $payload[$j - 1]);
            $bin = unpack('H2', $payload[$j]);
            $v = hexdec($bin[1]) << 8;
            $num_16b = hexdec($u[1]) + $v;
            $chk_32b += $num_16b;
            $j += 2;
        }

        $chk_32b = ($chk_32b & 0xFFFF) + (($chk_32b & 0xFFFF0000) >> 16);
        $chk_16b = $chk_32b ^ 0xFFFF;

        return $chk_16b;
    }

    public function recv_reply($buffer_size = 1024)
    {
        //TODO -> _data_recv para ma parse nani nmo
        socket_recv($this->_zkclient, $this->_data_recv, $buffer_size, 0);
        var_dump("reply  packet " . unpack("H*", $this->_data_recv)[1]);
        $this->parse_answer($this->_data_recv);
        $this->reply_number += 1;
    }

    public function parse_answer($data = null)
    {
        if (strlen($data == null)) {
            return;
        }
//
        $this->last_reply_code = -1;
        $this->last_session_code = -1;
        $this->last_reply_counter = -1;
        $this->last_payload_data = array();

        //check the start tag
        $datas = str_split($data, 1);
        var_dump('datas ' . $data);
        var_dump('parse  param  ' . unpack('H*', $data)[1]);
        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8/H2h9/H2h10/H2h11/H2h12/H2h13/H2h14/H2h15/H2h16', $data);

        for ($i = 0; $i < 4; $i++) {
            $strt = unpack('H2', pack("I*", TCP::START_TAG[$i]));
            if (unpack('H2', $datas[$i])[1] != $strt[1]) {
                return false;
            }
        }
        $sl = array_slice($datas, 4, 4);
        $size = '';
        foreach ($sl as $s) {
            $size .= unpack("H*", $s)[1];
        }

        $this->last_reply_size = unpack('I', pack("H*", $size))[1];
        var_dump("last_reply_size " . $this->last_reply_size);

        $payload = array_slice($datas, 8, sizeof($datas));

        if (!$this->is_valid_payload($payload)) {
            echo("invalid checksum");
            return false;
        }

        $this->last_packet = $payload;
        $this->last_reply_code = hexdec(unpack('H2', $datas[9])[1] . unpack('H2', $datas[8])[1]);
        $this->last_session_code = hexdec(unpack('H2', $datas[13])[1] . unpack('H2', $datas[12])[1]);
        $this->last_reply_counter = hexdec(unpack('H2', $datas[15])[1] . unpack('H2', $datas[14])[1]);
        $this->last_payload_data = array_slice($datas, 16, sizeof($datas));
    }

    public function is_valid_payload($p)
    {
        if ($this->checksum($p) == 0) {
            return true;
            echo "yes";
        } else {
            echo "atay";
            return false;
        }
    }

    public function set_device_info($param, $new_value)
    {
        $h = array();
        $param .= '=' . $new_value;
        $param = str_split($param, 1);
        foreach ($param as $p) {
            array_push($h, pack('H*', dechex(unpack("C*", $p)[1])));
        }
        array_push($h, pack('H*', 0x00));
        $this->send_command(TCP::CMD_OPTIONS_WRQ, $h);
        $this->recv_reply();
        $ack1 = $this->recvd_ack();
        $this->send_command(TCP::CMD_REFRESH_OPTION);
        $this->recv_reply();
        $ack2 = $this->recvd_ack();
        return $ack1 and $ack2;

    }

    public function recvd_ack()
    {
        var_dump("last_reply " . $this->last_reply_code);
        return $this->last_reply_code == TCP::CMD_ACK_OK;
    }

    public function disable_device($timer = null)
    {
        if ($timer == null) {
            $this->send_command(TCP::CMD_DISABLE_DEVICE);
        } else {
            $t = array(pack('H', dechex($timer)));
            $this->send_command(TCP::CMD_DISABLE_DEVICE, $t);
        }
        $this->recv_reply();
        return $this->recvd_ack();
    }

    public function enable_realtime()
    {
        $data = [0xff, 0xff, 0x00, 0x00];
        $pckt = array();
        foreach ($data as $d) {
            array_push($pckt, pack('I*', $d)); //1-4
        }
        $this->send_command(TCP::CMD_REG_EVENT, $pckt);
        $this->recv_reply();
    }

    public function read_all_user_id()
    {
        $hex = '0109000500000000000000';
        $hex = str_split($hex, 2);
        $data = array();
        foreach ($hex as $h) {
            array_push($data, pack('H*', $h));
        }
        $this->send_command(TCP::CMD_DATA_WRRQ, $data);
        $users_dataset = $this->recv_long_reply();
        $total_size = sizeof($users_dataset);

    }

    public function recv_long_reply($buffer_size = 4096)
    {
        $pckt = $this->recv_packet($buffer_size);
        $this->parse_answer($pckt);
        $this->reply_number += 1;
        $data_set = array();
        if ($this->last_reply_code == (TCP::CMD_DATA)) {
            $data_set = $this->last_payload_data;
        } else if ($this->last_reply_code == TCP::CMD_PREPARE_DATA) {
            $a = $this->recv_packet(16);

        }
        return $data_set;

    }

    public function recv_packet($buffer_size = 1024)
    {
        $recv_data = '';
            if(@socket_recvfrom($this->_zkclient,$recv_data,$buffer_size,0,$this->_ip,$this->_port)){
                var_dump($recv_data);
            }else{
                echo "FALSE";
            }
//
//            var_dump('recv_data'.$recv_data);
////        } else {
////            var_dump(socket_read($this->_zkclient,1024));
////            var_dump('HHALA '.$recv_data.' '.socket_strerror(socket_last_error($this->_zkclient)));
//////            socket_read()
////        }
        return $recv_data;
    }

    public function recv_event()
    {
        $this->parse_answer($this->recv_packet());
        $this->last_event_code = $this->last_session_code;
        $this->send_packet($this->create_packet(TCP::CMD_ACK_OK, array(), 0));
    }

    public function get_last_event()
    {
        return $this->last_event_code;
    }
}
