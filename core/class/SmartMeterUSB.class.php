<?php
// vim: tabstop=4 autoindent

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
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/SmartMeterUSBConverter.class.php';

class SmartMeterUSB extends eqLogic {

	/*     * *************************Attributs****************************** */

	const PYTHON_PATH = __DIR__ . '/../../resources/venv/bin/python3';

	private static $_MQTT2 = 'mqtt2';
	private static $_TOPIC_PREFIX = 'smartmeter';

	/*     * ***********************Methode static*************************** */

	public static function nextName() {
		$nextNameId = config::byKey('nextCounterNameId',__CLASS__,1);
		config::save('nextCounterNameId', $nextNameId+1, __CLASS__);
		return __('compteur',__FILE__) . "_" . $nextNameId;
	}
	
	public static function getCmdsConfig() {
		$cmdFileName =__DIR__ . '/../config/cmds.json';
		$cmds = file_get_contents($cmdFileName);
		if ($cmds === false) {
			throw new Exception (sprintf(__("Erreur lors de la lecture du fichier %s",__FILE__),$cmdFileName));
		}
		$cmds = translate::exec($cmds, $cmdFileName);
		$cmds = json_decode($cmds, true);
		return $cmds;
	}

	public static function getCounters() {
		$counterFileName =__DIR__ . '/../config/counters.json';
		$counters = file_get_contents($counterFileName);
		if ($counters === false) {
			throw new Exception (sprintf(__("Erreur lors de la lecture du fichier %s",__FILE__),$counterFileName));
		}
		$counters = json_decode($counters, true);
		return $counters;
	}

	public static function backupExclude() {
		return [
			'resources/venv'
		];
	}

	public static function getImageForCounterType($_counterType) {
		$path = realpath(__DIR__ . '/../../desktop/img/' . $_counterType . '.png');
		if (file_exists($path)) {
			$path = preg_replace('/^.*?(\/plugins\/.*)/','\1', $path);
			return $path;
		}
		return plugin::byId(__CLASS__)->getPathImgIcon();
	}

	private static function pythonRequirementsInstalled(string $pythonPath, string $requirementsPath) {
		if (!file_exists($pythonPath) || !file_exists($requirementsPath)) {
			return false;
		}
		exec("{$pythonPath} -m pip freeze", $packages_installed);
		$packages = join("||", $packages_installed);
		exec("cat {$requirementsPath}", $packages_needed);
		foreach ($packages_needed as $line) {
			if (preg_match('/([^\s]+)[\s]*([>=~]=)[\s]*([\d+\.?]+)$/', $line, $need) === 1) {
				if (preg_match('/' . $need[1] . '==([\d+\.?]+)/', $packages, $install) === 1) {
					if ($need[2] == '==' && $need[3] != $install[1]) {
						return false;
					} elseif (version_compare($need[3], $install[1], '>')) {
						return false;
					}
				} else {
					return false;
				}
			}
		}
		return true;
	}

	public static function dependancy_info() {
		$return = array();
		$return['log'] = log::getPathToLog(__CLASS__ . '_update');
		$return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependance';
		$return['state'] = 'ok';
		if (file_exists(jeedom::getTmpFolder(__CLASS__) . '/dependance')) {
			$return['state'] = 'in_progress';
		} elseif (!self::pythonRequirementsInstalled(self::PYTHON_PATH, __DIR__ . '/../../resources/requirements.txt')) {
			$return['state'] = 'nok';
		}
		return $return;
	}

	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => __DIR__ . '/../../resources/install_#stype#.sh', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}

	public static function deamon_info() {
		return self::daemon_info();
	}
	public static function daemon_info() {
		$return = array();
		$return['log'] = __CLASS__;
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		$pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
		if (file_exists($pid_file)) {
			if (@posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				shell_exec($system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
			}
		}
		if (!class_exists(self::$_MQTT2)) {
			$return['launchable'] = 'nok';
			$return['launchable_message'] = sprintf(__("Le plugin %s n'est pas installé",__FILE__),self::$_MQTT2);
		} elseif (self::$_MQTT2::deamon_info()['state'] != 'ok') {
			$return['launchable'] = 'nok';
			$return['launchable_message'] = sprintf(__("Le démon %s n'est pas démarré",__FILE__),self::$_MQTT2);
		} elseif (count(SmartMeterUSBConverter::all(true)) == 0) {
			$return['launchable'] = 'nok';
			$return['launchable_message'] = __("Veuillez configurer et activer au moins un convertisseur USB",__FILE__);
		}
		return $return;
	}

	public static function deamon_start() {
		return self::daemon_start();
	}
	public static function daemon_start() {
		self::deamon_stop();
		self::$_MQTT2::addPluginTopic(__CLASS__, self::$_TOPIC_PREFIX);
		log::add("SmartMeterUSB","debug", "Listening to topic: '" . self::$_TOPIC_PREFIX . "'");
		$daemon_info = self::daemon_info();
		$converters = SmartMeterUSBConverter::all(true);
		if (count($converters) == 0) {
			throw new Exception (__("Veuillez configurer et activer au moins un convertisseur USB",__FILE__));
		}
		if ($daemon_info['launchable'] != "ok") {
			throw new Exception(__('Veuillez vérifier la configuration',__FILE__));
		}
		$daemonConfigFileName = jeedom::getTmpFolder(__CLASS__) . '/config.ini';
		if ($fd = fopen($daemonConfigFileName, 'w')) {
			foreach ($converters as $converter) {
				fwrite($fd, "[reader" . $converter->getId() . "]\n");
				fwrite($fd, "type = " . $converter->getType() . "\n");
				fwrite($fd, "port = " . $converter->getport() . "\n");
				fwrite($fd, "baurate = " . $converter->getBaurate() . "\n");
				fwrite($fd, "key = " . $converter->getKey() . "\n");
				fwrite($fd, "\n");
			}
			fwrite($fd, "[sink0]\n");
			fwrite($fd, "type = logger\n");
			fwrite($fd, "name = DataLogger\n");
			fwrite($fd, "\n");
			$mqttInfos = self::$_MQTT2::getFormatedInfos();
			fwrite($fd, "[sink1]\n");
			fwrite($fd, "type = mqtt\n");
			fwrite($fd, "host = " . $mqttInfos['ip'] . "\n");
			fwrite($fd, "port = " . $mqttInfos['port'] . "\n");
			fwrite($fd, "tls = False\n");
			fwrite($fd, "ca_file_path =\n");
			fwrite($fd, "check_hostname = False\n");
			fwrite($fd, "username = " . $mqttInfos['user'] . "\n");
			fwrite($fd, "password = " . $mqttInfos['password'] . "\n");
			fwrite($fd, "client_cert_path =\n");
			fwrite($fd, "client_key_path =\n");
			fwrite($fd, "\n");

			fwrite($fd, "[logging]\n");
			fwrite($fd, "default = DEBUG\n");
			fwrite($fd, "collector = DEBUG\n");
			fwrite($fd, "smartmeter = DEBUG\n");
			fwrite($fd, "sink = DEBUG\n");

			fclose($fd);

			chmod($daemonConfigFileName,0660);
			$path = realpath(__DIR__ . '/../../resources/bin');
			$cmd = self::PYTHON_PATH . " {$path}/SmartMeterUSBd.py";
			$cmd .= " -c {$daemonConfigFileName}";
			$cmd .= " -p " . jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
			exec ($cmd . ' >> ' . log::getPathToLog(__CLASS__ . '_daemon') . ' 2>&1 &');
			$ok = false;
			for ($i=0; $i < 10; $i++) {
				$daemon_info = self::daemon_info();
				if ($daemon_info['state'] == 'ok') {
					$ok = true;
					break;
				}
				sleep (1);
			}
			if (!$ok) {
				log::add(__CLASS__,'error',__('Impossible de lander le démon',__FILE__), 'unableStartDaemon');
				return false;
			}
			message::removeAll('SmartMeterUSB', 'unableStartDaemon');
			return true;
		} else {
			throw new Exception(sprintf(__("Erreur lors de la création du fichier: %s",__FILE__), $daemonConfigFileName));
		}
	}

	public static function deamon_stop() {
		return self::daemon_stop();
	}
	public static function daemon_stop() {
		$pidFile = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
		if (file_exists($pidFile)) {
			$pid = intval(trim(file_get_contents($pidFile)));
			system::kill($pid);
		}
		sleep(1);
	}

	public static function handleMqttMessage($_message) {
		log::add(__CLASS__, 'debug', 'handle Mqtt Message:' . json_encode($_message));

		$mappingFileName = __DIR__ . '/../config/OBISCode_mapping.json';
		$OBISCodes = file_get_contents($mappingFileName);
		if ($OBISCodes === false) {
			throw new Exception (sprintf(__("Erreur lors de la lecture du fichier %s",__FILE__),$mappingFileName));
		}
		$OBISCodes = json_decode($OBISCodes,true);

		foreach (array_keys($_message) as $topicPrefix) {
			if ($topicPrefix !== self::$_TOPIC_PREFIX) {
				log::add(__CLASS__, 'warning', __("Le message n'est pas pour le plugin SmatrMeterUSB",__FILE__));
				continue;
			}
			foreach ($_message[$topicPrefix] as $counterNr => $mesures) {
				$counter = SmartMeterUSB::byLogicalId($counterNr, __CLASS__);
				if (!is_object($counter)) {
					if (config::byKey('autoCreateCounter',__CLASS__)) {
						$name = self::nextName();
						log::add(__CLASS__,"info",sprintf(__("Création du compteur %s (%s)",__FILE__),$counterNr, $name));
						$counter = new SmartMeterUSB();
						$counter->setEqType_name(__CLASS__);
						$counter->setName($name);
						$counter->setLogicalId($counterNr);
						$counter->save();
						$counter = SmartMeterUSB::byLogicalId($counterNr, __CLASS__);
						if (!is_object($counter)) {
							log::add(__CLASS__,"error",sprintf(__("Erreur lors de la création du compteur N° %s (%s)",__FILE__),$counterNr(),$name));
							continue;
						}
					} else {
						log::add(__CLASS__,"warning",sprintf(__("Le compteur %s est introuvable",__FILE__),$counterNr));
						continue;
					}
				}
				if (!$counter->getIsEnable()) {
					continue;
				}
				foreach ($mesures as $mesure => $value) {
					if (!isset ($OBISCodes[$mesure])) {
						log::add(__CLASS__,"warning",sprintf(__("OBISCode pour la mesure %s introuvable",__FILE__),$mesure));
						continue;
					}
					$logicalId = $OBISCodes[$mesure];
					$cmd = $counter->createAndGetCmd($logicalId);
					if (!is_object($cmd)) {
						continue;
					}
					log::add(__CLASS__,"debug",sprintf("OBIS code: %-12s CmdName: %-20s Value:%-20s",$logicalId, $cmd->getName(), $value['value']));
					$tarif = 0;
					switch ($logicalId) {
						case '1.0:1.8.1':
						case '1.0:1.8.2':
						case '1.0:1.8.3':
						case '1.0:1.8.4':
						case '1.0:2.8.1':
						case '1.0:2.8.2':
						case '1.0:2.8.3':
						case '1.0:2.8.4':
							$oldValue = $cmd->execCmd();
							if ($oldValue != $value['value']) {
								$tarif = substr($logicalId,strrpos($logicalId,'.')+1);
							}

					}
					if ($tarif != 0) {
						$tarifCmd = $counter->createAndGetCmd('tarif');
						if (is_object($tarifCmd)) {
							$txt = config::byKey('tarif:' . $tarif . ':txt',__CLASS__,'');
							if ($txt !== '') {
								$tarif = $txt;
							}
							$counter->checkAndUpdateCmd($tarifCmd,$tarif);
						}
					}
					$counter->checkAndUpdateCmd($cmd,$value['value']);
				}
			}
		}
	}

	/*
	 * Permet d'indiquer des éléments supplémentaires à remonter dans les informations de configuration
	 * lors de la création semi-automatique d'un post sur le forum community
	public static function getConfigForCommunity() {
		// Cette function doit retourner des infos complémentataires sous la forme d'un
		// string contenant les infos formatées en HTML.
		return "les infos essentiel de mon plugin";
	}
	 */

	/*     * *********************Méthodes d'instance************************* */

	// Fonction exécutée automatiquement après la création de l'équipement
	public function postInsert() {
		if (config::byKey('autoCreateCounter',__CLASS__,1) == 1) {
			log::add(__CLASS__,"info",__("Les commandes seront céées automatiquement",__FILE__));
		} else {
			log::add(__CLASS__,"info",__("Céation des commandes",__FILE__) . "...");
			$cmds = self::getCmdsConfig();
			foreach ($cmds as $cmd_a) {
				$cmd = new SmartMeterUSBCmd();
				utils::a2o($cmd,$cmd_a);
				$cmd->seteqLogic_id($this->getId());
				$cmd->save();
			}
		}
	}

	public function getImage() {
		return $this->getImageForCounterType($this->getConfiguration('counterType',''));
	}

	public function createAndGetCmd ($_logicalId) {
		$cmd = $this->getCmd('info',$_logicalId);
		if (is_object($cmd)) {
			return $cmd;
		}
		if (! config::byKey('autoCreateCmd',__CLASS__)) {
			return null;
		}
		log::add(__CLASS__,"info",sprintf(__("Création de la commande '%s' pour le compteur '%s' (%s)",__FILE__),$_logicalId, $this->getLogicalId(), $this->getHumanName()));
		$cmds = self::getCmdsConfig();
		foreach ($cmds as $cmd_a) {
			if ($cmd_a['logicalId'] == $_logicalId) {
				$cmd = new SmartMeterUSBCmd();
				utils::a2o($cmd,$cmd_a);
				$cmd->seteqLogic_id($this->getId());
				$cmd->save();
				break;
			}
		}
		$cmd = $this->getCmd('info',$_logicalId);
		if (!is_object($cmd)) {
			throw new Exception (sprintf(__("Erreur lors de la création de la commande %s pour le compteur %s (%s)",__FILE__),$_logicalId, $this->getLogicalId(), $this->getHumanName()));
		}
		return $cmd;
	}

	public function sortCmds() {
		log::add(__CLASS__,"info",sprintf(__("Tri des commandes du compteur '%s' (%s)",__FILE__),$this->getLogicalId(),$this->getHumanName()));
		$cmds = self::getCmdsConfig();
		$order=1;
		foreach ($cmds as $cmd_a) {
			$cmd = $this->getCmd(null,$cmd_a['logicalId']);
			if (!is_object($cmd)) {
				continue;
			}
			$cmd->setOrder($order);
			$order++;
			if ($cmd->getChanged()) {
				$cmd->save();
			}
		}
	}

	/*
	* Permet de modifier l'affichage du widget (également utilisable par les commandes)
	public function toHtml($_version = 'dashboard') {}
	*/

	/*     * **********************Getteur Setteur*************************** */
}

class SmartMeterUSBCmd extends cmd {
	/*     * *************************Attributs****************************** */

	/*
	public static $_widgetPossibility = array();
	*/

	/*     * ***********************Methode static*************************** */

	public function postInsert() {
		$this->getEqLogic()->sortCmds();
	}

	/*     * *********************Methode d'instance************************* */

	/*
	* Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
	public function dontRemoveCmd() {
	return true;
	}
	*/

	// Exécution d'une commande
	public function execute($_options = array()) {
	}

	/*     * **********************Getteur Setteur*************************** */
}
