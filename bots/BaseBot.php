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
    public function doTurn($ants)     {
        foreach ($ants->myAnts as $ant) {
            list ($aRow, $aCol) = $ant->pos;
            foreach ($this->directions as $direction) {
                list($dRow, $dCol) = $ants->destination($aRow, $aCol, $direction);
                if ($ants->passable($dRow, $dCol)) {
                    $ants->issueOrder($aRow, $aCol, $direction);
                    break;
                }
            }
        }
    }

}

// end file