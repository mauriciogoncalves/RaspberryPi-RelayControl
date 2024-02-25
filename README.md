
# Raspberry Pi - Relay Control

A PHP web page that interacts with a Python script  to control lamps
from a raspberry pi   

![enter image description here](https://assets.raspberrypi.com/static/532b4c25752c4235d76cc41051baf9ab/3f4ea/877fb653-7b43-4931-9cee-977a22571f65_3b+Angle+2+refresh.jpg)

Plugged to an eight-channel relay connected to certain lamps in my house.
![enter image description here](https://fixmasterelectronics.com.ph/wp-content/uploads/2016/07/IMG_0554.jpg)


## There is 4 files:

- A **PHP** page that call a bash script. A special **sudo** permission was granted to apache user to allow php to call a specific script as root as required on Raspbian to access Pi GPio.
- A **Bash** script to be called by PHP and call the Python to run locally.
- A **Python** script with code that use a GPio library that can control the relay board. 
- A **text** file to save the last state of the switches.
```mermaid
graph LR
A[PHP relay.php] -- 2 shell_exec --> B([Bash script /bin/relay_python])
C(Text file with  last state saved) 
A -- 1 - Read last state saved --> C 
B --> D{Python lightControl.py}
D --> C
```
## Connection and setup

This eight-channel relay is connected to some lights at my home, some of then direcly connected, some of them integrated with 3 way switches on the wall, making possible control these light from the web page, but also manually from the regular switches on the walls.
![enter image description here](https://upload.wikimedia.org/wikipedia/commons/7/75/3-way_switch_animated.gif) 


## Control the lights from web page 
We can control the lights from these button in relay.php web page. 
We can use this page just clicking in these buttons or we can make a request defining the state of every relay.



#### Example link

http://myDomain.com/relay.php?r1=2&r2=3&r3=3&r4=3&r5=3&r6=3&r7=3&r8=3 
Each parameter is a relay, like r1 means relay 1
Each parameter value is the desired state
- Value **0** make the relay closed (**close**) 
- Value **1** make the relay open (**open**) 
- Value **2** change the state of the relay (**switch**) 
- Value **3** keep the state of the relay (**keep**) 

## Control the lights from Android app
I already use an Android App called **Yatse** to control tv box software called **Kodi** intalled in this raspberry Pi. 
In the Yatse i could add custom bottons that make an HTTP request 
The result is like this:
 [![Yatse](https://i.stack.imgur.com/Vp2cE.png)](https://www.youtube.com/shorts/agTWJfPldqQ)
