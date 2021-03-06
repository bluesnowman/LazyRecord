<?php

class TableParserTest extends PHPUnit_Framework_TestCase
{
    function testDrivers()
    {
        $types = array();

        $config = LazyRecord\ConfigLoader::getInstance();
        $config->loadFromSymbol(true); // force load from .lazy.php
        $config->init();


        $conns = LazyRecord\ConnectionManager::getInstance();
        if( $conns->hasDataSource('mysql') )
            $this->driverTest('mysql');
        if( $conns->hasDataSource('pgsql') )
            $this->driverTest('pgsql');
    }


    /**
     * @dataProvider getDrivers
     */
    function driverTest($driverType)
    {
        $conns = LazyRecord\ConnectionManager::getInstance();
        $conn   = $conns->getConnection($driverType);
        $driver = $conns->getQueryDriver($driverType);
        $parser = LazyRecord\TableParser\TableParser::create($driver,$conn);
        ok( $parser );

        $tables = $parser->getTables();
        ok( $tables );
        foreach(  $tables as $table ) {
            ok( $table );
            $schema = $parser->getTableSchema( $table );

            ok( $schema );
            ok( $schema->getColumns() );
        }
    }
}

