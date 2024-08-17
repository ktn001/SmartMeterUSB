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

require_once __DIR__ . '/smateMeterUSBAdapter.class.php';

class SmartMeterUSBAdapter {
	private $id = -1;
	private $type = '';
	private $port = '';
	private $baurate = '2400';
	private $key = '';
	private $enable = 0;

    /* *********************************** */
	/* ********* Méthodes static ********* */
    /* *********************************** */

	public static function nextId() {
		$nextId = config::byKey('nextAdapterId','SmartMeterUSB',1);
		config::save('nextAdapterId',$nextId+1,'SmartMeterUSB');
		return $nextId;
	}

	public static function all($_onlyEnable = false) {
		$configs = config::searchKey('adapter::%', 'SmartMeterUSB');
		$adapters = [];
		foreach ($configs as $config) {
			if ($_onlyEnable) {
				if (!isset($config['value']['enable']) || $config['value']['enable'] != 1) {
					continue;
				}
			}
			if (isset($value['key'])) {
				$config['value']['key'] = utils::decrypt($config['value']['key']);
			} else {
				$config['value']['key'] = '';
			}
			$adapter = new self();
			utils::a2o($adapter,$config['value']);
			$adapters[] = $adapter;
		}
		return $adapeters;
	}

	public static function byId($id) {
		$key = 'adapter::' . $this->getId();
		$value = config::byKey($key, 'SmartMeterUSB');
		if ($value == '') {
			return null;
		}
		$value = is_json($value,$value);
		if (isset($value['key'])) {
			$value['key'] = utils::decrypt($value['key']);
		} else {
			$value['key'] = '';
		}
		$adapter = new self();
		utils::a2o($adapter,$value);
		return $adapters;
	}

	/* *************************************** */
	/* ********* Méthodes d'instance ********* */
	/* *************************************** */

	public function save() {
		if ($this->getId() == -1) {
			$this->setId(self::nextId());
		}
		$value = utils::o2a($this);
		$value['key'] = utils::encrypt($value['key']);
		$value = json_encode($value);
		$key = 'adapter::' . $this->getId();
		config::save($key, $value, 'SmartMeterUSB');
	}

	/* *********************************** */
	/* ********* Getters setters ********* */
	/* *********************************** */

	/* id */
	public function setId($_id) {
		$this->id = $_id;
		return $this;
	}
	public function getId() {
		return $this->id;
	}

	/* type */
	public function setType($_type) {
		$this->type = $_type;
		return $this;
	}
	public function getType() {
		return $this->type;
	}

	/* port */
	public function setPort($_port) {
		$this->port = $_port;
		return $this;
	}
	public function getPort() {
		return $this->port;
	}

	/* key */
	public function setKey($_key) {
		$this->key = $_key;
		return $this;
	}
	public function getKey() {
		return $this->key;
	}

	/* baurate */
	public function setBaurate($_baurate) {
		$this->baurate = $_baurate;
		return $this;
	}
	public function getBaurate() {
		return $this->baurate;
	}

	/* enable */
	public function setEnable($_enable) {
		$this->enable = $_enable;
		return $this;
	}
	public function getEnable() {
		return $this->enable;
	}
}
