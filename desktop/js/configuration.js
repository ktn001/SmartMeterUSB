// vim: tabstop=2 autoindent expandtab

if (typeof SmartMeterUSBConfig === "undefined") {
  var SmartMeterUSBConfig = {
    ajaxUrl: "plugins/SmartMeterUSB/core/ajax/SmartMeterUSB.ajax.php",
  }

  SmartMeterUSBConfig.init = function () {
    document
      .getElementById("div_SmartMeterUSBConfig")
      .addEventListener("click", function (event) {
        let _target = null

        if ((_target = event.target.closest("#bt_addAdapter"))) {
          SmartMeterUSBConfig.addAdapter()
        }

        if ((_target = event.target.closest(".bt_remove_adapter"))) {
          _target.closest(".adapter").remove()
        }
      })

    window["SmartMeterUSB_postSaveConfiguration"] =
      SmartMeterUSBConfig.saveAdapters
  }

  SmartMeterUSBConfig.addAdapter = function (adapter) {
    console.log(counters)
    let adptr= ''
    adptr += '<div  style="background-color:var(--bg-modal-color);margin-top:5px; margin-bottom:20px; padding:10px">'
    adptr += '  <div>'
    adptr += '    <a class="btn btn-danger btn-xs pull-right bt_remove_adapter" style="position:relative;top:-10px;left:14px">'
    adptr += '      <i class="fas fa-minus-circle"></i>'
    adptr += '    </a>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">ID</label>'
    adptr += '    <div class="col-sm-1">'
    adptr += '      <span class="adapterAttr" data-l1key="id"></span>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Type}}'
    adptr += '      <sup><i class="fas fa-question-circle" title="{{Compteur connecté à l\'adaptateur}}"></i></sup>'
    adptr += '    </label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '       <select class="adapterAttr" data-l1key="type">'
    Object.keys(counters).forEach(function(key) {
      adptr += '<option value="' + key +'">' + counters[key] + '</option>'
    })
    adptr += '       </select>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Port}}'
    adptr += '      <sup><i class="fas fa-question-circle" title="{{port USB}}"></i></sup>'
    adptr += '    </label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '      <input class="adapterAttr" data-l1key="port" style="width:100%" placeholder="/dev/ttyUSB0"></input>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Baurate}}'
    adptr += '      <sup><i class="fas fa-question-circle" title="{{Vitesse de transmission}}"></i></sup>'
    adptr += '    </label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '      <input class="adapterAttr" data-l1key="baurate" style="width:100%" placeholder="2400"></input>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Clé}}'
    adptr += '      <sup><i class="fas fa-question-circle" title="{{Clé d\'encryprion du compteur}}"></i></sup>'
    adptr += '    </label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '      <input class="adapterAttr" data-l1key="key" style="width:100%"></input>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '  <div class="form-group">'
    adptr += '    <label class="col-sm-3 control-label">{{Activer}}</label>'
    adptr += '    <div class="col-sm-9">'
    adptr += '      <input type="checkbox"class="adapterAttr" data-l1key="enable"></input>'
    adptr += '    </div>'
    adptr += '  </div>'
    adptr += '</div>'
    let newAdapter = document.createElement("div")
    newAdapter.addClass("adapter col-lg-4 col-md-6 col-sm-12")
    newAdapter.innerHTML = adptr
    document.getElementById("adaptersContainer").appendChild(newAdapter)
    newAdapter.setJeeValues(adapter, ".adapterAttr")
    jeedomUtils.initTooltips(document.getElementById("adaptersContainer"))
  }

  SmartMeterUSBConfig.printAdapters = function () {
    domUtils.ajax({
      type: "POST",
      async: true,
      global: false,
      url: SmartMeterUSBConfig.ajaxUrl,
      data: {
        action: "getAdapters",
      },
      dataType: "json",
      success: function (data) {
        if (data.state != "ok") {
          jeedomUtils.showAlert({ message: data.result, level: "danger" })
          return
        }
        document.getElementById("adaptersContainer").empty()
        data.result.forEach(SmartMeterUSBConfig.addAdapter)
      },
    })
  }

  SmartMeterUSBConfig.saveAdapters = function () {
    let adapters = document
      .querySelectorAll("#adaptersContainer .adapter")
      .getJeeValues(".adapterAttr")
    domUtils.ajax({
      async: true,
      global: false,
      url: SmartMeterUSBConfig.ajaxUrl,
      data: {
        action: "saveAdapters",
        adapters: JSON.stringify(adapters),
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
SmartMeterUSBConfig.printAdapters()
