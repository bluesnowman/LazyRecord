<?php
namespace LazyRecord\Schema\SqlBuilder;
use LazyRecord\Schema\SchemaDeclare;
use LazyRecord\QueryDriver;

/**
 * Schema SQL builder
 *
 * @see http://www.sqlite.org/docs.html
 */
class SqliteDriver
    extends BaseDriver
    implements DriverInterface
{

    function buildColumnSql($schema, $column) {      
        $name = $column->name;
        $isa  = $column->isa ?: 'str';
        $type = $column->type;
        if( ! $type && $isa == 'str' )
            $type = 'text';

        $sql = $this->driver->getQuoteColumn( $name );
        $sql .= ' ' . $type;

        if( $column->required || $column->notNull )
            $sql .= ' NOT NULL';
        elseif( $column->null )
            $sql .= ' NULL';

        /**
         * if it's callable, we should not write the result into sql schema 
         */
        if( null !== ($default = $column->default) 
            && ! is_callable($column->default )  ) 
        {
            // for raw sql default value
            if( is_array($default) ) {
                $sql .= ' default ' . $default[0];
            } else {
                $sql .= ' default ' . $this->driver->inflate($default);
            }
        }

        if( $column->primary )
            $sql .= ' primary key';

        if( $column->autoIncrement )
            $sql .= ' autoincrement';

        if( $column->unique )
            $sql .= ' unique';

        // build reference
        // track(
        //		FOREIGN KEY(trackartist) REFERENCES artist(artistid)
        //		artist_id INTEGER REFERENCES artist
        // )
        foreach( $schema->relations as $rel ) {
            switch( $rel['type'] ) {

                // XXX: keep this
            case SchemaDeclare::belongs_to:
                $fs = new $rel['foreign']['schema'];
                $fcName = $rel['foreign']['column'];
                $fc = $fs->columns[$fcName];
                break;

            case SchemaDeclare::has_one:
                if( $rel['self']['column'] == $name ) { 
                    $fs = new $rel['foreign']['schema'];
                    $sql .= ' references ' . $fs->getTable();
                }
                break;
            }
        }

        return $sql;
    }

    public function build(SchemaDeclare $schema, $rebuild = true )
    {
        $sqls = array();

        if( $rebuild ) {
            $sqls[] = 'DROP TABLE IF EXISTS ' 
                . $this->driver->getQuoteTableName( $schema->getTable() );
        }

        $sql = 'CREATE TABLE ' 
            . $this->driver->getQuoteTableName($schema->getTable()) . " ( \n";
        $columnSql = array();
        foreach( $schema->columns as $name => $column ) {
            $columnSql[] = $this->buildColumnSql( $schema, $column );
        }
        $sql .= join(",\n",$columnSql);
        $sql .= "\n);\n";
        $sqls[] = $sql;
        return $sqls;
    }

}