<?php

require_once __DIR__.'/vendor/autoload.php';

$d = new \Geolid\Daemon\Daemon;
$d
    ->setLoopInterval(3000000)
    ->setTtl(10)
;

$d->run(function () {
    echo "hello\n";
});

echo $d->getShutdownReason();
