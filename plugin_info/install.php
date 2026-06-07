<?php
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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function SmartMeterUSB_goto_1() {
	config::save('supplier', 'other', 'SmartMeterUSB');
	config::save('country', 'other', 'SmartMeterUSB');

	$convertersConfigs = config::searchKey('converter::%', 'SmartMeterUSB');
	foreach ($converterConfigs as $converterConfig) {
		if (array_key_exists('baurate',$converterConfig)){
			$converterConfig['baudrate'] = $converterConfig['baurate'];
			unset ($converterConfig['baurate']);
			config::save('converter::' . $converterConfig['id'],$converterConfig,"SmartMeterUSB");
		}
	}
			
	// Initialisation de "protocol" à la valeur pas défau
	$converters = SmartMeterUSBConverter::all();
	foreach ($converters as $converter) {
		$converter->save();
	}
}

function SmartMeterUSB_upgrade() {
	$lastLevel = 1;

	$pluginLevel = config::byKey('pluginLevel', 'SmartMeterUSB', 0);
	log::add("SmartMeterUSB","info","pluginLevel: " . $pluginLevel . " => " . $lastLevel);
	for ($level = 0; $level <= $lastLevel; $level++) {
		if ($pluginLevel < $level) {
			$function = 'SmartMeterUSB_goto_' . $level;
			if (function_exists($function)) {
				log::add("SmartMeterUSB","debug","execution de " . $function . "()");
				$function();
			}
			config::save('pluginLevel',$level,'SmartMeterUSB');
			$pluginLevel = $level;
			log::add("SmartMeterUSB","info","pluginLevel: " . $pluginLevel);
		}
	}
}

// Fonction exécutée automatiquement après l'installation du plugin
function SmartMeterUSB_install() {
	log::add("SmartMeterUSB","info","execution de SmartMeterUSB_install()");
	SmartMeterUSB_upgrade();
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function SmartMeterUSB_update() {
	log::add("SmartMeterUSB","info","execution de SmartMeterUSB_update()");
	SmartMeterUSB_upgrade();
}

// Fonction exécutée automatiquement après la suppression du plugin
function SmartMeterUSB_remove() {
}
