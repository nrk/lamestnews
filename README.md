Alpaca
======

[Alpaca](http://github.com/nrk/alpaca) is a port to PHP of [Salvatore Sanfilippo](http://antirez.com)'s
[Lamer News](http://github.com/antirez/lamernews) implemented using [Silex](http://silex.sensiolabs.com)
and [Twig](http://twig.sensiolabs.org) for the web part and [Predis](http://github.com/nrk/predis) to
access the Redis datastore.

While Alpaca tries to stick to the original Ruby implementation as much as possible, especially when it
comes to the layout of the data stored in Redis and the look and feel of its web interface, some things
may differ.


## Installation

Once the Git repository has been cloned, enter the directory and initialize/update the submodules:

```bash
  $ git submodule init
  $ git submodule update
```

If you are using PHP 5.4 (which is currently in BETA) you can leverage its internal webserver to start
experimenting with Alpaca right away using the following command:

```bash
  $ php -S localhost:8000 -t web/
```

For production environments you can just use any webserver by following the usual configuration instructions
and pointing the document root to the `web` subdirectory of the repository.


## Development

When modifying Alpaca please be sure that no warnings or notices are emitted by PHP by running
the interpreter in your development environment with the `error_reporting` variable set to
`E_ALL | E_STRICT`.

The recommended way to contribute to Alpaca is to fork the project on GitHub, create new topic
branches on your newly created repository to fix or add features and then open a new pull request
with a description of the applied changes. Obviously, you can use any other Git hosting provider
of your preference. Diff patches will be accepted too, even though they are not the preferred way
to contribute to Alpaca.

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

The code for Alpaca is distributed under the terms of the MIT license (see LICENSE).
Parts taken from the original Lamer News code base remain licensed under the BSD license.
