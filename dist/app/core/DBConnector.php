<?php


class DBConnector
{
    // ------------------------------------------------------------------------- SINGLETON

    /** @var DBConnector */
    protected static $__instance;

    /**
     * @return DBConnector
     */
    public static function instance ()
    {
        // Create instance for the first time
        if ( is_null(self::$__instance) )
            self::$__instance = new DBConnector();
        return self::$__instance;
    }


    // ------------------------------------------------------------------------- CONNECT

    /**
     * PDO Object
     * @var PDO
     */
    protected $_connection;

    /**
     * PDO Object
     * @return PDO
     */
    public function getConnection () { return $this->_connection; }

    /**
     * Connect to database with to database.json credentials.
     */
    public function connect ()
    {
        // Do not connect multiple times
        if ( !is_null($this->_connection) ) return true;

        // Get DB info
        $dbInfo = App::instance()->loadJSON('database-credentials');
        $dsn = $dbInfo['driver'].':host='.$dbInfo['host'];
        $dbName = $dbInfo['name'];

        // Try to connect
        $initDB = false;
        try
        {
            $this->_connection = new PDO( $dsn.';dbname='.$dbName, $dbInfo['user'], $dbInfo['password'] );
        }
        catch ( PDOException $e )
        {
            // This is not a unknown DB error, fatal
            $this->_connection = null;
            if ( $e->getCode() != 1049 ) return false;
            $initDB = true;
        }

        // DB does not exists init DB with schema
        if ( $initDB )
        {
            try
            {
                // Connect
                $this->_connection = new PDO( $dsn, $dbInfo['user'], $dbInfo['password'] );

                // Set PDO error mode to throw exceptions
                $this->_connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

                // Create and use DB
                $this->_connection->query("create database if not exists `$dbName`; use `$dbName`");

                // Fill DB with schema
                $this->_connection->exec( App::instance()->loadText('database-schema', 'sql') );
            }
            catch ( PDOException $e )
            {
                $this->_connection = null;
                return false;
            }
        }

        // Disable PDO exceptions throws to catch them with more safe false returns bellow
        $this->_connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT );

        // Disable query preparation emulation
        // This will force queries to be prepared by MySQL
        // Beware, this may fall-back silently :
        // https://stackoverflow.com/questions/134099/are-pdo-prepared-statements-sufficient-to-prevent-sql-injection
        $this->_connection->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );

        // Connected and DB initialized
        return true;
    }

    // ------------------------------------------------------------------------- QUERY BUILDING

    /**
     * Build params for PDO query :
     * param1=:param1
     * IMPORTANT : Only manage 1 parameter (no and / or yet)
     * TODO : Allow more complex parameters querying
     * @param array $params Key and values to generate query. Values will be ignored.
     * @return string
     */
    protected function buildParamsQuery ( $params )
    {
        $output = '';
        foreach ( $params as $key => $value )
            $output .= $key.'=:'.$key;

        return $output;
    }

    /**
     * Build insert query for PDO :
     * (param1, param2) values (param1=:param1, param2=:param2)
     * @param array $params Key and values to generate query. Values will be ignored.
     * @return string
     */
    protected function buildInsertQuery ( $params )
    {
        $keys = [];
        foreach ( $params as $key => $value )
            $keys[] = ':'.$key;

        $columns = implode(', ', array_keys( $params ));
        $values  = implode(', ', $keys );

        return "($columns) values ($values)";
    }

    // ------------------------------------------------------------------------- GET

    /**
     * Select from connected DB.
     * Will return a PDO statement, not fetched data.
     * @param string $table Table to gather data from.
     * @param array $whereParams Associative array to filter select.
     * @param string $fields Fields to retrieve, default is *, all.
     * @return bool|PDOStatement Returns false if anything goes wrong.
     */
    public function select ( $table, $whereParams, $fields = '*' )
    {
        // Not connected
        if ( is_null($this->_connection) ) return false;

        // Implode fields from array to comma separated string
        if ( is_array($fields) )
            $fields = implode(', ', $fields);

        // Build query
        $whereQuery = $this->buildParamsQuery( $whereParams );

        // Execute statement
        $statement = $this->_connection->prepare("select $fields from $table where $whereQuery");

        if ( $statement === false )
            return false;
        else
        {
            $result = $statement->execute( $whereParams );
            return $result ? $statement : false;
        }
    }

    /**
     * Fetch one row after a select.
     * @param string $table Table to gather data from.
     * @param array $whereParams Associative array to filter select.
     * @param string $fields Fields to retrieve, default is *, all.
     * @return bool|mixed Returns false if anything goes wrong.
     */
    public function fetch ( $table, $whereParams, $fields = '*' )
    {
        $statement = $this->select( $table, $whereParams, $fields );
        return $statement === false ? false : $statement->fetch( PDO::FETCH_ASSOC );
    }

    /**
     * Fetch multiple rows after a select.
     * @param string $table Table to gather data from.
     * @param array $whereParams Associative array to filter select.
     * @param string $fields Fields to retrieve, default is *, all.
     * @return bool|mixed Returns false if anything goes wrong.
     */
    public function fetchAll ( $table, $whereParams, $fields = '*' )
    {
        $statement = $this->select( $table, $whereParams, $fields );
        return $statement === false ? false : $statement->fetchAll( PDO::FETCH_ASSOC );
    }

    // ------------------------------------------------------------------------- ALTER

    /**
     * Insert new row with data.
     * Test === false to check if it failed because first returned ID is 0 ;)
     * @param string $table Table to insert data to.
     * @param array $params Data to insert in new row.
     * @return bool|string Will return last inserted ID if success, or false if anything goes wrong.
     */
    public function insert ( $table, $params )
    {
        // Not connected
        if ( is_null($this->_connection) ) return false;

        // Build query
        $insertQuery = $this->buildInsertQuery( $params );
        $statement = $this->_connection->prepare("insert into $table $insertQuery");

        // Execute statement
        return (
            $statement === false ? false
            : ($statement->execute( $params ) ? $this->_connection->lastInsertId() : false )
        );
    }

    /**
     * Update data from row.
     * @param string $table Table to update data to.
     * @param array $whereParams Associative array to filter select the row to update.
     * @param array $setParams Data to update into selected row.
     * @return bool Will return false if anything goes wrong.
     */
    public function update ( $table, $whereParams, $setParams )
    {
        // Not connected
        if ( is_null($this->_connection) ) return false;

        // Build query
        $whereQuery = $this->buildParamsQuery( $whereParams );
        $setQuery = $this->buildParamsQuery( $setParams );
        $statement = $this->_connection->prepare("update $table set $setQuery where $whereQuery");

        // Execute statement
        return (
            $statement === false ? false
            : $statement->execute( array_merge($setParams, $whereParams) )
        );
    }

    // TODO : Mixed automatic insert and update
    //public function upsert ( $table, $whereParams, $setParams ) { }

    /**
     * Delete a row.
     * @param string $table Table to delete data from.
     * @param array $whereParams Associative array to filter select the row to update.
     * @return bool Will return false if anything goes wrong.
     */
    public function delete ($table, $whereParams )
    {
        // Not connected
        if ( is_null($this->_connection) ) return false;

        // Build query
        $paramsQuery = $this->buildParamsQuery( $whereParams );
        $statement = $this->_connection->prepare("delete from $table where $paramsQuery");

        // Execute statement
        return (
            $statement === false ? false
            : $statement->execute( $whereParams )
        );
    }
}