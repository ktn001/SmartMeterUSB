// vim: tabstop=2 autoindent expandtab

if (typeof SmartMeterUSBConfig === "undefined") {
  var SmartMeterUSBConfig = {
    ajaxUrl : 'plugins/SmartMeterUSB/core/ajax/SmartMeterUSB.ajax.php',
  }

  SmartMeterUSBConfig.init = function() {
    document.getElementById('div_SmartMeterUSBConfig').closest('.panel-primary').addEventListener('click', function(event) {
      let _target = null

      if (_target = event.target.closest('#bt_addAdapter')) {
        SmartMeterUSBConfig.addAdapter()
      }

      if (_target = event.target.closest('#bt_savePluginConfig')) {
        SmartMeterUSBConfig.saveAdapters()
      }
    })
  }

  SmartMeterUSBConfig.addAdapter = function(adapter) {
    let adptr = '<div>'
    adptr +=   '<div class="form-group">'
    adptr +=     '<label class="col-sm-2 control-label">ID</label>'
    adptr +=     '<div class="col-sm-8">'
    adptr +=       '<span class="adapterAttr" data-l1key="id"></span>'
    adptr +=     '</div>'
    adptr +=   '</div>'
    adptr +=   '<div class="form-group">'
    adptr +=     '<label class="col-sm-2 control-label">{{Type}}'
    adptr +=       '<sup><i class="fas fa-question-circle" title="{{Compteur connecté à l\'adaptateur}}"></i></sup>'
    adptr +=     '</label>'
    adptr +=     '<div class="col-sm-8">'
    adptr +=       '<select class="adapterAttr" data-l1key="type">'
    adptr +=         '<option value="lg360">Landis+Gyr E360</option>'
    adptr +=         '<option value="lg450">Landis+Gyr E450</option>'
    adptr +=         '<option value="lg570">Landis+Gyr E570</option>'
    adptr +=         '<option value="iskraam550">Iskraemeco AM550</option>'
    adptr +=         '<option value="kamstrup_han">Kamstrup OMNIPOWER with HAN-NVE module</option>'
    adptr +=       '</select>'
    adptr +=     '</div>'
    adptr +=   '</div>'
    adptr +=   '<div class="form-group">'
    adptr +=     '<label class="col-sm-2 control-label">{{Port}}'
    adptr +=       '<sup><i class="fas fa-question-circle" title="{{port USB}}"></i></sup>'
    adptr +=     '</label>'
    adptr +=     '<div class="col-sm-8">'
    adptr +=       '<input class="adapterAttr" data-l1key="port" style="width:100%" placeholder="/dev/USB0"></input>'
    adptr +=     '</div>'
    adptr +=   '</div>'
    adptr +=   '<div class="form-group">'
    adptr +=     '<label class="col-sm-2 control-label">{{Baurate}}'
    adptr +=       '<sup><i class="fas fa-question-circle" title="{{Vitesse de transmission}}"></i></sup>'
    adptr +=     '</label>'
    adptr +=     '<div class="col-sm-8">'
    adptr +=       '<input class="adapterAttr" data-l1key="baurate" style="width:100%" placeholder="2400"></input>'
    adptr +=     '</div>'
    adptr +=   '</div>'
    adptr +=   '<div class="form-group">'
    adptr +=     '<label class="col-sm-2 control-label">{{Clé}}'
    adptr +=       '<sup><i class="fas fa-question-circle" title="{{Clé d\'encryprion du compteur}}"></i></sup>'
    adptr +=     '</label>'
    adptr +=     '<div class="col-sm-8">'
    adptr +=       '<input class="adapterAttr" data-l1key="key" style="width:100%"></input>'
    adptr +=     '</div>'
    adptr +=   '</div>'
    adptr += '</div>'
    adptr += ''
    adptr += ''
    adptr += ''
    let newAdapter = document.createElement('div')
    newAdapter.innerHTML = adptr
    document.getElementById('adaptersContainer').appendChild(newAdapter)
    jeedomUtils.initTooltips(document.getElementById('adaptersContainer'))
  }

  SmartMeterUSBConfig.printAdapters = function() {
    domUtils.ajax({
      type: 'POST',
      async: true,
      global: false,
      url: SmartMeterUSBConfig.ajaxUrl,
      data: {
        action: 'getAdapters',
      },
      dataType: 'json',
      success: function(data) {
        if (data.state != 'ok') {
          jeedonUtils.showAlert({message: data.result, level: 'danger'})
          return
        }
        let adapters = json_decode(data.result)
      }
    })
  }

  SmartMeterUSBConfig.saveAdapters = function() {
  }

}

SmartMeterUSBConfig.init()
SmartMeterUSBConfig.printAdapters()
