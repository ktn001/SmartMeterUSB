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

class SmartMeterUSBAdapter {
	private $id = -1;
	private $type = '';
	private $port = '';
	private $baurate = '2400';
	private $key = '';
	private $enable = 0;
	private $_changed = false;

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
			if (isset($config['value']['key'])) {
				$config['value']['key'] = utils::decrypt($config['value']['key']);
			} else {
				$config['value']['key'] = '';
			}
			$adapter = new self();
			utils::a2o($adapter,$config['value']);
			$adapter->_changed = false;
			$adapters[] = $adapter;
		}
		return $adapters;
	}

	public static function byId($id) {
		$key = 'adapter::' . $id;
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
		$adapter->_changed = false;
		return $adapter;
	}

	/* *************************************** */
	/* ********* Méthodes d'instance ********* */
	/* *************************************** */

	public function save() {
		if (!is_numeric($this->getId()) || $this->getId() < 1 ) {
			$this->setId(self::nextId());
		}
		$value = utils::o2a($this);
		$value['key'] = utils::encrypt($value['key']);
		$value = json_encode($value);
		$key = 'adapter::' . $this->getId();
		config::save($key, $value, 'SmartMeterUSB');
		$this->_changed = false;
	}

	public function remove() {
		if ($this->getId() == -1) {
			return;
		}
		$key = 'adapter::' . $this->getId();
		config::remove($key, 'SmartMeterUSB');
		return;
	}

	public function isChanged() {
		return $this->_changed;
	}

	/* *********************************** */
	/* ********* Getters setters ********* */
	/* *********************************** */

	/* id */
	public function setId($_id) {
		if ($this->id !== $_id) {
			$this->_changed = true;
		}
		$this->id = $_id;
		return $this;
	}
	public function getId() {
		return $this->id;
	}

	/* type */
	public function setType($_type) {
		if ($this->type !== $_type) {
			$this->_changed = true;
		}
		$this->type = $_type;
		return $this;
	}
	public function getType() {
		return $this->type;
	}

	/* port */
	public function setPort($_port) {
		if ($this->port !== $_port) {
			$this->_changed = true;
		}
		$this->port = $_port;
		return $this;
	}
	public function getPort() {
		return $this->port;
	}

	/* key */
	public function setKey($_key) {
		if ($this->key !== $_key) {
			$this->_changed = true;
		}
		$this->key = $_key;
		return $this;
	}
	public function getKey() {
		return $this->key;
	}

	/* baurate */
	public function setBaurate($_baurate) {
		if ($this->baurate !== $_baurate) {
			$this->_changed = true;
		}
		$this->baurate = $_baurate;
		return $this;
	}
	public function getBaurate() {
		return $this->baurate;
	}

	/* enable */
	public function setEnable($_enable) {
		if ($this->enable !== $_enable) {
			$this->_changed = true;
		}
		$this->enable = $_enable;
		return $this;
	}
	public function getEnable() {
		return $this->enable;
	}
}
