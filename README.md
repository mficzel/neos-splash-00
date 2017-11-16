
![Neos-CMS & Flow-Framework ](https://avatars3.githubusercontent.com/u/11575267?s=400&v=4)

# Neos-CMS / Flow-Framework distribution builder

Creates a new [Neos-CMS / Flow-Framework](https://www.neos.io) project-distribution by using `composer create-project`.

## Usage

Run command:

```bash
composer create-project -n neos/installer __new_project__
```

You will be asked questions regarding your desired distribution.

**Please note:** For reasons of automation the installer initially installs some thrid-party dependencies. 
These will be removed at the end of the installation process.

## Acknowledgements

This installer is inspired by the [Icehawk Installer](https://github.com/icehawk/installer). 
