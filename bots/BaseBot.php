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
					
					$game->logger->write(sprintf('Deferring %s move to (%d,%d)',  $ant->name, $result['move'][0], $result['move'][1]), AntLogger::LOG_GAME_FLOW);
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
		
		$game->logger->write('Starting resolution for ' . count($deferredTurns) . ' deferred turns.', AntLogger::LOG_GAME_FLOW);

		$deferredTurns2 = array();

		// resolve follow - reversing will fix 
		while ($dturn = array_pop($deferredTurns)) {
			$ant = $dturn['ant'];
			
			// result = [ 'status' => status, 'value' => data, 'move' => array(r,c) ]
			$result = $ant->mission->doTurn($ant, $game);		

			if ($result['status'] === Ants::TURN_DEFER) {
				$deferredTurns2[] = $result;
				continue;
			}
			$game->logger->write(sprintf('Resoved %s (%d,%d) follow collision.',  $ant->name, $ant->row, $ant->col),AntLogger::LOG_GAME_FLOW);
		}

		// resolve pass
		foreach ($deferredTurns2 as $dturnIdx => $dturn) {
			$ant1 = $dturn['ant'];
			$nextPt1 = $dturn['move'];
			foreach ($deferredTurns2 as $k => $t) {
				$ant2 = $t['ant'];
				$nextPt2 = $dturn['move'];
				if (($ant1->pos === $nextPt2) && ($ant2->pos === $nextPt1)) {

					// manually issue the order - this should be abstracted out to ... somewhere.

					$this->logger->write(sprintf("Resoving %s deferred moved %s to %d,%d", $ant1->name, $direction[0], $nextPt1[0], $nextPt1[1]), AntLogger::LOG_MISSION);
					$direction = $game->direction($ant1->row, $ant1->col, $nextPt1[0], $nextPt1[1]);
					$game->issueOrder($ant1->row, $ant1->col, $direction[0]);
					$ant1->pos = array($nextPt1[0], $nextPt1[1]);

					$this->logger->write(sprintf("Resoving %s deferred moved %s to %d,%d", $ant2->name, $direction[0], $nextPt2[0], $nextPt2[1]), AntLogger::LOG_MISSION);
					$direction = $game->direction($ant2->row, $ant2->col, $nextPt2[0], $nextPt2[1]);
					$game->issueOrder($ant2->row, $ant2->col, $direction[0]);
					$ant2->pos = array($nextPt2[0], $nextPt2[1]);

					// remove the ant2 deferred turn as well
					array_splice($deferredTurns, $k, 1);

					$game->logger->write(sprintf('Resoved $s (%d,%d) $s(%d,%d) pass collision.', $ant1->name, $ant1->row, $ant1->col,  $ant2->name, $ant2->row, $ant2->col),AntLogger::LOG_GAME_FLOW);
				}
			} // foreach inner

			array_splice($deferredTurns2, $dturnIdx, 1);
		} // foreach outer

		$game->logger->write(count($deferredTurns2) . ' Unresolved deferred turns.', AntLogger::LOG_GAME_FLOW);
		
		$game->logger->write('doTurns complete. Total elaspsed turn time:' . number_format($game->getElapsedTime() - $doTurnsStart, 2) . 'ms', AntLogger::LOG_GAME_FLOW);
    } // doTurn

}

// end file