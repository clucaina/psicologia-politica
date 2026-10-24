<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/_private/core.php';
require dirname(__DIR__).'/_private/news_ingest.php';
try {
    $summary=pp_news_cron_run();
    fwrite(STDOUT,pp_json(['time'=>pp_now(),...$summary]).PHP_EOL);
    exit(count($summary['errors'])>0?2:0);
} catch(Throwable $e) {
    $message=$e instanceof PPError?$e->getMessage():'Error de ingesta editorial.';
    fwrite(STDERR,pp_json(['time'=>pp_now(),'status'=>'failed','error'=>$message]).PHP_EOL);
    exit(1);
}
