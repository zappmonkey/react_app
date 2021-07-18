<?php

require __DIR__ . '/../vendor/autoload.php';

use React\Http\Message\Response;

$app = new ReactApp\Base();

$config = new \ReactApp\DBALConfig(\ReactApp\DBALConfig::DRIVER_SQLITE, 'dev.sqlite');
$dbal = $app->getContainer()->make(\ReactApp\DBAL::class, [
    'config' => $config,
]);
$app->setDBAL($dbal);

$app->get('/', function()
{
    return new Response(200, ['Content-Type' => 'text/plain'], 'Hello world');
});

$app->get('/{module}/test/{user_id}[/]', function(string $module, \Drift\DBAL\Connection $connection, int $user_id)
{
    return $connection->query(
        $connection->createQueryBuilder()
            ->select('*')
            ->from('user', 'u')
        )->then(function(\Drift\DBAL\Result $result) {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($result->fetchAllRows()));
    }, function ($error) {
        error_log(print_r($error, true));
    });
});
$app->run();