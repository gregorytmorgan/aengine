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
		
        foreach ($game->myAnts as $i => $ant) {
			if ($game->myAnts[$i]->mission) {
				$antTurnStart = $game->getElapsedTime();
				$results = $game->myAnts[$i]->mission->doTurn($game->myAnts[$i], $game);
				$game->logger->write($ant->name . ' - Elaspsed turn time: ' .  number_format($game->getElapsedTime() - $antTurnStart, 2) . 'ms', AntLogger::LOG_GAME_FLOW);
			} else {
				$game->logger->write($game->myAnts[$i]->name . ' has no mission.', AntLogger::LOG_WARN);
			}
			
        } // each ant

		$game->logger->write('doTurns complete. Total elaspsed turn time:' . number_format($game->getElapsedTime() - $doTurnsStart, 2) . 'ms', AntLogger::LOG_GAME_FLOW);
    } // doTurn

}

// end file