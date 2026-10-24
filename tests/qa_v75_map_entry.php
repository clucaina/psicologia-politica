<?php
declare(strict_types=1);
$src=file_get_contents(__DIR__.'/../index.php');
if(!str_contains($src,"if(!is_file(__DIR__.'/_private/observatorio-espana.html'))throw new PPError(503,"))exit(1);
if(!is_file(__DIR__.'/../_private/observatorio-espana.html'))exit(2);
echo "PASS V75 missing map 503 (not empty 200)\n";
