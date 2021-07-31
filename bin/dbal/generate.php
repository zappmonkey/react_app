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
} else {
    echo "Removeing models from model directory \n";
    foreach (scandir($modelDir) as $file) {
        if (is_file($modelDir.$file)) {
            echo "- Removeing model {$file} \n";
            unlink($modelDir.$file);
        }
    }
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
$manyToManyTables = [];
foreach (scandir($dbalDirectory) as $file) {
    if (strlen($file) < 5 || substr($file, -5) !== '.yaml') {
        continue;
    }
    $tableConfig = Yaml::parseFile($dbalDirectory . $file);
    $tableConfig['index'] = $tableConfig['index'] ?? [];
    echo "Creating table {$tableConfig['name']}\n";
    $table = $schema->createTable($tableConfig['name']);
    if (!empty($tableConfig['relations']['many_to_one'])) {
        foreach ($tableConfig['relations']['many_to_one'] as $relation) {
            $relationTable = $relation['name'] ?? $relation['table'];
            $column = $relationTable . '_id';
            if (empty($tableConfig['columns'][$column])) {
                $tableConfig['columns'][$column] = ['type' => 'integer', 'options' => ['unsigned' => true]];
                $tableConfig['index'][$column] = [$column];
            }
        }
    }
    foreach ($tableConfig['columns'] as $column => $details) {
        echo "- add column {$column} to {$tableConfig['name']} \n";
        $table->addColumn($column, $details['type'], $details['options'] ?? []);
    }
    if ($tableConfig['primary'] ?? false) {
        $table->setPrimaryKey($tableConfig['primary']);
    }
    foreach ($tableConfig['unique'] ?? [] as $unique) {
        $table->addUniqueIndex($unique);
    }

    foreach ($tableConfig['index'] ?? [] as $index) {
        $table->addIndex($index);
    }

    if (!empty($tableConfig['relations']['many_to_one'])) {
        foreach ($tableConfig['relations']['many_to_one'] as $relation) {
            $relationTable = $relation['name'] ?? $relation['table'];
            $column = $relationTable . '_id';
            $table->addForeignKeyConstraint($relation['table'], [$column], [$tableConfig['name'] . '_id']);
        }
    }
    // Check for many to many
    if (!empty($tableConfig['relations']['many_to_many'])) {
        foreach ($tableConfig['relations']['many_to_many'] as $relation) {
            $relationTable = $relation['name'] ?? $relation['table'];
            $tableName = manyToMany($tableConfig['name'], $relationTable);
            if (in_array($tableName, $manyToManyTables)) {
                continue;
            }
            $manyToManyTables[] = $tableName;
            $key1 = $relationTable . '_id';
            $key2 = $tableConfig['name'] . '_id';
            $relationTable = $schema->createTable($tableName);
            $relationTable->addColumn($key1, 'integer', []);
            $relationTable->addColumn($key2, 'integer', []);
            $relationTable->setPrimaryKey([$key1, $key2]);
            echo "Created many_to_many {$tableName}\n";
        }
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
