#!/usr/bin/php
<?php

require_once 'bots/Ants.php';

// Ant #1
$ant1 = new Ant(array('row' => 0, 'col' => 0));

// test magic getter/setter
$ant1->row = 1;
$ant1->col = 2;
$pos = $ant1->pos;
// test virtual attrib row
echo "pos: ({$ant1->row}, {$pos[1]})\n";

// test toString
echo $ant1 . "\n";

// Ant #2, test 2nd instance
$ant2 = new Ant(array('row' => 10, 'col' => 10));
echo $ant2;

$ant2->pos = array(20, 20);
echo $ant2 . "\n";


$fn = array(
	function ()  { echo "A\n"; },
	function ()  { echo "B\n"; },	
);

foreach ($fn as $f) {
	$f();
}
	