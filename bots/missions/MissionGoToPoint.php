<?php

//require_once 'Mission.php';
//require_once 'State.php';

/**
 * Ant mission to go a point on the map.
 *
 * @author gmorgan
 */
class MissionGoToPoint extends Mission {

	public $goalPt = null;

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
		global $STUCK_STATE;
		
		$init_state = new State(array(
			'id' => 'init',
			'name' => 'Initialized',
			'action' => array($this, 'init'),
			'actionName' => 'Initialize Mission',
			'events' => array(
				array(
					'test' => function ($ant = false, $mission = false, $game = false, $args = false) { return true; },
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
					'test' => array(
						function ($ant = false, $mission = false, $game = false, $args = false) {
$mission->logger->write(sprintf('Move state test, ant(%d,%d), goal(%d,%d)', $ant->row, $ant->col, $mission->goalPt[0], $mission->goalPt[1]));
							return $mission->goalPt === $ant->pos; },
						array($this)
					),
					'next' => 'end'
				)
			),
			'debug' => $this->debug
		));

		$this->states = array(
			'init' => $init_state,
			'moving' => $move_state,
			'end' => $END_STATE,
			'stuck' => $STUCK_STATE,
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
	function init (Ant $ant, Mission $mission, Ants $game) {

//$this->logger->write(sprintf("Mission init 1 %d,%d  %d,%d-------------------------------------------",$ant->row, $ant->col, $this->goalPt[0], $this->goalPt[1]));

		$path = $game->terrainMap->findPath(array($ant->row, $ant->col), $this->goalPt);

		if (!$path) {
			$this->logger->write(sprintf("State init - pathFind failed for (%d,%d) to (%d,%d)  Using current point.", $ant->row, $ant->col, $this->goalPt[0], $this->goalPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			//$path = array(array($ant->row, $ant->col));
			$this->path = false;
		}

//$this->logger->write('path: ' . var_export($path, true));

		$this->path = $path;
	}

	/**
	 * getNextMove
	 *
	 * @param Ant $ant
	 * @param Ants $game
	 * @return array Turn structure ['ant' => ant, 'status' => status, 'value' => data, 'move' => array(r,c) ]
	 */
	protected function getNextMove(Ant $ant, Mission $mission, Ants $game) {
		
		if (!$this->path) {
			$this->logger->write(sprintf("%s", $this) . ' Empty path.', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			
			return parent::getNextMove($ant, $mission, $game);
			
//			return array(
//				'ant' => $ant,
//				'status' => Ants::TURN_FAIL,
//				'value' => false,
//				'move' => false
//			);
		}

		$nextPt = array_shift($this->path);

		if (!$nextPt) {
			$this->logger->write(sprintf("%s", $this) . ' SHIFT FAILED?.', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			return array(
				'ant' => $ant,
				'status' => Ants::TURN_FAIL,
				'value' => false,
				'move' => false
			);
		}
		
		// use old game passable for now.  Future, use terrianMap?
		$passable = $game->passable($nextPt[0], $nextPt[1]);
		
		if ($game->mapGet(array($nextPt[0], $nextPt[1])) === Ants::MY_ANT) {
			// if it's not passable because one of my ants, defer
			// $defer = ['ant' => ant, 'status' => status, 'value' => data, 'move' => array(r,c) ]
			$defer = array(
				'ant' => $ant,
				'status' => Ants::TURN_DEFER,
				'value' => null,
				'move' => array($nextPt[0], $nextPt[1])
			);
			return $defer;
		}

		if ($passable) {
			// theres probably a better place for this
			if (($ant->firstTurn % $game->viewradius) === 0) {
				$game->terrainMap->updateView(array($nextPt[0], $nextPt[1]), Ants::LAND);
			}
			return array(
				'ant' => $ant,
				'status' => Ants::TURN_OK,
				'value' => false,
				'move' => array($nextPt[0], $nextPt[1])
			);
		}

		$this->logger->write(sprintf("%s  Path point (%d, %d) blocked.", $this->name, $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);

		// for some reason the path is blocked - another ant?, put the point
		// back on the path and wait a turn.  After that?  Recalc?  Solution
		// needs to avoid deadlock.
		$this->stuck++;

		array_unshift($this->path, $nextPt);

		$this->logger->write(sprintf("%s Path (%d, %d) blocked.", $this, $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);

		if ($this->stuck > $this->stuckThreshold) {
			$this->logger->write(sprintf("%s  is stuck on path point (%d, %d). Count:%d.", $ant, $nextPt[0], $nextPt[1], $this->stuck), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
		}

		if ($this->stuck > $this->stuckThreshold) {
			$this->logger->write(sprintf("%s  is stuck on path point (%d, %d). Count:%d.", $ant, $nextPt[0], $nextPt[1], $this->stuck), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
		}			
		
		return array(
			'ant' => $ant,
			'status' => Ants::TURN_FAIL,
			'value' => false,
			'move' => array($nextPt[0], $nextPt[1])
		);
	}
		
		
} //  MissionGoToPoint


// end file