# rector-p partial rector execution

## install

`composer req andersundsehr/rector-p`

## Why should you need this?

if you have a large old project and want to use rector, it can be a pain to convert the whole project at once.
So you can use rector-p to convert the project file by file.
you will get asked for each file that has changes if you want to apply them.

## Example

`rector-p` or `vendor/bin/rector-p` if you did not setup your PATH environment variable

[![asciicast](https://asciinema.org/a/671145.png)](https://asciinema.org/a/671145)

### if you only want to run it with a specific path you can do it like this:

`rector-p src/Controller/`  
or  
`rector-p src/Controller/MyController.php src/Controller/MyOtherController.php`

# options you can use:

`rector-p --help`
````bash
Usage:
  partial [options] [--] [<source>...]

Arguments:
  source                Files or directories to be upgraded.

Options:
  -s, --startOver       Start over with the first file (be default rector-p keeps a record of files that have no changes in them)
  -p, --chunk=CHUNK     chunk(part) definition eg 1/2 (first half) or 2/2 (second half) or 3/10 (third tenth) [default: "1/1"]
  -c, --config=CONFIG   Path to config file [default: "/app/rector.php"]
  -h, --help            Display help for the given command. When no command is given display help for the partial command
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
````

# with ♥️ from anders und sehr GmbH

> If something did not work 😮  
> or you appreciate this Extension 🥰 let us know.

> We are hiring https://www.andersundsehr.com/karriere/
