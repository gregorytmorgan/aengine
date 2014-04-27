<?php
// sudo pear install --alldeps pear.php.net/Math_Vector-0.7.0
require_once "Math/Vector.php";
require_once "Math/Vector2.php";
require_once "Math/Vector3.php";
require_once "Math/VectorOp.php";

require_once "AntLogger.php";
require_once "Ant.php";
require_once "Map.php";

//define('MY_ANT', 0);
//define('ANTS', 0);
//define('DEAD', -1);		// LAND
//define('LAND', -2);		// LAND
//define('FOOD', -3);		// LAND
//define('HIVE', -4);		// LAND
//define('WATER', -5);	
//define('UNSEEN', -6);

//define('DEBUG_LEVEL', AntLogger::LOG_ALL);
define('DEBUG_LEVEL', AntLogger::LOG_MAPDUMP + AntLogger::LOG_GAME_FLOW + AntLogger::LOG_INPUT + AntLogger::LOG_OUTPUT); //   - AntLogger::LOG_INPUT - AntLogger::LOG_OUTPUT - AntLogger::LOG_MAPDUMP

/**
 * Ants
 */
class Ants {

	const MY_ANT = 0;
	const ANTS = 0;
	const DEAD = -1;		// LAND
	const LAND = -2;		// LAND
	const FOOD = -3;		// LAND
	const HIVE = -4;	// LAND
	const UNSEEN = -5;
	const WATER = -6;

	const TURN_DEFER = '_TURN_DEFER_';
	const TURN_OK = '_TURN_OK_';
	const TURN_FAIL = '_TURN_FAIL_';
	
	const Alpha = 'abcdefghijslmnopqrstuvwxyz';

    public $turns = 0;
	public $turn = 1;
    public $rows = 0;
    public $cols = 0;
    public $loadtime = 0;
    public $turntime = 0;

    public $viewradius2 = 0;
	public $viewradius = 0;

    public $attackradius2 = 0;
	public $attackradius = 0;

    public $spawnradius2 = 0;
	public $spawnradius = 0;

    public $map;
    public $myAnts = array();
	public $nMyAnts = 0;
    public $enemyAnts = array();
	public $nEnemyAnts = 0;
    public $myHills = array();
    public $enemyHills = array();
    public $deadAnts = array();

	public $gameStartTime = 0;
	public $terrainMap = null;
	public $antMap = null; // not used yet

	/**
	 * Food NOT be gathered
	 *
	 * [row, col, lastKnownTurn]
	 *
	 * @var array
	 */
	public $food = array();
	
	/**
	 * Food being gathered
	 * 
	 * [row, col, lastKnownTurn, antId]
	 *
	 * @var array
	 */
	public $foodTargets = array();

	
    public $AIM = array(
        'n' => array(-1, 0),
        'e' => array(0, 1),
        's' => array(1, 0),
        'w' => array(0, -1) );

    public $RIGHT = array (
        'n' => 'e',
        'e' => 's',
        's' => 'w',
        'w' => 'n');

    public $LEFT = array (
        'n' => 'w',
        'e' => 'n',
        's' => 'e',
        'w' => 's');

    public $BEHIND = array (
        'n' => 's',
        's' => 'n',
        'e' => 'w',
        'w' => 'e'
        );

	/**
	 * Logger
	 * 
	 * @var AntLogger
	 */
	public $logger = null;
	
	/**
	 * 
	 * @param array $args
	 */
	function __construct($args = array()) {
		
		// game start time
		list($usec, $sec) = explode(" ", microtime());
		$this->gameStartTime = (float)$sec + (float)$usec;
		
		$this->logger = new AntLogger(array(
			'logLevel' => DEBUG_LEVEL
		));
	}
	
    public function issueOrder($aRow, $aCol, $direction) {


		$pt = $this->gridWrap(array($aRow, $aCol));


		$this->logger->write(sprintf("Raw output: o %d %d %s", $pt[0], $pt[1], $direction), AntLogger::LOG_OUTPUT);
        printf("o %d %d %s\n", $pt[0], $pt[1], $direction);
        flush();
    }

    public function finishTurn() {
        echo("go\n");
        flush();
		$this->logger->write("Finished turn " . $this->turn, AntLogger::LOG_GAME_FLOW);
		$this->turn++;
    }
    
    public function setup($data) {
		
		$this->logger->write("Starting setup processing start for turn " . $this->turn, AntLogger::LOG_GAME_FLOW);
		
        foreach ($data as $line) {
            if (strlen($line) > 0) {
                $tokens = explode(' ',$line);
                $key = $tokens[0];
                if (property_exists($this, $key)) {
                    $this->{$key} = (int)$tokens[1];
                }
            }
        }

        for ($row = 0; $row < $this->rows; $row++) {
            for ($col = 0; $col < $this->cols; $col++) {
                $this->mapSet($row, $col, Ants::LAND);
            }
        }

		if ($this->viewradius2) {
			$this->viewradius = round(sqrt($this->viewradius2));
		}

		if ($this->attackradius2) {
			$this->attackradius = round(sqrt($this->attackradius2));
		}

		if ($this->spawnradius2) {
			$this->spawnradius = round(sqrt($this->attackradius2));
		}

		$this->terrainMap = new Map(array(
			'rows' => $this->rows,
			'columns' => $this->cols,
			'viewradius2' => $this->viewradius2,
			'defaultChar' => ($this->viewradius2) ? Ants::UNSEEN : Ants::LAND
		));

		$this->dumpGame(AntLogger::LOG_GAME_FLOW);
    }

    /**
     * update
     * 
     */
    public function update($data) {
		
		$this->logger->write("\n<GREEN>Starting update processing for turn " . $this->turn . '.</GREEN>', AntLogger::LOG_GAME_FLOW);

		$this->logger->write("Raw Input - turn " . $this->turn, AntLogger::LOG_INPUT);
		$this->logger->write("----------------", AntLogger::LOG_INPUT);
		$this->logger->write(implode("\n", $data), AntLogger::LOG_INPUT);

		$this->dumpTurn(AntLogger::LOG_GAME_FLOW);

        // clear ant and food data
        foreach ($this->myAnts as $ant) {
            list($row, $col) = $ant->ppos;
            $this->mapSet($row, $col, Ants::LAND);
        }

        foreach ($this->enemyAnts as $ant) {
            list($row, $col) = (is_array($ant)) ? $ant : $ant->pos;
            $this->mapSet($row, $col, Ants::LAND);
        }
        $this->enemyAnts = array();

        foreach ($this->deadAnts as $deadAnt) {	
            list($row, $col) = (is_array($deadAnt)) ? $ant : $deadAnt->pos;
            $this->mapSet($row, $col, Ants::LAND);
        }
        $this->deadAnts = array();

//        foreach ($this->food as $ant) {
//            list($row, $col) = $ant->pos;
//            $this->mapSet($row, $col, Ants::LAND);
//			$this->terrainMap->set(array($row, $col), Ants::LAND);
//        }

        //$this->food = array();
        $this->myHills = array();
        $this->enemyHills = array();

		$myAntCount = 0;

		// turn input processing
		//
        // update map and create new ant and food lists
		//
		// store long term?
        foreach ($data as $line) {
            if (strlen($line) > 0) {
                $tokens = explode(' ',$line);
                if (count($tokens) >= 3) {
                    $row = (int)$tokens[1];
                    $col = (int)$tokens[2];
                    if ($tokens[0] == 'a') {				// a = live ant, format: w row col owner
                        $owner = (int)$tokens[3];
						
						$this->mapSet($row, $col, $owner);
						
						if ($owner === 0) {
							$myAntCount++; // for lost ant check;
							$ant = $this->lookupAntByPosition($row, $col);
							if ($ant) {
								// ?
							} else {
								$ant = $this->getNewAnt($row, $col, $owner, $this);
								$this->addAnt($ant);
								$this->terrainMap->updateView(array($row, $col));
							}

							if (!$ant) {
								$this->logger->write("Lost ant at $row, $col", AntLogger::LOG_ERROR);
							}
							
                        } else {
							$this->enemyAnts[] = array($row, $col);
                        }
                    } elseif ($tokens[0] == 'f') {			// f = food, format: f row col
                        $this->mapSet($row, $col, Ants::FOOD);
						$this->terrainMap->set(array($row, $col), Ants::LAND);

						$idx = $this->lookupFood(array($row, $col));

						if ($idx !== false) {
							// food = [row, col, lastKnowTurn]
							$this->food[$idx][2] = $this->turn; // update food lastKnownTurn
						} else {
							$idx = $this->lookupTargetedFood(array($row, $col));
					
							if ($idx === false) {
								$this->food[] = array($row, $col, $this->turn);
							} else {
								$this->foodTargets[$idx][2] = $this->turn;
							}
						}
                    } elseif ($tokens[0] == 'w') {			// w = water, format: w row col
                        $this->mapSet($row, $col, Ants::WATER);
						$this->terrainMap->set(array($row, $col), Ants::WATER);
                    } elseif ($tokens[0] == 'd') {			// dead ant, format: d row col owner
						
						$this->terrainMap->set(array($row, $col), Ants::LAND);
						
						$owner = (int)$tokens[3];				
						
						$hiveOwner = $this->isHive($row, $col);
						if ($hiveOwner === false) {
							$this->mapSet($row, $col, Ants::LAND);
						} else {
							$this->mapSet($row, $col, $owner);
						}

						$this->killAnt(array($row, $col), $owner); // add to deadAnts
                    } elseif ($tokens[0] == 'h') {			// h = hill, format: w row col owner
                        $owner = (int)$tokens[3];
                        if ($owner === 0) {
                            $this->myHills[] = array($row, $col, $owner);
                        } else {
                            $this->enemyHills[] = array($row, $col, $owner);
                        }
						$this->terrainMap->set(array($row, $col), Ants::LAND);
                    }
                } // tokens >- 3
            } // not empty line
        } // each line

		$this->logger->write("Reconciling tracked items ...", AntLogger::LOG_GAME_FLOW);

		//
		// Reconciliation - For item we're tracking, remove the ones no longer listed by the server.
		// 
		// Tracked items:
		//		my ants
		//		ememy ant (NOT YET)
		//		food

		// Reconcile food. For untargeted food that was not timestamped this turn,
		// remove it from the list
		for ($i = 0, $len = count($this->food); $i < $len; $i++) {
			if ($this->food[$i][2] !== $this->turn) {

	$this->logger->write("Food at " . $this->food[$i][0] . "," . $this->food[$i][1] . " is gone.", AntLogger::LOG_GAME_FLOW);

				array_splice($this->food, $i, 1);

				// update the meta map
				if ($this->mapGet(array($this->food[$i][0], $this->food[$i][1])) === Ants::FOOD) {
					$this->mapSet(array($this->food[$i][0], $this->food[$i][1]), Ants::LAND);
				}
			}
			$i--;
			$len--;
		}

		// For targeted food, as above but pull the ant off the assignment
		for ($i = 0, $len = count($this->foodTargets); $i < $len; $i++) {
			if ($this->foodTargets[$i][2] !== $this->turn) {

				$this->logger->write("Food at " . $this->foodTargets[$i][0] . "," . $this->foodTargets[$i][1] . " being gathered by ant id " . $this->foodTargets[$i][3] . " is stale. LastKnownTurn: " . 	$this->foodTargets[$i][2], AntLogger::LOG_GAME_FLOW);

				// update the meta map
				if ($this->mapGet(array( $this->foodTargets[$i][0],  $this->foodTargets[$i][1])) === Ants::FOOD) {
					$this->mapSet(array( $this->foodTargets[$i][0],  $this->foodTargets[$i][1]), Ants::LAND);
				}

				// foodTargets = [row, col, lastKnownTurn, antId]
				$ant = $this->lookupAntById($this->foodTargets[$i][3]);
				
				if ($ant) {
					
					$this->logger->write("Food at " . $this->foodTargets[$i][0] . "," . $this->foodTargets[$i][1] . " being gathered by ant " . $ant->name . " is gone.  Resetting mission", AntLogger::LOG_GAME_FLOW);
					
					// give the default mission if no food, we'll reassign a better one later
					$ant->mission = new Mission(array(
						'debug' => DEBUG_LEVEL,
						'game' => $this,
					));
				} else {
					// maybe the ant died while looking for the food
					$this->logger->write("Ant with id " . $this->foodTargets[$i][3] . ' has disappeard while looking for food', AntLogger::LOG_GAME_FLOW | AntLogger::LOG_WARN);
				}

				// remove it, it's a stale entry
				array_splice($this->foodTargets, $i, 1);
				$i--;
				$len--;
			} // bad food
		}

		if ($myAntCount === count($this->myAnts)) {
			$this->logger->write("Ant count - ok.", AntLogger::LOG_GAME_FLOW);
		} else {
			$this->logger->write("Ant count - error.  Server says:" . $myAntCount . " Game count:" . count($this->myAnts), AntLogger::LOG_ERROR);
			$this->dumpAnts(AntLogger::LOG_ERROR);
		}

		$this->logger->write("Reconciling tracked items ... done.", AntLogger::LOG_GAME_FLOW);

		//
		// Mission Planning
		//
		// For any ant with a default mission (or none) then reassign to gather food
		//

		$this->logger->write("Mission planning started ...", AntLogger::LOG_GAME_FLOW);

		// missions
		// - defend point
		// - defend space
		// - rally to point
		// - attact
		// - scout

		// $this->updateMissions() ... based on game state/turn and the percentages of assigned missions
//		$missions = array();
//
//		while (!empty($missions) ) {
//
//			$nextMission =  array_shift($missions);
//
//			$assigned = false;
//			foreach ($this->myAnts as $antKey => $ant) {
//				if ($ant->mission->priority < $nextMission->priority) {
//					$nextMissionClassName = getClass($nextMission);
//					$missionConfig = $nextMissionClassName::setup($this, $ant, $this->terrainMap /* others? */);
//
////					$ant->mission = new $nextMissionClassName(array(
////						'debug' => DEBUG_LEVEL,
////						'game' => $this,
////						'startPt' => $ant->pos,
////						'goalPt' => $pair['food'],
////						'terrainMap' => $this->terrainMap
////					));
//
//					$ant->mission = new $nextMissionClassName($missionConfig);
//
//					unset($this->myAnts[$antKey]); // or splice
//
//					$assigned = true;
//					break;
//				}
//			} // each ant
//
//			if (!$assigned) {
//				array_shift($missions, $nextMission);
//			}
//
//		} // while missions



		//
		// start future $this->updateMissions()
		//

		$needsMission = array();
		foreach ($this->myAnts as $ant) {
			if (get_class($ant->mission) === 'Mission') {
				$needsMission[] = $ant;
			}
		}

		$this->logger->write("Remissioning " . count($needsMission) . " ants", AntLogger::LOG_GAME_FLOW);


		$antFoodMissionPairs = $this->getBestFoodTargets($needsMission);

//$this->logger->write('Ant - food pairs:');
//$this->logger->write(var_export($antFoodMissionPairs, true));

		foreach ($antFoodMissionPairs as $pair) {

			$ant = $this->lookupAntById($pair['ant']->id);

			$ant->mission = new MissionGoToPoint(array(
				'debug' => DEBUG_LEVEL,
				'game' => $this,
				'startPt' => $ant->pos,
				'goalPt' => $pair['food'],
				'terrainMap' => $this->terrainMap
			));

			$this->foodTargets[] = array($pair['food'][0], $pair['food'][1], $this->turn, $ant->id);

			$idx = $this->lookupFood($pair['food']);
			if ($idx !== false) {
				array_splice($this->food, $idx, 1);
			}
		}

		$this->logger->write("Mission planning ... done.", AntLogger::LOG_GAME_FLOW);

		//
		// end future $this->updateMissions()
		//



		$this->dumpMap(AntLogger::LOG_MAPDUMP);
		//$this->logger->write("Terrian Map:", AntLogger::LOG_MAPDUMP);
		//$this->logger->write($this->terrainMap, AntLogger::LOG_MAPDUMP);
		$this->logger->write("Food:\n Available food: " . count($this->food), AntLogger::LOG_GAME_FLOW);

		$str = "";
		foreach($this->foodTargets as $ft) {
			$str .= '(' . implode(',', $ft) . ') ';
		}
		$this->logger->write(" Targeted food: " . (($str) ? $str : '0'), AntLogger::LOG_GAME_FLOW);
		


		// end food gathering


		$this->logger->write("Update processing for turn " . $this->turn . " complete", AntLogger::LOG_GAME_FLOW);
    }

	/**
	 * passable
	 * 
	 * @param integer $row Row
	 * @param integer $col Column
	 * @return boolean
	 */
    public function passable($row, $col) {
		
		$mapVal = $this->mapGet($row, $col);
		
		$retval = ($mapVal > Ants::WATER) && ($mapVal !== Ants::MY_ANT);
		
$this->logger->write(sprintf("Ants.passable(%d,%d) = %d, result:%d", $row, $col, $mapVal, $retval ));
		
        return $retval;
    }

	/**
	 * unoccupied
	 * 
	 * @param integer $row Row
	 * @param integer $col Column
	 * @return boolean
	 */
    public function unoccupied($row, $col) {
        return in_array($this->mapGet($row, $col), array(Ants::LAND, Ants::DEAD));
    }

    /**
	 * destination
	 * 
	 * @param integer $row Row
	 * @param integer $col Column
	 * @param string $direction n|e|s|w
	 * @return array
	 */
    public function destination($row, $col, $direction) {
        list($dRow, $dCol) = $this->AIM[$direction];
        $nRow = ($row + $dRow) % $this->rows;
        $nCol = ($col + $dCol) % $this->cols;
        if ($nRow < 0) { 
			$nRow += $this->rows;
		}
        if ($nCol < 0) {
			$nCol += $this->cols;
		}
        return array($nRow, $nCol);
    }

	/**
	 * Distance between two cells taking into account board wrapping
	 *
	 * @param integer $row1
	 * @param integer $col1
	 * @param integer $row2
	 * @param integer $col2
	 * @return integer
	 */
    public function distance($row1, $col1, $row2, $col2) {
        $dRow = abs($row1 - $row2);
        $dCol = abs($col1 - $col2);

        $dRow = min($dRow, $this->rows - $dRow);
        $dCol = min($dCol, $this->cols - $dCol);

        return sqrt($dRow * $dRow + $dCol * $dCol);
    }

    /**
	 * Calc the direction(n, r, s, w) based on current square and next square.
     * 
	 * @param intger $row1 Start point row.
	 * @param intger $col1 Start point col.
	 * @param intger $row2 End point row.
	 * @param intger $col2 End point col.
	 * @return array Return [direction], where direction = 'n'|'e'|'s'|'w' 
	 */
    public function direction($row1, $col1, $row2, $col2) {

//$this->logger->write(sprintf("direction - entry %d,%d %d,%d", $row1, $col1, $row2, $col2), AntLogger::LOG_MISSION);

        $d = array();
        $row1 = $row1 % $this->rows;
        $row2 = $row2 % $this->rows;
        $col1 = $col1 % $this->cols;
        $col2 = $col2 % $this->cols;

        if ($row1 < $row2) {
            if ($row2 - $row1 >= $this->rows/2) {
                $d[]= 'n';
            }
            if ($row2 - $row1 <= $this->rows/2) {
                $d[]= 's';
            }
        } elseif ($row2 < $row1) {
            if ($row1 - $row2 >= $this->rows/2) {
                $d[]= 's';
            }
            if ($row1 - $row2 <= $this->rows/2) {
                $d[]= 'n';
            }
        }
        if ($col1 < $col2) {
            if ($col2 - $col1 >= $this->cols/2) {
                $d[]= 'w';
            }
            if ($col2 - $col1 <= $this->cols/2) {
                $d[]= 'e';
            }
        } elseif ($col2 < $col1) {
            if ($col1 - $col2 >= $this->cols/2) {
                $d[]= 'e';
            }
            if ($col1 - $col2 <= $this->cols/2) {
                $d[]= 'w';
            }
        }
        return $d;
    }

	/**
	 * Get a new Ant with a mission.
	 * 
	 * @param integer $row Row.
	 * @param integer $col Column.
	 * @param integer $owner Owner.
	 * @return Ant
	 */
	public function getNewAnt($row, $col, $owner, $game) {
		
			$dir = array('North', 'East', 'South', 'West');

			$nAnts = count($this->myAnts);
			
			switch($nAnts % 4) {
				case 0:	// N
					$goalPt = array(0, floor($game->cols/ 2));
					break;
				case 1:	// E
					$goalPt = array(floor($game->rows / 2), $game->cols - 1);
					break;
				case 2:	// S
					$goalPt = array($game->rows - 1, floor($game->cols / 2));
					break;
				case 3: // W
					$goalPt = array(floor($game->rows / 2), $game->cols - 1);
					break;
				default:
					break;				
			};

//			$pt = $this->getFoodTarget(array($row, $col));
//
//			if ($pt) {
//				$pt = $this->terrainMap->getPassablePoint(array($row, $col), 4);
//				// last ditch effor to get the ant off the hive
//				if (!$pt) {
//					$this->logger->write("Failed to get a passible point", AntLogger::LOG_GAME_FLOW | AntLogger::LOG_ANT);
//					for ($i = 0; $i < 4; $i++) {
//						if ( $this->passible($this->getDirection($this->directions[$i]))) {
//							$pt = $this->directions[$i];
//						}
//					}
//				}
//			}
//
//			if ($pt) {
//				$mission = new MissionGoToPoint(array(
//					'debug' => DEBUG_LEVEL,
//					'game' => $game,
//					'startPt' => array($row, $col),
//					'goalPt' => array($row, $col), // the hive
//					'terrainMap' => $this->terrainMap
//				));
//			} else {
				$mission = new Mission(array(
					'debug' => DEBUG_LEVEL,
					'game' => $game,
				));				
//			}

			$ant = new Ant(array(
			'row' => $row,
			'col' => $col, 
			'owner' => (int)$owner,
			'debug' => DEBUG_LEVEL,
			'mission' => $mission
		));	
			
		$this->logger->write(sprintf("Created new ant:%s", $ant), AntLogger::LOG_GAME_FLOW | AntLogger::LOG_ANT);			
			
		return $ant;
	}
	
	/**
	 * Add an Ant
	 * 
	 * @param object $ant
	 * @return boolean Return an Ant object on success, false otherwise;
	 */
	public function addAnt($ant) {
		if ($ant->owner !== 0) {
			$this->logger->write('Ants.addAnt() - Not my ant', AntLogger::LOG_ERROR);
		}
		$this->myAnts[] = $ant;
		$this->nMyAnts++;
	}

	/**
	 * Lookup one of my ants based on it's id.
	 *
	 * @param integer
	 * @return object|false Return an Ant object on success, false otherwise;
	 */
	public function lookupAntById($id) {

		if (empty($this->myAnts)) {
			return false;
		}

		for ($i = 0, $len = count($this->myAnts); $i < $len; $i++) {
			$ant = $this->myAnts[$i];
			if ($ant->id === $id) {
				return $ant;
			}
		}

		return false;
	}

	/**
	 * Find a entry in the available food array by location.
	 *
	 * @param array $pt
	 * @return returns the index into the $this->food array on success, false otherwise.
	 */
	public function lookupFood ($pt) {

		if (empty($this->food)) {
			return false;
		}		

		for ($i = 0, $len = count($this->food); $i < $len; $i++) {
			$food = $this->food[$i];
			if ($food[0] === $pt[0] && $food[1] === $pt[1]) {
				return $i;
			}
		}

		return false;
	}

	/**
	 * Find a entry in the targeted food array by location.
	 *
	 * @param array $pt
	 * @return returns the index into the $this->food array on success, false otherwise.
	 */
	public function lookupTargetedFood ($pt) {

		if (empty($this->foodTargets)) {
			return false;
		}

		for ($i = 0, $len = count($this->foodTargets); $i < $len; $i++) {
			$food = $this->foodTargets[$i];
			if ($food[0] === $pt[0] && $food[1] === $pt[1]) {
				return $i;
			}
		}

		return false;
	}

	/**
	 * getFoodTarget
	 * 
	 * @param type $pt
	 * @return array Return a food point
	 */
	public function getFoodTarget($pt) {

		// need to sort and get the best food, or better yet have a mission
		// assign phase after process game data, then assign bests
	
		return array_pop($this->food);
	}	

	/**
	 * getFoodTarget - simple ant/food matching
	 *
	 * @param type $pt
	 * @return array Return a food point
	 */
	public function getBestFoodTargets($ants) {

		//throw new Exception('Not implemented');

$this->logger->write('getBestFoodTargets - entry.  Ant count:' . count($ants));

		$result = array();

		$antFood = array();

		foreach ($ants as $ant) {
			$af = array(
				'ant' => $ant,
				'food' => new PQueue() // extended to prioritize on lowest
			);

			// note: food = [row, colum, lastKnowTurn]
			foreach ($this->food as $food) {
				$af['food']->insert($food, abs($ant->row - $food[0]) + abs($ant->col - $food[1]));
			}

			if ($af['food']->count()) {
				$antFood[] = $af;
			}
		}

$this->logger->write('Computed ant - food values.');

		foreach ($antFood as $af) {

$this->logger->write(var_export($af['ant']->pos, true));

$this->logger->write('finding best food target for ant ' . $af['ant']->name);

			do {
				$PQfood = $af['food'];
				$food = $PQfood->extract();
				$idx = $this->lookupFood(array($food[0], $food[1]));
				if ($idx !== false) {
					$result[] = array('ant' => $ant, 'food' => $food);
					array_splice($this->food, $idx, 1);
					break;
				}
			} while (!empty($this->food) && !empty($antFood));

		}

		return $result;
	}


	/**
	 * Lookup one of my ants based on it's position.
	 *
	 * Takes postion as a point or row,col.
	 *
	 * @param integer|array $arg1 Point or row
	 * @param integer $arg2 Column
	 * @return object|false Return an Ant object on success, false otherwise;
	 */
	public function lookupAntByPosition($arg1, $arg2 = null) {

//$this->logger->write(sprintf('lookupAntByPosition entry - %d, %d', $arg1, ((is_null($arg2)) ? 'null': $arg2)));

		if (empty($this->myAnts)) {
			return false;
		}

		if (is_array($arg1)) {
			$row = (isset($arg1['row']) ? (int)$arg1['row'] : (int)$arg1[0]);
			$col = (isset($arg1['col']) ? (int)$arg1['col'] : (int)$arg1[1]);
		} else {
			$row = (int)$arg1;
			$col = (int)$arg2;
		}
		
		for ($i = 0, $len = count($this->myAnts); $i < $len; $i++) {
			$ant = $this->myAnts[$i];
			if ($ant->row === $row && $ant->col === $col) {
				return $ant;
			}
		}

//$this->logger->write(sprintf('lookupAntByPosition failed - (%d,%d) => (%d,%d)', $arg1, ((is_null($arg2)) ? 'null': $arg2), $row, $col));

		return false;
	}

	/**
	 * Move an ant to the deadAnts list. Set state to dead if frendly.
	 *
	 * @param Ant $ant Takes an Ant object.
	 * @return boolean Return true on success, false otherwise.
	 */
	public function killAnt($deadAnt, $owner) {

//$this->logger->write('killAnt - entry ' . $ant->row. ',' . $ant->col);

		$found = false;

		if ($owner === 0) {
			for ($i = 0, $len = count($this->myAnts); $i < $len; $i++) {
				$ant = $this->myAnts[$i];
				if ($ant->row === $deadAnt[0] && $ant->col === $deadAnt[1]) {
					//$ant->mission->setState('dead');
					array_splice($this->myAnts, $i, 1);
					$this->deadAnts[] = $ant;
					$found = true;
					break;
				}
			}
			$this->logger->write(sprintf("<RED>CASUALTY: (%d, %d) %s</RED>", $deadAnt[0], $deadAnt[1], (($ant) ? $ant->name : 'Lost')), AntLogger::LOG_GAME_FLOW);
		} else {
			for ($i = 0, $len = count($this->enemyAnts); $i < $len; $i++) {
				$ant = $this->enemyAnts[$i];
				if ($ant[0] === $deadAnt[0] &&  $ant[1] === $deadAnt[1]) {
					array_splice($this->enemyAnts, $i, 1);
					$this->deadAnts[] = $ant;
					$found = true;
					break;
				}
			}
			$this->logger->write(sprintf("<GREEN>KILLED: (%d, %d)</GREEN>", $deadAnt[0], $deadAnt[1]), AntLogger::LOG_GAME_FLOW);
		}
		
//$this->logger->write('killAnt - Ant ' . $ant->row. ',' . $ant->col . ' NOT found.');

		return $found;
	}


	/**
	 * Is map[r,c] a hive? If so, return the owner.
	 *
	 * @param integer $row
	 * @param integer $col
	 * @return integer|false Return owner if found, false otherwise.
	 */
	public function isHive($row, $col) {
		foreach ($this->myHills as $h) {
			if ($h[0] === $row && $h[1] === $col) {
				return $h[2];
			}
		}

		foreach ($this->enemyHills as $h) {
			if ($h[0] === $row && $h[1] === $col) {
				return $h[2];
			}
		}

		return false;
	}

	/**
	 * Start the stdin loop
	 *
	 *	.   = land
	 *	%   = water
	 *	*   = food
	 *	!   = dead ant or ants
	 *	?   = unseen territory
	 *	a-j = ant
	 *	A-J = ant on its own hill
	 *	0-9 = hill
	 *
	 * @param Ant $bot
	 */
    public function dumpMap($grp = AntLogger::LOG_ALL) {

		$raw = false; // for debugging for now

		for ($i = -1, $ilen = count($this->map); $i < $ilen; $i++) {
			if ($i === -1) {
				$this->logger->write("  ", $grp, array('noEndline' => true));
				for ($j = 0, $jlen = count($this->map[0]); $j < $jlen; $j++) {
					$this->logger->write(sprintf("%s", (strlen($j) < 2) ? ($j . ' ') : ($j . '')), $grp, array('noEndline' => true));
				}
				$this->logger->write('', $grp);
				continue;
			}
			for ($j = -1, $jlen = count($this->map[$i]); $j < $jlen; $j++) {
				if ($j === -1) {
					$this->logger->write(sprintf("%s", (strlen($i) < 2) ? ($i . ' ') : ($i . '')), $grp, array('noEndline' => true));
					continue;
				}
				if ($raw) {
					$char = $this->map[$i][$j];
				} else {
					switch ($this->map[$i][$j]) {
						case Ants::DEAD:
							$char = '!';
							break;
						case Ants::LAND:
							$owner = $this->isHive($i, $j);
							if ($owner === false) {
								$char = '.';
							} else {
								$char = $owner; // hive with no ant
							}
							break;
						case Ants::FOOD:
							$char = '*';
							break;
						case Ants::WATER:
							$char = '%';
							break;
						case Ants::UNSEEN:
							$char = '?';
							break;
						default:
							$char = $this->map[$i][$j];
							if (is_numeric($char)) {
								if ($this->isHive($i, $j) === false) {
									$char = mb_substr(self::Alpha, (int)$char, 1); // ant not on hive
								} else {
									$char = strtoupper(mb_substr(self::Alpha, (int)$char, 1)); // Ant on hive
								}
							} else {
								$this->logger->write('DOES THIS OCCUR ????????????????????????????????????????????????????????????');
								$char = $this->map[$i][$j];
							}
					}
				}

				if (strlen((string)$char) < 2) {
					$char = ' ' . $char;
				}

				$this->logger->write($char, $grp, array('noEndline' => true));

			} // for j
			
			$this->logger->write('', $grp);
		}
		$this->logger->write('', $grp, array('noEndline' => false));
		
		$mh = '';
		foreach ($this->myHills as $h) {
			array_splice($h, 2, 1);
			$mh .= '(' .implode(',', $h) . '), ';
		}

		$eh = '';
		foreach ($this->enemyHills as $h) {
			$eh .= '(' .implode(',', $h) . '), ';
		}
		
		$this->logger->write('MyHives: ' . substr($mh, 0, -2) . '. Enemy Hives: ' . substr($eh, 0, -2) . ".\n", $grp);
	}

	/**
	 *
	 */
    public function dumpAnts($grp = AntLogger::LOG_ALL) {
		$this->logger->write('Dead ants (' .  count($this->deadAnts) . '):', $grp);
		$this->logger->write('Enemy ants (' .  count($this->enemyAnts) . '):', $grp);
		$this->logger->write('My ants (' . $this->nMyAnts . '):', $grp);
		for ($i = 0, $len = count($this->myAnts); $i < $len; $i++) {
			$this->logger->write(sprintf("  %s", $this->myAnts[$i]), $grp);
		}
	}

	/**
	 *
	 */
    public function dumpGame($grp = AntLogger::LOG_ALL) {
		
		$tstamp = date("Y-m-d H:i:s T", $this->gameStartTime);
		 
		$this->logger->write('Game Summary', $grp);
		$this->logger->write('----------------', $grp);
		$this->logger->write('Start Time: ' . $tstamp, $grp);
		$this->logger->write('Map: ' . $this->rows . 'x' . $this->cols, $grp);
		$this->logger->write('Turns: ' . $this->turns, $grp);
		
		$this->logger->write('Load Time: ' . $this->loadtime, $grp);		
		$this->logger->write('Turn Time: ' . $this->turntime, $grp);	
		
		$this->logger->write('View Radius: ' . $this->viewradius2, $grp);	
		$this->logger->write('Attack Radius: ' . $this->attackradius2, $grp);	
		$this->logger->write('Food Radius: ' . $this->spawnradius2, $grp);			
		
		$this->dumpAnts($grp);
	}

	/**
	 * Start the strdin loop
	 *
	 * @param Ant $bot
	 */
    public function dumpTurn($grp = AntLogger::LOG_ALL) {
		$this->logger->write("\nTurn " . $this->turn . " Initial State Summary", $grp);
		$this->logger->write('----------------', $grp);
		$this->logger->write("Food:", $grp);
		$this->logger->write(" Available food: " . count($this->food), $grp);
		$this->logger->write(" Targeted food: " . count($this->foodTargets), $grp);
		$this->dumpAnts($grp);
	}

	/**
	 * getElapsedTime
	 *
	 * @return integer
	 */
    public function getElapsedTime() {
		list($usec, $sec) = explode(" ", microtime());
		return  $this->gameStartTime - (float)$sec + (float)($usec * 1000.0);
	}
	
	/**
	 * get
	 *
	 * Input: pt or row,col
	 *
	 * @param array $pt Point
	 * @return string
	 */
    public function mapGet ($arg1, $arg2 = null) {
		if (is_array($arg1)) {
			$wPt = $this->gridWrap($arg1);
		} else {
			$wPt = $this->gridWrap(array($arg1, $arg2));
		}
		return $this->map[$wPt[0]][$wPt[1]];
	}

	/**
	 * mapSet
	 * 
	 * mapSet(pt, val) or  mapSet(row, col, val)
	 *  
	 * @param array $pt Point to set.
	 * @param mixed $value Value of point.
	 */
    public function mapSet ($arg1, $arg2, $arg3 = null) {
		if (is_array($arg1)) {
			$wPt = $this->gridWrap($arg1);
			$value = $arg2;
//$this->logger->write(sprintf("Ants.mapSet(%d,%d) -> (%d,%d) = %d", $arg1[0], $arg1[1], $wPt[0], $wPt[1], $value));
		} else {
			$wPt = $this->gridWrap(array($arg1, $arg2));
			$value = $arg3;
//$this->logger->write(sprintf("Ants.mapSet(%d,%d) -> (%d,%d) = %d", $arg1, $arg2, $wPt[0], $wPt[1], $value));
		}

		$this->map[$wPt[0]][$wPt[1]] = $value;
	}

	/**
	 * gridWrap
	 *
	 * @param array $pt Point
	 * @return array
	 */
	public function gridWrap($pt) {
		$row = $pt[0];
		$col = $pt[1];
		$r = $row % $this->rows;
		$c = $col % $this->cols;
		return array(
			($row < 0) ? $r + $this->rows : $r,
			($col < 0) ? $c + $this->cols : $c,
		);
	}	
	
	/**
	 * Main game loop
	 *
	 * @param Ant $bot
	 */
    public static function run($bot){
		$ants = new Ants();
		$map_data = array();
		while (true) {
			$current_line = fgets(STDIN,1024);
			$current_line = trim($current_line);
			if ($current_line === 'ready') {
				$ants->setup($map_data);
				$ants->finishTurn();
				$map_data = array();
			} elseif ($current_line === 'go') {
				$ants->update($map_data);
				$bot->doTurn($ants); // ants == game data
				$ants->finishTurn();

// throw new Exception('debug die in Ants run loop'); // do one game turn

				$map_data = array();
			} else {
				$map_data[] = $current_line;
			}
		}
	}
}
