<?php

require_once 'Ants.php';

/**
 * GTM Bot
 */
class BaseBot {
    private $directions = array('n','e','s','w');

    /**
     * doTurn
     */
    public function doTurn($ants) {
        foreach ($ants->myAnts as $i => $ant) {
            list ($aRow, $aCol) = $ant->pos;
            foreach ($this->directions as $direction) {
                list($dRow, $dCol) = $ants->destination($aRow, $aCol, $direction);
				$passable = $ants->passable($dRow, $dCol);
                if ($passable) {
                    $ants->issueOrder($aRow, $aCol, $direction);
					$ant->logger->write(sprintf("%s", $ant) . ' moved ' . $direction . ' to ' . $dRow . ', ' . $dCol, AntLogger::LOG_GAME_FLOW);
					$ants->myAnts[$i]->pos = array($dRow, $dCol);
                    break;
                }
            } // directions
			if (!$passable) {
				$ant->logger->write(sprintf("%s", $ant) . ' has no where to go', AntLogger::LOG_BOT);
			}
        }
    } // doTurn

}

// end file