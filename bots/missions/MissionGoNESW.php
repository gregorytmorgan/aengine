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
