<?php

if ($argc !=2) {
    throw new DomainException('Please provide a threshold parameter to measure TTLs of redis keys');
}

$threshold = (int) $argv[1];
if ($threshold < 1) {
    throw new DomainException('Threshold parameter must be a stricly positive integer');
}

$redis = new Redis();
$redis->connect('127.0.0.1:6379');

$keys = $redis->keys('md*');

$data = array();

foreach($keys as $key) {
    $metadata = unserialize($redis->get($key));

    $dateString = $metadata[0][1]['date'][0];
    $date = new Datetime($dateString);

    $currentTime =  time() - $date->format('U');
    $data[$currentTime] []= "$currentTime\t$dateString\t$key :\t" . $metadata[0][1]['cache-control'][0] . "\n";
}

ksort($data, SORT_NUMERIC);
foreach($data as $ts => $elem) {
    foreach($elem as $key => $val){
        $side = $ts>$threshold ? '>': '';
        echo "$side\t$key : \t$val";
    }
}
