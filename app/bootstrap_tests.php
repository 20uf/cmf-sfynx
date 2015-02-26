<?php
require_once __DIR__ . '/bootstrap.php.cache';

use Symfony\Component\Process\PhpProcess;
Phake::setClient(Phake::CLIENT_PHPUNIT);

$process = new PhpProcess('php app/console doctrine:database:drop --force --env=test');
$process->run();

$process = new PhpProcess('php app/console doctrine:database:create --env=test');
$process->run();

$process = new PhpProcess('php app/console doctrine:schema:create --env=test');
$process->run();

$process = new PhpProcess('php app/console doctrine:schema:update --force --env=test');
$process->run();