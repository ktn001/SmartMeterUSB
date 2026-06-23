# vim: tabstop=4 autoindent expandtab
import os
import sys
import argparse
import logging
import asyncio
from asyncio import CancelledError
import signal
import smartmeter_datacollector.config
import smartmeter_datacollector.factory
from configparser import ConfigParser

pid_file = None
config_file = None
loglevel = "info"

smrt_coll = {
    'sinks': [],
    'readers': [],
    'collector': None
}

def options():
    global pid_file
    global config_file
    global loglevel

    parser = argparse.ArgumentParser()
    parser.add_argument("-p", "--pidfile", help="fichier pid", required=True)
    parser.add_argument("-c", "--configfile", help="fichier de configuration", required=True)
    parser.add_argument("-l", "--loglevel", help="niveau de log", type=str)
    args = parser.parse_args()

    if args.pidfile:
        pid_file = args.pidfile

    if args.configfile:
        config_file = args.configfile

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
    format = '%(asctime)-15s[%(levelname)s] : %(message)s'
    logging.basicConfig(level=level,format=format, datefmt="%Y-%m-%d %H:%M:%S")

def signal_handler(sig, frame):
    pass

def getStartTasks(config_file):
    global smrt_coll

    config = ConfigParser()
    config.read(config_file)

    reader_tasks = []
    sink_tasks = []
    collector_tasks = []
    if (config.has_section('datacollector')):
        if (not config.has_option('datacollector','ConfigFile')):
            logging.error("Le fichier de configuration pour 'smartmeter_datacollector' n'est pas défini!")
        else:
            smtr_dtacoll_conf_file = config.get('datacollector','ConfigFile')
            smtr_dtacoll_conf = smartmeter_datacollector.config.read_config_files(smtr_dtacoll_conf_file)

            smrt_coll['readers'] = smartmeter_datacollector.factory.build_meters(smtr_dtacoll_conf)
            smrt_coll['sinks'] = smartmeter_datacollector.factory.build_sinks(smtr_dtacoll_conf)
            smrt_coll['collector'] = smartmeter_datacollector.factory.build_collector(smrt_coll['readers'], smrt_coll['sinks'])

            for sink in smrt_coll['sinks']:
                sink_tasks.append(sink.start())
            for reader in smrt_coll['readers']:
                reader_tasks.append(reader.start())
            collector_tasks.append(smrt_coll['collector'].process_queue())
    return {
        'sink_tasks': sink_tasks,
        'reader_tasks': reader_tasks,
        'collector_tasks': collector_tasks,
    }

def getStopTasks():
    sink_tasks = []
    for sink in smrt_coll['sinks']:
        sink_tasks.append(sink.stop())
    return {
        'sink_tasks': sink_tasks,
    }

async def build_and_run(config_file):

    tasks = getStartTasks(config_file)
    try:
        await asyncio.gather(*tasks['sink_tasks'])
        await asyncio.gather(*tasks['reader_tasks'], *tasks['collector_tasks'])
    except CancelledError:
        logging.info("Cancelled_error")
    finally:
        logging.info("App shutting down now.")
        tasks = getStopTasks()
        await asyncio.gather(*tasks['sink_tasks'])
        os.unlink(pid_file)


options()
initLogging()

#signal.signal(signal.SIGTERM, signal_handler)

pid = str(os.getpid())
f = open(pid_file, "w")
f.write(f"{pid}\n")
f.close()

asyncio.run(build_and_run(config_file))
