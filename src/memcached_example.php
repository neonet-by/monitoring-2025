<?php

$memcache = memcache_connect('localhost',11211);

memcache_set($memcache,'test','123',0,10000);
memcache_set($memcache,'test','456',0,10000);
memcache_set($memcache,'test.540','Данные',0,10000);


echo memcache_get($memcache,'test');
echo '<br>';
echo memcache_get($memcache,'test.540');

?>
