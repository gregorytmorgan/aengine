<?php

require_once 'State.php';
require_once 'missions/MissionGoToPoint.php';
require_once 'missions/MissionGoNESW.php';
require_once 'missions/MissionPatrol.php';

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

	public $logger;
	
	protected $debug;	

	protected $initialState;

	protected $endState;

	/**
	 * How many times a move has failed
	 * @var type
	 */
	protected $stuck = 0;

	/**
	 * Try something else after a while
	 *
	 * @var type
	 */
	protected $stuckThreshold = 2;

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
		global $STUCK_STATE;
		
		$this->id = (isset($args['id'])) ? $args['id'] : get_class($this) . Mission::$instance;
		$this->name = (isset($args['name'])) ?  $args['name'] : get_class($this) . ' #' . Mission::$instance;
		$this->debug = (isset($args['debug'])) ?  $args['debug'] : self::DEBUG_LEVEL_DEFAULT;
		$this->game = (isset($args['game'])) ?  $args['game'] : self::DEBUG_LEVEL_DEFAULT;
		
		$this->logger = new AntLogger(array(
			'logLevel' => $this->debug
		));

		$this->states = array(
			'init' => $INIT_STATE,
			'end' => $END_STATE,
			'stuck' => $STUCK_STATE,
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
	 * Move to a new square, do an action.
	 * 
	 * @param Ant $ant
	 * @param object $game Game data
	 * @return mixed
	 */
	function doTurn(Ant $ant, $game = array()) {
		$result = null;


		// check for events that might trigger a state change
		foreach ($this->state->events as $evt) {
			if (is_callable($evt['test']) || (isset($evt['test'][0]) && is_callable($evt['test'][0]))) {
				$nextState = false;
				if (is_callable($evt['test'])) {
					if ($evt['test']($ant, $game)) {
						$nextState = true;
					}
				} else {
					$args = array($ant, $game);
					if (isset($evt['test'][1]) && is_array($evt['test'][1])) {
						array_push($args, $evt['test'][1]);
					}
					if (call_user_func_array($evt['test'][0], $args)) {
						$nextState = true;
					}
				}
				if ($nextState) {
					$prevState = $this->state->name;
					$this->setState($evt['next']);
					$this->logger->write(sprintf("%s transitioning from state %s to state %s", $this->name, $prevState, $this->state->name), AntLogger::LOG_MISSION);
				}
			} else {
				$this->logger->write('Event test for state ' . sprintf("%s", $this->state) . ' is not callable', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			}
		}

		// do the action for the turn
		if ($this->state->action) {
			if (is_callable($this->state->action) || (isset($this->state->action[0]) && is_callable($this->state->action[0]))) {
				$this->logger->write($ant->name . ' - Firing ant action "' . $this->state->actionName . '"', AntLogger::LOG_GAME_FLOW | AntLogger::LOG_MISSION);
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
				$this->logger->write($ant->name . ' action is not callable(' . $this->state->actionName . ')', AntLogger::LOG_ERROR);
			}
		}		
		
		
		return $result;
	}	

	/**
	 * Move along a set of points. The patrol move path will be set in the transition event
	 *
	 * @param Ant $ant This ant.
	 * @param Ants $game is the Ants game data.
	 * @return string|false Returns the direction to move next turn on success, false if nowhere to go.
	 */
	function move (Ant $ant, Ants $game) {

		$nextPt = $this->getNextMove($ant, $game);

		// direction will be an empty array if pt0 == pt1
		$direction = $game->direction($ant->row, $ant->col, $nextPt[0], $nextPt[1]);

		if ($nextPt && $direction) {
			$direction = $game->direction($ant->row, $ant->col, $nextPt[0], $nextPt[1]);

			if ($ant->firstTurn % $game->viewradius === 0) {
				$game->terrainMap->updateView(array($nextPt[0], $nextPt[1]), Ants::LAND);
			}

			$this->logger->write(sprintf("%s moved %s to %d,%d", $ant->name, $direction[0], $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION);
			$game->issueOrder($ant->row, $ant->col, $direction[0]);
			$ant->pos = array($nextPt[0], $nextPt[1]);
			$stuck = 0;
			return true;
		}

		$this->logger->write(sprintf("%s", $ant) . ' has no where to go', AntLogger::LOG_MISSION);

		return false;

	} //move

	/**
	 * getNextMove
	 *
	 * @param Ant $ant
	 * @param Ants $game
	 * @return string|boolean
	 */
	protected function getNextMove(Ant $ant, Ants $game) {
		throw new Exception ('Override this function to return n|e|s|w');
	}

	/**
	 * printPath
	 * 
	 * A path is just an array of points (arrays).
	 * 
	 * @param array $path
	 * @return string
	 */
	public function printPath($path) {
		$str = '';
		if ($path) {
			foreach ($path as $p) {
				$str .= '(' . implode(',', $p) . ')';
			}
		}
		return $str;
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

// end file