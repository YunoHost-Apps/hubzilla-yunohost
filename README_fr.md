# Hubzilla pour YunoHost

[![Niveau d'intégration](https://dash.yunohost.org/integration/hubzilla.svg)](https://dash.yunohost.org/appci/app/hubzilla) ![](https://ci-apps.yunohost.org/ci/badges/hubzilla.status.svg) ![](https://ci-apps.yunohost.org/ci/badges/hubzilla.maintain.svg)  
[![Installer Hubzilla avec YunoHost](https://install-app.yunohost.org/install-with-yunohost.svg)](https://install-app.yunohost.org/?app=hubzilla)

*[Read this readme in english.](./README.md)*
*[Lire ce readme en français.](./README_fr.md)*

> *Ce package vous permet d'installer Hubzilla rapidement et simplement sur un serveur YunoHost.
Si vous n'avez pas YunoHost, regardez [ici](https://yunohost.org/#/install) pour savoir comment l'installer et en profiter.*

## Vue d'ensemble

Plateforme de publication décentralisée et un réseau social.

**Version incluse :** 5.6~ynh1



## Captures d'écran

![](./doc/screenshots/hubzilla-1.png)

## Avertissements / informations importantes

## Installation
Before installing, read the [Hubzilla installation instructions](https://framagit.org/hubzilla/core/blob/master/install/INSTALL.txt) for important information about:

### Register a new domain and add it to YunoHost
- Hubzilla requires a dedicated domain, so obtain one and add it using the YunoHost admin panel. **Domains -> Add domain**. As Hubzilla uses the full domain and is installed on the root, you can create a subdomain such as hubzilla.domain.tld. Don't forget to update your DNS if you manage them manually.

## Ldap Admin user rights, logs and failed database updates

- **For admin rights**: When installation is complete, you will need to visit your new hub's page and login with the **admin account username** which was entered at the time of installation process. You should then be able to create your first channel and have the **admin rights** for the hub.

- **For normal YunoHost users**: Normal LDAP users can login through LDAP authentication and create there channels.

- **Failing to get admin rights**: If the admin cannot access the admin settings at `https://hubzilla.example.com/admin` then you have to **manually add 4096** to the **account_roles** under **accounts** for that user in the **database through phpMyAdmin**.

- **For logs**: Go to **admin->logs** and enter the file name **php.log**.

- **Failed Database after Upgrade:** Some times databse upgrade fails after version upgrade. You can go to hub eg. `https://hubzilla.example.com/admin/dbsync/` and check the numbers of failled update. These updates will have to be ran manually by **phpMyAdmin**.

## Documentations et ressources

* Site officiel de l'app : https://zotlabs.org/page/hubzilla/hubzilla-project
* Dépôt de code officiel de l'app : https://framagit.org/hubzilla/core
* Documentation YunoHost pour cette app : https://yunohost.org/app_hubzilla
* Signaler un bug : https://github.com/YunoHost-Apps/hubzilla_ynh/issues

## Informations pour les développeurs

Merci de faire vos pull request sur la [branche testing](https://github.com/YunoHost-Apps/hubzilla_ynh/tree/testing).

Pour essayer la branche testing, procédez comme suit.
```
sudo yunohost app install https://github.com/YunoHost-Apps/hubzilla_ynh/tree/testing --debug
ou
sudo yunohost app upgrade hubzilla -u https://github.com/YunoHost-Apps/hubzilla_ynh/tree/testing --debug
```

**Plus d'infos sur le packaging d'applications :** https://yunohost.org/packaging_apps