# StopForumSpam

A MediaWiki extension that allows integration various [https://www.stopforumspam.com/](stopforumspam.org) deny-lists.

## Installing

See also: https://www.mediawiki.org/wiki/Extension:StopForumSpam#Installation

1. Download and place the file(s) in a directory called StopForumSpam within your MediaWiki extensions/ folder.
2. Add the following code at the bottom of your ```LocalSettings.php```:
```
wfLoadExtension( 'StopForumSpam' );
```
3. Configure the extension as required.  **Note:** A proper value for ```$wgSFSIPListLocation``` _MUST_ be configured before the extension is used within any production environment.
4. Navigate to Special:Version on your wiki to verify that the extension is successfully installed.
5. Optionally run the provided maintenance script (```updateDenyList.php```) to populate and view information about the cache.

## Usage

1. Select an IP deny-list file from https://www.stopforumspam.com/downloads and assign the appropriate value to ```$wgSFSIPListLocation```.
2. Set ```$SFSReportOnly``` to ```true``` or ```false```.  A value of ```true``` will log events but WILL NOT enforce blocking non-read user actions for IP addresses within the deny list.  A value of ```false``` will both log events and block non-read user actions for IP addresses within the deny list.

## Authors

* **Legoktm** [https://www.mediawiki.org/wiki/User:Legoktm]
* **Skizzers** [https://www.mediawiki.org/wiki/User:Skizzerz]
* **Aaron Schultz** [aschulz@wikimedia.org]
* **Sam Reed** [reedy@wikimedia.org]
* **Scott Bassett** [sbassett@wikimedia.org]

## License

This project is licensed under the GPL 2.0 License - see the [COPYING](COPYING) file for details.
