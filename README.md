Lamest News
===========

Lamer than lame.

[Lamest](http://github.com/nrk/lamest) is a port to PHP of [Salvatore Sanfilippo](http://antirez.com)'s
[Lamer News](http://github.com/antirez/lamernews) implemented using [Silex](http://silex.sensiolabs.com)
and [Twig](http://twig.sensiolabs.org) for the web part and [Predis](http://github.com/nrk/predis) to
access the Redis datastore.

While the project tries to stick to the original Ruby implementation as much as possible, especially when
it comes to the layout of the data stored in Redis and the look and feel of its web interface, some things
might differ (one of them is the use of a templating engine).

Right now it is a work in progress and the priority so far has been to catch up with Lamer News, so
there are still a lot of areas that could be improved and tests are still missing. As for performances,
they are worse than the original Ruby implementation, even with an opcode cache like APC or XCache,
mostly due to the fact that the application is reloaded on each request instead of being long-running,
but a few experiments using [AiP](http://github.com/indeyets/appserver-in-php) showed that following
such an approach would result in noticeable gains by bringing latency down to 5ms from the current 11ms.


## Installation

Once the Git repository has been cloned, enter the directory and fetch all the needed dependencies using
[Composer](http://packagist.org/about-composer) by typing `composer install` in a shell. If Composer is
not installed or globally available on your system then you can download its phar package and use it to
install the dependencies:

```bash
  $ wget http://getcomposer.org/composer.phar
  $ php composer.phar install
```


## Execution

If you are using PHP 5.4, which reached the Release Candidate phase, you can leverage its 
[built-in webserver](http://php.net/manual/en/features.commandline.webserver.php) to start experimenting
with Lamest right away using the following command:

```bash
  $ php -S localhost:8000 -t web/
```

In production environments you can just use any webserver by following the usual configuration
instructions and pointing the document root to the `web` subdirectory of the repository.
For performance reasons you might want to make sure that Silex debugging is disabled and the
Twig template cache is set and points to a directory writable by the PHP process:

```php
<?php
//...
$app['debug'] = false;
//...
$app->register(new TwigProvider(), array(
	// ...
    'twig.options' => array('cache' => '/path/to/cache'),
    // ...
));
```


## Development

When modifying code please make sure that no warnings or notices are emitted by PHP by running
the interpreter in your development environment with the `error_reporting` variable set to
`E_ALL | E_STRICT`.

The recommended way to contribute is to fork the project on GitHub, create new topic branches on
your newly created repository to fix or add features and then open a new pull request with a
description of the applied changes. Obviously, you can use any other Git hosting provider of your
preference. Diff patches will be accepted too, even though they are not the preferred way to
contribute to the project.

When reporting issues on the bug tracker please provide as much information as possible if you do
not want to be redirected [here](http://yourbugreportneedsmore.info/).


## Dependencies

- [PHP](http://www.php.net) >= 5.3.2
- [Silex](http://silex.sensiolabs.com)
- [Twig](http://twig.sensiolabs.com)
- [Predis](http://github.com/nrk/predis) >= 0.7.0-dev
- [PredisServiceProvider](http://github.com/nrk/PredisServiceProvider)


## Authors

- [Daniele Alessandri](mailto:suppakilla@gmail.com) ([twitter](http://twitter.com/JoL1hAHN))


## License

The code for Lamest News is distributed under the terms of the MIT license (see LICENSE).
Parts taken from the original Lamer News code base remain licensed under the BSD license.
