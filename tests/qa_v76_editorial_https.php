<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
require __DIR__.'/../_private/editorial.php';
$allowed=['https://www.ine.es/dyngs/Prensa/TV0826.htm','https://doi.org/10.1038/s41467-026-78228-y'];
$blocked=['http://ine.es/a','https:/ine.es/a','https://user@ine.es/a','https://localhost/study','https://192.168.0.1/','https://'];
foreach($allowed as $url)if(!pp_editorial_news_source_ready($url))throw new RuntimeException('Valid HTTPS source rejected: '.$url);
foreach($blocked as $url)if(pp_editorial_news_source_ready($url))throw new RuntimeException('Unsafe or unverified source accepted: '.$url);
echo 'PASS V76 scientific news: HTTPS source is required, invalid and local links refused'.PHP_EOL;
