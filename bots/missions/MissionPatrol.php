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

	public $path = null;

	public $radius;

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
		global $DEAD_STATE;

		$init_state = new State(array(
			'id' => 'init',
			'name' => 'Initialized',
			'action' => array($this, 'init'),
			'actionName' => 'Initialize Mission',
			'events' => array(
				array(
					'test' => array(
						function ($ant, $data = array()) {
							return !emtpy($arg[0]->path);
						},
						array($this)
					),
					'next' => 'moving'
				),
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
						function ($ant, $data = array(), $arg) { 
							//return !$arg[0]->path;
							return true;
						},
						array($this)
					),
					'next' => 'patrol'
				)
			),
							
//				array(
//					'test' => array(
//						function ($ant, $data = array(), $arg) {
//$arg[0]->logger->write(sprintf('Move state test, ant(%d,%d), goal(%d,%d)', $ant->row, $ant->col, $arg[0]->goalPt[0], $arg[0]->goalPt[1]));
//							return $arg[0]->goalPt === $ant->pos; },
//						array($this)
//					),
//					'next' => 'end'
//				)							
							
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
						function ($ant, $data = array(), $arg) { 
							return true;
						},
						array($this)
					),
					'next' => 'move'
				)
			),
			'debug' => $this->debug
		));

		$this->states = array(
			'init' => $init_state,
			'moving' => $move_state,
			'patrol' => $patrol_state,
			'end' => $END_STATE
		);

		$initialState = $init_state;
		$endState = $END_STATE;

		$this->state = $init_state;

		$this->logger->write(sprintf("%s Initialized", $this), AntLogger::LOG_MISSION | AntLogger::LOG_MISSION);
	}

	/**
	 * State - For a patrol mission, init goes from start point to patrol path
	 *
	 * 1) get path in random direction from here to for patrolradius/2
	 * 2) create 4 patrol waypoints using radius
	 *
	 * @param Ant $ant This ant.
	 * @param Ants $game is the Ants game data.
	 * @return boolean
	 */
	function init (Ant $ant, Ants $game) {

//		$angle = rand(0, 1) * 6.28;
//		$patrolStart = $game->terrainMap->gridWrap(array(abs(cos($angle) * ($ant->row - 1)), abs(sin($angle) * ($ant->col - 1))));
//		$attempts = 0;
//		while (!$game->passable($patrolStart[0], $patrolStart[1]) && $attempts++ < 10) {
//			$angle = rand(0, 1) * 6.28;
//			$patrolStart = $game->terrainMap->gridWrap(array(abs(cos($angle) * ($ant->row - 1)), abs(sin($angle) * ($ant->col - 1))));
//		}
//		$this->logger->write(sprintf($ant->name . " - PatrolStart: %d,%d", $ant->row, $ant->col), AntLogger::LOG_MISSION);

		$this->path = array();
		$this->waypoints = array();

		// get 4 point, then stich together with find path

		$it = 0;
		while (count($this->waypoints) < 4 && $it++ < 100) {
			$r = $ant->row + rand($this->radius, $this->radius * 2);
			$c = $ant->col + rand($this->radius, $this->radius * 2);
			if ($game->passable($r, $c)) {
				 $wp = array('pt' => array($r, $c), 'path' => null);
			} else {
				continue;
			}

			if ($it === 100) {
				throw new Exception('Could generate waypoints');
			}

			//$this->logger->write("wp:" . var_export($this->waypoints, true));


			$startPt = (empty($this->waypoints)) ? array($ant->row, $ant->col) : $this->waypoints[count($this->waypoints) - 1]['pt'];

			$this->logger->write("startPt:" . var_export($startPt, true));


			$path = $game->terrainMap->findPath($startPt, $wp['pt']);

			if (!empty($path)) {
				$wp['path'] = $path;
				$this->waypoints[] = $wp ;
			}
		}

		$str = '';
		foreach ($this->waypoints as $node) {
				$str .= "(" . implode(",", $node['path']) . ") ";
			}
		return $str;
		$this->logger->write("Waypoints:" . $str);

//		$halfRad = round($this->radius/2);
//		$this->path = array(
//			array($ant->row + $halfRad, $ant->col + $halfRad),
//			array($ant->row - $halfRad, $ant->col + $halfRad),
//			array($ant->row + $halfRad, $ant->col - $halfRad),
//			array($ant->row - $halfRad, $ant->col - $halfRad),
//		);

		$patrolStart = $this->path[0];
		$this->centerPt = array($ant->row, $ant->col);

		//$path = $game->terrainMap->findPath($this->centerPt, $patrolStart);
		//$game->terrainMap->plotPath($game->terrainMap->grid,  $path, AntLogger::LOG_MISSION);
		//$this->logger->write(sprintf("Path: %s", $this->printPath($path)), AntLogger::LOG_MISSION);

//		if (!$path) {
//			$this->logger->write(sprintf("State init - pathFind failed for (%d,%d) to (%d,%d)", $ant->row, $ant->col, $patrolStart[0], $patrolStart[1]), AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
//			//$path = array(array($ant->row, $ant->col));
//			$this->path = false;
//			return false;
//		}


		$this->move($ant, $game);

		

//		$nextPt = array_shift($this->path);
//
//		if (!$nextPt) {
//			$this->logger->write(sprintf("%s", $this) . ' SHIFT FAILED?.', AntLogger::LOG_MISSION | AntLogger::LOG_ERROR);
//			return false;
//		}
//
//		// from the current position, what coords result if we travel $direction?
//		$direction = $game->direction($ant->row, $ant->col, $nextPt[0], $nextPt[1]);
//
//		// is the dest coord ok?
//		//$passable = $game->passable($nextPt[0], $nextPt[1]);
//		$passable = $game->terrainMap->passable(array($nextPt[0], $nextPt[1]));
//
//		// myMap->passable()
//		if ($passable) {
//			$this->logger->write(sprintf("%s %s moved %s to %d,%d", $ant->name, $this, $direction[0], $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION);
//			$game->issueOrder($ant->row, $ant->col, $direction[0]);
//			$ant->pos = array($nextPt[0], $nextPt[1]);
//			$stuck = 0;
//			return true;
//		} else {
//			// for some reason the path is blocked - another ant?, put the point
//			// back on the path and wait a turn.  After that?  Recalc?  Solution
//			// needs to avoid deadlock.
//			$stuck++;
//			array_unshift($this->path, $nextPt);
//			$this->logger->write(sprintf("%s  Path point (%d, %d) blocked.", $this, $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
//
//			if ($stuck > $this->stuckThreshold) {
//				$this->logger->write(sprintf("%s  is stuck on path point (%d, %d). Count:%d.", $ant, $nextPt[0], $nextPt[1], $stuck), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
//			}
//
//			// move to stuck state
//		}
//
//		$this->logger->write(sprintf("%s", $ant) . ' has no where to go', AntLogger::LOG_MISSION);

		return false;
	}

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

		$passable = $game->terrainMap->passable($nextPt[0], $nextPt[1]);

		if ($passable) {
			return array($nextPt[0], $nextPt[1]);
		}

		// for some reason the path is blocked - another ant?, put the point
		// back on the path and wait a turn.  After that?  Recalc?  Solution
		// needs to avoid deadlock.
		array_unshift($this->path, $nextPt);
		$this->logger->write(sprintf("%s  Path point (%d, %d) blocked.", $this, $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
		$this->stuck++;

		if ($this->stuck > $this->stuckThreshold) {
			$this->logger->write(sprintf("%s  is stuck on path point (%d, %d). Count:%d.", $ant, $nextPt[0], $nextPt[1], $this->stuck), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
		}

		if ($this->stuck > $this->stuckThreshold) {
			$this->logger->write(sprintf("%s  is stuck on path point (%d, %d). Count:%d.", $ant, $nextPt[0], $nextPt[1], $this->stuck), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
		}

		return false;
	}

	/**
	 * Do patrol action
	 * 
	 * Attack?
	 * Gather food?
	 * 
	 * @param Ant $ant
	 * @param Ants $game
	 * @return boolean
	 */
	public function patrol(Ant $ant, Ants $game) {
		$this->logger->write($ant->name . ' - Doing the patrol action.', AntLogger::LOG_MISSION);

		if (empty( $this->path)) {
			$this->setState('init');
		}

		return true;
	}
	
} //  MissionGoToPoint