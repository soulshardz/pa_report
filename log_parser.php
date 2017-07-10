<?php

// NOTE: all in one solution, should split in multiple files if complexity rises!

class PerformanceReporter {

	protected $_db;
	protected $_config;

	protected $_dailyData;
	protected $_groupData;
	protected $_aggregatedData;
	protected $_month;
	protected $_year;
	protected $_report;

	protected $_groupFK;
	protected $_allowedFileModes;
	protected $_debug;

	public function __construct( $groupFK )
	{
		if( 0 >= intval( $groupFK ) )
		{
			throw new Exception( "The group FK should be positive integer!" );			
		}

		$this->_groupFK = $groupFK;
		$this->_groupData = array();
		$this->_aggregatedData = array();
		$this->_dailyData = array();
		$this->_allowedFileModes = array( "w", "r" );
		$this->_month = date('m');
		$this->_year = date('Y');
		$this->_report = array();
		$this->_debug = false;

		$this->_initQueries();
	}

	public function parse()
	{
		if( $this->_debug ) echo PHP_EOL . " parse start" . PHP_EOL;

		if( empty( $this->_groupData ) )
		{
			$this->_loadGroupData();
		}

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

		$dbResult = $this->_db->select( $this->_q( 'getSubscriptionFKs', array( 'groupFK' => $this->_groupFK ) ) );

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
			$successCount = 0;
			$failCount = 0;

			exec( 'grep "Push success" ' . $logFileName . ' 2>/dev/null | cut -f2 | sort | uniq -c', $success );
			exec( 'grep "Push failed" ' . $logFileName . ' 2>/dev/null | cut -f2 | sort | uniq -c', $fail );

			if( isset( $success[0] ) )
			{
				$stringParts = explode( " ", trim( $success[0] ) );
				$successCount = intval( array_shift( $stringParts ) );
			}

			if( isset( $fail[0] ) )
			{
				$stringParts = explode( " ", trim( $fail[0] ) );
				$failCount = intval( array_shift( $stringParts ) );
			}

			if( !isset( $this->_dailyData[ $row[ "value" ] ][ "success" ] ) )
			{
				$this->_dailyData[ $row[ "value" ] ][ "success" ] = 0;
			}

			$this->_dailyData[ $row[ "value" ] ][ "success" ]	+= $successCount;

			if( !isset( $this->_dailyData[ $row[ "value" ] ][ "fail" ] ) )
			{
				$this->_dailyData[ $row[ "value" ] ][ "fail" ] = 0;
			}			

			$this->_dailyData[ $row[ "value" ] ][ "fail" ]	+= $failCount;
		}

		$this->_saveAggregate();
		if( $this->_debug ) echo PHP_EOL . " parse end" . PHP_EOL;
	}

	public function setDb( $dbObject )
	{
		if( !is_object( $dbObject ) )
		{
			throw new Exception( "Invalid parameter passed for database object. [" . gettype( $dbObject ) . "] passed, should be object!" );
		}

		$this->_db = $dbObject;
	}

	public function setDebug( $flag )
	{
		if( !is_bool( $flag ) )
		{
			throw new Exception( "Invalid parameter passed for debug switch. [" . gettype( $flag ) . "] passed, should be boolean!" );
		}

		$this->_debug = $flag;
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

		// validate/parse the log date
		if( isset( $this->_config[ "logDate" ] ) )
		{
			$this->_config[ "logDate" ] = date( 'Y-m-d', strtotime( $this->_config[ "logDate" ] ) );
		}
	}

	public function setMonth( $month, $year = null )
	{

		if( is_null( $year ) )
		{
			$year = date('Y');
		}

		if( preg_match( '/^[0-9]{2}$/', $month ) )
		{
			$parsedDate = date( "Y-m-d", strtotime( $year . "-" . $month ) );
		}
		else
		{
			$parsedDate = date( "Y-m-d", strtotime( $year . " " . $month ) );
		}

		// if the combined product of the month and year parameters is invalid - stop execution.
		if( "1970-01-01" === $parsedDate )
		{
			throw new Exception( "Parameters for month and year should be valid, parseable string representation!" );
		}

		$this->_year = date( "Y", strtotime( $parsedDate ));
		$this->_month = date( "m", strtotime( $parsedDate ));
	}

	public function aggregate()
	{
		$this->parse();
		$this->report();
	}

	public function report()
	{
		if( $this->_debug ) echo PHP_EOL . " reporting start" . PHP_EOL;
		if( empty( $this->_groupData ) )
		{
			$this->_loadGroupData();
		}

		$this->_buildReport();

		if( true === $this->_config[ "shouldSendEmail" ] )
		{
			$this->_sendEmail();
		}
		else
		{
			foreach ($this->_reportResults as $url => $data )
			{
				// flatten the values for each url
				$ar = array_merge( array( "url" => $url  ), $data );
				fputcsv(STDOUT, $ar );
			}
		}
		if( $this->_debug ) echo PHP_EOL . " reporting end" . PHP_EOL;
	}

	public function getall()
	{
		return $this;
	}

	protected function _initQueries()
	{
		$this->_queries[ "getClientData" ] 			= "SELECT * FROM groups WHERE id = {groupFK}";
		$this->_queries[ "getSubscriptionFKs" ]		= "SELECT xs.id, xsp.value FROM xml_subscription AS xs INNER JOIN xml_subscription_params AS xsp ON xs.id = xsp.xml_subscriptionFK WHERE xs.groupFK = {groupFK} AND xsp.name = 'POST_URL' AND xs.del = 'no' AND xsp.del = 'no'";
	}

	protected function _loadGroupData()
	{
		$dbResult = $this->_db->select( $this->_q( 'getClientData', array( "groupFK" => $this->_groupFK ) ) );
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

		$fileSize = filesize( $this->_config[ "actualAggregateFileName" ] );

		if( 0 < $fileSize )
		{
			$aggregateData = fread( $fh, $fileSize );
		}
		else
		{
			$aggregateData = "{}";
		}

		fclose( $fh );

		$parsedData = json_decode( $aggregateData, true );

		if( !is_array( $parsedData ) )
		{
			$parsedData = array();
		}
		
		$this->_aggregatedData = $parsedData;
		
	}

	protected function _saveAggregate()
	{
		if( 0 >= count( $this->_dailyData ) )
		{
			throw new Exception( "There is nothing to save to the aggregate file!" );
		}

		if( date( "Y-m", strtotime( $this->_config[ "logDate" ] ) ) === $this->_year . "-" . $this->_month )
		{
			$this->_loadAggregate();

			if( !isset( $this->_aggregatedData[ $this->_groupFK ] ) )
			{
				$this->_aggregatedData[ $this->_groupFK ] = array();
			}

			foreach ( $this->_dailyData as $url => $dailyDataRow )
			{
				$this->_aggregatedData[ $this->_groupFK ][ $url ][ $this->_config[ "logDate" ] ] = $dailyDataRow;
			}

			$fh = $this->_getAggregateFileHandle( "w" );

			if( !fwrite($fh, json_encode( $this->_aggregatedData ) ) )
			{
				fclose( $fh );
				throw new Exception( "Cannot write out data to aggregate file!" );
			}

			fclose( $fh );
		}

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

		if( !isset( $this->_config[ "aggregateFileNamePattern" ] ) )
		{
			throw new Exception( "Aggregate file name absent from configuration!" );
		}

		$this->_config[ "actualAggregateFileName" ] = str_replace( array( "{month}", "{year}" ), array( $this->_month, $this->_year ), $this->_config[ "aggregateFileNamePattern" ] );

		if( !is_file( $this->_config[ "actualAggregateFileName" ] ) )
		{
			if( !touch( $this->_config[ "actualAggregateFileName" ] ) )
			{
				throw new Exception( "Aggregate file cannot be found or is not readable by the script process!" );
			}
		}

		if( !is_writable( $this->_config[ "actualAggregateFileName" ] ) )
		{
			throw new Exception( "Aggregate file is not writeable by the script process!" );
		}

		$aggregateFileHandle = @fopen( $this->_config[ "actualAggregateFileName" ], $mode );

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

	protected function _buildReport()
	{
		$reportForMonth = $this->_year . "-" . $this->_month;

		$nextDay = date( "Y-m-d", strtotime( $reportForMonth ) );

		$shouldSeekNextDay = true;

		// if the requested month is current month - last day to report should be yesterday
		if( $this->_month === date( "m" ) )
		{
			$lastDay = date( "Y-m-d", strtotime( "yesterday" ) );
		}
		else // if it is different - find the last day of month
		{
			$lastDay = date( "Y-m-d", strtotime( date( "Y-m-01", strtotime( $this->_year . '-' . $this->_month . " +1 month" ) ) . " -1 day" ) );
		}

		$this->_report[ 'firstDay' ] = $nextDay;
		$this->_report[ 'lastDay' ] = $lastDay;

		$this->_loadAggregate();

		$this->_reportResults = array();

		while( $shouldSeekNextDay )
		{
			// if already reached lastDay - stop gathering data after current loop.
			if( $nextDay === $lastDay )
			{
				$shouldSeekNextDay = false;
			}

			if( isset( $this->_aggregatedData[ $this->_groupFK ] ) )
			{
				foreach ( $this->_aggregatedData[ $this->_groupFK ] as $url => $urlDailyData )
				{
					if( isset( $urlDailyData[ $nextDay ] ) )
					{
						if( !isset( $this->_reportResults[ $url ][ "success" ] ) )
						{
							$this->_reportResults[ $url ][ "success" ] = 0;
						}

						$this->_reportResults[ $url ][ "success" ] += $urlDailyData[ $nextDay ][ "success" ];

						if( !isset( $this->_reportResults[ $url ][ "fail" ] ) )
						{
							$this->_reportResults[ $url ][ "fail" ] = 0;
						}

						$this->_reportResults[ $url ][ "fail" ] += $urlDailyData[ $nextDay ][ "fail" ];
					}
				}
			}

			// increment next day
			$nextDay = date( "Y-m-d", strtotime( $nextDay . " +1 day" ) );
		}
	}

	protected function _sendEmail()
	{
		if( !isset( $this->_config[ "mailRecipients" ] ) || 0 >= count( $this->_config[ "mailRecipients" ] ) )
		{
			throw new Exception( "No mail recipients configured!" );
		}

		// generate the email message
		ob_start();

		$clientName = $this->_groupData[ "name" ];
		$from = $this->_report[ "firstDay" ];
		$to = $this->_report[ "lastDay" ];
		$aggregatedData = $this->_reportResults;

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

		$query = $this->_queries[ $queryName ];

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