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



define('DEBUG_LEVEL', AntLogger::LOG_ALL);

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
	const WATER = -5;
	const UNSEEN = -6;

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
    public $food = array();
	public $gameStartTime = 0;
	public $terrainMap = null;
	
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
			'logLevel' =>  DEBUG_LEVEL  // - AntLogger::LOG_INPUT - AntLogger::LOG_OUTPUT - AntLogger::LOG_MAPDUMP
		));
	}
	
    public function issueOrder($aRow, $aCol, $direction) {
		$this->logger->write(sprintf("Raw output: o %s %s %s", $aRow, $aCol, $direction), AntLogger::LOG_OUTPUT);
        printf("o %s %s %s\n", $aRow, $aCol, $direction);
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
		
		$this->logger->write("<GREEN>Starting update processing for turn " . $this->turn . '.</GREEN>', AntLogger::LOG_GAME_FLOW);

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
            list($row, $col) = $ant;
            $this->mapSet($row, $col, Ants::LAND);
        }
        $this->enemyAnts = array();

        foreach ($this->deadAnts as $ant) {
            list($row, $col) = $ant->pos;
            $this->mapSet($row, $col, Ants::LAND);
        }
        $this->deadAnts = array();

        foreach ($this->food as $ant) {
            list($row, $col) = $ant->pos;
            $this->mapSet($row, $col, Ants::LAND);
			$this->terrainMap->set(array($row, $col), Ants::LAND);
        }

        $this->food = array();
        $this->myHills = array();
        $this->enemyHills = array();

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
						
						// put the new ant on the game map
                        $this->mapSet($row, $col, mb_substr(self::Alpha, $owner, 1));
						
						if ($owner === 0) {
							$ant = $this->lookupAnt($row, $col);
							if ($ant) {
								// do nothing?
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
                        $this->food []= array($row, $col);
                    } elseif ($tokens[0] == 'w') {			// w = water, format: w row col
                        $this->mapSet($row, $col, Ants::WATER);
						$this->terrainMap->set(array($row, $col), Ants::WATER);
                    } elseif ($tokens[0] == 'd') {			// dead ant, format: d row col owner
						$this->deadAnts[] = array($row,$col);
                        $this->terrainMap->set(array($row, $col), Ants::LAND);
						if (DEBUG_LEVEL) {
							$ant = $this->lookupAnt($row, $col);
							if ($ant) {
								$this->logger->write(sprintf("CASUALTY: %s", $ant), AntLogger::LOG_GAME_FLOW);
							} else {
								$this->logger->write(sprintf("KILLED: (%d, %d)", $row, $col), AntLogger::LOG_GAME_FLOW);
							}
						}
						$this->deadAnts[] = array($row,$col);
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

		$this->dumpMap(AntLogger::LOG_MAPDUMP);

		$this->logger->write("Terrian Map:"); 
		$this->logger->write($this->terrainMap);

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
		
		$retval = $this->mapGet($row, $col);
		
$this->logger->write(sprintf("Ants.passible(%d,%d) = %d", $row, $col, $retval));
		
        return $retval > Ants::WATER;
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

$this->logger->write(sprintf("direction - entry %d,%d %d,%d", $row1, $col1, $row2, $col2), AntLogger::LOG_MISSION);

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

			$farpoint = round(sqrt($this->rows*$this->rows + $this->cols*$this->cols));
			
			$mission = new MissionPatrol(array(
				'debug' => DEBUG_LEVEL,
				'game' => $game,
				'startPt' => array($row, $col),
				'centerPt' => array($row, $col), // the hive
				'terrainMap' => $this->terrainMap,
				'firstTurn' => $this->turn,
				'radius' => rand($this->attackradius, $farpoint)
			));

			if (!$mission) {
				throw new Exception('NO MISSSION');
			}

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

//$this->logger->write(var_export($ant, true)); die();

		if ($ant->owner !== 0) {
			$this->logger->write('Ants.addAnt() - Not my ant', AntLogger::LOG_ERROR);
		}
		$this->myAnts[] = $ant;
		$this->nMyAnts++;
	}
	
	/**
	 * Lookup one of my ants based on it's position.
	 * 
	 * @param integer|array $arg1
	 * @param integer $arg2
	 * @return object|false Return an Ant object on success, false otherwise;
	 */
	public function lookupAnt($arg1, $arg2 = null) {
		if (is_array($arg1)) {
			$row = (isset($arg1['row']) ? $arg1['row'] : $arg1[0]);
			$col = (isset($arg1['col']) ? $arg1['col'] : $arg1[0]);
		} else {
			$row = $arg1;
			$col = $arg2;
		}
		
		for ($i = 0; $i < $this->nMyAnts; $i++) {
			$ant = $this->myAnts[$i];
			if ($ant->row === $row && $ant->col == $col) {
				return $ant;
			}
		}

		return false;
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
		for ($i = 0, $ilen = count($this->map); $i < $ilen; $i++) {
			$this->logger->write('', $grp, array('noEndline' => true));
			for ($j = 0, $jlen = count($this->map[$i]); $j < $jlen; $j++) {
				switch ($this->map[$i][$j]) {
					case Ants::DEAD:
						$char = '!';
						break;
					case Ants::LAND:
						$owner = $this->isHive($i, $j);
						if ($owner === false) {
							$char = '.';
						} else {
							$char = $owner;
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
						$hiveOwner = $this->isHive($i, $j);
						if ($hiveOwner === false) {
							$char = $this->map[$i][$j];
						} else {
							$char = strtoupper($this->map[$i][$j]);
						}
				}
				if (strlen((string)$char) < 2) {
					$char .= ' ';
				}
				$this->logger->write($char, $grp, array('noEndline' => true));
			}
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
		
		$this->logger->write('MyHives: ' . substr($mh, 0, -2) . '. Enemy Hives: ' . substr($eh, 0, -2) . ".\n");
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
		$this->dumpAnts($grp);
		$this->logger->write("\n");
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
	 * @param array $pt
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
				$map_data = array();
			} else {
				$map_data[] = $current_line;
			}
		}
	}
}
