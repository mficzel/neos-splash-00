
![Neos CMS & Flow Framework ](https://avatars3.githubusercontent.com/u/11575267?s=400&v=4)

# Neos CMS / Flow Framework Installer

Creates a new [Neos CMS / Flow Framework](https://www.neos.io) project distribution by using `composer create-project`.


## Usage

Run command:

```bash
composer create-project -n neos/installer /path/to/new-project
```

You will be asked questions regarding your desired installation.

**Please note:** For reasons of automation the installer initially installs some thrid-party dependencies. 
These will be removed at the end of the installation process.

## Quick installation check

Start the php built-in webserver:

```bash
cd /path/to/new-project
./flow server:run
```

Visit in your browser: http://127.0.0.1:8081/

**Please note:** You need to replace `127.0.0.1` with your machine's IP address, if you're not on your local host.

You should see a welcome page.

## Acknowledgements

This installer is inspired by the [Icehawk Installer](https://github.com/icehawk/installer). 
