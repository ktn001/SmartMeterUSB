# vim: tabstop=4 autoindent expandtab
import logging
import json
import asyncio
import paho.mqtt.client as mqtt

class QueueToMqtt():
    def __init__(self, _username, _password, _host, _port, _queue):
        self._queue = _queue
        self._mqtt_username = _username
        self._mqtt_password = _password
        self._mqtt_host = _host
        self._mqtt_port = _port

    async def start(self):
        logging.info("START QUEUETOMQTT")
        self._mqttc = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
        self._mqttc.username_pw_set(self._mqtt_username, self._mqtt_password)
        self._mqttc.connect(
            host=self._mqtt_host,
            port=int(self._mqtt_port),
        )
        self._mqttc.enable_logger()
        self._mqttc.loop_start()
        self._task = asyncio.create_task(self.run())

    async def stop(self):
        logging.info("STOP QUEUETOMQTT")
        self._mqttc.loop_stop()
        self._task.cancel()

    async def run(self):
        try:
            while True:
                if not self._queue.empty():
                    payload = self._queue.get()
                    payload = json.loads(payload)
                    topic = self._topic(payload)
                    data = {
                        'value': payload['value'],
                        'timestamp': payload['timestamp']
                    }
                    data = json.dumps(data)
                    self._mqttc.publish(topic, data, qos=1)
                    # await asyncio.sleep(0.1)
                else:
                    await asyncio.sleep(1)
        except Exception as e:
            logging.error(e)

    def _topic(self, payload):
        if 'source' in payload.keys():
            return f"smartmeter/{payload['source']}/{payload['identifier']}"
        else:
            return "smartmeter/inconnu"
