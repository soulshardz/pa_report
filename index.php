<?php
	define( 'ENVIRONMENT', 'dev' );
	
	require_once 'log_parser.php';
	require_once 'db.php';

	$allowedCommands = array( "full" => "aggregate", "parse"=>"parse", "report" => "report" );

	if( !in_array( count( $argv ), array(3,4,5) ) )
	{
		showUsage();
	}

	try
	{
		if( !in_array( $argv[1] , array_keys( $allowedCommands ) ) )
		{
			throw new Exception( "Not allowed command passed: [" . $argv[1] . "]!" );
		}
		$command = $argv[1];

		$groupFK = $argv[2];
		
		$configurationFile = 'default';

		if( 4 == count( $argv ) )
		{
			if( is_string( $argv[3] ) && "" != $argv[3] )
			{
				$configurationFile = $argv[3];
			}
		}

		$year = null;
		$month = date( "m" );

		if( 5 == count( $argv ) )
		{
			if( is_string( $argv[4] ) && "" != $argv[4] )
			{
				$monthYear = $argv[4];

				$dateParts = explode(" ", $monthYear );

				if( 1 === count( $dateParts ) )
				{
					array_push( $dateParts, null );
				}

				if( 2 === count( $dateParts ) )
				{
					list( $year, $month ) = $dateParts;
				}
			}
		}

		$configurationFileName = './'.$configurationFile . '.conf';

		if( !is_file( $configurationFileName ) || !is_readable( $configurationFileName ) )
		{
			throw new Exception( "Cannot read specified configuration file [" . $configurationFileName . "]!" );
		}

		require_once $configurationFileName;
		
		$dbCfgArr = array(
			'host' => file_get_contents( "/home/ubuntu/.spocosydb" ),
			'username' => 'spocosy_dev',
			'password' => 'spocosy_dev',
			'database' => 'SpoCoSy',
			'tablePrefix'   => ''
		);

		$db = DB::create( 'spocosydb', $dbCfgArr );
		$db->open();

		$reporter = new PerformanceReporter( $groupFK );
		$reporter->setDebug( true );
		$reporter->setDb( $db );
		$reporter->setConfig( $config );		
		$reporter->setMonth( $month, $year );

		// var_dump( $reporter->getall() );
		$reporter->{$allowedCommands[ $command ]}();
	}
	catch( Exception $e )
	{
		echo "Error occurred: " . $e->getMessage() . "\n\nExecution terminated!\n\n";
	}
	
	function showUsage()
	{
		global $allowedCommands;
		echo "\nScript usage:\n\n\tLEGEND:\n\n\t\tcommand -> What action to perform: one of [" . implode("|", array_keys( $allowedCommands ) ) . "]\n\n\t\tgroupFK -> Integer value for group/client\n\t\tconfiguration file -> Filename without the extension to use for configuration. Defaults to 'default', looking for a file called 'default.conf' in the working directory.\n\t\tyear-month -> Parseable string representing month and year for which to report (recommended format YYYY-MM).\n\n\tphp ./index.php command groupFK (configurationFile) (year-month)\n\n";
		exit();
	}

?>