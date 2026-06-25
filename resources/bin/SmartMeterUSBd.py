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
config = None
smtr_config = False
loglevel = "info"

_smtr_collector = False
_coroutines_to_startstop = False
_coroutines_to_run = False


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

def smtrCollector():
    global _smtr_collector

    if type(_smtr_collector) is not bool:
        return _smtr_collector
    if (smtr_config):
        logging.info("Créaton du collecteur pour smartmeter_datacollector")
        _smtr_collector = smartmeter_datacollector.factory.build_collector([], [])
    else:
        _smtr_collector = None
    return _smtr_collector

def coroutinesToStartAndStop():
    """~liste de coroutines devant être démarrés avant les tasks du run et arrêtés après

    Chaque élément de la liste est un dict avec les clés suivantes:
        - start : la coroutine de démarrage
        - stop : la coroutine d'arrêt
        - info : un dict avec des infos
    """
    global _coroutines_to_startstop
    if type(_coroutines_to_startstop) is list:
        return _coroutines_to_startstop

    _coroutines_to_startstop = []
    smtr_collector = smtrCollector()

    # ---- Les coroutines "sink" pour "smartmeter_datacollector"
    if (smtr_config):
        sinks = smartmeter_datacollector.factory.build_sinks(smtr_config)
        for sink in sinks:
            coroutine = {}
            coroutine['start'] = sink.start()
            coroutine['stop'] = sink.stop()
            coroutine['info'] = {}
            coroutine['info']['type'] = 'smtr_sink'
            coroutine['info']['sink'] = sink
            coroutine['info']['desc'] = f'Task sink {type(sink)} pour smartmeter_datacollector'
            smtr_collector.register_sink(sink)
            _coroutines_to_startstop.append(coroutine)

    logging.debug("coroutine_to_startstop: %s", _coroutines_to_startstop)
    return _coroutines_to_startstop

def coroutinesToRun():
    """Retourne une liste de coroutines devant être lancés et awaited jusqu'à l'arrêt du deamon

    Chaque élément de la liste est un dict avec les clés suivantes:
        - run : la coroutine
        - info : un dict avec des infos
    """

    global _coroutines_to_run
    if type(_coroutines_to_run) is list:
        return _coroutine_to_run

    _coroutines_to_run = []
    smtr_collector = smtrCollector()

    # ---- Les tasks "reader" pour "smartmeter_datacollector"
    if (smtr_config):
        meters = smartmeter_datacollector.factory.build_meters(smtr_config)
        for meter in meters:
            meter.register(smtr_collector)
            coroutine = {}
            coroutine['run'] = meter.start()
            coroutine['info'] = {}
            coroutine['info']['type'] = 'smtr_meter'
            coroutine['info']['meter'] = meter
            coroutine['info']['desc'] = 'Task meter pour smartmeter_datacollector'
            _coroutines_to_run.append(coroutine)
        
        coroutine = {}
        coroutine['run'] = smtr_collector.process_queue()
        coroutine['info'] = {}
        coroutine['info']['type'] = 'smtr_collector'
        coroutine['info']['collector'] = smtr_collector
        coroutine['info']['desc'] = 'smartmeter_datacollector collector'
        _coroutines_to_run.append(coroutine)
    logging.debug("coroutines_to_run: %s",_coroutines_to_run)
    return _coroutines_to_run

def shutdown(tasks):
    logging.info("Signal SIGTERM reçu! Arrêt des coroutines...")
    for task in tasks:
        task.cancel()

async def run():
    # ---- lancement des tâches de fond
    for coroutine in coroutinesToStartAndStop():
        logging.info("Lancement de %s", coroutine['info']['desc'])
        await coroutine['start']
        logging.debug('OK')

    # ----interception du signal SIGTERM
    tasks = []
    loop = asyncio.get_running_loop()
    for coroutine in coroutinesToRun():
        tasks.append(loop.create_task(coroutine['run']))
    loop.add_signal_handler(signal.SIGTERM, lambda: shutdown(tasks))

    # ---- run de la boucle centrale
    try:
        await asyncio.gather(*tasks)
    except asyncio.CancelledError:
        pass

    # ---- Arrêt des tâches de fond
    for coroutine in coroutinesToStartAndStop():
        logging.info("Arret de %s", coroutine['info']['desc'])
        await coroutine['stop']
        logging.debug('OK')

options()
initLogging()

# ---- Lecture du fichier de configuration global
config = ConfigParser()
config.read(config_file)
if (config.has_section('datacollector')):
    if (not config.has_option('datacollector','ConfigFile')):
        logging.error("Le fichier de configuration pour 'smartmeter_datacollector' n'est pas défini!")
    else:
        smtr_config_file = config.get('datacollector','ConfigFile')
        smtr_config = smartmeter_datacollector.config.read_config_files(smtr_config_file)

#signal.signal(signal.SIGTERM, signal_handler)

pid = str(os.getpid())
f = open(pid_file, "w")
f.write(f"{pid}\n")
f.close()

asyncio.run(run())
