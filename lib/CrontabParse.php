<?php

/**
 * Created by PhpStorm.
 * User: yuyc
 * Date: 2016/8/19
 * Time: 18:17
 */
class CrontabParse
{

    /**
     * crontab 规则解析
     * @param      $cronRule
     * @param null $starttime
     */
    public function parse($cronRule, $starttime=null) {
        $result = [];
        $pattern = '/^(((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+)?((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i';
        if (!preg_match($pattern, trim($cronRule))) {
            throw new InvalidArgumentException('$cronRule 格式不正确');
        }

        if ($starttime && !is_numeric($starttime)) {
            throw new InvalidArgumentException('$startime must be vaild unix timestamp.');
        }

        $crontab = preg_split('#[\s+]#i', $cronRule);
        $entryCount = count($crontab);
        if (5 == $entryCount) {
            $formatTime = [
                'seconds' => [0],
                'minutes' => $this->parseFormatEntries($crontab[0], 0, 59),
                'hours' => $this->parseFormatEntries($crontab[1], 0, 23),
                'days' => $this->parseFormatEntries($crontab[2], 1, 31),
                'months' => $this->parseFormatEntries($crontab[3], 1, 12),
                'weeks' => $this->parseFormatEntries($crontab[4], 0, 6),
            ];
        }

        if (6 == $entryCount) {
            //单秒位有取值时，分时日月周无意义
            $formatTime = [
                'seconds' => $this->parseFormatEntries($crontab[0], 0, 59),
                'minutes' => $this->parseFormatEntries($crontab[1], 0, 59),
                'hours' => $this->parseFormatEntries($crontab[2], 0, 23),
                'days' => $this->parseFormatEntries($crontab[3], 1, 31),
                'months' => $this->parseFormatEntries($crontab[4], 1, 12),
                'weeks' => $this->parseFormatEntries($crontab[5], 0, 6),
            ];
        }

        for ($i = 1; $i <= 60; $i++) {
            $timestamp = $starttime + $i;
            $ret =  in_array(date('s', $timestamp), $formatTime['seconds']) &&
                    in_array(date('i', $timestamp), $formatTime['minutes']) &&
                    in_array(date('G', $timestamp), $formatTime['hours']) &&
                    in_array(date('d', $timestamp), $formatTime['days']) &&
                    in_array(date('n', $timestamp), $formatTime['months']) &&
                    in_array(date('w', $timestamp), $formatTime['weeks']);
            if ($ret) {
                $result[] = $timestamp;
            }
        }

        return $result;
    }

    /**
     * 解析单个时间实体
     * @param $str 时间实体字符串
     * @param $min 最小时间点
     * @param $max 最大时间点
     */
    protected function parseFormatEntries($str, $min, $max) {
        $result = array();
        $v = explode(',', $str);
        foreach ($v as $vv) {
            $vvv = explode('/', $vv);
            $step = empty($vvv[1]) ? 1 : $vvv[1];
            $vvvv = explode('-', $vvv[0]);
            $_min = count($vvvv) == 2 ? $vvvv[0] : ($vvv[0] == '*' ? $min : $vvv[0]);
            $_max = count($vvvv) == 2 ? $vvvv[1] : ($vvv[0] == '*' ? $max : $vvv[0]);
            for ($i = $_min; $i <= $_max; $i += $step) {
                $result[$i] = intval($i);
            }
        }
        ksort($result);
        return $result;
    }






}