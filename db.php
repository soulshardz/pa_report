<?php

/**
 * Mysql database wrapper class.
 * @todo       Create unit tests!
 */
class DB
{
	private static $instance = array();

	/**
	 * @var Connection name
	 */
	
	public $debugQueries;

	private $label;

	private $host;

	private $username;

	private $password;

	private $database;

	private $__tablePrefix;

	private $connection;

	private function __construct()
	{
		//do not need constructior body
	}

	/**
	 * Disabled cloning.
	 */
	private function __clone()
	{
		//do not need clone body
	}

	/**
	 * Resets the object upon initialization
	 */
	private function init( $cfg )
	{
		// @todo validate cfg

		$this->host = $cfg['host'];
		$this->username = $cfg['username'];
		$this->password = $cfg['password'];
		$this->database = $cfg['database'];
		$this->__tablePrefix = $cfg['tablePrefix'];
		$this->connection = null;
		$this->debugQueries = false;
	}

	/**
	 * Initiates the internal instance and returns it.
	 *
	 * @param      string  $label connection name
	 * @param      array  $cfg configurations array
	 * @return     Config  Returns the internal singleton instance.
	 */
	public static function create( $label, $cfg )
	{
		// @todo: validate label

		if( !isset(self::$instance[ $label ]) || self::$instance[ $label ] == null )
		{
			self::$instance[ $label ] = new self();
			self::$instance[ $label ]->label = $label;
			self::$instance[ $label ]->init( $cfg );
		}

		return self::$instance[ $label ];
	}

	/**
	 * Open a connection to the DB
	 */
	public function open()
	{
		$sDBi = new \mysqli( $this->host, $this->username, $this->password, $this->database  );
		
		if ( $sDBi->connect_error)
		{
			throw new Exception( 'Can not open database.' );
			exit;
		}
		$this->connection = $sDBi;
	}

	/**
	 * Close DB connection - not used for Persistant connections
	 */
	public function close()
	{
		$this->connection->close();
	}

	/**
  	 * Is the connection to the DB already open
	 */
	public function is_open()
	{
		return $this->connection != null ? true : false;
	}


	/**
	 * Wrapper for mysqli query method.
	 *
	 * @param      string  $sql    The sql query string to execute.
	 * @todo 						Add logging of errors to files organized by user and date.
	 *
	 * @return     mixed  Returns TRUE|FALSE upon query execution. For SELECT queries returns result object.
	 */
	public function query( $sql )
	{
		if( empty($sql) )
		{
			throw new Exception( 'Empty sql query.' );
		}

		if( !$this->is_open() )
		{
			throw new Exception( 'Database is not open.' );
		}

		if( "dev" === ENVIRONMENT && true === $this->debugQueries )
		{
			echo "\ndb[ ". $this->database ." ] @ [ " . $this->host . " ] exec SQL:\n\t" . $sql . "\n";
		}

		$results = $this->connection->query( $sql );
		$errno = $this->connection->errno;

		if( $errno )
		{
			if( "live" === ENVIRONMENT )
			{
				throw new Exception( 'Invalid query: '.$sql.chr(10).$this->connection->error );
			}
			else
			{
				throw new Exception( 'Invalid query! Aborting request. Error hash [' . md5( $sql ) . ']' );
				// @todo Add logging of errors to files organized by user and date.
			}
		}

		return $results;
	}


	/**
	 * Wrapper for mysqli last_insert_id property.
	 *
	 * @return     int  Returns integer if the last query updated AUTO_INCREMENT column, else returns zero.
	 */
	public function last_insert_id()
	{
		$result = $this->connection->insert_id;

		return $result;
	}

	/**
	 * Wrapper for mysqli affected_rows property.
	 *
	 * @return     int  Returns integer of how many rows the last query affected
	 */
	public function affected_rows()
	{
		$result = $this->connection->affected_rows;

		return $result;
	}

	/**
	 * Wrapper/alias method for issuing SELECT queries via the $this->query method.
	 *
	 * @param      string  $sql    The SELECT sql query string.
	 *
	 * @return     array   Array of matching database record. Empty if none are found.
	 * 
	 * @todo       Add more SELECT specific validations if needed ( pre $this->query ).
	 */
	public function select( $sql )
	{
		$res = $this->query( $sql );

		$rows = array();
		while( $row = $res->fetch_array( MYSQLI_ASSOC ) )
		{
			$rows[] = $row;
		}

		$res->free_result();
		return $rows;
	}


	/**
	 * Alias for the mysqli::escape_string() method.
	 *
	 * @param      string|int  $value  The value to be escaped.
	 * @throws     Exception
	 * 
	 * @return     string      Returns the escaped string
	 */
	public function escape( $value )
	{
		if( !is_string( $value ) && !is_int( $value ) )
		{
			throw new Exception( 'Not valid argument! You have passed "'.gettype( $value ).'", but it should be string or integer.' );			
		}

		// if the value is integer - convert it to string first.
		if( is_int( $value ) )
		{
			$value = (string) $value;
		}

		return $this->connection->escape_string( $value );
	}

	public function getTablePrefix()
	{
		return $this->__tablePrefix;
	}
}