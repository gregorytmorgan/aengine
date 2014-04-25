<?php

require_once 'State.php';
require_once 'missions/MissionGoToPoint.php';
require_once 'missions/MissionPatrol.php';

/**
 * Ant mission - simple state machine for ant actions.
 *
 * @author gmorgan
 */
class Mission {

	const DEBUG_LEVEL_DEFAULT = AntLogger::LOG_ALL;

	static $instance = 1;
	
	public $id;

	public $name;

	public $logger;
	
	protected $debug;	

	/**
	 * The initial state of the Mission - so we can Mission.reset()
	 *
	 * @var State
	 */
	protected $initialState;

	/**
	 * The current state of the Mission - so we can Mission.end().
	 *
	 * @var State
	 */
	protected $endState;

	/**
	 * The mission turn counter vs game turn
	 *
	 * @var integer
	 */
	protected $turn;

	/**
	 * How many times a move has failed.
	 *
	 * @var integer
	 */
	protected $stuck = 0;

	/**
	 * How many mission turns we're willing to be stuck for.
	 *
	 * @var integer
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
		global $MOVE_STATE;
		
		$this->id = (isset($args['id'])) ? $args['id'] : get_class($this) . Mission::$instance;
		$this->name = (isset($args['name'])) ?  $args['name'] : get_class($this) . ' #' . Mission::$instance;
		$this->debug = (isset($args['debug'])) ?  $args['debug'] : self::DEBUG_LEVEL_DEFAULT;
		$this->game = (isset($args['game'])) ?  $args['game'] : self::DEBUG_LEVEL_DEFAULT;
		
		$this->logger = new AntLogger(array(
			'logLevel' => $this->debug
		));

		$this->initialState = $INIT_STATE;
		$this->endState = $END_STATE;
		$this->state = $INIT_STATE;

		$this->states = array(
			'init' => $INIT_STATE,
			'move' => $MOVE_STATE,
			'end' => $END_STATE,
			'stuck' => $STUCK_STATE,
		);

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_MISSION);
		
		Mission::$instance++;
	}

	/**
	 * Start the mission over.
	 */
	public function reset() {
		$this->setState = $states[$this->initialState];
	}

	/**
	 * End the mission.
	 */
	public function end() {
		$this->setState = $states[$this->initialState];
	}


	/**
	 * Return the current Mission state.
	 * 
	 * @return State
	 */
	public function getState() {
		return $this->state;
	}

	/**
	 * Set the current Mission state.
	 *
	 * @param State|string $state State or state id.
	 * @return boolean Return true on success, false otherwise.
	 */
	public function setState($state) {
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
	 * Do a turn for an ant. 
	 *
	 * Move to a new square, do an action.  Called called by bot/game loop.
	 * 
	 * @param Ant $ant
	 * @param object $game Game data
	 * @return mixed
	 */
	public function doTurn(Ant $ant, $game = array()) {
		$result = null;

		if ($this->state->id === $this->endState->id) {
			$this->logger->write($ant->name . " - is in it's end state.  Skipping turn ", AntLogger::LOG_GAME_FLOW | AntLogger::LOG_MISSION);
			return true;
		}

		// do the action for the turn
		if ($this->state->action) {
			if (is_callable($this->state->action) || (isset($this->state->action[0]) && is_callable($this->state->action[0]))) {
				$this->logger->write($ant->name . ' - Firing ant action "' . $this->state->actionName . '"', AntLogger::LOG_GAME_FLOW | AntLogger::LOG_MISSION);
				if (is_callable($this->state->action)) {
					$result = call_user_func_array($this->state->action, array($ant, $this, $game));
				} else {
					$args = array($ant, $this, $game);
					if (isset($this->state->action[1]) && is_array($this->state->action[1])) {
						array_push($args, $this->state->action[1]);
					}
					$result = call_user_func_array($this->state->action[0], $args);
				}
			} else {
				$this->logger->write($ant->name . ' action is not callable(' . $this->state->actionName . ')', AntLogger::LOG_ERROR);
			}
		}

		// check for events that might trigger a state change
		foreach ($this->state->events as $evt) {
			if (is_callable($evt['test']) || (isset($evt['test'][0]) && is_callable($evt['test'][0]))) {
				$nextState = false;
				if (is_callable($evt['test'])) {
					if ($evt['test']($ant, $this, $game)) {
						$nextState = true;
					}
				} else {
					$args = array($ant, $this, $game);
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

		return $result;
	}	

	/**
	 * The default 'move' method.
	 *
	 * @param Ant $ant This ant.
	 * @param Ants $game is the Ants game data.
	 * @return Ouputs a game direction command to STDOUT on success, returns false otherwise.
	 */
	public function move (Ant $ant, Mission $mission, Ants $game) {

		$nextPt = $this->getNextMove($ant, $mission, $game);

		if (!$nextPt) {
			$this->logger->write($ant->name . ' getNextMove() did not return a move.', AntLogger::LOG_MISSION);
			return false;
		}

		// direction will be an empty array if pt0 == pt1
		$direction = $game->direction($ant->row, $ant->col, $nextPt[0], $nextPt[1]);

		if ($nextPt && $direction) {
			$direction = $game->direction($ant->row, $ant->col, $nextPt[0], $nextPt[1]);
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
	protected function getNextMove(Ant $ant, Mission $mission, Ants $game) {

		$directions = array(
			array($ant->row - 1, $ant->col),	// n
			array($ant->row, $ant->col + 1),	// e
			array($ant->row + 1, $ant->col),	// s
			array($ant->row, $ant->col - 1)		// w
		);

		for ($i = 0; $i < 4; $i++) {
			$nextPt = $directions[$i];
			if ($game->passable($nextPt[0], $nextPt[1])) {
				return $directions[$i];
			}
		}

		// for some reason the path is blocked

		$this->stuck++;

		$this->logger->write(sprintf("%s Path (%d, %d) blocked.", $this, $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);

		if ($this->stuck > $this->stuckThreshold) {
			$this->logger->write(sprintf("%s  is stuck on path point (%d, %d). Count:%d.", $ant, $nextPt[0], $nextPt[1], $this->stuck), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
			$this->setState('end');
		}

		return false;
	}
	
	/**
	 * Stringify a Mission object
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
