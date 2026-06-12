# vim: tabstop=4 expandtab autoindent
import sys
import argparse
import logging
import struct
import math
import time
import random
import signal
import serial

logLevel = "info"
info = logging.info
debug = logging.debug
warning = logging.warning
error = logging.error

invoke_id = 0
port = None
baudrate = ''

# --- DICTIONNAIRE DE CONFIGURATION DU COMPTEUR ---
UNITS = {
    "W"   : bytes([27]),  # Watt
    "VA"  : bytes([28]),  # volt-ampère
    "var" : bytes([29]),  # puissance réactive 
    "Wh"  : bytes([30]),  # Watt heure
    "VAh" : bytes([31]),  # VA heure
    "varh": bytes([32]),  # Energie réactive
    "A"   : bytes([33]),  # Ampere
    "C"   : bytes([34]),  # Coulomb
    "V"   : bytes([35]),  # Volt
    "Hz"  : bytes([44]),  # Herz
    "%"   : bytes([56]),  # pourcent
    "sans": bytes([255]), # sans unité
}

OBIS_CONFIG = {
    "0.0.96.1.0":  {"type": "text"},   # Numéro de série du compteur
    "0.0.96.1.1":  {"type": "text"},   # Version du firmware / ID secondaire

    "1.0.1.7.0":   {"type": "int", "unite": "W"},    # Puissance active positive instantanée (A+) (W)
    "1.0.2.7.0":   {"type": "int", "unite": "W"},    # Puissance active négative instantanée (A-) (W)

    "1.0.1.8.0":   {"type": "int", "unite": "Wh"},    # Index Énergie active positive totale (A+) (Wh)
    "1.0.2.8.0":   {"type": "int", "unite": "Wh"},    # Index Énergie active négative totale (A-) (Wh)

    # Tensions de phases (Multiplié par 10 pour intégrer la décimale ex: 230.4 V -> 2304)
    "1.0.32.7.0":  {"type": "float", "scale": 10, "unite": "V"},   # Tension Phase 1
    "1.0.52.7.0":  {"type": "float", "scale": 10, "unite": "V"},   # Tension Phase 2
    "1.0.72.7.0":  {"type": "float", "scale": 10, "unite": "V"},   # Tension Phase 3

    # Courants de phases (Multiplié par 1000 pour les milliampères ex: 1.425 A -> 1425)
    "1.0.31.7.0":  {"type": "float", "scale": 1000, "unite": "A", "signed": True}, # Courant Phase 1
    "1.0.51.7.0":  {"type": "float", "scale": 1000, "unite": "A", "signed": True}, # Courant Phase 2
    "1.0.71.7.0":  {"type": "float", "scale": 1000, "unite": "A", "signed": True}, # Courant Phase 3

    # Puissance active par Phase
    "1.0.1.7.1":   {"type": "int", "unite": "W"},      # Puissance active positive instantanée Phase 1 (A+) (W)
    "1.0.1.7.2":   {"type": "int", "unite": "W"},      # Puissance active positive instantanée Phase 2 (A+) (W)
    "1.0.1.7.3":   {"type": "int", "unite": "W"},      # Puissance active positive instantanée Phase 3 (A+) (W)

    # Index d'énergie par tarifs (Wh)
    "1.0.1.8.1":   {"type": "int", "unite": "Wh"},    # Index Énergie active positive Tarif 1
    "1.0.1.8.2":   {"type": "int", "unite": "Wh"},    # Index Énergie active positive Tarif 2
    "1.0.2.8.1":   {"type": "int", "unite": "Wh"},    # Index Énergie active négative Tarif 1
    "1.0.2.8.2":   {"type": "int", "unite": "Wh"},    # Index Énergie active négative Tarif 2
}

# ---- Traitement de option de la ligne de commande

def options():
    global logLevel
    global port
    global baudrate

    parser = argparse.ArgumentParser()
    parser.add_argument('-p', '--port', help="Port pour emmision des trames", required=True)
    parser.add_argument('-b', '--baudrate', help="vitesse du port", default=115200)
    parser.add_argument('-l', '--level', help="niveau de log", default='info')
   
    args = parser.parse_args()

    if args.level:
        logLevel = args.level

    if args.port:
        port = args.port

    if args.baudrate:
        baudrate = args.baudrate

# ---- Initialisation du logging

def init_logging():
    levels = {
        'debug'   : logging.DEBUG,
        'info'    : logging.INFO,
        'warning' : logging.WARNING,
        'error'   : logging.ERROR,
        'critical': logging.CRITICAL,
    }
    _level = levels.get(logLevel, logging.ERROR)
    format = '%(asctime)s [%(levelname)s] %(message)s'
    dateformat = '%Y-%m-%d %H:%M:%S'
    logging.basicConfig(level=_level, format=format, datefmt = dateformat)

def signal_handler(sig, frame):
    debug ("Terminé")
    sys.exit(0)

# ---- Fourni la liste des info OBIS à transmettre

def obises():
    global invoke_id

    index_consomation = 0
    index_consomation_T1 = 0
    index_consomation_T2 = 0
    index_injection = 0
    index_injection_T1 = 0
    index_injection_T2 = 0

    while True:
        invoke_id += 1
        if invoke_id > 255:
            invoke_id = 0

        if (invoke_id < 128):
            tarif = 1
        else:
            tarif = 2

        debug (f"invoke_id: {invoke_id}")
        radian = invoke_id * 6.28 / 255
        debug (f"radian: {radian}")

        courant_1 = math.sin(radian) * 20
        courant_2 = math.cos(radian) * 20
        courant_3 = math.cos(radian + 1) * 20

        tension_1 = 230 + (random.random()*4 -2)
        tension_2 = 230 + (random.random()*4 -2)
        tension_3 = 230 + (random.random()*4 -2)

        puissance_1 = tension_1 * courant_1
        puissance_2 = tension_2 * courant_2
        puissance_3 = tension_3 * courant_3
        puissance = puissance_1 + puissance_2 + puissance_3

        energie = puissance *  5 / 3600
        if (energie > 0):
            index_consomation += energie
            if (tarif == 1):
                index_consomation_T1 += energie
            else:
                index_consomation_T2 += energie
        else:
            energie = - energie
            index_injection += energie
            if (tarif == 1):
                index_injection_T1 += energie
            else:
                index_injection_T2 += energie
        if (puissance > 0):
            consommation = puissance
            injection = 0
        else:
            consommation = 0
            injection = - puissance
        obises = [
            ["0.0.96.1.0",  "59815402"],                  # Numéro de série (Texte)
        ]
        # obises = [
        #     ["0.0.96.1.0",  "59815402"],                  # Numéro de série (Texte)
        #     ["1.0.1.8.0",   round(index_consomation)],    # Index Energie consommée (Wh)
        #     ["1.0.1.8.1",   round(index_consomation_T1)], # Index T1 consommation (Wh)
        #     ["1.0.1.8.2",   round(index_consomation_T2)], # Index T2 consommation (Wh)
        #     ["1.0.2.8.0",   round(index_injection)],      # Index Energie injectée (Wh)
        #     ["1.0.2.8.1",   round(index_injection_T1)],   # Index T1 injection (Wh)
        #     ["1.0.2.8.2",   round(index_injection_T2)],   # Index T2 injection (Wh)
        #     ["1.0.1.7.0",   consommation],                # Puissance consommée (W)
        #     ["1.0.2.7.0",   injection],                   # Puissance injectée (W)
        #     ["1.0.31.7.0",  courant_1],                   # Courant Phase 1
        #     ["1.0.51.7.0",  courant_2],                   # Courant Phase 2
        #     ["1.0.71.7.0",  courant_3],                   # Courant Phase 3
        #     ["1.0.32.7.0",  tension_1],                   # Tension Phase 1
        #     ["1.0.52.7.0",  tension_2],                   # Tension Phase 2
        #     ["1.0.72.7.0",  tension_3],                   # Tension Phase 3
        # ]
        yield obises


def calculate_fcs(data: bytes) -> bytes:
    """
    Calcule le FCS d'une trame HDLC (sans les 0x7E de début et fin).
    data : tous les octets entre les deux flags (adresse + commande + contrôle + information)
    retour : 2 octets en little‑endian (FCS prêt à être inséré dans la trame)
    """
    crc = 0xFFFF
    for b in data:
        crc ^= (b << 8)
        for _ in range(8):
            if crc & 0x8000:
                crc = (crc << 1) ^ 0x1021
            else:
                crc <<= 1
            crc &= 0xFFFF
    # Complément à 1 (XOR final)
    crc ^= 0xFFFF
    # Retourner en little‑endian (octet faible en premier)
    return bytes([crc & 0xFF, (crc >> 8) & 0xFF])

# ---- Retourne une longeur au format BER

def ber_length_encode(length: int) -> bytes:
    """
    Encode une longueur entière selon les règles BER (Basic Encoding Rules).

    Args:
        length: La longueur à encoder (entier >= 0).

    Returns:
        bytes: L'encodage BER de la longueur.

    Raises:
        ValueError: Si length < 0, ou si le nombre d'octets nécessaire pour
                    encoder la longueur dépasse 127 (cas extrême non supporté
                    par la plupart des implémentations BER).
    """
    if length < 0:
        raise ValueError("La longueur ne peut pas être négative")

    # Forme courte : longueur < 128
    if length < 128:
        return bytes([length])

    # Forme longue : calcul du nombre d'octets nécessaires
    # (longueur >= 128)
    len_bytes = (length.bit_length() + 7) // 8  # octets minimaux pour représenter length

    # La norme BER limite le nombre d'octets de longueur à 127 (car bits 7)
    if len_bytes > 0x7F:   # 127
        raise ValueError(
            f"Longueur trop grande : nécessite {len_bytes} octets, "
            f"mais le format BER n'autorise que 127 octets maximum"
        )

    # Premier octet : bit7=1, bits0-6 = nombre d'octets suivants
    first_byte = 0x80 | len_bytes

    # Encoder la longueur en big-endian sur exactement len_bytes octets
    return bytes([first_byte]) + length.to_bytes(len_bytes, 'big')

# ---- Transforme les OBIS en trames OBIS binaires

def obises_to_trames(obises):
    trames = []
    for obis in obises:
        code, value = obis

        # ---- CODE OBIS
        debug (f"  code OBIS: {code}")
        parts = [int(x) for x in code.split('.')]
        if (len(parts) != 5):
            raise Exception(f'Le code OBIS {code} est incorrect')
        parts.append(255)
        OBIS_bytes = bytes(parts)

        # ---- VALEUR (encodage BER)
        debug (f"  valeur   : {value}")
        config = OBIS_CONFIG.get(code)
        if (not config):
            raise Exception(f'Config pour le code OBIS {code} introuvable')

        if (config['type'] == 'text'):
            val_bytes = value.encode('ascii')
            ber  = b'\x02' # attribut "structure"
            ber += b'\x09' # type "octet-string"
            ber += bytes([len(val_bytes)]) + val_bytes

        elif (config['type'] == 'int'):
            value = int(value)
            if (value < 256):
                format = ">B"   # 1 byte
            elif (value < 65536):
                format = ">H"   # 2 bytes
            elif (value < 4294967296):
                format = ">I"   # 4 bytes
            else:
                format = ">Q"   # 8 bytes
            val_bytes = struct.pack(format, int(value))

            ber  = b'\x02' # attribut "structure"
            ber += b'\x06' # type "long-unsigned"
            ber += bytes([len(val_bytes)]) # + 2 pour le facteur et l'unité
            ber += val_bytes
            ber += b'\x00'   # facteur = 1 (10**0)
            ber += UNITS[config['unite']]

        elif (config['type'] == 'float'):
            value = int(round(float(value) * int(config['scale'])))
            factor = int(math.log10(config['scale'])) * -1
            factor_byte = struct.pack(">b",factor)
            
            value = int(value)
            if (config.get('signed', False)):
                type_ber = bytes([0x05])
                if (-128 <= value <= 127):
                    format = ">b"   # 1 byte
                if (-32768 <= value <= 32767):
                    format = ">h"   # 2 bytes
                if (-2147483648 <= value <= 2147483647):
                    format = ">l"   # 4 bytes
                else:
                    format = ">q"   # 8 bytes
            else:
                type_ber = bytes([0x06])
                if (value < 256):
                    format = ">B"   # 1 byte
                elif (value < 65536):
                    format = ">H"   # 2 bytes
                elif (value < 4294967296):
                    format = ">I"   # 4 bytes
                else:
                    format = ">Q"   # 8 bytes
            val_bytes = struct.pack(format, value)

            ber  = b'\x02' # attribut "structure"
            ber += type_ber # type "long-unsigned"
            ber += bytes([len(val_bytes)]) # + 2 pour le facteur et l'unité
            ber += val_bytes
            ber += factor_byte   # facteur
            ber += UNITS[config['unite']]

        else:
            ber = b''

        trame = OBIS_bytes + ber
        trames.append(trame)
    return trames

def get_apdu(obis_trames):

    longueur = 0
    for obis_trame in obis_trames:
        longueur += len(obis_trame)
    longueur += 3 # longueur de (Invoke Id + tagList + nbElems)
    longueur_ber = ber_length_encode(longueur)

    apdu = bytes([0xc8]) # data-notofication (mode push)
    apdu += longueur_ber
    apdu += bytes([invoke_id])   # Invoke ID
    apdu += bytes([1])   # tag "List"
    apdu += bytes([len(obis_trames)]) # nbElem
    for obis_trame in obis_trames:
        apdu += bytes([0x02]) + bytes([len(obis_trame)]) + obis_trame
    return apdu

def get_hdlc(apdu):
    len_apdu = len(apdu) + 8
    high_bits = (len_apdu >> 8) & 0x07
    addr_first = 0xA0 | high_bits
    length_byte = len_apdu & 0xFF

    hdlc  = bytes([addr_first, length_byte]) # adresses
    hdlc += bytes([16 << 1 | 1])      # Adresse Client (16 << 1) + 1
    hdlc += bytes([1 << 1 | 1])       # Adresse Server (0 >> 1) + 1
    hdlc += bytes([0x03])    # trame UI - Unnumbered
    hdlc += bytes([0x03])    # Controle (pas de séquence particulier)
    hdlc += apdu

    hdlc += calculate_fcs(hdlc)
    hdlc = bytes([0x7e]) + hdlc + bytes([0x7e])
    return hdlc

# ---- Affiche le trames OBIS binaires

def debug_trames(trames):
    if ( not type(trames) == list):
        debug(trames.hex(' '))
    else:
        for trame in trames:
            debug(trame.hex(' '))

# --------------
# ---- MAIN ----
# --------------
options()
init_logging()
signal.signal(signal.SIGTERM, signal_handler)
try :
    ser = serial.Serial(port, int(baudrate), timeout=1)
except Exception as e:
    error(f"Impossible d'ouvrir {port} ({baudrate}): {e}")
    sys.exit(1)
try:
    info(f"Connection série établie sur {port} à {baudrate} bauds")
    obises_set = obises()
    for obises in obises_set:
        info ("run")
        obisTrames = obises_to_trames(obises)
        debug_trames(obisTrames)
        apdu = get_apdu(obisTrames)
        debug_trames (apdu)
        hdlc = get_hdlc(apdu)
        debug_trames (hdlc)
        ser.write(hdlc)
        ser.flush()
        time.sleep(5)
except KeyboardInterrupt:
    pass

