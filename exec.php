<?php

$cmd = (  isset($argv[1])  ) ? $argv[1] : false;

$output = array();

if ($cmd) {
    echo exec($cmd, $output, $retval);
    echo implode("\n", $output);
} else {
    echo 'no command';
}

echo "Done";