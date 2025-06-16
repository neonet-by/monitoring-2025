<?php

$memcache = memcache_connect('localhost',11211);

memcache_set($memcache,'test','123',0,10000);
memcache_set($memcache,'test','456',0,10000);
memcache_set($memcache,'test.540','Данные',0,10000);


echo memcache_get($memcache,'test.540');
echo '<br>';
echo memcache_get($memcache,'test');


memcache_set($memcache,'channel.a002.1.sc_error',0,0,10000);
memcache_set($memcache,'channel.a002.1.pes_error',1212,0,10000);
memcache_set($memcache,'channel.a004.2.sc_error',122,0,10000);
memcache_set($memcache,'channel.a004.2.pes_error',711,0,10000);

?>
