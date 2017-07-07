<?php

// NOTE: all in one solution, should split in multiple files if complexity rises!

class PerformanceReporter {

	protected $_db;
	protected $_config;

	protected $_dailyData;
	protected $_groupData;
	protected $_aggregatedData;

	protected $_groupFK;
	protected $_allowedFileModes;

	public function __construct( $groupFK )
	{
		if( !is_int( $groupFK ) )
		{
			throw new Exception( "The group FK should be an integer!" );			
		}

		if( 0 >= intval( $groupFK ) )
		{
			throw new Exception( "The group FK should be positive integer!" );			
		}

		$this->_groupFK = $groupFK;
		$this->_groupData = array();
		$this->_aggregatedData = array();
		$this->_dailyData = array();
		$this->_allowedFileModes = array( "w", "r" );

		$this->_initQueries();

		$this->_loadGroupData();
	}

	public function parse()
	{
		$result = false;

		if( !isset( $this->_config[ "logDir" ] ) || !isset( $this->_config[ "logFileNamePattern" ] ) )
		{
			throw new Exception( "Log directory or name pattern absent from configuration!" );
		}

		if( !isset( $this->_config[ "logDate" ] ) )
		{
			throw new Exception( "Log date absent from configuration!" );
		}

		$subscriptionFKs = array();
		$this->_dailyData = array();

		$dbResult = $this->db->query( $this->_q( 'getSubscriptionsFKs', array( 'groupFK' => $groupFK ) ) );

		if( !is_array( $dbResult ) || 0 >= count( $dbResult ) )
		{
			throw new Exception("No subscriptions found for the client!" );
		}

		foreach( $dbResult as $row )
		{
			// map subscription id to its URL
			$subscriptionFKs[ $row[ "id" ] ] = $row[ "value" ];
			$logFileName = str_replace( "{subscriptionFK}", $row[ "id" ], $this->_config[ "logDir" ] . '/' . $this->_config[ "logDate" ] . '/' . $this->_config[ "logFileNamePattern" ] );

			$success = array();
			$fail = array();

			exec( 'grep "Push success" ' . $logFileName . ' | cut -f2 | sort | uniq -c', $success );
			exec( 'grep "Push failed" ' . $logFileName . ' | cut -f2 | sort | uniq -c', $fail );

			$this->_dailyData[ $row[ "value" ] ][ "success" ]	= $success;
			$this->_dailyData[ $row[ "value" ] ][ "failed" ]		= $fail;
		}

		$this->_setAggregate();

		return $result;
	}

	public function setDb( $dbObject )
	{
		if( !is_object( $dbObject ) )
		{
			throw new Exception( "Invalid parameter passed for database object. [" . gettype( $dbObject ) . "] passed, should be object!" );
		}

		$this->_db = $dbObject;
	}

	public function setConfig( $configArray )
	{
		if( !is_array( $configArray ) )
		{
			throw new Exception( "Invalid parameter passed for configuration array. [" . gettype( $configArray ) . "] passed, should be an array!" );
		}

		if( 0 >= count( $configArray ) )
		{
			throw new Exception( "The configuration array should NOT be empty!" );
		}

		$this->_config = $configArray;

		// validate the log date
		if( isset( $this->_config[ "logDate" ] ) )
		{
			$this->_config[ "logDate" ] = date( 'Y-m-d', strtotime( $this->_config[ "logDate" ] ) );
		}
	}

	public function aggregate()
	{
		$this->parse();
		$this->report();
	}

	public function report()
	{
		$this->_loadAggregate();

		if( true === $this->_config[ "shouldSendEmail" ] )
		{
			$this->_sendEmail();
		}
		else
		{
			fputcsv(STDOUT, $this->_aggregatedData );
		}
	}

	protected function _initQueries()
	{
		$this->_queries[ "getClientData" ] 			= "SELECT * FROM groups WHERE id = {groupFK}";
		$this->_queries[ "getSubscriptionFKs" ]		= "SELECT xs.id, xsp.value FROM xml_subscription AS xs INNER JOIN xml_subscription_params AS xsp ON xs.id = xsp.xml_subscriptionFK WHERE xs.groupFK = {groupFK} AND xsp.name = 'POST_URL' AND xs.del = 'no' AND xsp.del = 'no'";
	}

	protected function _loadGroupData()
	{
		$dbResult = $this->_db->query( $this->_q( 'getClientData', array( "groupFK" => $this->_groupFK ) ) );
		if( !is_array( $dbResult ) || 0 >= count( $dbResult ) )
		{
			throw new Exception( "Cannot find the requested group via ID [" . $this->_groupFK . "]!" );
		}

		if( "yes" === $dbResult[0][ "del" ] )
		{
			throw new Exception( "Requested group is deleted!" );
		}

		$this->_groupData = $dbResult[0];
	}

	protected function _loadAggregate()
	{
		$fh = $this->_getAggregateFileHandle( "r" );

		$aggregateData = fread( $fh, filesize( $this->_config[ "aggregateFileName" ] ) );

		fclose( $fh );

		$parsedData = json_decode( $aggregateData );

		if( !is_array( $parsedData ) )
		{
			throw new Exception( "Corrupt aggregated data after reading [" . $this->_config[ "aggregateFileName" ] . "]!" );
		}
		
		$this->_aggregatedData = $parsedData;
		
	}

	protected function _setAggregate()
	{
		$result = false;

		if( 0 >= count( $this->_dailyData ) )
		{
			throw new Exception( "There is nothing to save to the aggregate file!" );
		}

		$this->_loadAggregate();

		if( !isset( $this->_aggregatedData[ $this->_groupFK ] ) )
		{
			$this->_aggregatedData[ $this->_groupFK ] = array( "total" => array() );
		}

		foreach ( $this->_dailyData as $dailyDataRow )
		{
			if( !isset( $this->_aggregatedData[  ] )
			{

			}
			
		}

		$fh = $this->_getAggregateFileHandle( "w" );

		return $result;
	}

	protected function _getAggregateFileHandle( $mode )
	{
		if( !is_string( $mode ) )
		{
			throw new Exception( "File mode should be string!" );
		}

		if( ! in_array( $mode, $this->_allowedFileModes ) )
		{
			throw new Exception( "Not allowed file mode[" . $mode . "] requested!" );
		}

		if( !isset( $this->_config[ "aggregateFileName" ] ) )
		{
			throw new Exception( "Aggregate file name absent from configuration!" );
		}

		if( !is_file( $this->_config[ "aggregateFileName" ] ) )
		{
			throw new Exception( "Aggregate file cannot be found or is not readable by the script process!" );
		}

		if( !is_writable( $this->_config[ "aggregateFileName" ] ) )
		{
			throw new Exception( "Aggregate file is not writeable by the script process!" );
		}

		$aggregateFileHandle = @fopen( $this->_config[ "aggregateFileName" ], $mode );

		if( false === $aggregateFileHandle )
		{
			switch ( $mode ) {
				case 'w':
					$mode = 'writing';
					break;
				case 'r':
					$mode = 'reading';
					break;
				default:
					throw new Exception( "Unrecognized file mode [" . $mode . "]", 1);
					break;
			}

			throw new Exception( "Aggregate file cannot be opened for " . $mode . "!" );
		}

		return $aggregateFileHandle;
	}

	protected function _sendEmail( $aggregatedData )
	{
		if( !isset( $this->_config[ "mailRecipients" ] ) || 0 >= count( $this->_config[ "mailRecipients" ] ) )
		{
			throw new Exception( "No mail recipients configured!" );
		}

		// generate the email message
		ob_start();

		$clientName = $this->_groupData[ "name" ];
		$period = $this->_config[ "period" ];
		$aggregatedData = $this->_aggregatedData;

		require_once 'email_template.php';
		$message = ob_get_clean();

		$headers = "From: Cron Reporter <DONOTREPLY@enetpulse.com>\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

		if( !mail( $this->_config[ 'mailRecipients' ] , str_replace( "{clientName}", $clientName, $this->_config[ 'mailSubject' ] ) ,$message, $headers) )
		{
			throw new Exception( "Cannot send e-mail!" );
		}
	}

	protected function _q( $queryName, $replacementArray = array() )
	{
		if( !is_array( $replacementArray ) )
		{
			throw new Exception( "Replacements should be key -> value array!" );
		}

		if( !is_string( $queryName ) || "" === $queryName )
		{
			throw new Exception( "Query name should be not empty string!" );
		}

		if( !isset( $this->_queries[ $queryName ] ) )
		{
			throw new Exception( "Not existing query requested [" . $queryName . "]" );
			
		}

		if( 0 < count( $replacementArray ) )
		{
			foreach( $replacementArray as $key => $value )
			{
				$query = str_replace('{' . $key . '}', $value, $query );
			}
		}

		return $query;
	}
}

?>