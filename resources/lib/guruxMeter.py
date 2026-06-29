# vim: tabstop=4 autoindent expandtab
import logging
import serial
import json
import asyncio
import time
from gurux_dlms import GXDLMSClient, GXByteBuffer, GXReplyData
from gurux_dlms.enums import InterfaceType, Authentication

# Dictionnaire des unités DLMS
DLMS_UNITS = {27: "W", 30: "Wh", 33: "A", 35: "V"}

class GuruxMeter():
    def __init__(self, _queue, _port, _baudrate):
        self._queue = _queue
        self._port = _port
        self._baudrate = _baudrate
        self.stream_buffer = GXByteBuffer()
        self._counterId = ''
        self.client = GXDLMSClient()
        self.client.interfaceType = InterfaceType.HDLC
        self.client.useLogicalNameReferencing = True
        self.client.clientAddress = 16
        self.client.serverAddress = 1
        self.client.authentication = Authentication.NONE


    async def start(self):
        logging.debug("START GURUX METER: baudrate: %s, port: %s",self._baudrate, self._port)
        try:
            ser = serial.Serial(
                port = self._port,
                baudrate = self._baudrate,
                bytesize=serial.EIGHTBITS,
                parity=serial.PARITY_NONE,
                stopbits=serial.STOPBITS_ONE,
                timeout=0.5
            )
            startTime = time.time()
        except:
            logging.error("Impossible d'ouvrir le port série %s", self._port)
            raise

        try:
            while True:
                raw_bytes = ser.read_all()
                if raw_bytes:
                    self.stream_buffer.set(raw_bytes)
                    while True:
                        reply_data_container = GXReplyData()
                        if self.client.getData(self.stream_buffer, reply_data_container):
                            raw_frame = reply_data_container.data

                            if raw_frame and len(raw_frame) > 0:
                                decoded_value = reply_data_container.value
                                if decoded_value is not None and isinstance(decoded_value, (list, tuple)):
                                    meter_data = {}

                                    for item in decoded_value:
                                        if isinstance(item, list) and len(item) >= 2:
                                            # 1. Formatage du code OBIS
                                            ob = item[0]
                                            obis_str = f"{str(ob[0])}.{str(ob[1])}:{str(ob[2])}.{str(ob[3])}.{str(ob[4])}.{str(ob[5])}"

                                            # 2. Conversion valeur brute / texte
                                            raw_val = item[1]
                                            if isinstance(raw_val, bytearray):
                                                try:
                                                    final_value = raw_val.decode('utf-8')
                                                except:
                                                    final_value = raw_val.hex()
                                            else:
                                                final_value = raw_val

                                            # 3. Application du scalefactor
                                            unit_str = ""
                                            if len(item) == 3 and isinstance(item[2], list) and len(item[2]) >= 2:
                                                scale_factor = item[2][0]
                                                unit_code = item[2][1]

                                                if isinstance(scale_factor, (int, float)) and scale_factor != 0:
                                                    final_value = round(float(raw_val) * (10 ** scale_factor), 3)

                                                unit_str = DLMS_UNITS.get(unit_code, f"Unit_{unit_code}")

                                            # Insertion dans le dictionnaire temporaire
                                            meter_data[obis_str] = {
                                                "value": final_value,
                                                "unit": unit_str
                                            }
                                            logging.debug ("%s  value: %s unit: %s",obis_str, final_value, unit_str)
                                    if (not self._counterId):
                                        for obis, value in meter_data.items():
                                            if obis == '0.0:96.1.1.255':
                                                self._counterId = value['value']
                                                break
                                            if obis == '0.0:96.1.1.255':
                                                self.alt1 = value['value']
                                            if hasattr(self, 'alt1') and (time.time()-self.startTime) > 120:
                                                self._counterId = self.alt1
                                                break
                                            if obis == '0.0:42.0.0.255':
                                                self.alt2 = value['value']
                                            if hasattr(self, 'alt2') and (time.time()-self.startTime) > 180:
                                                self._counterId = self.alt2
                                                break
                                    if (self._counterId):
                                        data = {'counterId': self._counterId, 'data': meter_data}
                                        self._queue.put(json.dumps(data))


                        else:
                            break
                else:
                    await asyncio.sleep (0.5)
        except Exception as e:
            logging.error(e)
            raise

    async def stop(self):
        pass
