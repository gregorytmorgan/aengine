<?php
/**
 * Execute a system command
 */

if (count($argv) <= 1) {
  echo "ERROR - no command";
  exit();	
} 

unset($argv[0]);

$cmd = implode(" ", $argv);

$output = array();
$retval = null;

exec($cmd, $output, $retval);

echo implode("\n", $output);

