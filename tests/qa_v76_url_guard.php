<?php
declare(strict_types=1);
require __DIR__.'/../_private/core.php';
$valid=['https://www.ine.es/dyngs/Prensa/ECV2025.htm','https://journals.sagepub.com/doi/10.1177/0010000000','http://example.org/article','https://doi.org/10.1111/abc.123?lang=es'];
$invalid=['https:/ine.es/a','https:ine.es','https://','javascript:alert(1)','http://localhost:8080/','http://127.0.0.1/','http://10.0.0.2/','https://192.168.1.1/','https://user:pass@ine.es/a','https://ine.es'.chr(92).'@evil.test','https://www.ine.es/a'.chr(10).'header:bad',"https://ine.es/\t",'  https://ine.es/','https://ine.es/  ','file:///etc/passwd','//ine.es/test'];
foreach($valid as $url)if(pp_url($url)!==$url)throw new RuntimeException('Rejected legitimate external source: '.$url);
foreach($invalid as $url)if(pp_url($url)!=='')throw new RuntimeException('Accepted malformed/unsafe source: '.bin2hex($url));
$links=pp_links(['https:/bad','https://www.ine.es/a','https://www.ine.es/a','http://localhost/a']);
if($links!==['https://www.ine.es/a'])throw new RuntimeException('External link deduplication');
echo 'PASS: V76 external source URL validation'.PHP_EOL;
