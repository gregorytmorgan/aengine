<?php

/**
 * State
 *
 *	events: [
 *		[
 *			'test': callable, Test to determine transition
 *			'next': string id of the next state
 *		]
 *	]
 *
 *	actionName: "Go North"
 *
 *	action: callable - The function to execute every turn,
 * 
 */
class State {

	const DEBUG_LEVEL_DEFAULT = AntLogger::LOG_ALL;

	static $instance = 1;

	protected $id;

	public $name;

	protected $logger;

	protected $debug;

	/**
	 * Each event entry has two keys: 'test' and 'next'.
	 *
	 * 'test' contains a function will return if a transition state is reached.
	 * 'next' contains the id string of the next state in the Mission states array.
	 *
	 * @var array
	 */
	public $events;

	/**
	 * A PHP callable.  The function that is called on every turn.
	 *
	 * @var callable
	 */
	public $action;

	/**
	 * The name of th action.
	 *
	 * @var string
	 */
	public $actionName;

	/**
	 *
	 * @param array $args
	 */
	function __construct($args = array()){
		$this->id = (isset($args['id'])) ? $args['id'] : get_class($this) . State::$instance;
		$this->name = (isset($args['name'])) ?  $args['name'] : get_class($this) . ' #' . State::$instance;
		$this->debug = (isset($args['debug'])) ?  $args['debug'] : self::DEBUG_LEVEL_DEFAULT;

		$this->logger = new AntLogger(array(
			'logLevel' => $this->debug
		));

		$this->events = (isset($args['events'])) ?  $args['events'] : array();
		$this->action = (isset($args['action'])) ? $args['action'] : false;
		$this->actionName = (isset($args['actionName'])) ? $args['actionName'] : 'Do nothing';

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_MISSION);

		State::$instance++;
	}

	/**
	 *
	 * @return string
	 */
    public function __toString (){
		$str =  $this->name . ' Action:' . $this->actionName;
		return $str;
    }

}

/**
 *  Common states
 */
$INIT_STATE = new State(array(
	'id' => 'init',
	'name' => 'Initialized',
	'action' => false,
	'actionName' => 'NoAction',
	'events' => array(
		array(
			'test' => function ($ant, $data = array()) { return true; },
			'next' => 'end'
		)
	),
	'debug' => AntLogger::LOG_NONE
));

//$NOP_STATE = new State(array(
//	'id' => 'nop',
//	'name' => 'Nop',
//	'action' => false,
//	'actionName' => 'NoAction',
//	'events' => array(
//		array(
//			'test' => function ($ant, $data = array()) { return false; },
//			'next' => null
//		)
//	),
//	'debug' => AntLogger::LOG_NONE
//));

$END_STATE = new State(array(
	'id' => 'end',
	'name' => 'End',
	'action' => false,
	'actionName' => 'NoAction',
	'events' => array(
		array(
			'test' => function ($ant, $data = array()) { return false; },
			'next' => null
		)
	),
	'debug' => AntLogger::LOG_NONE
));

// end file
