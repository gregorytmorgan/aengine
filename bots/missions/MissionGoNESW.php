<?php

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
//	function move ($ant, Ants $game) {
//		$directions = array('n', 'e', 's', 'w');
//
//		foreach ($directions as $direction) {
//
//			// from the current position, what coords result if we travel $direction?
//			list($dRow, $dCol) = $game->destination($ant->row, $ant->col, $direction);	// myMap->destination()
//
//			// is the dest coord ok?
//			$passable = $game->passable($dRow, $dCol);									// myMap->passable()
//			if ($passable) {
//
//				if ($ant->firstTurn % $game->viewradius === 0) {
//					$game->terrainMap->updateView(array($dRow, $dCol), Ants::LAND);
//				}
//
//				$this->logger->write(sprintf("%s %s moved %s to %d,%d", $ant->name, $this, $direction, $dRow, $dCol), AntLogger::LOG_MISSION);
//				$game->issueOrder($ant->row, $ant->col, $direction);
//				//$game->map[$row][$col] = LAND;											// myMap->update()
//				$ant->pos = array($dRow, $dCol);										// myMap->update()
//				return $direction;
//			}
//		} // directions
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

		// for some reason the path is blocked - another ant?
		
		$this->stuck++;

		$this->logger->write(sprintf("%s Path (%d, %d) blocked.", $this, $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);

		if ($this->stuck > $this->stuckThreshold) {
			$this->logger->write(sprintf("%s  is stuck on path point (%d, %d). Count:%d.", $ant, $nextPt[0], $nextPt[1], $this->stuck), AntLogger::LOG_MISSION | AntLogger::LOG_WARN);
		}

		return false;
	}

}

// end file
