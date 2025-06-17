<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$user = $_SESSION['user'];

$memcache = new Memcache();
$memcache->connect('localhost', 11211);


function getStatusClass($value) {
    if ($value === 'N/A') return 'neutral';
    $value = (int)$value;
    if ($value > 1000) return 'critical';
    if ($value > 100) return 'warning';
    return 'normal';
}

$channels = [
    'a002' => 'RTG TV',
    'a004' => 'DRIVE',
    'a003' => 'TEST'
];

$data = [];

foreach ($channels as $id => $name) {
    $activeInputData = null;
    for ($input = 1; $input <= 2; $input++) {
        $prefix = "channel.{$id}.{$input}";

        $sc_error = memcache_get($memcache, "$prefix.sc_error");
        $pes_error = memcache_get($memcache, "$prefix.pes_error");
        $pcr_error = memcache_get($memcache, "$prefix.pcr_error");
        $bitrate = memcache_get($memcache, "$prefix.bitrate");
        $onair = memcache_get($memcache, "$prefix.onair");
        $timestamp = memcache_get($memcache, "$prefix.timestamp");

        $entry = [
            'name' => $name,
            'input' => $input,
            'sc_error' => $sc_error !== false ? $sc_error : 'N/A',
            'pes_error' => $pes_error !== false ? $pes_error : 'N/A',
            'pcr_error' => $pcr_error !== false ? $pcr_error : 'N/A',
            'bitrate' => $bitrate !== false ? $bitrate : 'N/A',
            'onair' => $onair !== false ? $onair : 'N/A',
            'timestamp' => $timestamp !== false ? $timestamp : 'N/A',
        ];

        if ($entry['onair']) {
            $activeInputData = $entry;
            break;  
        }

        
        if ($input === 1 && $activeInputData === null) {
            $activeInputData = $entry;
        }
    }

    if ($activeInputData !== null) {
        $data[] = $activeInputData;
    }
}

include 'template.php';
