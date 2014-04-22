#!/usr/bin/php
<?php

require_once 'bots/Map.php';
require_once 'bots/Ants.php';

$map = new Map(array(
    'rows' => 8,
    'columns' => 8,
    'defaultChar' => '_'
));

$map->grid[0][3] = 'x';
$map->grid[0][4] = 'x';
$map->grid[0][6] = 'x';

$map->grid[1][1] = 'x';
$map->grid[1][4] = 'x';

$map->grid[2][1] = 'x';
$map->grid[2][2] = 'x';
$map->grid[2][6] = 'x';

$map->grid[3][1] = 'x';
$map->grid[3][4] = 'x';

$map->grid[4][3] = 'x';
$map->grid[4][4] = 'x';
$map->grid[4][6] = 'x';

$map->grid[5][1] = 'x';
$map->grid[5][4] = 'x';
$map->grid[5][6] = 'x';

$map->grid[6][1] = 'x';
$map->grid[6][2] = 'x';
$map->grid[6][6] = 'x';

$map->grid[7][4] = 'x';


$path = $map->findPath(array(2,0),array(10,18));

echo $map;

$str = '';
foreach ($path as $k => $v) {
  $map->grid[$v[0]][$v[1]] = '.';
  $str .= "(" . implode(",", $v) . ") ";
}

echo "Path: " . $str . "\n";
echo $map;

// end file


	