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

        foreach ($game->myAnts as $i => $ant) {
			if ($game->myAnts[$i]->mission) {
				$results = $game->myAnts[$i]->mission->doTurn($game->myAnts[$i], $game);
			} else {
				$game->logger->write($game->myAnts[$i]->name . ' has no mission.', AntLogger::LOG_WARN);
			}
        } // each ant

		$game->logger->write('doTurns complete.', AntLogger::LOG_GAME_FLOW);
    } // doTurn

}

// end file