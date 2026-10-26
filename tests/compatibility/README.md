# PHP compatibility regression tests

Run the focused compatibility checks independently of the historical development
suite:

```sh
composer install --working-dir=tests/compatibility
php tests/compatibility/lint.php
php tests/compatibility/run.php
```

GitHub Actions runs these checks on PHP 7.4 and PHP 8.0–8.4. The lint command
fails on deprecations as well as syntax errors. Runtime checks convert reported
warnings and deprecations into exceptions and do not contact external services.

To repeat the runtime checks against an application's existing dependencies,
set `COMPAT_AUTOLOAD` to its Composer autoloader:

```sh
COMPAT_AUTOLOAD=/path/to/application/vendor/autoload.php php tests/compatibility/run.php
```

The test bootstrap loads this checkout's library classes before the application's
Composer classmap. The application and its installed dependencies are not modified.
These focused checks do not replace the historical complete test suite.
