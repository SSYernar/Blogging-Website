<?php 
interface RedBean_Driver
{
	/**
	 * Runs a query and fetches results as a multi dimensional array.
	 *
	 * @param string $sql      SQL to be executed
	 * @param array  $bindings list of values to bind to SQL snippet
	 *
	 * @return array
	 */
	public function GetAll( $sql, $bindings = array() );
	/**
	 * Runs a query and fetches results as a column.
	 *
	 * @param string $sql      SQL Code to execute
	 * @param array  $bindings list of values to bind to SQL snippet
	 *
	 * @return array
	 */
	public function GetCol( $sql, $bindings = array() );
	/**
	 * Runs a query and returns results as a single cell.
	 *
	 * @param string $sql      SQL to execute
	 * @param array  $bindings list of values to bind to SQL snippet
	 *
	 * @return mixed
	 */
	public function GetCell( $sql, $bindings = array() );
	
	/**
	 * Runs a query and returns results as an associative array
	 * indexed by the first column.
	 *
	 * @param string $sql      SQL to execute
	 * @param array  $bindings list of values to bind to SQL snippet
	 *
	 * @return mixed
	 */
	public function GetAssocRow( $sql, $bindings = array() );
	
	/**
	 * Runs a query and returns a flat array containing the values of
	 * one row.
	 *
	 * @param string $sql      SQL to execute
	 * @param array  $bindings list of values to bind to SQL snippet
	 * 
	 * @return array
	 */
	public function GetRow( $sql, $bindings = array() );
	/**
	 * Executes SQL code and allows key-value binding.
	 * This function allows you to provide an array with values to bind
	 * to query parameters. For instance you can bind values to question
	 * marks in the query. Each value in the array corresponds to the
	 * question mark in the query that matches the position of the value in the
	 * array. You can also bind values using explicit keys, for instance
	 * array(":key"=>123) will bind the integer 123 to the key :key in the
	 * SQL. This method has no return value.
	 *
	 * @param string $sql      SQL Code to execute
	 * @param array  $bindings list of values to bind to SQL snippet
	 *
	 * @return array Affected Rows
	 */
	public function Execute( $sql, $bindings = array() );
	/**
	 * Returns the latest insert ID if driver does support this
	 * feature.
	 *
	 * @return integer
	 */
	public function GetInsertID();
	/**
	 * Returns the number of rows affected by the most recent query
	 * if the currently selected driver driver supports this feature.
	 *
	 * @return integer
	 */
	public function Affected_Rows();
	/**
	 * Toggles debug mode. In debug mode the driver will print all
	 * SQL to the screen together with some information about the
	 * results. All SQL code that passes through the driver will be
	 * passes on to the screen for inspection.
	 * This method has no return value.
	 *
	 * @param boolean $trueFalse turn on/off
	 *
	 * @return void
	 */
	public function setDebugMode( $tf );
	/**
	 * Starts a transaction.
	 *
	 * @return void
	 */
	public function CommitTrans();
	/**
	 * Commits a transaction.
	 *
	 * @return void
	 */
	public function StartTrans();
	/**
	 * Rolls back a transaction.
	 *
	 * @return void
	 */
	public function FailTrans();
}
class RedBean_Driver_PDO implements RedBean_Driver
{
	/**
	 * @var string
	 */
	protected $dsn;
	/**
	 * @var boolean
	 */
	protected $debug = FALSE;
	/**
	 * @var RedBean_Logger
	 */
	protected $logger = NULL;
	/**
	 * @var PDO
	 */
	protected $pdo;
	/**
	 * @var integer
	 */
	protected $affectedRows;
	/**
	 * @var integer
	 */
	protected $resultArray;
	/**
	 * @var array
	 */
	protected $connectInfo = array();
	/**
	 * @var boolean
	 */
	protected $isConnected = FALSE;
	/**
	 * @var bool
	 */
	protected $flagUseStringOnlyBinding = FALSE;
	/**
	 * @var string
	 */
	protected $mysqlEncoding = '';
	/**
	 * @var boolean
	 */
	protected $autoSetEncoding = TRUE;
	/**
	 * Binds parameters. This method binds parameters to a PDOStatement for
	 * Query Execution. This method binds parameters as NULL, INTEGER or STRING
	 * and supports both named keys and question mark keys.
	 *
	 * @param  PDOStatement $statement  PDO Statement instance
	 * @param  array        $bindings   values that need to get bound to the statement
	 *
	 * @return void
	 */
	protected function bindParams( $statement, $bindings )
	{
		foreach ( $bindings as $key => &$value ) {
			if ( is_integer( $key ) ) {
				if ( is_null( $value ) ) {
					$statement->bindValue( $key + 1, NULL, PDO::PARAM_NULL );
				} elseif ( !$this->flagUseStringOnlyBinding && RedBean_QueryWriter_AQueryWriter::canBeTreatedAsInt( $value ) && $value < 2147483648 ) {
					$statement->bindParam( $key + 1, $value, PDO::PARAM_INT );
				} else {
					$statement->bindParam( $key + 1, $value, PDO::PARAM_STR );
				}
			} else {
				if ( is_null( $value ) ) {
					$statement->bindValue( $key, NULL, PDO::PARAM_NULL );
				} elseif ( !$this->flagUseStringOnlyBinding && RedBean_QueryWriter_AQueryWriter::canBeTreatedAsInt( $value ) && $value < 2147483648 ) {
					$statement->bindParam( $key, $value, PDO::PARAM_INT );
				} else {
					$statement->bindParam( $key, $value, PDO::PARAM_STR );
				}
			}
		}
	}
	/**
	 * This method runs the actual SQL query and binds a list of parameters to the query.
	 * slots. The result of the query will be stored in the protected property
	 * $rs (always array). The number of rows affected (result of rowcount, if supported by database)
	 * is stored in protected property $affectedRows. If the debug flag is set
	 * this function will send debugging output to screen buffer.
	 *
	 * @param string $sql      the SQL string to be send to database server
	 * @param array  $bindings the values that need to get bound to the query slots
	 *
	 * @return void
	 *
	 * @throws RedBean_Exception_SQL
	 */
	protected function runQuery( $sql, $bindings, $options = array() )
	{
		$this->connect();
		if ( $this->debug && $this->logger ) {
			$this->logger->log( $sql, $bindings );
		}
		try {
			if ( strpos( 'pgsql', $this->dsn ) === 0 ) {
				$statement = $this->pdo->prepare( $sql, array( PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT => TRUE ) );
			} else {
				$statement = $this->pdo->prepare( $sql );
			}
			$this->bindParams( $statement, $bindings );
			$statement->execute();
			$this->affectedRows = $statement->rowCount();
			if ( $statement->columnCount() ) {
				
				$fetchStyle = ( isset( $options['fetchStyle'] ) ) ? $options['fetchStyle'] : NULL;
				
				$this->resultArray = $statement->fetchAll( $fetchStyle );
				if ( $this->debug && $this->logger ) {
					$this->logger->log( 'resultset: ' . count( $this->resultArray ) . ' rows' );
				}
			} else {
				$this->resultArray = array();
			}
		} catch ( PDOException $e ) {
			//Unfortunately the code field is supposed to be int by default (php)
			//So we need a property to convey the SQL State code.
			$err = $e->getMessage();
			if ( $this->debug && $this->logger ) $this->logger->log( 'An error occurred: ' . $err );
			$exception = new RedBean_Exception_SQL( $err, 0 );
			$exception->setSQLState( $e->getCode() );
			throw $exception;
		}
	}
	/**
	* Try to fix MySQL character encoding problems.
	* MySQL < 5.5 does not support proper 4 byte unicode but they
	* seem to have added it with version 5.5 under a different label: utf8mb4.
	* We try to select the best possible charset based on your version data.
	*/
	protected function setEncoding()
	{
		$driver = $this->pdo->getAttribute( PDO::ATTR_DRIVER_NAME );
		$version = floatval( $this->pdo->getAttribute( PDO::ATTR_SERVER_VERSION ) );
		if ( $driver === 'mysql' ) {
			$encoding = ($version >= 5.5) ? 'utf8mb4' : 'utf8';
			$this->pdo->setAttribute( PDO::MYSQL_ATTR_INIT_COMMAND, 'SET NAMES '.$encoding ); //on every re-connect
			$this->pdo->exec(' SET NAMES '. $encoding); //also for current connection
			$this->mysqlEncoding = $encoding;
		}
	}
	/**
	* Returns the best possible encoding for MySQL based on version data.
	*
	* @return string
	*/
	public function getMysqlEncoding()
	{
		return $this->mysqlEncoding;
	}
	/**
	 * Constructor. You may either specify dsn, user and password or
	 * just give an existing PDO connection.
	 * Examples:
	 *    $driver = new RedBean_Driver_PDO($dsn, $user, $password);
	 *    $driver = new RedBean_Driver_PDO($existingConnection);
	 *
	 * @param string|PDO $dsn    database connection string
	 * @param string     $user   optional, usename to sign in
	 * @param string     $pass   optional, password for connection login
	 *
	 */
	public function __construct( $dsn, $user = NULL, $pass = NULL, $autoSetEncoding = TRUE )
	{
		$this->autoSetEncoding = $autoSetEncoding;
		if ( $dsn instanceof PDO ) {
			$this->pdo = $dsn;
			$this->isConnected = TRUE;
			if ( $this->autoSetEncoding !== FALSE ) {
				$this->setEncoding();
			}
			$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			$this->pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
			// make sure that the dsn at least contains the type
			$this->dsn = $this->getDatabaseType();
		} else {
			$this->dsn = $dsn;
			$this->connectInfo = array( 'pass' => $pass, 'user' => $user );
		}
	}
	/**
	 * Whether to bind all parameters as strings.
	 *
	 * @param boolean $yesNo pass TRUE to bind all parameters as strings.
	 *
	 * @return void
	 */
	public function setUseStringOnlyBinding( $yesNo )
	{
		$this->flagUseStringOnlyBinding = (boolean) $yesNo;
	}
	/**
	* Establishes a connection to the database using PHP PDO
	* functionality. If a connection has already been established this
	* method will simply return directly. This method also turns on
	* UTF8 for the database and PDO-ERRMODE-EXCEPTION as well as
	* PDO-FETCH-ASSOC.
	*
	* @throws PDOException
	*
	* @return void
	*/
	public function connect()
   {
		if ( $this->isConnected ) return;
   	try {
			$user = $this->connectInfo['user'];
   		$pass = $this->connectInfo['pass'];
   		$this->pdo = new PDO(
				$this->dsn,
   			$user,
   			$pass,
   			array(PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
   					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
   			)
   		);
			if ( $this->autoSetEncoding !== FALSE ) {
				$this->setEncoding();
			}
			$this->pdo->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, TRUE );
			$this->isConnected = TRUE;
   	} catch ( PDOException $exception ) {
   			$matches = array();
   			$dbname  = ( preg_match( '/dbname=(\w+)/', $this->dsn, $matches ) ) ? $matches[1] : '?';
   			throw new PDOException( 'Could not connect to database (' . $dbname . ').', $exception->getCode() );
   	}
	}
	/**
	 * @see RedBean_Driver::GetAll
	 */
	public function GetAll( $sql, $bindings = array() )
	{
		$this->runQuery( $sql, $bindings );
		return $this->resultArray;
	}
	
	/**
	 * @see Driver::GetAssocRow
	 */
	public function GetAssocRow( $sql, $bindings = array() )
	{
		$this->runQuery( $sql, $bindings, array( 
				'fetchStyle' => PDO::FETCH_ASSOC 
			) 
		);
		
		return $this->resultArray;
	}
	
	/**
	 * @see RedBean_Driver::GetCol
	 */
	public function GetCol( $sql, $bindings = array() )
	{
		$rows = $this->GetAll( $sql, $bindings );
		$cols = array();
		if ( $rows && is_array( $rows ) && count( $rows ) > 0 ) {
			foreach ( $rows as $row ) {
				$cols[] = array_shift( $row );
			}
		}
		return $cols;
	}
	/**
	 * @see RedBean_Driver::GetCell
	 */
	public function GetCell( $sql, $bindings = array() )
	{
		$arr = $this->GetAll( $sql, $bindings );
		$row1 = array_shift( $arr );
		$col1 = array_shift( $row1 );
		return $col1;
	}
	/**
	 * @see RedBean_Driver::GetRow
	 */
	public function GetRow( $sql, $bindings = array() )
	{
		$arr = $this->GetAll( $sql, $bindings );
		return array_shift( $arr );
	}
	/**
	 * @see RedBean_Driver::Excecute
	 */
	public function Execute( $sql, $bindings = array() )
	{
		$this->runQuery( $sql, $bindings );
		return $this->affectedRows;
	}
	/**
	 * @see RedBean_Driver::GetInsertID
	 */
	public function GetInsertID()
	{
		$this->connect();
		return (int) $this->pdo->lastInsertId();
	}
	/**
	 * @see RedBean_Driver::Affected_Rows
	 */
	public function Affected_Rows()
	{
		$this->connect();
		return (int) $this->affectedRows;
	}
	/**
	 * Toggles debug mode. In debug mode the driver will print all
	 * SQL to the screen together with some information about the
	 * results.
	 *
	 * @param boolean        $trueFalse turn on/off
	 * @param RedBean_Logger $logger    logger instance
	 *
	 * @return void
	 */
	public function setDebugMode( $tf, $logger = NULL )
	{
		$this->connect();
		$this->debug = (bool) $tf;
		if ( $this->debug and !$logger ) {
			$logger = new RedBean_Logger_Default();
		}
		$this->setLogger( $logger );
	}
	/**
	 * Injects RedBean_Logger object.
	 * Sets the logger instance you wish to use.
	 *
	 * @param RedBean_Logger $logger the logger instance to be used for logging
	 */
	public function setLogger( RedBean_Logger $logger )
	{
		$this->logger = $logger;
	}
	/**
	 * Gets RedBean_Logger object.
	 * Returns the currently active RedBean_Logger instance.
	 *
	 * @return RedBean_Logger
	 */
	public function getLogger()
	{
		return $this->logger;
	}
	/**
	 * @see RedBean_Driver::StartTrans
	 */
	public function StartTrans()
	{
		$this->connect();
		$this->pdo->beginTransaction();
	}
	/**
	 * @see RedBean_Driver::CommitTrans
	 */
	public function CommitTrans()
	{
		$this->connect();
		$this->pdo->commit();
	}
	/**
	 * @see RedBean_Driver::FailTrans
	 */
	public function FailTrans()
	{
		$this->connect();
		$this->pdo->rollback();
	}
	/**
	 * Returns the name of database driver for PDO.
	 * Uses the PDO attribute DRIVER NAME to obtain the name of the
	 * PDO driver.
	 *
	 * @return string
	 */
	public function getDatabaseType()
	{
		$this->connect();
		return $this->pdo->getAttribute( PDO::ATTR_DRIVER_NAME );
	}
	/**
	 * Returns the version number of the database.
	 *
	 * @return mixed $version version number of the database
	 */
	public function getDatabaseVersion()
	{
		$this->connect();
		return $this->pdo->getAttribute( PDO::ATTR_CLIENT_VERSION );
	}
	/**
	 * Returns the underlying PHP PDO instance.
	 *
	 * @return PDO
	 */
	public function getPDO()
	{
		$this->connect();
		return $this->pdo;
	}
	/**
	 * Closes database connection by destructing PDO.
	 *
	 * @return void
	 */
	public function close()
	{
		$this->pdo         = NULL;
		$this->isConnected = FALSE;
	}
	/**
	 * Returns TRUE if the current PDO instance is connected.
	 *
	 * @return boolean
	 */
	public function isConnected()
	{
		return $this->isConnected && $this->pdo;
	}
}
class RedBean_OODBBean implements IteratorAggregate, ArrayAccess, Countable
{
	/**
	 * Setting: use beautiful columns, i.e. turn camelcase column names into snake case column names
	 * for database.
	 *
	 * @var boolean
	 */
	private static $flagUseBeautyCols = TRUE;
	/**
	 * Setting: use IDs as keys when exporting. By default this has been turned off because exports
	 * to Javascript may cause problems due to Javascript Sparse Array implementation (i.e. causing large arrays
	 * with lots of 'gaps').
	 *
	 * @var boolean
	 */
	private static $flagKeyedExport = FALSE;
	/**
	 * Whether to skip beautification of columns or not.
	 * 
	 * @var boolean
	 */
	private $flagSkipBeau = FALSE;
	/**
	 * This is where the real properties of the bean live. They are stored and retrieved
	 * by the magic getter and setter (__get and __set).
	 *
	 * @var array $properties
	 */
	private $properties = array();
	/**
	 * Here we keep the meta data of a bean.
	 *
	 * @var array
	 */
	private $__info = array();
	/**
	 * The BeanHelper allows the bean to access the toolbox objects to implement
	 * rich functionality, otherwise you would have to do everything with R or
	 * external objects.
	 *
	 * @var RedBean_BeanHelper
	 */
	private $beanHelper = NULL;
	/**
	 * @var null
	 */
	private $fetchType = NULL;
	/**
	 * @var string
	 */
	private $withSql = '';
	/**
	 * @var array
	 */
	private $withParams = array();
	/**
	 * @var string
	 */
	private $aliasName = NULL;
	/**
	 * @var string
	 */
	private $via = NULL;
	
	/**
	 * @var boolean
	 */
	private $writeOnly = false;
	/** Returns the alias for a type
	 *
	 * @param string $type type
	 *
	 * @return string $type type
	 */
	private function getAlias( $type )
	{
		if ( $this->fetchType ) {
			$type            = $this->fetchType;
			$this->fetchType = NULL;
		}
		return $type;
	}
	/**
	 * Internal method.
	 * Obtains a shared list for a certain type.
	 *
	 * @param string $type the name of the list you want to retrieve.
	 *
	 * @return array
	 */
	private function getSharedList( $type, $redbean, $toolbox )
	{
		$writer = $toolbox->getWriter();
		if ( $this->via ) {
			$oldName = $writer->getAssocTable( array( $this->__info['type'], $type ) );
			if ( $oldName !== $this->via ) {
				//set the new renaming rule
				$writer->renameAssocTable( $oldName, $this->via );
				$this->via = NULL;
			}
		}
		$type             = $this->beau( $type );
		$assocManager     = $redbean->getAssociationManager();
		$beans            = $assocManager->relatedSimple( $this, $type, $this->withSql, $this->withParams );
		$this->withSql    = '';
		$this->withParams = array();
		return $beans;
	}
	/**
	 * Internal method.
	 * Obtains the own list of a certain type.
	 *
	 * @param string $type name of the list you want to retrieve
	 *
	 * @return array
	 */
	private function getOwnList( $type, $redbean )
	{
		$type = $this->beau( $type );
		if ( $this->aliasName ) {
			$parentField = $this->aliasName;
			$myFieldLink = $parentField . '_id';
			$this->__info['sys.alias.' . $type] = $this->aliasName;
			$this->aliasName = NULL;
		} else {
			$parentField = $this->__info['type'];
			$myFieldLink = $parentField . '_id';
		}
		$beans = array();
		if ( $this->getID() > 0 ) {
			$firstKey = NULL;
			if ( count( $this->withParams ) > 0 ) {
				reset( $this->withParams );
				$firstKey = key( $this->withParams );
			}
			if ( !is_numeric( $firstKey ) || $firstKey === NULL ) {
				$bindings           = $this->withParams;
				$bindings[':slot0'] = $this->getID();
				$beans = $redbean->find( $type, array(), " $myFieldLink = :slot0 " . $this->withSql, $bindings );
			} else {
				$bindings = array_merge( array( $this->getID() ), $this->withParams );
				$beans = $redbean->find( $type, array(), " $myFieldLink = ? " . $this->withSql, $bindings );
			}
		}
		$this->withSql    = '';
		$this->withParams = array();
		foreach ( $beans as $beanFromList ) {
			$beanFromList->__info['sys.parentcache.' . $parentField] = $this;
		}
		return $beans;
	}
	/**
	 * By default own-lists and shared-lists no longer have IDs as keys (3.3+),
	 * this is because exportAll also does not offer this feature and we want the
	 * ORM to be more consistent. Also, exporting without keys makes it easier to
	 * export lists to Javascript because unlike in PHP in JS arrays will fill up gaps.
	 *
	 * @param boolean $yesNo
	 *
	 * @return void
	 */
	public static function setFlagKeyedExport( $flag )
	{
		self::$flagKeyedExport = (boolean) $flag;
	}
	/**
	 * Flag indicates whether column names with CamelCase are supported and automatically
	 * converted; example: isForSale -> is_for_sale
	 *
	 * @param boolean
	 *
	 * @return void
	 */
	public static function setFlagBeautifulColumnNames( $flag )
	{
		self::$flagUseBeautyCols = (boolean) $flag;
	}
	/**
	 * Initializes a bean. Used by OODB for dispensing beans.
	 * It is not recommended to use this method to initialize beans. Instead
	 * use the OODB object to dispense new beans. You can use this method
	 * if you build your own bean dispensing mechanism.
	 *
	 * @param string             $type       type of the new bean
	 * @param RedBean_BeanHelper $beanhelper bean helper to obtain a toolbox and a model
	 *
	 * @return void
	 */
	public function initializeForDispense( $type, RedBean_BeanHelper $beanhelper )
	{
		$this->beanHelper         = $beanhelper;
		$this->__info['type']     = $type;
		$this->__info['sys.id']   = 'id';
		$this->__info['sys.orig'] = array( 'id' => 0 );
		$this->__info['tainted']  = TRUE;
		$this->properties['id']   = 0;
	}
	/**
	 * Sets the Bean Helper. Normally the Bean Helper is set by OODB.
	 * Here you can change the Bean Helper. The Bean Helper is an object
	 * providing access to a toolbox for the bean necessary to retrieve
	 * nested beans (bean lists: ownBean, sharedBean) without the need to
	 * rely on static calls to the facade (or make this class dep. on OODB).
	 *
	 * @param RedBean_BeanHelper $helper
	 *
	 * @return void
	 */
	public function setBeanHelper( RedBean_BeanHelper $helper )
	{
		$this->beanHelper = $helper;
	}
	/**
	 * Returns an ArrayIterator so you can treat the bean like
	 * an array with the properties container as its contents.
	 * This method is meant for PHP and allows you to access beans as if
	 * they were arrays, i.e. using array notation:
	 * 
	 * $bean[ $key ] = $value;
	 * 
	 * Note that not all PHP functions work with the array interface.
	 *
	 * @return ArrayIterator
	 */
	public function getIterator()
	{
		return new ArrayIterator( $this->properties );
	}
	/**
	 * Imports all values from an associative array $array. Chainable.
	 * This method imports the values in the first argument as bean
	 * propery and value pairs. Use the second parameter to provide a
	 * selection. If a selection array is passed, only the entries
	 * having keys mentioned in the selection array will be imported.
	 * Set the third parameter to TRUE to preserve spaces in selection keys.
	 *
	 * @param array        $array     what you want to import
	 * @param string|array $selection selection of values
	 * @param boolean      $notrim    if TRUE selection keys will NOT be trimmed
	 *
	 * @return RedBean_OODBBean
	 */
	public function import( $array, $selection = FALSE, $notrim = FALSE )
	{
		if ( is_string( $selection ) ) {
			$selection = explode( ',', $selection );
		}
		if ( !$notrim && is_array( $selection ) ) {
			foreach ( $selection as $key => $selected ) {
				$selection[$key] = trim( $selected );
			}
		}
		foreach ( $array as $key => $value ) {
			if ( $key != '__info' ) {
				if ( !$selection || ( $selection && in_array( $key, $selection ) ) ) {
					$this->$key = $value;
				}
			}
		}
		return $this;
	}
	/**
	 * Imports data from another bean. Chainable.
	 * Copies the properties from the source bean to the internal
	 * property list.
	 *
	 * @param RedBean_OODBBean $sourceBean the source bean to take properties from
	 *
	 * @return RedBean_OODBBean
	 */
	public function importFrom( RedBean_OODBBean $sourceBean )
	{
		$this->__info['tainted'] = TRUE;
		$this->properties = $sourceBean->properties;
		return $this;
	}
	/**
	 * Injects the properties of another bean but keeps the original ID.
	 * Just like import() but keeps the original ID.
	 * Chainable.
	 *
	 * @param RedBean_OODBBean $otherBean the bean whose properties you would like to copy
	 *
	 * @return RedBean_OODBBean
	 */
	public function inject( RedBean_OODBBean $otherBean )
	{
		$myID = $this->properties['id'];
		$this->import( $otherBean->export() );
		$this->id = $myID;
		return $this;
	}
	/**
	 * Exports the bean as an array.
	 * This function exports the contents of a bean to an array and returns
	 * the resulting array.
	 *
	 * @param boolean $meta    set to TRUE if you want to export meta data as well
	 * @param boolean $parents set to TRUE if you want to export parents as well
	 * @param boolean $onlyMe  set to TRUE if you want to export only this bean
	 * @param array   $filters optional whitelist for export
	 *
	 * @return array
	 */
	public function export( $meta = FALSE, $parents = FALSE, $onlyMe = FALSE, $filters = array() )
	{
		$arr = array();
		if ( $parents ) {
			foreach ( $this as $key => $value ) {
				if ( substr( $key, -3 ) != '_id' ) continue;
				$prop = substr( $key, 0, strlen( $key ) - 3 );
				$this->$prop;
			}
		}
		$hasFilters = is_array( $filters ) && count( $filters );
		foreach ( $this as $key => $value ) {
			if ( !$onlyMe && is_array( $value ) ) {
				$vn = array();
				foreach ( $value as $i => $b ) {
					if ( is_numeric( $i ) && !self::$flagKeyedExport ) {
						$vn[] = $b->export( $meta, FALSE, FALSE, $filters );
					} else {
						$vn[$i] = $b->export( $meta, FALSE, FALSE, $filters );
					}
					$value = $vn;
				}
			} elseif ( $value instanceof RedBean_OODBBean ) {
				if ( $hasFilters ) {
					if ( !in_array( strtolower( $value->getMeta( 'type' ) ), $filters ) ) continue;
				}
				$value = $value->export( $meta, $parents, FALSE, $filters );
			}
			$arr[$key] = $value;
		}
		if ( $meta ) {
			$arr['__info'] = $this->__info;
		}
		return $arr;
	}
	/**
	 * Exports the bean to an object.
	 * This method exports the bean data to the specified object.
	 * Only scalar values will be exported by this method.
	 *
	 * @param object $obj target object
	 *
	 * @return array
	 */
	public function exportToObj( $object )
	{
		foreach ( $this->properties as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$object->$key = $value;
			}
		}
	}
	/**
	 * Implements isset() function for use as an array.
	 *
	 * @param string $property name of the property you want to check
	 *
	 * @return boolean
	 */
	public function __isset( $property )
	{
		return isset( $this->properties[$property] );
	}
	/**
	 * Returns the ID of the bean no matter what the ID field is.
	 *
	 * @return string|null
	 */
	public function getID()
	{
		return ( isset( $this->properties['id'] ) ) ? (string) $this->properties['id'] : NULL;
	}
	/**
	 * Unsets a property. This method will load the property first using
	 * __get.
	 *
	 * @param  string $property property
	 *
	 * @return void
	 */
	public function __unset( $property )
	{
		$this->writeOnly = true;
		$this->__get( $property );
		$this->writeOnly = false;
		$fieldLink = $property . '_id';
		if ( isset( $this->$fieldLink ) ) {
			//wanna unset a bean reference?
			$this->$fieldLink = NULL;
		}
		if ( ( isset( $this->properties[$property] ) ) ) {
			unset( $this->properties[$property] );
		}
	}
	/**
	 * Removes a property from the properties list without invoking
	 * an __unset on the bean.
	 *
	 * @param  string $property property that needs to be unset
	 *
	 * @return void
	 */
	public function removeProperty( $property )
	{
		unset( $this->properties[$property] );
	}
	/**
	 * Adds WHERE clause conditions to ownList retrieval.
	 * For instance to get the pages that belong to a book you would
	 * issue the following command: $book->ownPage
	 * However, to order these pages by number use:
	 *
	 * $book->with(' ORDER BY `number` ASC ')->ownPage
	 *
	 * the additional SQL snippet will be merged into the final
	 * query.
	 *
	 * @param string|RedBean_SQLHelper $sql      SQL to be added to retrieval query.
	 * @param array                    $bindings array with parameters to bind to SQL snippet
	 *
	 * @return RedBean_OODBBean
	 */
	public function with( $sql, $bindings = array() )
	{
		if ( $sql instanceof RedBean_SQLHelper ) {
			list( $this->withSql, $this->withParams ) = $sql->getQuery();
		} else {
			$this->withSql    = $sql;
			$this->withParams = $bindings;
		}
		return $this;
	}
	/**
	 * Just like with(). Except that this method prepends the SQL query snippet
	 * with AND which makes it slightly more comfortable to use a conditional
	 * SQL snippet. For instance to filter an own-list with pages (belonging to
	 * a book) on specific chapters you can use:
	 *
	 * $book->withCondition(' chapter = 3 ')->ownPage
	 *
	 * This will return in the own list only the pages having 'chapter == 3'.
	 *
	 * @param string|RedBean_SQLHelper $sql      SQL to be added to retrieval query (prefixed by AND)
	 * @param array                    $bindings array with parameters to bind to SQL snippet
	 *
	 * @return RedBean_OODBBean
	 */
	public function withCondition( $sql, $bindings = array() )
	{
		if ( $sql instanceof RedBean_SQLHelper ) {
			list( $sql, $bindings ) = $sql->getQuery();
		}
		$this->withSql    = ' AND ' . $sql;
		$this->withParams = $bindings;
		return $this;
	}
	/**
	 * Prepares an own-list to use an alias. This is best explained using
	 * an example. Imagine a project and a person. The project always involves
	 * two persons: a teacher and a student. The person beans have been aliased in this
	 * case, so to the project has a teacher_id pointing to a person, and a student_id
	 * also pointing to a person. Given a project, we obtain the teacher like this:
	 *
	 * $project->fetchAs('person')->teacher;
	 *
	 * Now, if we want all projects of a teacher we cant say:
	 *
	 * $teacher->ownProject
	 *
	 * because the $teacher is a bean of type 'person' and no project has been
	 * assigned to a person. Instead we use the alias() method like this:
	 *
	 * $teacher->alias('teacher')->ownProject
	 *
	 * now we get the projects associated with the person bean aliased as
	 * a teacher.
	 *
	 * @param string $aliasName the alias name to use
	 *
	 * @return RedBean_OODBBean
	 */
	public function alias( $aliasName )
	{
		$this->aliasName = $this->beau( $aliasName );
		return $this;
	}
	/**
	 * Returns properties of bean as an array.
	 * This method returns the raw internal property list of the
	 * bean. Only use this method for optimization purposes. Otherwise
	 * use the export() method to export bean data to arrays.
	 *
	 * @return array
	 */
	public function getProperties()
	{
		return $this->properties;
	}
	/**
	 * Turns a camelcase property name into an underscored property name.
	 * Examples:
	 *    oneACLRoute -> one_acl_route
	 *    camelCase -> camel_case
	 *
	 * Also caches the result to improve performance.
	 *
	 * @param string $property
	 *
	 * @return string
	 */
	public function beau( $property )
	{
		static $beautifulColumns = array();
		if ( !self::$flagUseBeautyCols ) return $property;
		if ( ctype_lower( $property ) ) return $property;
		if (
			strpos( $property, 'own' ) === 0
			|| strpos( $property, 'shared' ) === 0
		) {
			return $property;
		}
		if ( !isset( $beautifulColumns[$property] ) ) {
			$beautifulColumns[$property] = strtolower( preg_replace( '/(?<=[a-z])([A-Z])|([A-Z])(?=[a-z])/', '_$1$2', $property ) );
		}
		return $beautifulColumns[$property];
	}
	/**
	 * Clears state.
	 * Internal method. Clears the state of the query modifiers of the bean.
	 * Query modifiers are: with(), withCondition(), alias() and fetchAs().
	 * 
	 * @return void
	 */
	private function clear() {
		$this->withSql    = '';
		$this->withParams = array();
		$this->aliasName  = NULL;
		$this->fetchType  = NULL;
	}
	/**
	 * Magic Getter. Gets the value for a specific property in the bean.
	 * If the property does not exist this getter will make sure no error
	 * occurs. This is because RedBean allows you to query (probe) for
	 * properties. If the property can not be found this method will
	 * return NULL instead.
	 *
	 * @param string $property name of the property you wish to obtain the value of
	 *
	 * @return mixed
	 */
	public function &__get( $property )
	{
		if ( !$this->flagSkipBeau ) $property = $this->beau( $property );
		if ( $this->beanHelper ) {
			list( $redbean, , , $toolbox ) = $this->beanHelper->getExtractedToolbox();
		}
		$isOwn    = strpos( $property, 'own' ) === 0 && ctype_upper( substr( $property, 3, 1 ) );
		$isShared = strpos( $property, 'shared' ) === 0 && ctype_upper( substr( $property, 6, 1 ) );
		if ($isOwn) $listName = lcfirst( substr( $property, 3 ) );
		$hasAlias = (!is_null($this->aliasName));
		$differentAlias = ($hasAlias && $isOwn && isset($this->__info['sys.alias.'.$listName])) ?
				  ($this->__info['sys.alias.'.$listName] !== $this->aliasName) : FALSE;
		$hasSQL = ($this->withSql !== '');
		$exists = isset( $this->properties[$property] );
		if ($exists && !$isOwn && !$isShared) {
			$this->clear();
			return $this->properties[$property];
		}
		if ($exists && !$hasSQL && !$differentAlias) {
			$this->clear();
			return $this->properties[$property];
		}
		$fieldLink = $property . '_id';
		if ( isset( $this->$fieldLink ) && $fieldLink !== $this->getMeta( 'sys.idfield' ) ) {
			$this->__info['tainted'] = TRUE;
			$bean = NULL;
			if ( isset( $this->__info["sys.parentcache.$property"] ) ) {
				$bean = $this->__info["sys.parentcache.$property"];
			}
			if ( $this->writeOnly ) {
				$this->clear();
				$NULL = null;
				return $NULL;
			}
			if ( !$bean ) {
				$type = $this->getAlias( $property );
				if ( $this->withSql !== '' ) {
					$beans = $redbean->find(
							  $type,
							  array( 'id' => array( $this->properties[$fieldLink] ) ),
							  $this->withSql, $this->withParams );
					$bean             = ( empty( $beans ) ) ? NULL : reset( $beans );
					$this->withSql    = '';
					$this->withParams = '';
				} else {
					$bean = $redbean->load( $type, $this->properties[$fieldLink] );
				}
			}
			$this->properties[$property] = $bean;
			$this->clear();
			return $this->properties[$property];
		}
		if ( $isOwn || $isShared ) {
			if ( $isOwn ) {
				$beans = $this->getOwnList( $listName, $redbean );
			} else {
				$beans = $this->getSharedList( lcfirst( substr( $property, 6 ) ), $redbean, $toolbox );
			}
			$this->properties[$property] = $beans;
			$this->__info["sys.shadow.$property"] = $beans;
			$this->__info['tainted']              = TRUE;
			$this->clear();
			return $this->properties[$property];
		}
		$this->clear();
		$NULL = NULL;
		return $NULL;
	}
	/**
	 * Magic Setter. Sets the value for a specific property.
	 * This setter acts as a hook for OODB to mark beans as tainted.
	 * The tainted meta property can be retrieved using getMeta("tainted").
	 * The tainted meta property indicates whether a bean has been modified and
	 * can be used in various caching mechanisms.
	 *
	 * @param string $property name of the property you wish to assign a value to
	 * @param  mixed $value    the value you want to assign
	 *
	 * @return void
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function __set( $property, $value )
	{
		$property = $this->beau( $property );
		$this->flagSkipBeau = TRUE;
		$this->writeOnly = true;
		$this->__get( $property );
		$this->writeOnly = false;
		$this->flagSkipBeau = FALSE;
		$this->setMeta( 'tainted', TRUE );
		if (isset( $this->properties[$property.'_id'] )
			&& !( $value instanceof RedBean_OODBBean )
		) {
			if ( is_null( $value ) || $value === FALSE ) {
				$this->__unset( $property );
				return;
			} else {
				throw new RedBean_Exception_Security( 'Cannot cast to bean.' );
			}
		}
		if ( $value === FALSE ) {
			$value = '0';
		} elseif ( $value === TRUE ) {
			$value = '1';
		} elseif ( $value instanceof DateTime ) {
			$value = $value->format( 'Y-m-d H:i:s' );
		}
		$this->properties[$property] = $value;
	}
	/**
	 * Sets a property directly, for internal use only.
	 *
	 * @param string  $property     property
	 * @param mixed   $value        value
	 * @param boolean $updateShadow whether you want to update the shadow
	 * @param boolean $taint        whether you want to mark the bean as tainted
	 *
	 * @return void
	 */
	public function setProperty( $property, $value, $updateShadow = FALSE, $taint = FALSE )
	{
		$this->properties[$property] = $value;
		if ( $updateShadow ) {
			$this->__info['sys.shadow.' . $property] = $value;
		}
		if ( $taint ) {
			$this->__info['tainted'] = TRUE;
		}
	}
	/**
	 * Returns the value of a meta property. A meta property
	 * contains extra information about the bean object that will not
	 * get stored in the database. Meta information is used to instruct
	 * RedBean as well as other systems how to deal with the bean.
	 * For instance: $bean->setMeta("buildcommand.unique", array(
	 * array("column1", "column2", "column3") ) );
	 * Will add a UNIQUE constraint for the bean on columns: column1, column2 and
	 * column 3.
	 * To access a Meta property we use a dot separated notation.
	 * If the property cannot be found this getter will return NULL instead.
	 *
	 * @param string $path    path
	 * @param mixed  $default default value
	 *
	 * @return mixed
	 */
	public function getMeta( $path, $default = NULL )
	{
		return ( isset( $this->__info[$path] ) ) ? $this->__info[$path] : $default;
	}
	/**
	 * Stores a value in the specified Meta information property. $value contains
	 * the value you want to store in the Meta section of the bean and $path
	 * specifies the dot separated path to the property. For instance "my.meta.property".
	 * If "my" and "meta" do not exist they will be created automatically.
	 *
	 * @param string $path  path
	 * @param mixed  $value value
	 *
	 * @return RedBean_OODBBean
	 */
	public function setMeta( $path, $value )
	{
		$this->__info[$path] = $value;
		return $this;
	}
	/**
	 * Copies the meta information of the specified bean
	 * This is a convenience method to enable you to
	 * exchange meta information easily.
	 *
	 * @param RedBean_OODBBean $bean
	 *
	 * @return RedBean_OODBBean
	 */
	public function copyMetaFrom( RedBean_OODBBean $bean )
	{
		$this->__info = $bean->__info;
		return $this;
	}
	/**
	 * Sends the call to the registered model.
	 *
	 * @param string $method name of the method
	 * @param array  $args   argument list
	 *
	 * @return mixed
	 */
	public function __call( $method, $args )
	{
		if ( !isset( $this->__info['model'] ) ) {
			$model = $this->beanHelper->getModelForBean( $this );
			if ( !$model ) {
				return NULL;
			}
			$this->__info['model'] = $model;
		}
		if ( !method_exists( $this->__info['model'], $method ) ) {
			return NULL;
		}
		return call_user_func_array( array( $this->__info['model'], $method ), $args );
	}
	/**
	 * Implementation of __toString Method
	 * Routes call to Model. If the model implements a __toString() method this
	 * method will be called and the result will be returned. In case of an 
	 * echo-statement this result will be printed. If the model does not
	 * implement a __toString method, this method will return a JSON
	 * representation of the current bean.
	 *
	 * @return string
	 */
	public function __toString()
	{
		$string = $this->__call( '__toString', array() );
		if ( $string === NULL ) {
			return json_encode( $this->properties );
		} else {
			return $string;
		}
	}
	/**
	 * Implementation of Array Access Interface, you can access bean objects
	 * like an array.
	 * Call gets routed to __set.
	 *
	 * @param  mixed $offset offset string
	 * @param  mixed $value  value
	 *
	 * @return void
	 */
	public function offsetSet( $offset, $value )
	{
		$this->__set( $offset, $value );
	}
	/**
	 * Implementation of Array Access Interface, you can access bean objects
	 * like an array.
	 *
	 * @param  mixed $offset property
	 *
	 * @return boolean
	 */
	public function offsetExists( $offset )
	{
		return isset( $this->properties[$offset] );
	}
	/**
	 * Implementation of Array Access Interface, you can access bean objects
	 * like an array.
	 * Unsets a value from the array/bean.
	 *
	 * @param  mixed $offset property
	 *
	 * @return void
	 */
	public function offsetUnset( $offset )
	{
		unset( $this->properties[$offset] );
	}
	/**
	 * Implementation of Array Access Interface, you can access bean objects
	 * like an array.
	 * Returns value of a property.
	 *
	 * @param  mixed $offset property
	 *
	 * @return mixed
	 */
	public function offsetGet( $offset )
	{
		return $this->__get( $offset );
	}
	/**
	 * Chainable method to cast a certain ID to a bean; for instance:
	 * $person = $club->fetchAs('person')->member;
	 * This will load a bean of type person using member_id as ID.
	 *
	 * @param  string $type preferred fetch type
	 *
	 * @return RedBean_OODBBean
	 */
	public function fetchAs( $type )
	{
		$this->fetchType = $type;
		return $this;
	}
	/**
	 * For polymorphic bean relations.
	 * Same as fetchAs but uses a column instead of a direct value.
	 *
	 * @param string $column
	 *
	 * @return RedBean_OODBBean
	 */
	public function poly( $field )
	{
		return $this->fetchAs( $this->$field );
	}
	/**
	 * Treats the bean like a node in a tree and searches for all
	 * nested or parent beans.
	 *
	 * To get all parent pages of a page:
	 *
	 * $parentPages = $page->searchIn('page');
	 *
	 * To get all child pages:
	 *
	 * $pages = $parentPage->searchIn('ownPage');
	 *
	 * When searching in lists you can use SQL snippets in withCondition():
	 *
	 * $pages = $parentPage
	 *     ->withCondition(' rank = ? ', array($rank))
	 *     ->searchIn('ownPage');
	 *
	 * Also works with alias() and fetchAs().
	 * Note that shared lists are NOT supported.
	 *
	 * @param string $property property/list to search
	 *
	 * @return array
	 */
	public function searchIn($property)
	{
		if ( strpos( $property, 'shared' ) === 0 && ctype_upper( substr( $property, 6, 1 ) ) ) {
			throw new RedBean_Exception_Security( 'Cannot search a shared list recursively.' );
		}
		$oldFetchType = $this->fetchType;
		$oldAliasName = $this->aliasName;
		$oldWith      = $this->withSql;
		$oldBindings  = $this->withParams;
		unset( $this->__info["sys.parentcache.$property"] );
		$beanOrBeans  = $this->$property;
		if ( $beanOrBeans instanceof RedBean_OODBBean ) {
			$bean  = $beanOrBeans;
			$key   = $bean->properties['id'];
			$beans = array( $key => $bean );
		} elseif ( is_null( $beanOrBeans ) ) {
			$beans = array();
		} else {
			$beans = $beanOrBeans;
		}
		unset( $this->properties[$property] );
		unset( $this->__info["sys.shadow.$property"] );
		if ( $oldWith === '' ) {
			$ufbeans = $beans;
		} else {
			$this->fetchType = $oldFetchType;
			$this->aliasName = $oldAliasName;
			$ufbeans         = $this->$property;
			if ( is_null( $ufbeans ) ) $ufbeans = array();
			if ( $ufbeans instanceof RedBean_OODBBean ) $ufbeans = array( $ufbeans );
		}
		foreach( $ufbeans as $bean ) {
			$bean->fetchType  = $oldFetchType;
			$bean->aliasName  = $oldAliasName;
			$bean->withSql    = $oldWith;
			$bean->withParams = $oldBindings;
			$newBeans = $bean->searchIn( $property );
			$beans = array_replace( $beans, $newBeans );
		}
		return $beans;
	}
	/**
	 * Implementation of Countable interface. Makes it possible to use
	 * count() function on a bean.
	 *
	 * @return integer
	 */
	public function count()
	{
		return count( $this->properties );
	}
	/**
	 * Checks whether a bean is empty or not.
	 * A bean is empty if it has no other properties than the id field OR
	 * if all the other property are empty().
	 *
	 * @return boolean
	 */
	public function isEmpty()
	{
		$empty = TRUE;
		foreach ( $this->properties as $key => $value ) {
			if ( $key == 'id' ) {
				continue;
			}
			if ( !empty( $value ) ) {
				$empty = FALSE;
			}
		}
		return $empty;
	}
	/**
	 * Chainable setter.
	 *
	 * @param string $property the property of the bean
	 * @param mixed  $value    the value you want to set
	 *
	 * @return RedBean_OODBBean
	 */
	public function setAttr( $property, $value )
	{
		$this->$property = $value;
		return $this;
	}
	/**
	 * Comfort method.
	 * Unsets all properties in array.
	 *
	 * @param array $properties properties you want to unset.
	 *
	 * @return RedBean_OODBBean
	 */
	public function unsetAll( $properties )
	{
		foreach ( $properties as $prop ) {
			if ( isset( $this->properties[$prop] ) ) {
				unset( $this->properties[$prop] );
			}
		}
		return $this;
	}
	/**
	 * Returns original (old) value of a property.
	 * You can use this method to see what has changed in a
	 * bean.
	 *
	 * @param string $property name of the property you want the old value of
	 *
	 * @return mixed
	 */
	public function old( $property )
	{
		$old = $this->getMeta( 'sys.orig', array() );
		if ( array_key_exists( $property, $old ) ) {
			return $old[$property];
		}
		return NULL;
	}
	/**
	 * Convenience method.
	 * Returns TRUE if the bean has been changed, or FALSE otherwise.
	 * Same as $bean->getMeta('tainted');
	 * Note that a bean becomes tainted as soon as you retrieve a list from
	 * the bean. This is because the bean lists are arrays and the bean cannot
	 * determine whether you have made modifications to a list so RedBeanPHP
	 * will mark the whole bean as tainted.
	 *
	 * @return boolean
	 */
	public function isTainted()
	{
		return $this->getMeta( 'tainted' );
	}
	/**
	 * Returns TRUE if the value of a certain property of the bean has been changed and
	 * FALSE otherwise.
	 *
	 * @param string $property name of the property you want the change-status of
	 *
	 * @return boolean
	 */
	public function hasChanged( $property )
	{
		return ( array_key_exists( $property, $this->properties ) ) ?
			$this->old( $property ) != $this->properties[$property] : FALSE;
	}
	/**
	 * Creates a N-M relation by linking an intermediate bean.
	 * This method can be used to quickly connect beans using indirect
	 * relations. For instance, given an album and a song you can connect the two
	 * using a track with a number like this:
	 *
	 * Usage:
	 *
	 * $album->link('track', array('number'=>1))->song = $song;
	 *
	 * or:
	 *
	 * $album->link($trackBean)->song = $song;
	 *
	 * What this method does is adding the link bean to the own-list, in this case
	 * ownTrack. If the first argument is a string and the second is an array or
	 * a JSON string then the linking bean gets dispensed on-the-fly as seen in
	 * example #1. After preparing the linking bean, the bean is returned thus
	 * allowing the chained setter: ->song = $song.
	 *
	 * @param string|RedBean_OODBBean $type          type of bean to dispense or the full bean
	 * @param string|array            $qualification JSON string or array (optional)
	 *
	 * @return RedBean_OODBBean
	 */
	public function link( $typeOrBean, $qualification = array() )
	{
		if ( is_string( $typeOrBean ) ) {
			$bean = $this->beanHelper->getToolBox()->getRedBean()->dispense( $typeOrBean );
			if ( is_string( $qualification ) ) {
				$data = json_decode( $qualification, TRUE );
			} else {
				$data = $qualification;
			}
			foreach ( $data as $key => $value ) {
				$bean->$key = $value;
			}
		} else {
			$bean = $typeOrBean;
		}
		$list = 'own' . ucfirst( $bean->getMeta( 'type' ) );
		array_push( $this->$list, $bean );
		return $bean;
	}
	/**
	 * Returns the same bean freshly loaded from the database.
	 *
	 * @return RedBean_OODBBean
	 */
	public function fresh()
	{
		return $this->beanHelper->getToolbox()->getRedBean()->load( $this->getMeta( 'type' ), $this->properties['id'] );
	}
	/**
	 * Registers a association renaming globally.
	 *
	 * @param string $via type you wish to use for shared lists
	 *
	 * @return RedBean_OODBBean
	 */
	public function via( $via )
	{
		$this->via = $via;
		return $this;
	}
	/**
	 * Counts all own beans of type $type.
	 * Also works with alias(), with() and withCondition().
	 *
	 * @param string $type the type of bean you want to count
	 *
	 * @return integer
	 */
	public function countOwn( $type )
	{
		$type = $this->beau( $type );
		if ( $this->aliasName ) {
			$myFieldLink     = $this->aliasName . '_id';
			$this->aliasName = NULL;
		} else {
			$myFieldLink = $this->__info['type'] . '_id';
		}
		$count = 0;
		if ( $this->getID() !== 0 ) {
			$firstKey = NULL;
			if ( count( $this->withParams ) > 0 ) {
				reset( $this->withParams );
				$firstKey = key( $this->withParams );
			}
			if ( !is_numeric( $firstKey ) || $firstKey === NULL ) {
					$bindings           = $this->withParams;
					$bindings[':slot0'] = $this->getID();
					$count              = $this->beanHelper->getToolbox()->getWriter()->queryRecordCount( $type, array(), " $myFieldLink = :slot0 " . $this->withSql, $bindings );
			} else {
					$bindings = array_merge( array( $this->getID() ), $this->withParams );
					$count    = $this->beanHelper->getToolbox()->getWriter()->queryRecordCount( $type, array(), " $myFieldLink = ? " . $this->withSql, $bindings );
			}
		}
		$this->withSql    = '';
		$this->withParams = array();
		return (int) $count;
	}
	/**
	 * Counts all shared beans of type $type.
	 * Also works with via(), with() and withCondition().
	 *
	 * @param string $type type of bean you wish to count
	 *
	 * @return integer
	 */
	public function countShared( $type )
	{
		$toolbox = $this->beanHelper->getToolbox();
		$redbean = $toolbox->getRedBean();
		$writer  = $toolbox->getWriter();
		if ( $this->via ) {
			$oldName = $writer->getAssocTable( array( $this->__info['type'], $type ) );
			if ( $oldName !== $this->via ) {
				//set the new renaming rule
				$writer->renameAssocTable( $oldName, $this->via );
				$this->via = NULL;
			}
		}
		$type  = $this->beau( $type );
		$count = 0;
		if ( $this->getID() > 0 ) {
			$count = $redbean->getAssociationManager()->relatedCount( $this, $type, $this->withSql, $this->withParams, TRUE );
		}
		$this->withSql    = '';
		$this->withParams = array();
		return (integer) $count;
	}
	/**
	 * Tests whether the database identities of two beans are equal.
	 *
	 * @param RedBean_OODBBean $bean other bean
	 *
	 * @return boolean
	 */
	public function equals(RedBean_OODBBean $bean) {
		return (bool) (
			   ( (string) $this->properties['id'] === (string) $bean->properties['id'] )
			&& ( (string) $this->__info['type']   === (string) $bean->__info['type']   )
		);
	}
}
abstract class RedBean_Observable { //bracket must be here - otherwise coverage software does not understand.
	/**
	 * @var array
	 */
	private $observers = array();
	/**
	 * Implementation of the Observer Pattern.
	 * Adds an event listener to the observable object.
	 * First argument should be the name of the event you wish to listen for.
	 * Second argument should be the object that wants to be notified in case
	 * the event occurs.
	 *
	 * @param string           $eventname event identifier
	 * @param RedBean_Observer $observer  observer instance
	 *
	 * @return void
	 */
	public function addEventListener( $eventname, RedBean_Observer $observer )
	{
		if ( !isset( $this->observers[$eventname] ) ) {
			$this->observers[$eventname] = array();
		}
		foreach ( $this->observers[$eventname] as $o ) {
			if ( $o == $observer ) {
				return;
			}
		}
		$this->observers[$eventname][] = $observer;
	}
	/**
	 * Notifies listeners.
	 * Sends the signal $eventname, the event identifier and a message object
	 * to all observers that have been registered to receive notification for
	 * this event. Part of the observer pattern implementation in RedBeanPHP.
	 *
	 * @param string $eventname event you want signal
	 * @param mixed  $info      message object to send along
	 *
	 * @return void
	 */
	public function signal( $eventname, $info )
	{
		if ( !isset( $this->observers[$eventname] ) ) {
			$this->observers[$eventname] = array();
		}
		foreach ( $this->observers[$eventname] as $observer ) {
			$observer->onEvent( $eventname, $info );
		}
	}
}
interface RedBean_Observer
{
	/**
	 * An observer object needs to be capable of receiving
	 * notifications. Therefore the observer needs to implement the
	 * onEvent method with two parameters, the event identifier specifying the
	 * current event and a message object (in RedBeanPHP this can also be a bean).
	 * 
	 * @param string $eventname event identifier
	 * @param mixed  $bean      a message sent along with the notification
	 *
	 * @return void
	 */
	public function onEvent( $eventname, $bean );
}
interface RedBean_Adapter
{
	/**
	 * Returns the latest SQL statement
	 *
	 * @return string
	 */
	public function getSQL();
	/**
	 * Executes an SQL Statement using an array of values to bind
	 * If $noevent is TRUE then this function will not signal its
	 * observers to notify about the SQL execution; this to prevent
	 * infinite recursion when using observers.
	 *
	 * @param string  $sql      SQL
	 * @param array   $bindings values
	 * @param boolean $noevent  no event firing
	 */
	public function exec( $sql, $bindings = array(), $noevent = FALSE );
	/**
	 * Executes an SQL Query and returns a resultset.
	 * This method returns a multi dimensional resultset similar to getAll
	 * The values array can be used to bind values to the place holders in the
	 * SQL query.
	 *
	 * @param string $sql      SQL
	 * @param array  $bindings values
	 *
	 * @return array
	 */
	public function get( $sql, $bindings = array() );
	/**
	 * Executes an SQL Query and returns a resultset.
	 * This method returns a single row (one array) resultset.
	 * The values array can be used to bind values to the place holders in the
	 * SQL query.
	 *
	 * @param string $sql      SQL
	 * @param array  $bindings values to bind
	 *
	 * @return array
	 */
	public function getRow( $sql, $bindings = array() );
	/**
	 * Executes an SQL Query and returns a resultset.
	 * This method returns a single column (one array) resultset.
	 * The values array can be used to bind values to the place holders in the
	 * SQL query.
	 *
	 * @param string $sql      SQL
	 * @param array  $bindings values to bind
	 *
	 * @return array
	 */
	public function getCol( $sql, $bindings = array() );
	/**
	 * Executes an SQL Query and returns a resultset.
	 * This method returns a single cell, a scalar value as the resultset.
	 * The values array can be used to bind values to the place holders in the
	 * SQL query.
	 *
	 * @param string $sql      SQL
	 * @param array  $bindings values to bind
	 *
	 * @return string
	 */
	public function getCell( $sql, $bindings = array() );
	/**
	 * Executes the SQL query specified in $sql and takes
	 * the first two columns of the resultset. This function transforms the
	 * resultset into an associative array. Values from the the first column will
	 * serve as keys while the values of the second column will be used as values.
	 * The values array can be used to bind values to the place holders in the
	 * SQL query.
	 *
	 * @param string $sql      SQL
	 * @param array  $bindings values to bind
	 *
	 * @return array
	 */
	public function getAssoc( $sql, $bindings = array() );
	
	/**
	 * Executes the SQL query specified in $sql and indexes
	 * the row by the first column.
	 * 
	 * @param string $sql      SQL
	 * @param array  $bindings values to bind
	 *
	 * @return array
	 */
	public function getAssocRow( $sql, $bindings = array() );
	/**
	 * Returns the latest insert ID.
	 *
	 * @return integer
	 */
	public function getInsertID();
	/**
	 * Returns the number of rows that have been
	 * affected by the last update statement.
	 *
	 * @return integer
	 */
	public function getAffectedRows();
	/**
	 * Returns the original database resource. This is useful if you want to
	 * perform operations on the driver directly instead of working with the
	 * adapter. RedBean will only access the adapter and never to talk
	 * directly to the driver though.
	 *
	 * @return object
	 */
	public function getDatabase();
	/**
	 * This method is part of the RedBean Transaction Management
	 * mechanisms.
	 * Starts a transaction.
	 *
	 * @return void
	 */
	public function startTransaction();
	/**
	 * This method is part of the RedBean Transaction Management
	 * mechanisms.
	 * Commits the transaction.
	 *
	 * @return void
	 */
	public function commit();
	/**
	 * This method is part of the RedBean Transaction Management
	 * mechanisms.
	 * Rolls back the transaction.
	 *
	 * @return void
	 */
	public function rollback();
	/**
	 * Closes database connection.
	 *
	 * @return void
	 */
	public function close();
}
class RedBean_Adapter_DBAdapter extends RedBean_Observable implements RedBean_Adapter
{
	/**
	 * @var RedBean_Driver
	 */
	private $db = NULL;
	/**
	 * @var string
	 */
	private $sql = '';
	/**
	 * Constructor.
	 *
	 * Creates an instance of the RedBean Adapter Class.
	 * This class provides an interface for RedBean to work
	 * with ADO compatible DB instances.
	 *
	 * @param RedBean_Driver $database ADO Compatible DB Instance
	 */
	public function __construct( $database )
	{
		$this->db = $database;
	}
	/**
	 * @see RedBean_Adapter::getSQL
	 */
	public function getSQL()
	{
		return $this->sql;
	}
	/**
	 * @see RedBean_Adapter::exec
	 */
	public function exec( $sql, $bindings = array(), $noevent = FALSE )
	{
		if ( !$noevent ) {
			$this->sql = $sql;
			$this->signal( 'sql_exec', $this );
		}
		return $this->db->Execute( $sql, $bindings );
	}
	/**
	 * @see RedBean_Adapter::get
	 */
	public function get( $sql, $bindings = array() )
	{
		$this->sql = $sql;
		$this->signal( 'sql_exec', $this );
		return $this->db->GetAll( $sql, $bindings );
	}
	/**
	 * @see RedBean_Adapter::getRow
	 */
	public function getRow( $sql, $bindings = array() )
	{
		$this->sql = $sql;
		$this->signal( 'sql_exec', $this );
		return $this->db->GetRow( $sql, $bindings );
	}
	/**
	 * @see RedBean_Adapter::getCol
	 */
	public function getCol( $sql, $bindings = array() )
	{
		$this->sql = $sql;
		$this->signal( 'sql_exec', $this );
		return $this->db->GetCol( $sql, $bindings );
	}
	/**
	 * @see RedBean_Adapter::getAssoc
	 */
	public function getAssoc( $sql, $bindings = array() )
	{
		$this->sql = $sql;
		$this->signal( 'sql_exec', $this );
		$rows  = $this->db->GetAll( $sql, $bindings );
		$assoc = array();
		if ( !$rows ) {
			return $assoc;
		}
		foreach ( $rows as $row ) {
			if ( empty( $row ) ) continue;
			if ( count( $row ) > 1 ) {
				$key   = array_shift( $row );
				$value = array_shift( $row );
			} else {
				$key   = array_shift( $row );
				$value = $key;
			}
			$assoc[$key] = $value;
		}
		return $assoc;
	}
	
	/**
	 * @see Adapter::getAssocRow
	 */
	public function getAssocRow($sql, $bindings = array())
	{
		$this->sql = $sql;
		$this->signal( 'sql_exec', $this );
		return $this->db->GetAssocRow( $sql, $bindings );
	}
	/**
	 * @see RedBean_Adapter::getCell
	 */
	public function getCell( $sql, $bindings = array(), $noSignal = NULL )
	{
		$this->sql = $sql;
		if ( !$noSignal ) $this->signal( 'sql_exec', $this );
		$arr = $this->db->getCol( $sql, $bindings );
		if ( $arr && is_array( $arr ) && isset( $arr[0] ) ) {
			return ( $arr[0] );
		}
		return NULL;
	}
	/**
	 * @see RedBean_Adapter::getInsertID
	 */
	public function getInsertID()
	{
		return $this->db->getInsertID();
	}
	/**
	 * @see RedBean_Adapter::getAffectedRows
	 */
	public function getAffectedRows()
	{
		return $this->db->Affected_Rows();
	}
	/**
	 * @see RedBean_Adapter::getDatabase
	 */
	public function getDatabase()
	{
		return $this->db;
	}
	/**
	 * @see RedBean_Adapter::startTransaction
	 */
	public function startTransaction()
	{
		$this->db->StartTrans();
	}
	/**
	 * @see RedBean_Adapter::commit
	 */
	public function commit()
	{
		$this->db->CommitTrans();
	}
	/**
	 * @see RedBean_Adapter::rollback
	 */
	public function rollback()
	{
		$this->db->FailTrans();
	}
	/**
	 * @see RedBean_Adapter::close.
	 */
	public function close()
	{
		$this->db->close();
	}
}
interface RedBean_QueryWriter
{
	/**
	 * Query Writer constants.
	 */
	const C_SQLSTATE_NO_SUCH_TABLE                  = 1;
	const C_SQLSTATE_NO_SUCH_COLUMN                 = 2;
	const C_SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION = 3;
	/**
	 * Define data type regions
	 *
	 * 00 - 80: normal data types
	 * 80 - 99: special data types, only scan/code if requested
	 * 99     : specified by user, don't change
	 */
	const C_DATATYPE_RANGE_SPECIAL   = 80;
	const C_DATATYPE_RANGE_SPECIFIED = 99;
	/**
	 * Define GLUE types for use with glueSQLCondition methods.
	 * Determines how to prefix a snippet of SQL before appending it
	 * to other SQL (or integrating it, mixing it otherwise).
	 * 
	 * WHERE - glue as WHERE condition
	 * AND   - glue as AND condition
	 */
	const C_GLUE_WHERE = 1;
	const C_GLUE_AND   = 2;
	/**
	 * Glues an SQL snippet to the beginning of a WHERE clause.
	 * This ensures users don't have to add WHERE to their query snippets.
	 *
	 * The snippet gets prefixed with WHERE or AND 
	 * if it starts with a condition.
	 * 
	 * If the snippet does NOT start with a condition (or this function thinks so)
	 * the snippet is returned as-is.
	 * 
	 * The GLUE type determines the prefix:
	 * 
	 * - NONE  prefixes with WHERE
	 * - WHERE prefixes with WHERE and replaces AND if snippets starts with AND  
	 * - AND   prefixes with AND
	 * 
	 * This method will never replace WHERE with AND since a snippet should never
	 * begin with WHERE in the first place. OR is not supported.
	 * 
	 * Only a limited set of clauses will be recognized as non-conditions.
	 * For instance beginning a snippet with complex statements like JOIN or UNION
	 * will not work. This is too complex for use in a snippet.
	 *
	 * @param string  $sql   SQL Snippet
	 * @param integer $glue  the GLUE type - how to glue (C_GLUE_WHERE or C_GLUE_AND)
	 * 
	 * @return string
	 */
	public function glueSQLCondition( $sql, $glue = NULL );
	/**
	 * Returns the tables that are in the database.
	 *
	 * @return array
	 */
	public function getTables();
	/**
	 * This method will create a table for the bean.
	 * This methods accepts a type and infers the corresponding table name.
	 *
	 * @param string $type type of bean you want to create a table for
	 *
	 * @return void
	 */
	public function createTable( $type );
	/**
	 * Returns an array containing all the columns of the specified type.
	 * The format of the return array looks like this:
	 * $field => $type where $field is the name of the column and $type
	 * is a database specific description of the datatype.
	 *
	 * This methods accepts a type and infers the corresponding table name.
	 *
	 * @param string $type type of bean you want to obtain a column list of
	 *
	 * @return array
	 */
	public function getColumns( $type );
	/**
	 * Returns the Column Type Code (integer) that corresponds
	 * to the given value type. This method is used to determine the minimum
	 * column type required to represent the given value.
	 *
	 * @param string $value value
	 *
	 * @return integer
	 */
	public function scanType( $value, $alsoScanSpecialForTypes = FALSE );
	/**
	 * This method will add a column to a table.
	 * This methods accepts a type and infers the corresponding table name.
	 *
	 * @param string  $type   name of the table
	 * @param string  $column name of the column
	 * @param integer $field  data type for field
	 *
	 * @return void
	 */
	public function addColumn( $type, $column, $field );
	/**
	 * Returns the Type Code for a Column Description.
	 * Given an SQL column description this method will return the corresponding
	 * code for the writer. If the include specials flag is set it will also
	 * return codes for special columns. Otherwise special columns will be identified
	 * as specified columns.
	 *
	 * @param string  $typedescription description
	 * @param boolean $includeSpecials whether you want to get codes for special columns as well
	 *
	 * @return integer
	 */
	public function code( $typedescription, $includeSpecials = FALSE );
	/**
	 * This method will widen the column to the specified data type.
	 * This methods accepts a type and infers the corresponding table name.
	 *
	 * @param string  $type       type / table that needs to be adjusted
	 * @param string  $column     column that needs to be altered
	 * @param integer $datatype   target data type
	 *
	 * @return void
	 */
	public function widenColumn( $type, $column, $datatype );
	/**
	 * Selects records from the database.
	 * This methods selects the records from the database that match the specified
	 * type, conditions (optional) and additional SQL snippet (optional).
	 *
	 * @param string $type       name of the table you want to query
	 * @param array  $conditions criteria ( $column => array( $values ) )
	 * @param string $addSQL     additional SQL snippet
	 * @param array  $bindings   bindings for SQL snippet
	 *
	 * @return array
	 */
	public function queryRecord( $type, $conditions = array(), $addSql = NULL, $bindings = array() );
	/**
	 * Returns records through an intermediate type. This method is used to obtain records using a link table and
	 * allows the SQL snippets to reference columns in the link table for additional filtering or ordering.
	 *
	 * @param string $sourceType source type, the reference type you want to use to fetch related items on the other side
	 * @param string $destType   destination type, the target type you want to get beans of
	 * @param mixed  $linkID     ID to use for the link table
	 * @param string $addSql     Additional SQL snippet
	 * @param array  $bindings   Bindings for SQL snippet
	 *
	 * @return array
	 */
	public function queryRecordRelated( $sourceType, $destType, $linkID, $addSql = '', $bindings = array() );
	/**
	 * Returns linking records. This method is used to obtain records using a link table and
	 * allows the SQL snippets to reference columns in the link table for additional filtering or ordering.
	 *
	 * @param string $sourceType source type, the reference type you want to use to fetch related items on the other side
	 * @param string $destType   destination type, the target type you want to get beans of
	 * @param mixed  $linkID     ID to use for the link table
	 * @param string $addSql     Additional SQL snippet
	 * @param array  $bindings   Bindings for SQL snippet
	 *
	 * @return array
	 */
	public function queryRecordLinks( $sourceType, $destType, $linkIDs, $addSql = '', $bindings = array() );
	/**
	 * Returns the row that links $sourceType $sourcID to $destType $destID in an N-M relation.
	 *
	 * @param string $sourceType source type, the first part of the link you're looking for
	 * @param string $destType   destination type, the second part of the link you're looking for
	 * @param string $sourceID   ID for the source
	 * @param string $destID     ID for the destination
	 *
	 * @return array|null
	 */
	public function queryRecordLink( $sourceType, $destType, $sourceID, $destID );
	/**
	 * Counts the number of records in the database that match the
	 * conditions and additional SQL.
	 *
	 * @param string $type       name of the table you want to query
	 * @param array  $conditions criteria ( $column => array( $values ) )
	 * @param string $addSQL     additional SQL snippet
	 * @param array  $bindings   bindings for SQL snippet
	 *
	 * @return integer
	 */
	public function queryRecordCount( $type, $conditions = array(), $addSql = NULL, $bindings = array() );
	/**
	 * Returns the number of records linked through $linkType and satisfying the SQL in $addSQL/$bindings.
	 *
	 * @param string $sourceType source type
	 * @param string $targetType the thing you want to count
	 * @param mixed  $linkID     the of the source type
	 * @param string $addSQL     additional SQL snippet
	 * @param array  $bindings   bindings for SQL snippet
	 *
	 * @return integer
	 */
	public function queryRecordCountRelated( $sourceType, $targetType, $linkID, $addSQL = '', $bindings = array() );
	/**
	 * This method should update (or insert a record), it takes
	 * a table name, a list of update values ( $field => $value ) and an
	 * primary key ID (optional). If no primary key ID is provided, an
	 * INSERT will take place.
	 * Returns the new ID.
	 * This methods accepts a type and infers the corresponding table name.
	 *
	 * @param string  $type            name of the table to update
	 * @param array   $updatevalues    list of update values
	 * @param integer $id              optional primary key ID value
	 *
	 * @return integer
	 */
	public function updateRecord( $type, $updatevalues, $id = NULL );
	/**
	 * Deletes records from the database.
	 * @note $addSql is always prefixed with ' WHERE ' or ' AND .'
	 *
	 * @param string $type       name of the table you want to query
	 * @param array  $conditions criteria ( $column => array( $values ) )
	 * @param string $sql        additional SQL
	 * @param array  $bindings   bindings
	 *
	 * @return void
	 */
	public function deleteRecord( $type, $conditions = array(), $addSql = '', $bindings = array() );
	/**
	 * Deletes all links between $sourceType and $destType in an N-M relation.
	 *
	 * @param string $sourceType source type
	 * @param string $destType   destination type
	 * @param string $sourceID   source ID
	 *
	 * @return void
	 */
	public function deleteRelations( $sourceType, $destType, $sourceID );
	/**
	 * This method will add a UNIQUE constraint index to a table on columns $columns.
	 * This methods accepts a type and infers the corresponding table name.
	 *
	 * @param string $type               type
	 * @param array  $columnsPartOfIndex columns to include in index
	 *
	 * @return void
	 */
	public function addUniqueIndex( $type, $columns );
	/**
	 * This method will check whether the SQL state is in the list of specified states
	 * and returns TRUE if it does appear in this list or FALSE if it
	 * does not. The purpose of this method is to translate the database specific state to
	 * a one of the constants defined in this class and then check whether it is in the list
	 * of standard states provided.
	 *
	 * @param string $state sql state
	 * @param array  $list  list
	 *
	 * @return boolean
	 */
	public function sqlStateIn( $state, $list );
	/**
	 * This method will remove all beans of a certain type.
	 * This methods accepts a type and infers the corresponding table name.
	 *
	 * @param  string $type bean type
	 *
	 * @return void
	 */
	public function wipe( $type );
	/**
	 * Given two types this method will add a foreign key constraint.
	 *
	 * @param string $sourceType source type
	 * @param string $destType   destination type
	 *
	 * @return void
	 */
	public function addConstraintForTypes( $sourceType, $destType );
	/**
	 * This method will add a foreign key from type and field to
	 * target type and target field.
	 * The foreign key is created without an action. On delete/update
	 * no action will be triggered. The FK is only used to allow database
	 * tools to generate pretty diagrams and to make it easy to add actions
	 * later on.
	 * This methods accepts a type and infers the corresponding table name.
	 *
	 *
	 * @param  string $type           type that will have a foreign key field
	 * @param  string $targetType     points to this type
	 * @param  string $field          field that contains the foreign key value
	 * @param  string $targetField    field where the fk points to
	 *
	 * @return void
	 */
	public function addFK( $type, $targetType, $field, $targetField );
	/**
	 * This method will add an index to a type and field with name
	 * $name.
	 * This methods accepts a type and infers the corresponding table name.
	 *
	 * @param string $type   type to add index to
	 * @param string $name   name of the new index
	 * @param string $column field to index
	 *
	 * @return void
	 */
	public function addIndex( $type, $name, $column );
	/**
	 * Checks and filters a database structure element like a table of column
	 * for safe use in a query. A database structure has to conform to the
	 * RedBeanPHP DB security policy which basically means only alphanumeric
	 * symbols are allowed. This security policy is more strict than conventional
	 * SQL policies and does therefore not require database specific escaping rules.
	 *
	 * @param string  $databaseStructure name of the column/table to check
	 * @param boolean $noQuotes          TRUE to NOT put backticks or quotes around the string
	 *
	 * @return string
	 */
	public function esc( $databaseStructure, $dontQuote = FALSE );
	/**
	 * Removes all tables and views from the database.
	 *
	 * @return void
	 */
	public function wipeAll();
	/**
	 * Renames an association. For instance if you would like to refer to
	 * album_song as: track you can specify this by calling this method like:
	 *
	 * renameAssociation('album_song','track')
	 *
	 * This allows:
	 *
	 * $album->sharedSong
	 *
	 * to add/retrieve beans from track instead of album_song.
	 * Also works for exportAll().
	 *
	 * This method also accepts a single associative array as
	 * its first argument.
	 *
	 * @param string|array $from
	 * @param string       $to (optional)
	 *
	 * @return void
	 */
	public function renameAssocTable( $from, $to = NULL );
	/**
	 * Returns the format for link tables.
	 * Given an array containing two type names this method returns the
	 * name of the link table to be used to store and retrieve
	 * association records.
	 *
	 * @param  array $types two types array($type1, $type2)
	 *
	 * @return string
	 */
	public function getAssocTable( $types );
}
abstract class RedBean_QueryWriter_AQueryWriter { //bracket must be here - otherwise coverage software does not understand.
	/**
	 * @var RedBean_Adapter_DBAdapter
	 */
	protected $adapter;
	/**
	 * @var string
	 */
	protected $defaultValue = 'NULL';
	/**
	 * @var string
	 */
	protected $quoteCharacter = '';
	/**
	 * @var boolean
	 */
	protected $flagUseCache = FALSE;
	/**
	 * @var array
	 */
	protected $cache = array();
	/**
	 * @var array
	 */
	protected static $renames = array();
	/**
	 * @var array
	 */
	public $typeno_sqltype = array();
	/**
	 * Generates a list of parameters (slots) for an SQL snippet.
	 * This method calculates the correct number of slots to insert in the
	 * SQL snippet and determines the correct type of slot. If the bindings
	 * array contains named parameters this method will return named ones and
	 * update the keys in the value list accordingly (that's why we use the &).
	 *
	 * If you pass an offset the bindings will be re-added to the value list.
	 * Some databases cant handle duplicate parameter names in queries.
	 *
	 * @param array   &$valueList     list of values to generate slots for (gets modified if needed)
	 * @param array   $otherBindings  list of additional bindings
	 * @param integer $offset         start counter at...
	 *
	 * @return string
	 */
	protected function getParametersForInClause( &$valueList, $otherBindings, $offset = 0 )
	{
		if ( is_array( $otherBindings ) && count( $otherBindings ) > 0 ) {
			reset( $otherBindings );
			$key = key( $otherBindings );
			if ( !is_numeric($key) ) {
				$filler  = array();
				$newList = (!$offset) ? array() : $valueList;
				$counter = $offset;
				foreach( $valueList as $value ) {
					$slot           = ':slot' . ( $counter++ );
					$filler[]       = $slot;
					$newList[$slot] = $value;
				}
				// Change the keys!
				$valueList = $newList;
				return implode( ',', $filler );
			}
		}
		return implode( ',', array_fill( 0, count( $valueList ), '?' ) );
	}
	/**
	 * Returns a cache key for the cache values passed.
	 * This method returns a fingerprint string to be used as a key to store
	 * data in the writer cache.
	 *
	 * @param array $keyValues key-value to generate key for
	 *
	 * @return string
	 */
	private function getCacheKey( $keyValues )
	{
		return json_encode( $keyValues );
	}
	/**
	 * Returns the values associated with the provided cache tag and key.
	 *
	 * @param string $cacheTag cache tag to use for lookup
	 * @param string $key      key to use for lookup
	 *
	 * @return mixed
	 */
	private function getCached( $cacheTag, $key )
	{
		$sql = $this->adapter->getSQL();
		if ( strpos( $sql, '-- keep-cache' ) !== strlen( $sql ) - 13 ) {
			// If SQL has been taken place outside of this method then something else then
			// a select query might have happened! (or instruct to keep cache)
			$this->cache = array();
		} else {
			if ( isset( $this->cache[$cacheTag][$key] ) ) {
				return $this->cache[$cacheTag][$key];
			}
		}
		return NULL;
	}
	/**
	 * Stores data from the writer in the cache under a specific key and cache tag.
	 * A cache tag is used to make sure the cache remains consistent. In most cases the cache tag
	 * will be the bean type, this makes sure queries associated with a certain reference type will
	 * never contain conflicting data.
	 * You can only store one item under a cache tag. Why not use the cache tag as a key? Well
	 * we need to make sure the cache contents fits the key (and key is based on the cache values).
	 * Otherwise it would be possible to store two different result sets under the same key (the cache tag).
	 *
	 * @param string $cacheTag cache tag (secondary key)
	 * @param string $key      key
	 * @param array  $values   content to be stored
	 *
	 * @return void
	 */
	private function putResultInCache( $cacheTag, $key, $values )
	{
		$this->cache[$cacheTag] = array(
			$key => $values
		);
	}
	/**
	 * Creates an SQL snippet from a list of conditions of format:
	 *
	 * array(
	 *    key => array(
	 *           value1, value2, value3 ....
	 *        )
	 * )
	 *
	 * @param array  $conditions list of conditions
	 * @param array  $bindings   parameter bindings for SQL snippet
	 * @param string $addSql     SQL snippet
	 *
	 * @return string
	 */
	private function makeSQLFromConditions( $conditions, &$bindings, $addSql = '' )
	{
		reset( $bindings );
		$firstKey       = key( $bindings );
		$paramTypeIsNum = ( is_numeric( $firstKey ) );
		$counter        = 0;
		$sqlConditions = array();
		foreach ( $conditions as $column => $values ) {
			if ( !count( $values ) ) continue;
			$sql = $this->esc( $column );
			$sql .= ' IN ( ';
			if ( !is_array( $values ) ) $values = array( $values );
			// If it's safe to skip bindings, do so...
			if ( ctype_digit( implode( '', $values ) ) ) {
				$sql .= implode( ',', $values ) . ' ) ';
				// only numeric, cant do much harm
				$sqlConditions[] = $sql;
			} else {
				if ( $paramTypeIsNum ) {
					$sql .= implode( ',', array_fill( 0, count( $values ), '?' ) ) . ' ) ';
					array_unshift($sqlConditions, $sql);
					foreach ( $values as $k => $v ) {
						$values[$k] = strval( $v );
						array_unshift( $bindings, $v );
					}
				} else {
					$slots = array();
					foreach( $values as $k => $v ) {
						$slot            = ':slot'.$counter++;
						$slots[]         = $slot;
						$bindings[$slot] = strval( $v );
					}
					$sql .= implode( ',', $slots ).' ) ';
					$sqlConditions[] = $sql;
				}
			}
		}
		$sql = '';
		if ( is_array( $sqlConditions ) && count( $sqlConditions ) > 0 ) {
			$sql = implode( ' AND ', $sqlConditions );
			$sql = " WHERE ( $sql ) ";
			if ( $addSql ) $sql .= $addSql;
		} elseif ( $addSql ) {
			$sql = $addSql;
		}
		return $sql;
	}
	/**
	 * Returns the table names and column names for a relational query.
	 *
	 * @param string  $sourceType type of the source bean
	 * @param string  $destType   type of the bean you want to obtain using the relation
	 * @param boolean $noQuote    TRUE if you want to omit quotes
	 *
	 * @return array
	 */
	private function getRelationalTablesAndColumns( $sourceType, $destType, $noQuote = FALSE )
	{
		$linkTable   = $this->esc( $this->getAssocTable( array( $sourceType, $destType ) ), $noQuote );
		$sourceCol   = $this->esc( $sourceType . '_id', $noQuote );
		if ( $sourceType === $destType ) {
			$destCol = $this->esc( $destType . '2_id', $noQuote );
		} else {
			$destCol = $this->esc( $destType . '_id', $noQuote );
		}
		$sourceTable = $this->esc( $sourceType, $noQuote );
		$destTable   = $this->esc( $destType, $noQuote );
		return array( $sourceTable, $destTable, $linkTable, $sourceCol, $destCol );
	}
	/**
	 * Returns the sql that should follow an insert statement.
	 *
	 * @param string $table name
	 *
	 * @return string
	 */
	protected function getInsertSuffix( $table )
	{
		return '';
	}
	/**
	 * Checks whether a value starts with zeros. In this case
	 * the value should probably be stored using a text datatype instead of a
	 * numerical type in order to preserve the zeros.
	 *
	 * @param string $value value to be checked.
	 *
	 * @return boolean
	 */
	protected function startsWithZeros( $value )
	{
		$value = strval( $value );
		if ( strlen( $value ) > 1 && strpos( $value, '0' ) === 0 && strpos( $value, '0.' ) !== 0 ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	/**
	 * Inserts a record into the database using a series of insert columns
	 * and corresponding insertvalues. Returns the insert id.
	 *
	 * @param string $table         table to perform query on
	 * @param array  $insertcolumns columns to be inserted
	 * @param array  $insertvalues  values to be inserted
	 *
	 * @return integer
	 */
	protected function insertRecord( $type, $insertcolumns, $insertvalues )
	{
		$default = $this->defaultValue;
		$suffix  = $this->getInsertSuffix( $type );
		$table   = $this->esc( $type );
		if ( count( $insertvalues ) > 0 && is_array( $insertvalues[0] ) && count( $insertvalues[0] ) > 0 ) {
			foreach ( $insertcolumns as $k => $v ) {
				$insertcolumns[$k] = $this->esc( $v );
			}
			$insertSQL = "INSERT INTO $table ( id, " . implode( ',', $insertcolumns ) . " ) VALUES
			( $default, " . implode( ',', array_fill( 0, count( $insertcolumns ), ' ? ' ) ) . " ) $suffix";
			$ids = array();
			foreach ( $insertvalues as $i => $insertvalue ) {
				$ids[] = $this->adapter->getCell( $insertSQL, $insertvalue, $i );
			}
			$result = count( $ids ) === 1 ? array_pop( $ids ) : $ids;
		} else {
			$result = $this->adapter->getCell( "INSERT INTO $table (id) VALUES($default) $suffix" );
		}
		if ( $suffix ) return $result;
		$last_id = $this->adapter->getInsertID();
		return $last_id;
	}
	/**
	 * Checks table name or column name.
	 *
	 * @param string $table table string
	 *
	 * @return string
	 *
	 * @throws RedBean_Exception_Security
	 */
	protected function check( $struct )
	{
		if ( !preg_match( '/^[a-zA-Z0-9_]+$/', $struct ) ) {
			throw new RedBean_Exception_Security( 'Identifier does not conform to RedBeanPHP security policies.' );
		}
		return $struct;
	}
	/**
	 * Checks whether a number can be treated like an int.
	 *
	 * @param  string $value string representation of a certain value
	 *
	 * @return boolean
	 */
	public static function canBeTreatedAsInt( $value )
	{
		return (bool) ( ctype_digit( strval( $value ) ) && strval( $value ) === strval( intval( $value ) ) );
	}
	/**
	 * @see RedBean_QueryWriter::getAssocTableFormat
	 */
	public static function getAssocTableFormat( $types )
	{
		sort( $types );
		$assoc = implode( '_', $types );
		return ( isset( self::$renames[$assoc] ) ) ? self::$renames[$assoc] : $assoc;
	}
	/**
	 * @see RedBean_QueryWriter::renameAssociation
	 */
	public static function renameAssociation( $from, $to = NULL )
	{
		if ( is_array( $from ) ) {
			foreach ( $from as $key => $value ) self::$renames[$key] = $value;
			return;
		}
		self::$renames[$from] = $to;
	}
	/**
	 * Checks whether the specified type (i.e. table) already exists in the database.
	 * Not part of the Object Database interface!
	 *
	 * @param string $table table name
	 *
	 * @return boolean
	 */
	public function tableExists( $table )
	{
		$tables = $this->getTables();
		return in_array( $table, $tables );
	}
	/**
	 * @see RedBean_QueryWriter::glueSQLCondition
	 */
	public function glueSQLCondition( $sql, $glue = NULL )
	{
		static $snippetCache = array();
		if ( trim( $sql ) === '' ) {
			return $sql;
		}
		$key = $glue . '|' . $sql;
		if ( isset( $snippetCache[$key] ) ) {
			return $snippetCache[$key];
		}
		$lsql = ltrim( $sql );
		if ( preg_match( '/^(AND|OR|WHERE|ORDER|GROUP|HAVING|LIMIT|OFFSET)\s+/i', $lsql ) ) {
			if ( $glue === RedBean_QueryWriter::C_GLUE_WHERE && stripos( $lsql, 'AND' ) === 0 ) {
				$snippetCache[$key] = ' WHERE ' . substr( $lsql, 3 );
			} else {
				$snippetCache[$key] = $sql;
			}
		} else {
			$snippetCache[$key] = ( ( $glue === RedBean_QueryWriter::C_GLUE_AND ) ? ' AND ' : ' WHERE ') . $sql;
		}
		return $snippetCache[$key];
	}
	/**
	 * @see RedBean_QueryWriter::esc
	 */
	public function esc( $dbStructure, $dontQuote = FALSE )
	{
		$this->check( $dbStructure );
		return ( $dontQuote ) ? $dbStructure : $this->quoteCharacter . $dbStructure . $this->quoteCharacter;
	}
	/**
	 * @see RedBean_QueryWriter::addColumn
	 */
	public function addColumn( $type, $column, $field )
	{
		$table  = $type;
		$type   = $field;
		$table  = $this->esc( $table );
		$column = $this->esc( $column );
		$type = ( isset( $this->typeno_sqltype[$type] ) ) ? $this->typeno_sqltype[$type] : '';
		$this->adapter->exec( "ALTER TABLE $table ADD $column $type " );
	}
	/**
	 * @see RedBean_QueryWriter::updateRecord
	 */
	public function updateRecord( $type, $updatevalues, $id = NULL )
	{
		$table = $type;
		if ( !$id ) {
			$insertcolumns = $insertvalues = array();
			foreach ( $updatevalues as $pair ) {
				$insertcolumns[] = $pair['property'];
				$insertvalues[]  = $pair['value'];
			}
			return $this->insertRecord( $table, $insertcolumns, array( $insertvalues ) );
		}
		if ( $id && !count( $updatevalues ) ) {
			return $id;
		}
		$table = $this->esc( $table );
		$sql   = "UPDATE $table SET ";
		$p = $v = array();
		foreach ( $updatevalues as $uv ) {
			$p[] = " {$this->esc( $uv["property"] )} = ? ";
			$v[] = $uv['value'];
		}
		$sql .= implode( ',', $p ) . ' WHERE id = ? ';
		$v[] = $id;
		$this->adapter->exec( $sql, $v );
		return $id;
	}
	/**
	 * @see RedBean_QueryWriter::queryRecord
	 */
	public function queryRecord( $type, $conditions = array(), $addSql = NULL, $bindings = array() )
	{
		$addSql = $this->glueSQLCondition( $addSql, ( count($conditions) > 0) ? RedBean_QueryWriter::C_GLUE_AND : NULL );
		$key = NULL;
		if ( $this->flagUseCache ) {
			$key = $this->getCacheKey( array( $conditions, $addSql, $bindings, 'select' ) );
			if ( $cached = $this->getCached( $type, $key ) ) {
				return $cached;
			}
		}
		$table = $this->esc( $type );
		$sql   = $this->makeSQLFromConditions( $conditions, $bindings, $addSql );
		$sql   = "SELECT * FROM {$table} {$sql} -- keep-cache";
		$rows  = $this->adapter->get( $sql, $bindings );
		if ( $this->flagUseCache && $key ) {
			$this->putResultInCache( $type, $key, $rows );
		}
		return $rows;
	}
	/**
	 * @see RedBean_QueryWriter::queryRecordRelated
	 */
	public function queryRecordRelated( $sourceType, $destType, $linkIDs, $addSql = '', $bindings = array() )
	{
		$addSql = $this->glueSQLCondition( $addSql, RedBean_QueryWriter::C_GLUE_WHERE );
		list( $sourceTable, $destTable, $linkTable, $sourceCol, $destCol ) = $this->getRelationalTablesAndColumns( $sourceType, $destType );
		$key = $this->getCacheKey( array( $sourceType, $destType, implode( ',', $linkIDs ), $addSql, $bindings ) );
		if ( $this->flagUseCache && $cached = $this->getCached( $destType, $key ) ) {
			return $cached;
		}
		$inClause = $this->getParametersForInClause( $linkIDs, $bindings );
		if ( $sourceType === $destType ) {
			$inClause2 = $this->getParametersForInClause( $linkIDs, $bindings, count( $bindings ) ); //for some databases
			$sql = "
			SELECT
				{$destTable}.*,
				COALESCE(
				NULLIF({$linkTable}.{$sourceCol}, {$destTable}.id),
				NULLIF({$linkTable}.{$destCol}, {$destTable}.id)) AS linked_by
			FROM {$linkTable}
			INNER JOIN {$destTable} ON
			( {$destTable}.id = {$linkTable}.{$destCol} AND {$linkTable}.{$sourceCol} IN ($inClause) ) OR
			( {$destTable}.id = {$linkTable}.{$sourceCol} AND {$linkTable}.{$destCol} IN ($inClause2) )
			{$addSql}
			-- keep-cache";
			$linkIDs = array_merge( $linkIDs, $linkIDs );
		} else {
			$sql = "
			SELECT
				{$destTable}.*,
				{$linkTable}.{$sourceCol} AS linked_by
			FROM {$linkTable}
			INNER JOIN {$destTable} ON
			( {$destTable}.id = {$linkTable}.{$destCol} AND {$linkTable}.{$sourceCol} IN ($inClause) )
			{$addSql}
			-- keep-cache";
		}
		$bindings = array_merge( $linkIDs, $bindings );
		$rows = $this->adapter->get( $sql, $bindings );
		$this->putResultInCache( $destType, $key, $rows );
		return $rows;
	}
	/**
	 * @see RedBean_QueryWriter::queryRecordLinks
	 */
	public function queryRecordLinks( $sourceType, $destType, $linkIDs, $addSql = '', $bindings = array() )
	{
		$addSql = $this->glueSQLCondition( $addSql, RedBean_QueryWriter::C_GLUE_WHERE );
		list( $sourceTable, $destTable, $linkTable, $sourceCol, $destCol ) = $this->getRelationalTablesAndColumns( $sourceType, $destType );
		$key = $this->getCacheKey( array( $sourceType, $destType, implode( ',', $linkIDs ), $addSql, $bindings ) );
		if ( $this->flagUseCache && $cached = $this->getCached( $linkTable, $key ) ) {
			return $cached;
		}
		$inClause = $this->getParametersForInClause( $linkIDs, $bindings );
		$selector = "{$linkTable}.*";
		if ( $sourceType === $destType ) {
			$inClause2 = $this->getParametersForInClause( $linkIDs, $bindings, count( $bindings ) ); //for some databases
			$sql = "
			SELECT {$selector} FROM {$linkTable}
			INNER JOIN {$destTable} ON
			( {$destTable}.id = {$linkTable}.{$destCol} AND {$linkTable}.{$sourceCol} IN ($inClause) ) OR
			( {$destTable}.id = {$linkTable}.{$sourceCol} AND {$linkTable}.{$destCol} IN ($inClause2) )
			{$addSql}
			-- keep-cache";
			$linkIDs = array_merge( $linkIDs, $linkIDs );
		} else {
			$sql = "
			SELECT {$selector} FROM {$linkTable}
			INNER JOIN {$destTable} ON
			( {$destTable}.id = {$linkTable}.{$destCol} AND {$linkTable}.{$sourceCol} IN ($inClause) )
			{$addSql}
			-- keep-cache";
		}
		$bindings = array_merge( $linkIDs, $bindings );
		$rows = $this->adapter->get( $sql, $bindings );
		$this->putResultInCache( $linkTable, $key, $rows );
		return $rows;
	}
	/**
	 * @see RedBean_QueryWriter::queryRecordLink
	 */
	public function queryRecordLink( $sourceType, $destType, $sourceID, $destID )
	{
		list( $sourceTable, $destTable, $linkTable, $sourceCol, $destCol ) = $this->getRelationalTablesAndColumns( $sourceType, $destType );
		$key = $this->getCacheKey( array( $sourceType, $destType, $sourceID, $destID ) );
		if ( $this->flagUseCache && $cached = $this->getCached( $linkTable, $key ) ) {
			return $cached;
		}
		if ( $sourceTable === $destTable ) {
			$sql = "SELECT {$linkTable}.* FROM {$linkTable}
				WHERE ( {$sourceCol} = ? AND {$destCol} = ? ) OR
				 ( {$destCol} = ? AND {$sourceCol} = ? ) -- keep-cache";
			$row = $this->adapter->getRow( $sql, array( $sourceID, $destID, $sourceID, $destID ) );
		} else {
			$sql = "SELECT {$linkTable}.* FROM {$linkTable}
				WHERE {$sourceCol} = ? AND {$destCol} = ? -- keep-cache";
			$row = $this->adapter->getRow( $sql, array( $sourceID, $destID ) );
		}
		$this->putResultInCache( $linkTable, $key, $row );
		return $row;
	}
	/**
	 * @see RedBean_QueryWriter::queryRecordCount
	 */
	public function queryRecordCount( $type, $conditions = array(), $addSql = NULL, $bindings = array() )
	{
		$addSql = $this->glueSQLCondition( $addSql );
		$table  = $this->esc( $type );
		$sql    = $this->makeSQLFromConditions( $conditions, $bindings, $addSql );
		$sql    = "SELECT COUNT(*) FROM {$table} {$sql} -- keep-cache";
		return (int) $this->adapter->getCell( $sql, $bindings );
	}
	/**
	 * @see RedBean_QueryWriter::queryRecordCountRelated
	 */
	public function queryRecordCountRelated( $sourceType, $destType, $linkID, $addSql = '', $bindings = array() )
	{
		list( $sourceTable, $destTable, $linkTable, $sourceCol, $destCol ) = $this->getRelationalTablesAndColumns( $sourceType, $destType );
		if ( $sourceType === $destType ) {
			$sql = "
			SELECT COUNT(*) FROM {$linkTable}
			INNER JOIN {$destTable} ON
			( {$destTable}.id = {$linkTable}.{$destCol} AND {$linkTable}.{$sourceCol} = ? ) OR
			( {$destTable}.id = {$linkTable}.{$sourceCol} AND {$linkTable}.{$destCol} = ? )
			{$addSql}
			-- keep-cache";
			$bindings = array_merge( array( $linkID, $linkID ), $bindings );
		} else {
			$sql = "
			SELECT COUNT(*) FROM {$linkTable}
			INNER JOIN {$destTable} ON
			( {$destTable}.id = {$linkTable}.{$destCol} AND {$linkTable}.{$sourceCol} = ? )
			{$addSql}
			-- keep-cache";
			$bindings = array_merge( array( $linkID ), $bindings );
		}
		return (int) $this->adapter->getCell( $sql, $bindings );
	}
	/**
	 * @see RedBean_QueryWriter::deleteRecord
	 */
	public function deleteRecord( $type, $conditions = array(), $addSql = NULL, $bindings = array() )
	{
		$addSql = $this->glueSQLCondition( $addSql );
		$table  = $this->esc( $type );
		$sql    = $this->makeSQLFromConditions( $conditions, $bindings, $addSql );
		$sql    = "DELETE FROM {$table} {$sql}";
		$this->adapter->exec( $sql, $bindings );
	}
	/**
	 * @see RedBean_QueryWriter::deleteRelations
	 */
	public function deleteRelations( $sourceType, $destType, $sourceID )
	{
		list( $sourceTable, $destTable, $linkTable, $sourceCol, $destCol ) = $this->getRelationalTablesAndColumns( $sourceType, $destType );
		if ( $sourceTable === $destTable ) {
			$sql = "DELETE FROM {$linkTable}
				WHERE ( {$sourceCol} = ? ) OR
				( {$destCol} = ?  )
			";
			$this->adapter->exec( $sql, array( $sourceID, $sourceID ) );
		} else {
			$sql = "DELETE FROM {$linkTable}
				WHERE {$sourceCol} = ? ";
			$this->adapter->exec( $sql, array( $sourceID ) );
		}
	}
	/**
	 * @see RedBean_QueryWriter::widenColumn
	 */
	public function widenColumn( $type, $column, $datatype )
	{
		if ( !isset($this->typeno_sqltype[$datatype]) ) return;
		$table   = $type;
		$type    = $datatype;
		$table   = $this->esc( $table );
		$column  = $this->esc( $column );
		$newtype = $this->typeno_sqltype[$type];
		$this->adapter->exec( "ALTER TABLE $table CHANGE $column $column $newtype " );
	}
	/**
	 * @see RedBean_QueryWriter::wipe
	 */
	public function wipe( $type )
	{
		$table = $this->esc( $type );
		$this->adapter->exec( "TRUNCATE $table " );
	}
	/**
	 * @see RedBean_QueryWriter::addFK
	 */
	public function addFK( $type, $targetType, $field, $targetField, $isDependent = FALSE )
	{
		$table           = $this->esc( $type );
		$tableNoQ        = $this->esc( $type, TRUE );
		$targetTable     = $this->esc( $targetType );
		$column          = $this->esc( $field );
		$columnNoQ       = $this->esc( $field, TRUE );
		$targetColumn    = $this->esc( $targetField );
		$targetColumnNoQ = $this->esc( $targetField, TRUE );
		$db = $this->adapter->getCell( 'SELECT DATABASE()' );
		$fkName = 'fk_' . $tableNoQ . '_' . $columnNoQ . '_' . $targetColumnNoQ . ( $isDependent ? '_casc' : '' );
		$cName  = 'cons_' . $fkName;
		$cfks = $this->adapter->getCell( "
			SELECT CONSTRAINT_NAME
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA ='$db' AND TABLE_NAME = '$tableNoQ'  AND COLUMN_NAME = '$columnNoQ' AND
			CONSTRAINT_NAME <>'PRIMARY' AND REFERENCED_TABLE_NAME is not null
		" );
		$flagAddKey = FALSE;
		try {
			// No keys
			if ( !$cfks ) {
				$flagAddKey = TRUE; //go get a new key
			}
			// Has fk, but different setting, --remove
			if ( $cfks && $cfks != $cName ) {
				$this->adapter->exec( "ALTER TABLE $table DROP FOREIGN KEY $cfks " );
				$flagAddKey = TRUE; //go get a new key.
			}
			if ( $flagAddKey ) {
				$this->adapter->exec( "ALTER TABLE  $table
				ADD CONSTRAINT $cName FOREIGN KEY $fkName (  $column ) REFERENCES  $targetTable (
				$targetColumn) ON DELETE " . ( $isDependent ? 'CASCADE' : 'SET NULL' ) . ' ON UPDATE SET NULL ;' );
			}
		} catch ( Exception $e ) {
			// Failure of fk-constraints is not a problem
		}
	}
	/**
	 * @see RedBean_QueryWriter::renameAssocTable
	 */
	public function renameAssocTable( $from, $to = NULL )
	{
		self::renameAssociation( $from, $to );
	}
	/**
	 * @see RedBean_QueryWriter::getAssocTable
	 */
	public function getAssocTable( $types )
	{
		return self::getAssocTableFormat( $types );
	}
	/**
	 * @see RedBean_QueryWriter::addConstraintForTypes
	 */
	public function addConstraintForTypes( $sourceType, $destType )
	{
		list( $sourceTable, $destTable, $linkTable, $sourceCol, $destCol ) = $this->getRelationalTablesAndColumns( $sourceType, $destType, TRUE );
		$this->constrain( $linkTable, $sourceTable, $destTable, $sourceCol, $destCol );
	}
	/**
	 * Turns caching on or off. Default: off.
	 * If caching is turned on retrieval queries fired after eachother will
	 * use a result row cache.
	 *
	 * @param boolean
	 */
	public function setUseCache( $yesNo )
	{
		$this->flushCache();
		$this->flagUseCache = (bool) $yesNo;
	}
	/**
	 * Flushes the Query Writer Cache.
	 *
	 * @return void
	 */
	public function flushCache()
	{
		$this->cache = array();
	}
	/**
	 * @deprecated Use esc() instead.
	 *
	 * @param string  $column   column to be escaped
	 * @param boolean $noQuotes omit quotes
	 *
	 * @return string
	 */
	public function safeColumn( $column, $noQuotes = FALSE )
	{
		return $this->esc( $column, $noQuotes );
	}
	/**
	 * @deprecated Use esc() instead.
	 *
	 * @param string  $table    table to be escaped
	 * @param boolean $noQuotes omit quotes
	 *
	 * @return string
	 */
	public function safeTable( $table, $noQuotes = FALSE )
	{
		return $this->esc( $table, $noQuotes );
	}
	/**
	 * @deprecated Use addContraintForTypes instead.
	 *
	 * @param RedBean_OODBBean $bean1 bean
	 * @param RedBean_OODBBean $bean2 bean
	 *
	 * @return void
	 */
	public function addConstraint( RedBean_OODBBean $bean1, RedBean_OODBBean $bean2 )
	{
		$this->addConstraintForTypes( $bean1->getMeta( 'type' ), $bean2->getMeta( 'type' ) );
	}
}
class RedBean_QueryWriter_MySQL extends RedBean_QueryWriter_AQueryWriter implements RedBean_QueryWriter
{
	/**
	 * Data types
	 */
	const C_DATATYPE_BOOL             = 0;
	const C_DATATYPE_UINT8            = 1;
	const C_DATATYPE_UINT32           = 2;
	const C_DATATYPE_DOUBLE           = 3;
	const C_DATATYPE_TEXT8            = 4;
	const C_DATATYPE_TEXT16           = 5;
	const C_DATATYPE_TEXT32           = 6;
	const C_DATATYPE_SPECIAL_DATE     = 80;
	const C_DATATYPE_SPECIAL_DATETIME = 81;
	const C_DATATYPE_SPECIAL_POINT    = 90;
	const C_DATATYPE_SPECIFIED        = 99;
	/**
	 * @var RedBean_Adapter_DBAdapter
	 */
	protected $adapter;
	/**
	 * @var string
	 */
	protected $quoteCharacter = '`';
	/**
	 * Add the constraints for a specific database driver: MySQL.
	 *
	 * @todo Too many arguments; find a way to solve this in a neater way.
	 *
	 * @param string $table     table     table to add constrains to
	 * @param string $table1    table1    first reference table
	 * @param string $table2    table2    second reference table
	 * @param string $property1 property1 first column
	 * @param string $property2 property2 second column
	 *
	 * @return boolean $succes whether the constraint has been applied
	 */
	protected function constrain( $table, $table1, $table2, $property1, $property2 )
	{
		try {
			$db  = $this->adapter->getCell( 'SELECT database()' );
			$fks = $this->adapter->getCell(
				"SELECT count(*)
				FROM information_schema.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND
				CONSTRAINT_NAME <>'PRIMARY' AND REFERENCED_TABLE_NAME IS NOT NULL",
				array( $db, $table )
			);
			// already foreign keys added in this association table
			if ( $fks > 0 ) {
				return FALSE;
			}
			$columns = $this->getColumns( $table );
			if ( $this->code( $columns[$property1] ) !== RedBean_QueryWriter_MySQL::C_DATATYPE_UINT32 ) {
				$this->widenColumn( $table, $property1, RedBean_QueryWriter_MySQL::C_DATATYPE_UINT32 );
			}
			if ( $this->code( $columns[$property2] ) !== RedBean_QueryWriter_MySQL::C_DATATYPE_UINT32 ) {
				$this->widenColumn( $table, $property2, RedBean_QueryWriter_MySQL::C_DATATYPE_UINT32 );
			}
			$sql = "
				ALTER TABLE " . $this->esc( $table ) . "
				ADD FOREIGN KEY($property1) references `$table1`(id) ON DELETE CASCADE;
			";
			$this->adapter->exec( $sql );
			$sql = "
				ALTER TABLE " . $this->esc( $table ) . "
				ADD FOREIGN KEY($property2) references `$table2`(id) ON DELETE CASCADE
			";
			$this->adapter->exec( $sql );
			return TRUE;
		} catch ( Exception $e ) {
			return FALSE;
		}
	}
	/**
	 * Constructor
	 *
	 * @param RedBean_Adapter $adapter Database Adapter
	 */
	public function __construct( RedBean_Adapter $adapter )
	{
		$this->typeno_sqltype = array(
			RedBean_QueryWriter_MySQL::C_DATATYPE_BOOL             => ' TINYINT(1) UNSIGNED ',
			RedBean_QueryWriter_MySQL::C_DATATYPE_UINT8            => ' TINYINT(3) UNSIGNED ',
			RedBean_QueryWriter_MySQL::C_DATATYPE_UINT32           => ' INT(11) UNSIGNED ',
			RedBean_QueryWriter_MySQL::C_DATATYPE_DOUBLE           => ' DOUBLE ',
			RedBean_QueryWriter_MySQL::C_DATATYPE_TEXT8            => ' VARCHAR(255) ',
			RedBean_QueryWriter_MySQL::C_DATATYPE_TEXT16           => ' TEXT ',
			RedBean_QueryWriter_MySQL::C_DATATYPE_TEXT32           => ' LONGTEXT ',
			RedBean_QueryWriter_MySQL::C_DATATYPE_SPECIAL_DATE     => ' DATE ',
			RedBean_QueryWriter_MySQL::C_DATATYPE_SPECIAL_DATETIME => ' DATETIME ',
			RedBean_QueryWriter_MySQL::C_DATATYPE_SPECIAL_POINT    => ' POINT ',
		);
		$this->sqltype_typeno = array();
		foreach ( $this->typeno_sqltype as $k => $v ) {
			$this->sqltype_typeno[trim( strtolower( $v ) )] = $k;
		}
		$this->adapter = $adapter;
		$this->encoding = $this->adapter->getDatabase()->getMysqlEncoding();
	}
	/**
	 * This method returns the datatype to be used for primary key IDS and
	 * foreign keys. Returns one if the data type constants.
	 *
	 * @return integer $const data type to be used for IDS.
	 */
	public function getTypeForID()
	{
		return self::C_DATATYPE_UINT32;
	}
	/**
	 * @see RedBean_QueryWriter::getTables
	 */
	public function getTables()
	{
		return $this->adapter->getCol( 'show tables' );
	}
	/**
	 * @see RedBean_QueryWriter::createTable
	 */
	public function createTable( $table )
	{
		$table = $this->esc( $table );
		$encoding = $this->adapter->getDatabase()->getMysqlEncoding();
		$sql   = "CREATE TABLE $table (id INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY ( id )) ENGINE = InnoDB DEFAULT CHARSET={$encoding} COLLATE={$encoding}_unicode_ci ";
		$this->adapter->exec( $sql );
	}
	/**
	 * @see RedBean_QueryWriter::getColumns
	 */
	public function getColumns( $table )
	{
		$columnsRaw = $this->adapter->get( "DESCRIBE " . $this->esc( $table ) );
		$columns = array();
		foreach ( $columnsRaw as $r ) {
			$columns[$r['Field']] = $r['Type'];
		}
		return $columns;
	}
	/**
	 * @see RedBean_QueryWriter::scanType
	 */
	public function scanType( $value, $flagSpecial = FALSE )
	{
		$this->svalue = $value;
		if ( is_null( $value ) ) return RedBean_QueryWriter_MySQL::C_DATATYPE_BOOL;
		if ( $flagSpecial ) {
			if ( preg_match( '/^\d{4}\-\d\d-\d\d$/', $value ) ) {
				return RedBean_QueryWriter_MySQL::C_DATATYPE_SPECIAL_DATE;
			}
			if ( preg_match( '/^\d{4}\-\d\d-\d\d\s\d\d:\d\d:\d\d$/', $value ) ) {
				return RedBean_QueryWriter_MySQL::C_DATATYPE_SPECIAL_DATETIME;
			}
		}
		$value = strval( $value );
		if ( !$this->startsWithZeros( $value ) ) {
			if ( $value === TRUE || $value === FALSE || $value === '1' || $value === '' ) {
				return RedBean_QueryWriter_MySQL::C_DATATYPE_BOOL;
			}
			if ( is_numeric( $value ) && ( floor( $value ) == $value ) && $value >= 0 && $value <= 255 ) {
				return RedBean_QueryWriter_MySQL::C_DATATYPE_UINT8;
			}
			if ( is_numeric( $value ) && ( floor( $value ) == $value ) && $value >= 0 && $value <= 4294967295 ) {
				return RedBean_QueryWriter_MySQL::C_DATATYPE_UINT32;
			}
			if ( is_numeric( $value ) ) {
				return RedBean_QueryWriter_MySQL::C_DATATYPE_DOUBLE;
			}
		}
		if ( mb_strlen( $value, 'UTF-8' ) <= 255 ) {
			return RedBean_QueryWriter_MySQL::C_DATATYPE_TEXT8;
		}
		if ( mb_strlen( $value, 'UTF-8' ) <= 65535 ) {
			return RedBean_QueryWriter_MySQL::C_DATATYPE_TEXT16;
		}
		return RedBean_QueryWriter_MySQL::C_DATATYPE_TEXT32;
	}
	/**
	 * @see RedBean_QueryWriter::code
	 */
	public function code( $typedescription, $includeSpecials = FALSE )
	{
		if ( isset( $this->sqltype_typeno[$typedescription] ) ) {
			$r = $this->sqltype_typeno[$typedescription];
		} else {
			$r = self::C_DATATYPE_SPECIFIED;
		}
		if ( $includeSpecials ) {
			return $r;
		}
		if ( $r >= RedBean_QueryWriter::C_DATATYPE_RANGE_SPECIAL ) {
			return self::C_DATATYPE_SPECIFIED;
		}
		return $r;
	}
	/**
	 * @see RedBean_QueryWriter::addUniqueIndex
	 */
	public function addUniqueIndex( $table, $columns )
	{
		$table = $this->esc( $table );
		sort( $columns ); // Else we get multiple indexes due to order-effects
		foreach ( $columns as $k => $v ) {
			$columns[$k] = $this->esc( $v );
		}
		$r    = $this->adapter->get( "SHOW INDEX FROM $table" );
		$name = 'UQ_' . sha1( implode( ',', $columns ) );
		if ( $r ) {
			foreach ( $r as $i ) {
				if ( $i['Key_name'] == $name ) {
					return;
				}
			}
		}
		$sql = "ALTER IGNORE TABLE $table
                ADD UNIQUE INDEX $name (" . implode( ',', $columns ) . ")";
		$this->adapter->exec( $sql );
	}
	/**
	 * @see RedBean_QueryWriter::addIndex
	 */
	public function addIndex( $type, $name, $column )
	{
		$table  = $type;
		$table  = $this->esc( $table );
		$name   = preg_replace( '/\W/', '', $name );
		$column = $this->esc( $column );
		foreach ( $this->adapter->get( "SHOW INDEX FROM $table " ) as $ind ) if ( $ind['Key_name'] === $name ) return;
		try {
			$this->adapter->exec( "CREATE INDEX $name ON $table ($column) " );
		} catch ( Exception $e ) {
		}
	}
	/**
	 * @see RedBean_QueryWriter::sqlStateIn
	 */
	public function sqlStateIn( $state, $list )
	{
		$stateMap = array(
			'42S02' => RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE,
			'42S22' => RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_COLUMN,
			'23000' => RedBean_QueryWriter::C_SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION
		);
		return in_array( ( isset( $stateMap[$state] ) ? $stateMap[$state] : '0' ), $list );
	}
	/**
	 * @see RedBean_QueryWriter::wipeAll
	 */
	public function wipeAll()
	{
		$this->adapter->exec( 'SET FOREIGN_KEY_CHECKS = 0;' );
		foreach ( $this->getTables() as $t ) {
			try {
				$this->adapter->exec( "DROP TABLE IF EXISTS `$t`" );
			} catch ( Exception $e ) {
			}
			try {
				$this->adapter->exec( "DROP VIEW IF EXISTS `$t`" );
			} catch ( Exception $e ) {
			}
		}
		$this->adapter->exec( 'SET FOREIGN_KEY_CHECKS = 1;' );
	}
}
class RedBean_QueryWriter_SQLiteT extends RedBean_QueryWriter_AQueryWriter implements RedBean_QueryWriter
{
	/**
	 * @var RedBean_Adapter_DBAdapter
	 */
	protected $adapter;
	/**
	 * @var string
	 */
	protected $quoteCharacter = '`';
	/**
	 * Data types
	 */
	const C_DATATYPE_INTEGER   = 0;
	const C_DATATYPE_NUMERIC   = 1;
	const C_DATATYPE_TEXT      = 2;
	const C_DATATYPE_SPECIFIED = 99;
	/**
	 * Gets all information about a table (from a type).
	 *
	 * Format:
	 * array(
	 *    name => name of the table
	 *    columns => array( name => datatype )
	 *    indexes => array() raw index information rows from PRAGMA query
	 *    keys => array() raw key information rows from PRAGMA query
	 * )
	 *
	 * @param string $type type you want to get info of
	 *
	 * @return array $info
	 */
	protected function getTable( $type )
	{
		$tableName = $this->esc( $type, TRUE );
		$columns   = $this->getColumns( $type );
		$indexes   = $this->getIndexes( $type );
		$keys      = $this->getKeys( $type );
		$table = array(
			'columns' => $columns,
			'indexes' => $indexes,
			'keys' => $keys,
			'name' => $tableName
		);
		$this->tableArchive[$tableName] = $table;
		return $table;
	}
	/**
	 * Puts a table. Updates the table structure.
	 * In SQLite we can't change columns, drop columns, change or add foreign keys so we
	 * have a table-rebuild function. You simply load your table with getTable(), modify it and
	 * then store it with putTable()...
	 *
	 * @param array $tableMap information array
	 */
	protected function putTable( $tableMap )
	{
		$table = $tableMap['name'];
		$q     = array();
		$q[]   = "DROP TABLE IF EXISTS tmp_backup;";
		$oldColumnNames = array_keys( $this->getColumns( $table ) );
		foreach ( $oldColumnNames as $k => $v ) $oldColumnNames[$k] = "`$v`";
		$q[] = "CREATE TEMPORARY TABLE tmp_backup(" . implode( ",", $oldColumnNames ) . ");";
		$q[] = "INSERT INTO tmp_backup SELECT * FROM `$table`;";
		$q[] = "PRAGMA foreign_keys = 0 ";
		$q[] = "DROP TABLE `$table`;";
		$newTableDefStr = '';
		foreach ( $tableMap['columns'] as $column => $type ) {
			if ( $column != 'id' ) {
				$newTableDefStr .= ",`$column` $type";
			}
		}
		$fkDef = '';
		foreach ( $tableMap['keys'] as $key ) {
			$fkDef .= ", FOREIGN KEY(`{$key['from']}`)
						 REFERENCES `{$key['table']}`(`{$key['to']}`)
						 ON DELETE {$key['on_delete']} ON UPDATE {$key['on_update']}";
		}
		$q[] = "CREATE TABLE `$table` ( `id` INTEGER PRIMARY KEY AUTOINCREMENT  $newTableDefStr  $fkDef );";
		foreach ( $tableMap['indexes'] as $name => $index ) {
			if ( strpos( $name, 'UQ_' ) === 0 ) {
				$cols = explode( '__', substr( $name, strlen( 'UQ_' . $table ) ) );
				foreach ( $cols as $k => $v ) $cols[$k] = "`$v`";
				$q[] = "CREATE UNIQUE INDEX $name ON `$table` (" . implode( ',', $cols ) . ")";
			} else $q[] = "CREATE INDEX $name ON `$table` ({$index['name']}) ";
		}
		$q[] = "INSERT INTO `$table` SELECT * FROM tmp_backup;";
		$q[] = "DROP TABLE tmp_backup;";
		$q[] = "PRAGMA foreign_keys = 1 ";
		foreach ( $q as $sq ) $this->adapter->exec( $sq );
	}
	/**
	 * Returns the indexes for type $type.
	 *
	 * @param string $type
	 *
	 * @return array $indexInfo index information
	 */
	protected function getIndexes( $type )
	{
		$table   = $this->esc( $type, TRUE );
		$indexes = $this->adapter->get( "PRAGMA index_list('$table')" );
		$indexInfoList = array();
		foreach ( $indexes as $i ) {
			$indexInfoList[$i['name']] = $this->adapter->getRow( "PRAGMA index_info('{$i['name']}') " );
			$indexInfoList[$i['name']]['unique'] = $i['unique'];
		}
		return $indexInfoList;
	}
	/**
	 * Returns the keys for type $type.
	 *
	 * @param string $type
	 *
	 * @return array $keysInfo keys information
	 */
	protected function getKeys( $type )
	{
		$table = $this->esc( $type, TRUE );
		$keys  = $this->adapter->get( "PRAGMA foreign_key_list('$table')" );
		$keyInfoList = array();
		foreach ( $keys as $k ) {
			$keyInfoList['from_' . $k['from'] . '_to_table_' . $k['table'] . '_col_' . $k['to']] = $k;
		}
		return $keyInfoList;
	}
	/**
	 * Adds a foreign key to a type
	 *
	 * @param  string  $type        type you want to modify table of
	 * @param  string  $targetType  target type
	 * @param  string  $field       field of the type that needs to get the fk
	 * @param  string  $targetField field where the fk needs to point to
	 * @param  integer $buildopt    0 = NO ACTION, 1 = ON DELETE CASCADE
	 *
	 * @return boolean $didIt
	 *
	 * @note: cant put this in try-catch because that can hide the fact
	 *      that database has been damaged.
	 */
	protected function buildFK( $type, $targetType, $field, $targetField, $constraint = FALSE )
	{
		$consSQL = ( $constraint ? 'CASCADE' : 'SET NULL' );
		$t       = $this->getTable( $type );
		$label   = 'from_' . $field . '_to_table_' . $targetType . '_col_' . $targetField;
		if ( isset( $t['keys'][$label] )
			&& $t['keys'][$label]['table'] === $targetType
			&& $t['keys'][$label]['from'] === $field
			&& $t['keys'][$label]['to'] === $targetField
			&& $t['keys'][$label]['on_delete'] === $consSQL
		) return FALSE;
		$t['keys'][$label] = array(
			'table'     => $targetType,
			'from'      => $field,
			'to'        => $targetField,
			'on_update' => 'SET NULL',
			'on_delete' => $consSQL
		);
		$this->putTable( $t );
		return TRUE;
	}
	/**
	 * Add the constraints for a specific database driver: SQLite.
	 *
	 * @param string $table     table to add fk constrains to
	 * @param string $table1    first reference table
	 * @param string $table2    second reference table
	 * @param string $property1 first reference column
	 * @param string $property2 second reference column
	 *
	 * @return boolean $success whether the constraint has been applied
	 */
	protected function constrain( $table, $table1, $table2, $property1, $property2 )
	{
		$firstState  = $this->buildFK( $table, $table1, $property1, 'id', TRUE );
		$secondState = $this->buildFK( $table, $table2, $property2, 'id', TRUE );
		return ( $firstState && $secondState );
	}
	/**
	 * Constructor
	 *
	 * @param RedBean_Adapter $adapter Database Adapter
	 */
	public function __construct( RedBean_Adapter $adapter )
	{
		$this->typeno_sqltype = array(
			RedBean_QueryWriter_SQLiteT::C_DATATYPE_INTEGER => 'INTEGER',
			RedBean_QueryWriter_SQLiteT::C_DATATYPE_NUMERIC => 'NUMERIC',
			RedBean_QueryWriter_SQLiteT::C_DATATYPE_TEXT    => 'TEXT',
		);
		$this->sqltype_typeno = array();
		foreach ( $this->typeno_sqltype as $k => $v ) {
			$this->sqltype_typeno[$v] = $k;
		}
		$this->adapter = $adapter;
	}
	/**
	 * This method returns the datatype to be used for primary key IDS and
	 * foreign keys. Returns one if the data type constants.
	 *
	 * @return integer $const data type to be used for IDS.
	 */
	public function getTypeForID()
	{
		return self::C_DATATYPE_INTEGER;
	}
	/**
	 * @see RedBean_QueryWriter::scanType
	 */
	public function scanType( $value, $flagSpecial = FALSE )
	{
		$this->svalue = $value;
		if ( $value === FALSE ) return self::C_DATATYPE_INTEGER;
		if ( $value === NULL ) return self::C_DATATYPE_INTEGER;
		if ( $this->startsWithZeros( $value ) ) return self::C_DATATYPE_TEXT;
		if ( is_numeric( $value ) && ( intval( $value ) == $value ) && $value < 2147483648 ) return self::C_DATATYPE_INTEGER;
		if ( ( is_numeric( $value ) && $value < 2147483648 )
			|| preg_match( '/\d{4}\-\d\d\-\d\d/', $value )
			|| preg_match( '/\d{4}\-\d\d\-\d\d\s\d\d:\d\d:\d\d/', $value )
		) {
			return self::C_DATATYPE_NUMERIC;
		}
		return self::C_DATATYPE_TEXT;
	}
	/**
	 * @see RedBean_QueryWriter::addColumn
	 */
	public function addColumn( $table, $column, $type )
	{
		$column = $this->check( $column );
		$table  = $this->check( $table );
		$type   = $this->typeno_sqltype[$type];
		$this->adapter->exec( "ALTER TABLE `$table` ADD `$column` $type " );
	}
	/**
	 * @see RedBean_QueryWriter::code
	 */
	public function code( $typedescription, $includeSpecials = FALSE )
	{
		$r = ( ( isset( $this->sqltype_typeno[$typedescription] ) ) ? $this->sqltype_typeno[$typedescription] : 99 );
		
		return $r;
	}
	/**
	 * @see RedBean_QueryWriter::widenColumn
	 */
	public function widenColumn( $type, $column, $datatype )
	{
		$t = $this->getTable( $type );
		$t['columns'][$column] = $this->typeno_sqltype[$datatype];
		$this->putTable( $t );
	}
	/**
	 * @see RedBean_QueryWriter::getTables();
	 */
	public function getTables()
	{
		return $this->adapter->getCol( "SELECT name FROM sqlite_master
			WHERE type='table' AND name!='sqlite_sequence';" );
	}
	/**
	 * @see RedBean_QueryWriter::createTable
	 */
	public function createTable( $table )
	{
		$table = $this->esc( $table );
		$sql   = "CREATE TABLE $table ( id INTEGER PRIMARY KEY AUTOINCREMENT ) ";
		$this->adapter->exec( $sql );
	}
	/**
	 * @see RedBean_QueryWriter::getColumns
	 */
	public function getColumns( $table )
	{
		$table      = $this->esc( $table, TRUE );
		$columnsRaw = $this->adapter->get( "PRAGMA table_info('$table')" );
		$columns    = array();
		foreach ( $columnsRaw as $r ) $columns[$r['name']] = $r['type'];
		return $columns;
	}
	/**
	 * @see RedBean_QueryWriter::addUniqueIndex
	 */
	public function addUniqueIndex( $type, $columns )
	{
		$name  = 'UQ_' . $this->esc( $type, TRUE ) . implode( '__', $columns );
		$t     = $this->getTable( $type );
		if ( isset( $t['indexes'][$name] ) ) return;
		$t['indexes'][$name] = array( 'name' => $name );
		$this->putTable( $t );
	}
	/**
	 * @see RedBean_QueryWriter::sqlStateIn
	 */
	public function sqlStateIn( $state, $list )
	{
		$stateMap = array(
			'HY000' => RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE,
			'23000' => RedBean_QueryWriter::C_SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION
		);
		return in_array( ( isset( $stateMap[$state] ) ? $stateMap[$state] : '0' ), $list );
	}
	/**
	 * @see RedBean_QueryWriter::addIndex
	 */
	public function addIndex( $type, $name, $column )
	{
		$table  = $type;
		$table  = $this->esc( $table );
		$name   = preg_replace( '/\W/', '', $name );
		$column = $this->esc( $column, TRUE );
		foreach ( $this->adapter->get( "PRAGMA INDEX_LIST($table) " ) as $ind ) {
			if ( $ind['name'] === $name ) return;
		}
		$t = $this->getTable( $type );
		$t['indexes'][$name] = array( 'name' => $column );
		$this->putTable( $t );
	}
	/**
	 * @see RedBean_QueryWriter::wipe
	 */
	public function wipe( $type )
	{
		$table = $this->esc( $type );
		
		$this->adapter->exec( "DELETE FROM $table " );
	}
	/**
	 * @see RedBean_QueryWriter::addFK
	 */
	public function addFK( $type, $targetType, $field, $targetField, $isDep = FALSE )
	{
		return $this->buildFK( $type, $targetType, $field, $targetField, $isDep );
	}
	/**
	 * @see RedBean_QueryWriter::wipeAll
	 */
	public function wipeAll()
	{
		$this->adapter->exec( 'PRAGMA foreign_keys = 0 ' );
		foreach ( $this->getTables() as $t ) {
			try {
				$this->adapter->exec( "DROP TABLE IF EXISTS `$t`" );
			} catch ( Exception $e ) {
			}
			try {
				$this->adapter->exec( "DROP TABLE IF EXISTS `$t`" );
			} catch ( Exception $e ) {
			}
		}
		$this->adapter->exec( 'PRAGMA foreign_keys = 1 ' );
	}
}
class RedBean_QueryWriter_PostgreSQL extends RedBean_QueryWriter_AQueryWriter implements RedBean_QueryWriter
{
	/**
	 * Data types
	 */
	const C_DATATYPE_INTEGER          = 0;
	const C_DATATYPE_DOUBLE           = 1;
	const C_DATATYPE_TEXT             = 3;
	const C_DATATYPE_SPECIAL_DATE     = 80;
	const C_DATATYPE_SPECIAL_DATETIME = 81;
	const C_DATATYPE_SPECIAL_POINT    = 90;
	const C_DATATYPE_SPECIAL_LSEG     = 91;
	const C_DATATYPE_SPECIAL_CIRCLE   = 92;
	const C_DATATYPE_SPECIAL_MONEY    = 93;
	const C_DATATYPE_SPECIFIED        = 99;
	/**
	 * @var RedBean_Adapter_DBAdapter
	 */
	protected $adapter;
	/**
	 * @var string
	 */
	protected $quoteCharacter = '"';
	/**
	 * @var string
	 */
	protected $defaultValue = 'DEFAULT';
	/**
	 * Returns the insert suffix SQL Snippet
	 *
	 * @param string $table table
	 *
	 * @return  string $sql SQL Snippet
	 */
	protected function getInsertSuffix( $table )
	{
		return 'RETURNING id ';
	}
	/**
	 * Add the constraints for a specific database driver: PostgreSQL.
	 *
	 * @param string $table     table to add fk constraints to
	 * @param string $table1    first reference table
	 * @param string $table2    second reference table
	 * @param string $property1 first reference column
	 * @param string $property2 second reference column
	 *
	 * @return boolean
	 */
	protected function constrain( $table, $table1, $table2, $property1, $property2 )
	{
		try {
			$adapter = $this->adapter;
			$fkCode  = 'fk' . md5( $table . $property1 . $property2 );
			$sql = "SELECT c.oid, n.nspname, c.relname,
				n2.nspname, c2.relname, cons.conname
				FROM pg_class c
				JOIN pg_namespace n ON n.oid = c.relnamespace
				LEFT OUTER JOIN pg_constraint cons ON cons.conrelid = c.oid
				LEFT OUTER JOIN pg_class c2 ON cons.confrelid = c2.oid
				LEFT OUTER JOIN pg_namespace n2 ON n2.oid = c2.relnamespace
				WHERE c.relkind = 'r'
					AND n.nspname IN ('public')
					AND (cons.contype = 'f' OR cons.contype IS NULL)
					AND (  cons.conname = '{$fkCode}a'	OR  cons.conname = '{$fkCode}b' )
			";
			$rows    = $adapter->get( $sql );
			if ( !count( $rows ) ) {
				$sql1 = "ALTER TABLE \"$table\" ADD CONSTRAINT
					{$fkCode}a FOREIGN KEY ($property1)
					REFERENCES \"$table1\" (id) ON DELETE CASCADE ";
				$sql2 = "ALTER TABLE \"$table\" ADD CONSTRAINT
					{$fkCode}b FOREIGN KEY ($property2)
					REFERENCES \"$table2\" (id) ON DELETE CASCADE ";
				$adapter->exec( $sql1 );
				$adapter->exec( $sql2 );
			}
			return TRUE;
		} catch ( Exception $e ) {
			return FALSE;
		}
	}
	/**
	 * Constructor
	 *
	 * @param RedBean_Adapter $adapter Database Adapter
	 */
	public function __construct( RedBean_Adapter $adapter )
	{
		$this->typeno_sqltype = array(
			self::C_DATATYPE_INTEGER          => ' integer ',
			self::C_DATATYPE_DOUBLE           => ' double precision ',
			self::C_DATATYPE_TEXT             => ' text ',
			self::C_DATATYPE_SPECIAL_DATE     => ' date ',
			self::C_DATATYPE_SPECIAL_DATETIME => ' timestamp without time zone ',
			self::C_DATATYPE_SPECIAL_POINT    => ' point ',
			self::C_DATATYPE_SPECIAL_LSEG     => ' lseg ',
			self::C_DATATYPE_SPECIAL_CIRCLE   => ' circle ',
			self::C_DATATYPE_SPECIAL_MONEY    => ' money ',
		);
		$this->sqltype_typeno = array();
		foreach ( $this->typeno_sqltype as $k => $v ) {
			$this->sqltype_typeno[trim( strtolower( $v ) )] = $k;
		}
		$this->adapter = $adapter;
	}
	/**
	 * This method returns the datatype to be used for primary key IDS and
	 * foreign keys. Returns one if the data type constants.
	 *
	 * @return integer $const data type to be used for IDS.
	 */
	public function getTypeForID()
	{
		return self::C_DATATYPE_INTEGER;
	}
	/**
	 * @see RedBean_QueryWriter::getTables
	 */
	public function getTables()
	{
		return $this->adapter->getCol( "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'" );
	}
	/**
	 * @see RedBean_QueryWriter::createTable
	 */
	public function createTable( $table )
	{
		$table = $this->esc( $table );
		$this->adapter->exec( " CREATE TABLE $table (id SERIAL PRIMARY KEY); " );
	}
	/**
	 * @see RedBean_QueryWriter::getColumns
	 */
	public function getColumns( $table )
	{
		$table      = $this->esc( $table, TRUE );
		$columnsRaw = $this->adapter->get( "SELECT column_name, data_type FROM information_schema.columns WHERE table_name='$table'" );
		$columns = array();
		foreach ( $columnsRaw as $r ) {
			$columns[$r['column_name']] = $r['data_type'];
		}
		return $columns;
	}
	/**
	 * @see RedBean_QueryWriter::scanType
	 */
	public function scanType( $value, $flagSpecial = FALSE )
	{
		$this->svalue = $value;
		if ( $flagSpecial && $value ) {
			if ( preg_match( '/^\d{4}\-\d\d-\d\d$/', $value ) ) {
				return RedBean_QueryWriter_PostgreSQL::C_DATATYPE_SPECIAL_DATE;
			}
			if ( preg_match( '/^\d{4}\-\d\d-\d\d\s\d\d:\d\d:\d\d(\.\d{1,6})?$/', $value ) ) {
				return RedBean_QueryWriter_PostgreSQL::C_DATATYPE_SPECIAL_DATETIME;
			}
			if ( preg_match( '/^\([\d\.]+,[\d\.]+\)$/', $value ) ) {
				return RedBean_QueryWriter_PostgreSQL::C_DATATYPE_SPECIAL_POINT;
			}
			if ( preg_match( '/^\[\([\d\.]+,[\d\.]+\),\([\d\.]+,[\d\.]+\)\]$/', $value ) ) {
				return RedBean_QueryWriter_PostgreSQL::C_DATATYPE_SPECIAL_LSEG;
			}
			if ( preg_match( '/^\<\([\d\.]+,[\d\.]+\),[\d\.]+\>$/', $value ) ) {
				return RedBean_QueryWriter_PostgreSQL::C_DATATYPE_SPECIAL_CIRCLE;
			}
			if ( preg_match( '/^\-?\$\d+/', $value ) ) {
				return RedBean_QueryWriter_PostgreSQL::C_DATATYPE_SPECIAL_MONEY;
			}
		}
		$sz = ( $this->startsWithZeros( $value ) );
		if ( $sz ) {
			return self::C_DATATYPE_TEXT;
		}
		if ( $value === NULL || ( $value instanceof RedBean_Driver_PDO_NULL ) || ( is_numeric( $value )
				&& floor( $value ) == $value
				&& $value < 2147483648
				&& $value > -2147483648 )
		) {
			return self::C_DATATYPE_INTEGER;
		} elseif ( is_numeric( $value ) ) {
			return self::C_DATATYPE_DOUBLE;
		} else {
			return self::C_DATATYPE_TEXT;
		}
	}
	/**
	 * @see RedBean_QueryWriter::code
	 */
	public function code( $typedescription, $includeSpecials = FALSE )
	{
		$r = ( isset( $this->sqltype_typeno[$typedescription] ) ) ? $this->sqltype_typeno[$typedescription] : 99;
		if ( $includeSpecials ) return $r;
		if ( $r >= RedBean_QueryWriter::C_DATATYPE_RANGE_SPECIAL ) {
			return self::C_DATATYPE_SPECIFIED;
		}
		return $r;
	}
	/**
	 * @see RedBean_QueryWriter::widenColumn
	 */
	public function widenColumn( $type, $column, $datatype )
	{
		$table   = $type;
		$type    = $datatype;
		$table   = $this->esc( $table );
		$column  = $this->esc( $column );
		$newtype = $this->typeno_sqltype[$type];
		$this->adapter->exec( "ALTER TABLE $table \n\t ALTER COLUMN $column TYPE $newtype " );
	}
	/**
	 * @see RedBean_QueryWriter::addUniqueIndex
	 */
	public function addUniqueIndex( $table, $columns )
	{
		$table = $this->esc( $table, TRUE );
		sort( $columns ); //else we get multiple indexes due to order-effects
		foreach ( $columns as $k => $v ) {
			$columns[$k] = $this->esc( $v );
		}
		$r = $this->adapter->get( "SELECT i.relname AS index_name
			FROM pg_class t,pg_class i,pg_index ix,pg_attribute a
			WHERE t.oid = ix.indrelid
				AND i.oid = ix.indexrelid
				AND a.attrelid = t.oid
				AND a.attnum = ANY(ix.indkey)
				AND t.relkind = 'r'
				AND t.relname = '$table'
			ORDER BY t.relname, i.relname;" );
		$name = "UQ_" . sha1( $table . implode( ',', $columns ) );
		if ( $r ) {
			foreach ( $r as $i ) {
				if ( strtolower( $i['index_name'] ) == strtolower( $name ) ) {
					return;
				}
			}
		}
		$sql = "ALTER TABLE \"$table\"
                ADD CONSTRAINT $name UNIQUE (" . implode( ',', $columns ) . ")";
		$this->adapter->exec( $sql );
	}
	/**
	 * @see RedBean_QueryWriter::sqlStateIn
	 */
	public function sqlStateIn( $state, $list )
	{
		$stateMap = array(
			'42P01' => RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE,
			'42703' => RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_COLUMN,
			'23505' => RedBean_QueryWriter::C_SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION
		);
		return in_array( ( isset( $stateMap[$state] ) ? $stateMap[$state] : '0' ), $list );
	}
	/**
	 * @see RedBean_QueryWriter::addIndex
	 */
	public function addIndex( $type, $name, $column )
	{
		$table  = $type;
		$table  = $this->esc( $table );
		$name   = preg_replace( '/\W/', '', $name );
		$column = $this->esc( $column );
		if ( $this->adapter->getCell( "SELECT COUNT(*) FROM pg_class WHERE relname = '$name'" ) ) {
			return;
		}
		try {
			$this->adapter->exec( "CREATE INDEX $name ON $table ($column) " );
		} catch ( Exception $e ) {
		}
	}
	/**
	 * @see RedBean_QueryWriter::addFK
	 */
	public function addFK( $type, $targetType, $field, $targetField, $isDep = FALSE )
	{
		try {
			$table           = $this->esc( $type );
			$column          = $this->esc( $field );
			$tableNoQ        = $this->esc( $type, TRUE );
			$columnNoQ       = $this->esc( $field, TRUE );
			$targetTable     = $this->esc( $targetType );
			$targetTableNoQ  = $this->esc( $targetType, TRUE );
			$targetColumn    = $this->esc( $targetField );
			$targetColumnNoQ = $this->esc( $targetField, TRUE );
			$sql = "SELECT
				tc.constraint_name, tc.table_name,
				kcu.column_name, ccu.table_name AS foreign_table_name,
				ccu.column_name AS foreign_column_name,rc.delete_rule
				FROM information_schema.table_constraints AS tc
				JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
				JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
				JOIN information_schema.referential_constraints AS rc ON ccu.constraint_name = rc.constraint_name
				WHERE constraint_type = 'FOREIGN KEY' AND tc.table_catalog=current_database()
					AND tc.table_name = '$tableNoQ'
					AND ccu.table_name = '$targetTableNoQ'
					AND kcu.column_name = '$columnNoQ'
					AND ccu.column_name = '$targetColumnNoQ'
			";
			$row             = $this->adapter->getRow( $sql );
			$flagAddKey      = FALSE;
			if ( !$row ) $flagAddKey = TRUE;
			if ( $row ) {
				if ( ( $row['delete_rule'] == 'SET NULL' && $isDep ) ||
					( $row['delete_rule'] != 'SET NULL' && !$isDep )
				) {
					// Delete old key and order a new one
					$flagAddKey = TRUE;
					$cName      = $row['constraint_name'];
					$sql        = "ALTER TABLE $table DROP CONSTRAINT $cName ";
					$this->adapter->exec( $sql );
				}
			}
			if ( $flagAddKey ) {
				$delRule = ( $isDep ? 'CASCADE' : 'SET NULL' );
				$this->adapter->exec( "ALTER TABLE  $table
					ADD FOREIGN KEY (  $column ) REFERENCES  $targetTable (
					$targetColumn) ON DELETE $delRule ON UPDATE SET NULL DEFERRABLE ;" );
				return TRUE;
			}
			return FALSE;
		} catch ( Exception $e ) {
			return FALSE;
		}
	}
	/**
	 * @see RedBean_QueryWriter::wipeAll
	 */
	public function wipeAll()
	{
		$this->adapter->exec( 'SET CONSTRAINTS ALL DEFERRED' );
		foreach ( $this->getTables() as $t ) {
			$t = $this->esc( $t );
			$this->adapter->exec( "DROP TABLE IF EXISTS $t CASCADE " );
		}
		$this->adapter->exec( 'SET CONSTRAINTS ALL IMMEDIATE' );
	}
}
class RedBean_QueryWriter_CUBRID extends RedBean_QueryWriter_AQueryWriter implements RedBean_QueryWriter
{
	/**
	 * Data types
	 */
	const C_DATATYPE_INTEGER          = 0;
	const C_DATATYPE_DOUBLE           = 1;
	const C_DATATYPE_STRING           = 2;
	const C_DATATYPE_SPECIAL_DATE     = 80;
	const C_DATATYPE_SPECIAL_DATETIME = 81;
	const C_DATATYPE_SPECIFIED        = 99;
	/**
	 * @var RedBean_Adapter_DBAdapter
	 */
	protected $adapter;
	/**
	 * @var string
	 */
	protected $quoteCharacter = '`';
	/**
	 * Obtains the keys of a table using the PDO schema function.
	 *
	 * @param string $table
	 *
	 * @return array
	 */
	protected function getKeys( $table, $table2 = NULL )
	{
		$pdo  = $this->adapter->getDatabase()->getPDO();
		$keys = $pdo->cubrid_schema( PDO::CUBRID_SCH_EXPORTED_KEYS, $table );
		if ( $table2 ) {
			$keys = array_merge( $keys, $pdo->cubrid_schema( PDO::CUBRID_SCH_IMPORTED_KEYS, $table2 ) );
		}
		return $keys;
	}
	/**
	 * Add the constraints for a specific database driver: CUBRID
	 *
	 * @param string $table     table
	 * @param string $table1    table1
	 * @param string $table2    table2
	 * @param string $property1 property1
	 * @param string $property2 property2
	 *
	 * @return boolean
	 */
	protected function constrain( $table, $table1, $table2, $property1, $property2 )
	{
		$this->buildFK( $table, $table1, $property1, 'id', TRUE );
		$this->buildFK( $table, $table2, $property2, 'id', TRUE );
	}
	/**
	 * This method adds a foreign key from type and field to
	 * target type and target field.
	 * The foreign key is created without an action. On delete/update
	 * no action will be triggered. The FK is only used to allow database
	 * tools to generate pretty diagrams and to make it easy to add actions
	 * later on.
	 * This methods accepts a type and infers the corresponding table name.
	 *
	 *
	 * @param  string $type           type that will have a foreign key field
	 * @param  string $targetType     points to this type
	 * @param  string $field          field that contains the foreign key value
	 * @param  string $targetField    field where the fk points to
	 *
	 * @return void
	 */
	protected function buildFK( $type, $targetType, $field, $targetField, $isDep = FALSE )
	{
		$table           = $this->esc( $type );
		$tableNoQ        = $this->esc( $type, TRUE );
		$targetTable     = $this->esc( $targetType );
		$targetTableNoQ  = $this->esc( $targetType, TRUE );
		$column          = $this->esc( $field );
		$columnNoQ       = $this->esc( $field, TRUE );
		$targetColumn    = $this->esc( $targetField );
		$keys            = $this->getKeys( $targetTableNoQ, $tableNoQ );
		$needsToDropFK   = FALSE;
		foreach ( $keys as $key ) {
			if ( $key['FKTABLE_NAME'] == $tableNoQ && $key['FKCOLUMN_NAME'] == $columnNoQ ) {
				// Already has an FK
				$needsToDropFK = TRUE;
				if ( ( $isDep && $key['DELETE_RULE'] == 0 ) || ( !$isDep && $key['DELETE_RULE'] == 3 ) ) {
					return;
				}
				break;
			}
		}
		if ( $needsToDropFK ) {
			$sql = "ALTER TABLE $table DROP FOREIGN KEY {$key['FK_NAME']} ";
			$this->adapter->exec( $sql );
		}
		$casc = ( $isDep ? 'CASCADE' : 'SET NULL' );
		$sql  = "ALTER TABLE $table ADD CONSTRAINT FOREIGN KEY($column) REFERENCES $targetTable($targetColumn) ON DELETE $casc ";
		$this->adapter->exec( $sql );
	}
	/**
	 * Constructor
	 *
	 * @param RedBean_Adapter $adapter Database Adapter
	 */
	public function __construct( RedBean_Adapter $adapter )
	{
		$this->typeno_sqltype = array(
			RedBean_QueryWriter_CUBRID::C_DATATYPE_INTEGER          => ' INTEGER ',
			RedBean_QueryWriter_CUBRID::C_DATATYPE_DOUBLE           => ' DOUBLE ',
			RedBean_QueryWriter_CUBRID::C_DATATYPE_STRING           => ' STRING ',
			RedBean_QueryWriter_CUBRID::C_DATATYPE_SPECIAL_DATE     => ' DATE ',
			RedBean_QueryWriter_CUBRID::C_DATATYPE_SPECIAL_DATETIME => ' DATETIME ',
		);
		$this->sqltype_typeno = array();
		foreach ( $this->typeno_sqltype as $k => $v ) {
			$this->sqltype_typeno[trim( ( $v ) )] = $k;
		}
		$this->sqltype_typeno['STRING(1073741823)'] = self::C_DATATYPE_STRING;
		$this->adapter = $adapter;
	}
	/**
	 * This method returns the datatype to be used for primary key IDS and
	 * foreign keys. Returns one if the data type constants.
	 *
	 * @return integer $const data type to be used for IDS.
	 */
	public function getTypeForID()
	{
		return self::C_DATATYPE_INTEGER;
	}
	/**
	 * @see RedBean_QueryWriter::getTables
	 */
	public function getTables()
	{
		$rows = $this->adapter->getCol( "SELECT class_name FROM db_class WHERE is_system_class = 'NO';" );
		return $rows;
	}
	/**
	 * @see RedBean_QueryWriter::createTable
	 */
	public function createTable( $table )
	{
		$sql  = 'CREATE TABLE '
			. $this->esc( $table )
			. ' ("id" integer AUTO_INCREMENT, CONSTRAINT "pk_'
			. $this->esc( $table, TRUE )
			. '_id" PRIMARY KEY("id"))';
		$this->adapter->exec( $sql );
	}
	/**
	 * @see RedBean_QueryWriter::getColumns
	 */
	public function getColumns( $table )
	{
		$table = $this->esc( $table );
		$columnsRaw = $this->adapter->get( "SHOW COLUMNS FROM $table" );
		$columns = array();
		foreach ( $columnsRaw as $r ) {
			$columns[$r['Field']] = $r['Type'];
		}
		return $columns;
	}
	/**
	 * @see RedBean_QueryWriter::scanType
	 */
	public function scanType( $value, $flagSpecial = FALSE )
	{
		$this->svalue = $value;
		if ( is_null( $value ) ) {
			return self::C_DATATYPE_INTEGER;
		}
		if ( $flagSpecial ) {
			if ( preg_match( '/^\d{4}\-\d\d-\d\d$/', $value ) ) {
				return self::C_DATATYPE_SPECIAL_DATE;
			}
			if ( preg_match( '/^\d{4}\-\d\d-\d\d\s\d\d:\d\d:\d\d$/', $value ) ) {
				return self::C_DATATYPE_SPECIAL_DATETIME;
			}
		}
		$value = strval( $value );
		if ( !$this->startsWithZeros( $value ) ) {
			if ( is_numeric( $value ) && ( floor( $value ) == $value ) && $value >= -2147483647 && $value <= 2147483647 ) {
				return self::C_DATATYPE_INTEGER;
			}
			if ( is_numeric( $value ) ) {
				return self::C_DATATYPE_DOUBLE;
			}
		}
		return self::C_DATATYPE_STRING;
	}
	/**
	 * @see RedBean_QueryWriter::code
	 */
	public function code( $typedescription, $includeSpecials = FALSE )
	{
		$r = ( ( isset( $this->sqltype_typeno[$typedescription] ) ) ? $this->sqltype_typeno[$typedescription] : self::C_DATATYPE_SPECIFIED );
		if ( $includeSpecials ) {
			return $r;
		}
		if ( $r >= RedBean_QueryWriter::C_DATATYPE_RANGE_SPECIAL ) {
			return self::C_DATATYPE_SPECIFIED;
		}
		return $r;
	}
	/**
	 * @see RedBean_QueryWriter::addColumn
	 */
	public function addColumn( $type, $column, $field )
	{
		$table  = $type;
		$type   = $field;
		$table  = $this->esc( $table );
		$column = $this->esc( $column );
		$type   = array_key_exists( $type, $this->typeno_sqltype ) ? $this->typeno_sqltype[$type] : '';
		$this->adapter->exec( "ALTER TABLE $table ADD COLUMN $column $type " );
	}
	/**
	 * @see RedBean_QueryWriter::addUniqueIndex
	 */
	public function addUniqueIndex( $table, $columns )
	{
		$table = $this->esc( $table );
		sort( $columns ); // else we get multiple indexes due to order-effects
		foreach ( $columns as $k => $v ) {
			$columns[$k] = $this->esc( $v );
		}
		$r = $this->adapter->get( "SHOW INDEX FROM $table" );
		$name = 'UQ_' . sha1( implode( ',', $columns ) );
		if ( $r ) {
			foreach ( $r as $i ) {
				if ( strtoupper( $i['Key_name'] ) == strtoupper( $name ) ) {
					return;
				}
			}
		}
		$sql = "ALTER TABLE $table ADD CONSTRAINT UNIQUE $name (" . implode( ',', $columns ) . ")";
		$this->adapter->exec( $sql );
	}
	/**
	 * @see RedBean_QueryWriter::sqlStateIn
	 */
	public function sqlStateIn( $state, $list )
	{
		return ( $state == 'HY000' ) ? ( count( array_diff( array(
				RedBean_QueryWriter::C_SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION,
				RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_COLUMN,
				RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE
			), $list ) ) !== 3 ) : FALSE;
	}
	/**
	 * @see RedBean_QueryWriter::addIndex
	 */
	public function addIndex( $type, $name, $column )
	{
		$table  = $type;
		$table  = $this->esc( $table );
		$name   = preg_replace( '/\W/', '', $name );
		$column = $this->esc( $column );
		$index  = $this->adapter->getRow( "SELECT 1 as `exists` FROM db_index WHERE index_name = ? ", array( $name ) );
		if ( $index && $index['exists'] ) {
			return; // positive number will return, 0 will continue.
		}
		try {
			$this->adapter->exec( "CREATE INDEX $name ON $table ($column) " );
		} catch ( Exception $e ) {
		}
	}
	/**
	 * @see RedBean_QueryWriter::addFK
	 */
	public function addFK( $type, $targetType, $field, $targetField, $isDependent = FALSE )
	{
		$this->buildFK( $type, $targetType, $field, $targetField, $isDependent );
	}
	/**
	 * @see RedBean_QueryWriter::wipeAll
	 */
	public function wipeAll()
	{
		foreach ( $this->getTables() as $t ) {
			foreach ( $this->getKeys( $t ) as $k ) {
				$this->adapter->exec( "ALTER TABLE \"{$k['FKTABLE_NAME']}\" DROP FOREIGN KEY \"{$k['FK_NAME']}\"" );
			}
			$this->adapter->exec( "DROP TABLE \"$t\"" );
		}
	}
	/**
	 * @see RedBean_QueryWriter::esc
	 */
	public function esc( $dbStructure, $noQuotes = FALSE )
	{
		return parent::esc( strtolower( $dbStructure ), $noQuotes );
	}
}
class RedBean_Exception extends LogicException
{
}
class RedBean_Exception_SQL extends RuntimeException
{
	/**
	 * @var string
	 */
	private $sqlState;
	/**
	 * Returns an ANSI-92 compliant SQL state.
	 *
	 * @return string $state ANSI state code
	 */
	public function getSQLState()
	{
		return $this->sqlState;
	}
	/**
	 * @todo parse state to verify valid ANSI92!
	 *       Stores ANSI-92 compliant SQL state.
	 *
	 * @param string $sqlState code
	 *
	 * @return void
	 */
	public function setSQLState( $sqlState )
	{
		$this->sqlState = $sqlState;
	}
	/**
	 * To String prints both code and SQL state.
	 *
	 * @return string $message prints this exception instance as a string
	 */
	public function __toString()
	{
		return '[' . $this->getSQLState() . '] - ' . $this->getMessage();
	}
}
class RedBean_Exception_Security extends RedBean_Exception
{
}
class RedBean_OODB extends RedBean_Observable
{
	/**
	 * @var array
	 */
	protected $chillList = array();
	/**
	 * @var array
	 */
	protected $dep = array();
	/**
	 * @var array
	 */
	protected $stash = NULL;
	/*
	 * @var integer
	 */
	protected $nesting = 0;
	/**
	 * @var RedBean_Adapter_DBAdapter
	 */
	protected $writer;
	/**
	 * @var boolean
	 */
	protected $isFrozen = FALSE;
	/**
	 * @var RedBean_BeanHelper_Facade
	 */
	protected $beanhelper = NULL;
	/**
	 * @var RedBean_AssociationManager
	 */
	protected $assocManager = NULL;
	/**
	 * Handles Exceptions. Suppresses exceptions caused by missing structures.
	 *
	 * @param Exception $exception exception
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	private function handleException( Exception $exception )
	{
		if ( !$this->writer->sqlStateIn( $exception->getSQLState(),
			array(
				RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE,
				RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_COLUMN ) )
		) {
			throw $exception;
		}
	}
	/**
	 * Unboxes a bean from a FUSE model if needed and checks whether the bean is
	 * an instance of RedBean_OODBBean.
	 *
	 * @param RedBean_OODBBean $bean bean you wish to unbox
	 *
	 * @return RedBean_OODBBean
	 *
	 * @throws RedBean_Exception_Security
	 */
	private function unboxIfNeeded( $bean )
	{
		if ( $bean instanceof RedBean_SimpleModel ) {
			$bean = $bean->unbox();
		}
		if ( !( $bean instanceof RedBean_OODBBean ) ) {
			throw new RedBean_Exception_Security( 'OODB Store requires a bean, got: ' . gettype( $bean ) );
		}
		return $bean;
	}
	/**
	 * Process groups. Internal function. Processes different kind of groups for
	 * storage function. Given a list of original beans and a list of current beans,
	 * this function calculates which beans remain in the list (residue), which
	 * have been deleted (are in the trashcan) and which beans have been added
	 * (additions).
	 *
	 * @param  array $originals originals
	 * @param  array $current   the current beans
	 * @param  array $additions beans that have been added
	 * @param  array $trashcan  beans that have been deleted
	 * @param  array $residue   beans that have been left untouched
	 *
	 * @return array
	 */
	private function processGroups( $originals, $current, $additions, $trashcan, $residue )
	{
		return array(
			array_merge( $additions, array_diff( $current, $originals ) ),
			array_merge( $trashcan, array_diff( $originals, $current ) ),
			array_merge( $residue, array_intersect( $current, $originals ) )
		);
	}
	/**
	 * Figures out the desired type given the cast string ID.
	 *
	 * @param string $cast cast identifier
	 *
	 * @return integer
	 *
	 * @throws RedBean_Exception_Security
	 */
	private function getTypeFromCast( $cast )
	{
		if ( $cast == 'string' ) {
			$typeno = $this->writer->scanType( 'STRING' );
		} elseif ( $cast == 'id' ) {
			$typeno = $this->writer->getTypeForID();
		} elseif ( isset( $this->writer->sqltype_typeno[$cast] ) ) {
			$typeno = $this->writer->sqltype_typeno[$cast];
		} else {
			throw new RedBean_Exception_Security( 'Invalid Cast' );
		}
		return $typeno;
	}
	/**
	 * Processes an embedded bean.
	 *
	 * @param RedBean_OODBBean|RedBean_SimpleModel $embeddedBean the bean or model
	 *
	 * @return integer
	 */
	private function prepareEmbeddedBean( $embeddedBean )
	{
		if ( !$embeddedBean->id || $embeddedBean->getMeta( 'tainted' ) ) {
			$this->store( $embeddedBean );
		}
		return $embeddedBean->id;
	}
	/**
	 * Orders the Query Writer to create a table if it does not exist already and
	 * adds a note in the build report about the creation.
	 *
	 * @param RedBean_OODBBean $bean  bean to update report of
	 * @param string           $table table to check and create if not exists
	 *
	 * @return void
	 */
	private function createTableIfNotExists( RedBean_OODBBean $bean, $table )
	{
		//Does table exist? If not, create
		if ( !$this->isFrozen && !$this->tableExists( $this->writer->esc( $table, TRUE ) ) ) {
			$this->writer->createTable( $table );
			$bean->setMeta( 'buildreport.flags.created', TRUE );
		}
	}
	/**
	 * Adds the unique constraints described in the meta data.
	 *
	 * @param RedBean_OODBBean $bean bean
	 *
	 * @return void
	 */
	private function addUniqueConstraints( RedBean_OODBBean $bean )
	{
		if ( $uniques = $bean->getMeta( 'buildcommand.unique' ) ) {
			$table = $bean->getMeta( 'type' );
			foreach ( $uniques as $unique ) {
				$this->writer->addUniqueIndex( $table, $unique );
			}
		}
	}
	/**
	 * Stores a cleaned bean; i.e. only scalar values. This is the core of the store()
	 * method. When all lists and embedded beans (parent objects) have been processed and
	 * removed from the original bean the bean is passed to this method to be stored
	 * in the database.
	 *
	 * @param RedBean_OODBBean $bean the clean bean
	 *
	 * @return void
	 */
	private function storeBean( RedBean_OODBBean $bean )
	{
		if ( !$this->isFrozen ) {
			$this->check( $bean );
		}
		$table = $bean->getMeta( 'type' );
		if ( $bean->getMeta( 'tainted' ) ) {
			$this->createTableIfNotExists( $bean, $table );
			$updateValues = $this->getUpdateValues( $bean );
			if ( !$this->isFrozen ) {
				$this->addUniqueConstraints( $bean );
			}
			$bean->id = $this->writer->updateRecord( $table, $updateValues, $bean->id );
			$bean->setMeta( 'tainted', FALSE );
		}
	}
	/**
	 * Returns a structured array of update values using the following format:
	 * array(
	 *        property => $property,
	 *        value => $value
	 * );
	 *
	 * @param RedBean_OODBBean $bean bean to extract update values from
	 *
	 * @return array
	 */
	private function getUpdateValues( RedBean_OODBBean $bean )
	{
		$updateValues = array();
		foreach ( $bean as $property => $value ) {
			if ( !$this->isFrozen && $property !== 'id' ) {
				$this->moldTable( $bean, $property, $value );
			}
			if ( $property !== 'id' ) {
				$updateValues[] = array( 'property' => $property, 'value' => $value );
			}
		}
		return $updateValues;
	}
	/**
	 * Molds the table to fit the bean data.
	 * Given a property and a value and the bean, this method will
	 * adjust the table structure to fit the requirements of the property and value.
	 * This may include adding a new column or widening an existing column to hold a larger
	 * or different kind of value. This method employs the writer to adjust the table
	 * structure in the database. Schema updates are recorded in meta properties of the bean.
	 *
	 * @param RedBean_OODBBean $bean     bean to get cast data from and store meta in
	 * @param string           $property property to store
	 * @param mixed            $value    value to store
	 *
	 * @return void
	 */
	private function moldTable( RedBean_OODBBean $bean, $property, $value )
	{
		$table   = $bean->getMeta( 'type' );
		$columns = $this->writer->getColumns( $table );
		if ( !in_array( $bean->getMeta( 'type' ), $this->chillList ) ) {
			if ( $bean->getMeta( "cast.$property", -1 ) !== -1 ) { //check for explicitly specified types
				$cast   = $bean->getMeta( "cast.$property" );
				$typeno = $this->getTypeFromCast( $cast );
			} else {
				$cast   = FALSE;
				$typeno = $this->writer->scanType( $value, TRUE );
			}
			if ( isset( $columns[$this->writer->esc( $property, TRUE )] ) ) { //Is this property represented in the table ?
				if ( !$cast ) { //rescan without taking into account special types >80
					$typeno = $this->writer->scanType( $value, FALSE );
				}
				$sqlt = $this->writer->code( $columns[$this->writer->esc( $property, TRUE )] );
				if ( $typeno > $sqlt ) { //no, we have to widen the database column type
					$this->writer->widenColumn( $table, $property, $typeno );
					$bean->setMeta( 'buildreport.flags.widen', TRUE );
				}
			} else {
				$this->writer->addColumn( $table, $property, $typeno );
				$bean->setMeta( 'buildreport.flags.addcolumn', TRUE );
				$this->processBuildCommands( $table, $property, $bean );
			}
		}
	}
	/**
	 * Processes a list of beans from a bean. A bean may contain lists. This
	 * method handles shared addition lists; i.e. the $bean->sharedObject properties.
	 *
	 * @param RedBean_OODBBean $bean             the bean
	 * @param array            $sharedAdditions  list with shared additions
	 *
	 * @return void
	 *
	 * @throws RedBean_Exception_Security
	 */
	private function processSharedAdditions( $bean, $sharedAdditions )
	{
		foreach ( $sharedAdditions as $addition ) {
			if ( $addition instanceof RedBean_OODBBean ) {
				$this->assocManager->associate( $addition, $bean );
			} else {
				throw new RedBean_Exception_Security( 'Array may only contain RedBean_OODBBeans' );
			}
		}
	}
	/**
	 * Processes a list of beans from a bean. A bean may contain lists. This
	 * method handles own lists; i.e. the $bean->ownObject properties.
	 * A residue is a bean in an own-list that stays where it is. This method
	 * checks if there have been any modification to this bean, in that case
	 * the bean is stored once again, otherwise the bean will be left untouched.
	 *
	 * @param RedBean_OODBBean $bean       the bean
	 * @param array            $ownresidue list
	 *
	 * @return void
	 */
	private function processResidue( $ownresidue )
	{
		foreach ( $ownresidue as $residue ) {
			if ( $residue->getMeta( 'tainted' ) ) {
				$this->store( $residue );
			}
		}
	}
	/**
	 * Processes a list of beans from a bean. A bean may contain lists. This
	 * method handles own lists; i.e. the $bean->ownObject properties.
	 * A trash can bean is a bean in an own-list that has been removed
	 * (when checked with the shadow). This method
	 * checks if the bean is also in the dependency list. If it is the bean will be removed.
	 * If not, the connection between the bean and the owner bean will be broken by
	 * setting the ID to NULL.
	 *
	 * @param RedBean_OODBBean $bean        the bean
	 * @param array            $ownTrashcan list
	 *
	 * @return void
	 */
	private function processTrashcan( $bean, $ownTrashcan )
	{
		$myFieldLink = $bean->getMeta( 'type' ) . '_id';
		if ( is_array( $ownTrashcan ) && count( $ownTrashcan ) > 0 ) {
			$first = reset( $ownTrashcan );
			if ( $first instanceof RedBean_OODBBean ) {
				$alias = $bean->getMeta( 'sys.alias.' . $first->getMeta( 'type' ) );
				if ( $alias ) {
					$myFieldLink = $alias . '_id';
				}
			}
		}
		foreach ( $ownTrashcan as $trash ) {
			if ( isset( $this->dep[$trash->getMeta( 'type' )] ) && in_array( $bean->getMeta( 'type' ), $this->dep[$trash->getMeta( 'type' )] ) ) {
				$this->trash( $trash );
			} else {
				$trash->$myFieldLink = NULL;
				$this->store( $trash );
			}
		}
	}
	/**
	 * Unassociates the list items in the trashcan.
	 *
	 * @param RedBean_OODBBean $bean           bean
	 * @param array            $sharedTrashcan list
	 *
	 * @return void
	 */
	private function processSharedTrashcan( $bean, $sharedTrashcan )
	{
		foreach ( $sharedTrashcan as $trash ) {
			$this->assocManager->unassociate( $trash, $bean );
		}
	}
	/**
	 * Stores all the beans in the residue group.
	 *
	 * @param RedBean_OODBBean $bean          bean
	 * @param array            $sharedresidue list
	 *
	 * @return void
	 */
	private function processSharedResidue( $bean, $sharedresidue )
	{
		foreach ( $sharedresidue as $residue ) {
			$this->store( $residue );
		}
	}
	/**
	 * Processes embedded beans.
	 * Each embedded bean will be indexed and foreign keys will
	 * be created if the bean is in the dependency list.
	 *
	 * @param RedBean_OODBBean $bean          bean
	 * @param array            $embeddedBeans embedded beans
	 *
	 * @return void
	 */
	private function addForeignKeysForParentBeans( $bean, $embeddedBeans )
	{
		foreach ( $embeddedBeans as $linkField => $embeddedBean ) {
			$this->writer->addIndex( $bean->getMeta( 'type' ),
				'index_foreignkey_' . $bean->getMeta( 'type' ) . '_' . $embeddedBean->getMeta( 'type' ),
				$linkField );
			$isDep = $this->isDependentOn( $bean->getMeta( 'type' ), $embeddedBean->getMeta( 'type' ) );
			$this->writer->addFK( $bean->getMeta( 'type' ), $embeddedBean->getMeta( 'type' ), $linkField, 'id', $isDep );
		}
	}
	/**
	 * Part of the store() functionality.
	 * Handles all new additions after the bean has been saved.
	 * Stores addition bean in own-list, extracts the id and
	 * adds a foreign key. Also adds a constraint in case the type is
	 * in the dependent list.
	 *
	 * @param RedBean_OODBBean $bean         bean
	 * @param array            $ownAdditions list of addition beans in own-list
	 *
	 * @return void
	 *
	 * @throws RedBean_Exception_Security
	 */
	private function processAdditions( $bean, $ownAdditions )
	{
		$myFieldLink = $bean->getMeta( 'type' ) . '_id';
		if ( $bean && count( $ownAdditions ) > 0 ) {
			$first = reset( $ownAdditions );
			if ( $first instanceof RedBean_OODBBean ) {
				$alias = $bean->getMeta( 'sys.alias.' . $first->getMeta( 'type' ) );
				if ( $alias ) {
					$myFieldLink = $alias . '_id';
				}
			}
		}
		foreach ( $ownAdditions as $addition ) {
			if ( $addition instanceof RedBean_OODBBean ) {
				$addition->$myFieldLink = $bean->id;
				$addition->setMeta( 'cast.' . $myFieldLink, 'id' );
				$this->store( $addition );
				if ( !$this->isFrozen ) {
					$this->writer->addIndex( $addition->getMeta( 'type' ),
						'index_foreignkey_' . $addition->getMeta( 'type' ) . '_' . $bean->getMeta( 'type' ),
						$myFieldLink );
					$isDep = $this->isDependentOn( $addition->getMeta( 'type' ), $bean->getMeta( 'type' ) );
					$this->writer->addFK( $addition->getMeta( 'type' ), $bean->getMeta( 'type' ), $myFieldLink, 'id', $isDep );
				}
			} else {
				throw new RedBean_Exception_Security( 'Array may only contain RedBean_OODBBeans' );
			}
		}
	}
	/**
	 * Determines whether the bean has 'loaded lists' or
	 * 'loaded embedded beans' that need to be processed
	 * by the store() method.
	 *
	 * @param RedBean_OODBBean $bean bean to be examined
	 *
	 * @return boolean
	 */
	private function hasListsOrObjects( RedBean_OODBBean $bean )
	{
		$processLists = FALSE;
		foreach ( $bean as $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$processLists = TRUE;
				break;
			}
		}
		return $processLists;
	}
	/**
	 * Checks whether reference type has been marked as dependent on target type.
	 * This is the result of setting reference type as a key in R::dependencies() and
	 * putting target type in its array.
	 *
	 * @param string $refType   reference type
	 * @param string $otherType other type / target type
	 *
	 * @return boolean
	 */
	private function isDependentOn( $refType, $otherType )
	{
		return (boolean) ( isset( $this->dep[$refType] ) && in_array( $otherType, $this->dep[$refType] ) );
	}
	/**
	 * Processes all column based build commands.
	 * A build command is an additional instruction for the Query Writer. It is processed only when
	 * a column gets created. The build command is often used to instruct the writer to write some
	 * extra SQL to create indexes or constraints. Build commands are stored in meta data of the bean.
	 * They are only for internal use, try to refrain from using them in your code directly.
	 *
	 * @param  string           $table    name of the table to process build commands for
	 * @param  string           $property name of the property to process build commands for
	 * @param  RedBean_OODBBean $bean     bean that contains the build commands
	 *
	 * @return void
	 */
	private function processBuildCommands( $table, $property, RedBean_OODBBean $bean )
	{
		if ( $inx = ( $bean->getMeta( 'buildcommand.indexes' ) ) ) {
			if ( isset( $inx[$property] ) ) {
				$this->writer->addIndex( $table, $inx[$property], $property );
			}
		}
	}
	/**
	 * Converts an embedded bean to an ID, removed the bean property and
	 * stores the bean in the embedded beans array.
	 *
	 * @param array            $embeddedBeans destination array for embedded bean
	 * @param RedBean_OODBBean $bean          target bean
	 * @param string           $property      property that contains the embedded bean
	 * @param RedBean_OODBBean $value         embedded bean itself
	 */
	private function processEmbeddedBean( &$embeddedBeans, $bean, $property, RedBean_OODBBean $value )
	{
		$linkField        = $property . '_id';
		$bean->$linkField = $this->prepareEmbeddedBean( $value );
		$bean->setMeta( 'cast.' . $linkField, 'id' );
		$embeddedBeans[$linkField] = $value;
		$bean->removeProperty( $property );
	}
	/**
	 * Stores a bean and its lists in one run.
	 *
	 * @param RedBean_OODBBean $bean
	 *
	 * @return void
	 */
	private function processLists( RedBean_OODBBean $bean )
	{
		$sharedAdditions = $sharedTrashcan = $sharedresidue = $sharedItems = $ownAdditions = $ownTrashcan = $ownresidue = $embeddedBeans = array(); //Define groups
		foreach ( $bean as $property => $value ) {
			$value = ( $value instanceof RedBean_SimpleModel ) ? $value->unbox() : $value;
			if ( $value instanceof RedBean_OODBBean ) {
				$this->processEmbeddedBean( $embeddedBeans, $bean, $property, $value );
			} elseif ( is_array( $value ) ) {
				$originals = $bean->getMeta( 'sys.shadow.' . $property, array() );
				if ( strpos( $property, 'own' ) === 0 ) {
					list( $ownAdditions, $ownTrashcan, $ownresidue ) = $this->processGroups( $originals, $value, $ownAdditions, $ownTrashcan, $ownresidue );
					$bean->removeProperty( $property );
				} elseif ( strpos( $property, 'shared' ) === 0 ) {
					list( $sharedAdditions, $sharedTrashcan, $sharedresidue ) = $this->processGroups( $originals, $value, $sharedAdditions, $sharedTrashcan, $sharedresidue );
					$bean->removeProperty( $property );
				}
			}
		}
		$this->storeBean( $bean );
		if ( !$this->isFrozen ) {
			$this->addForeignKeysForParentBeans( $bean, $embeddedBeans );
		}
		$this->processTrashcan( $bean, $ownTrashcan );
		$this->processAdditions( $bean, $ownAdditions );
		$this->processResidue( $ownresidue );
		$this->processSharedTrashcan( $bean, $sharedTrashcan );
		$this->processSharedAdditions( $bean, $sharedAdditions );
		$this->processSharedResidue( $bean, $sharedresidue );
	}
	/**
	 * Constructor, requires a query writer.
	 *
	 * @param RedBean_QueryWriter $writer writer
	 */
	public function __construct( RedBean_QueryWriter $writer )
	{
		if ( $writer instanceof RedBean_QueryWriter ) {
			$this->writer = $writer;
		}
		$this->beanhelper = new RedBean_BeanHelper_Facade();
	}
	/**
	 * Toggles fluid or frozen mode. In fluid mode the database
	 * structure is adjusted to accomodate your objects. In frozen mode
	 * this is not the case.
	 *
	 * You can also pass an array containing a selection of frozen types.
	 * Let's call this chilly mode, it's just like fluid mode except that
	 * certain types (i.e. tables) aren't touched.
	 *
	 * @param boolean|array $toggle TRUE if you want to use OODB instance in frozen mode
	 *
	 * @return void
	 */
	public function freeze( $toggle )
	{
		if ( is_array( $toggle ) ) {
			$this->chillList = $toggle;
			$this->isFrozen  = FALSE;
		} else {
			$this->isFrozen = (boolean) $toggle;
		}
	}
	/**
	 * Returns the current mode of operation of RedBean.
	 * In fluid mode the database
	 * structure is adjusted to accomodate your objects.
	 * In frozen mode
	 * this is not the case.
	 *
	 * @return boolean
	 */
	public function isFrozen()
	{
		return (bool) $this->isFrozen;
	}
	/**
	 * Dispenses a new bean (a RedBean_OODBBean Bean Object)
	 * of the specified type. Always
	 * use this function to get an empty bean object. Never
	 * instantiate a RedBean_OODBBean yourself because it needs
	 * to be configured before you can use it with RedBean. This
	 * function applies the appropriate initialization /
	 * configuration for you.
	 *
	 * @param string $type   type of bean you want to dispense
	 * @param string $number number of beans you would like to get
	 *
	 * @return RedBean_OODBBean
	 */
	public function dispense( $type, $number = 1 )
	{
		$beans = array();
		for ( $i = 0; $i < $number; $i++ ) {
			$bean = new RedBean_OODBBean;
			$bean->initializeForDispense( $type, $this->beanhelper );
			if ( !$this->isFrozen ) {
				$this->check( $bean );
			}
			$this->signal( 'dispense', $bean );
			$beans[] = $bean;
		}
		return ( count( $beans ) === 1 ) ? array_pop( $beans ) : $beans;
	}
	/**
	 * Sets bean helper to be given to beans.
	 * Bean helpers assist beans in getting a reference to a toolbox.
	 *
	 * @param RedBean_BeanHelper $beanhelper helper
	 *
	 * @return void
	 */
	public function setBeanHelper( RedBean_BeanHelper $beanhelper )
	{
		$this->beanhelper = $beanhelper;
	}
	/**
	 * Checks whether a RedBean_OODBBean bean is valid.
	 * If the type is not valid or the ID is not valid it will
	 * throw an exception: RedBean_Exception_Security.
	 *
	 * @param RedBean_OODBBean $bean the bean that needs to be checked
	 *
	 * @return void
	 *
	 * @throws RedBean_Exception_Security $exception
	 */
	public function check( RedBean_OODBBean $bean )
	{
		//Is all meta information present?
		if ( !isset( $bean->id ) ) {
			throw new RedBean_Exception_Security( 'Bean has incomplete Meta Information id ' );
		}
		if ( !( $bean->getMeta( 'type' ) ) ) {
			throw new RedBean_Exception_Security( 'Bean has incomplete Meta Information II' );
		}
		//Pattern of allowed characters
		$pattern = '/[^a-z0-9_]/i';
		//Does the type contain invalid characters?
		if ( preg_match( $pattern, $bean->getMeta( 'type' ) ) ) {
			throw new RedBean_Exception_Security( 'Bean Type is invalid' );
		}
		//Are the properties and values valid?
		foreach ( $bean as $prop => $value ) {
			if (
				is_array( $value )
				|| ( is_object( $value ) )
			) {
				throw new RedBean_Exception_Security( "Invalid Bean value: property $prop" );
			} else if (
				strlen( $prop ) < 1
				|| preg_match( $pattern, $prop )
			) {
				throw new RedBean_Exception_Security( "Invalid Bean property: property $prop" );
			}
		}
	}
	/**
	 * Searches the database for a bean that matches conditions $conditions and sql $addSQL
	 * and returns an array containing all the beans that have been found.
	 *
	 * Conditions need to take form:
	 *
	 * array(
	 *    'PROPERTY' => array( POSSIBLE VALUES... 'John', 'Steve' )
	 *    'PROPERTY' => array( POSSIBLE VALUES... )
	 * );
	 *
	 * All conditions are glued together using the AND-operator, while all value lists
	 * are glued using IN-operators thus acting as OR-conditions.
	 *
	 * Note that you can use property names; the columns will be extracted using the
	 * appropriate bean formatter.
	 *
	 * @param string $type       type of beans you are looking for
	 * @param array  $conditions list of conditions
	 * @param string $addSQL     SQL to be used in query
	 * @param array  $bindings   whether you prefer to use a WHERE clause or not (TRUE = not)
	 *
	 * @return array
	 *
	 * @throws RedBean_Exception_SQL
	 */
	public function find( $type, $conditions = array(), $sql = NULL, $bindings = array() )
	{
		//for backward compatibility, allow mismatch arguments:
		if ( is_array( $sql ) ) {
			if ( isset( $sql[1] ) ) {
				$bindings = $sql[1];
			}
			$sql = $sql[0];
		}
		try {
			$beans = $this->convertToBeans( $type, $this->writer->queryRecord( $type, $conditions, $sql, $bindings ) );
			return $beans;
		} catch ( RedBean_Exception_SQL $exception ) {
			$this->handleException( $exception );
		}
		return array();
	}
	/**
	 * Checks whether the specified table already exists in the database.
	 * Not part of the Object Database interface!
	 *
	 * @deprecated Use RedBean_QueryWriter_AQueryWriter::typeExists() instead.
	 *
	 * @param string $table table name
	 *
	 * @return boolean
	 */
	public function tableExists( $table )
	{
		return $this->writer->tableExists( $table );
	}
	/**
	 * Stores a bean in the database. This method takes a
	 * RedBean_OODBBean Bean Object $bean and stores it
	 * in the database. If the database schema is not compatible
	 * with this bean and RedBean runs in fluid mode the schema
	 * will be altered to store the bean correctly.
	 * If the database schema is not compatible with this bean and
	 * RedBean runs in frozen mode it will throw an exception.
	 * This function returns the primary key ID of the inserted
	 * bean.
	 * 
	 * The return value is an integer if possible. If it is not possible to
	 * represent the value as an integer a string will be returned. We use
	 * explicit casts instead of functions to preserve performance 
	 * (0.13 vs 0.28 for 10000 iterations on Core i3).
	 *
	 * @param RedBean_OODBBean|RedBean_SimpleModel $bean bean to store
	 *
	 * @return integer|string
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function store( $bean )
	{
		$bean         = $this->unboxIfNeeded( $bean );
		$processLists = $this->hasListsOrObjects( $bean );
		if ( !$processLists && !$bean->getMeta( 'tainted' ) ) {
			return $bean->getID(); //bail out!
		}
		$this->signal( 'update', $bean );
		$processLists = $this->hasListsOrObjects( $bean ); //check again, might have changed by model!
		if ( $processLists ) {
			$this->processLists( $bean );
		} else {
			$this->storeBean( $bean );
		}
		$this->signal( 'after_update', $bean );
		return ( (string) $bean->id === (string) (int) $bean->id ) ? (int) $bean->id : (string) $bean->id;
	}
	/**
	 * Loads a bean from the object database.
	 * It searches for a RedBean_OODBBean Bean Object in the
	 * database. It does not matter how this bean has been stored.
	 * RedBean uses the primary key ID $id and the string $type
	 * to find the bean. The $type specifies what kind of bean you
	 * are looking for; this is the same type as used with the
	 * dispense() function. If RedBean finds the bean it will return
	 * the RedBean_OODB Bean object; if it cannot find the bean
	 * RedBean will return a new bean of type $type and with
	 * primary key ID 0. In the latter case it acts basically the
	 * same as dispense().
	 *
	 * Important note:
	 * If the bean cannot be found in the database a new bean of
	 * the specified type will be generated and returned.
	 *
	 * @param string  $type type of bean you want to load
	 * @param integer $id   ID of the bean you want to load
	 *
	 * @throws RedBean_Exception_SQL
	 *
	 * @return RedBean_OODBBean
	 *
	 */
	public function load( $type, $id )
	{
		$bean = $this->dispense( $type );
		if ( isset( $this->stash[$this->nesting][$id] ) ) {
			$row = $this->stash[$this->nesting][$id];
		} else {
			try {
				$rows = $this->writer->queryRecord( $type, array( 'id' => array( $id ) ) );
			} catch ( RedBean_Exception_SQL $exception ) {
				if ( $this->writer->sqlStateIn( $exception->getSQLState(),
					array(
						RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_COLUMN,
						RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE )
				)
				) {
					$rows = 0;
					if ( $this->isFrozen ) {
						throw $exception; //only throw if frozen
					}
				}
			}
			if ( empty( $rows ) ) {
				return $bean;
			}
			$row = array_pop( $rows );
		}
		$bean->setMeta( 'sys.orig', $row )->import( $row );
		$this->nesting++;
		$this->signal( 'open', $bean );
		$this->nesting--;
		return $bean->setMeta( 'tainted', FALSE );
	}
	/**
	 * Removes a bean from the database.
	 * This function will remove the specified RedBean_OODBBean
	 * Bean Object from the database.
	 *
	 * @param RedBean_OODBBean|RedBean_SimpleModel $bean bean you want to remove from database
	 *
	 * @return void
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function trash( $bean )
	{
		if ( $bean instanceof RedBean_SimpleModel ) {
			$bean = $bean->unbox();
		}
		if ( !( $bean instanceof RedBean_OODBBean ) ) {
			throw new RedBean_Exception_Security( 'OODB Store requires a bean, got: ' . gettype( $bean ) );
		}
		$this->signal( 'delete', $bean );
		foreach ( $bean as $property => $value ) {
			if ( $value instanceof RedBean_OODBBean ) {
				$bean->removeProperty( $property );
			}
			if ( is_array( $value ) ) {
				if ( strpos( $property, 'own' ) === 0 ) {
					$bean->removeProperty( $property );
				} elseif ( strpos( $property, 'shared' ) === 0 ) {
					$bean->removeProperty( $property );
				}
			}
		}
		if ( !$this->isFrozen ) {
			$this->check( $bean );
		}
		try {
			$this->writer->deleteRecord( $bean->getMeta( 'type' ), array( 'id' => array( $bean->id ) ), NULL );
		} catch ( RedBean_Exception_SQL $exception ) {
			$this->handleException( $exception );
		}
		$bean->id = 0;
		$this->signal( 'after_delete', $bean );
	}
	/**
	 * Returns an array of beans. Pass a type and a series of ids and
	 * this method will bring you the corresponding beans.
	 *
	 * important note: Because this method loads beans using the load()
	 * function (but faster) it will return empty beans with ID 0 for
	 * every bean that could not be located. The resulting beans will have the
	 * passed IDs as their keys.
	 *
	 * @param string $type type of beans
	 * @param array  $ids  ids to load
	 *
	 * @return array
	 */
	public function batch( $type, $ids )
	{
		if ( !$ids ) {
			return array();
		}
		$collection = array();
		try {
			$rows = $this->writer->queryRecord( $type, array( 'id' => $ids ) );
		} catch ( RedBean_Exception_SQL $e ) {
			$this->handleException( $e );
			$rows = FALSE;
		}
		$this->stash[$this->nesting] = array();
		if ( !$rows ) {
			return array();
		}
		foreach ( $rows as $row ) {
			$this->stash[$this->nesting][$row['id']] = $row;
		}
		foreach ( $ids as $id ) {
			$collection[$id] = $this->load( $type, $id );
		}
		$this->stash[$this->nesting] = NULL;
		return $collection;
	}
	/**
	 * This is a convenience method; it converts database rows
	 * (arrays) into beans. Given a type and a set of rows this method
	 * will return an array of beans of the specified type loaded with
	 * the data fields provided by the result set from the database.
	 *
	 * @param string $type type of beans you would like to have
	 * @param array  $rows rows from the database result
	 *
	 * @return array
	 */
	public function convertToBeans( $type, $rows )
	{
		$collection                  = array();
		$this->stash[$this->nesting] = array();
		foreach ( $rows as $row ) {
			$id                               = $row['id'];
			$this->stash[$this->nesting][$id] = $row;
			$collection[$id]                  = $this->load( $type, $id );
		}
		$this->stash[$this->nesting] = NULL;
		return $collection;
	}
	/**
	 * Counts the number of beans of type $type.
	 * This method accepts a second argument to modify the count-query.
	 * A third argument can be used to provide bindings for the SQL snippet.
	 *
	 * @param string $type     type of bean we are looking for
	 * @param string $addSQL   additional SQL snippet
	 * @param array  $bindings parameters to bind to SQL
	 *
	 * @return integer
	 *
	 * @throws RedBean_Exception_SQL
	 */
	public function count( $type, $addSQL = '', $bindings = array() )
	{
		try {
			return (int) $this->writer->queryRecordCount( $type, array(), $addSQL, $bindings );
		} catch ( RedBean_Exception_SQL $exception ) {
			if ( !$this->writer->sqlStateIn( $exception->getSQLState(), array( RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE ) ) ) {
				throw $exception;
			}
		}
		return 0;
	}
	/**
	 * Trash all beans of a given type. Wipes an entire type of bean.
	 *
	 * @param string $type type of bean you wish to delete all instances of
	 *
	 * @return boolean
	 *
	 * @throws RedBean_Exception_SQL
	 */
	public function wipe( $type )
	{
		try {
			$this->writer->wipe( $type );
			return TRUE;
		} catch ( RedBean_Exception_SQL $exception ) {
			if ( !$this->writer->sqlStateIn( $exception->getSQLState(), array( RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE ) ) ) {
				throw $exception;
			}
			return FALSE;
		}
	}
	/**
	 * Returns an Association Manager for use with OODB.
	 * A simple getter function to obtain a reference to the association manager used for
	 * storage and more.
	 *
	 * @return RedBean_AssociationManager
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function getAssociationManager()
	{
		if ( !isset( $this->assocManager ) ) {
			throw new RedBean_Exception_Security( 'No association manager available.' );
		}
		return $this->assocManager;
	}
	/**
	 * Sets the association manager instance to be used by this OODB.
	 * A simple setter function to set the association manager to be used for storage and
	 * more.
	 *
	 * @param RedBean_AssociationManager $assoc sets the association manager to be used
	 *
	 * @return void
	 */
	public function setAssociationManager( RedBean_AssociationManager $assocManager )
	{
		$this->assocManager = $assocManager;
	}
	/**
	 * Sets a dependency list. Dependencies can be used to make
	 * certain beans depend on others. This causes dependent beans to get removed
	 * once the bean they depend on has been removed as well.
	 * A dependency takes the form:
	 *
	 * $me => depends on array( $bean1, $bean2 )
	 *
	 * For instance a to inform RedBeanPHP about the fact that a page
	 * depends on a book:
	 *
	 * 'page' => array('book')
	 *
	 * A bean can depend on multiple other beans.
	 *
	 * A dependency does two things:
	 *
	 * 1. Adds a ON CASCADE DELETE
	 * 2. trashes the depending bean if the entry in the ownList is removed
	 *
	 * @param array $dep
	 *
	 * @return void
	 */
	public function setDepList( $dependencyList )
	{
		$this->dep = $dependencyList;
	}
	/**
	 * Preloads certain properties for beans.
	 * Understands aliases.
	 *
	 * Usage: $redbean->preload($books, array('coauthor'=>'author'));
	 *
	 * Usage for nested beans:
	 *
	 * $redbean->preload($texts, array('page', 'page.book', 'page.book.author'));
	 *
	 * preloads pages, books and authors.
	 * You may also use a shortcut here:
	 *
	 * $redbean->preload($texts, array('page', '*.book', '*.author'));
	 *
	 * Can also load preload lists:
	 *
	 * $redbean->preload($books, array('ownPage'=>'page', '*.ownText'=>'text', 'sharedTag'=>'tag'));
	 *
	 * @param array $beans beans
	 * @param array $types types to load
	 *
	 * @return array
	 */
	public function preload( $beans, $typeList, $closure = NULL )
	{
		$preloader = new RedBean_Preloader( $this );
		return $preloader->load( $beans, $typeList, $closure );
	}
}
class RedBean_ToolBox
{
	/**
	 * @var RedBean_OODB
	 */
	protected $oodb;
	/**
	 * @var RedBean_QueryWriter
	 */
	protected $writer;
	/**
	 * @var RedBean_Adapter_DBAdapter
	 */
	protected $adapter;
	/**
	 * Constructor.
	 * The toolbox is an integral part of RedBeanPHP providing the basic
	 * architectural building blocks to manager objects, helpers and additional tools
	 * like plugins. A toolbox contains the three core components of RedBeanPHP:
	 * the adapter, the query writer and the core functionality of RedBeanPHP in
	 * OODB.
	 *
	 * @param RedBean_OODB              $oodb    Object Database
	 * @param RedBean_Adapter_DBAdapter $adapter Adapter
	 * @param RedBean_QueryWriter       $writer  Writer
	 *
	 * @return RedBean_ToolBox
	 */
	public function __construct( RedBean_OODB $oodb, RedBean_Adapter $adapter, RedBean_QueryWriter $writer )
	{
		$this->oodb    = $oodb;
		$this->adapter = $adapter;
		$this->writer  = $writer;
		return $this;
	}
	/**
	 * Returns the query writer in this toolbox.
	 * The Query Writer is responsible for building the queries for a
	 * specific database and executing them through the adapter.
	 *
	 * @return RedBean_QueryWriter
	 */
	public function getWriter()
	{
		return $this->writer;
	}
	/**
	 * Returns the OODB instance in this toolbox.
	 * OODB is responsible for creating, storing, retrieving and deleting 
	 * single beans. Other components rely
	 * on OODB for their basic functionality.
	 *
	 * @return RedBean_OODB
	 */
	public function getRedBean()
	{
		return $this->oodb;
	}
	/**
	 * Returns the database adapter in this toolbox.
	 * The adapter is responsible for executing the query and binding the values.
	 * The adapter also takes care of transaction handling.
	 * 
	 * @return RedBean_Adapter_DBAdapter
	 */
	public function getDatabaseAdapter()
	{
		return $this->adapter;
	}
}
class RedBean_AssociationManager extends RedBean_Observable
{
	/**
	 * @var RedBean_OODB
	 */
	protected $oodb;
	/**
	 * @var RedBean_Adapter_DBAdapter
	 */
	protected $adapter;
	/**
	 * @var RedBean_QueryWriter
	 */
	protected $writer;
	/**
	 * Handles Exceptions. Suppresses exceptions caused by missing structures.
	 *
	 * @param Exception $exception
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	private function handleException( Exception $exception )
	{
		if ( !$this->writer->sqlStateIn( $exception->getSQLState(),
			array(
				RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE,
				RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_COLUMN )
			)
		) {
			throw $exception;
		}
	}
	/**
	 * Internal method.
	 * Returns the many-to-many related rows of table $type for bean $bean using additional SQL in $sql and
	 * $bindings bindings. If $getLinks is TRUE, link rows are returned instead.
	 *
	 * @param RedBean_OODBBean $bean     reference bean
	 * @param string           $type     target type
	 * @param boolean          $getLinks TRUE returns rows from the link table
	 * @param string           $sql      additional SQL snippet
	 * @param array            $bindings bindings
	 *
	 * @return array
	 *
	 * @throws RedBean_Exception_Security
	 * @throws RedBean_Exception_SQL
	 */
	private function relatedRows( $bean, $type, $getLinks = FALSE, $sql = '', $bindings = array() )
	{
		if ( !is_array( $bean ) && !( $bean instanceof RedBean_OODBBean ) ) {
			throw new RedBean_Exception_Security(
				'Expected array or RedBean_OODBBean but got:' . gettype( $bean )
			);
		}
		$ids = array();
		if ( is_array( $bean ) ) {
			$beans = $bean;
			foreach ( $beans as $singleBean ) {
				if ( !( $singleBean instanceof RedBean_OODBBean ) ) {
					throw new RedBean_Exception_Security(
						'Expected RedBean_OODBBean in array but got:' . gettype( $singleBean )
					);
				}
				$ids[] = $singleBean->id;
			}
			$bean = reset( $beans );
		} else {
			$ids[] = $bean->id;
		}
		$sourceType = $bean->getMeta( 'type' );
		try {
			if ( !$getLinks ) {
				return $this->writer->queryRecordRelated( $sourceType, $type, $ids, $sql, $bindings );
			} else {
				return $this->writer->queryRecordLinks( $sourceType, $type, $ids, $sql, $bindings );
			}
		} catch ( RedBean_Exception_SQL $exception ) {
			$this->handleException( $exception );
			return array();
		}
	}
	/**
	 * Associates a pair of beans. This method associates two beans, no matter
	 * what types. Accepts a base bean that contains data for the linking record.
	 * This method is used by associate. This method also accepts a base bean to be used
	 * as the template for the link record in the database.
	 *
	 * @param RedBean_OODBBean $bean1 first bean
	 * @param RedBean_OODBBean $bean2 second bean
	 * @param RedBean_OODBBean $bean  base bean (association record)
	 *
	 * @throws Exception|RedBean_Exception_SQL
	 *
	 * @return mixed
	 */
	protected function associateBeans( RedBean_OODBBean $bean1, RedBean_OODBBean $bean2, RedBean_OODBBean $bean )
	{
		$property1 = $bean1->getMeta( 'type' ) . '_id';
		$property2 = $bean2->getMeta( 'type' ) . '_id';
		if ( $property1 == $property2 ) {
			$property2 = $bean2->getMeta( 'type' ) . '2_id';
		}
		//add a build command for Unique Indexes
		$bean->setMeta( 'buildcommand.unique', array( array( $property1, $property2 ) ) );
		//add a build command for Single Column Index (to improve performance in case unqiue cant be used)
		$indexName1 = 'index_for_' . $bean->getMeta( 'type' ) . '_' . $property1;
		$indexName2 = 'index_for_' . $bean->getMeta( 'type' ) . '_' . $property2;
		$bean->setMeta( 'buildcommand.indexes', array( $property1 => $indexName1, $property2 => $indexName2 ) );
		$this->oodb->store( $bean1 );
		$this->oodb->store( $bean2 );
		$bean->setMeta( "cast.$property1", "id" );
		$bean->setMeta( "cast.$property2", "id" );
		$bean->$property1 = $bean1->id;
		$bean->$property2 = $bean2->id;
		$results   = array();
		try {
			$id = $this->oodb->store( $bean );
			//On creation, add constraints....
			if ( !$this->oodb->isFrozen() &&
				$bean->getMeta( 'buildreport.flags.created' )
			) {
				$bean->setMeta( 'buildreport.flags.created', 0 );
				if ( !$this->oodb->isFrozen() ) {
					$this->writer->addConstraintForTypes( $bean1->getMeta( 'type' ), $bean2->getMeta( 'type' ) );
				}
			}
			$results[] = $id;
		} catch ( RedBean_Exception_SQL $exception ) {
			if ( !$this->writer->sqlStateIn( $exception->getSQLState(),
				array( RedBean_QueryWriter::C_SQLSTATE_INTEGRITY_CONSTRAINT_VIOLATION ) )
			) {
				throw $exception;
			}
		}
		return $results;
	}
	/**
	 * Constructor
	 *
	 * @param RedBean_ToolBox $tools toolbox
	 */
	public function __construct( RedBean_ToolBox $tools )
	{
		$this->oodb    = $tools->getRedBean();
		$this->adapter = $tools->getDatabaseAdapter();
		$this->writer  = $tools->getWriter();
		$this->toolbox = $tools;
	}
	/**
	 * Creates a table name based on a types array.
	 * Manages the get the correct name for the linking table for the
	 * types provided.
	 *
	 * @todo find a nice way to decouple this class from QueryWriter?
	 *
	 * @param array $types 2 types as strings
	 *
	 * @return string
	 */
	public function getTable( $types )
	{
		return $this->writer->getAssocTable( $types );
	}
	/**
	 * Associates two beans in a many-to-many relation.
	 * This method will associate two beans and store the connection between the
	 * two in a link table. Instead of two single beans this method also accepts
	 * two sets of beans. Returns the ID or the IDs of the linking beans.
	 * 
	 * @param RedBean_OODBBean|array $beans1 one or more beans to form the association
	 * @param RedBean_OODBBean|array $beans2 one or more beans to form the association
	 *
	 * @return array
	 */
	public function associate( $beans1, $beans2 )
	{
		if ( !is_array( $beans1 ) ) {
			$beans1 = array( $beans1 );
		}
		if ( !is_array( $beans2 ) ) {
			$beans2 = array( $beans2 );
		}
		$results = array();
		foreach ( $beans1 as $bean1 ) {
			foreach ( $beans2 as $bean2 ) {
				$table     = $this->getTable( array( $bean1->getMeta( 'type' ), $bean2->getMeta( 'type' ) ) );
				$bean      = $this->oodb->dispense( $table );
				$results[] = $this->associateBeans( $bean1, $bean2, $bean );
			}
		}
		return ( count( $results ) > 1 ) ? $results : reset( $results );
	}
	/**
	 * Counts the number of related beans in an N-M relation.
	 * This method returns the number of beans of type $type associated
	 * with reference bean(s) $bean. The query can be tuned using an
	 * SQL snippet for additional filtering.
	 *
	 * @param RedBean_OODBBean|array $bean     a bean object or an array of beans
	 * @param string                 $type     type of bean you're interested in
	 * @param string                 $sql      SQL snippet (optional)
	 * @param array                  $bindings bindings for your SQL string
	 *
	 * @return integer
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function relatedCount( $bean, $type, $sql = NULL, $bindings = array() )
	{
		if ( !( $bean instanceof RedBean_OODBBean ) ) {
			throw new RedBean_Exception_Security(
				'Expected array or RedBean_OODBBean but got:' . gettype( $bean )
			);
		}
		if ( !$bean->id ) {
			return 0;
		}
		$beanType = $bean->getMeta( 'type' );
		try {
			return $this->writer->queryRecordCountRelated( $beanType, $type, $bean->id, $sql, $bindings );
		} catch ( RedBean_Exception_SQL $exception ) {
			$this->handleException( $exception );
			return 0;
		}
	}
	/**
	 * Returns all ids of beans of type $type that are related to $bean. If the
	 * $getLinks parameter is set to boolean TRUE this method will return the ids
	 * of the association beans instead. You can also add additional SQL. This SQL
	 * will be appended to the original query string used by this method. Note that this
	 * method will not return beans, just keys. For a more convenient method see the R-facade
	 * method related(), that is in fact a wrapper for this method that offers a more
	 * convenient solution. If you want to make use of this method, consider the
	 * OODB batch() method to convert the ids to beans.
	 *
	 * Since 3.2, you can now also pass an array of beans instead just one
	 * bean as the first parameter.
	 *
	 * @throws RedBean_Exception_SQL
	 *
	 * @param RedBean_OODBBean|array $bean     reference bean
	 * @param string                 $type     target type
	 * @param boolean                $getLinks whether you are interested in the assoc records
	 * @param string                 $sql      room for additional SQL
	 * @param array                  $bindings bindings for SQL snippet
	 *
	 * @return array
	 */
	public function related( $bean, $type, $getLinks = FALSE, $sql = '', $bindings = array() )
	{
		$sql  = $this->writer->glueSQLCondition( $sql );
		$rows = $this->relatedRows( $bean, $type, $getLinks, $sql, $bindings );
		$ids  = array();
		foreach ( $rows as $row ) $ids[] = $row['id'];
		return $ids;
	}
	/**
	 * Breaks the association between two beans. This method unassociates two beans. If the
	 * method succeeds the beans will no longer form an association. In the database
	 * this means that the association record will be removed. This method uses the
	 * OODB trash() method to remove the association links, thus giving FUSE models the
	 * opportunity to hook-in additional business logic. If the $fast parameter is
	 * set to boolean TRUE this method will remove the beans without their consent,
	 * bypassing FUSE. This can be used to improve performance.
	 *
	 * @param RedBean_OODBBean $bean1 first bean
	 * @param RedBean_OODBBean $bean2 second bean
	 * @param boolean          $fast  If TRUE, removes the entries by query without FUSE
	 *
	 * @return void
	 */
	public function unassociate( $beans1, $beans2, $fast = NULL )
	{
		$beans1 = ( !is_array( $beans1 ) ) ? array( $beans1 ) : $beans1;
		$beans2 = ( !is_array( $beans2 ) ) ? array( $beans2 ) : $beans2;
		foreach ( $beans1 as $bean1 ) {
			foreach ( $beans2 as $bean2 ) {
				try {
					$this->oodb->store( $bean1 );
					$this->oodb->store( $bean2 );
					$type1 = $bean1->getMeta( 'type' );
					$type2 = $bean2->getMeta( 'type' );
					$row      = $this->writer->queryRecordLink( $type1, $type2, $bean1->id, $bean2->id );
					$linkType = $this->getTable( array( $type1, $type2 ) );
					if ( $fast ) {
						$this->writer->deleteRecord( $linkType, array( 'id' => $row['id'] ) );
						return;
					}
					$beans = $this->oodb->convertToBeans( $linkType, array( $row ) );
					if ( count( $beans ) > 0 ) {
						$bean = reset( $beans );
						$this->oodb->trash( $bean );
					}
				} catch ( RedBean_Exception_SQL $exception ) {
					$this->handleException( $exception );
				}
			}
		}
	}
	/**
	 * Removes all relations for a bean. This method breaks every connection between
	 * a certain bean $bean and every other bean of type $type. Warning: this method
	 * is really fast because it uses a direct SQL query however it does not inform the
	 * models about this. If you want to notify FUSE models about deletion use a foreach-loop
	 * with unassociate() instead. (that might be slower though)
	 *
	 * @param RedBean_OODBBean $bean reference bean
	 * @param string           $type type of beans that need to be unassociated
	 *
	 * @return void
	 */
	public function clearRelations( RedBean_OODBBean $bean, $type )
	{
		$this->oodb->store( $bean );
		try {
			$this->writer->deleteRelations( $bean->getMeta( 'type' ), $type, $bean->id );
		} catch ( RedBean_Exception_SQL $exception ) {
			$this->handleException( $exception );
		}
	}
	/**
	 * Given two beans this function returns TRUE if they are associated using a
	 * many-to-many association, FALSE otherwise.
	 *
	 * @throws RedBean_Exception_SQL
	 *
	 * @param RedBean_OODBBean $bean1 bean
	 * @param RedBean_OODBBean $bean2 bean
	 *
	 * @return boolean
	 */
	public function areRelated( RedBean_OODBBean $bean1, RedBean_OODBBean $bean2 )
	{
		try {
			$row = $this->writer->queryRecordLink( $bean1->getMeta( 'type' ), $bean2->getMeta( 'type' ), $bean1->id, $bean2->id );
			return (boolean) $row;
		} catch ( RedBean_Exception_SQL $exception ) {
			$this->handleException( $exception );
			return FALSE;
		}
	}
	/**
	 * Swaps a property of two beans.
	 * Given two beans and a property this method swaps the value of the
	 * property in the beans.
	 *
	 * @deprecated
	 * This method does not seem very useful.
	 *
	 * @param array  $beans    beans
	 * @param string $property property to swap
	 *
	 * @return void
	 */
	public function swap( $beans, $property )
	{
		$bean1            = array_shift( $beans );
		$bean2            = array_shift( $beans );
		$tmp              = $bean1->$property;
		$bean1->$property = $bean2->$property;
		$bean2->$property = $tmp;
		$this->oodb->store( $bean1 );
		$this->oodb->store( $bean2 );
	}
	/**
	 * Returns all the beans associated with $bean.
	 * This method will return an array containing all the beans that have
	 * been associated once with the associate() function and are still
	 * associated with the bean specified. The type parameter indicates the
	 * type of beans you are looking for. You can also pass some extra SQL and
	 * values for that SQL to filter your results after fetching the
	 * related beans.
	 *
	 * Don't try to make use of subqueries, a subquery using IN() seems to
	 * be slower than two queries!
	 *
	 * Since 3.2, you can now also pass an array of beans instead just one
	 * bean as the first parameter.
	 *
	 * @param RedBean_OODBBean|array $bean      the bean you have
	 * @param string                 $type      the type of beans you want
	 * @param string                 $sql       SQL snippet for extra filtering
	 * @param array                  $bindings  values to be inserted in SQL slots
	 * @param boolean                $glue      whether the SQL should be prefixed with WHERE
	 *
	 * @return array
	 */
	public function relatedSimple( $bean, $type, $sql = '', $bindings = array() )
	{
		$sql   = $this->writer->glueSQLCondition( $sql );
		$rows  = $this->relatedRows( $bean, $type, FALSE, $sql, $bindings );
		$links = array();
		foreach ( $rows as $key => $row ) {
			if ( !isset( $links[$row['id']] ) ) {
				$links[$row['id']] = array();
			}
			$links[$row['id']][] = $row['linked_by'];
			unset( $rows[$key]['linked_by'] );
		}
		$beans = $this->oodb->convertToBeans( $type, $rows );
		foreach ( $beans as $bean ) {
			$bean->setMeta( 'sys.belongs-to', $links[$bean->id] );
		}
		return $beans;
	}
	/**
	 * Returns only a single associated bean.
	 * You can also pass some extra SQL and
	 * values for that SQL to filter your results after fetching the
	 * related beans.
	 * 
	 * @see RedBean_AssociationManager::relatedSimple.
	 *
	 * @param RedBean_OODBBean $bean     bean provided
	 * @param string           $type     type of bean you are searching for
	 * @param string           $sql      SQL for extra filtering
	 * @param array            $bindings values to be inserted in SQL slots
	 *
	 * @return RedBean_OODBBean
	 */
	public function relatedOne( RedBean_OODBBean $bean, $type, $sql = NULL, $bindings = array() )
	{
		$beans = $this->relatedSimple( $bean, $type, $sql, $bindings );
		if ( empty( $beans ) ) {
			return NULL;
		}
		return reset( $beans );
	}
	/**
	 * Returns only the last, single associated bean.
	 * You can also pass some extra SQL and
	 * values for that SQL to filter your results after fetching the
	 * related beans.
	 * 
	 * @see RedBean_AssociationManager::relatedSimple.
	 *
	 * @param RedBean_OODBBean $bean     bean provided
	 * @param string           $type     type of bean you are searching for
	 * @param string           $sql      SQL for extra filtering
	 * @param array            $bindings values to be inserted in SQL slots
	 *
	 * @return RedBean_OODBBean
	 */
	public function relatedLast( RedBean_OODBBean $bean, $type, $sql = NULL, $bindings = array() )
	{
		$beans = $this->relatedSimple( $bean, $type, $sql, $bindings );
		if ( empty( $beans ) ) {
			return NULL;
		}
		return end( $beans );
	}
}
class RedBean_Preloader
{
	/**
	 * @var RedBean_AssociationManager
	 */
	private $assocManager;
	/**
	 * @var RedBean_OODB
	 */
	private $oodb;
	/**
	 * @var integer
	 */
	private $counterID = 0;
	/**
	 * @var array
	 */
	private $filteredBeans = array();
	/**
	 * @var array
	 */
	private $retrievals = array();
	/**
	 * @var integer
	 */
	private $iterationIndex = 0;
	/**
	 * Extracts the type list for preloader.
	 * Explodes a list of comma separated types and splits
	 * the type definition in list -> type pairs if needed.
	 *
	 * @param array|string $typeList list of types
	 *
	 * @return array
	 */
	private function extractTypesFromTypeList( $typeList )
	{
		if ( !is_string( $typeList ) ) {
			return $typeList;
		}
		$typeList = explode( ',', $typeList );
		$types = array();
		foreach ( $typeList as $value ) {
			if ( strpos( $value, '|' ) !== FALSE ) {
				list( $key, $newValue ) = explode( '|', $value );
				$types[$key] = $newValue;
			} else {
				$types[] = $value;
			}
		}
		return $types;
	}
	/**
	 * Extracts preloading request for type from array.
	 *
	 * @param array $typeInfo type info
	 *
	 * @return array
	 */
	private function extractTypeInfo( $typeInfo )
	{
		if ( is_array( $typeInfo ) && !isset( $typeInfo[1] ) ) {
			$typeInfo[1] = array( NULL, array() );
		}
		
		list( $type, $sqlObj ) = ( is_array( $typeInfo ) ? $typeInfo : array( $typeInfo, array( NULL, array() ) ) );
		if ( !isset($sqlObj[1]) ) {
			$sqlObj[1] = array();
		}
		
		list( $sql, $bindings ) = $sqlObj;
		return array( $type, $sql, $bindings );
	}
	/**
	 * Marks the input beans.
	 * This method is used to connect the current selection of beans to
	 * input beans.
	 *
	 * @param array $beans beans to connect to input beans
	 *
	 * @return array
	 */
	private function markBeans( $filteredBeans )
	{
		$this->counterID = 0;
		foreach ( $filteredBeans as $bean ) {
			$bean->setMeta( 'sys.input-bean-id', array( $this->counterID => $this->counterID ) );
			$this->counterID++;
		}
		return $filteredBeans;
	}
	/**
	 * Adds the beans from the next step in the path to the collection of filtered
	 * beans.
	 *
	 * @param string $nesting       property (list or bean property)
	 *
	 * @return void
	 */
	private function addBeansForNextStepInPath( $nesting )
	{
		$filtered = array();
		foreach ( $this->filteredBeans as $bean ) {
			$addInputIDs = $bean->getMeta( 'sys.input-bean-id' );
			if ( isset($bean->$nesting) && is_array( $bean->$nesting ) ) {
				$nestedBeans = $bean->$nesting;
				foreach ( $nestedBeans as $nestedBean ) {
					$this->addInputBeanIDsToBean( $nestedBean, $addInputIDs );
				}
				$filtered = array_merge( $filtered, $nestedBeans );
			} elseif ( isset($bean->$nesting) && !is_null( $bean->$nesting ) ) {
				$this->addInputBeanIDsToBean( $bean->$nesting, $addInputIDs );
				$filtered[] = $bean->$nesting;
			}
		}
		$this->filteredBeans = $filtered;
	}
	/**
	 * Expands * and & symbols in preload syntax.
	 * Also adds the returned field name to the list of fields.
	 *
	 * @param string $key       key value for this field
	 * @param string $type      type of bean
	 * @param string $oldField  last field we've processed
	 * @param array  $oldFields list of previously gathered field names
	 *
	 * @return string
	 */
	private function getPreloadField( $key, $type, $oldField, &$oldFields )
	{
		$field = ( is_numeric( $key ) ) ? $type : $key; //use an alias?
		if ( strpos( $field, '*' ) !== FALSE ) {
			$oldFields[] = $oldField;
			$field       = str_replace( '*', implode( '.', $oldFields ), $field );
		}
		if ( strpos( $field, '&' ) !== FALSE ) {
			$field = str_replace( '&', implode( '.', $oldFields ), $field );
		}
		return $field;
	}
	/**
	 * For Preloader: adds the IDs of your input beans to the nested beans, otherwise
	 * we dont know how to pass them to the each-function later on.
	 *
	 * @param RedBean_OODBBean $nestedBean  nested bean
	 * @param array            $addInputIDs input ids
	 *
	 * @return void
	 */
	private function addInputBeanIDsToBean( $nestedBean, $addInputIDs )
	{
		$currentInputBeanIDs = $nestedBean->getMeta( 'sys.input-bean-id' );
		if ( !is_array( $currentInputBeanIDs ) ) {
			$currentInputBeanIDs = array();
		}
		foreach ( $addInputIDs as $addInputID ) {
			$currentInputBeanIDs[$addInputID] = $addInputID;
		}
		$nestedBean->setMeta( 'sys.input-bean-id', $currentInputBeanIDs );
	}
	/**
	 * For preloader: calls the function defined in $closure with retrievals for each
	 * bean in the first parameter.
	 *
	 * @param closure|string $closure    closure to invoke per bean
	 * @param array          $beans      beans to iterate over
	 * @param array          $retrievals retrievals to send as arguments to closure
	 *
	 * @return void
	 */
	private function invokePreloadEachFunction( $closure, $beans, $retrievals )
	{
		if ( $closure ) {
			$key = 0;
			foreach ( $beans as $bean ) {
				$bindings = array();
				foreach ( $retrievals as $r ) {
					$bindings[] = ( isset( $r[$key] ) ) ? $r[$key] : NULL;
				}
				array_unshift( $bindings, $bean );
				call_user_func_array( $closure, $bindings );
				$key++;
			}
		}
	}
	/**
	 * Fills the retrieval array with the beans in the (recently) retrieved
	 * shared/own list. This method asks the $filteredBean which original input bean
	 * it belongs to, then it will fill the parameter array for the specified
	 * iteration with the beans obtained for the filtered bean. This ensures the
	 * callback function for ::each will receive the correct bean lists as
	 * parameters for every iteration.
	 *
	 * @param RedBean_OODBBean $filteredBean         the bean we've retrieved lists for
	 * @param array            $list                 the list we've retrieved for the bean
	 *
	 * @return void
	 */
	private function fillParamArrayRetrievals( $filteredBean, $list )
	{
		$inputBeanIDs = $filteredBean->getMeta( 'sys.input-bean-id' );
		foreach ( $inputBeanIDs as $inputBeanID ) {
			if ( !isset( $this->retrievals[$this->iterationIndex][$inputBeanID] ) ) {
				$this->retrievals[$this->iterationIndex][$inputBeanID] = array();
			}
			foreach ( $list as $listKey => $listBean ) {
				$this->retrievals[$this->iterationIndex][$inputBeanID][$listKey] = $listBean;
			}
		}
	}
	/**
	 * Fills retrieval array with parent beans.
	 *
	 * @param array            $inputBeanIDs ids
	 * @param RedBean_OODBBean $parent       parent bean
	 */
	private function fillParamArrayRetrievalsWithParent( $inputBeanIDs, $parent )
	{
		foreach ( $inputBeanIDs as $inputBeanID ) {
			$this->retrievals[$this->iterationIndex][$inputBeanID] = $parent;
		}
	}
	/**
	 * Gathers the IDs to preload and maps the ids to the original beans.
	 *
	 * @param array  $filteredBeans filtered beans
	 * @param string $field         field name
	 *
	 * @return array
	 */
	private function gatherIDsToPreloadAndMap( $filteredBeans, $field )
	{
		$ids = $map = array();
		if ( strpos( $field, 'shared' ) !== 0 ) {
			// Gather ids to load the desired bean collections
			foreach ( $filteredBeans as $bean ) {
				if ( strpos( $field, 'own' ) === 0 ) {
					// Based on bean->id for ownlist
					$id       = $bean->id;
					$ids[$id] = $id;
				} elseif ( $id = $bean->{$field . '_id'} ) {
					// Based on bean_id for parent
					$ids[$id] = $id;
					if ( !isset( $map[$id] ) ) {
						$map[$id] = array();
					}
					$map[$id][] = $bean;
				}
			}
		}
		return array( $ids, $map );
	}
	/**
	 * Gathers the own list for a bean from a pool of child beans loaded by
	 * the preloader.
	 *
	 * @param RedBean_OODBBean $filteredBean
	 * @param array            $children
	 * @param string           $link
	 *
	 * @return array
	 */
	private function gatherOwnBeansFromPool( $filteredBean, $children, $link )
	{
		$list = array();
		foreach ( $children as $child ) {
			if ( $child->$link == $filteredBean->id ) {
				$list[$child->id] = $child;
			}
		}
		return $list;
	}
	/**
	 * Gathers the shared list for a bean from a pool of shared beans loaded
	 * by the preloader.
	 *
	 * @param RedBean_OODBBean $filteredBean
	 * @param array            $sharedBeans
	 *
	 * @return array
	 */
	private function gatherSharedBeansFromPool( $filteredBean, $sharedBeans )
	{
		$list = array();
		foreach ( $sharedBeans as $sharedBean ) {
			if ( in_array( $filteredBean->id, $sharedBean->getMeta( 'sys.belongs-to' ) ) ) {
				$list[] = $sharedBean;
			}
		}
		return $list;
	}
	/**
	 * Initializes the preloader.
	 * Initializes the filtered beans array, the retrievals array and
	 * the iteration index.
	 */
	private function init()
	{
		$this->iterationIndex = 0;
		$this->retrievals     = array();
		$this->filteredBeans  = array();
	}
	/**
	 * Preloads the shared beans.
	 *
	 * @param string $type     type of beans to load
	 * @param string $sql      additional SQL snippet for loading
	 * @param array  $bindings parameter bindings for SQL snippet
	 * @param string $field    field to store preloaded beans in
	 *
	 * @return void
	 */
	private function preloadSharedBeans( $type, $sql, $bindings, $field )
	{
		$sharedBeans = $this->assocManager->relatedSimple( $this->filteredBeans, $type, $sql, $bindings );
		// Let the filtered beans gather their beans
		foreach ( $this->filteredBeans as $filteredBean ) {
			$list = $this->gatherSharedBeansFromPool( $filteredBean, $sharedBeans );
			$filteredBean->setProperty( $field, $list, TRUE, TRUE );
			$this->fillParamArrayRetrievals( $filteredBean, $list );
		}
	}
	/**
	 * Preloads the own beans.
	 *
	 * @param string $type     type of beans to load
	 * @param string $sql      additional SQL snippet for loading
	 * @param array  $bindings parameter bindings for SQL snippet
	 * @param string $field    field to store preloaded beans in
	 * @param array  $ids      list of ids to load
	 * @param string $alias    alias for own list (optional)
	 *
	 * @return void
	 */
	private function preloadOwnBeans( $type, $sql, $bindings, $field, $ids, $alias = null )
	{
		$bean = reset( $this->filteredBeans );
		$link = ( $alias === NULL ) ? $bean->getMeta( 'type' ) . '_id' : ( $alias.'_id' );
		$children = $this->oodb->find( $type, array( $link => $ids ), $sql, $bindings );
		foreach ( $this->filteredBeans as $filteredBean ) {
			$list = $this->gatherOwnBeansFromPool( $filteredBean, $children, $link );
			$filteredBean->setProperty( $field, $list, TRUE, TRUE );
			$this->fillParamArrayRetrievals( $filteredBean, $list );
		}
	}
	/**
	 * Preloads parent beans.
	 *
	 * @param string $type  type of bean to load
	 * @param string $field field to store parent in
	 * @param array  $ids   list of ids to load
	 * @param array  $map   mapping to use (children indexed by parent bean ids)
	 * @param string $sql   optional SQL snippet for additional filtering
	 * @param array  $array optional bindings for SQL snippet
	 * 
	 * @return void
	 */
	private function preloadParentBeans( $type, $field, $ids, $map, $sql = NULL, $bindings = array() )
	{
		foreach ( $this->oodb->find( $type, array( 'id' => $ids ), $sql, $bindings ) as $parent ) {
			foreach ( $map[$parent->id] as $childBean ) {
				$childBean->setProperty( $field, $parent );
				$inputBeanIDs = $childBean->getMeta( 'sys.input-bean-id' );
				$this->fillParamArrayRetrievalsWithParent( $inputBeanIDs, $parent );
			}
		}
	}
	/**
	 * Simple input correction function. Checks whether input is a single bean
	 * and wraps it in an array if necessary.
	 *
	 * @param RedBean_OODBBean|array $beanOrBeans input
	 *
	 * @return array
	 */
	private function convertBeanToArrayIfNeeded( $beanOrBeans )
	{
		if ( !is_array( $beanOrBeans ) ) {
			$beanOrBeans = array( $beanOrBeans );
		}
		return $beanOrBeans;
	}
	/**
	 * Constructor.
	 *
	 * @param RedBean_OODB $oodb
	 */
	public function __construct( $oodb )
	{
		$this->oodb = $oodb;
		$this->assocManager = $oodb->getAssociationManager();
	}
	/**
	 * Preloads certain properties for beans.
	 * Understands aliases.
	 *
	 * Usage: 
	 * 
	 * R::preload($books, 'author');
	 * 
	 * - preloads all the authors of all books, 
	 * saves you a query per for-each iteration
	 * 
	 * R::preload($books, array('coauthor'=>'author'));
	 * 
	 * - same but with alias
	 * 
	 * R::preload($texts,'page,page.book,page.book.author');
    * 
	 * - preloads all pages for the texts, the books and the authors
	 * 
	 * R::preload($texts,'page,*.book,*.author');
	 * 
	 * - same as above bit with short syntax (* means prefix with previous types)
	 *
	 * R::preload($p,'book,*.author,&.shelf');
	 * 
	 * - if author and shelf are on the same level use & instead of *.
	 * 
	 * The other way around is possible as well, to load child beans in own-lists or
	 * shared-lists use:
	 * 
	 * R::preload($books,'ownPage|page,sharedGenre|genre');
	 * 
	 * @param array        $beans beans beans to use as a reference for preloading
	 * @param array|string $types types to load, either string or array
	 *
	 * @return array
	 */
	public function load( $beans, $typeList, $closure = NULL )
	{
		$beans = $this->convertBeanToArrayIfNeeded( $beans );
		$this->init();
		$types     = $this->extractTypesFromTypeList( $typeList );
		$oldFields = array();
		$oldField = '';
		foreach ( $types as $key => $typeInfo ) {
			list( $type, $sql, $bindings ) = $this->extractTypeInfo( $typeInfo );
			$this->retrievals[$this->iterationIndex] = array();
			
			$alias = NULL;
			if ( strpos( $key, '/' ) !== false ) {
				list( $key, $alias ) = explode( '/', $key );
			}
			$field = $this->getPreloadField( $key, $type, $oldField, $oldFields );
			$this->filteredBeans = $this->markBeans( $beans );
			// Filtering: find the right beans in the path
			while ( $p = strpos( $field, '.' ) ) {
				$this->addBeansForNextStepInPath( substr( $field, 0, $p ) );
				$field = substr( $field, $p + 1 );
			}
			$oldField = $field;
			$type = ( strpos( $type, '.' ) !== FALSE ) ? $field : $type;
			if ( count( $this->filteredBeans ) === 0 ) continue;
			list( $ids, $map ) = $this->gatherIDsToPreloadAndMap( $this->filteredBeans, $field );
			if ( strpos( $field, 'shared' ) === 0 ) {
				$this->preloadSharedBeans( $type, $sql, $bindings, $field );
			} elseif ( strpos( $field, 'own' ) === 0 ) {
				// Preload for own-list using find
				$this->preloadOwnBeans( $type, $sql, $bindings, $field, $ids, $alias );
			} else {
				// Preload for parent objects using batch()
				$this->preloadParentBeans( $type, $field, $ids, $map, $sql, $bindings );
			}
			$this->iterationIndex++;
		}
		$this->invokePreloadEachFunction( $closure, $beans, $this->retrievals );
	}
}
class RedBean_AssociationManager_ExtAssociationManager extends RedBean_AssociationManager
{
	/**
	 * @deprecated
	 *
	 * Associates two beans and allows you to specify a base bean to form the
	 * link between the two.
	 *
	 * This method has been deprecated, please use $bean->link instead to form
	 * many-to-many associations with additional properties.
	 *
	 * @param RedBean_OODBBean $bean1    the first bean you want to associate
	 * @param RedBean_OODBBean $bean2    the second bean you want to associate
	 * @param RedBean_OODBBean $baseBean the link bean
	 *
	 * @return array
	 */
	public function extAssociate( RedBean_OODBBean $bean1, RedBean_OODBBean $bean2, RedBean_OODBBean $baseBean )
	{
		$table = $this->getTable( array( $bean1->getMeta( 'type' ), $bean2->getMeta( 'type' ) ) );
		$baseBean->setMeta( 'type', $table );
		return $this->associateBeans( $bean1, $bean2, $baseBean );
	}
	/**
	 * @deprecated
	 *
	 * Simplified version of extAssociate().
	 * Associates two beans $bean1 and $bean2 with additional properties defined in
	 * third parameter $extra. This third parameter can be either an array, a
	 * JSON string, a single value (will be assigned to property 'extra') or a
	 * bean.
	 *
	 * This method has been deprecated, please use $bean->link instead to form
	 * many-to-many associations with additional properties.
	 *
	 * @param RedBean_OODBBean $bean1 the first bean you want to associate
	 * @param RedBean_OODBBean $bean2 the second bean you want to associate
	 * @param mixed            $extra one or more additional properties and values
	 *
	 * @return array
	 */
	public function extAssociateSimple( $beans1, $beans2, $extra = NULL )
	{
		if ( !is_array( $extra ) ) {
			$info = json_decode( $extra, TRUE );
			if ( !$info ) $info = array( 'extra' => $extra );
		} else {
			$info = $extra;
		}
		$bean = $this->oodb->dispense( 'xtypeless' );
		$bean->import( $info );
		return $this->extAssociate( $beans1, $beans2, $bean );
	}
}
class RedBean_Setup
{
	/**
	 * This method checks the DSN string.
	 * Checks the validity of the DSN string.
	 * If the DSN contains an invalid database identifier this method
	 * will trigger an error.
	 *
	 * @param string $dsn
	 *
	 * @return boolean
	 */
	private static function checkDSN( $dsn )
	{
		if ( !preg_match( '/^(mysql|sqlite|pgsql|cubrid|oracle):/', strtolower( trim( $dsn ) ) ) ) {
			trigger_error( 'Unsupported DSN' );
		}
		return TRUE;
	}
	/**
	 * Initializes the database and prepares a toolbox.
	 * The kickstart method assembles a toolbox based on your DSN and
	 * credentials and returns it.
	 * The toolbox contains all the necessary core components for
	 * RedBeanPHP to start working with your database. Most RedBeanPHP
	 * components are stand-alone and require a toolbox to work.
	 *
	 * @param  string|PDO $dsn      Database Connection String (or PDO instance)
	 * @param  string     $username Username for database
	 * @param  string     $password Password for database
	 * @param  boolean    $frozen   Start in frozen mode?
	 *
	 * @return RedBean_ToolBox
	 */
	public static function kickstart( $dsn, $username = NULL, $password = NULL, $frozen = FALSE, $autoSetEncoding = TRUE )
	{
		if ( $dsn instanceof PDO ) {
			$db  = new RedBean_Driver_PDO( $dsn );
			$dsn = $db->getDatabaseType();
		} else {
			self::checkDSN( $dsn );
			if ( strpos( $dsn, 'oracle' ) === 0 ) {
				$db = new RedBean_Driver_OCI( $dsn, $username, $password);
			} else {
				$db = new RedBean_Driver_PDO( $dsn, $username, $password, $autoSetEncoding );
			}
		}
		$adapter = new RedBean_Adapter_DBAdapter( $db );
		if ( strpos( $dsn, 'pgsql' ) === 0 ) {
			$writer = new RedBean_QueryWriter_PostgreSQL( $adapter );
		} else if ( strpos( $dsn, 'sqlite' ) === 0 ) {
			$writer = new RedBean_QueryWriter_SQLiteT( $adapter );
		} else if ( strpos( $dsn, 'cubrid' ) === 0 ) {
			$writer = new RedBean_QueryWriter_CUBRID( $adapter );
		} else if ( strpos( $dsn, 'oracle' ) === 0 ) {
			$writer = new RedBean_QueryWriter_Oracle( $adapter );
		} else {
			$writer = new RedBean_QueryWriter_MySQL( $adapter );
		}
		$redbean = new RedBean_OODB( $writer );
		if ( $frozen ) {
			$redbean->freeze( TRUE );
		}
		$toolbox = new RedBean_ToolBox( $redbean, $adapter, $writer );
		return $toolbox;
	}
}
interface RedBean_IModelFormatter
{
	/**
	 * ModelHelper will call this method of the class
	 * you provide to discover the model
	 *
	 * @param string $model
	 *
	 * @return string $formattedModel
	 */
	public function formatModel( $model );
}
interface RedBean_Logger
{
	/**
	 * A logger (for PDO or OCI driver) needs to implement the log method.
	 * The log method will receive logging data. Note that the number of parameters is 0, this means
	 * all parameters are optional and the number may vary. This way the logger can be used in a very
	 * flexible way. Sometimes the logger is used to log a simple error message and in other
	 * situations sql and bindings are passed.
	 * The log method should be able to accept all kinds of parameters and data by using
	 * functions like func_num_args/func_get_args.
	 * 
	 * @return void
	 */
	public function log();
}
class RedBean_Logger_Default implements RedBean_Logger
{
	/**
	 * Default logger method logging to STDOUT.
	 * This is the default/reference implementation of a logger.
	 * This method will write the message value to STDOUT (screen).
	 *
	 * @param $message (optional)
	 *
	 * @return void
	 */
	public function log()
	{
		if ( func_num_args() < 1 ) return;
		foreach ( func_get_args() as $argument ) {
			if ( is_array( $argument ) ) {
				echo print_r( $argument, TRUE );
			} else {
				echo $argument;
			}
			echo "<br>\n";
		}
	}
}
interface RedBean_BeanHelper
{
	/**
	 * Returns a toolbox to empower the bean.
	 * This allows beans to perform OODB operations by themselves,
	 * as such the bean is a proxy for OODB. This allows beans to implement
	 * their magic getters and setters and return lists.
	 *
	 * @return RedBean_Toolbox $toolbox toolbox
	 */
	public function getToolbox();
	/**
	 * Does approximately the same as getToolbox but also extracts the
	 * toolbox for you.
	 * This method returns a list with all toolbox items in Toolbox Constructor order:
	 * OODB, adapter, writer and finally the toolbox itself!.
	 *
	 * @return array
	 */
	public function getExtractedToolbox();
	/**
	 * Given a certain bean this method will
	 * return the corresponding model.
	 *
	 * @param RedBean_OODBBean $bean
	 *
	 * @return string
	 */
	public function getModelForBean( RedBean_OODBBean $bean );
}
class RedBean_BeanHelper_Facade implements RedBean_BeanHelper
{
	/**
	 * @see RedBean_BeanHelper::getToolbox
	 */
	public function getToolbox()
	{
		return RedBean_Facade::$toolbox;
	}
	/**
	 * @see RedBean_BeanHelper::getModelForBean
	 */
	public function getModelForBean( RedBean_OODBBean $bean )
	{
		$modelName = RedBean_ModelHelper::getModelName( $bean->getMeta( 'type' ), $bean );
		if ( !class_exists( $modelName ) ) {
			return NULL;
		}
		$obj = RedBean_ModelHelper::factory( $modelName );
		$obj->loadBean( $bean );
		return $obj;
	}
	/**
	 * @see RedBean_BeanHelper::getExtractedToolbox
	 */
	public function getExtractedToolbox()
	{
		$toolbox = $this->getToolbox();
		return array( $toolbox->getRedBean(), $toolbox->getDatabaseAdapter(), $toolbox->getWriter(), $toolbox );
	}
}
class RedBean_SimpleModel
{
	/**
	 * @var RedBean_OODBBean
	 */
	protected $bean;
	/**
	 * Used by FUSE: the ModelHelper class to connect a bean to a model.
	 * This method loads a bean in the model.
	 *
	 * @param RedBean_OODBBean $bean bean
	 *
	 * @return void
	 */
	public function loadBean( RedBean_OODBBean $bean )
	{
		$this->bean = $bean;
	}
	/**
	 * Magic Getter to make the bean properties available from
	 * the $this-scope.
	 *
	 * @note this method returns a value, not a reference!
	 *       To obtain a reference unbox the bean first!
	 *
	 * @param string $prop property
	 *
	 * @return mixed
	 */
	public function __get( $prop )
	{
		return $this->bean->$prop;
	}
	/**
	 * Magic Setter.
	 * Sets the value directly as a bean property.
	 *
	 * @param string $prop  property
	 * @param mixed  $value value
	 *
	 * @return void
	 */
	public function __set( $prop, $value )
	{
		$this->bean->$prop = $value;
	}
	/**
	 * Isset implementation.
	 * Implements the isset function for array-like access.
	 *
	 * @param  string $key key to check
	 *
	 * @return boolean
	 */
	public function __isset( $key )
	{
		return isset( $this->bean->$key );
	}
	/**
	 * Box the bean using the current model.
	 * This method wraps the current bean in this model.
	 * This method can be reached using FUSE through a simple
	 * OODBBean. The method returns a RedBeanPHP Simple Model.
	 * This is useful if you would like to rely on PHP type hinting.
	 * You can box your beans before passing them to functions or methods
	 * with typed parameters.
	 *
	 * @return RedBean_SimpleModel
	 */
	public function box()
	{
		return $this;
	}
	/**
	 * Unbox the bean from the model.
	 * This method returns the bean inside the model.
	 *
	 * @return RedBean_OODBBean
	 */
	public function unbox()
	{
		return $this->bean;
	}
}
class RedBean_ModelHelper implements RedBean_Observer
{
	/**
	 * @var RedBean_IModelFormatter
	 */
	private static $modelFormatter;
	/**
	 * @var RedBean_DependencyInjector
	 */
	private static $dependencyInjector;
	/**
	 * @see RedBean_Observer::onEvent
	 */
	public function onEvent( $eventName, $bean )
	{
		$bean->$eventName();
	}
	/**
	 * Given a model ID (model identifier) this method returns the
	 * full model name.
	 *
	 * @param string           $model
	 * @param RedBean_OODBBean $bean
	 *
	 * @return string
	 */
	public static function getModelName( $model, $bean = NULL )
	{
		if ( self::$modelFormatter ) {
			return self::$modelFormatter->formatModel( $model, $bean );
		} else {
			$prefix = defined('REDBEAN_MODEL_PREFIX') ? REDBEAN_MODEL_PREFIX : 'Model_';
			return $prefix . ucfirst( $model );
		}
	}
	/**
	 * Sets the model formatter to be used to discover a model
	 * for Fuse.
	 *
	 * @param string $modelFormatter
	 *
	 * @return void
	 */
	public static function setModelFormatter( $modelFormatter )
	{
		self::$modelFormatter = $modelFormatter;
	}
	/**
	 * Obtains a new instance of $modelClassName, using a dependency injection
	 * container if possible.
	 *
	 * @param string $modelClassName name of the model
	 *
	 * @return object
	 */
	public static function factory( $modelClassName )
	{
		if ( self::$dependencyInjector ) {
			return self::$dependencyInjector->getInstance( $modelClassName );
		}
		return new $modelClassName();
	}
	/**
	 * Sets the dependency injector to be used.
	 *
	 * @param RedBean_DependencyInjector $di injector to be used
	 *
	 * @return void
	 */
	public static function setDependencyInjector( RedBean_DependencyInjector $di )
	{
		self::$dependencyInjector = $di;
	}
	/**
	 * Stops the dependency injector from resolving dependencies. Removes the
	 * reference to the dependency injector.
	 *
	 * @return void
	 */
	public static function clearDependencyInjector()
	{
		self::$dependencyInjector = NULL;
	}
	/**
	 * Attaches the FUSE event listeners. Now the Model Helper will listen for
	 * CRUD events. If a CRUD event occurs it will send a signal to the model
	 * that belongs to the CRUD bean and this model will take over control from
	 * there.
	 *
	 * @param RedBean_Observable $observable
	 *
	 * @return void
	 */
	public function attachEventListeners( RedBean_Observable $observable )
	{
		foreach ( array( 'update', 'open', 'delete', 'after_delete', 'after_update', 'dispense' ) as $e ) {
			$observable->addEventListener( $e, $this );
		}
	}
}
class RedBean_SQLHelper
{
	/**
	 * @var RedBean_Adapter
	 */
	protected $adapter;
	/**
	 * @var boolean
	 */
	protected $capture = FALSE;
	/**
	 * @var string
	 */
	protected $sql = '';
	/**
	 * @var boolean
	 */
	protected static $flagUseCamelCase = TRUE;
	/**
	 * @var array
	 */
	protected $params = array();
	/**
	 * Toggles support for camelCased statements.
	 * If set to TRUE this will turn camelCase into spaces.
	 * For instance leftJoin becomes
	 * 'left join'.
	 *
	 * @param boolean $yesNo TRUE to use camelcase mode
	 *
	 * @return void
	 */
	public static function useCamelCase( $yesNo )
	{
		self::$flagUseCamelCase = (boolean) $yesNo;
	}
	/**
	 * Constructor.
	 * Allows you to mix PHP and SQL as if they were one language.
	 *
	 * @param RedBean_Adapter_DBAdapter $adapter database adapter for querying
	 */
	public function __construct( RedBean_Adapter $adapter )
	{
		$this->adapter = $adapter;
	}
	/**
	 * Magic method to construct SQL query.
	 * Accepts any kind of message and turns it into an SQL statement and
	 * adds it to the query string.
	 * If camelcase is set to TRUE camelCase transitions will be turned into spaces.
	 * Underscores will be replaced with spaces as well.
	 * Arguments will be imploded using a comma as glue character and are also added
	 * to the query.
	 *
	 * If capture mode is on, this method returns a reference to itself allowing
	 * chaining.
	 *
	 * If capture mode if off, this method will immediately exceute the resulting
	 * SQL query and return a string result.
	 *
	 * @param string $funcName name of the next SQL statement/keyword
	 * @param array  $args     list of statements to be seperated by commas
	 *
	 * @return string|RedBean_SQLHelper
	 */
	public function __call( $funcName, $args = array() )
	{
		if ( self::$flagUseCamelCase ) {
			static $funcCache = array();
			if ( !isset( $funcCache[$funcName] ) ) {
				$funcCache[$funcName] = strtolower( preg_replace( '/(?<=[a-z])([A-Z])|([A-Z])(?=[a-z])/', '_$1$2', $funcName ) );
			}
			$funcName = $funcCache[$funcName];
		}
		$funcName = str_replace( '_', ' ', $funcName );
		if ( $this->capture ) {
			$this->sql .= ' ' . $funcName . ' ' . implode( ',', $args );
			return $this;
		} else {
			return $this->adapter->getCell( 'SELECT ' . $funcName . '(' . implode( ',', $args ) . ')' );
		}
	}
	/**
	 * Begins SQL query.
	 * Turns on capture mode. The helper will now postpone execution of the
	 * resulting SQL until the get() method has been invoked.
	 *
	 * @return RedBean_SQLHelper
	 */
	public function begin()
	{
		$this->capture = TRUE;
		return $this;
	}
	/**
	 * Adds a value to the parameter list.
	 * This method adds a value to the list of parameters that will be bound
	 * to the SQL query. Chainable.
	 *
	 * @param mixed $param parameter to be added
	 *
	 * @return RedBean_SQLHelper
	 */
	public function put( $param )
	{
		$this->params[] = $param;
		return $this;
	}
	/**
	 * Executes query and returns the result.
	 * In capture mode this method will execute the query you have build using
	 * this helper and return the result.
	 * The parameter determines how to retrieve the results from the query.
	 * Possible options are: 'cell', 'row', 'col' or 'all'.
	 * Use cell to obtain a single cell, row for a row, col for a column and all for
	 * a multidimensional array.
	 *
	 * @param string $retrieval One of these 'cell', 'row', 'col' or 'all'.
	 *
	 * @return mixed $result
	 */
	public function get( $what = '' )
	{
		$what = 'get' . ucfirst( $what );
		$rs   = $this->adapter->$what( $this->sql, $this->params );
		$this->clear();
		return $rs;
	}
	/**
	 * Clears the parameter list as well as the SQL query string.
	 *
	 * @return RedBean_SQLHelper
	 */
	public function clear()
	{
		$this->sql     = '';
		$this->params  = array();
		$this->capture = FALSE; //turn off capture mode (issue #142)
		return $this;
	}
	/**
	 * To explicitly add a piece of SQL.
	 *
	 * @param string $sql sql
	 *
	 * @return RedBean_SQLHelper
	 */
	public function addSQL( $sql )
	{
		if ( $this->capture ) {
			$this->sql .= ' ' . $sql . ' ';
		}
		return $this;
	}
	/**
	 * Returns query parts.
	 * This method returns the query parts in an array.
	 * This method returns an array with the following format:
	 *
	 * array(
	 *        string $sqlStatementString,
	 *        array $parameters
	 * )
	 *
	 * @return array
	 */
	public function getQuery()
	{
		$list = array( $this->sql, $this->params );
		$this->clear();
		return $list;
	}
	/**
	 * Nests another query builder query in the current query.
	 *
	 * @param RedBean_SQLHelper
	 *
	 * @return RedBean_SQLHelper
	 */
	public function nest( RedBean_SQLHelper $sqlHelper )
	{
		list( $sql, $params ) = $sqlHelper->getQuery();
		$this->sql .= $sql;
		$this->params += $params;
		return $this;
	}
	/**
	 * Writes a '(' to the sql query.
	 *
	 * @return RedBean_SQLHelper
	 */
	public function open()
	{
		if ( $this->capture ) {
			$this->sql .= ' ( ';
		}
		return $this;
	}
	/**
	 * Writes a ')' to the sql query.
	 *
	 * @return RedBean_SQLHelper
	 */
	public function close()
	{
		if ( $this->capture ) {
			$this->sql .= ' ) ';
		}
		return $this;
	}
	/**
	 * Generates question mark slots for an array of values.
	 * For each entry of the array this method generates a single
	 * question mark character slot. Finally the slots are glued
	 * separated by commas and returned as a single string.
	 *
	 * @param array $array Array with values to generate slots for
	 *
	 * @return string
	 */
	public function genSlots( $array )
	{
		if ( is_array( $array ) && count( $array ) > 0 ) {
			$filler = array_fill( 0, count( $array ), '?' );
			return implode( ',', $filler );
		} else {
			return '';
		}
	}
	/**
	 * Returns a new SQL Helper with the same adapter as the current one.
	 *
	 * @return RedBean_SQLHelper
	 */
	public function getNew()
	{
		return new self( $this->adapter );
	}
	/**
	 * When cast to string, simply print the query and its bindings.
	 * 
	 * @return string
	 */
	public function __toString()
	{
		list( $query, $params ) = $this->getQuery();
		return print_r( array(
			 'query'  => $query,
			 'params' => $params
		), true );
	}
}
class RedBean_TagManager
{
	/**
	 * @var RedBean_Toolbox
	 */
	protected $toolbox;
	/**
	 * @var RedBean_AssociationManager
	 */
	protected $associationManager;
	/**
	 * @var RedBean_OODBBean
	 */
	protected $redbean;
	/**
	 * Checks if the argument is a comma separated string, in this case
	 * it will split the string into words and return an array instead.
	 * In case of an array the argument will be returned 'as is'.
	 *
	 * @param array|string $tagList list of tags
	 *
	 * @return array
	 */
	private function extractTagsIfNeeded( $tagList )
	{
		if ( $tagList !== FALSE && !is_array( $tagList ) ) {
			$tags = explode( ',', (string) $tagList );
		} else {
			$tags = $tagList;
		}
		return $tags;
	}
	/**
	 * Constructor.
	 * The tag manager offers an easy way to quickly implement basic tagging
	 * functionality.
	 *
	 * @param RedBean_Toolbox $toolbox
	 */
	public function __construct( RedBean_Toolbox $toolbox )
	{
		$this->toolbox = $toolbox;
		$this->redbean = $toolbox->getRedBean();
		$this->associationManager = $this->redbean->getAssociationManager();
	}
	/**
	 * Finds a tag bean by it's title.
	 * Internal method.
	 *
	 * @param string $title title
	 *
	 * @return RedBean_OODBBean
	 */
	protected function findTagByTitle( $title )
	{
		$beans = $this->redbean->find( 'tag', array( 'title' => array( $title ) ) );
		if ( $beans ) {
			$bean = reset( $beans );
			return $bean;
		}
		return NULL;
	}
	/**
	 * Tests whether a bean has been associated with one ore more
	 * of the listed tags. If the third parameter is TRUE this method
	 * will return TRUE only if all tags that have been specified are indeed
	 * associated with the given bean, otherwise FALSE.
	 * If the third parameter is FALSE this
	 * method will return TRUE if one of the tags matches, FALSE if none
	 * match.
	 * 
	 * Tag list can be either an array with tag names or a comma separated list
	 * of tag names.
	 *
	 * @param  RedBean_OODBBean $bean bean to check for tags
	 * @param  array|string     $tags list of tags
	 * @param  boolean          $all  whether they must all match or just some
	 *
	 * @return boolean
	 */
	public function hasTag( $bean, $tags, $all = FALSE )
	{
		$foundtags = $this->tag( $bean );
		$tags = $this->extractTagsIfNeeded( $tags );
		$same = array_intersect( $tags, $foundtags );
		if ( $all ) {
			return ( implode( ',', $same ) === implode( ',', $tags ) );
		}
		return (bool) ( count( $same ) > 0 );
	}
	/**
	 * Removes all sepcified tags from the bean. The tags specified in
	 * the second parameter will no longer be associated with the bean.
	 * 
	 * Tag list can be either an array with tag names or a comma separated list
	 * of tag names.
	 *
	 * @param  RedBean_OODBBean $bean    tagged bean
	 * @param  array|string     $tagList list of tags (names)
	 *
	 * @return void
	 */
	public function untag( $bean, $tagList )
	{
		$tags = $this->extractTagsIfNeeded( $tagList );
		foreach ( $tags as $tag ) {
			if ( $t = $this->findTagByTitle( $tag ) ) {
				$this->associationManager->unassociate( $bean, $t );
			}
		}
	}
	/**
	 * Tags a bean or returns tags associated with a bean.
	 * If $tagList is NULL or omitted this method will return a
	 * comma separated list of tags associated with the bean provided.
	 * If $tagList is a comma separated list (string) of tags all tags will
	 * be associated with the bean.
	 * You may also pass an array instead of a string.
	 * 
	 * Tag list can be either an array with tag names or a comma separated list
	 * of tag names.
	 *
	 * @param RedBean_OODBBean $bean    bean to be tagged
	 * @param array|string     $tagList a list of tags
	 *
	 * @return array
	 */
	public function tag( RedBean_OODBBean $bean, $tagList = NULL )
	{
		if ( is_null( $tagList ) ) {
			$tags = array();
			$keys = $this->associationManager->related( $bean, 'tag' );
			if ( $keys ) {
				$tags = $this->redbean->batch( 'tag', $keys );
			}
			$foundTags = array();
			foreach ( $tags as $tag ) {
				$foundTags[] = $tag->title;
			}
			return $foundTags;
		}
		$this->associationManager->clearRelations( $bean, 'tag' );
		$this->addTags( $bean, $tagList );
		return $tagList;
	}
	/**
	 * Adds tags to a bean.
	 * If $tagList is a comma separated list of tags all tags will
	 * be associated with the bean.
	 * You may also pass an array instead of a string.
	 * 
	 * Tag list can be either an array with tag names or a comma separated list
	 * of tag names.
	 *
	 * @param RedBean_OODBBean $bean    bean to add tags to
	 * @param array|string     $tagList list of tags to add to bean
	 *
	 * @return void
	 */
	public function addTags( RedBean_OODBBean $bean, $tagList )
	{
		$tags = $this->extractTagsIfNeeded( $tagList );
		if ( $tagList === FALSE ) {
			return;
		}
		foreach ( $tags as $tag ) {
			if ( !$t = $this->findTagByTitle( $tag ) ) {
				$t        = $this->redbean->dispense( 'tag' );
				$t->title = $tag;
				$this->redbean->store( $t );
			}
			$this->associationManager->associate( $bean, $t );
		}
	}
	/**
	 * Returns all beans that have been tagged with one or more
	 * of the specified tags.
	 * 
	 * Tag list can be either an array with tag names or a comma separated list
	 * of tag names.
	 *
	 * @param string       $beanType type of bean you are looking for
	 * @param array|string $tagList  list of tags to match
	 *
	 * @return array
	 */
	public function tagged( $beanType, $tagList )
	{
		$tags       = $this->extractTagsIfNeeded( $tagList );
		$collection = array();
		$tags       = $this->redbean->find( 'tag', array( 'title' => $tags ) );
		if ( is_array( $tags ) && count( $tags ) > 0 ) {
			$collectionKeys = $this->associationManager->related( $tags, $beanType );
			if ( $collectionKeys ) {
				$collection = $this->redbean->batch( $beanType, $collectionKeys );
			}
		}
		return $collection;
	}
	/**
	 * Returns all beans that have been tagged with ALL of the tags given.
	 * 
	 * Tag list can be either an array with tag names or a comma separated list
	 * of tag names.
	 * 
	 * @param string        $beanType type of bean you are looking for
	 * @param array|string  $tagList  list of tags to match
	 *
	 * @return array
	 */
	public function taggedAll( $beanType, $tagList )
	{
		$tags  = $this->extractTagsIfNeeded( $tagList );
		$beans = array();
		foreach ( $tags as $tag ) {
			$beans = $this->tagged( $beanType, $tag );
			if ( isset( $oldBeans ) ) {
				$beans = array_intersect_assoc( $beans, $oldBeans );
			}
			$oldBeans = $beans;
		}
		return $beans;
	}
}
class RedBean_LabelMaker
{
	/**
	 * @var RedBean_Toolbox
	 */
	protected $toolbox;
	/**
	 * Constructor.
	 *
	 * @param RedBean_ToolBox $toolbox
	 */
	public function __construct( RedBean_ToolBox $toolbox )
	{
		$this->toolbox = $toolbox;
	}
	/**
	 * A label is a bean with only an id, type and name property.
	 * This function will dispense beans for all entries in the array. The
	 * values of the array will be assigned to the name property of each
	 * individual bean.
	 *
	 * @param string $type   type of beans you would like to have
	 * @param array  $labels list of labels, names for each bean
	 *
	 * @return array
	 */
	public function dispenseLabels( $type, $labels )
	{
		$labelBeans = array();
		foreach ( $labels as $label ) {
			$labelBean       = $this->toolbox->getRedBean()->dispense( $type );
			$labelBean->name = $label;
			$labelBeans[]    = $labelBean;
		}
		return $labelBeans;
	}
	/**
	 * Gathers labels from beans. This function loops through the beans,
	 * collects the values of the name properties of each individual bean
	 * and stores the names in a new array. The array then gets sorted using the
	 * default sort function of PHP (sort).
	 *
	 * @param array $beans list of beans to loop
	 *
	 * @return array
	 */
	public function gatherLabels( $beans )
	{
		$labels = array();
		foreach ( $beans as $bean ) {
			$labels[] = $bean->name;
		}
		sort( $labels );
		return $labels;
	}
	
	/**
	 * Returns a label or an array of labels for use as ENUMs.
	 * 
	 * @param string $enum ENUM specification for label
	 * 
	 * @return array|RedBean_OODBBean
	 */
	public function enum( $enum )
	{
		$oodb = $this->toolbox->getRedBean();
		
		if ( strpos( $enum, ':' ) === FALSE ) {
			$type  = $enum;
			$value = FALSE;
		} else {
			list( $type, $value ) = explode( ':', $enum );
			$value                = preg_replace( '/\W+/', '_', strtoupper( trim( $value ) ) );
		}
		
		$values = $oodb->find( $type );
		
		if ( $value === FALSE ) {
			return $values;
		}
		
		foreach( $values as $enumItem ) {
				if ( $enumItem->name === $value ) return $enumItem;	
		}
		
		$newEnumItems = $this->dispenseLabels( $type, array( $value ) );
		$newEnumItem  = reset( $newEnumItems );
		
		$oodb->store( $newEnumItem );
		
		return $newEnumItem;
	}
}
class RedBean_Finder
{
	/**
	 * @var RedBean_ToolBox
	 */
	protected $toolbox;
	/**
	 * @var RedBean_OODB
	 */
	protected $redbean;
	/**
	 * Constructor.
	 * The Finder requires a toolbox.
	 *
	 * @param RedBean_ToolBox $toolbox
	 */
	public function __construct( RedBean_ToolBox $toolbox )
	{
		$this->toolbox = $toolbox;
		$this->redbean = $toolbox->getRedBean();
	}
	/**
	 * Finds a bean using a type and a where clause (SQL).
	 * As with most Query tools in RedBean you can provide values to
	 * be inserted in the SQL statement by populating the value
	 * array parameter; you can either use the question mark notation
	 * or the slot-notation (:keyname).
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return array
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function find( $type, $sql = NULL, $bindings = array() )
	{
		if ( $sql instanceof RedBean_SQLHelper ) {
			list( $sql, $bindings ) = $sql->getQuery();
		}
		if ( !is_array( $bindings ) ) {
			throw new RedBean_Exception_Security(
				'Expected array, ' . gettype( $bindings ) . ' given.'
			);
		}
		return $this->redbean->find( $type, array(), $sql, $bindings );
	}
	/**
	 * @see RedBean_Finder::find
	 *      The variation also exports the beans (i.e. it returns arrays).
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return array
	 */
	public function findAndExport( $type, $sql = NULL, $bindings = array() )
	{
		$arr = array();
		foreach ( $this->find( $type, $sql, $bindings ) as $key => $item ) {
			$arr[$key] = $item->export();
		}
		return $arr;
	}
	/**
	 * @see RedBean_Finder::find
	 *      This variation returns the first bean only.
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return RedBean_OODBBean
	 */
	public function findOne( $type, $sql = NULL, $bindings = array() )
	{
		$items = $this->find( $type, $sql, $bindings );
		if ( empty($items) ) {
			return NULL;
		}
		return reset( $items );
	}
	/**
	 * @see RedBean_Finder::find
	 *      This variation returns the last bean only.
	 *
	 * @param string $type     the type of bean you are looking for
	 * @param string $sql      SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return RedBean_OODBBean
	 */
	public function findLast( $type, $sql = NULL, $bindings = array() )
	{
		$items = $this->find( $type, $sql, $bindings );
		if ( empty($items) ) {
			return NULL;
		}
		return end( $items );
	}
	/**
	 * @see RedBean_Finder::find
	 *      Convience method. Tries to find beans of a certain type,
	 *      if no beans are found, it dispenses a bean of that type.
	 *
	 * @param  string $type     the type of bean you are looking for
	 * @param  string $sql      SQL query to find the desired bean, starting right after WHERE clause
	 * @param  array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return array
	 */
	public function findOrDispense( $type, $sql = NULL, $bindings = array() )
	{
		$foundBeans = $this->find( $type, $sql, $bindings );
		if ( empty( $foundBeans ) ) {
			return array( $this->redbean->dispense( $type ) );
		} else {
			return $foundBeans;
		}
	}
	/**
	 * Returns the bean identified by the RESTful path.
	 * For instance:
	 *
	 *        $user
	 *        /site/1/page/3
	 *
	 * returns page with ID 3 in ownPage of site 1 in ownSite of
	 * $user bean.
	 *
	 * Works with shared lists as well:
	 *
	 *        $user
	 *        /site/1/page/3/shared-ad/4
	 *
	 * Note that this method will open all intermediate beans so you can
	 * attach access control rules to each bean in the path.
	 *
	 * @param RedBean_OODBBean $bean
	 * @param array            $steps  (an array representation of a REST path)
	 *
	 * @return RedBean_OODBBean
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function findByPath( $bean, $steps )
	{
		$numberOfSteps = count( $steps );
		if ( !$numberOfSteps ) return $bean;
		if ( $numberOfSteps % 2 ) {
			throw new RedBean_Exception_Security( 'Invalid path: needs 1 more element.' );
		}
		for ( $i = 0; $i < $numberOfSteps; $i += 2 ) {
			$steps[$i] = trim( $steps[$i] );
			if ( $steps[$i] === '' ) {
				throw new RedBean_Exception_Security( 'Cannot access list.' );
			}
			if ( strpos( $steps[$i], 'shared-' ) === FALSE ) {
				$listName = 'own' . ucfirst( $steps[$i] );
				$listType = $this->toolbox->getWriter()->esc( $steps[$i] );
			} else {
				$listName = 'shared' . ucfirst( substr( $steps[$i], 7 ) );
				$listType = $this->toolbox->getWriter()->esc( substr( $steps[$i], 7 ) );
			}
			$list = $bean->withCondition( " {$listType}.id = ? ", array( $steps[$i + 1] ) )->$listName;
			if ( !isset( $list[$steps[$i + 1]] ) ) {
				throw new RedBean_Exception_Security( 'Cannot access bean.' );
			}
			$bean = $list[$steps[$i + 1]];
		}
		return $bean;
	}
}
class RedBean_Facade
{
	/**
	 * RedBeanPHP version constant.
	 */
	const C_REDBEANPHP_VERSION = '3.5';
	
	/**
	 * @var boolean
	 */
	private static $strictType = TRUE;
	/**
	 * @var array
	 */
	public static $toolboxes = array();
	/**
	 * @var RedBean_ToolBox
	 */
	public static $toolbox;
	/**
	 * @var RedBean_OODB
	 */
	public static $redbean;
	/**
	 * @var RedBean_QueryWriter
	 */
	public static $writer;
	/**
	 * @var RedBean_Adapter_DBAdapter
	 */
	public static $adapter;
	/**
	 * @var RedBean_AssociationManager
	 */
	public static $associationManager;
	/**
	 * @var RedBean_AssociationManager_ExtAssociationManager
	 */
	public static $extAssocManager;
	/**
	 * @var RedBean_TagManager
	 */
	public static $tagManager;
	/**
	 * @var RedBean_DuplicationManager
	 */
	public static $duplicationManager;
	/**
	 * @var RedBean_LabelMaker
	 */
	public static $labelMaker;
	/**
	 * @var RedBean_Finder
	 */
	public static $finder;
	/**
	 * @var string
	 */
	public static $currentDB = '';
	/**
	 * @var RedBean_SQLHelper
	 */
	public static $f;
	
	/**
	 * @var array
	 */
	public static $plugins = array();
	/**
	 * Internal Query function, executes the desired query. Used by
	 * all facade query functions. This keeps things DRY.
	 *
	 * @throws RedBean_Exception_SQL
	 *
	 * @param string $method   desired query method (i.e. 'cell', 'col', 'exec' etc..)
	 * @param string $sql      the sql you want to execute
	 * @param array  $bindings array of values to be bound to query statement
	 *
	 * @return array
	 */
	private static function query( $method, $sql, $bindings )
	{
		if ( !self::$redbean->isFrozen() ) {
			try {
				$rs = RedBean_Facade::$adapter->$method( $sql, $bindings );
			} catch ( RedBean_Exception_SQL $exception ) {
				if ( self::$writer->sqlStateIn( $exception->getSQLState(),
					array(
						RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_COLUMN,
						RedBean_QueryWriter::C_SQLSTATE_NO_SUCH_TABLE )
					)
				) {
					return ( $method === 'getCell' ) ? NULL : array();
				} else {
					throw $exception;
				}
			}
			return $rs;
		} else {
			return RedBean_Facade::$adapter->$method( $sql, $bindings );
		}
	}
	/**
	 * Returns the RedBeanPHP version string.
	 * The RedBeanPHP version string always has the same format "X.Y"
	 * where X is the major version number and Y is the minor version number.
	 * Point releases are not mentioned in the version string.
	 * 
	 * @return string
	 */
	public static function getVersion()
	{
		return self::C_REDBEANPHP_VERSION;
	}
	
	/**
	 * Turns an array (post/request array) into a collection of beans.
	 * Handy for turning forms into bean structures that can be stored with a
	 * single call.
	 *
	 * Typical usage:
	 *
	 * $struct = R::graph($_POST);
	 * R::store($struct);
	 *
	 * Example of a valid array:
	 *
	 *    $form = array(
	 *        'type' => 'order',
	 *        'ownProduct' => array(
	 *            array('id' => 171, 'type' => 'product'),
	 *        ),
	 *        'ownCustomer' => array(
	 *            array('type' => 'customer', 'name' => 'Bill')
	 *        ),
	 *        'sharedCoupon' => array(
	 *            array('type' => 'coupon', 'name' => '123'),
	 *            array('type' => 'coupon', 'id' => 3)
	 *        )
	 *    );
	 *
	 * Each entry in the array will become a property of the bean.
	 * The array needs to have a type-field indicating the type of bean it is
	 * going to be. The array can have nested arrays. A nested array has to be
	 * named conform the bean-relation conventions, i.e. ownPage/sharedPage
	 * each entry in the nested array represents another bean.
	 *
	 * @param array   $array       array to be turned into a bean collection
	 * @param boolean $filterEmpty whether you want to exclude empty beans
	 *
	 * @return array
	 *
	 * @throws RedBean_Exception_Security
	 */
	public static function graph( $array, $filterEmpty = FALSE ) 
	{ 
		$c = new RedBean_Plugin_Cooker;
		$c->setToolbox( self::$toolbox );
		return $c->graph( $array, $filterEmpty);
	}
	
	/**
	 * Logs queries beginning with CREATE or ALTER to file (TimeLine).
	 * Attaches a listener to the adapter to monitor for schema altering queries.
	 * 
	 * @param string $filename destination file
	 * 
	 * @return void
	 */
	public static function log($filename) 
	{ 
		$tl = new RedBean_Plugin_TimeLine($filename); 
		self::$adapter->addEventListener('sql_exec', $tl);	
	}
	/**
	 * Kickstarts redbean for you. This method should be called before you start using
	 * RedBean. The Setup() method can be called without any arguments, in this case it will
	 * try to create a SQLite database in /tmp called red.db (this only works on UNIX-like systems).
	 *
	 * @param string  $dsn      Database connection string
	 * @param string  $username Username for database
	 * @param string  $password Password for database
	 * @param boolean $frozen   TRUE if you want to setup in frozen mode
	 *
	 * @return RedBean_ToolBox
	 */
	public static function setup( $dsn = NULL, $username = NULL, $password = NULL, $frozen = FALSE )
	{
		if ( is_null( $dsn ) ) {
			$dsn = 'sqlite:/' . sys_get_temp_dir() . '/red.db';
		}
		self::addDatabase( 'default', $dsn, $username, $password, $frozen );
		self::selectDatabase( 'default' );
		return self::$toolbox;
	}
	/**
	 * Starts a transaction within a closure (or other valid callback).
	 * If an Exception is thrown inside, the operation is automatically rolled back.
	 * If no Exception happens, it commits automatically.
	 * It also supports (simulated) nested transactions (that is useful when
	 * you have many methods that needs transactions but are unaware of
	 * each other).
	 * ex:
	 *        $from = 1;
	 *        $to = 2;
	 *        $amount = 300;
	 *
	 *        R::transaction(function() use($from, $to, $amount)
	 *        {
	 *            $accountFrom = R::load('account', $from);
	 *            $accountTo = R::load('account', $to);
	 *
	 *            $accountFrom->money -= $amount;
	 *            $accountTo->money += $amount;
	 *
	 *            R::store($accountFrom);
	 *            R::store($accountTo);
	 *      });
	 *
	 * @param callable $callback Closure (or other callable) with the transaction logic
	 *
	 * @throws RedBean_Exception_Security
	 *
	 * @return mixed
	 *
	 */
	public static function transaction( $callback )
	{
		if ( !is_callable( $callback ) ) {
			throw new RedBean_Exception_Security( 'R::transaction needs a valid callback.' );
		}
		static $depth = 0;
		$result = null;
		try {
			if ( $depth == 0 ) {
				self::begin();
			}
			$depth++;
			$result = call_user_func( $callback ); //maintain 5.2 compatibility
			$depth--;
			if ( $depth == 0 ) {
				self::commit();
			}
		} catch ( Exception $exception ) {
			$depth--;
			if ( $depth == 0 ) {
				self::rollback();
			}
			throw $exception;
		}
		return $result;
	}
	/**
	 * Adds a database to the facade, afterwards you can select the database using
	 * selectDatabase($key), where $key is the name you assigned to this database.
	 * 
	 * Usage:
	 * 
	 * R::addDatabase( 'database-1', 'sqlite:/tmp/db1.txt' );
	 * R::selectDatabase( 'database-1' ); //to select database again
	 * 
	 * This method allows you to dynamically add (and select) new databases
	 * to the facade. Adding a database with the same key as an older database
	 * will cause this entry to be overwritten.
	 * 
	 * @param string      $key    ID for the database
	 * @param string      $dsn    DSN for the database
	 * @param string      $user   User for connection
	 * @param NULL|string $pass   Password for connection
	 * @param bool        $frozen Whether this database is frozen or not
	 *
	 * @return void
	 */
	public static function addDatabase( $key, $dsn, $user = NULL, $pass = NULL, $frozen = FALSE, $autoSetEncoding = TRUE )
	{
		self::$toolboxes[$key] = RedBean_Setup::kickstart( $dsn, $user, $pass, $frozen, $autoSetEncoding );
	}
	/**
	 * Selects a different database for the Facade to work with.
	 * If you use the R::setup() you don't need this method. This method is meant
	 * for multiple database setups. This method selects the database identified by the
	 * database ID ($key). Use addDatabase() to add a new database, which in turn
	 * can be selected using selectDatabase(). If you use R::setup(), the resulting
	 * database will be stored under key 'default', to switch (back) to this database
	 * use R::selectDatabase( 'default' ). This method returns TRUE if the database has been
	 * switched and FALSE otherwise (for instance if you already using the specified database).
	 *
	 * @param  string $key Key of the database to select
	 *
	 * @return boolean
	 */
	public static function selectDatabase( $key )
	{
		if ( self::$currentDB === $key ) {
			return FALSE;
		}
		self::configureFacadeWithToolbox( self::$toolboxes[$key] );
		self::$currentDB = $key;
		return TRUE;
	}
	/**
	 * Toggles DEBUG mode.
	 * In Debug mode all SQL that happens under the hood will
	 * be printed to the screen or logged by provided logger.
	 * If no database connection has been configured using R::setup() or
	 * R::selectDatabase() this method will throw an exception.
	 *
	 * @param boolean        $tf
	 * @param RedBean_Logger $logger
	 *
	 * @throws RedBean_Exception_Security
	 */
	public static function debug( $tf = TRUE, $logger = NULL )
	{
		if ( !$logger ) {
			$logger = new RedBean_Logger_Default;
		}
		if ( !isset( self::$adapter ) ) {
			throw new RedBean_Exception_Security( 'Use R::setup() first.' );
		}
		self::$adapter->getDatabase()->setDebugMode( $tf, $logger );
	}
	/**
	* Inspects the database schema. If you pass the type of a bean this
	* method will return the fields of its table in the database.
	* The keys of this array will be the field names and the values will be
	* the column types used to store their values.
	* If no type is passed, this method returns a list of all tables in the database.
	*
	* @param string $type Type of bean (i.e. table) you want to inspect
	*
	* @return array
	*/
	public static function inspect( $type = NULL )
	{
		return ($type === NULL) ? self::$writer->getTables() : self::$writer->getColumns( $type );
	}
	/**
	 * Stores a bean in the database. This method takes a
	 * RedBean_OODBBean Bean Object $bean and stores it
	 * in the database. If the database schema is not compatible
	 * with this bean and RedBean runs in fluid mode the schema
	 * will be altered to store the bean correctly.
	 * If the database schema is not compatible with this bean and
	 * RedBean runs in frozen mode it will throw an exception.
	 * This function returns the primary key ID of the inserted
	 * bean.
	 * 
	 * The return value is an integer if possible. If it is not possible to
	 * represent the value as an integer a string will be returned.
	 *
	 * @param RedBean_OODBBean|RedBean_SimpleModel $bean bean to store
	 *
	 * @return integer|string
	 *
	 * @throws RedBean_Exception_Security
	 */
	public static function store( $bean )
	{
		return self::$redbean->store( $bean );
	}
	/**
	 * Toggles fluid or frozen mode. In fluid mode the database
	 * structure is adjusted to accomodate your objects. In frozen mode
	 * this is not the case.
	 *
	 * You can also pass an array containing a selection of frozen types.
	 * Let's call this chilly mode, it's just like fluid mode except that
	 * certain types (i.e. tables) aren't touched.
	 *
	 * @param boolean|array $trueFalse
	 */
	public static function freeze( $tf = TRUE )
	{
		self::$redbean->freeze( $tf );
	}
	/**
	 * Loads multiple types of beans with the same ID.
	 * This might look like a strange method, however it can be useful
	 * for loading a one-to-one relation.
	 *
	 * Usage:
	 * list($author, $bio) = R::load('author, bio', $id);
	 *
	 * @param string|array $types
	 * @param mixed        $id
	 *
	 * @return RedBean_OODBBean
	 */
	public static function loadMulti( $types, $id )
	{
		if ( is_string( $types ) ) {
			$types = explode( ',', $types );
		}
		if ( !is_array( $types ) ) {
			return array();
		}
		foreach ( $types as $k => $typeItem ) {
			$types[$k] = self::$redbean->load( $typeItem, $id );
		}
		return $types;
	}
	/**
	 * Loads a bean from the object database.
	 * It searches for a RedBean_OODBBean Bean Object in the
	 * database. It does not matter how this bean has been stored.
	 * RedBean uses the primary key ID $id and the string $type
	 * to find the bean. The $type specifies what kind of bean you
	 * are looking for; this is the same type as used with the
	 * dispense() function. If RedBean finds the bean it will return
	 * the RedBean_OODB Bean object; if it cannot find the bean
	 * RedBean will return a new bean of type $type and with
	 * primary key ID 0. In the latter case it acts basically the
	 * same as dispense().
	 *
	 * Important note:
	 * If the bean cannot be found in the database a new bean of
	 * the specified type will be generated and returned.
	 *
	 * @param string  $type type of bean you want to load
	 * @param integer $id   ID of the bean you want to load
	 *
	 * @throws RedBean_Exception_SQL
	 *
	 * @return RedBean_OODBBean
	 */
	public static function load( $type, $id )
	{
		return self::$redbean->load( $type, $id );
	}
	/**
	 * Removes a bean from the database.
	 * This function will remove the specified RedBean_OODBBean
	 * Bean Object from the database.
	 *
	 * @param RedBean_OODBBean|RedBean_SimpleModel $bean bean you want to remove from database
	 *
	 * @return void
	 *
	 * @throws RedBean_Exception_Security
	 */
	public static function trash( $bean )
	{
		self::$redbean->trash( $bean );
	}
	/**
	 * Dispenses a new RedBean OODB Bean for use with
	 * the rest of the methods.
	 *
	 * @param string  $type   type
	 * @param integer $number number of beans to dispense
	 *
	 * @return array|RedBean_OODBBean
	 *
	 * @throws RedBean_Exception_Security
	 */
	public static function dispense( $type, $num = 1 )
	{
		if ( !preg_match( '/^[a-z0-9]+$/', $type ) && self::$strictType ) {
			throw new RedBean_Exception_Security( 'Invalid type: ' . $type );
		}
		return self::$redbean->dispense( $type, $num );
	}
	
	/**
	 * Takes a comma separated list of bean types
	 * and dispenses these beans. For each type in the list
	 * you can specify the number of beans to be dispensed.
	 * 
	 * Usage:
	 * 
	 * list($book, $page, $text) = R::dispenseAll('book,page,text');
	 * 
	 * This will dispense a book, a page and a text. This way you can
	 * quickly dispense beans of various types in just one line of code.
	 * 
	 * Usage:
	 * 
	 * list($book, $pages) = R::dispenseAll('book,page*100');
	 * 
	 * This returns an array with a book bean and then another array
	 * containing 100 page beans.
	 * 
	 * @param string $order a description of the desired dispense order using the syntax above
	 * 
	 * @return array
	 */
	public static function dispenseAll( $order )
	{
		$list = array();
		foreach( explode( ',', $order ) as $order ) {
			if ( strpos( $order, '*' ) !== false ) {
				list( $type, $amount ) = explode( '*', $order );
			} else {
				$type   = $order;
				$amount = 1;
			}
			$list[] = self::dispense( $type, $amount );
		}
		return $list;
	}
	/**
	 * Toggles strict bean type names.
	 * If set to TRUE (default) this will forbid the use of underscores and
	 * uppercase characters in bean type strings (R::dispense).
	 *
	 * @param boolean
	 */
	public static function setStrictTyping( $trueFalse )
	{
		self::$strictType = (bool) $trueFalse;
	}
	/**
	 * Convience method. Tries to find beans of a certain type,
	 * if no beans are found, it dispenses a bean of that type.
	 *
	 * @param  string $type     type of bean you are looking for
	 * @param  string $sql      SQL code for finding the bean
	 * @param  array  $bindings parameters to bind to SQL
	 *
	 * @return array
	 */
	public static function findOrDispense( $type, $sql = NULL, $bindings = array() )
	{
		return self::$finder->findOrDispense( $type, $sql, $bindings );
	}
	/**
	 * Associates two Beans. This method will associate two beans with eachother.
	 * You can then get one of the beans by using the related() function and
	 * providing the other bean. You can also provide a base bean in the extra
	 * parameter. This base bean allows you to add extra information to the association
	 * record. Note that this is for advanced use only and the information will not
	 * be added to one of the beans, just to the association record.
	 * It's also possible to provide an array or JSON string as base bean. If you
	 * pass a scalar this function will interpret the base bean as having one
	 * property called 'extra' with the value of the scalar.
	 *
	 * @todo extract from facade
	 *
	 * @param RedBean_OODBBean $bean1            bean that will be part of the association
	 * @param RedBean_OODBBean $bean2            bean that will be part of the association
	 * @param mixed            $extra            bean, scalar, array or JSON providing extra data.
	 *
	 * @return mixed
	 */
	public static function associate( $beans1, $beans2, $extra = NULL )
	{
		if ( !$extra ) {
			return self::$associationManager->associate( $beans1, $beans2 );
		} else {
			return self::$extAssocManager->extAssociateSimple( $beans1, $beans2, $extra );
		}
	}
	/**
	 * Breaks the association between two beans.
	 * This functions breaks the association between a pair of beans. After
	 * calling this functions the beans will no longer be associated with
	 * eachother. Calling related() with either one of the beans will no longer
	 * return the other bean.
	 *
	 * @param RedBean_OODBBean $bean1 bean
	 * @param RedBean_OODBBean $bean2 bean
	 *
	 * @return mixed
	 */
	public static function unassociate( $beans1, $beans2, $fast = FALSE )
	{
		self::$associationManager->unassociate( $beans1, $beans2, $fast );
	}
	/**
	 * Returns all the beans associated with $bean.
	 * This method will return an array containing all the beans that have
	 * been associated once with the associate() function and are still
	 * associated with the bean specified. The type parameter indicates the
	 * type of beans you are looking for. You can also pass some extra SQL and
	 * values for that SQL to filter your results after fetching the
	 * related beans.
	 *
	 * Don't try to make use of subqueries, a subquery using IN() seems to
	 * be slower than two queries!
	 *
	 * Since 3.2, you can now also pass an array of beans instead just one
	 * bean as the first parameter.
	 *
	 * @param RedBean_OODBBean|array $bean     the bean you have, the reference bean
	 * @param string                 $type     the type of beans you want to search for
	 * @param string                 $sql      SQL snippet for extra filtering
	 * @param array                  $bindings values to be inserted in SQL slots
	 *
	 * @return array
	 */
	public static function related( $bean, $type, $sql = '', $bindings = array() )
	{
		return self::$associationManager->relatedSimple( $bean, $type, $sql, $bindings );
	}
	/**
	 * Counts the number of related beans in an N-M relation.
	 * Counts the number of beans of type $type that are related to $bean,
	 * using optional filtering SQL $sql with $bindings. This count will
	 * only search for N-M associated beans (works like countShared).
	 * The $bean->countShared() method is the preferred way to obtain this
	 * number.  
	 *
	 * @warning not a preferred method, use $bean->countShared if possible.
	 * 
	 * @param RedBean_OODBBean $bean     the bean you have, the reference bean
	 * @param string           $type     the type of bean you want to count
	 * @param string           $sql      SQL snippet for extra filtering
	 * @param array            $bindings values to be inserted in SQL slots
	 *
	 * @return integer
	 */
	public static function relatedCount( $bean, $type, $sql = NULL, $bindings = array() )
	{
		return self::$associationManager->relatedCount( $bean, $type, $sql, $bindings );
	}
	/**
	 * Returns only a single associated bean.
	 * This works just like R::related but returns a single bean. Which bean will be
	 * returned depends on the SQL snippet provided.
	 * For more details refer to R::related.
	 * 
	 * @warning not a preferred method, use $bean->shared if possible.
	 *
	 * @param RedBean_OODBBean $bean     the bean you have, the reference bean
	 * @param string           $type     type of bean you are searching for
	 * @param string           $sql      SQL for extra filtering
	 * @param array            $bindings values to be inserted in SQL slots
	 *
	 * @return RedBean_OODBBean
	 */
	public static function relatedOne( RedBean_OODBBean $bean, $type, $sql = NULL, $bindings = array() )
	{
		return self::$associationManager->relatedOne( $bean, $type, $sql, $bindings );
	}
	/**
	 * Returns only the last associated bean.
	 * This works just like R::related but returns a single bean, the last one.
	 * If the query result contains multiple beans, the last bean from this result set will be returned.
	 * For more details refer to R::related.
	 * 
	 * @warning not a preferred method, use $bean->shared if possible.
	 *
	 * @param RedBean_OODBBean $bean     bean provided
	 * @param string           $type     type of bean you are searching for
	 * @param string           $sql      SQL for extra filtering
	 * @param array            $bindings values to be inserted in SQL slots
	 *
	 * @return RedBean_OODBBean
	 */
	public static function relatedLast( RedBean_OODBBean $bean, $type, $sql = NULL, $bindings = array() )
	{
		return self::$associationManager->relatedLast( $bean, $type, $sql, $bindings );
	}
	/**
	 * Checks whether a pair of beans is related N-M. This function does not
	 * check whether the beans are related in N:1 way.
	 * The name may be bit confusing because two beans can be related in
	 * various ways. This method only checks for many-to-many relations, for other
	 * relations please use $bean->ownX where X is the type of the bean you are
	 * looking for.
	 * 
	 * @param RedBean_OODBBean $bean1 first bean
	 * @param RedBean_OODBBean $bean2 second bean
	 *
	 * @return boolean
	 */
	public static function areRelated( RedBean_OODBBean $bean1, RedBean_OODBBean $bean2 )
	{
		return self::$associationManager->areRelated( $bean1, $bean2 );
	}
	/**
	 * Clears all associated beans.
	 * Breaks all many-to-many associations of a bean and a specified type.
	 * Only breaks N-M relations.
	 * 
	 * @warning not a preferred method, use $bean->shared = array() if possible.
	 *
	 * @param RedBean_OODBBean $bean bean you wish to clear many-to-many relations for
	 * @param string           $type type of bean you wish to break associations with
	 *
	 * @return void
	 */
	public static function clearRelations( RedBean_OODBBean $bean, $type )
	{
		self::$associationManager->clearRelations( $bean, $type );
	}
	/**
	 * Finds a bean using a type and a where clause (SQL).
	 * As with most Query tools in RedBean you can provide values to
	 * be inserted in the SQL statement by populating the value
	 * array parameter; you can either use the question mark notation
	 * or the slot-notation (:keyname).
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return array
	 */
	public static function find( $type, $sql = NULL, $bindings = array() )
	{
		return self::$finder->find( $type, $sql, $bindings );
	}
	/**
	 * @see RedBean_Facade::find
	 *      The findAll() method differs from the find() method in that it does
	 *      not assume a WHERE-clause, so this is valid:
	 *
	 * R::findAll('person',' ORDER BY name DESC ');
	 *
	 * Your SQL does not have to start with a valid WHERE-clause condition.
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return array
	 */
	public static function findAll( $type, $sql = NULL, $bindings = array() )
	{
		return self::$finder->find( $type, $sql, $bindings );
	}
	/**
	 * @see RedBean_Facade::find
	 * The variation also exports the beans (i.e. it returns arrays).
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return array
	 */
	public static function findAndExport( $type, $sql = NULL, $bindings = array() )
	{
		return self::$finder->findAndExport( $type, $sql, $bindings );
	}
	/**
	 * @see RedBean_Facade::find
	 * This variation returns the first bean only.
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return RedBean_OODBBean
	 */
	public static function findOne( $type, $sql = NULL, $bindings = array() )
	{
		return self::$finder->findOne( $type, $sql, $bindings );
	}
	/**
	 * @see RedBean_Facade::find
	 * This variation returns the last bean only.
	 *
	 * @param string $type     type   the type of bean you are looking for
	 * @param string $sql      sql    SQL query to find the desired bean, starting right after WHERE clause
	 * @param array  $bindings values array of values to be bound to parameters in query
	 *
	 * @return RedBean_OODBBean
	 */
	public static function findLast( $type, $sql = NULL, $bindings = array() )
	{
		return self::$finder->findLast( $type, $sql, $bindings );
	}
	/**
	 * Returns an array of beans. Pass a type and a series of ids and
	 * this method will bring you the corresponding beans.
	 *
	 * important note: Because this method loads beans using the load()
	 * function (but faster) it will return empty beans with ID 0 for
	 * every bean that could not be located. The resulting beans will have the
	 * passed IDs as their keys.
	 *
	 * @param string $type type of beans
	 * @param array  $ids  ids to load
	 *
	 * @return array
	 */
	public static function batch( $type, $ids )
	{
		return self::$redbean->batch( $type, $ids );
	}
	
	/**
	 * @see RedBean_Facade::batch
	 * 
	 * Alias for batch(). Batch method is older but since we added so-called *All
	 * methods like storeAll, trashAll, dispenseAll and findAll it seemed logical to
	 * improve the consistency of the Facade API and also add an alias for batch() called
	 * loadAll.
	 * 
	 * @param string $type type of beans
	 * @param array  $ids  ids to load
	 *
	 * @return array
	 */
	public static function loadAll( $type, $ids ) 
	{
		return self::$redbean->batch( $type, $ids );
	}
	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return integer
	 */
	public static function exec( $sql, $bindings = array() )
	{
		return self::query( 'exec', $sql, $bindings );
	}
	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return array
	 */
	public static function getAll( $sql, $bindings = array() )
	{
		return self::query( 'get', $sql, $bindings );
	}
	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return string
	 */
	public static function getCell( $sql, $bindings = array() )
	{
		return self::query( 'getCell', $sql, $bindings );
	}
	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return array
	 */
	public static function getRow( $sql, $bindings = array() )
	{
		return self::query( 'getRow', $sql, $bindings );
	}
	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return array
	 */
	public static function getCol( $sql, $bindings = array() )
	{
		return self::query( 'getCol', $sql, $bindings );
	}
	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 * Results will be returned as an associative array. The first
	 * column in the select clause will be used for the keys in this array and
	 * the second column will be used for the values. If only one column is
	 * selected in the query, both key and value of the array will have the
	 * value of this field for each row.
	 *
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return array
	 */
	public static function getAssoc( $sql, $bindings = array() )
	{
		return self::query( 'getAssoc', $sql, $bindings );
	}
	
	/**
	 * Convenience function to execute Queries directly.
	 * Executes SQL.
	 * Results will be returned as an associative array indexed by the first
	 * column in the select.
	 * 
	 * @param string $sql       sql    SQL query to execute
	 * @param array  $bindings  values a list of values to be bound to query parameters
	 *
	 * @return array
	 */
	public static function getAssocRow( $sql, $bindings = array() )
	{
		return self::query( 'getAssocRow', $sql, $bindings );
	}
	/**
	 * Makes a copy of a bean. This method makes a deep copy
	 * of the bean.The copy will have the following features.
	 * - All beans in own-lists will be duplicated as well
	 * - All references to shared beans will be copied but not the shared beans themselves
	 * - All references to parent objects (_id fields) will be copied but not the parents themselves
	 * In most cases this is the desired scenario for copying beans.
	 * This function uses a trail-array to prevent infinite recursion, if a recursive bean is found
	 * (i.e. one that already has been processed) the ID of the bean will be returned.
	 * This should not happen though.
	 *
	 * Note:
	 * This function does a reflectional database query so it may be slow.
	 *
	 * @param RedBean_OODBBean $bean  bean to be copied
	 * @param array            $trail for internal usage, pass array()
	 * @param boolean          $pid   for internal usage
	 *
	 * @return array
	 */
	public static function dup( $bean, $trail = array(), $pid = FALSE, $filters = array() )
	{
		self::$duplicationManager->setFilters( $filters );
		return self::$duplicationManager->dup( $bean, $trail, $pid );
	}
	/**
	 * Exports a collection of beans. Handy for XML/JSON exports with a
	 * Javascript framework like Dojo or ExtJS.
	 * What will be exported:
	 * - contents of the bean
	 * - all own bean lists (recursively)
	 * - all shared beans (not THEIR own lists)
	 *
	 * @param    array|RedBean_OODBBean $beans   beans to be exported
	 * @param    boolean                $parents whether you want parent beans to be exported
	 * @param   array                   $filters whitelist of types
	 *
	 * @return    array
	 */
	public static function exportAll( $beans, $parents = FALSE, $filters = array() )
	{
		return self::$duplicationManager->exportAll( $beans, $parents, $filters );
	}
	/**
	 * @deprecated
	 * Given two beans and a property this method will
	 * swap the values of the property in the beans.
	 *
	 * @param array  $beans    beans to swap property values of
	 * @param string $property property whose value you want to swap
	 *
	 * @return void
	 */
	public static function swap( $beans, $property )
	{
		self::$associationManager->swap( $beans, $property );
	}
	/**
	 * Converts a series of rows to beans.
	 * This method converts a series of rows to beans.
	 * The type of the desired output beans can be specified in the
	 * first parameter. The second parameter is meant for the database
	 * result rows.
	 *
	 * @param string $type type of beans to produce
	 * @param array  $rows must contain an array of array
	 *
	 * @return array
	 */
	public static function convertToBeans( $type, $rows )
	{
		return self::$redbean->convertToBeans( $type, $rows );
	}
	/**
	 * Part of RedBeanPHP Tagging API.
	 * Tests whether a bean has been associated with one ore more
	 * of the listed tags. If the third parameter is TRUE this method
	 * will return TRUE only if all tags that have been specified are indeed
	 * associated with the given bean, otherwise FALSE.
	 * If the third parameter is FALSE this
	 * method will return TRUE if one of the tags matches, FALSE if none
	 * match.
	 *
	 * @param  RedBean_OODBBean $bean bean to check for tags
	 * @param  array            $tags list of tags
	 * @param  boolean          $all  whether they must all match or just some
	 *
	 * @return boolean
	 */
	public static function hasTag( $bean, $tags, $all = FALSE )
	{
		return self::$tagManager->hasTag( $bean, $tags, $all );
	}
	/**
	 * Part of RedBeanPHP Tagging API.
	 * Removes all specified tags from the bean. The tags specified in
	 * the second parameter will no longer be associated with the bean.
	 *
	 * @param  RedBean_OODBBean $bean    tagged bean
	 * @param  array            $tagList list of tags (names)
	 *
	 * @return void
	 */
	public static function untag( $bean, $tagList )
	{
		self::$tagManager->untag( $bean, $tagList );
	}
	/**
	 * Part of RedBeanPHP Tagging API.
	 * Tags a bean or returns tags associated with a bean.
	 * If $tagList is NULL or omitted this method will return a
	 * comma separated list of tags associated with the bean provided.
	 * If $tagList is a comma separated list (string) of tags all tags will
	 * be associated with the bean.
	 * You may also pass an array instead of a string.
	 *
	 * @param RedBean_OODBBean $bean    bean
	 * @param mixed            $tagList tags
	 *
	 * @return string
	 */
	public static function tag( RedBean_OODBBean $bean, $tagList = NULL )
	{
		return self::$tagManager->tag( $bean, $tagList );
	}
	/**
	 * Part of RedBeanPHP Tagging API.
	 * Adds tags to a bean.
	 * If $tagList is a comma separated list of tags all tags will
	 * be associated with the bean.
	 * You may also pass an array instead of a string.
	 *
	 * @param RedBean_OODBBean $bean    bean
	 * @param array            $tagList list of tags to add to bean
	 *
	 * @return void
	 */
	public static function addTags( RedBean_OODBBean $bean, $tagList )
	{
		self::$tagManager->addTags( $bean, $tagList );
	}
	/**
	 * Part of RedBeanPHP Tagging API.
	 * Returns all beans that have been tagged with one of the tags given.
	 *
	 * @param string $beanType type of bean you are looking for
	 * @param array  $tagList  list of tags to match
	 *
	 * @return array
	 */
	public static function tagged( $beanType, $tagList )
	{
		return self::$tagManager->tagged( $beanType, $tagList );
	}
	/**
	 * Part of RedBeanPHP Tagging API.
	 * Returns all beans that have been tagged with ALL of the tags given.
	 *
	 * @param string $beanType type of bean you are looking for
	 * @param array  $tagList  list of tags to match
	 *
	 * @return array
	 */
	public static function taggedAll( $beanType, $tagList )
	{
		return self::$tagManager->taggedAll( $beanType, $tagList );
	}
	/**
	 * Wipes all beans of type $beanType.
	 *
	 * @param string $beanType type of bean you want to destroy entirely
	 *
	 * @return boolean
	 */
	public static function wipe( $beanType )
	{
		return RedBean_Facade::$redbean->wipe( $beanType );
	}
	/**
	 * Counts the number of beans of type $type.
	 * This method accepts a second argument to modify the count-query.
	 * A third argument can be used to provide bindings for the SQL snippet.
	 *
	 * @param string $type     type of bean we are looking for
	 * @param string $addSQL   additional SQL snippet
	 * @param array  $bindings parameters to bind to SQL
	 *
	 * @return integer
	 *
	 * @throws RedBean_Exception_SQL
	 */
	public static function count( $type, $addSQL = '', $bindings = array() )
	{
		return RedBean_Facade::$redbean->count( $type, $addSQL, $bindings );
	}
	/**
	 * Configures the facade, want to have a new Writer? A new Object Database or a new
	 * Adapter and you want it on-the-fly? Use this method to hot-swap your facade with a new
	 * toolbox.
	 *
	 * @param RedBean_ToolBox $tb toolbox
	 *
	 * @return RedBean_ToolBox
	 */
	public static function configureFacadeWithToolbox( RedBean_ToolBox $tb )
	{
		$oldTools                 = self::$toolbox;
		self::$toolbox            = $tb;
		self::$writer             = self::$toolbox->getWriter();
		self::$adapter            = self::$toolbox->getDatabaseAdapter();
		self::$redbean            = self::$toolbox->getRedBean();
		self::$finder             = new RedBean_Finder( self::$toolbox );
		self::$associationManager = new RedBean_AssociationManager( self::$toolbox );
		self::$redbean->setAssociationManager( self::$associationManager );
		self::$labelMaker         = new RedBean_LabelMaker( self::$toolbox );
		self::$extAssocManager    = new RedBean_AssociationManager_ExtAssociationManager( self::$toolbox );
		$helper                   = new RedBean_ModelHelper();
		$helper->attachEventListeners( self::$redbean );
		self::$associationManager->addEventListener( 'delete', $helper );
		self::$duplicationManager = new RedBean_DuplicationManager( self::$toolbox );
		self::$tagManager         = new RedBean_TagManager( self::$toolbox );
		self::$f                  = new RedBean_SQLHelper( self::$adapter );
		return $oldTools;
	}
	/**
	 * Facade Convience method for adapter transaction system.
	 * Begins a transaction.
	 *
	 * @return bool
	 */
	public static function begin()
	{
		if ( !self::$redbean->isFrozen() ) return FALSE;
		self::$adapter->startTransaction();
		return TRUE;
	}
	/**
	 * Facade Convience method for adapter transaction system.
	 * Commits a transaction.
	 *
	 * @return bool
	 */
	public static function commit()
	{
		if ( !self::$redbean->isFrozen() ) return FALSE;
		self::$adapter->commit();
		return TRUE;
	}
	/**
	 * Facade Convience method for adapter transaction system.
	 * Rolls back a transaction.
	 *
	 * @return bool
	 */
	public static function rollback()
	{
		if ( !self::$redbean->isFrozen() ) return FALSE;
		self::$adapter->rollback();
		return TRUE;
	}
	/**
	 * Returns a list of columns. Format of this array:
	 * array( fieldname => type )
	 * Note that this method only works in fluid mode because it might be
	 * quite heavy on production servers!
	 *
	 * @param  string $table   name of the table (not type) you want to get columns of
	 *
	 * @return array
	 */
	public static function getColumns( $table )
	{
		return self::$writer->getColumns( $table );
	}
	/**
	 * Generates question mark slots for an array of values.
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	public static function genSlots( $array )
	{
		return self::$f->genSlots( $array );
	}
	/**
	 * Nukes the entire database.
	 * This will remove all schema structures from the database.
	 * Only works in fluid mode. Be careful with this method.
	 * 
	 * @warning dangerous method, will remove all tables, columns etc.
	 *
	 * @return void
	 */
	public static function nuke()
	{
		if ( !self::$redbean->isFrozen() ) {
			self::$writer->wipeAll();
		}
	}
	/**
	 * Sets a list of dependencies.
	 * A dependency list contains an entry for each dependent bean.
	 * A dependent bean will be removed if the relation with one of the
	 * dependencies gets broken.
	 *
	 * Example:
	 *
	 * array(
	 *    'page' => array('book', 'magazine')
	 * )
	 *
	 * A page will be removed if:
	 *
	 * unset($book->ownPage[$pageID]);
	 *
	 * or:
	 *
	 * unset($magazine->ownPage[$pageID]);
	 *
	 * but not if:
	 *
	 * unset($paper->ownPage[$pageID]);
	 *
	 * @param array $dep list of dependencies
	 *
	 * @return void
	 */
	public static function dependencies( $dep )
	{
		self::$redbean->setDepList( $dep );
	}
	/**
	 * Short hand function to store a set of beans at once, IDs will be
	 * returned as an array. For information please consult the R::store()
	 * function.
	 * A loop saver.
	 *
	 * @param array $beans list of beans to be stored
	 *
	 * @return array
	 */
	public static function storeAll( $beans )
	{
		$ids = array();
		foreach ( $beans as $bean ) {
			$ids[] = self::store( $bean );
		}
		return $ids;
	}
	/**
	 * Short hand function to trash a set of beans at once.
	 * For information please consult the R::trash() function.
	 * A loop saver.
	 *
	 * @param array $beans list of beans to be trashed
	 *
	 * @return void
	 */
	public static function trashAll( $beans )
	{
		foreach ( $beans as $bean ) {
			self::trash( $bean );
		}
	}
	/**
	 * Toggles Writer Cache.
	 * Turns the Writer Cache on or off. The Writer Cache is a simple
	 * query based caching system that may improve performance without the need
	 * for cache management. This caching system will cache non-modifying queries
	 * that are marked with special SQL comments. As soon as a non-marked query
	 * gets executed the cache will be flushed. Only non-modifying select queries
	 * have been marked therefore this mechanism is a rather safe way of caching, requiring
	 * no explicit flushes or reloads. Of course this does not apply if you intend to test
	 * or simulate concurrent querying.
	 * 
	 * @param boolean $yesNo TRUE to enable cache, FALSE to disable cache
	 * 
	 * @return void
	 */
	public static function useWriterCache( $yesNo )
	{
		self::getWriter()->setUseCache( $yesNo );
	}
	
	/**
	 * A label is a bean with only an id, type and name property.
	 * This function will dispense beans for all entries in the array. The
	 * values of the array will be assigned to the name property of each
	 * individual bean.
	 *
	 * @param string $type   type of beans you would like to have
	 * @param array  $labels list of labels, names for each bean
	 *
	 * @return array
	 */
	public static function dispenseLabels( $type, $labels )
	{
		return self::$labelMaker->dispenseLabels( $type, $labels );
	}
	
	/**
	 * Generates and returns an ENUM value. This is how RedBeanPHP handles ENUMs.
	 * Either returns a (newly created) bean respresenting the desired ENUM
	 * value or returns a list of all enums for the type.
	 * 
	 * To obtain (and add if necessary) an ENUM value:
	 * 
	 * $tea->flavour = R::enum( 'flavour:apple' );
	 * 
	 * Returns a bean of type 'flavour' with  name = apple.
	 * This will add a bean with property name (set to APPLE) to the database
	 * if it does not exist yet. 
	 * 
	 * To obtain all flavours:
	 * 
	 * R::enum('flavour');
	 * 
	 * To get a list of all flavour names:
	 * 
	 * R::gatherLabels( R::enum( 'flavour' ) );
	 * 
	 * @param string $enum either type or type-value
	 * 
	 * @return array|RedBean_OODBBean
	 */
	public static function enum( $enum ) 
	{
		return self::$labelMaker->enum( $enum );
	}
	/**
	 * Gathers labels from beans. This function loops through the beans,
	 * collects the values of the name properties of each individual bean
	 * and stores the names in a new array. The array then gets sorted using the
	 * default sort function of PHP (sort).
	 *
	 * @param array $beans list of beans to loop
	 *
	 * @return array
	 */
	public static function gatherLabels( $beans )
	{
		return self::$labelMaker->gatherLabels( $beans );
	}
	/**
	 * Closes the database connection.
	 *
	 * @return void
	 */
	public static function close()
	{
		if ( isset( self::$adapter ) ) {
			self::$adapter->close();
		}
	}
	/**
	 * Simple convenience function, returns ISO date formatted representation
	 * of $time.
	 *
	 * @param mixed $time UNIX timestamp
	 *
	 * @return string
	 */
	public static function isoDate( $time = NULL )
	{
		if ( !$time ) {
			$time = time();
		}
		return @date( 'Y-m-d', $time );
	}
	/**
	 * Simple convenience function, returns ISO date time
	 * formatted representation
	 * of $time.
	 *
	 * @param mixed $time UNIX timestamp
	 *
	 * @return string
	 */
	public static function isoDateTime( $time = NULL )
	{
		if ( !$time ) $time = time();
		return @date( 'Y-m-d H:i:s', $time );
	}
	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @param RedBean_Adapter $adapter
	 *
	 * @return void
	 */
	public static function setDatabaseAdapter( RedBean_Adapter $adapter )
	{
		self::$adapter = $adapter;
	}
	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @param RedBean_QueryWriter $writer
	 *
	 * @return void
	 */
	public static function setWriter( RedBean_QueryWriter $writer )
	{
		self::$writer = $writer;
	}
	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @param RedBean_OODB $redbean
	 */
	public static function setRedBean( RedBean_OODB $redbean )
	{
		self::$redbean = $redbean;
	}
	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @return RedBean_Adapter_DBAdapter
	 */
	public static function getDatabaseAdapter()
	{
		return self::$adapter;
	}
	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @return RedBean_QueryWriter
	 */
	public static function getWriter()
	{
		return self::$writer;
	}
	/**
	 * Optional accessor for neat code.
	 * Sets the database adapter you want to use.
	 *
	 * @return RedBean_OODB
	 */
	public static function getRedBean()
	{
		return self::$redbean;
	}
	/**
	 * Returns the toolbox currently used by the facade.
	 * To set the toolbox use R::setup() or R::configureFacadeWithToolbox().
	 * To create a toolbox use RedBean_Setup::kickstart(). Or create a manual
	 * toolbox using the RedBean_Toolbox class.
	 *
	 * @return RedBean_ToolBox
	 */
	public static function getToolBox()
	{
		return self::$toolbox;
	}
	/**
	 * Preloads certain properties for beans.
	 * Understands aliases.
	 *
	 * Usage: 
	 * 
	 * R::preload($books, 'author');
	 * 
	 * - preloads all the authors of all books, 
	 * saves you a query per for-each iteration
	 * 
	 * R::preload($books, array('coauthor'=>'author'));
	 * 
	 * - same but with alias
	 * 
	 * R::preload($texts,'page,page.book,page.book.author');
    * 
	 * - preloads all pages for the texts, the books and the authors
	 * 
	 * R::preload($texts,'page,*.book,*.author');
	 * 
	 * - same as above bit with short syntax (* means prefix with previous types)
	 *
	 * R::preload($p,'book,*.author,&.shelf');
	 * 
	 * - if author and shelf are on the same level use & instead of *.
	 * 
	 * The other way around is possible as well, to load child beans in own-lists or
	 * shared-lists use:
	 * 
	 * R::preload($books,'ownPage|page,sharedGenre|genre');
	 * 
	 * @param array        $beans beans beans to use as a reference for preloading
	 * @param array|string $types types to load, either string or array
	 *
	 * @return array
	 */
	public static function preload( $beans, $types, $closure = NULL )
	{
		return self::$redbean->preload( $beans, $types, $closure );
	}
	/**
	 * Alias for preload.
	 * Preloads certain properties for beans.
	 * Understands aliases.
	 * 
	 * @see RedBean_Facade::preload
	 *
	 * Usage: R::preload($books, array('coauthor'=>'author'));
	 *
	 * @param array        $beans   beans beans to use as a reference for preloading
	 * @param array|string $types   types to load, either string or array
	 * @param closure      $closure function to call
	 * 
	 * @return array
	 */
	public static function each( $beans, $types, $closure = NULL )
	{
		return self::preload( $beans, $types, $closure );
	}
	/**
	 * Facade method for RedBean_QueryWriter_AQueryWriter::renameAssociation()
	 *
	 * @param string|array $from
	 * @param string       $to
	 *
	 * @return void
	 */
	public static function renameAssociation( $from, $to = NULL )
	{
		RedBean_QueryWriter_AQueryWriter::renameAssociation( $from, $to );
	}
	
	/**
	 * Little helper method for Resty Bean Can server and others.
	 * Takes an array of beans and exports each bean.
	 * Unlike exportAll this method does not recurse into own lists
	 * and shared lists, the beans are exported as-is, only loaded lists
	 * are exported.
	 * 
	 * @param array $beans beans
	 * 
	 * @return array
	 */
	public static function beansToArray( $beans )
	{
		$list = array();
		foreach( $beans as $bean ) {
			$list[] = $bean->export();
		}
		return $list;
	}
	
	/**
	 * Dynamically extends the facade with a plugin.
	 * Using this method you can register your plugin with the facade and then
	 * use the plugin by invoking the name specified plugin name as a method on
	 * the facade.
	 * 
	 * Usage:
	 * 
	 * R::ext( 'makeTea', function() { ... }  );
	 * 
	 * Now you can use your makeTea plugin like this:
	 * 
	 * R::makeTea();
	 * 
	 * @param string   $pluginName name of the method to call the plugin
	 * @param callable $callable   a PHP callable
	 */
	public static function ext( $pluginName, $callable )
	{
		if ( !ctype_alnum( $pluginName ) ) {
			throw new RedBean_Exception( 'Plugin name may only contain alphanumeric characters.' );
		}
		self::$plugins[$pluginName] = $callable;
	}
	/**
	 * Call static for use with dynamic plugins. This magic method will
	 * intercept static calls and route them to the specified plugin.
	 *  
	 * @param string $pluginName name of the plugin
	 * @param array  $params     list of arguments to pass to plugin method
	 * 
	 * @return mixed
	 */
	public static function __callStatic( $pluginName, $params )
	{
		if ( !ctype_alnum( $pluginName) ) {
			throw new RedBean_Exception( 'Plugin name may only contain alphanumeric characters.' );
		}
		if ( !isset( self::$plugins[$pluginName] ) ) {
			throw new RedBean_Exception( 'Plugin \''.$pluginName.'\' does not exist, add this plugin using: R::ext(\''.$pluginName.'\')' );
		}
		return call_user_func_array( self::$plugins[$pluginName], $params );
	}
}
//Compatibility with PHP 5.2 and earlier
if ( !function_exists( 'lcfirst' ) ) {
	function lcfirst( $str ) { return (string) ( strtolower( substr( $str, 0, 1 ) ) . substr( $str, 1 ) ); }
}
interface RedBean_Plugin
{
}
;
class RedBean_Plugin_BeanCan implements RedBean_Plugin
{
	/**
	 * List of JSON RPC2 error code definitions.
	 */
	const C_JSONRPC2_PARSE_ERROR        = -32700;
	const C_JSONRPC2_INVALID_REQUEST    = -32600;
	const C_JSONRPC2_METHOD_NOT_FOUND   = -32601;
	const C_JSONRPC2_INVALID_PARAMETERS = -32602;
	const C_JSONRPC2_INTERNAL_ERROR     = -32603;
	const C_JSONRPC2_SPECIFIED_ERROR    = -32099;
	/**
	 * @var RedBean_ModelHelper
	 */
	private $modelHelper;
	/**
	 * @var array
	 */
	private $whitelist;
	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->modelHelper = new RedBean_ModelHelper;
	}
	/**
	 * Writes a response object for the client (JSON encoded). Internal method.
	 *
	 * @param mixed   $result       result
	 * @param integer $id           request ID
	 * @param integer $errorCode    error code from server
	 * @param string  $errorMessage error message from server
	 *
	 * @return string $json
	 */
	private function resp( $result = NULL, $id = NULL, $errorCode = '-32603', $errorMessage = 'Internal Error' )
	{
		$response = array( 'jsonrpc' => '2.0' );
		if ( !is_null( $id ) ) $response['id'] = $id;
		if ( $result ) {
			$response['result'] = $result;
		} else {
			$response['error'] = array(
				'code'    => $errorCode,
				'message' => $errorMessage
			);
		}
		return json_encode( $response );
	}
	/**
	 * Handles a JSON RPC 2 request to store a bean.
	 *
	 * @param string $id       request ID, identification for request
	 * @param string $beanType type of the bean you want to store
	 * @param array  $data     data array
	 *
	 * @return string
	 */
	private function store( $id, $beanType, $data )
	{
		if ( !isset( $data[0] ) ) {
			return $this->resp( NULL, $id, self::C_JSONRPC2_INVALID_PARAMETERS, 'First param needs to be Bean Object' );
		}
		$data = $data[0];
		if ( !isset( $data['id'] ) ) {
			$bean = RedBean_Facade::dispense( $beanType );
		} else {
			$bean = RedBean_Facade::load( $beanType, $data['id'] );
		}
		$bean->import( $data );
		$rid = RedBean_Facade::store( $bean );
		return $this->resp( $rid, $id );
	}
	/**
	 * Handles a JSON RPC 2 request to load a bean.
	 *
	 * @param string $id       request ID, identification for request
	 * @param string $beanType type of the bean you want to store
	 * @param array  $data     data array containing the ID of the bean to load
	 *
	 * @return string
	 */
	private function load( $id, $beanType, $data )
	{
		if ( !isset( $data[0] ) ) {
			return $this->resp( NULL, $id, self::C_JSONRPC2_INVALID_PARAMETERS, 'First param needs to be Bean ID' );
		}
		$bean = RedBean_Facade::load( $beanType, $data[0] );
		return $this->resp( $bean->export(), $id );
	}
	/**
	 * Handles a JSON RPC 2 request to trash a bean.
	 *
	 * @param string $id       request ID, identification for request
	 * @param string $beanType type of the bean you want to delete
	 * @param array  $data     data array
	 *
	 * @return string
	 */
	private function trash( $id, $beanType, $data )
	{
		if ( !isset( $data[0] ) ) {
			return $this->resp( NULL, $id, self::C_JSONRPC2_INVALID_PARAMETERS, 'First param needs to be Bean ID' );
		}
		$bean = RedBean_Facade::load( $beanType, $data[0] );
		RedBean_Facade::trash( $bean );
		return $this->resp( 'OK', $id );
	}
	/**
	 * Handles a JSON RPC 2 request to export a bean.
	 *
	 * @param string $id       request ID, identification for request
	 * @param string $beanType type of the bean you want to export
	 * @param array  $data     data array
	 *
	 * @return string
	 */
	private function export( $id, $beanType, $data )
	{
		if ( !isset( $data[0] ) ) {
			return $this->resp( NULL, $id, self::C_JSONRPC2_INVALID_PARAMETERS, 'First param needs to be Bean ID' );
		}
		$bean  = RedBean_Facade::load( $beanType, $data[0] );
		$array = RedBean_Facade::exportAll( array( $bean ), TRUE );
		return $this->resp( $array, $id );
	}
	/**
	 * Handles a JSON RPC 2 request to perform a custom operation on a bean.
	 *
	 * @param string $id       request ID, identification for request
	 * @param string $beanType type of the bean you want to store
	 * @param string $action   action you want to invoke on bean model
	 * @param array  $data     data array
	 *
	 * @return string
	 */
	private function custom( $id, $beanType, $action, $data )
	{
		$modelName = $this->modelHelper->getModelName( $beanType );
		if ( !class_exists( $modelName ) ) {
			return $this->resp( NULL, $id, self::C_JSONRPC2_METHOD_NOT_FOUND, 'No such bean in the can!' );
		}
		$beanModel = new $modelName;
		if ( !method_exists( $beanModel, $action ) ) {
			return $this->resp( NULL, $id, self::C_JSONRPC2_METHOD_NOT_FOUND, "Method not found in Bean: $beanType " );
		}
		return $this->resp( call_user_func_array( array( $beanModel, $action ), $data ), $id );
	}
	/**
	 * Extracts bean type, action identifier,
	 * data array and method name from json array.
	 *
	 * @param array $jsonArray JSON array containing the details
	 *
	 * @return array
	 */
	private function getDataFromJSON( $jsonArray )
	{
		$beanType = NULL;
		$action   = NULL;
		if ( !isset( $jsonArray['params'] ) ) {
			$data = array();
		} else {
			$data = $jsonArray['params'];
		}
		//Check method signature
		$method = explode( ':', trim( $jsonArray['method'] ) );
		if ( count( $method ) === 2 ) {
			//Collect Bean and Action
			$beanType = $method[0];
			$action   = $method[1];
		}
		return array( $beanType, $action, $data, $method );
	}
	/**
	 * Dispatches the JSON RPC request to one of the private methods.
	 *
	 * @param string $id       identification of request
	 * @param string $beanType type of the bean you wish to apply the action to
	 * @param string $action   action to apply
	 * @param array  $data     data array containing parameters or details
	 *
	 * @return array
	 */
	private function dispatch( $id, $beanType, $action, $data )
	{
		try {
			switch ( $action ) {
				case 'store':
					return $this->store( $id, $beanType, $data );
				case 'load':
					return $this->load( $id, $beanType, $data );
				case 'trash':
					return $this->trash( $id, $beanType, $data );
				case 'export':
					return $this->export( $id, $beanType, $data );
				default:
					return $this->custom( $id, $beanType, $action, $data );
			}
		} catch ( Exception $exception ) {
			return $this->resp( NULL, $id, self::C_JSONRPC2_SPECIFIED_ERROR, $exception->getCode() . '-' . $exception->getMessage() );
		}
	}
	/**
	 * Sets a whitelist with format: array('beantype'=>array('update','customMethod')) etc.
	 * or simply string 'all' (for backward compatibility).
	 *
	 * @param array|string $whitelist  a white list of beans and methods that should be accessible through the BeanCan Server.
	 *
	 * @return RedBean_Plugin_BeanCan
	 */
	public function setWhitelist( $whitelist )
	{
		$this->whitelist = $whitelist;
		return $this;
	}
	/**
	 * Processes a JSON object request.
	 * Second parameter can be a white list with format: array('beantype'=>array('update','customMethod')) etc.
	 * or simply string 'all' (for backward compatibility).
	 *
	 * @param array        $jsonObject JSON request object
	 * @param array|string $whitelist  a white list of beans and methods that should be accessible through the BeanCan Server.
	 *
	 * @return mixed $result result
	 */
	public function handleJSONRequest( $jsonString )
	{
		if ( !$jsonArray = json_decode( $jsonString, TRUE ) ) { //Decode JSON string
			return $this->resp( NULL, NULL, self::C_JSONRPC2_PARSE_ERROR, 'Cannot Parse JSON' );
		}
		if ( !isset( $jsonArray['jsonrpc'] ) ) {
			return $this->resp( NULL, NULL, self::C_JSONRPC2_INVALID_REQUEST, 'No RPC version' );
		}
		if ( ( $jsonArray['jsonrpc'] != '2.0' ) ) {
			return $this->resp( NULL, NULL, self::C_JSONRPC2_INVALID_REQUEST, 'Incompatible RPC Version' );
		}
		if ( !isset( $jsonArray['id'] ) ) { //DO we have an ID to identify this request?
			return $this->resp( NULL, NULL, self::C_JSONRPC2_INVALID_REQUEST, 'No ID' );
		}
		$id = $jsonArray['id']; //Fetch the request Identification String.
		if ( !isset( $jsonArray['method'] ) ) { //Do we have a method?
			return $this->resp( NULL, $id, self::C_JSONRPC2_INVALID_REQUEST, 'No method' );
		}
		list( $beanType, $action, $data, $method ) = $this->getDataFromJSON( $jsonArray ); //Do we have params?
		if ( count( $method ) !== 2 ) {
			return $this->resp( NULL, $id, self::C_JSONRPC2_INVALID_REQUEST, 'Invalid method signature. Use: BEAN:ACTION' );
		}
		if ( !( $this->whitelist === 'all' || ( isset( $this->whitelist[$beanType] ) && in_array( $action, $this->whitelist[$beanType] ) ) ) ) {
			return $this->resp( NULL, $id, self::C_JSONRPC2_INVALID_REQUEST, 'This bean is not available. Set whitelist to "all" or add to whitelist.' );
		}
		if ( preg_match( '/\W/', $beanType ) ) { //May not contain anything other than ALPHA NUMERIC chars and _
			return $this->resp( NULL, $id, self::C_JSONRPC2_INVALID_REQUEST, 'Invalid Bean Type String' );
		}
		if ( preg_match( '/\W/', $action ) ) {
			return $this->resp( NULL, $id, self::C_JSONRPC2_INVALID_REQUEST, 'Invalid Action String' );
		}
		return $this->dispatch( $id, $beanType, $action, $data );
	}
	/**
	 * Support for RESTFul GET-requests.
	 * Only supports very BASIC REST requests, for more functionality please use
	 * the JSON-RPC 2 interface.
	 *
	 * @param string $pathToResource RESTFul path to resource
	 *
	 * @return string $json a JSON encoded response ready for sending to client
	 */
	public function handleRESTGetRequest( $pathToResource )
	{
		if ( !is_string( $pathToResource ) ) {
			return $this->resp( NULL, 0, self::C_JSONRPC2_SPECIFIED_ERROR, 'IR' );
		}
		$resourceInfo = explode( '/', $pathToResource );
		$type = $resourceInfo[0];
		try {
			if ( count( $resourceInfo ) < 2 ) {
				return $this->resp( RedBean_Facade::findAndExport( $type ) );
			} else {
				$id = (int) $resourceInfo[1];
				return $this->resp( RedBean_Facade::load( $type, $id )->export(), $id );
			}
		} catch ( Exception $exception ) {
			return $this->resp( NULL, 0, self::C_JSONRPC2_SPECIFIED_ERROR );
		}
	}
}
class RedBean_Plugin_BeanCanResty implements RedBean_Plugin
{
	/**
	 * HTTP Error codes used by Resty BeanCan Server.
	 */
	const C_HTTP_BAD_REQUEST           = 400;
	const C_HTTP_FORBIDDEN_REQUEST     = 403;
	const C_HTTP_NOT_FOUND             = 404;
	const C_HTTP_INTERNAL_SERVER_ERROR = 500;
	/**
	 * @var RedBean_OODB
	 */
	private $oodb;
	/**
	 * @var RedBean_ToolBox
	 */
	private $toolbox;
	/**
	 * @var array
	 */
	private $whitelist;
	/**
	 * @var array
	 */
	private $sqlSnippets = array();
	/**
	 * @var string
	 */
	private $method;
	/**
	 * @var array
	 */
	private $payload = array();
	/**
	 * @var string
	 */
	private $uri;
	/**
	 * Reference bean, the bean used to find other beans in a REST request.
	 * All beans should be reachable given this root bean.
	 *
	 * @var RedBean_OODBBean
	 */
	private $root;
	/**
	 * Name of the currently selected list.
	 *
	 * @var string
	 */
	private $list;
	/**
	 * @var RedBean_OODBBean
	 */
	private $bean;
	/**
	 * Name of the type of the currently selected list.
	 *
	 * @var string
	 */
	private $type;
	/**
	 * Type of the currently selected bean.
	 *
	 * @var string
	 */
	private $beanType;
	/**
	 * List of bindings for the SQL snippet.
	 *
	 * @var array
	 */
	private $sqlBindings;
	/**
	 * An SQL snippet to sort or modify the contents of a list.
	 *
	 * @var string
	 */
	private $sqlSnippet;
	/**
	 * Writes a response object for the client (JSON encoded). Internal method.
	 * Returns a pseudo HTTP/REST response. You can refine or alter this response
	 * before sending it to the client.
	 *
	 * @param mixed   $result       result
	 * @param integer $errorCode    error code from server
	 * @param string  $errorMessage error message from server
	 *
	 * @return array $response
	 */
	private function resp( $result = NULL, $errorCode = '500', $errorMessage = 'Internal Error' )
	{
		$response = array( 'red-resty' => '1.0' );
		if ( $result !== NULL ) {
			$response['result'] = $result;
		} else {
			$response['error'] = array( 'code' => $errorCode, 'message' => $errorMessage );
		}
		return $response;
	}
	/**
	 * Handles a REST GET request.
	 * Returns the selected bean using the basic export method of the bean.
	 * Returns an array formatted according to RedBeanPHP REST BeanCan
	 * formatting specifications.
	 *
	 * @return array
	 */
	private function get()
	{
		return $this->resp( $this->bean->export() );
	}
	/**
	 * Handles a REST PUT request.
	 * Updates the bean described in the payload array in the database.
	 * Returns an array formatted according to RedBeanPHP REST BeanCan
	 * formatting specifications.
	 *
	 * Format of the payload array:
	 *
	 * array(
	 *        'bean' => array( property => value pairs )
	 * )
	 *
	 * @return array
	 */
	private function put()
	{
		if ( !isset( $this->payload['bean'] ) ) {
			return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'Missing parameter \'bean\'.' );
		}
		if ( !is_array( $this->payload['bean'] ) ) {
			return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'Parameter \'bean\' must be object/array.' );
		}
		foreach ( $this->payload['bean'] as $key => $value ) {
			if ( !is_string( $key ) || !is_string( $value ) ) {
				return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'Object "bean" invalid.' );
			}
		}
		$this->bean->import( $this->payload['bean'] );
		$this->oodb->store( $this->bean );
		$this->bean = $this->oodb->load( $this->bean->getMeta( 'type' ), $this->bean->id );
		return $this->resp( $this->bean->export() );
	}
	/**
	 * Handles a REST POST request.
	 * Stores the bean described in the payload array in the database.
	 * Returns an array formatted according to RedBeanPHP REST BeanCan
	 * formatting specifications.
	 *
	 * Format of the payload array:
	 *
	 * array(
	 *        'bean' => array( property => value pairs )
	 * )
	 *
	 * @return array
	 */
	private function post()
	{
		if ( !isset( $this->payload['bean'] ) ) {
			return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'Missing parameter \'bean\'.' );
		}
		if ( !is_array( $this->payload['bean'] ) ) {
			return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'Parameter \'bean\' must be object/array.' );
		}
		foreach ( $this->payload['bean'] as $key => $value ) {
			if ( !is_string( $key ) || !is_string( $value ) ) {
				return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'Object \'bean\' invalid.' );
			}
		}
		$newBean = $this->oodb->dispense( $this->type );
		$newBean->import( $this->payload['bean'] );
		if ( strpos( $this->list, 'shared-' ) === FALSE ) {
			$listName = 'own' . ucfirst( $this->list );
		} else {
			$listName = 'shared' . ucfirst( substr( $this->list, 7 ) );
		}
		array_push( $this->bean->$listName, $newBean );
		$this->oodb->store( $this->bean );
		$newBean = $this->oodb->load( $newBean->getMeta( 'type' ), $newBean->id );
		return $this->resp( $newBean->export() );
	}
	/**
	 * Opens a list and returns the contents of the list.
	 * By default a list is interpreted as the own-list of the current bean.
	 * If the list begins with the prefix 'shared-' the shared list of the
	 * bean will be opened instead. Internal method.
	 *
	 * @return array
	 */
	private function openList()
	{
		$listOfBeans = array();
		$listName = ( strpos( $this->list, 'shared-' ) === 0 ) ? ( 'shared' . ucfirst( substr( $this->list, 7 ) ) ) : ( 'own' . ucfirst( $this->list ) );
		if ( $this->sqlSnippet ) {
			if ( preg_match( '/^(ORDER|GROUP|HAVING|LIMIT|OFFSET|TOP)\s+/i', ltrim( $this->sqlSnippet ) ) ) {
				$beans = $this->bean->with( $this->sqlSnippet, $this->sqlBindings )->$listName;
			} else {
				$beans = $this->bean->withCondition( $this->sqlSnippet, $this->sqlBindings )->$listName;
			}
		} else {
			$beans = $this->bean->$listName;
		}
		foreach ( $beans as $listBean ) {
			$listOfBeans[] = $listBean->export();
		}
		return $this->resp( $listOfBeans );
	}
	/**
	 * Handles a REST DELETE request.
	 * Deletes the selected bean.
	 * Returns an array formatted according to RedBeanPHP REST BeanCan
	 * formatting specifications. Internal method.
	 *
	 * @return array
	 */
	private function delete()
	{
		$this->oodb->trash( $this->bean );
		return $this->resp( 'OK' );
	}
	/**
	 * Handles a custom request method.
	 * Passes the arguments specified in 'param' to the method
	 * specified as request method of the selected bean.
	 * Returns an array formatted according to RedBeanPHP REST BeanCan
	 * formatting specifications. Internal method.
	 *
	 * Payload array:
	 *
	 * array('param' => array(
	 *        param1, param2 etc..
	 * ))
	 *
	 * @return array
	 */
	private function custom()
	{
		if ( !isset( $this->payload['param'] ) ) {
			$this->payload['param'] = array();
		}
		if ( !is_array( $this->payload['param'] ) ) {
			return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'Parameter \'param\' must be object/array.' );
		}
		$answer = call_user_func_array( array( $this->bean, $this->method ), $this->payload['param'] );
		
		return $this->resp( $answer );
	}
	/**
	 * Extracts SQL snippet and SQL bindings from the SQL bundle.
	 * Selects the appropriate SQL snippet for the list to be opened.
	 *
	 * @return void
	 */
	private function extractSQLSnippetsForGETList()
	{
		$sqlBundleItem = ( isset( $this->sqlSnippets[$this->list] ) ) ? $this->sqlSnippets[$this->list] : array( NULL, array() );
		if ( isset( $sqlBundleItem[0] ) ) {
			$this->sqlSnippet = $sqlBundleItem[0];
		}
		if ( isset( $sqlBundleItem[1] ) ) {
			$this->sqlBindings = $sqlBundleItem[1];
		}
	}
	/**
	 * Dispatches the REST request to the appropriate method.
	 * Returns a response array.
	 *
	 * @return array
	 */
	private function dispatch()
	{
		if ( $this->method == 'GET' ) {
			if ( $this->list === NULL ) {
				return $this->get();
			}
			return $this->openList();
		} elseif ( $this->method == 'DELETE' ) {
			return $this->delete();
		} elseif ( $this->method == 'POST' ) {
			return $this->post();
		} elseif ( $this->method == 'PUT' ) {
			return $this->put();
		}
		return $this->custom();
	}
	/**
	 * Determines whether the bean type and action appear on the whitelist.
	 *
	 * @return boolean
	 */
	private function isOnWhitelist()
	{
		return (
			$this->whitelist === 'all'
			|| (
				$this->list === null
				&& isset( $this->whitelist[$this->beanType] )
				&& in_array( $this->method, $this->whitelist[$this->beanType] )
				|| (
					$this->list !== null
					&& isset( $this->whitelist[$this->type] )
					&& in_array( $this->method, $this->whitelist[$this->type] )
				)
			)
		);
	}
	/**
	 * Finds a bean by its URI.
	 * Returns the bean identified by the specified URI. 
	 * 
	 * For more details 
	 * @see RedBean_Finder::findByPath
	 *
	 * @return void
	 */
	private function findBeanByURI()
	{
		$finder = new RedBean_Finder( $this->toolbox );
		$this->bean     = $finder->findByPath( $this->root, $this->uri );
		$this->beanType = $this->bean->getMeta( 'type' );
	}
	/**
	 * Extract list information.
	 * Returns FALSE if the list cannot be read due to incomplete specification, i.e.
	 * less than one entry in the URI array.
	 *
	 * @return boolean
	 */
	private function extractListInfo()
	{
		if ( $this->method == 'POST' ) {
			if ( count( $this->uri ) < 1 ) return FALSE;
			$this->list = array_pop( $this->uri );
			$this->type = ( strpos( $this->list, 'shared-' ) === 0 ) ? substr( $this->list, 7 ) : $this->list;
		} elseif ( $this->method === 'GET' && count( $this->uri ) > 1 ) {
			$lastItemInURI = $this->uri[count( $this->uri ) - 1];
			if ( $lastItemInURI === 'list' ) {
				array_pop( $this->uri );
				$this->list = array_pop( $this->uri );
				$this->type = ( strpos( $this->list, 'shared-' ) === 0 ) ? substr( $this->list, 7 ) : $this->list;
				$this->extractSQLSnippetsForGETList();
			}
		}
		return TRUE;
	}
	/**
	 * Checks whether the URI contains invalid characters.
	 *
	 * @return boolean
	 */
	private function isURIValid()
	{
		if ( preg_match( '|^[\w\-/]*$|', $this->uri ) ) {
			return FALSE;
		}
		return TRUE;
	}
	/**
	 * Extracts the URI.
	 *
	 * @return void
	 */
	private function extractURI()
	{
		$this->uri = ( ( strlen( $this->uri ) ) ) ? explode( '/', ( $this->uri ) ) : array();
	}
	/**
	 * Handles the REST request and returns a response array.
	 *
	 * @return array
	 */
	private function handleRESTRequest()
	{
		try {
			if ( $this->isURIValid() ) {
				return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'URI contains invalid characters.' );
			}
			if ( !is_array( $this->payload ) ) {
				return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'Payload needs to be array.' );
			}
			$this->extractURI();
			if ( $this->extractListInfo() === FALSE ) {
				return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'Missing list.' );
			}
			if ( !is_null( $this->type ) && !preg_match( '|^[\w]+$|', $this->type ) ) {
				return $this->resp( NULL, self::C_HTTP_BAD_REQUEST, 'Invalid list.' );
			}
			try {
				$this->findBeanByURI();
			} catch ( Exception $e ) {
				return $this->resp( NULL, self::C_HTTP_NOT_FOUND, $e->getMessage() );
			}
			if ( !$this->isOnWhitelist() ) {
				return $this->resp( NULL, self::C_HTTP_FORBIDDEN_REQUEST, 'This bean is not available. Set whitelist to "all" or add to whitelist.' );
			}
			return $this->dispatch();
		} catch ( Exception $e ) {
			return $this->resp( NULL, self::C_HTTP_INTERNAL_SERVER_ERROR, 'Exception: ' . $e->getCode() );
		}
	}
	/**
	 * Clears internal state of the REST BeanCan.
	 *
	 * @return void
	 */
	private function clearState()
	{
		$this->list        = NULL;
		$this->bean        = NULL;
		$this->type        = NULL;
		$this->beanType    = NULL;
		$this->sqlBindings = array();
		$this->sqlSnippet  = NULL;
	}
	/**
	 * Constructor.
	 * Creates a new instance of the Resty BeanCan Server.
	 * If no toolbox is provided the Resty BeanCan Server object will
	 * try to obtain the toolbox currently used by the RedBeanPHP facade.
	 * If you use only the R-methods and not the advanced objects this should be fine.
	 *
	 * @param RedBean_ToolBox $toolbox (optional)
	 */
	public function __construct( $toolbox = NULL )
	{
		if ( $toolbox instanceof RedBean_ToolBox ) {
			$this->toolbox = $toolbox;
			$this->oodb    = $toolbox->getRedBean();
		} else {
			$this->toolbox = RedBean_Facade::getToolBox();
			$this->oodb    = RedBean_Facade::getRedBean();
		}
	}
	/**
	 * The Resty BeanCan uses a white list to determine whether the current
	 * request is allowed.
	 * 
	 * A whitelist has the following format: 
	 * 
	 * array( 'book' 
	 *	            => array( 'POST', 'GET', 'publish'),
	 *	       'page'
	 *             => etc...
	 * 
	 * this will allow the methods 'POST', 'GET' and 'publish' for beans of type 'book'.
	 * To allow all methods on all beans pass the string 'all'.
	 * 
	 * @param array|string $whitelist  a white list of beans and methods that should be accessible through the BeanCan Server.
	 *
	 * @return RedBean_Plugin_BeanCan
	 */
	public function setWhitelist( $whitelist )
	{
		$this->whitelist = $whitelist;
		return $this;
	}
	/**
	 * Handles a REST request.
	 * Returns a JSON response string.
	 *
	 * The first argument need to be the reference bean, or root bean (for instance 'user 1').
	 * The second argument is a path to select a bean relative to the root.
	 * For instance to select the 3rd page of a book of a user: 'book/1/page/3'.
	 * The third argument need to specify the REST method (GET/POST/DELETE/PUT) or NON-REST method
	 * (sendMail) to invoke. Optional arguments include the payload ($_POST) and
	 * a list of SQL snippets (the SQL bundle). The SQL bundle contains additional SQL and bindings
	 * per type, if a list gets accessed the SQL with the type-key of the list will be used to filter
	 * or sort the results.
	 * 
	 * Only method-bean combinations mentioned in the whitelist will be allowed.
	 * Also note that handleREST accepts ALL kinds of methods. You can pass proper HTTP methods
	 * or fabricated methods. The latter will just cause the methods to be invoked on the specified beans.
	 * 
	 * @param RedBean_OODBBean $root        root bean for REST action
	 * @param string           $uri         the URI of the RESTful operation
	 * @param string           $method      the method you want to apply
	 * @param array            $payload     payload (for POSTs)
	 * @param array            $sqlSnippets a bundle of SQL snippets to use
	 *
	 * @return string
	 */
	public function handleREST( $root, $uri, $method, $payload = array(), $sqlSnippets = array() )
	{
		$this->sqlSnippets = $sqlSnippets;
		$this->method      = $method;
		$this->payload     = $payload;
		$this->uri         = $uri;
		$this->root        = $root;
		$this->clearState();
		$result = $this->handleRESTRequest();
		return $result;
	}
}
class RedBean_Plugin_QueryLogger implements RedBean_Observer, RedBean_Plugin
{
	/**
	 * @var array
	 */
	protected $logs = array();
	/**
	 * Singleton pattern
	 * Constructor - private
	 */
	private function __construct() { }
	/**
	 * Creates a new instance of the Query Logger and attaches
	 * this logger to the adapter.
	 *
	 * @static
	 *
	 * @param RedBean_Observable $adapter the adapter you want to attach to
	 *
	 * @return RedBean_Plugin_QueryLogger
	 */
	public static function getInstanceAndAttach( RedBean_Observable $adapter )
	{
		$queryLog = new RedBean_Plugin_QueryLogger;
		$adapter->addEventListener( 'sql_exec', $queryLog );
		return $queryLog;
	}
	/**
	 * Implementation of the onEvent() method for Observer interface.
	 * If a query gets executed this method gets invoked because the
	 * adapter will send a signal to the attached logger.
	 *
	 * @param  string                    $eventName ID of the event (name)
	 * @param  RedBean_Adapter_DBAdapter $adapter   adapter that sends the signal
	 *
	 * @return void
	 */
	public function onEvent( $eventName, $adapter )
	{
		if ( $eventName == 'sql_exec' ) {
			$this->logs[] = $adapter->getSQL();
		}
	}
	/**
	 * Searches the logs for the given word and returns the entries found in
	 * the log container.
	 *
	 * @param  string $word word to look for
	 *
	 * @return array
	 */
	public function grep( $word )
	{
		$found = array();
		foreach ( $this->logs as $log ) {
			if ( strpos( $log, $word ) !== FALSE ) {
				$found[] = $log;
			}
		}
		return $found;
	}
	/**
	 * Returns all the logs.
	 *
	 * @return array
	 */
	public function getLogs()
	{
		return $this->logs;
	}
	/**
	 * Clears the logs.
	 *
	 * @return void
	 */
	public function clear()
	{
		$this->logs = array();
	}
}
class RedBean_Plugin_Cooker implements RedBean_Plugin
{
	/**
	 * @var boolean
	 */
	private static $loadBeans = FALSE;
	/**
	 * @var boolean
	 */
	private static $useNULLForEmptyString = FALSE;
	/**
	 * @var RedBean_Toolbox
	 */
	private $toolbox;
	/**
	 * @var RedBean_OODB
	 */
	private $redbean;
	/**
	 * If you enable bean loading graph will load beans if there is an ID in the array.
	 * This is very powerful but can also cause security issues if a user knows how to
	 * manipulate beans and there is no model based ID validation.
	 *
	 * @param boolean $yesNo
	 *
	 * @return void
	 */
	public static function enableBeanLoading( $yesNo )
	{
		self::$loadBeans = ( $yesNo );
	}
	/**
	 * Static version of setUseNullFlag.
	 *
	 * @param boolean $yesNo
	 *
	 * @return void
	 */
	public static function setUseNullFlagSt( $yesNo )
	{
		self::$useNULLForEmptyString = (boolean) $yesNo;
	}
	/**
	 * Sets the toolbox to be used by graph()
	 *
	 * @param RedBean_Toolbox $toolbox toolbox
	 *
	 * @return void
	 */
	public function setToolbox( RedBean_Toolbox $toolbox )
	{
		$this->toolbox = $toolbox;
		$this->redbean = $this->toolbox->getRedbean();
	}
	/**
	 * Loads bean, recurses if one of the property appears to be a list.
	 *
	 * @param array   $array       data array to import as a bean
	 * @param boolean $filterEmpty if TRUE empty STRING values are converted to NULL (default FALSE)
	 *
	 * @return RedBean_OODBBean
	 *
	 * @throws RedBean_Exception_Security
	 */
	private function loadBean( &$array, $filterEmpty )
	{
		$type = $array['type'];
		unset( $array['type'] );
		if ( isset( $array['id'] ) ) { // Do we need to load the bean?
			if ( self::$loadBeans ) {
				$bean = $this->redbean->load( $type, (int) $array['id'] );
			} else {
				throw new RedBean_Exception_Security( 'Attempt to load a bean in Cooker. Use enableBeanLoading to override but please read security notices first.' );
			}
		} else {
			$bean = $this->redbean->dispense( $type );
		}
		foreach ( $array as $property => $value ) {
			if ( is_array( $value ) ) {
				$bean->$property = $this->graph( $value, $filterEmpty );
			} else {
				$bean->$property = ( $value == '' && self::$useNULLForEmptyString ) ? NULL : $value;
			}
		}
		return $bean;
	}
	/**
	 * Loads a list. Recurses for every bean in the list.
	 *
	 * @param array   $array       data array to import as a list
	 * @param boolean $filterEmpty if TRUE empty lists will NOT be imported
	 *
	 * @return array
	 *
	 * @throws RedBean_Exception_Security
	 */
	private function loadList( &$array, $filterEmpty )
	{
		$beans = array();
		foreach ( $array as $key => $value ) {
			$listBean = $this->graph( $value, $filterEmpty );
			if ( !( $listBean instanceof RedBean_OODBBean ) ) {
				throw new RedBean_Exception_Security( 'Expected bean but got :' . gettype( $listBean ) );
			}
			if ( $listBean->isEmpty() ) {
				if ( !$filterEmpty ) {
					$beans[$key] = $listBean;
				}
			} else {
				$beans[$key] = $listBean;
			}
		}
		return $beans;
	}
	/**
	 * Turns an array (post/request array) into a collection of beans.
	 * Handy for turning forms into bean structures that can be stored with a
	 * single call.
	 *
	 * Typical usage:
	 *
	 * $struct = R::graph($_POST);
	 * R::store($struct);
	 *
	 * Example of a valid array:
	 *
	 *    $form = array(
	 *        'type' => 'order',
	 *        'ownProduct' => array(
	 *            array('id' => 171, 'type' => 'product'),
	 *        ),
	 *        'ownCustomer' => array(
	 *            array('type' => 'customer', 'name' => 'Bill')
	 *        ),
	 *        'sharedCoupon' => array(
	 *            array('type' => 'coupon', 'name' => '123'),
	 *            array('type' => 'coupon', 'id' => 3)
	 *        )
	 *    );
	 *
	 * Each entry in the array will become a property of the bean.
	 * The array needs to have a type-field indicating the type of bean it is
	 * going to be. The array can have nested arrays. A nested array has to be
	 * named conform the bean-relation conventions, i.e. ownPage/sharedPage
	 * each entry in the nested array represents another bean.
	 *
	 * @param    array  $array       array to be turned into a bean collection
	 * @param   boolean $filterEmpty whether you want to exclude empty beans
	 *
	 * @return    array
	 *
	 * @throws RedBean_Exception_Security
	 */
	public function graph( $array, $filterEmpty = FALSE )
	{
		if ( is_array( $array ) && isset( $array['type'] ) ) {
			return $this->loadBean( $array, $filterEmpty );
		} elseif ( is_array( $array ) ) {
			return $this->loadList( $array, $filterEmpty );
		} else {
			throw new RedBean_Exception_Security( 'Expected array but got :' . gettype( $array ) );
		}
	}
	/**
	 * Toggles the use-NULL flag.
	 *
	 * @param boolean $yesNo
	 *
	 * @return void
	 */
	public function setUseNullFlag( $yesNo )
	{
		self::$useNULLForEmptyString = (bool) $yesNo;
	}
}
class RedBean_Plugin_Cache extends RedBean_OODB implements RedBean_Plugin
{
	/**
	 * @var array
	 */
	protected $cache = array();
	/**
	 * @var integer
	 */
	protected $hits = 0;
	/**
	 * @var integer
	 */
	protected $misses = 0;
	/**
	 * Constructor.
	 * Cache decorates RedBeanPHP OODB class, so needs a writer.
	 *
	 * @param RedBean_QueryWriter $writer
	 */
	public function __construct( RedBean_QueryWriter $writer )
	{
		parent::__construct( $writer );
	}
	/**
	 * Loads a bean by type and id. If the bean cannot be found an
	 * empty bean will be returned instead. This is a cached version
	 * of the loader, if the bean has been cached it will be served
	 * from cache, otherwise the bean will be retrieved from the database
	 * as usual an a new cache entry will be added..
	 *
	 * @param string  $type type of bean you are looking for
	 * @param integer $id   identifier of the bean
	 *
	 * @return RedBean_OODBBean $bean the bean object found
	 */
	public function load( $type, $id )
	{
		if ( isset( $this->cache[$type][$id] ) ) {
			$this->hits++;
			$bean = $this->cache[$type][$id];
		} else {
			$this->misses++;
			$bean = parent::load( $type, $id );
			if ( $bean->id ) {
				if ( !isset( $this->cache[$type] ) ) {
					$this->cache[$type] = array();
				}
				$this->cache[$type][$id] = $bean;
			}
		}
		return $bean;
	}
	/**
	 * Stores a RedBean OODBBean and caches it.
	 *
	 * @param RedBean_OODBBean $bean the bean you want to store
	 *
	 * @return mixed
	 */
	public function store( $bean )
	{
		$id   = parent::store( $bean );
		$type = $bean->getMeta( 'type' );
		if ( !isset( $this->cache[$type] ) ) {
			$this->cache[$type] = array();
		}
		$this->cache[$type][$id] = $bean;
		return $id;
	}
	/**
	 * Trashes a RedBean OODBBean and removes it from cache.
	 *
	 * @param RedBean_OODBBean $bean bean
	 *
	 * @return mixed
	 */
	public function trash( $bean )
	{
		$type = $bean->getMeta( 'type' );
		$id   = $bean->id;
		if ( isset( $this->cache[$type][$id] ) ) {
			unset( $this->cache[$type][$id] );
		}
		parent::trash( $bean );
	}
	/**
	 * Flushes the cache for a given type.
	 *
	 * @param string $type
	 *
	 * @return RedBean_Plugin_Cache
	 */
	public function flush( $type )
	{
		if ( isset( $this->cache[$type] ) ) {
			$this->cache[$type] = array();
		}
		return $this;
	}
	/**
	 * Flushes the cache completely.
	 *
	 * @return RedBean_Plugin_Cache
	 */
	public function flushAll()
	{
		$this->cache = array();
		return $this;
	}
	/**
	 * Returns the number of hits. If a call to load() or
	 * batch() can use the cache this counts as a hit.
	 * Otherwise it's a miss.
	 *
	 * @return integer
	 */
	public function getHits()
	{
		return $this->hits;
	}
	/**
	 * Returns the number of hits. If a call to load() or
	 * batch() can use the cache this counts as a hit.
	 * Otherwise it's a miss.
	 *
	 * @return integer
	 */
	public function getMisses()
	{
		return $this->misses;
	}
	/**
	 * Resets hits counter to 0.
	 */
	public function resetHits()
	{
		$this->hits = 0;
	}
	/**
	 * Resets misses counter to 0.
	 */
	public function resetMisses()
	{
		$this->misses = 0;
	}
}
class RedBean_Plugin_TimeLine extends RedBean_Plugin_QueryLogger implements RedBean_Plugin {
	/**
	 * Path to file to write SQL and comments to.
	 * 
	 * @var string 
	 */
	protected $file;
	/**
	 * Constructor.
	 * Requires a path to an existing and writable file.
	 * 
	 * @param string $outputPath path to file to write schema changes to. 
	 */
	public function __construct($outputPath) {
		if (!file_exists($outputPath) || !is_writable($outputPath)) 
			throw new RedBean_Exception_Security('Cannot write to file: '.$outputPath);
		$this->file = $outputPath;
	}
	/**
	 * Implementation of the onEvent() method for Observer interface.
	 * If a query gets executed this method gets invoked because the
	 * adapter will send a signal to the attached logger.
	 *
	 * @param  string $eventName          ID of the event (name)
	 * @param  RedBean_DBAdapter $adapter adapter that sends the signal
	 *
	 * @return void
	 */
	public function onEvent($eventName, $adapter) {
		if ($eventName == 'sql_exec') {
			$sql = $adapter->getSQL();
			$this->logs[] = $sql;
			if (strpos($sql, 'ALTER') === 0) {
				$write = "-- ".date('Y-m-d H:i')." | Altering table. \n";
				$write .= $sql;
				$write .= "\n\n";
			}
			if (strpos($sql, 'CREATE') === 0) {
				$write = "-- ".date('Y-m-d H:i')." | Creating new table. \n";
				$write .= $sql;
				$write .= "\n\n";
			}
			if (isset($write)) {
				file_put_contents($this->file, $write, FILE_APPEND);
			}
		}
	}
}
class RedBean_DependencyInjector
{
	/**
	 * @var array
	 */
	protected $dependencies = array();
	/**
	 * Adds a dependency to the list.
	 * You can add dependencies using this method. Pass both the key of the
	 * dependency and the dependency itself. The key of the dependency is a
	 * name that should match the setter. For instance if you have a dependency
	 * class called My_Mailer and a setter on the model called setMailSystem
	 * you should pass an instance of My_Mailer with key MailSystem.
	 * The injector will now look for a setter called setMailSystem.
	 *
	 * @param string $dependencyID name of the dependency (should match setter)
	 * @param mixed  $dependency   the service to be injected
	 *
	 * @return void
	 */
	public function addDependency( $dependencyID, $dependency )
	{
		$this->dependencies[$dependencyID] = $dependency;
	}
	/**
	 * Returns an instance of the class $modelClassName completely
	 * configured as far as possible with all the available
	 * service objects in the dependency list.
	 *
	 * @param string $modelClassName the name of the class of the model
	 *
	 * @return mixed
	 */
	public function getInstance( $modelClassName )
	{
		$object = new $modelClassName;
		if ( $this->dependencies && is_array( $this->dependencies ) ) {
			foreach ( $this->dependencies as $key => $dep ) {
				$depSetter = 'set' . $key;
				if ( method_exists( $object, $depSetter ) ) {
					$object->$depSetter( $dep );
				}
			}
		}
		return $object;
	}
}
class RedBean_DuplicationManager
{
	/**
	 * @var RedBean_Toolbox
	 */
	protected $toolbox;
	/**
	 * @var RedBean_AssociationManager
	 */
	protected $associationManager;
	/**
	 * @var RedBean_OODB
	 */
	protected $redbean;
	/**
	 * @var array
	 */
	protected $tables = array();
	/**
	 * @var array
	 */
	protected $columns = array();
	/**
	 * @var array
	 */
	protected $filters = array();
	/**
	 * @var array
	 */
	protected $cacheTables = FALSE;
	/**
	 * Copies the shared beans in a bean, i.e. all the sharedBean-lists.
	 *
	 * @param RedBean_OODBBean $copy   target bean to copy lists to
	 * @param string           $shared name of the shared list
	 * @param array            $beans  array with shared beans to copy
	 *
	 * @return void
	 */
	private function copySharedBeans( RedBean_OODBBean $copy, $shared, $beans )
	{
		$copy->$shared = array();
		foreach ( $beans as $subBean ) {
			array_push( $copy->$shared, $subBean );
		}
	}
	/**
	 * Copies the own beans in a bean, i.e. all the ownBean-lists.
	 * Each bean in the own-list belongs exclusively to its owner so
	 * we need to invoke the duplicate method again to duplicate each bean here.
	 *
	 * @param RedBean_OODBBean $copy        target bean to copy lists to
	 * @param string           $owned       name of the own list
	 * @param array            $beans       array with shared beans to copy
	 * @param array            $trail       array with former beans to detect recursion
	 * @param boolean          $preserveIDs TRUE means preserve IDs, for export only
	 *
	 * @return void
	 */
	private function copyOwnBeans( RedBean_OODBBean $copy, $owned, $beans, $trail, $preserveIDs )
	{
		$copy->$owned = array();
		foreach ( $beans as $subBean ) {
			array_push( $copy->$owned, $this->duplicate( $subBean, $trail, $preserveIDs ) );
		}
	}
	/**
	 * Creates a copy of bean $bean and copies all primitive properties (not lists)
	 * and the parents beans to the newly created bean. Also sets the ID of the bean
	 * to 0.
	 *
	 * @param RedBean_OODBBean $bean bean to copy
	 *
	 * @return RedBean_OODBBean
	 */
	private function createCopy( RedBean_OODBBean $bean )
	{
		$type = $bean->getMeta( 'type' );
		$copy = $this->redbean->dispense( $type );
		$copy->importFrom( $bean );
		$copy->id = 0;
		return $copy;
	}
	/**
	 * Generates a key from the bean type and its ID and determines if the bean
	 * occurs in the trail, if not the bean will be added to the trail.
	 * Returns TRUE if the bean occurs in the trail and FALSE otherwise.
	 *
	 * @param array            $trail list of former beans
	 * @param RedBean_OODBBean $bean  currently selected bean
	 *
	 * @return boolean
	 */
	private function inTrailOrAdd( &$trail, RedBean_OODBBean $bean )
	{
		$type = $bean->getMeta( 'type' );
		$key  = $type . $bean->getID();
		if ( isset( $trail[$key] ) ) {
			return TRUE;
		}
		$trail[$key] = $bean;
		return FALSE;
	}
	/**
	 * Given the type name of a bean this method returns the canonical names
	 * of the own-list and the shared-list properties respectively.
	 * Returns a list with two elements: name of the own-list, and name
	 * of the shared list.
	 *
	 * @param string $typeName bean type name
	 *
	 * @return array
	 */
	private function getListNames( $typeName )
	{
		$owned  = 'own' . ucfirst( $typeName );
		$shared = 'shared' . ucfirst( $typeName );
		return array( $owned, $shared );
	}
	/**
	 * Determines whether the bean has an own list based on
	 * schema inspection from realtime schema or cache.
	 *
	 * @param string $type   bean type to get list for
	 * @param string $target type of list you want to detect
	 *
	 * @return boolean
	 */
	protected function hasOwnList( $type, $target )
	{
		return isset( $this->columns[$target][$type . '_id'] );
	}
	/**
	 * Determines whether the bea has a shared list based on
	 * schema inspection from realtime schema or cache.
	 *
	 * @param string $type   bean type to get list for
	 * @param string $target type of list you are looking for
	 *
	 * @return boolean
	 */
	protected function hasSharedList( $type, $target )
	{
		return in_array( RedBean_QueryWriter_AQueryWriter::getAssocTableFormat( array( $type, $target ) ), $this->tables );
	}
	/**
	 * @see RedBean_DuplicationManager::dup
	 *
	 * @param RedBean_OODBBean $bean          bean to be copied
	 * @param array            $trail         trail to prevent infinite loops
	 * @param boolean          $preserveIDs   preserve IDs
	 *
	 * @return RedBean_OODBBean
	 */
	protected function duplicate( RedBean_OODBBean $bean, $trail = array(), $preserveIDs = FALSE )
	{
		if ( $this->inTrailOrAdd( $trail, $bean ) ) return $bean;
		$type = $bean->getMeta( 'type' );
		$copy = $this->createCopy( $bean );
		foreach ( $this->tables as $table ) {
			if ( $table == $type ) continue;
			if ( !empty( $this->filters ) ) {
				if ( !in_array( $table, $this->filters ) ) continue;
			}
			list( $owned, $shared ) = $this->getListNames( $table );
			if ( $this->hasSharedList( $type, $table ) ) {
				if ( $beans = $bean->$shared ) {
					$this->copySharedBeans( $copy, $shared, $beans );
				}
			} elseif ( $this->hasOwnList( $type, $table ) ) {
				if ( $beans = $bean->$owned ) {
					$this->copyOwnBeans( $copy, $owned, $beans, $trail, $preserveIDs );
				}
				$copy->setMeta( 'sys.shadow.' . $owned, NULL );
			}
			$copy->setMeta( 'sys.shadow.' . $shared, NULL );
		}
		$copy->id = ( $preserveIDs ) ? $bean->id : $copy->id;
		return $copy;
	}
	/**
	 * Constructor,
	 * creates a new instance of DupManager.
	 *
	 * @param RedBean_Toolbox $toolbox
	 */
	public function __construct( RedBean_Toolbox $toolbox )
	{
		$this->toolbox            = $toolbox;
		$this->redbean            = $toolbox->getRedBean();
		$this->associationManager = $this->redbean->getAssociationManager();
	}
	/**
	 * For better performance you can pass the tables in an array to this method.
	 * If the tables are available the duplication manager will not query them so
	 * this might be beneficial for performance.
	 *
	 * @param array $tables
	 *
	 * @return void
	 */
	public function setTables( $tables )
	{
		foreach ( $tables as $key => $value ) {
			if ( is_numeric( $key ) ) {
				$this->tables[] = $value;
			} else {
				$this->tables[]      = $key;
				$this->columns[$key] = $value;
			}
		}
		$this->cacheTables = TRUE;
	}
	/**
	 * Returns a schema array for cache.
	 *
	 * @return array
	 */
	public function getSchema()
	{
		return $this->columns;
	}
	/**
	 * Indicates whether you want the duplication manager to cache the database schema.
	 * If this flag is set to TRUE the duplication manager will query the database schema
	 * only once. Otherwise the duplicationmanager will, by default, query the schema
	 * every time a duplication action is performed (dup()).
	 *
	 * @param boolean $yesNo
	 */
	public function setCacheTables( $yesNo )
	{
		$this->cacheTables = $yesNo;
	}
	/**
	 * A filter array is an array with table names.
	 * By setting a table filter you can make the duplication manager only take into account
	 * certain bean types. Other bean types will be ignored when exporting or making a
	 * deep copy. If no filters are set all types will be taking into account, this is
	 * the default behavior.
	 *
	 * @param array $filters
	 */
	public function setFilters( $filters )
	{
		if ( !is_array( $filters ) ) {
			$filters = array( $filters );
		}
		$this->filters = $filters;
	}
	/**
	 * Makes a copy of a bean. This method makes a deep copy
	 * of the bean.The copy will have the following features.
	 * - All beans in own-lists will be duplicated as well
	 * - All references to shared beans will be copied but not the shared beans themselves
	 * - All references to parent objects (_id fields) will be copied but not the parents themselves
	 * In most cases this is the desired scenario for copying beans.
	 * This function uses a trail-array to prevent infinite recursion, if a recursive bean is found
	 * (i.e. one that already has been processed) the ID of the bean will be returned.
	 * This should not happen though.
	 *
	 * Note:
	 * This function does a reflectional database query so it may be slow.
	 *
	 * Note:
	 * this function actually passes the arguments to a protected function called
	 * duplicate() that does all the work. This method takes care of creating a clone
	 * of the bean to avoid the bean getting tainted (triggering saving when storing it).
	 *
	 * @param RedBean_OODBBean $bean          bean to be copied
	 * @param array            $trail         for internal usage, pass array()
	 * @param boolean          $preserveIDs   for internal usage
	 *
	 * @return RedBean_OODBBean
	 */
	public function dup( RedBean_OODBBean $bean, $trail = array(), $preserveIDs = FALSE )
	{
		if ( !count( $this->tables ) ) {
			$this->tables = $this->toolbox->getWriter()->getTables();
		}
		if ( !count( $this->columns ) ) {
			foreach ( $this->tables as $table ) {
				$this->columns[$table] = $this->toolbox->getWriter()->getColumns( $table );
			}
		}
		$rs = $this->duplicate( clone( $bean ), $trail, $preserveIDs );
		if ( !$this->cacheTables ) {
			$this->tables  = array();
			$this->columns = array();
		}
		return $this->duplicate( $rs, $trail, $preserveIDs );
	}
	/**
	 * Exports a collection of beans. Handy for XML/JSON exports with a
	 * Javascript framework like Dojo or ExtJS.
	 * What will be exported:
	 * - contents of the bean
	 * - all own bean lists (recursively)
	 * - all shared beans (not THEIR own lists)
	 *
	 * @param   array|RedBean_OODBBean  $beans   beans to be exported
	 * @param   boolean                 $parents also export parents
	 * @param   array                   $filters only these types (whitelist)
	 *
	 * @return array
	 */
	public function exportAll( $beans, $parents = FALSE, $filters = array() )
	{
		$array = array();
		if ( !is_array( $beans ) ) {
			$beans = array( $beans );
		}
		foreach ( $beans as $bean ) {
			$this->setFilters( $filters );
			$duplicate = $this->dup( $bean, array(), TRUE );
			$array[]   = $duplicate->export( FALSE, $parents, FALSE, $filters );
		}
		return $array;
	}
}
class R extends RedBean_Facade{
  
}