<?php

require_once 'Ants.php';


/**
 * GTM Bot
 */
class BaseBot {

    /**
     * Bot turn loop
     */
    public function doTurn($ants) {
        foreach ($ants->myAnts as $i => $ant) {
			 $results = $ants->myAnts[$i]->doTurn($ants);
			 
//			 if ($command) {
//				$ants->issueOrder($ant->row,  $ant->col, $command);
//			 }
			 
			 //$ants->myAnts[$i]->pos = array();
        } // each ant	
    } // doTurn

}

// end file