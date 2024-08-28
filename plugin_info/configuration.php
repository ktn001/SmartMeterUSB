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

sendVarToJs('counters', SmartMeterUSB::getCounters());

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<div id="div_SmartMeterUSBConfig">
	<div class="col-md-6 col-sm-12">
		<form class="form-horizontal">
			<div>
				<legend><i class="fas fa-tachometer-alt"></i> {{Compteurs}}</legend>
			</div>
			<div class="form-group">
				<label class="col-sm-6 control-label">{{Création auto des compteurs}}</label>
				<div class="col-sm-1">
					<input class="configKey form-control" type="checkbox" data-l1key="autoCreateCounter" checked></input>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-6 control-label">{{Création auto des commandes}}</label>
				<div class="col-sm-1">
					<input class="configKey form-control" type="checkbox" data-l1key="autoCreateCmd" checked></input>
				</div>
			</div>
		</form>
	</div>
	<div class="col-md-6 col-sm-12">
		<form class="form-horizontal">
			<legend><i class="fas fa-clock"></i> {{Plages horaire}}</legend>
			<fieldset>
				<div class="form-group">
					<label class="col-sm-1 control-label">1</label>
					<div class="col-sm-4">
						<input class="configKey form-control" data-l1key="tarif:1:txt" placeholder="{{HP}}"></input>
					</div>
					<label class="col-sm-1 control-label">2</label>
					<div class="col-sm-4">
						<input class="configKey form-control" data-l1key="tarif:2:txt" placeholder="{{HC}}"></input>
					</div>
				</div>
			</fieldset>
			<!--
			<fieldset>
				<div class="form-group">
					<label class="col-sm-1 control-label">3</label>
					<div class="col-sm-4">
						<input class="configKey form-control" data-l1key="tarif:3:txt"></input>
					</div>
					<label class="col-sm-1 control-label">4</label>
					<div class="col-sm-4">
						<input class="configKey form-control" data-l1key="tarif:4:txt"></input>
					</div>
				</div>
			</fieldset>
			-->
		<form class="form-horizontal">
	</div>
	<div class="col-sm-12 form-horizontal">
		<legend><i class="fab fa-usb"></i> {{Convertisseurs USB}}
			<a class="btn btn-success btn-xs pull-right" id="bt_addConverter" style="position:relative;top:-5px">
				<i class="fas fa-plus-circle"></i> {{Ajouter un convertisseur}}
			</a>
		</legend>
		<div id='convertersContainer'></div>
	</div>
</div>
<?php include_file('desktop', 'configuration', 'js', 'SmartMeterUSB'); ?>
