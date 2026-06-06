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

try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
	require_once __DIR__ . '/../class/SmartMeterUSB.class.php';
	include_file('core', 'authentification', 'php');

	if (!isConnect('admin')) {
		throw new Exception(__('401 - Accès non autorisé', __FILE__));
	}

	if (init('action') == 'getNames') {
		$names = SmartMeterUSBSimulator::getNames();
		ajax::success($names);
	}

	if (init('action') == 'command') {
		$name = init('name');
		$command = init('command');
		$simulator = SmartMeterUSBSimulator::byName($name);
		switch ($command) {
			case 'start':
				$simulator->start();
				ajax::success();
			break;
			case 'stop':
				$simulator->stop();
				ajax::success();
			break;
		}
		throw new Exception(__('Aucune commande correspondante à', __FILE__) . ' : ' . $command);
	}

	if (init('action') == 'getStates') {
		$states = SmartMeterUSBSimulator::getAllStates();
		ajax::success($states);
	}

	if (init('action') == 'getReaderPorts') {
		$readerPorts = SmartMeterUSBSimulator::getAllReaderPorts();
		ajax::success($readerPorts);
	}

	throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
	/*     * *********Catch exeption*************** */
}
catch (Exception $e) {
	ajax::error(displayException($e), $e->getCode());
}
