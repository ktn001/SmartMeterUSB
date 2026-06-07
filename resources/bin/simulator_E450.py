# vim: tabstop=4 autoindent expandtab
import argparse
import signal
import time
import serial

port = ''
baudrate = 115200

def options():
    global port
    global baudrate

    parser = argparse.ArgumentParser()
    parser.add_argument("-p", "--port", help="port tty", required=True)
    parser.add_argument("-b", "--baudrate", help="vitesse du port", type = int)

    args = parser.parse_args()

    if args.port:
        port = args.port

    if args.baudrate:
        baudrate = int(args.baudrate)

def run():
    # Configuration du port virtuel simulant le compteur
    # 2400 bauds, 8 bits, parité impaire, 1 bit d'arrêt
    
    try:
        ser = serial.Serial(
            port = port,
            baudrate = 115200,
            bytesize = serial.EIGHTBITS,
            parity = serial.PARITY_ODD,
            stopbits = serial.STOPBITS_ONE,
            timeout = 1
        )
        print(f"[-] Simulateur Landis+Gyr E450 démarré sur {port}")
    except Exception as e:
        print(f"[!] Erreur de port série : {e}")
        exit(1)
    
    # Trame hexadécimale exemple (HDLC encapsulant du DLMS/COSEM avec codes OBIS)
    # Contient les structures de données standards (1.8.1, 1.8.2, etc.)
    # TR_E450_MOCK = bytes.fromhex(
    #     "7EA0B50321101DE6E600E604001C012000001C011000000107020412000809060"
    #     "000010801FF0600014C50020412000809060000010802FF060000AF2102041200"
    #     "0809060000020801FF0600000000020412000809060000020802FF06000000000"
    #     "20412000809060000010700FF06000001F41A5C7E"
    # )
    
    TR_E450_MOCK = bytes.fromhex(
        "7EA0C30321101DE6E600E604001C012000001C011000000107020412000809060"
        "000600100FF09083539383135343032020412000809060000010801FF0600014C"
        "50020412000809060000010802FF060000AF21020412000809060000020801FF0"
        "600000000020412000809060000020802FF060000000002041200080906000001"
        "0700FF06000001F41A5C7E"
    )
    
    try:
        while True:
            print("[+] Envoi d'une trame de données d'énergie (Intervalle: 5s)...")
            ser.write(TR_E450_MOCK)
            ser.flush()
            time.sleep(5)  # Mode push réglementaire toutes les 5 secondes
    except KeyboardInterrupt:
        print("\n[-] Arrêt du simulateur.")
    finally:
        ser.close()

options()
run()
