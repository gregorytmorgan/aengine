<?php

require_once 'Ants.php';
require_once 'BaseBot.php';

/**
 * GTM Bot
 */
class GTMBot extends BaseBot {
	// do something exta?
}

/**
 * Don't run bot when unit-testing
 */
if( !defined('PHPUnit_MAIN_METHOD') ) {
    Ants::run( new GTMBot() );
}