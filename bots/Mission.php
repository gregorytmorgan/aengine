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
		$this->game = (isset($args['game'])) ?  $args['game'] : self::DEBUG_LEVEL_DEFAULT;
		
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

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_MISSION);
		
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
				$this->logger->write($ant->name . ' - Firing ant action ' . $this->state->actionName, AntLogger::LOG_GAME_FLOW | AntLogger::LOG_MISSION);
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
					$this->logger->write(sprintf("%s transitioning from state %s to state %s", $this->name, $prevState, $this->state->name), AntLogger::LOG_MISSION);
				} 
			} else {
				$this->logger->write('Event test for state ' . sprintf("%s", $this->state) . ' is not callable', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
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

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_MISSION);
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
				$this->logger->write(sprintf("%s %s moved %s to %d,%d", $ant->name, $this, $direction, $dRow, $dCol), AntLogger::LOG_MISSION);
				$game->issueOrder($ant->row, $ant->col, $direction);
				//$game->map[$row][$col] = LAND;											// myMap->update()
				$ant->pos = array($dRow, $dCol);										// myMap->update()
				return $direction;
			}
		} // directions

		$this->logger->write(sprintf("%s", $ant) . ' has no where to go', AntLogger::LOG_MISSION);

		return false;

	} //move
	
}


/**
 * Ant mission to go a point on the map.
 *
 * @author gmorgan
 */
class MissionGoToPoint extends Mission {
	
	protected $pathMap = null;
	
	protected $goalPt = null;
	
	protected $path = null;
	
	/**
	 * Call the parent constructor, then redefine the mission states
	 * 
	 * @param array $args
	 */
	function __construct($args = array()) {
		
		parent::__construct($args);

		$this->goalPt = (isset($args['goalPt'])) ?  $args['goalPt'] : null;

		if (!$this->goalPt) {
			$this->logger->write(sprintf("%s Bad goal point.", $this), AntLogger::LOG_ANT | AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
		}
		
		global $END_STATE;
		
		$this->pathMap = new Map(array(
			'debug' => $this->debug,
			'rows' => $this->game->rows,
			'columns' => $this->game->cols,
			'defaultChar' => UNSEEN		// define('UNSEEN', -5); from Ants.php
		));
		
		$init_state = new State(array(
			'id' => 'init',
			'name' => 'Initialized',
			'action' => array($this, 'init'),
			'actionName' => 'Initialize Mission',
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
			'name' => 'moving',
			'action' => array($this, 'move'),
			'actionName' => 'Move next',
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

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_MISSION | AntLogger::LOG_MISSION);
	}
	
	/**
	 * Get a move for $ant for this mission based on $game.
	 * 
	 * @param Ant $ant This ant.
	 * @param Ants $game is the Ants game data.
	 * @return boolean
	 */
	function init ($ant, Ants $game) {
		
$this->logger->write(sprintf("Mission init 1 %d,%d  %d,%d-------------------------------------------",$ant->row, $ant->col, $this->goalPt[0], $this->goalPt[1]));	

		$path = $this->pathMap->findPath(array($ant->row, $ant->col), $this->goalPt);

$this->logger->write('Mission init 2 -------------------------------------------');			
		
		if (!$path) {
			$this->logger->write(sprintf("%s Init - pathFind failed, Using current point.", $this), AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			//$path = array(array($ant->row, $ant->col));
			$this->path = false;
		}
		
		$this->path = $path;
	}
	
	/**
	 * Move along path.
	 * 
	 * @param Ant $ant This ant.
	 * @param Ants $game is the Ants game data.
	 * @return string|false Returns the direction to move next turn on success, false if nowhere to go.
	 */
	function move ($ant, Ants $game) {

		if (!$this->path) {
			$this->logger->write(sprintf("%s", $this) . ' Empty path.', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			return false;
		}
		
		$nextPt = array_shift($this->path);

		if (!$nextPt) {
			$this->logger->write(sprintf("%s", $this) . ' SHIFT FAILED?.', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			return false;
		}

		$direction = $game->direction($ant->row, $ant->col, $nextPt[0], $nextPt[1]);

		// is the dest coord ok?
		$passable = $game->passable($nextPt[0], $nextPt[1]);									// myMap->passable()
		if ($passable) {
			$this->logger->write(sprintf("%s %s moved %s to %d,%d", $ant->name, $this, $direction, $dRow, $dCol), AntLogger::LOG_MISSION);
			$game->issueOrder($ant->row, $ant->col, $direction);
			//$game->map[$row][$col] = LAND;											// myMap->update()
			$ant->pos = array($dRow, $dCol);										// myMap->update()
			return $direction;
		} else {
			// for some reason the path is blocked - another ant?, put the point 
			// back on the path and wait a turn.  After that?  Recalc?  Solution 
			// needs to avoid deadlock.
			array_unshift($this->path, $nextPt);
			$this->logger->write(sprintf("%s  Path point (%d, %d) blocked.", $this, $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
		}

		$this->logger->write(sprintf("%s", $ant) . ' has no where to go', AntLogger::LOG_MISSION);

		return false;

	} //move
	
	
} //  MissionGoToPoint



// end file