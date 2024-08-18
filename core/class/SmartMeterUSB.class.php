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
require_once __DIR__ . '/SmartMeterUSBAdapter.class.php';

class SmartMeterUSB extends eqLogic {

  /*     * *************************Attributs****************************** */

	const PYTHON_PATH = __DIR__ . '/../../resources/venv/bin/python3';

	private static $_MQTT2 = 'mqtt2';
	private static $_TOPIC_PREFIX = 'smartmeter';

  /*     * ***********************Methode static*************************** */

	public static function backupExclude() {
		return [
			'resources/venv'
		];
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
		} elseif (count(SmartMeterUSBAdapter::all(true)) == 0) {
			$return['launchable'] = 'nok';
			$return['launchable_message'] = __("Veuillez configurer et activer au moins un adaptateur USB",__FILE__);
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
		$adapters = SmartMeterUSBAdapter::all(true);
		if (count($adapters) == 0) {
			throw new Exception (__("Veuillez configurer et activer au moins un adaptateur USB",__FILE__));
		}
		if ($daemon_info['launchable'] != "ok") {
			throw new Exception(__('Veuillez vérifier la configuration',__FILE__));
		}
		$daemonConfigFileName = jeedom::getTmpFolder(__CLASS__) . '/config.ini';
		if ($fd = fopen($daemonConfigFileName, 'w')) {
			foreach ($adapters as $adapter) {
				fwrite($fd, "[reader" . $adapter->getId() . "]\n");
				fwrite($fd, "type = " . $adapter->getType() . "\n");
				fwrite($fd, "port = " . $adapter->getport() . "\n");
				fwrite($fd, "baurate = " . $adapter->getBaurate() . "\n");
				fwrite($fd, "key = " . $adapter->getKey() . "\n");
				fwrite($fd, "\n");

			}
			fclose($file);
			chmod($daemonConfigFileName,0660);
		} else {
			throw new Exception(sprintf(__("Erreur lors de la création du fichier: %s",__FILE__), $daemonConfigFileName));
		}
			
	}

	public static function deamon_stop() {
		return self::daemon_stop();
	}
	public static function daemon_stop() {
		self::$_MQTT2::removePluginTopic(self::$_TOPIC_PREFIX);
	}

  /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
  public static function cron5() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
  public static function cron10() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  public static function cron15() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
  public static function cron30() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
  */

  /*
  * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
  */

  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
	// do some checks or modify on $value
	return $value;
  }
  */

  /*
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
	// no return value
  }
  */

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

  // Fonction exécutée automatiquement avant la création de l'équipement
  public function preInsert() {
  }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
	$this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
	$this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

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
