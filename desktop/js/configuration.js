// vim: tabstop=2 autoindent expandtab

if (typeof SmartMeterUSBConfig === "undefined") {
  var SmartMeterUSBConfig = {
    ajaxUrl: "plugins/SmartMeterUSB/core/ajax/SmartMeterUSB.ajax.php",
  }

  SmartMeterUSBConfig.init = function () {
    ConfigContainer = document.getElementById("div_SmartMeterUSBConfig")

    if (showDev == 0){
      ConfigContainer.querySelectorAll('.showDev').addClass('hidden')
    }

    ConfigContainer.addEventListener("click", function (event) {
      let _target = null

      if ((_target = event.target.closest("#bt_addConverter"))) {
        SmartMeterUSBConfig.addConverter()
      }

      if ((_target = event.target.closest(".bt_remove_converter"))) {
        _target.closest(".converter").remove()
      }
    })

    ConfigContainer.addEventListener('change', function (event) {
      if ((_target = event.target.closest('.configKey[data-l1key=supplier]'))) {
        if (_target.value == '') {
          _target.value = 'other'
        }
      }
    })

    window["SmartMeterUSB_postSaveConfiguration"] =
      SmartMeterUSBConfig.saveConverters
  }

  SmartMeterUSBConfig.addConverter = function (converter=[]) {
    let adptr = ''
    adptr += '<div  style="background-color:var(--bg-modal-color);margin-top:5px; margin-bottom:20px; padding:10px">'
    adptr += '  <div>'
    adptr += '    <a class="btn btn-danger btn-xs pull-right bt_remove_converter" style="position:relative;top:-10px;left:14px">'
    adptr += '      <i class="fas fa-minus-circle"></i>'
    adptr += '    </a>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">ID</label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '      <span class="converterAttr" data-l1key="id"></span>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Type}}'
    adptr += '      <sup><i class="fas fa-question-circle" title="{{Compteur connecté au convertisseur}}"></i></sup>'
    adptr += '    </label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '       <select class="converterAttr" data-l1key="type">'
    Object.keys(counters).forEach(function (key) {
      adptr += '<option value="' + key + '">' + counters[key]['name'] + " (" + counters[key]['iface'] + ")</option>"
    })
    adptr += '       </select>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Port}}'
    adptr += '      <sup><i class="fas fa-question-circle" title="{{port USB}}"></i></sup>'
    adptr += '    </label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '      <select class="converterAttr" data-l1key="port">'
    for (port in portsUSB) {
      adptr += '        <option value="' + portsUSB[port] + '">' + port + '</option>'
    }
    adptr += '      </select>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Protocol}}</label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '      <select class="converterAttr" data-l1key="protocol">'
    adptr += '        <option value="0">{{auto}}</option>'
    protocols.forEach(function (key) {
      adptr += '    <option value="' + key.id + '">' + key.label + '</option>'
    })
    adptr += '      </select>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Baudrate}}'
    adptr += '      <sup><i class="fas fa-question-circle" title="{{Vitesse de transmission (optionel)}}"></i></sup>'
    adptr += '    </label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '      <input class="converterAttr" data-l1key="baudrate" style="width:100%"></input>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Clé}}'
    adptr += '      <sup><i class="fas fa-question-circle" title="{{Clé d\'encryption du compteur}}"></i></sup>'
    adptr += '    </label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '      <input class="converterAttr" data-l1key="key" style="width:100%"></input>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Activer}}</label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '      <input type="checkbox"class="converterAttr" data-l1key="enable"></input>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '</div>'
    let newConverter = document.createElement("div")
    newConverter.addClass("converter col-lg-4 col-md-6 col-sm-12")
    newConverter.innerHTML = adptr
    document.getElementById("convertersContainer").appendChild(newConverter)
    if (showDev != 0) {
      SmartMeterUSBSimulator.addReaderPortOptions(newConverter.querySelector('[data-l1key=port]'))
      setTimeout(function() {
        newConverter.setJeeValues(converter, ".converterAttr")
      },200)
    } else {
       newConverter.setJeeValues(converter, ".converterAttr")
    }
    jeedomUtils.initTooltips(document.getElementById("convertersContainer"))
  }

  SmartMeterUSBConfig.printConverters = function () {
    domUtils.ajax({
      async: true,
      global: false,
      url: SmartMeterUSBConfig.ajaxUrl,
      data: {
        action: "getConverters",
      },
      dataType: "json",
      success: function (data) {
        if (data.state != "ok") {
          jeedomUtils.showAlert({ message: data.result, level: "danger" })
          return
        }
        document.getElementById("convertersContainer").empty()
        data.result.forEach(SmartMeterUSBConfig.addConverter)
      },
    })
  }

  SmartMeterUSBConfig.saveConverters = function () {
    let converters = document
      .querySelectorAll("#convertersContainer .converter")
      .getJeeValues(".converterAttr")
    domUtils.ajax({
      async: true,
      global: false,
      url: SmartMeterUSBConfig.ajaxUrl,
      data: {
        action: "saveConverters",
        converters: JSON.stringify(converters),
      },
      dataType: "json",
      success: function (data) {
        if (data.state != "ok") {
          jeedomUtils.showAlert({ message: data.result, level: "danger" })
          return
        }
      },
    })
  }
}

SmartMeterUSBConfig.init()
SmartMeterUSBConfig.printConverters()
