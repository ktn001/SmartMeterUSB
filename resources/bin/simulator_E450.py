# vim: tabstop=4 expandtab autoindent
import sys
import argparse
import logging
import datetime
import struct
import math
import time
import random
import signal
import serial

logLevel = "info"

step = 0
port = None
baudrate = ''

index_consommation = 0
index_consommation_T1 = 0
index_consommation_T2 = 0
index_injection = 0
index_injection_T1 = 0
index_injection_T2 = 0

# [OBIScode, value, valueType, objectType, attr]
# valueType:
#    byteArray : 9
#
# ObjectType:
#    setup : 40
cosems = [
    ["0.8.25.9.0","0.8.25.9.0.255",     9, 40, 2],
    ["0.8.25.9.0","",                  -1, 40, 1],
    ["0.0.42.0.0","CKY1030655933512",   9,  1, 2],
    ["0.0.96.1.1","1935912",            9,  1, 2],
    ["0.0.1.0.0", "2021-07-06 14:58:18",9,  8, 2],

    ["1.0.1.8.0", "0",                  6,  3, 2],
    ["1.0.1.8.1", "0",                  6,  3, 2],
    ["1.0.1.8.2", "0",                  6,  3, 2],
    ["1.0.2.8.0", "0",                  6,  3, 2],
    ["1.0.2.8.1", "0",                  6,  3, 2],
    ["1.0.2.8.2", "0",                  6,  3, 2],
    ["1.0.1.7.0", "0",                  6,  3, 2],
    ["1.0.2.7.0", "0",                  6,  3, 2],
    ["1.0.31.7.0","0",                  5,  3, 2],
    ["1.0.51.7.0","0",                  5,  3, 2],
    ["1.0.71.7.0","0",                  5,  3, 2],
    ["1.0.32.7.0","0",                  6,  3, 2],
    ["1.0.52.7.0","0",                  6,  3, 2],
    ["1.0.72.7.0","0",                  6,  3, 2],
]

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
    logging.debug ("Terminé")
    sys.exit(0)

# ---- Fourni la liste des info OBIS à transmettre

def getCosems():
    global step
    global cosems
    global index_consommation
    global index_consommation_T1
    global index_consommation_T2
    global index_injection
    global index_injection_T1
    global index_injection_T2

    step += 1
    if step > 255:
        step = 0

    if step > 128:
        tarif = 1
    else:
        tarif = 2

    logging.info("Step: %s", step)
    radian = step * 6.28 / 255

    courant_1 = math.sin(radian) * 500
    courant_2 = math.cos(radian) * 500
    courant_3 = math.cos(radian + 1) * 500

    tension_1 = 230 + (random.random()*4 -2)
    tension_2 = 230 + (random.random()*4 -2)
    tension_3 = 230 + (random.random()*4 -2)

    puissance_1 = tension_1 * courant_1/100
    puissance_2 = tension_2 * courant_2/100
    puissance_3 = tension_3 * courant_3/100
    puissance = puissance_1 + puissance_2 + puissance_3

    energie = puissance *  5 / 3600
    if (energie > 0):
        index_consommation += energie
        if (tarif == 1):
            index_consommation_T1 += energie
        else:
            index_consommation_T2 += energie
    else:
        energie = - energie
        index_injection += energie
        if (tarif == 1):
            index_injection_T1 += energie
        else:
            index_injection_T2 += energie
    index_consommation = index_consommation_T1 + index_consommation_T2
    index_injection = index_injection_T1 + index_injection_T2
    if (puissance > 0):
        consommation = puissance
        injection = 0
    else:
        consommation = 0
        injection = - puissance

    for cosem in cosems:
        if   cosem[0] == '1.0.1.8.0':
            cosem[1] = str(round(index_consommation))
        elif cosem[0] == '1.0.1.8.1':
            cosem[1] = str(round(index_consommation_T1))
        elif cosem[0] == '1.0.1.8.2':
            cosem[1] = str(round(index_consommation_T2))
        elif cosem[0] == '1.0.2.8.0':
            cosem[1] = str(round(index_injection))
        elif cosem[0] == '1.0.2.8.1':
            cosem[1] = str(round(index_injection_T1))
        elif cosem[0] == '1.0.2.8.2':
            cosem[1] = str(round(index_injection_T2))
        elif cosem[0] == '1.0.1.7.0':
            cosem[1] = str(round(consommation))
        elif cosem[0] == '1.0.2.7.0':
            cosem[1] = str(round(injection))
        elif cosem[0] == '1.0.31.7.0':
            cosem[1] = str(round(courant_1))
        elif cosem[0] == '1.0.51.7.0':
            cosem[1] = str(round(courant_2))
        elif cosem[0] == '1.0.71.7.0':
            cosem[1] = str(round(courant_3))
        elif cosem[0] == '1.0.32.7.0':
            cosem[1] = str(round(tension_1))
        elif cosem[0] == '1.0.52.7.0':
            cosem[1] = str(round(tension_2))
        elif cosem[0] == '1.0.72.7.0':
            cosem[1] = str(round(tension_3))
        logging.debug(cosem)
    return cosems

def getCrc(data):
    crc = 0xFFFF
    poly = 0x8408
    for byte in data:
        crc ^= byte
        for _ in range(8):
            if (crc & 0x0001) != 0:
                crc = (crc >> 1) ^ poly
            else:
                crc >>= 1
    crc ^= 0xffff
    return crc.to_bytes(2, byteorder='little')

# ---- Retourne une longeur au format BER

def berLengthEncode(length: int) -> bytes:
    if length < 0:
        raise ValueError("La longueur ne peut pas être négative")
    if length <= 0x80:
        return bytes([length])
    if length <= 0xff:
        return bytes([0x81]) + length.to_bytes(1)
    if length <= 0xffff:
        return bytes([0x82]) + length.to_bytes(2)
    if length <= 0xffffffff:
        return bytes([0x84]) + length.to_bytes(4)
    raise valueError("La longueur ne peux pas être supérieure à 0xFFFFFFFF")

# ---- Retourne la liste de blocks qui constituent la trame DLMS
def getBlocks():
    blocks = []

    # ---- Entête DLMS
    cmd = bytes([0x0F])    # Data_notification
    invokeID = bytes([0x00, 0x00, 0xCB, 0xC6])
    block = cmd + invokeID

    # ---- horodatage
    len_ = bytes([0x0C]) # 12 bytes pour l'horodatage
    date_ = datetime.datetime(2021,7,6,14,58,16)
    year = date_.year.to_bytes(2)
    month = date_.month.to_bytes(1)
    day = date_.day.to_bytes(1)
    weekday = date_.weekday().to_bytes(1)
    hour = date_.hour.to_bytes(1)
    minute = date_.minute.to_bytes(1)
    second = date_.second.to_bytes(1)
    ms = bytes([0xFF]) # pas de milisecondes
    tz = bytes([0x80, 0x00]) # pas de timezone
    status = bytes([0x00]) # ????
    block += len_ + year + month + day + weekday + hour + minute + second + ms + tz + status

    # ---- La structure qui contiendra les COSEMs
    cosems = getCosems()
    type_ = bytes([0x02])   # type STRUCTURE
    len_ = len(cosems)      # le nombre d'éléments dans la structure
    block += type_ + berLengthEncode(len_)

    # ---- La liste des objets COSEMs

    type_ = bytes([0x01]) # type ARRAY
    len_ = len(cosems)      # le nombre d'éléments dans la structure
    block += type_ + berLengthEncode(len_)
    for cosem in cosems:
        if len(cosem) == 0:
            continue
        entry = bytes([0x02, 0x04]) # STRUCTURE de 4 éléments

        # Element 0: Le type d'objet
        entry += bytes([0x12])  # un entier 16 bits
        entry += cosem[3].to_bytes(2)

        # Element 1: Le code OBIS
        entry += bytes([0x09, 0x06]) # ARRAY de 6 éléments
        OBIS = cosem[0].split('.')
        if len(OBIS) < 6:
            OBIS.append('255')
        for n in OBIS:
            entry += bytes([int(n)])

        # Element 2: L'attr
        entry += bytes([0x0f]) # un entier dur 8 bits
        entry += cosem[4].to_bytes(1)

        # Element 4: 0
        entry += bytes([0x12])  # un entier 16 bits
        entry += int(0).to_bytes(2)

        if (len(block) + len(entry)) < 110:
            block += entry
        else:
            blockNr = len(blocks) + 1
            if blockNr == 1:
                blockEntete = bytes([0xE6, 0xE7, 0x00, 0xE0, 0x40])
            else:
                blockEntete = bytes([0xE0, 0x40])
            blockEntete += blockNr.to_bytes(2)
            blockEntete += bytes([0x00, 0x00])
            blockEntete += berLengthEncode(len(block))
            block = blockEntete + block

            blocks.append(block)
            block = entry
        entry = bytes([])


    # ---- les valeurs COSEM

    for cosem in cosems:
        value = cosem[1]
        datatype = cosem[2]
        objectType = cosem[3]

        if datatype == 5:
            entry += bytes([0x05])
            entry += int(value).to_bytes(4, signed=True)
        elif datatype == 6:
            entry += bytes([0x06])
            entry += int(value).to_bytes(4)
        elif datatype == 9:
            if objectType == 1:
                value = value.encode()
                entry += bytes([0x09, len(value)]) 
                entry += value
            elif objectType == 8:
                if value:
                    dt = datetime.datetime.fromisoformat(value)
                    status = bytes([0x81])
                else:
                    dt = datetime.now()
                    status = bytes([0x00])
                entry += bytes([0x09, 0x0c]) # ARRAY de 12 bytes
                year = dt.year.to_bytes(2)
                month = dt.month.to_bytes(1)
                day = dt.day.to_bytes(1)
                weekday = dt.weekday().to_bytes(1)
                hour = dt.hour.to_bytes(1)
                minute = dt.minute.to_bytes(1)
                second = dt.second.to_bytes(1)
                ms = bytes([0xFF]) # pas de milisecondes
                tz = bytes([0x80, 0x00]) # pas de timezone
                entry += year+month+day+weekday+hour+minute+second+ms+tz+status
            elif objectType == 40:
                entry += bytes([0x09, 0x06]) # ARRAY de 6 bytes
                value = value.split('.')
                if len(value) < 6:
                    value.append('255')
                for n in value:
                    entry += bytes([int(n)])
        elif datatype == 18:
            entry += bytes([0x12])
            entry += int(value).to_bytes(2)

        if (len(block) + len(entry)) < 110:
            block += entry
        else:
            blockEntete = bytes([0xE0, 0x40])
            blockNr = len(blocks) + 1
            blockEntete += blockNr.to_bytes(2)
            blockEntete += bytes([0x00, 0x00])
            blockEntete += berLengthEncode(len(block))
            block = blockEntete + block
            blocks.append(block)
            block = entry
        entry = bytes([])

    blockEntete = bytes([0xE0, 0xC0])
    blockNr = len(blocks) + 1
    blockEntete += blockNr.to_bytes(2)
    blockEntete += bytes([0x00, 0x00])
    blockEntete += berLengthEncode(len(block))
    block = blockEntete + block
    blocks.append(block)
    return blocks

    
# ---- Retourne le liste des trames HDLC à transmettre

def getHDLCTrames():
    blocks = getBlocks()
    HDLCTrames = []
    for block in blocks:
        HDLCTrame = bytes([0x7e, 0xa0])
        length = 1 + 1 + 2 + 1 + 1 + 2 + len(block) + 2
        HDLCTrame += length.to_bytes(1)
        HDLCTrame += bytes([0xce, 0xFF])
        HDLCTrame += bytes([0x03])
        HDLCTrame += bytes([0x13])
        HDLCTrame += getCrc(HDLCTrame[1:])
        HDLCTrame += block
        HDLCTrame += getCrc(HDLCTrame[1:])
        HDLCTrame += bytes([0x7e])
        HDLCTrames.append(HDLCTrame)

    return HDLCTrames

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
    logging.info(f"Connection série établie sur {port} à {baudrate} bauds")
    time.sleep(3)
    while True:
        HDLCTrames = getHDLCTrames()
        for HDLCTrame in HDLCTrames:
            logging.debug(HDLCTrame.hex(" ").upper())
            ser.write(HDLCTrame)
            ser.flush()
        time.sleep(5)

except KeyboardInterrupt:
    pass

