<?php

$memcache = new Memcache(); 
$memcache->connect('localhost', 11211); 

$memcache->set('test', '123', 0, 10000);
$memcache->set('test', '456', 0, 10000);
$memcache->set('test.540', 'Данные', 0, 10000);

echo $memcache->get('test');
echo '<br>';
echo $memcache->get('test.540');

?>
