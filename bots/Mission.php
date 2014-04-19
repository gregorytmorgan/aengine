<?php

require_once 'State.php';

/**
 * Ant mission - simple state machine for ant actions.
 *
 * @author gmorgan
 */
class Mission {

	const DEBUG_LEVEL_DEFAULT = AntLogger::LOG_ALL;

	static $instance = 1; 	
	
	protected $id;

	public $name;

	protected $logger;	
	
	protected $debug;	

	protected $initialState;

	protected $endState;

	/**
	 * The current state of the Mission.
	 * 
	 * @var State
	 */
	protected $state;

	/**
	 * A list of Mission states.
	 *
	 * @var array
	 */
	protected $states;	
	
	/**
	 * 
	 * @param array $args
	 */
	function __construct($args = array()){

		global $INIT_STATE;		
		global $END_STATE;
		
		$this->id = (isset($args['id'])) ? $args['id'] : get_class($this) . Mission::$instance;
		$this->name = (isset($args['name'])) ?  $args['name'] : get_class($this) . ' #' . Mission::$instance;
		$this->debug = (isset($args['debug'])) ?  $args['debug'] : self::DEBUG_LEVEL_DEFAULT;

		$this->logger = new AntLogger(array(
			'logLevel' => $this->debug
		));

		$this->states = array(
			'init' => $INIT_STATE,
			'end' => $END_STATE
		);

		$initialState = $INIT_STATE;
		$endState = $END_STATE;

		$this->state = $INIT_STATE;

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_ANT);
		
		Mission::$instance++;
	}
	
	function reset() {
		$this->setState = $states[$this->initialState];
	}

	/**
	 * Return the current Mission state.
	 * 
	 * @return State
	 */
	function getState() {
		return $this->state;
	}

	/**
	 * Set the current Mission state.
	 *
	 * @param State|string $state State or state id.
	 * @return boolean Return true on success, false otherwise.
	 */
	function setState($state) {
		if (is_string($state)) {
			if (isset($this->states[$state])) {
				$newState = $this->states[$state];
			} else {
				$this->logger->write("Mission.setState() passed an invalid state($state)", AntLogger::LOG_ERROR);
				return false;
			}
		} else if (get_class($state) === 'State')  {
			$newState = $state;
		} else {
			$s = (gettype($state) === 'object') ? get_class($state) : $state;
			$this->logger->write("Mission.setState() passed an invalid state(" .  $s . ")", AntLogger::LOG_ERROR);
			return false;
		}
		$this->state = $newState;

		return true;
	}

	/**
	 * doTurn
	 *
	 * @param Ant $ant
	 * @param object $game Game data
	 * @return mixed
	 */
	function doTurn(Ant $ant, $game = array()) {
		$result = null;

		if ($this->state->action) {
			if (is_callable($this->state->action) || (isset($this->state->action[0]) && is_callable($this->state->action[0]))) {
				$this->logger->write($ant->name . ' - Firing ant action ' . $this->state->actionName, AntLogger::LOG_GAME_FLOW + AntLogger::LOG_ANT);
				if (is_callable($this->state->action)) {
					$result = call_user_func_array($this->state->action, array($ant, $game));
				} else {
					$args = array($ant, $game);
					if (isset($this->state->action[1]) && is_array($this->state->action[1])) {
						array_push($args, $this->state->action[1]);
					}
					$result = call_user_func_array($this->state->action[0], $args);
				}
			} else {
				$this->logger->write('Action is not callable', AntLogger::LOG_ERROR);
			}
		}

		foreach ($this->state->events as $evt) {
			if (is_callable($evt['test'])) {
				if ($evt['test']($ant, $game)) {
					$prevState = $this->state->name;
					$this->setState($evt['next']);
					$this->logger->write(sprintf("%s transitioning from state %s to state %s", $this->name, $prevState, $this->state->name), AntLogger::LOG_ANT);
				} 
			} else {
				$this->logger->write('Event test for state ' . sprintf("%s", $this->state) . ' is not callable', AntLogger::LOG_ERROR);
			}
		}
		
		return $result;
	}	
	
	/**
	 * 
	 * @return string
	 */
    public function __toString () {
		$strStates = '';
		foreach ($this->states as $state) {
			$next = '';
			foreach ($state->events as $evt) {
				$next .= $evt['next'] . '|';
			}
			$strStates .= $state->name . "(" . substr($next, 0 , -1) . "), ";
		}
		$str =  $this->name . ' States:[' . substr($strStates, 0 , -2) . '] Current State:' . $this->state; // write state list as well
		return $str;
    }

}

/**
 * Ant mission to go straight until an obstacle is encountered, then turn
 * N, E, S, W.
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

		global $END_STATE;
		
		$init_state = new State(array(
			'id' => 'init',
			'name' => 'Initialized',
			'action' => false,
			'actionName' => 'NoAction',
			'events' => array(
				array(
					'test' => function ($ant, $data = array()) { return true; },
					'next' => 'moving'
				)
			),
			'debug' => $this->debug
		));

		$move_state = new State(array(
			'id' => 'move',
			'name' => 'Moving',
			'action' => array($this, 'move'),
			'actionName' => 'Move NESW',
			'events' => array (
				array(
					'test' => function ($ant, $data = array()) { return false; },
					'next' => 'end'
				)
			),
			'debug' => $this->debug
		));
			
		$this->states = array(
			'init' => $init_state,
			'moving' => $move_state,
			'end' => $END_STATE
		);			

		$initialState = $init_state;
		$endState = $END_STATE;
		
		$this->state = $init_state;

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_ANT);
	}
	
	/**
	 * Get a move for $ant for this mission based on $game.
	 * 
	 * @param Ant $ant This ant.
	 * @param Ants $game is the Ants game data.
	 * @return string|false Returns the direction to move next turn on success, false if nowhere to go.
	 */
	function move ($ant, Ants $game) {
		$directions = array('n', 'e', 's', 'w');

		foreach ($directions as $direction) {
			
			// from the current position, what coords result if we travel $direction? 
			list($dRow, $dCol) = $game->destination($ant->row, $ant->col, $direction);	// myMap->destination()
			
			// is the dest coord ok?
			$passable = $game->passable($dRow, $dCol);									// myMap->passable()
			if ($passable) {
				$this->logger->write(sprintf("%s %s moved %s to %d,%d", $ant->name, $this, $direction, $dRow, $dCol), AntLogger::LOG_ANT);
				$game->issueOrder($ant->row, $ant->col, $direction);
				$ant->pos = array($dRow, $dCol);
				return $direction;
			}

			$this->logger->write(sprintf("%s", $ant) . ' has no where to go', AntLogger::LOG_ANT);
			
			return false;
		} // directions
	} //move
	
}

// end file