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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<div id="div_SmartMeterUSBConfig">
	<form class="form-horizontal">
		<fieldset>
			<div>
				<legend><i class="fas fa-tachometer-alt"></i> {{Compteurs}}
			</div>
			<div class="form-group">
				<label class="col-sm-2 col-xs-6 control-label">{{Création auto des compteurs}}</label>
				<div class="col-sm-9 col-xs-6">
					<input class="configKey" type="checkbox" data-l1key="autoCreateCounter" checked></input>
				</div>
			<div class="form-group">
			</div>
				<label class="col-sm-2 col-xs-6 control-label">{{Création auto des commandes}}</label>
				<div class="col-sm-9 col-xs-6">
					<input class="configKey" type="checkbox" data-l1key="autoCreateCmd" checked></input>
				</div>
			</div>
		</fieldset>
		<fieldset>
			<div>
				<legend><i class="fab fa-usb"></i> {{Adaptateurs}}
					<a class="btn btn-success btn-xs pull-right" id="bt_addAdapter" style="position:relative;top:-5px">
						<i class="fas fa-plus-circle"></i> {{Ajouter un adaptateur}}
					</a>
				</legend>
				<div id='adaptersContainer'></div>
			</div>
		</fieldset>
	</form>
</div>
<?php include_file('desktop', 'configuration', 'js', 'SmartMeterUSB'); ?>
