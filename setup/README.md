# Use the Command Line to Manage ILIAS

The ILIAS command line app can be called via `php setup\setup.php`. It contains four
main commands to manage ILIAS installations:

* `install` will [set an installation up](#install-ilias)
* `update` will [update an installation](#update-ilias)
* `status` will [report status of an installation](#report-status-of-ilias)
* `build-artifacts` [recreates static assets](#build-ilias-artifacts) of an installation
* `achieve` [a named objective](#achieve-a-named-objective) of an agent 
* `migrate` will run [needed migrations](#migrations)

`install` and `update` also supply switches and options for a granular control of the inclusion of plugins:

* `--skip <plugin name>` will exclude the named plugin from the command
* `--no-plugins` will exclude all plugins from the command
* `install <plugin name>` (or `update <plugin name>` respectively) will update or install the specified plugin

`install` requires a [configuration file](#about-the-config-file) to do the job.
`update` can be used without this file for updating the installation only, but is
required to transfer any modified setting from this file to the installation.
The app also supports a `help` command that lists arguments and
options of the available commands.


## Install ILIAS

To install ILIAS with all plugins from the command line, call `php setup/setup.php install config.json`
from within the ILIAS folder you checked out from GitHub (or downloaded from elsewhere).
`config.json` can be the path to some [configuration file](#about-the-config-file)
which does not need to reside in the ILIAS folder. Also, `setup/setup.php` could be
the path to the `setup.php` when the command is called from somewhere else.

You most probably want to execute the setup with the user that also executes your
webserver to avoid problems with filesystem permissions. The installation creates
directories and files that the webserver will need to read and sometimes even modify.
If you need to run setup as another user, please make sure that the user that executes
the webserver has the necessary filesystem permissions (e.g. by using chown), to
avoid some errors which may be difficult to troubleshoot.

The setup will ask you to confirm some assumptions during the setup process, where
you will have to type `yes` (or `no`, of course). These checks can be overwritten
with the `--yes` option, which confirm any assumption for you automatically.

There might be cases where the setup aborts for some reasons. These reasons might
require further actions on your side which the setup cannot perform. Make sure you
read messages from the setup carefully and act accordingly. If you do not change the
config file, it is safe to execute the installation process a second time for the
same installation a during the initial setup process.

Do not discard the `config.json` you use for the installation, you will need it later
on to update that installation. If you want to overwrite specific fields in the
configuration file you can use the `--config="<path>=<value>"` option, even several
times. If you e.g. use `--config="database.password=XYZ"` the field `database.password`
from the original config will be overwritten with `XYZ`. This allows to use one
configuration for multiple setups and overwrite it from the CLI or even share
configs without secrets.

The setup will also install plugins of the installation, unless the plugin explicitely
defines that it cannot be installed via CLI setup. If you still want to skip a plugin
for installation, use the skip-option: `php setup/setup.php install --skip <plugin name> config.json`.
The option can be repeated to cover multiple plugins. If you want to skip plugins
alltogether, use the `--no-plugins` option. If you only want to install a specific
plugin, use `php setup/setup.php install config.json <plugin name>`.


## Update ILIAS

To update ILIAS from the command line, call `php setup/setup.php update`
from within your ILIAS folder. This will update ILIAS as well as update the
database of the installation or do other necessary task for the update.
This does not update the source code.
If there are changes in your config.json file call `php setup/setup.php update config.json`
from within your ILIAS folder.  This will also update the configuration of ILIAS according
to the provided configuration.

Plugins are updated just as the core of ILIAS (if the plugin does not exclude itself),
where the plugins can be controlled with the same options as for `install`.

Sometimes it might happen that the database update steps detect some edge case
or warn about a possible loss of data. In this case the update is aborted with
a message and can be resumed after the messages were read carefully and acted
upon. 
You may use the `--ignore-db-update-messages` at your own risk if you want
to silence the messages.

When an update step failed, you might get a message about inconsistent order 
of already performed steps when resuming the setup:
> step 2 was started last, but step 1 was finished last. 
> Aborting because of that mismatch.

You may reset the records for those steps by running:
```
php setup/setup.php achieve database.resetFailedSteps
```
However, be sure to understand the cause for the failing steps and tend to it before 
resetting and re-running the update.

## Report Status of ILIAS

Via `php setup/setup.php status` you can get a status of your ILIAS installation.
The command uses a best effort approach, so according to the status of your
system the output might contain more or less fields. When calling this for a
system where ILIAS was not installed, for example, the output only contains the
information that ilias is not installed. The command also reports on the configuration
of the installation.

The output of the command is formatted as YAML to be easily readable by people and
machines. So we encourage you to use this command for monitoring your system and
also request status information via our feature process that you are interested in.

Like for `install` and `update`, plugins are included here, but can be controlled
via options.


## Build ILIAS Artifacts

Artifacts are source code files that are created based on the ILIAS source tree.
You can refresh them by calling `php setup/setup.php build-artifacts` from your
installation. Make sure you run the command with the webserver user or adjust
filesystem permissions later on, because the webserver will need to access the
generated files. Please do not invoke this function unless it is explicitly stated
in update or patch instructions or you know what you are doing.

Like for `install` and `update`, plugins are included here, but can be controlled
via options.


## Achieve a Named Objective

Some components of ILIAS will publish named objectives to the setup via their
agent. The most notorious example for this is the component `UICore` which provides
the objective `buildIlCtrlArtifacts` that will generate routing information for the
GUI. To achieve a single objective from an agent, e.g. for control structure reload,
run `php setup/setup.php achieve $AGENT_NAME.$OBJECTIVE_NAME`, e.g. 
`php setup/setup.php achieve uicore.buildIlCtrlArtifacts` to generate the necessary
artifacts for the control structure. The agent might need to a config file to work,
which may be added as last parameter: 
`php setup/setup.php achieve uicore.buildIlCtrlArtifacts config.json`

## List available objectives
Calling `php setup/setup.php achieve` without any arguments and options  
or calling `php setup/setup.php achieve --list` will list all available objectives.

# Migrations

Migrations are major changes in the ILIAS database or file system that are 
necessary after an update. Migrations can take quite a long time, which is 
why they are available separately as a command. The advantage is that you can 
perform migrations after the update when the installation is already online again. 
For more information, see [https://docu.ilias.de/goto_docu_wiki_wpage_6399_1357.html](https://docu.ilias.de/goto_docu_wiki_wpage_6399_1357.html)

The command lists available migrations:

`php setup/setup.php migrate`


```
! [NOTE] There are 1 to run:

ilFileObjectMigrationAgent.ilFileObjectToStorageMigration: Migration of File-Objects to Storage service [remaining steps: 1110]
```

Individual migrations can then be started as follows, e.g.:

`php setup/setup.php migrate --run ilFileObjectMigrationAgent.ilFileObjectToStorageMigration`

A migration must be confirmed in each case, e.g.:

``` 
Do you really want to run the following migration? Make sure you have a backup
of all your data. You will run this migration on your own risk.

Please type 'ilFileObjectToStorageMigration' to confirm and start.:
>
```

With `--yes` migrations can be confirmed automatically.

Migrations are divided into individual steps, of which there can be many depending
on the migration. A default number of steps is executed in each case; the number 
can be increased or set with `--steps=...`.

## About the Config File

The config file is a json file with the following structure. **Mandatory fields
are printed bold**, all other fields might be omitted. A minimal example is
[here](minimal-config.json).

* **common** (type: object) settings relevant for the complete installation, e.g.:
    ``` 
    "common" : {
        "client_id" : "test7",
        "server_timezone" : "Europe/Berlin",
        "register_nic" : true
    }
    ```
  * **client_id** (type: string) is the identifier to be used for the installation 
  * *server_timezone* (type: string) where the installation resides, given as `region/city`,
    e.g. `Europe/Berlin`, defaults to `UTC`
  * *register_nic* (boolean) sends the identification number of the installation to a server
    of the ILIAS society together with some information about the installation, defaults to `false`
* *backgroundtasks* (type: object) is a service to run tasks for users in separate processes, e.g.:
    ``` 
    "backgroundtasks" : {
        "type" : "sync",
        "max_number_of_concurrent_tasks" : 3
    },
    ``` 
  * *type* (type: string) might be `async` or `sync`, defaults to `sync`
  * *max_number_of_concurrent_tasks* (type: number) that all users can run together, defaults to `1`
* **database** (type: object) is required to connect to the database, e.g.:
    ```
    "database" : {
        "type" : "innodb",
        "host" : "192.168.47.11",
        "port" : 3306,
        "database" : "db_test7",
        "user" : "test7_homer",
        "password" : "homers-secret",
        "create_database" : true
    },
    ```
  * *type* (type: string) of the database, `innodb`, defaults
    to `innodb`
  * *host* (type: string) the database server runs on, defaults to `localhost`
  * *port* (type: string or number) the database server uses, defaults to `3306`
  * *database* (type: string) name to be used, defaults to `ilias`
  * **user** (type: string) to be used to connect to the database
  * *password*  (type: string) to be used to connect to the database
  * *create_database* (type: boolean) if a database with the given name does not exist? Defaults to `true`.
* **filesystem** (type: object) configuration, e.g.:
    ```
    "filesystem" : {
        "data_dir" : "/var/ilias_external_data/test7"
    },
    ```
  * **data_dir** (type: string) outside the web directory where ILIAS puts some data
* *globalcache* (type: object) is a service for caching various information, e.g.:
    ```
    "globalcache" : {
        "service" : "static",
        "components" : "all"
    },
    ```
    or
    ```
    "globalcache" : {
        "service" : "apc",
        "components" : {
            "clng" : true,
            "comp" : true,
            "events" : true,
            "global_screen" : true,
            "obj_def" : true,
            "ilctrl" : true,
            "tpl" : true,
            "tpl_blocks" : true,
            "tpl_variables" : true
        }
    },
    ```
    or
    ```
    "globalcache" : {
        "service" : "memcached",
        "components" : "all",
        "memcached_nodes" : [
            {
                "active" : true,
                "host" : "example1.com",
                "port" : 4711,
                "weight" : 10
            },
            {
                "active" : false,
                "host" : "example2.com",
                "port" : 4712,
                "weight" : 90
            }
        ]
    },
    ```
  * *service* (type: string) to be used for caching. Either `none`, `static`, `memcached`
    or `apc`, defaults to  `static`.
  * *components* (type: string or object) that should use caching. Can be `all` or any list of components that
    support caching,  (must be set too, if *service* is set)
  * *memcached_nodes* (type: array of objects) if *service* equals `memcached` place your nodes here
* **http** (type: object) configuration, e.g.:
    ```
    "http" : {
        "path" : "https://test7.ilias.de/",
		"https_autodetection" : {
			"header_name" : "my-header-name",
			"header_value" : "my-header-value"
		},
		"proxy" : {
			"host" : "webproxy.ilias.de",
			"port" : "8088"
		}
    },
    ```
  * **path** (type: string) to your installation on the internet
  * *https_autodetection* (type: object) allows ILIAS to be run behind a proxy that terminates ssl
    connections
    * *header_name* (type: string) that the proxy sets to indicate ssl connections
    * *header_value* (type: string) that the proxy sets for said header
  * *proxy* (type: object) for outgoing http connections
    * *host* (type: string) the proxy runs on
    * *port* (type: string or number) the proxy listens on
* *logging* (type: object) configuration if logging should be used
    ```
	"logging" : {
		"enable" : true,
		"path_to_logfile" : "/var/log/ilias_test7.log",
		"errorlog_dir" : "/var/log/ilias_errorlogs/"
	},
    ```
  * *enable* (type: boolean) the logging, defaults to `false`
  * *path_to_logfile* (type: string) to be used for logging
  * *errorlog_dir* (type: string) to put error logs in
* *mathjax* (type: object) contains settings for Services/MathJax
    
    The MathJax settings can also be done manually in the ILIAS adminstration.  
    Settings included here will overwrite those at the next update.
    MathJax 3 is supported, but MathJax 2 is recommended.
    ```
	"mathjax": {
		"client_enabled": true,
		"client_polyfill_url": "",
		"client_script_url": "https://cdn.jsdelivr.net/npm/mathjax@2.7.9/MathJax.js?config=TeX-AMS-MML_HTMLorMML,Safe",
		"client_limiter": 0,
		"server_enabled": true,
		"server_address": "http://your.mathjax.server:8003",
		"server_timeout": 5,
		"server_for_browser": true,
		"server_for_export": true,
		"server_for_pdf": true
	},
    ```
  * *client_enabled* (type: boolean) client-side rendering in the browser is enabled
  * *client_polyfill_url* (type: string) url of a polyfill script for MathJax 3 to support older browsers
  * *client_script_url* (type: string) url of the MathJax script to be included on the browser page
  * *client_limiter* (type: integer) type of delimiters expected by the included MathJax script
    * 0: \\( ... \\)
    * 1: [tex] ... [/tex]
    * 2: \<span class="math"\> ... \</span\>
  * *server_enabled* (type: boolean) server-side rendering is enabled
  * *server_address* (type: string) address of the rendering server
  * *server_timeout* (type: integer) timeout in seconds to wait for a server response
  * *server_for_browser* (type: boolean) use the server for rendering in the browser
  * *server_for_export* (type: boolean) use the server for HTML exports
  * *server_for_pdf* (type: boolean) use the server for PDF generation
* *preview* (type: object) contains settings for Services/Preview
    ```
	"preview" : {
		"path_to_ghostscript" : "/usr/bin/gs"
	},
    ```
  * *path_to_ghostscript* (type: string) executable
* *mediaobject* (type: object) contains settings for Services/MediaObjects
    ```
	"mediaobject" : {
		"path_to_ffmpeg" : "/usr/bin/ffmpeg"
	},
    ```
  * *path_to_ffmpeg* (type: string) executable
* *style* (type: obejct) configuration to change the ILIAS look
    ```
	"style" : {
		"manage_system_styles" : true,
		"path_to_lessc" : "/usr/bin/lessc"
	},
    ```
  * *manage_system_styles* (type: boolean) via a GUI in the installation, defaults to `false`
  * *path_to_lessc* (type: string) to compile less to css
* **systemfolder** (type: object) settings for Module/SystemFolder
    ```
	"systemfolder" : {
		"client" : {
			"name" : "test7",
			"description" : "Test Installation for ILIAS 7",
			"institution" : "Atomic Powerplant Springfield"
		},
		"contact" : {
			"firstname" : "Homer",
			"lastname" : "Simpson",
			"title" : "Sir",
			"position" : "Security Inspector Sector 7G",
			"institution" : "Atomic Powerplant Springfield",
			"street" : "742 Evergreen Terrace",
			"zipcode" : "12345",
			"city" : "Springfield",
			"country" : "USA",
			"phone" : "(939) 555-0113",
			"email" : "Chunkylover53@aol.com"
		}
	},
    ```
  * *client* (type: string) information
    * *name* (type: string) of the ILIAS installation
    * *description* (type: string) of the installation
    * *institution* (type: string) that provides the installation
  * **contact** (type: string) to a person behind the installation
    * **firstname** (type: string) of said person
    * **lastname** (type: string) of said person
    * *title* (type: string) of said person
    * *position* (type: string) of said person
    * *institution* (type: string) of said person
    * *street* (type: string) of said person
    * *zipcode* (type: string) of said person
    * *city* (type: string) of said person
    * *country* (type: string) of said person
    * *phone* (type: string) of said person
    * **email** (type: string) of said person
* *utilities* (type: object) contains settings for Services/Utilities
    ```
	"utilities" : {
		"path_to_convert" : "/usr/bin/convert",
		"path_to_zip" : "/usr/bin/zip",
		"path_to_unzip" : "/usr/bin/unzip"
	},
    ```
  * *path_to_convert* (type: string) from ImageMagick, to resize images
  * *path_to_zip*" (type: string) to zip files
  * *path_to_unzip*" (type: string) to unzip files
* *virusscanner* (type: object) configuration
    ```
	"virusscanner" : {
		"virusscanner" : "clamav",
		"path_to_scan" : "/usr/bin/clamdscan",
		"path_to_clean" : "/usr/bin/clamdscan --remove=yes",
	},
    ```
    or
    ```
	"virusscanner" : {
		"virusscanner" : "icap",
		"icap_host" : "192.168.47.12",
		"icap_port" : 4712,
		"icap_service_name" : "icap-name",
		"icap_client_path" : "icap-client-path"
	},
    ```
  * *virusscanner* (type: string) to be used. Either `none`, `sophos`, `antivir`, `clamav` or `icap`
  * *path_to_scan* (type: string) command of the scanner
  * *path_to_clean* (type: string) command of the scanner
  * *icap_host* (type: string) host address of the icap scanner
  * *icap_port* (type: string or number) port if the icap scanner
  * *icap_service_name* (type: string) service name of the icap scanner
  * *icap_client_path* (type: string) path to the `c-icap-client`, if this is left empty, a php client will be used
* *privacysecurity* (type: object)
    ```
	"privacysecurity" : {
		"https_enabled" : true,
		"auth_duration" : 3000,
		"account_assistance_duration" : 3000
	},
    ```
  * *https_enabled* (type: boolean) forces https on login page, defaults to `false`
  * *auth_duration* (type: integer) stretches the auth-duration on logins to the given amount in ms, defaults to `null`
  * *account_assistance_duration* (type: integer) stretches the password- and username-assistance duration to the given amount in ms, defaults to `null`
* *webservices* (type: object)
    ```
	"webservices" : {
		"soap_user_administration" : true,
		"soap_wsdl_path" : "https://test7.ilias.de/webservice/soap/server.php?wsdl",
		"soap_connect_timeout" : 30,
		"rpc_server_host" : "192.168.47.13",
		"rpc_server_port" : "11112"
	},
    ```
  * *soap_user_administration* (type: boolean) enable administration per soap, defaults to `false`
  * *soap_wsdl_path* (type: string) path to the ilias wsdl file, default is `http:///webservice/soap/server.php?wsdl`
  * *soap_connect_timeout* (type: number) maximum time in seconds until a connection attempt to the SOAP-Webservice is interrupted, defaults to `10`
  * *rpc_server_host* (type: string) Java-Server host (must be set too, if *rpc_server_port* is set)
  * *rpc_server_port* (type: string or number) Java-Server port (must be set too, if *rpc_server_host* is set)
* *chatroom* (type: object) see also [Chat Server Setup](/Modules/Chatroom/README.md), eg.:
    ```
	"chatroom" : {
		"address" : "192.168.47.14",
		"port" : 8081,
		"sub_directory" : "/chat",
		"https" : {
			"cert" : "/etc/ssl/certs/server.pem",
			"key" : "/etc/ssl/private/server.key",
			"dhparam" : "/etc/ssl/private/dhparam.pem"
		},
		"log" : "/var/log/ilias_onscreenchat/access.log",
		"log_level" : "info",
		"error_log" : "/var/log/ilias_onscreenchat/error.log",
		"ilias_proxy" : {
			"ilias_url" : "https://chat-ilias-proxy.ilias.de"
		},
		"client_proxy" : {
			"client_url" : "https://chat-client-proxy.ilias.de"
		},
		"deletion_interval" : {
			"deletion_unit" : "months",
			"deletion_value" : "6",
			"deletion_time" : "23:45"
		}
	}
    ```
  * *address* (type: string) IP-Address/FQN of Chat Server
  * *port* (type: string or number) of the chat server, possible value from `1` to `65535` 
  * *sub_directory* (type: string) http(s)://[IP/Domain]/[SUB_DIRECTORY]
  * *https* (type: object) adding this enables https
    * *cert* (type: string) absolute server path to the SSL certificate file e.g. `/etc/ssl/certs/server.pem`
    * *key* (type: string) absolute server path to the private key file e.g. `/etc/ssl/private/server.key`
    * *dhparam* (type: string) absolute server path to a file e.g. `/etc/ssl/private/dhparam.pem`
  * *log* (type: string) absolute server path to the chat server's log file e.g. `/var/www/ilias/data/chat.log`
  * *log_level* (type: string) possible values are `emerg`, `alert`, `crit` `error`, `warning`, `notice`, `info`, `debug`, `silly`, defaults to `warning`
  * *error_log* (type: string) absolute server path to the chat server's error log file e.g. `/var/www/ilias/data/chat_error.log`
  * *ilias_proxy* (type: object) ILIAS to Server Connection
    * *ilias_url* (type: string) URL for the Server connection
  * *client_proxy* (type: object) Client to Server Connection
    * *client_url* URL for the Server connection
  * *deletion_interval* (type: object)
    * *deletion_unit* (type: string) possible values are `days`, `weeks`, `months`, `years`
    * *deletion_value* (type: string or number) depending on `deletion_unit` possible values are `days max 31`, `weeks max 52`, `months max 12`, `years no max`
    * *deletion_time* (type: string) with format `HH:MM e.g. 23:30`
