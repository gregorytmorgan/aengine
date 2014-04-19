<?php

require_once 'State.php';

/**
 * Description of Missions
 *
 * @author gmorgan
 */
class Mission {
	
	static $instance = 1; 	
	
	protected $id;

	protected $name;

	protected $logger;	
	
	protected $debug;	

	protected $state;
	
	protected $states = array('init' => array());	
	
	const DEBUG_LEVEL_DEFAULT = 4095;

	/**
	 * 
	 * @param array $args
	 */
	function __construct($args = array()){

		global $INIT_STATE;		
		global $NOP_STATE;
		
		$this->id = (isset($args['id'])) ? $args['id'] : get_class($this) . Mission::$instance;
		$this->name = (isset($args['name'])) ?  $args['name'] : get_class($this) . ' #' . Mission::$instance;
		$this->debug = (isset($args['debug'])) ?  $args['debug'] : self::DEBUG_LEVEL_DEFAULT;
		$this->logger = new AntLogger(array());

		$this->states = array(
			'init' => $INIT_STATE,
			'nop' => $NOP_STATE
		);

		$this->setState('init');

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_BOT);
		
		Mission::$instance++;
	}
	
	function reset() {
		$this->setState = $states[$init->id];
	}
	
	function setState($state) {
		$this->state = $this->states[$state];
	}

	/**
	 * 1. chk current state for state change
	 * 2. action
	 * 
	 * or            ???????????????????????????????????
	 * 
	 * 1. action
	 * 2. chk cond for state change
	 * 
	 * 
	 * need to get some params from game, etc, to test(), what data ?????????????????????????????????
	 */
	function takeAction($ant, $data = array()) {

		$result = null;
		
		if ($this->state->action) {
			if (is_callable($this->state->action)) {
				$result = $this->state->action($ant, $data);
			} else {
				$this->logger->write('Action is not callable', AntLogger::LOG_ERROR);
				//$result = $this->state->action;
			}
		}
		
		foreach ($this->state->events as $evt) {
			if (is_callable($evt['test'])) {
				if ($evt['test']($ant, $data)) {		
					$this->logger->write('Transitioning to new state ' . sprintf("%s", $this->state), AntLogger::LOG_BOT);
					$this->setState($evt['next']);
				} 
			} else {
				$this->logger->write('Event test for state ' . sprintf("%s", $this->state) . ' is not callable', AntLogger::LOG_WARN);
			}
		}
		
		return $result;
	}	
	
	/**
	 * 
	 * @return string
	 */
    public function __toString (){
		$str =  $this->name . ' Current State:' . $this->state; // write state list as well
		return $str;
    }

}

/**
 * Description of Missions
 *
 * @author gmorgan
 */
class MissionGoNESW extends Mission {

	/**
	 * Call the parent constructor, then redefine the mission states
	 * 
	 * @param array $args
	 */
	function __construct($args = array()) {
		
		parent::__construct($args);
		
		global $INIT_STATE;		
		global $NOP_STATE;
		global $END_STATE;
		
		$this->id = (isset($args['id'])) ? $args['id'] : get_class($this) . MissionGoNESW::$instance;
		$this->name = (isset($args['name'])) ?  $args['name'] : get_class($this) . ' #' . MissionGoNESW::$instance;
		
		$this->states = array();
		
		$move_state = new State(array(
			'id' => 'move',
			'name' => 'Moving',
			'action' => function ($a, $d) { $this->move($a, $d); },
			'actionName' => 'Moving',
			'events' => array (
				array(
					'test' => function ($ant, $data = array()) { return false; },
					'next' => null
				)
			)			
		));
			
		$this->states = array(
			'init' => $INIT_STATE,
			'nop' => $NOP_STATE,
			'move' => $move_state,
			'end' => $END_STATE
		);			

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_BOT);
		
		MissionGoNESW::$instance++;
	}
	
	/**
	 * Get a for $ant for this mission based on $data
	 * 
	 * @param Ant $ant This ant.
	 * @param Ants $data Ants game data.
	 * @return string|false Returns the direction to move next turn on success, false if nowhere to go.
	 */
	function move ($ant, $data) {
		$directions = array('n', 'e', 's', 'w');
		
		//list ($aRow, $aCol) = $ant->pos;
		
		foreach ($directions as $direction) {
			
			// from the current position, what coords result if we travel $direction? 
			list($dRow, $dCol) = $data->destination($ant->row, $ant->col, $direction);	// myMap->destination()
			
			// is the dest coord ok?
			$passable = $data->passable($dRow, $dCol);									// myMap->passable()
			if ($passable) {
				$this->logger->write(sprintf("%s", $this) . ' moved ' . $direction . ' to ' . $dRow . ', ' . $dCol, AntLogger::LOG_ANT);
				$this->pos = array($dRow, $dCol);
				return $direction;
			}
			
			$this->logger->write(sprintf("%s", $ant) . ' has no where to go', AntLogger::LOG_BOT);
			$this->pos = array($dRow, $dCol);			
			
			return false;
		} // directions
	}
	
}

// end file