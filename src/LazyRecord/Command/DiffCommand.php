<?php
namespace LazyRecord\Command;
use Exception;
use CLIFramework\Command;
use LazyRecord\Schema;
use LazyRecord\Schema\SchemaFinder;
use LazyRecord\ConfigLoader;

class DiffCommand extends Command
{

    public function brief()
    {
        return 'diff database schema.';
    }

    public function options($opts)
    {
        // --data-source
        $opts->add('D|data-source:', 'specify data source id');
    }

    public function execute()
    {
        $options = $this->options;
        $logger = $this->logger;

        $loader = ConfigLoader::getInstance();
        $loader->load();
        $loader->initForBuild();


        $connectionManager = \LazyRecord\ConnectionManager::getInstance();
        $logger->info("Initialize connection manager...");

        // XXX: from config files
        $id = $options->{'data-source'} ?: 'default';
        $conn = $connectionManager->getConnection($id);
        $type = $connectionManager->getDataSourceDriver($id);
        $driver = $connectionManager->getQueryDriver($id);


    }
}
