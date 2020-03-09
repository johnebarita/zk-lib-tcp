<?php

/**
 * Created by PhpStorm.
 * User: capstonestudent
 * Date: 3/3/2020
 * Time: 7:56 AM
 */
class Realtime extends Thread
{
    public function run()
    {
       echo "test</br>";
       sleep(5);
    }
}
$real = new Realtime();
$real->run();