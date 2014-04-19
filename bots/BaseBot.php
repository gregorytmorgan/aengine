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
			 $results = $game->myAnts[$i]->mission->doTurn($game->myAnts[$i], $game);
        } // each ant

		$game->logger->write('doTurns complete.', AntLogger::LOG_GAME_FLOW);
    } // doTurn

}

// end file