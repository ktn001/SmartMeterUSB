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

	if (init('action') == 'getAdapters') {
		$adapters = SmartMeterUSBAdapter::all();
		foreach ($adapters as $key => $adapter) {
			$adapters[$key] = utils::o2a($adapter);
		}
		ajax::success($adapters);
	}

	if (init('action') == 'saveAdapters') {
		$adapters = json_decode(init('adapters'),true);
		$dbList = SmartMeterUSBAdapter::all();
		foreach ($adapters as $a_adapter) {
			if ($a_adapter['id'] == '') {
				$o_adapter = new SmartMeterUSBAdapter();
			} else {
				$o_adapter = SmartMeterUSBAdapter::byId($a_adapter['id']);
			}
			if (!is_object($o_adapter)) {
				$o_adapter = new SmartMeterUSBAdapter();
			}
			utils::a2o($o_adapter,$a_adapter);
			if ($o_adapter->isChanged()) {
				$o_adapter->save();
			}
			$enableList[$o_adapter->getId()] = true;
		}
		foreach ($dbList as $dbAdapter) {
			if (!isset($enableList[$dbAdapter->getId()])) {
				$dbAdapter->remove();
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
