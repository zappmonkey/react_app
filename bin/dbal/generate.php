<?php

use ReactApp\Base;
use ReactApp\DBAL\Config;
use ReactApp\DBAL\DBAL;
use Drift\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Twig\TwigFilter;
use React\EventLoop\Loop;

$appRoot = __DIR__ . '/../../../../../';
require $appRoot . 'vendor/autoload.php';

$dbalConfig = Yaml::parseFile($appRoot . 'config/dbal.yaml');

$database = $dbalConfig['config']['database'];
if ($dbalConfig['config']['driver'] === Config::DRIVER_SQLITE) {
    $database = $appRoot. $dbalConfig['config']['database'];
    if (!file_exists($database)) {
        echo "Creating sqlite database\n";
        file_put_contents($database, "");
        chmod($database, 0777);
    }
}

$app = new Base();
$config = new Config(
    $dbalConfig['config']['driver'] ?? Config::DRIVER_MYSQL,
    $database,
    $dbalConfig['config']['host'] ?? '',
    $dbalConfig['config']['port'] ?? 0,
    $dbalConfig['config']['user'] ?? '',
    $dbalConfig['config']['password'] ?? '',
);

$modelDir = $appRoot . 'src/' . $dbalConfig['model']['dir'] . '/';
if (!file_exists($modelDir)) {
    echo "Creating model directory \n";
    mkdir($modelDir, 0777, true);
}

$dbal = $app->getContainer()->make(DBAL::class, [
    'config' => $config,
]);
$app->setDBAL($dbal);

$loader = new FilesystemLoader(__DIR__ . '/assets/');
$twig = new Environment($loader, []);
$twig->addFilter(new TwigFilter('db_to_camel', 'dbToCamel'));
$twig->addFilter(new TwigFilter('to_php_type', 'toPhpType'));
$twig->addFilter(new TwigFilter('return_type', 'returnType'));
$twig->addFilter(new TwigFilter('many_to_many', 'manyToMany'));

/**
 * @var $connection Connection
 */
$connection = $app->getContainer()->get(Connection::class);
$schema = new Schema();
$dbalDirectory = $appRoot . 'config/' . $dbalConfig['model']['config'] . '/';
foreach (scandir($dbalDirectory) as $file) {
    if (strlen($file) < 5 || substr($file, -5) !== '.yaml') {
        continue;
    }
    $tableConfig = Yaml::parseFile($dbalDirectory . $file);
    echo "Creating table {$tableConfig['name']}\n";
    $table = $schema->createTable($tableConfig['name']);
    foreach ($tableConfig['columns'] as $column => $details) {
        $table->addColumn($column, $details['type'], $details['options'] ?? []);
    }
    if ($tableConfig['primary'] ?? false) {
        $table->setPrimaryKey($tableConfig['primary']);
    }
    foreach ($tableConfig['unique'] ?? [] as $unique) {
        $table->addUniqueIndex($unique);
    }
    echo "Creating model {$tableConfig['name']}\n";
    $model = $twig->render('model.twig', ['namespace' => $dbalConfig['namespace'], 'model' => $tableConfig]);
    file_put_contents(dbToCamel($modelDir . dbToCamel($tableConfig['name']) . '.php'), $model);
}

$connection->executeSchema($schema)
    ->then(function($result) {
        echo "Creating schema successful\n";
        echo "installation successful\n";
        Loop::stop();
    }, function($error) {
        echo "Creating schema error\n";
        echo "installation error\n";
        Loop::stop();
    });

$queryBuilder = $connection->createQueryBuilder();
$queryBuilder
    ->select('*')
    ->from('group', 'g')
    ->innerJoin('g', 'group_of_user', 'gu', 'g.group_id = gu.group_id')
    ->where('gu.user_id = ?')
$test = $connection
    ->query($connection->createQueryBuilder())
    ->select('*')
    ->from('test', 't')
    ->where($queryBuilder->expr()->orX(
        $queryBuilder->expr()->eq('t.id', '?'),
        $queryBuilder->expr()->eq('t.id', '?')
    ))
    ->setParameters(['1', '2']);

//$User = new \ZAPPStudio\API\Model\User();
//$User->get(1)
//    ->then(function() use ($User) {
//        echo(print_r(['loaded', $User->getId()], true));
//        $User->delete()->then(function($result) use ($User) {
//            echo(print_r(['deleted', $User->getId()], true));
//        });
//    });
//$User->findBy(['email' => 'mark@zz1tis.nl'])
//    ->then(function(array $users) {
//        echo(print_r($users, true));
////        Loop::stop();
//    }, function($error) {
//        echo "unable to find user\n";
////        Loop::stop();
//    });
//$User->findOneBy(['email' => 'mark@zz1tis.nl'])
//    ->then(function(\ZAPPStudio\API\Model\User $user) {
//        echo(print_r($user, true));
////        Loop::stop();
//    }, function($error) {
//        echo "unable to find user\n";
////        Loop::stop();
//    });
//$User->setEmail('mark@zz1tis.nl');
//$User->setFirstname('Mark');
//$User->setLastname("Freese");
//$User->flush()->then(function($result) {
//    echo(print_r($result, true));
//}, function($error) {
//    echo(print_r($error, true));
//});
//
//$user = $schema->createTable("user");
//$user->addColumn("user_id", "integer", ["unsigned" => true]);
//$user->addColumn("email", "string", ["length" => 256]);
//$user->addColumn("firstname", "string", ["length" => 256]);
//$user->addColumn("lastname", "string", ["length" => 256]);
//$user->addColumn("avatar", "string", ["length" => 256, 'notnull' => false]);
//$user->addColumn("password", "string", ["length" => 256, 'notnull' => false]);
//$user->addColumn("status", "string", ["length" => 256, 'default' => 'ACTIVE']);
//$user->addColumn("token", "string", ["length" => 1024, 'notnull' => false]);
//$user->addColumn("created", "datetime", ["default" => "CURRENT_TIMESTAMP"]);
//$user->addColumn("modified", "datetime", ["default" => "CURRENT_TIMESTAMP"]);
//
//$user->setPrimaryKey(["user_id"]);
//$user->addUniqueIndex(["email"]);
////$schema->createSequence("my_table_seq");
//
////$myForeign = $schema->createTable("my_foreign");
////$myForeign->addColumn("id", "integer");
////$myForeign->addColumn("user_id", "integer");
////$myForeign->addForeignKeyConstraint($user, array("user_id"), array("user_id"), array("onUpdate" => "CASCADE"));
//
//$connection->executeSchema($schema);

function dbToCamel(string $db, bool $firstCharacterUppercase = true)
{
    if (preg_match('/[A-Z]/', $db)) {
        return $db;//upper case letter present. So don't convert
    }
    $nameParts = explode('_', strtolower($db));
    $upperCase = $firstCharacterUppercase;

    $namePartsUppercase = array_map(function($value) use(&$upperCase)
    {
        if($upperCase){
            $value = ucfirst($value);
        }
        $upperCase = true;

        return $value;
    }, $nameParts);

    return implode("", $namePartsUppercase);
}

function toPhpType(string $type)
{
    switch ($type) {
        case 'integer':
            return 'int';
        case 'datetime':
            return '\DateTime';
    }
    return $type;
}

function toDataType(string $type)
{
    switch ($type) {
        case 'integer':
            return 'int';
        case 'datetime':
            return 'string';
    }
    return $type;
}

function manyToMany(string $table1, string $table2): string
{
    $tables = [$table1, $table2];
    sort($tables);
    return sprintf("%s_of_%s", $tables[0], $tables[1]);
}

function returnType(string $column, string $type)
{
    switch ($type) {
        case 'datetime':
            return sprintf('new \DateTime($this->%s);', $column);
    }
    return sprintf('(%s) $this->%s;', toPhpType($type), $column);
}