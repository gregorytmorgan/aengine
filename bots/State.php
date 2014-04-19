<?php

/**
 * State
 *
 *	events: [[state, trigger], ...]  ??????????????????????????
 *	actionName: "Go North"
 *	action: "n", function () { return 'n' }, ...
 */
class State {

	static $instance = 1;

	protected $id;

	protected $name;

	protected $logger;

	protected $debug;

	public $events;

	public $action;

	protected $actionName;

	const DEBUG_LEVEL_DEFAULT = 4095;

	/**
	 *
	 * @param array $args
	 */
	function __construct($args = array()){
		$this->id = (isset($args['id'])) ? $args['id'] : get_class($this) . State::$instance;
		$this->name = (isset($args['name'])) ?  $args['name'] : get_class($this) . ' #' . State::$instance;
		$this->debug = (isset($args['debug'])) ?  $args['debug'] : self::DEBUG_LEVEL_DEFAULT;
		$this->logger = new AntLogger(array());

		$this->events = (isset($args['events'])) ?  $args['events'] : array();
		$this->action = (isset($args['action'])) ? $args['action'] : false;
		$this->actionName = (isset($args['actionName'])) ? $args['actionName'] : 'Do nothing';

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_BOT);

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
	'name' => 'Initialize',
	'action' => false,
	'actionName' => 'NOP',
	'events' => array(
		array(
			'test' => function ($ant, $data = array()) { return true; },
			'next' => 'nop'
		)
	)
));

$NOP_STATE = new State(array(
	'id' => 'nop',
	'name' => 'Do nothing',
	'action' => false,
	'actionName' => 'NOP',
	'events' => array(
		array(
			'test' => function ($ant, $data = array()) { return false; },
			'next' => null
		)
	)
));

$END_STATE = new State(array(
	'id' => 'end',
	'name' => 'End',
	'action' => false,
	'actionName' => 'NOP',
	'events' => array(
		array(
			'test' => function ($ant, $data = array()) { return false; },
			'next' => null
		)
	)
));

// end file
