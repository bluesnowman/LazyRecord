<?php
namespace LazyRecord;
use Exception;
use PDOException;
use PDO;
use ArrayAccess;

class SQLQueryException extends Exception 
{
    public $args = array();
    public $sql;

    function __construct( $e , $dsId , $sql , $args ) {
        parent::__construct( 'SQL Query Error at "' . $dsId . '" data source, Message: ' . $e->getMessage() , 0 , $e);
        $this->sql = $sql;
        $this->args = $args;
    }
}

class ConnectionException extends Exception
{

}

/**
 * Connection Manager
 *
 *    $connManager = ConnectionManager::getInstance();
 *    $conn = $connManager->create( '{{id}}', '' );
 *
 *    $conn = $connManager->default(); // return PDO connection object 
 *
 *    $result = $conn->query( );
 *    $stm = $conn->prepare( );
 *    $stm->execute( );
 *
 *    foreach( $connManager as $dataSourceId => $dataSourceConfig ) {
 *
 *    }
 */

class ConnectionManager
    implements ArrayAccess
{


    /**
     * @var array contains data source configurations
     */
    public $datasources = array();

    /**
     * @var PDOConnections[] contains PDO connection objects.
     */
    public $conns = array();

    /**
     * Check if we have connected already
     *
     * @param PDO $conn pdo connection.
     * @param string $id data source id.
     */
    public function has($id)
    {
        return isset($this->conns[$id]);
    }


    /**
     * Add connection
     *
     * @param PDO $conn pdo connection
     * @param string $id data source id
     */
    public function add($conn, $id = 'default' )
    {
        if( isset( $this->conns[ $id ] ) )
            throw new Exception( "$id connection is already defined." );
        $this->conns[ $id ] = $conn;
    }


    /**
     * Add custom data source:
     *
     * source config:
     *
     * @param string $id data source id
     * @param string $config data source config
     */
    public function addDataSource($id,$config)
    {
        $this->datasources[ $id ] = $config;
    }

    public function hasDataSource($id = 'default')
    {
        return isset($this->datasources[ $id ] );
    }

    public function removeDataSource($id) 
    {
        unset( $this->datasource[$id] );
    }

    /**
     * Return datasource id(s)
     *
     * @return array key list
     */
    public function getDataSourceIdList()
    {
        return array_keys($this->datasources);
    }


    /**
     * Get datasource config
     *
     * @return array
     */
    public function getDataSource($id = 'default')
    {
        if( isset($this->datasources[ $id ] ) )
            return $this->datasources[ $id ];
    }


    /**
     * Get SQLBuilder\QueryDriver by data source id.
     *
     * @param string $id datasource name
     * @return LazyRecord\QueryDriver
     */
    public function getQueryDriver($id = 'default')
    {
        $self = $this;

        if( QueryDriver::hasInstance($id) ) {
            return QueryDriver::getInstance($id);
        }

        $driver = QueryDriver::getInstance($id);

        // configure query driver type
        if( $driverType = $this->getDriverType($id) ) {
            $conn = $this->getConnection($id);
            $driver->configure('driver',$driverType);
            $driver->quoter = function($string) use ($conn,$id) {
                // It's PDO quote
                return $conn->quote($string);
            };
        }

        // setup query driver options
        $config = isset($this->datasources[ $id ]) ? $this->datasources[ $id ] : null;
        if( $config && isset( $config['query_options'] ) ) {
            $queryOptions = $config['query_options'];
            foreach( $queryOptions as $option => $value ) {
                $driver->configure( $option , $value );
            }
        }

        // always use named parameter
        $driver->configure( 'placeholder', 'named' );
        return $driver;
    }

    public function getDriverType($id)
    {
        $config = $this->getDataSource($id);
        if( isset($config['driver']) ) {
            return $config['driver'];
        }
        if( isset($config['dsn']) ) {
            list($driverType) = explode( ':', $config['dsn'] , 2 );
            return $driverType;
        }
    }

    /**
     * Create connection
     *
     *    $dbh = new PDO('mysql:host=localhost;dbname=test', $user, $pass);
     *
     *    $pdo = new PDO( 
     *          'mysql:host=hostname;dbname=defaultDbName', 
     *          'username', 
     *          'password', 
     *          array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8") 
     *    ); 
     *
     *    $dbh = new PDO('pgsql:dbname=$dbname; host=$host; username=$username; password=$password'); 
     *    $pdo = new PDO( 'sqlite::memory:', null, null, array(PDO::ATTR_PERSISTENT => true) );
     *                     sqlite2:mydb.sq2
     *
     */
    public function getConnection($sourceId = 'default')
    {
        if( isset($this->conns[$sourceId]) ) {
            return $this->conns[$sourceId];
        } elseif( isset($this->datasources[ $sourceId ] ) ) {
            $config = $this->datasources[ $sourceId ];
            $dsn = null;

            if( isset($config['dsn']) ) {
                $dsn = $config['dsn'];
            }
            else { 
                // Build DSN connection string for PDO
                $driver = $config['driver'];
                $params = array();
                if( isset($config['database']) ) {
                    $params[] = 'dbname=' . $config['database'];
                }
                if( isset($config['host']) ) {
                    $params[] = 'host=' . $config['host'];
                }
                $dsn = $driver . ':' . join(';',$params );
            }

            // TODO: use constant() for `connection_options`
            $connectionOptions = isset($config['connection_options'])
                                     ? $config['connection_options'] : array();

            if( 'mysql' === $this->getDriverType($sourceId) ) {
                $connectionOptions[ PDO::MYSQL_ATTR_INIT_COMMAND ] = 'SET NAMES utf8';
            }

            $conn = new PDO( $dsn,
                (isset($config['user']) ? $config['user'] : (isset($config['username']) ? $config['username'] : null)),
                (isset($config['pass']) ? $config['pass'] : (isset($config['password']) ? $config['password'] : null)),
                $connectionOptions
            );

            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // TODO: can we make this optional ?
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // $driver = $this->getQueryDriver($sourceId);
            // register connection to connection pool
            return $this->conns[ $sourceId ] = $conn;
        }
        throw new ConnectionException("data source $sourceId not found.");
    }


    /**
     * Get default data source id
     *
     * @return string 'default'
     */
    public function getDefault()
    {
        return $this->getConnection('default');
    }


    /**
     * Get singleton instance
     */
    static function getInstance()
    {
        static $instance;
        return $instance ? $instance : $instance = new static;
    }


    /**
     * Close connection
     */
    public function close($sourceId)
    {
        if( isset($this->conns[ $sourceId ]) ) {
            $this->conns[ $sourceId ] = null;
            unset( $this->conns[ $sourceId ] );
        }
    }


    /**
     * Close all connections
     */
    public function closeAll()
    {
        foreach( $this->conns as $id => $conn ) {
            $this->close( $id );
        }
    }

    /**
     * free connections,
     * reset data sources
     */
    public function free()
    {
        $this->closeAll();
        $this->datasources = array();
        $this->conns = array();
    }



    /**
     * ArrayAccess interface
     *
     * @param string $name
     * @param mixed $value
     */
    public function offsetSet($name,$value)
    {
        $this->conns[ $name ] = $value;
    }
    

    /**
     * Check if a connection exists.
     *
     * @param string $name
     */
    public function offsetExists($name)
    {
        return isset($this->conns[ $name ]);
    }
    

    /**
     * Get connection by data source id.
     *
     * @param string $name
     */
    public function offsetGet($name)
    {
        return $this->conns[ $name ];
    }

    /**
     *
     */
    public function offsetUnset($name)
    {
        $this->close($name);
    }


    /**
     * get pdo connetion and make a query
     *
     * @param string $sql SQL statement
     */
    public function query($dsId,$sql)
    {
        return $this->getConnection($dsId)->query( $sql );
    }

    public function prepareAndExecute($dsId,$sql,$args = array() )
    {
        try {
            $conn = $this->getConnection($dsId);
            $stm = $conn->prepare( $sql );
            $stm->execute( $args ); // $success 
        } catch( PDOException $e ) {
            throw new SQLQueryException($e,$dsId,$sql,$args);
        }
        // if failed ?
        // if( false === $success ) {  }
        return $stm;
    }

    public function __destruct() 
    {
        $this->free();
    }
}
