<?php
/**
 * Created by PhpStorm.
 * User: yuyc
 * Date: 2016/8/23
 * Time: 17:47
 */

class Job extends SplHeap {

    private static $_instance = null;

    public static function factory() {
        if (empty(self::$_instance)) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function compare($value1, $value2) {
        if ($value1['time'] === $value2['time']) return 0;
        return ($value1['time'] < $value2['time']) ? 1 : -1;
    }

    /**
     * 设定任务
     * @param $schemeList  本次周期任务列表
     * @param $job  任务对象
     */
    public function set($schemeList, $job) {
        foreach ($schemeList as $item) {
            $job['starttime'] = $item;
            $this->insert(['time' => $item, 'job' => $job]);
        }

        echo 'debug-' . $this->count() . PHP_EOL;
    }

    /**
     * 获取任务列表
     * @param $starttime  获取任务的时间
     */
    public function get() {
        $starttime = time();
        $jobsList = [];
        while ($this->valid()) {
            $jobData = $this->extract();
//            print_r($jobData);
//            echo "\n";
//            echo $starttime;
//            echo "\n";
            if ($jobData['time']  > $starttime) {
                $this->insert($jobData);
                break;
            }
            $jobsList[] = $jobData['job'];
//            print_r($jobsList);
        }

        return $jobsList;
    }

}