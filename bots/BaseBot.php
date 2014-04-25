<?php

require_once 'Ants.php';


/**
 * GTM Bot
 */
class BaseBot {

    /**
     * Bot turn loop
	 * 
	 * @param Ants $game The game data
     */
    public function doTurn($game) {

		$game->logger->write('doTurns for ' . $game->nMyAnts . ' ants.', AntLogger::LOG_GAME_FLOW);

		$doTurnsStart = $game->getElapsedTime();
		
		$deferredTurns = array();

		foreach ($game->myAnts as $i => $ant) {

			if ($game->myAnts[$i]->mission) {
				$antTurnStart = $game->getElapsedTime();

				// result = ['ant' => ant, 'status' => status, 'value' => data, 'move' => array(r,c) ]
				$result = $game->myAnts[$i]->mission->doTurn($game->myAnts[$i], $game);		

				if ($result['status'] === Ants::TURN_DEFER) {
					//$result['ant'] = $game->myAnts[$i];
					$deferredTurns[] = $result;
				}

				$game->logger->write($ant->name . ' - Elaspsed turn time: ' .  number_format($game->getElapsedTime() - $antTurnStart, 2) . 'ms', AntLogger::LOG_GAME_FLOW);
			} else {
				$game->logger->write($game->myAnts[$i]->name . ' has no mission.', AntLogger::LOG_WARN);
			}

		} // each ant
			
		//
		// handle deferred turns
		//
		
		// resolve follow - reversing will fix 
		while ($dturn = array_pop($deferredTurns)) {
			$ant = $dturn['ant'];
			// result = [ 'status' => status, 'value' => data, 'move' => array(r,c) ]
			$result = $ant->mission->doTurn($ant, $game);		

			if ($turnResult['status'] === Ants::TURN_DEFER) {
				$deferredTurns[] = $result;
				continue;
			}
			$game->logger->write(sprintf('Resoved %s (%d,%d) follow collision.',  $ant->name, $ant->row, $ant->col),AntLogger::LOG_GAME_FLOW);
		}

		// resolve pass
		foreach ($deferredTurns as $dturnIdx => $dturn) {
			$ant1 = $dturn['ant'];
			$mv1 = $dturn['move'];
			foreach ($deferredTurns as $k => $t) {
				$ant2 = $t['ant'];
				$mv2 = $dturn['move'];
				if (($ant1->pos === $mv2) && ($ant2->pos === $mv1)) {

					// manually issue the order - this should be abstracted out to ... somewhere.

					$this->logger->write(sprintf("%s deferred moved %s to %d,%d", $ant->name, $direction[0], $nextPt[0], $nextPt[1]), AntLogger::LOG_MISSION);
					$direction = $game->direction($ant->row, $ant->col, $nextPt[0], $nextPt[1]); 
					$game->issueOrder($ant->row, $ant->col, $direction[0]);
					$ant->pos = array($nextPt[0], $nextPt[1]);

					// update the map tracking ant so the next ant doesn't move to the same location.  future: $game->antMap->set($nextPt[0], $nextPt[1], Ants::LAND) ...
					$game->mapSet($ant->row, $ant->col, Ants::LAND);  // how about hives? Are we tracking hives on the main map or just treating them as land?
					$game->mapSet($nextPt[0], $nextPt[1], Ants::ANTS);

					// remove the ant2 deferred turn as well
					array_splice($deferredTurns, $deferredTurns[$k], 1);

					$game->logger->write(sprintf('Resoved $s (%d,%d) $s(%d,%d) pass collision.', $ant1->name, $ant1->row, $ant1->col,  $ant2->name, $ant2->row, $ant2->col),AntLogger::LOG_GAME_FLOW);
				}
			} // foreach inner

			array_splice($deferredTurns, $deferredTurns[$dturnIdx], 1);
		} // foreach outer

		$game->logger->write('Unable to resolve ' . count($deferredTurns) . ' deferred turns.', AntLogger::LOG_GAME_FLOW);
		
		$game->logger->write('doTurns complete. Total elaspsed turn time:' . number_format($game->getElapsedTime() - $doTurnsStart, 2) . 'ms', AntLogger::LOG_GAME_FLOW);
    } // doTurn

}

// end file