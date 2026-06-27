# vim: tabstop=4 autoindent expandtab
import logging
import json
from smartmeter_datacollector.sinks.data_sink import DataSink
from smartmeter_datacollector.smartmeter.meter_data import MeterDataBundle
from smartmeter_datacollector.smartmeter.obis import OBISCode

class QueueSink(DataSink):
    def __init__(self, _queue):
        self._queue = _queue

    async def start(self):
        pass

    async def stop(self):
        pass

    async def send(self, data_bundle):
        source = data_bundle.source
        timestamp = data_bundle.timestamp
        logging.info(data_bundle.__repr__())
        for data_point in data_bundle.data_points:
            payload = {
                'source'     : source,
                'timestamp'  : timestamp.timestamp(),
                'identifier' : data_point.type.identifier,
                'obis'   : str(data_point.obis),
                'value'   : data_point.value,
                'unit'   : data_point.type.unit,
            }
            logging.info("SEND TO QUEUE %s", payload)
            self._queue.put(json.dumps(payload))
