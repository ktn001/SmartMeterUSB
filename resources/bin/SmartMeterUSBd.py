# vim: tabstop=4 autoindent expandtab
import os
import sys
import argparse
import logging
import asyncio
from asyncio import CancelledError
import signal
from smartmeter_datacollector import config, factory
from configparser import ConfigParser


pidFile = None
configFile = None
loglevel = "info"


def options():
    global pidFile
    global configFile

    parser = argparse.ArgumentParser()
    parser.add_argument("-p", "--pidfile", help="fichier pid", required=True)
    parser.add_argument("-c", "--configfile", help="fichier de configuration", required=True)
    parser.add_argument("-l", "--loglevel", help="niveau de log", type=str)
    args = parser.parse_args()

    if args.pidfile:
        pidFile = args.pidfile

    if args.configfile:
        configFile = args.configfile
        configFile = "/tmp/jeedom/SmartMeterUSB/datacollector.ini"

    if args.loglevel:
        loglevel = args.loglevel

def initLogging():
    levels = {
        'debug': logging.DEBUG,
        'info': logging.INFO,
        'notice': logging.WARNING,
        'warning': logging.WARNING,
        'error': logging.ERROR,
        'critical': logging.CRITICAL,
        'none': logging.CRITICAL
    }
    level = levels.get(loglevel, logging.WARNING)
    level = logging.DEBUG
    format = '%(asctime)-15s[%(levelname)s] : %(message)s'
    logging.basicConfig(level=level,format=format, datefmt="%Y-%m-%d %H:%M:%S")

def signal_handler(sig, frame):
    sys.exit(0)


async def build_and_start(app_config: ConfigParser):
    readers = factory.build_meters(app_config)
    sinks = factory.build_sinks(app_config)
    data_collector = factory.build_collector(readers, sinks)

    await asyncio.gather(*[sink.start() for sink in sinks])

    try:
        await asyncio.gather(
            *[reader.start() for reader in readers], data_collector.process_queue()
        )
    except CancelledError:
        pass
    finally:
        logging.info("App shutting down now.")
        await asyncio.gather(*[sink.stop() for sink in sinks])
        os.unlink(pidFile)


options()
initLogging()
signal.signal(signal.SIGTERM, signal_handler)
pid = str(os.getpid())
f = open(pidFile, "w")
f.write(f"{pid}\n")
f.close()


conf = config.read_config_files(configFile)
asyncio.run(build_and_start(conf))
