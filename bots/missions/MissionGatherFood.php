<?php



//require_once 'Mission.php';
//require_once 'State.php';

/**
 * Ant mission to go a point on the map.
 *
 * @author gmorgan
 */
class MissionGatherFood extends MissionGoToPoint {

	/**
	 * food gathering mission setup
	 *
	 * @param array $missionConfig <pre>
	 *	$ant
	 *	foodPt
	 *	terrainMap
	 * </pre>
	 */
	static function setup($missionConfig) {

		$needsMission = array();

		// ants
		//$antFoodMissionPairs = $this->getBestFoodTargets($needsMission);

//$this->logger->write('Ant - food pairs:');
//$this->logger->write(var_export($antFoodMissionPairs, true));

		//foreach ($antFoodMissionPairs as $pair) {

			//$ant = $this->lookupAntById($pair['ant']->id);

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
		//}



		// end food gathering


	}


	/**
	 * getFoodTarget - simple ant/food matching
	 *
	 *
	 * @param array $ants
	 * @param array $food
	 * @return array Return a food point
	 */
	static public function getBestFoodTargets($ants, $knownFood, $game) {
		
//$this->logger->write('getBestFoodTargets - entry.  Ant count:' . count($ants));

		$result = array();

		$antFood = array();

		foreach ($ants as $ant) {
			$af = array(
				'ant' => $ant,
				'food' => new PQueue() // extended to prioritize on lowest
			);

			// note: food = [row, colum, lastKnowTurn]
			foreach ($knownFood as $food) {
				$af['food']->insert($food, abs($ant->row - $food[0]) + abs($ant->col - $food[1]));
			}

			if ($af['food']->count()) {
				$antFood[] = $af;
			}
		}

//$this->logger->write('Computed ant - food values.');

		foreach ($antFood as $af) {

//$this->logger->write(var_export($af['ant']->pos, true));
//$this->logger->write('finding best food target for ant ' . $af['ant']->name);

			do {
				$PQfood = $af['food'];
				$food = $PQfood->extract();
				$idx = $game->lookupFood(array($food[0], $food[1]));
				if ($idx !== false) {
					$result[] = array('ant' => $ant, 'food' => $food);
					array_splice($knownFood, $idx, 1);
					break;
				}
			} while (!empty($knownFood) && !empty($antFood));

		}

		return $result;
	}


}
