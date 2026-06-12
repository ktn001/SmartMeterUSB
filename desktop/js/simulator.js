// vim: tabstop=2 autoindent expandtab
"use strict"

if (typeof SmartMeterUSBSimulator === "undefined") {
  var SmartMeterUSBSimulator = {
    ajaxUrl: "plugins/SmartMeterUSB/core/ajax/simulator.ajax.php",

    init: function() {
      SmartMeterUSBSimulator.buildSimulatorsTable()
      setTimeout(SmartMeterUSBSimulator.simulatorsStates,200)

      document.getElementById('simulatorsTable').addEventListener('click', function(event) {
        let _target = null

        if (_target = event.target.closest('.bt_startSimulator')){
          let simulatorName = _target.closest('tr').dataset.simulatorname
          SmartMeterUSBSimulator.sendCommand(simulatorName,'start')
          return
        }

        if (_target = event.target.closest('.bt_stopSimulator')){
          let simulatorName = _target.closest('tr').dataset.simulatorname
          SmartMeterUSBSimulator.sendCommand(simulatorName,'stop')
          return
        }
      })
    },

    buildSimulatorsTable: function() {
      domUtils.ajax({
        async: true,
        global:false,
        url: SmartMeterUSBSimulator.ajaxUrl,
        data: {
          action: "getNames",
        },
        dataType: "json",
        success: function (data) {
          if (data.state != 'ok') {
            jeedomUtils.showAlert({message: data.result, level: "danger"})
            return
          }
          let names = data.result
          names.forEach(function(name) {
            let tr = '<tr>'
            tr += '<td>' + name + '</td>'
            tr += '<td class="simulatorState"></td>'
            tr += '<td class="simulatorMessage"></td>'
            tr += '<td class="simulatorStart" style="text-align:center;">'
            tr +=   '<a class="btn btn-success btn-xs bt_startSimulator">'
            tr +=     '<i class="fas fa-play"></i>'
            tr +=   '</a>'
            tr += '</td>'
            tr += '<td class="simulatorStop" style="text-align:center;">'
            tr +=   '<a class="btn btn-danger btn-xs bt_stopSimulator">'
            tr +=     '<i class="fas fa-stop"></i>'
            tr +=   '</a>'
            tr += '</td>'
            tr += '<td class="simulatorLastLaunchTime"></td>'
            tr += '</tr>'
            let newRow = document.createElement("tr")
            newRow.innerHTML = tr
            newRow.addClass('simulator')
            newRow.setAttribute('data-simulatorName', name)
            document.getElementById('simulatorsTable').querySelector('tbody').appendChild(newRow)
          })
        }
      })
    },

    sendCommand: function(simulatorName, command) {
      domUtils.ajax({
        async: true,
        global:false,
        url: SmartMeterUSBSimulator.ajaxUrl,
        data: {
          action: "command",
          name: simulatorName,
          command: command,
        },
        dataType: "json",
        success: function (data) {
          if (data.state != 'ok') {
            jeedomUtils.showAlert({message: data.result, level: "danger"})
            return
          }
        }
      })
    },

    simulatorsStates: function() {
      domUtils.ajax({
        async: true,
        global:false,
        url: SmartMeterUSBSimulator.ajaxUrl,
        data: {
          action: "getStates",
        },
        dataType: "json",
        success: function (data) {
          if (data.state != 'ok') {
            jeedomUtils.showAlert({message: data.result, level: "danger"})
            return
          }
          let table = document.getElementById('simulatorsTable')
          if (table === null) {
            return
          }
          Object.keys(data.result).forEach(function(name) {
            let info = data.result[name]
            let content = ''
            if (info['state'] == 0) {
              content = '<span class="label label-danger">NOK</span>'
            } else {
              content = '<span class="label label-success">OK</span>'
            }
            document
              .getElementById('simulatorsTable')
              .querySelector('tr[data-simulatorName="' + name + '"] .simulatorState')
              .innerHTML = content
            document
              .getElementById('simulatorsTable')
              .querySelector('tr[data-simulatorName="' + name + '"] .simulatorMessage')
              .innerHTML = info['msg']
            document
              .getElementById('simulatorsTable')
              .querySelector('tr[data-simulatorName="' + name + '"] .simulatorLastLaunchTime')
              .innerHTML = info['lastLaunchTime']
          })
          setTimeout(SmartMeterUSBSimulator.simulatorsStates, 3000)
        }
      })
    },

    addReaderPortOptions: function (select) {
      domUtils.ajax({
        async: true,
        global:false,
        url: SmartMeterUSBSimulator.ajaxUrl,
        data: {
          action: "getReaderPorts",
        },
        dataType: "json",
        success: function (data) {
          if (data.state != 'ok') {
            jeedomUtils.showAlert({message: data.result, level: "danger"})
            return
          }
          Object.keys(data.result).forEach(function(simulator) {
            //let option = '<option value="' + data.result[simulator] + '">'
            //option += '{{simulateur}} ' + simulator + '</option>'
            let option = document.createElement('option')
            option.innerText = '{{simulateur}} ' + simulator
            option.setAttribute("value", data.result[simulator])
            select.options.add(option)
          })
        }
      })
    }
  }
}

SmartMeterUSBSimulator.init()
