<?php
	$allowedCommands = array( "full" => "aggregate", "parse"=>"parse", "report" => "report" );

	if( !in_array( count( $argv ), array(3,4) ) )
	{
		showUsage();
	}

	try
	{
		$groupFK = $argv[1];
		if( !in_array( $command , array_keys( $allowedCommands ) ) )
		{
			throw new Exception( "Not allowed command passed: [" . $argv[2] . "]!" );
		}
		
		$command = $argv[2];

		$configurationFile = 'default';

		if( 4 == count( $argv ) )
		{
			if( is_string( $argv[3] ) && "" != $argv[3] )
			{
				$configurationFile = $argv[3];
			}
		}

		$configurationFileName = './'.$configurationFile . '.conf';

		if( !is_file( $configurationFileName ) || !is_readable( $configurationFileName ) )
		{
			throw new Exception( "Cannot read specified configuration file [" . $configurationFileName . "]!" );
		}

		require_once $configurationFileName;
		require_once 'log_parser.php';

		$reporter = new PerformanceReporter( $groupFK );
		$reporter->setDb( $db );
		$reporter->setConfig( $config );

		$reporter->{$allowedCommands[ $command ]}();
	}
	catch( Exception $e )
	{
		echo "Error occurred: " . $e->getMessage() . '\n\nExecution terminated!';
	}
	
	function showUsage()
	{
		global $allowedCommands;
		echo "\nScript usage:\n\n\tLEGEND:\n\n\t\tcommand -> What action to perform: one of [" . implode("|", array_keys( $allowedCommands ) ) . "]\n\n\t\tgroupFK -> Integer value for group/client\n\t\tconfiguration file -> Filename without the extension to use for configuration. Defaults to 'default', looking for a file called 'default.conf' in the working directory.\n\n\tphp ./index.php groupFK (configurationFile)\n\n";
		exit();
	}

?>