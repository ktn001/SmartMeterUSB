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


def options():
    global pidFile
    global configFile

    parser = argparse.ArgumentParser()
    parser.add_argument("-p", "--pidfile", help="fichier pid", required=True)
    parser.add_argument(
        "-c", "--configfile", help="fichier de configuration", required=True
    )
    args = parser.parse_args()

    if args.pidfile:
        pidFile = args.pidfile

    if args.configfile:
        configFile = args.configfile


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
signal.signal(signal.SIGTERM, signal_handler)
pid = str(os.getpid())
f = open(pidFile, "w")
f.write(f"{pid}\n")
f.close()


conf = config.read_config_files(configFile)
asyncio.run(build_and_start(conf))
