<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of MissionPatrol
 *
 * @author gmorgan
 */
class MissionPatrol extends Mission{

	public $centerPt = null;

	protected $path = null;

	/**
	 * Call the parent constructor, then redefine the mission states
	 *
	 * @param array $args
	 */
	function __construct($args = array()) {

		parent::__construct($args);

		$this->centerPt = (isset($args['centerPt'])) ?  $args['centerPt'] : null;
		$this->radius = (isset($args['radius'])) ?  $args['radius'] : null;

		if (!$this->centerPt) {
			$this->logger->write(sprintf("%s Bad center point.", $this), AntLogger::LOG_ANT | AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
		}

		global $END_STATE;

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
					'test' => array(
						function ($ant, $data = array(), $arg) { return false; },
						array($this)
					),
					'next' => 'patrol'
				)
			),
			'debug' => $this->debug
		));

		$patrol_state = new State(array(
			'id' => 'patrol',
			'name' => 'patroling',
			'action' => array($this, 'patrol'),
			'actionName' => 'Patrol next',
			'events' => array (
				array(
					'test' => array(
						function ($ant, $data = array(), $arg) { return false; },
						array($this)
					),
					'next' => 'patrol'
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
	 * For a patrol mission, init goes from start point to patrol path
	 *
	 * @param Ant $ant This ant.
	 * @param Ants $game is the Ants game data.
	 * @return boolean
	 */
	function init (Ant $ant, Ants $game) {
		$angle = rand() * 6.28;
		//$patrolStart = array(cos($angle) * $this->radius, sin($angle) * $this->radius);
		$patrolStart = array(2,8);

		$path = $game->terrainMap->findPath(array($ant->row, $ant->col), $patrolStart);

$str = '';
if ($path) {
foreach ($path as $p) {
  $str .= '(' . implode(',', $p) . ')';
}
}

$this->logger->write('path:' . $str);

		if (!$path) {
			$this->logger->write(sprintf("State init - pathFind failed for (%d,%d) to (%d,%d)", $ant->row, $ant->col, $patrolStart[0], $patrolStart[1]), AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			//$path = array(array($ant->row, $ant->col));
			$this->path = false;
			return false;
		}

		$this->path = $path;

		$nextPt = array_shift($this->path);

		if (!$nextPt) {
			$this->logger->write(sprintf("%s", $this) . ' SHIFT FAILED?.', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			return false;
		}

		// from the current position, what coords result if we travel $direction?
		$direction = $game->direction($ant->row, $ant->col, $nextPt[0], $nextPt[1]);

		// is the dest coord ok?
		//$passable = $game->passable($nextPt[0], $nextPt[1]);
		$passable = $game->terrainMap->passible(array($nextPt[0], $nextPt[1]));

		// myMap->passable()
		if ($passable) {
			$this->logger->write(sprintf("%s %s moved %s to %d,%d", $ant->name, $this, $direction[0], $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION);
			$game->issueOrder($ant->row, $ant->col, $direction[0]);
			$ant->pos = array($nextPt[0], $nextPt[1]);
			$stuck = 0;
			return true;
		} else {
			// for some reason the path is blocked - another ant?, put the point
			// back on the path and wait a turn.  After that?  Recalc?  Solution
			// needs to avoid deadlock.
			$stuck++;
			array_unshift($this->path, $nextPt);
			$this->logger->write(sprintf("%s  Path point (%d, %d) blocked.", $this, $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);

			if ($stuck > $this->stuckThreshold) {
				$this->logger->write(sprintf("%s  is stuck on path point (%d, %d). Count:%d.", $ant, $nextPt[0], $nextPt[1], $stuck), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
			}

			// move to stuck state

		}

		$this->logger->write(sprintf("%s", $ant) . ' has no where to go', AntLogger::LOG_MISSION);
		return false;
	}

	/**
	 * Move along a set of waypoints. The patrol move path will be set in the transition event
	 *
	 * @param Ant $ant This ant.
	 * @param Ants $game is the Ants game data.
	 * @return string|false Returns the direction to move next turn on success, false if nowhere to go.
	 */
//	function move ($ant, Ants $game) {
//
//		if (!$this->path) {
//			$this->logger->write(sprintf("%s", $this) . ' Empty path.', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
//			return false;
//		}
//
//		$direction = $game->direction($ant->row, $ant->col, $nextPt[0], $nextPt[1]);
//
//		// is the dest coord ok?
//		$passable = $game->passable($nextPt[0], $nextPt[1]);									// myMap->passable()
//		if ($passable) {
//
//			if ($ant->firstTurn % $game->viewradius === 0) {
//				$game->terrainMap->updateView(array($nextPt[0], $nextPt[1]), Ants::LAND);
//			}
//
//			$this->logger->write(sprintf("%s %s moved %s to %d,%d", $ant->name, $this, $direction[0], $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION);
//			$game->issueOrder($ant->row, $ant->col, $direction[0]);
//			//$game->map[$row][$col] = LAND;											// myMap->update()
//			$ant->pos = array($nextPt[0], $nextPt[1]);										// myMap->update()
//			return $direction;
//		} else {
//			// for some reason the path is blocked - another ant? or food?, put the point
//			// back on the path and wait a turn.  After that?  Recalc?  Solution
//			// needs to avoid deadlock.
//			array_unshift($this->path, $nextPt);
//			$this->logger->write(sprintf("%s  Path point (%d, %d) blocked.", $this, $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
//		}
//
//		$this->logger->write(sprintf("%s", $ant) . ' has no where to go', AntLogger::LOG_MISSION);
//
//		return false;
//
//	} //move

	/**
	 * getNextMove
	 *
	 * @param Ant $ant
	 * @param Ants $game
	 * @return array|boolean Return the point for the next move
	 */
	protected function getNextMove(Ant $ant, Ants $game) {

		if (!$this->path) {
			$this->logger->write(sprintf("%s", $this) . ' Empty path.', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			return false;
		}

		$nextPt = array_shift($this->path);

		if (!$nextPt) {
			$this->logger->write(sprintf("%s", $this) . ' SHIFT FAILED?.', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
			return false;
		}

		array_unshift($this->path, $nextPt);

		$this->logger->write(sprintf("%s  Path point (%d, %d) blocked.", $this, $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);

		$passable = $game->passable($nextPt[0], $nextPt[1]);

		if ($passable) {
			return array($nextPt[0], $nextPt[1]);
		}

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

		return false;
	}


} //  MissionGoToPoint