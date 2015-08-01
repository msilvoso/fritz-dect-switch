#!/usr/bin/python

import sys
from Adafruit_CharLCDPlate import Adafruit_CharLCDPlate

# Initialize the LCD plate.  Should auto-detect correct I2C bus.  If not,
# pass '0' for early 256 MB Model B boards or '1' for all later versions
lcd = Adafruit_CharLCDPlate(1)

constants = { 'SELECT':lcd.SELECT, 'RIGHT':lcd.RIGHT, 'DOWN':lcd.DOWN, 'UP':lcd.UP, 'LEFT':lcd.LEFT, 'OFF':lcd.OFF, 'RED':lcd.RED, 'GREEN':lcd.GREEN, 'BLUE':lcd.BLUE, 'YELLOW':lcd.YELLOW, 'TEAL':lcd.TEAL, 'VIOLET':lcd.VIOLET, 'WHITE':lcd.WHITE, 'ON':lcd.ON }

#btn = ((lcd.LEFT  , 'LEFT'),
#       (lcd.UP    , 'UP'),
#       (lcd.DOWN  , 'DOWN'),
#       (lcd.RIGHT , 'RIGHT'),
#       (lcd.SELECT, 'SELECT'))

btn = ((16, 'LEFT'),
       (8, 'UP'),
       (4, 'DOWN'),
       (2, 'RIGHT'),
       (1, 'SELECT'))

# command line arguments 
if len(sys.argv) > 1:
    if sys.argv[1] == 'CHECK':
        pressed = lcd.buttons();
        if not pressed:
            sys.stdout.write("NONE\n")
            sys.stdout.flush()
            sys.exit(7)
        else:
            for b in btn:
                if b[0] & pressed:
                    sys.stdout.write(b[1]+"\n")
                    sys.stdout.flush()
                    sys.exit(b[0] + 10)
                    break
    elif sys.argv[1] == 'CLEAR':
        lcd.clear()
        lcd.backlight(constants[sys.argv[2]])
        sys.exit(0)
    elif sys.argv[1] == 'OFF':
        lcd.clear()
        lcd.backlight(lcd.OFF)
        sys.exit(0)
    elif sys.argv[1] == 'BUTTONS':
        sys.stdout.write(str(lcd.buttons()))
        sys.exit(0)
    else:
        lcd.clear()
        lcd.message(sys.argv[1])
        lcd.backlight(constants[sys.argv[2]])
        sys.exit(0)

# if no arguments read from stdin and write to stdout
while 1:
    line = sys.stdin.readline()
    if not line:
        break
    sline = line.strip().split(" ")
    #sys.stderr.write(sline[0])
    #sys.stderr.flush()
    if len(sline) > 0:
        if sline[0] == 'CHECK':
            pressed = lcd.buttons();
            if not pressed:
                sys.stdout.write("NONE\n")
                sys.stdout.flush()
            else:
                for b in btn:
                    if b[0] & pressed:
                        sys.stdout.write(b[1]+"\n")
                        sys.stdout.flush()
                        break
        elif sline[0] == 'CLEAR':
            lcd.clear()
            lcd.backlight(constants[sline[1]])
        elif sline[0] == 'OFF':
            lcd.clear()
            lcd.backlight(lcd.OFF)
        elif len(sline) == 2:
            lcd.clear()
            lcd.message(sline[0].replace("_"," ").replace("@","\n"))
            lcd.backlight(constants[sline[1]])
