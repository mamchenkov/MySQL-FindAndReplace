<?php

// This script is to solve the problem of doing database search and replace
// when developers have only gone and used the non-relational concept of
// serializing PHP arrays into single database columns.  It will search for all
// matching data on the database and change it, even if it's within a serialized
// PHP array.

// The big problem with serialised arrays is that if you do a normal DB
// style search and replace the lengths get mucked up.  This search deals with
// the problem by unserializing and reserializing the entire contents of the
// database you're working on.  It then carries out a search and replace on the
// data it finds, and dumps it back to the database.  So far it appears to work
// very well.  It was coded for our WordPress work where we often have to move
// large databases across servers, but I designed it to work with any database.
// Biggest worry for you is that you may not want to do a search and replace on
// every damn table - well, if you want, simply add some exclusions in the table
// loop and you'll be fine.  If you don't know how, you possibly shouldn't be
// using this script anyway.

// To use, simply configure the settings below and off you go.  I wouldn't
// expect the script to take more than a few seconds on most machines.

// BIG WARNING!  Take a backup first, and carefully test the results of this code.
// If you don't, and you vape your data then you only have yourself to blame.
// Seriously.  And if you're English is bad and you don't fully understand the
// instructions then STOP.  Right there.  Yes.  Before you do any damage.

// USE OF THIS SCRIPT IS ENTIRELY AT YOUR OWN RISK.  I/We accept no liability from its use.

// Written 20090525 by David Coveney of Interconnect IT Ltd (UK)
// http://www.davesgonemental.com or http://www.interconnectit.com or
// http://spectacu.la and released under the WTFPL
// ie, do what ever you want with the code, and I take no responsibility for it OK?
// If you don't wish to take responsibility, hire me through Interconnect IT Ltd
// on +44 (0)151 709 7977 and we will do the work for you, but at a cost, minimum 1hr
// To view the WTFPL go to http://sam.zoy.org/wtfpl/ (WARNING: it's a little rude, if you're sensitive)

// Credits:  moz667 at gmail dot com for his recursive_array_replace posted at
//           uk.php.net which saved me a little time - a perfect sample for me
//           and seems to work in all cases.

//  Start TIMER
//  -----------
$stimer = explode( ' ', microtime() );
$stimer = $stimer[1] + $stimer[0];
//  -----------

/**
 * Get run-time configuration
 * 
 * Parse command line options and return them as
 * associative array.  Anything in the form of
 * name=value goes.
 * 
 * @todo Check if we are in CLI or web mode. Do not always assume CLI.
 * @param array $argv All options
 * @return array
 */
function parseArgs($argv) {
	$result = array();

	// Set some defaults
	$result['hostname'] = 'localhost';
	$result['username'] = 'root';    
	$result['password'] = '';
	$result['database'] = '';
	$result['encoding'] = 'utf8';       // Set connection encoding. Use blank for default
	$result['find'] = '';               // what we are looking for
	$result['replace'] = '';            // what we are replacing with

	$argCount = count($argv);
	
	// $argv[0] is usually the name of the script itself
	if ($argCount > 1) {
		for($i = 1; $i < $argCount; $i++) {
			if (preg_match("/^(.*?)=(.*)$/", $argv[$i], $matches)) {
				$result[ $matches[1] ] = $matches[2];
			}
		}
	}

	return $result;
}

/**
 * Output message
 * 
 * This wrapper is handy for different levels of debug
 * output, as well as formatting (HTML vs. plain text).
 * 
 * @param string $message Message to output
 * @return void
 */
function out($message) {
	$ts = date('Y-m-d H:i:s');
	print "[$ts] $message\n";
}

/**
 * Connect to the database
 * 
 * @throws RuntimeException
 * @param array $args Connection parameters
 * @return resource
 */
function dbConnect($args) {
	
	$result = mysql_connect($args['hostname'], $args['username'], $args['password']); 
	if (!$result) {
		throw new RuntimeException(mysql_error());
	}

	if (!mysql_select_db($args['database'], $result)) {
		throw new RuntimeException(mysql_error());
	}

	if (!empty($args['encoding'])) {
		mysql_query('SET NAMES ' . $args['encoding'], $result);
		mysql_query('SET CHARACTER_SET ' . $args['encoding'], $result);
	}
	
	return $result;
}

/**
 * Get a list of all tables
 * 
 * @throws RuntimeException
 * @param resource $cid DB connection
 * @return array
 */
function getTables($cid) {
	$result = array();
	
	$sth = mysql_query('SHOW TABLES', $cid);
	if (!$sth) {
		throw new RuntimeException(mysql_error($cid));
	}
	
	while($table = array_values(mysql_fetch_assoc($sth))) {
		$result[] = $table[0];
	}

	return $result;
}

/**
 * Print report for gathered statistics
 * 
 * To make things simpler, all we do is just iterate over
 * associative array with stats, clean up the key and use 
 * it a label for the value.
 * 
 * @param array $stats List of gathered stats
 * @return void
 */
function printReport($stats) {
	out("Report");
	out("------");
	foreach ($stats as $key => $value) {
		$label = preg_replace('/_/', ' ', $key);
		$label = ucfirst($label);
		$value = number_format($value);
		out("$label: $value");
	}
}

try {
	$args = parseArgs($argv);
	$cid = dbConnect($args);
	$tables = getTables($cid);
}
catch (Exception $e) {
	out("Fatal error");
	out("-----------");
	out($e->getMessage());
	die();
}

$stats = array();
$stats['tables_checked'] = 0;
$stats['items_checked'] = 0;
$stats['items_changed'] = 0;

// Loop through the tables
foreach ($tables as $table) {

	$stats['tables_checked']++;

	out("Checking table: $table");

	$SQL = "DESCRIBE ".$table ;    // fetch the table description so we know what to do with it
	$fields_list = mysql_query($SQL, $cid);

	// Make a simple array of field column names

	$index_fields = "";  // reset fields for each table.
	$column_name = "";
	$table_index = "";
	$i = 0;

	while ($field_rows = mysql_fetch_array($fields_list)) {
		$column_name[$i++] = $field_rows['Field'];
		if ($field_rows['Key'] == 'PRI') {
			$table_index[$i] = true ;
		}
	}

	// now let's get the data and do search and replaces on it...

	$SQL = "SELECT * FROM ".$table;     // fetch the table contents
	$data = mysql_query($SQL, $cid);

	if (!$data) {
		echo("ERROR: " . mysql_error() . "<br/>$SQL<br/>"); 
	} 

	while ($row = mysql_fetch_array($data)) {

		// Initialise the UPDATE string we're going to build, and we don't do an update for each damn column...

		$need_to_update = false;
		$UPDATE_SQL = 'UPDATE '.$table. ' SET ';
		$WHERE_SQL = ' WHERE ';

		$j = 0;

		foreach ($column_name as $current_column) {
			$j++;
			$stats['items_checked']++;

			$data_to_fix = $row[$current_column];
			$edited_data = $data_to_fix;            // set the same now - if they're different later we know we need to update

			$unserialized = unserialize($data_to_fix);  // unserialise - if false returned we don't try to process it as serialised

			if ($unserialized) {
				recursive_array_replace($args['find'], $args['replace'], $unserialized);
				$edited_data = serialize($unserialized);
			}
			else {
				if (is_string($data_to_fix)) {
					$edited_data = str_replace($args['find'],$args['replace'],$data_to_fix) ;
				}
			}

			if ($data_to_fix != $edited_data) {   // If they're not the same, we need to add them to the update string

				$stats['items_changed']++;

				if ($need_to_update != false) {
					$UPDATE_SQL = $UPDATE_SQL.',';  // if this isn't our first time here, add a comma
				}
				$UPDATE_SQL = $UPDATE_SQL.' '.$current_column.' = "'.mysql_real_escape_string($edited_data).'"' ;
				$need_to_update = true; // only set if we need to update - avoids wasted UPDATE statements

			}

			if ($table_index[$j]){
				$WHERE_SQL = $WHERE_SQL.$current_column.' = "'.$row[$current_column].'" AND ';
			}
		}

		if ($need_to_update) {

		$count_updates_run;
		$WHERE_SQL = substr($WHERE_SQL,0,-4); // strip off the excess AND - the easiest way to code this without extra flags, etc.

		$UPDATE_SQL = $UPDATE_SQL.$WHERE_SQL;
		out($UPDATE_SQL);

		$result = mysql_query($UPDATE_SQL,$cid);
		if (!$result) {
			echo("ERROR: " . mysql_error() . "<br/>$UPDATE_SQL<br/>"); 
		} 

		}
	}
}

// Report
printReport($stats);

mysql_close($cid); 

//  End TIMER
//  ---------
$etimer = explode( ' ', microtime() );
$etimer = $etimer[1] + $etimer[0];
out("Script timer: " . ($etimer-$stimer). " seconds");
//  ---------

function recursive_array_replace($find, $replace, &$data) {

	if (is_array($data)) {
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				recursive_array_replace($find, $replace, $data[$key]);
			} 
			else {
				// have to check if it's string to ensure no switching to string for booleans/numbers/nulls - don't need any nasty conversions
				if (is_string($value)) {
					$data[$key] = str_replace($find, $replace, $value);
				}
			}
		}
	} 
	else {
		if (is_string($data)) $data = str_replace($find, $replace, $data);
	}

} 

?>
