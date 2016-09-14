<?php
/**
 * Created by PhpStorm.
 * User: solohin
 * Date: 14.09.16
 * Time: 11:06
 */
namespace solohin;

trait SpeedTest
{
    private $speedTestTimers = [];

    protected function startTimer($label = '')
    {
        $this->speedTestTimers[$label] = microtime(true);
    }

    protected function stopTimer($label = '')
    {
        if (!isset($this->speedTestTimers[$label])) {
            return 0;
        }
        return (microtime(true) - $this->speedTestTimers[$label]);
    }
}