# Icinga Director ImportSource for Apache Guacamole

## Why?

The built-in REST API ImportSource does not currently support POST requests without the body being automatically encoded into JSON. Furthermore, the Guacamole API requires an authentication token to be retrieved before other operations can be performed which is also unsupported.

## Tested with
- Icinga Web 2 2.11.1
- Director 1.8.1
- Guacamole 1.4.0

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
