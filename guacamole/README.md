# Icinga Director ImportSource for Guacamole

## Installation

Clone this repository from GitHub: 
```
git clone https://github.com/redbridge/icinga-guacamole.git /tmp/icinga-guacamole
```

Move the guacamole module into place:
```
mv /tmp/icinga-guacamole/guacamole /usr/share/icingaweb2/modules/guacamole
```

Enable the module:
```
icingacli module enable guacamole
```

Delete the temporary git folder:
```
rm -rf /tmp/icinga-guacamole
```

The Import Source can then be selected in Icinga web under `Automation -> Import Source -> Add -> Source Type -> Guacamole REST API`
