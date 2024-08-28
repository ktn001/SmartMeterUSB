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

	if (init('action') == 'getConverters') {
		$converters = SmartMeterUSBConverter::all();
		foreach ($converters as $key => $converter) {
			$converters[$key] = utils::o2a($converter);
		}
		ajax::success($converters);
	}

	if (init('action') == 'saveConverters') {
		$converters = json_decode(init('converters'),true);
		$dbList = SmartMeterUSBConverter::all();
		foreach ($converters as $a_converter) {
			if ($a_converter['id'] == '') {
				$o_converter = new SmartMeterUSBConverter();
			} else {
				$o_converter = SmartMeterUSBConverter::byId($a_converter['id']);
			}
			if (!is_object($o_converter)) {
				$o_converter = new SmartMeterUSBConverter();
			}
			utils::a2o($o_converter,$a_converter);
			if ($o_converter->isChanged()) {
				$o_converter->save();
			}
			$enableList[$o_converter->getId()] = true;
		}
		foreach ($dbList as $dbConverter) {
			if (!isset($enableList[$dbConverter->getId()])) {
				$dbConverter->remove();
			}
		}
		ajax::success();
	}

	if (init('action') == 'getImageForCounterType') {
		ajax::success(SmartMeterUSB::getImageForCounterType(init('counterType')));
	}

	throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
	/*     * *********Catch exeption*************** */
}
catch (Exception $e) {
	ajax::error(displayException($e), $e->getCode());
}
