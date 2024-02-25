#!/usr/bin/python
import RPi.GPIO as GPIO
import time
import sys
import json

GPIO.setwarnings(False)
GPIO.setmode(GPIO.BCM)

# init list with pin numbers
# this will change based in how gp io cables are connected
#pinList = [  22 , 23, 24, 25   ,  9   , 8 , 11, 7  ]
pinList = [4, 27, 2, 3, 17, 22, 10, 9]
#pinList = [  7  , 11,  8,  9   , 25   , 24 , 23, 22  ]

inputArgs = sys.argv  

for i in pinList: 
  GPIO.setup(i, GPIO.OUT) 
  if '1\n' == open('/var/www/html/firstRun').read():
    GPIO.output(i, GPIO.HIGH)

for i in range(1, len(inputArgs)):
  if int(inputArgs[i]) > 0 :
    for hi in range(0, len(pinList)+1): 
      if int(hi) == int(inputArgs[i]):
        GPIO.output(pinList[int(hi)-1], GPIO.LOW) 
        #print pinList[int(hi)-1]
  
  if int(inputArgs[i]) < 0 :
    for hi in range(0, len(pinList)+1): 
      if int(hi)*-1 == int(inputArgs[i]):
        GPIO.output(pinList[int(hi)-1], GPIO.HIGH) 
        #print pinList[int(hi)-1] * -1


with open('/var/www/html/firstRun', 'w') as the_file:
    the_file.write(json.dumps(inputArgs))
