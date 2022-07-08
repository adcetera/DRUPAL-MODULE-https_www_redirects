# HTTPS/WWW Redirect Module

This module allows site admins to force a site to use "https," "www," or both in URLs.

### Note

- After saving the configuration, Drupal's cache will be flushed.
- After saving, you may be logged out of Drupal as the site's domain or scheme may have changed based on settings applied.

## Overrides

Both settings (forced HTTPS or WWW) can be overridden in either the module configuration page or in the settings.php file.

### Overriding in settings.php

To override either setting, add the following to the bottom of the settings.php file:

```bash
/**
 * Ignore forced https
 */
$config['https_www_redirects.settings']['bypass_https_hosts'] = [
  'example.org',
  'example.com',
];

/**
 * Ignore forced www
 */
$config['https_www_redirects.settings']['bypass_www_hosts'] = [
  'local.lndo.site',
  'example.org'
];
```

### Note

- By default, 'localhost' or '127.0.0.1' hosts will be excluded from redirection settings in the module.
