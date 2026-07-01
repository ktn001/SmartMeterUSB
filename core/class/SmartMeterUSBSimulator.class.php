<?php
// vi: tabstop=4 autoindent

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */

class SmartMeterUSBSimulator {

	/*     * *************************Attributs****************************** */

	const PYTHON_PATH = __DIR__ . '/../../resources/venv/bin/python3';

	private static $configFile = __DIR__ . "/../config/simulator.json";
	private $name = '';
	private $pidFile = '';
	private $simulatorPort = '';
	private $readerPort = '';
	private $baudrate = "";
	private $counterScript = "";

	/*     * ***********************Methode static*************************** */

	public static function byName($name) {
		$simulator = new static($name);
		return $simulator;
	}

	public static function getNames() {
		$configs = file_get_contents(self::$configFile);
		if ($configs === false) {
			throw new Exception (__("Erreur lors de la lecture du fichier %s",__FILE__,$this->configFile));
		}
		$configs = json_decode($configs, true);
		return array_keys($configs);
	}

	public static function getAll() {
		$simulators = array();
		$names = self::getNames();
		foreach ($names as $name) {
			$simulator = new SmartMeterUSBSimulator($name);
			$simulators[] = $simulator;
		}
		return $simulators;
	}

	public static function getAllStates() {
		$simulators = self::getAll();
		$ret = array();
		foreach ($simulators as $simulator) {
			$ret[$simulator->getName()] = $simulator->state();
		}
		return $ret;
	}

	public static function getAllReaderPorts() {
		$simulators = self::getAll();
		$ret = array();
		foreach ($simulators as $simulator) {
			$ret[$simulator->getName()] = $simulator->getReaderPort();
		}
		return $ret;
	}

	public static function startUsed() {
		$simulators = self::getAll();
		$converters = SmartMeterUSBConverter::all(true);
		foreach ($simulators as $simulator) {
			foreach ($converters as $converter) {
				if ($converter->getPort() == $simulator->getReaderPort()){
					$simulator->start();
					break;
				}
			}
		}
	}

	/*     * *********************Méthodes d'instance************************* */

	public function __construct($name) {
		$this->name = $name;
		$configs = file_get_contents(self::$configFile);
		if ($configs === false) {
			throw new Exception (sprintf(__("Erreur lors de la lecture du fichier %s",__FILE__,$this->configFile)));
		}
		$configs = json_decode($configs, true);
		if (!isset($configs[$name])) {
			throw new Exception (sprintf(__("Erreur configuration pour l'émulateur %s introuvable",__FILE__),$name));
		}
		$config = $configs[$name];
		$this->pidFile = jeedom::getTmpFolder('SmartMeterUSB') . "/" . $config['pidFile'];
		$this->simulatorPort = jeedom::getTmpFolder('SmartMeterUSB') . "/" . $config['simulatorPort'];
		$this->readerPort = jeedom::getTmpFolder('SmartMeterUSB') . "/" . $config['readerPort'];
		$this->baudrate = $config['baudrate'];
		$this->counterScript = realpath(__DIR__ . "/../../resources/bin/" . $config['counter']);
	}

	public function counterStart() {
		log::add("SmartMeterUSB","debug",sprintf(__("Lancemant du compteur virtuel pour %s",__FILE__),$this->name));
		log::add("SmartMeterUSB","debug","Script: " . $this->getCounterScript());
		$cmd = self::PYTHON_PATH . " " . $this->getCounterScript();
		$cmd .= " -p " . $this->getSimulatorPort();
		$cmd .= " -b " . $this->getBaudrate();
		$cmd .= " -l " . log::convertLogLevel(log::getLogLevel('SmartMeterUSB'));
		log::add("SmartMeterUSB","debug",$cmd);
		$logFile = log::getPathToLog(__CLASS__ . "_" . $this->name . "_counter");
		exec($cmd . ' >> ' . $logFile . ' 2>&1 & echo $!', $output);
		$pid = $output[0];
		$this->addPid("counter",$pid);
	}

	public function socatStart() {
		log::add("SmartMeterUSB","debug",sprintf(__("Lancemant de socat pour %s",__FILE__),$this->name));
		$cmd = "/usr/bin/socat -d -d ";
		$cmd .= "PTY,link=" . $this->getSimulatorPort() . ",raw,echo=0,b" . $this->baudrate . ",parenb,parodd,cs8 ";
		$cmd .= "PTY,link=" . $this->getReaderPort() . ",raw,echo=0,b" . $this->baudrate . ",parenb,parodd,cs8 ";
		$logFile = log::getPathToLog(__CLASS__ . "_" . $this->name . "_socat");
		log::add("SmartMeterUSB","debug",$cmd);
		exec($cmd . ' >> ' . $logFile . ' 2>&1 & echo $!', $output);
		$pid = $output[0];
		$this->addPid("socat",$pid);
	}

	public function start() {
		$this->stop();
		log::add("SmartMeterUSB","info",sprintf(__("Lancement du simulateur %s",__FILE__),$this->name));
		$this->socatStart();
		$this->counterStart();
		config::save($this->getName() . '::lastLaunchTime', date('Y-m-d H:i:s'), 'SmartMeterUSB');
	}

	public function counterStop() {
		log::add("SmartMeterUSB","debug",sprintf(__("Arrêt du compteur virtuel pour %s",__FILE__),$this->name));
		$pid = $this->getPid('counter');
		if ($pid){
			system::kill($pid);
		}
		$this->removePid('counter');
	}

	public function socatStop() {
		log::add("SmartMeterUSB","debug",sprintf(__("Arrêt de socat pour %s",__FILE__),$this->name));
		$pid = $this->getPid('socat');
		if ($pid){
			system::kill($pid);
		}
		unlink($this->getReaderPort());
		unlink($this->getSimulatorPort());
		$this->removePid('socat');
	}

	public function stop() {
		log::add ("SmartMeterUSB","info",sprintf(__("Arrêt du simulateur %s",__FILE__),$this->name));
		$this->counterStop();
		$this->socatStop();
	}

	public function socatState() {
		$pid = $this->getPid('socat');
		if ($pid === false){
			return 0;
		}
		if (! file_exists('/proc/' . $pid)){
			return 0;
		}
		return 1;
	}

	public function counterState() {
		$pid = $this->getPid('counter');
		if ($pid === false){
			return 0;
		}
		if (! file_exists('/proc/' . $pid)){
			return 0;
		}
		return 1;
	}

	public function state() {
		$socatState = $this->socatState();
		$counterState = $this->counterState();
		$msg = '';
		$state = '';
		if ($socatState == 1 and $counterState == 1) {
			$state = 1;
			$msg = "baudrate: " . $this->getbaudrate();
		} else {
			$state = 0;
			if (! $socatState) {
				if ($msg) {
					$msg .= ", ";
				}
				$msg .= "socat NOK";
			}
			if (! $counterState) {
				if ($msg) {
					$msg .= ", ";
				}
				$msg .= "counter NOK";
			}
		}
		$ret = array(
			'state' => $state,
			'msg' => $msg,
			'lastLaunchTime' => config::byKey($this->getName() . '::lastLaunchTime', 'SmartMeterUSB', __('Inconnue',__FILE__))
		);
		return $ret;
	}

	public function getPids() {
		if (file_exists($this->getPidFile())) {
			$pids = file_get_contents($this->getPidFile());
			if ($pids === false) {
				$pids = array();
			} else {
				$pids = json_decode($pids,true);
			}
		} else {
			$pids = array();
		}
		return $pids;
	}

	public function getPid($script){
		$pids = $this->getPids();
		if (isset($pids[$script])) {
			return $pids[$script];
		}
		return false;
	}

	public function addPid($script,$pid) {
		$pids = $this->getPids();
		$pids[$script] = $pid;
		$pids = json_encode($pids, JSON_PRETTY_PRINT);
		$pidFile = fopen($this->getPidFile(),"w");
		fwrite($pidFile, $pids);
		fclose($pidFile);
	}

	public function removePid($script) {
		$pids = $this->getPids();
		if (isset($pids[$script])) {
			unset($pids[$script]);
		}
		if (empty($pids)) {
			if (file_exists($this->getPidFile())) {
				unlink($this->getPidFile());
			}
		} else {
		$pids = json_encode($pids, JSON_PRETTY_PRINT);
		$pidFile = fopen($this->getPidFile(),"w");
		fwrite($pidFile, $pids);
		fclose($pidFile);
		}
	}

	/*     * **********************Getteur Setteur*************************** */

	function getName() {
		return $this->name;
	}

	function getPidFile() {
		return $this->pidFile;
	}

	function getSimulatorPort() {
		return $this->simulatorPort;
	}

	function getReaderPort() {
		return $this->readerPort;
	}

	function getBaudrate() {
		return $this->baudrate;
	}

	function getCounterScript() {
		return $this->counterScript;
	}
}
