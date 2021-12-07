# Symfony exercise
### A ManyToMany relations backend and large dataset (.csv) import Command.

### Requirements:

- Build with Symfony the needed entities that could represent the following database model:

  - Films
    - Title
    - Publishing date
    - Genre(s)
    - Duration
    - Production company
    - Actor(s)
      - Name
      - Birthdate
      - Born place
      - Died date
    - Director(s)
      - Name
      - Birthdate
---

- Build Easyadmin controller to **view** Films data.
  - It must include a simple search (no filters needed) for relevant data for the user.
  - Visual aspect is not relevant, native styles from Easyadmin are enough.

---

- Build Easyadmin controller to **view and edit** Actors and Directors data.
  - It must include a simple search (no filters needed) for relevant data for the user.
  - Visual aspect is not relevant, native styles from Easyadmin are enough.

---

- Create a Symfony command to import the [attached csv file](IMDbMovies.csv). Source: https://www.kaggle.com/stefanoleone992/imdb-extensive-dataset
  - Csv is uniform but if a row is malformed we can ignore it.

---
    
### Solution proposed:

#### Notes about this proposal:

This exercise is intended to test Symfony capabilities creating CRUD controllers and commands. This is why almost all the code is oriented to work under a Symfony, doctrine and provided CrudControllers methods approach.

To gain some performance and limit RAM consuming as much as possible we decided to chunk the large .csv dataset provided in smaller parts and create temp files to store dependencies found in the file. This will create a huge number of small files under project's `var/temp` directory (hopefully if it works fine it will self clean at completion). These small files are easier to manage by the command instead of trying to manage the big one provided.

We have also set a 256Mb RAM max limit and disabled SQLLogger in the command `__constructor()` for performance optimization.

At database level there are a couple of indexes created by ORM annotations, but query performance on the backend should be configured at server level using a cache driver as `APC`, `memcache`, etc.

Although using this approach we have decent times importing the whole dataset (84k+ films and 34k+/400k+ directors/actors relations in about 15-20 minutes) we'd rather use a different approach for a production service, maybe using native database tools to import the data (e.g. using [`LOAD DATA LOCAL INFILE` if using MySQL](https://dev.mysql.com/doc/refman/8.0/en/load-data.html)...)

```
~/imdb-easyadmin$ time symfony console app:import-films IMDbMovies.csv -q

real	19m40.995s
user	6m42.244s
sys	5m31.211s
```

(*) Without the `-q` option, the command will output visual progress to the console, leading in higher (but reasonable) execution times.

The command is tested against [Blackfire.io](https://blackfire.io/) without relevant advices:

```
~/imdb-easyadmin$ blackfire run symfony console app:import-films IMDbMovies.csv

Blackfire run completed
Graph                 https://blackfire.io/profiles/9c887d0c-7e50-46b5-bb08-5e892466c554/graph
No tests!             Create some now https://blackfire.io/docs/testing-cookbooks/tests
No recommendations

Memory        158MB
Wall Time 25min 18s
Network         n/a     n/a     n/a
SQL             n/a     n/a
```
(*) Execution time and RAM consumed are increased by the `blackfire` command itself.

#### Persistence: 

The code proposed is tested against a development environment (i5/4cores + 16Gb RAM) running Ubuntu 20.04 with php 8.0 and MariaDB 10.3 with InnoDB storage engine locally installed. Also as requirement, we will need composer and Symfony cli.

```
~/imdb-easyadmin$ php -v
PHP 8.0.13 (cli) (built: Nov 22 2021 09:50:43) ( NTS )
Copyright (c) The PHP Group
Zend Engine v4.0.13, Copyright (c) Zend Technologies
    with Zend OPcache v8.0.13, Copyright (c), by Zend Technologies

~/imdb-easyadmin$ mysql -V
mysql  Ver 15.1 Distrib 10.3.32-MariaDB, for debian-linux-gnu (x86_64) using readline 5.2

~/imdb-easyadmin$ composer -V
Composer version 2.1.14 2021-11-30 10:51:43

~/imdb-easyadmin$ symfony -V
Symfony CLI version v4.26.10 (2021-12-05T15:14:13+0000 - stable)
```
For convenience, we provide a docker-compose configuration file with MySQL (latest) and Adminer for testing purposes.

### _Warning using docker images provided_

Docker configuration files are provided with default configuration options. They are not recommended for testing large database imports as they lack of any performance optimization.

If you decide to test them we will use a [Mysql latest](https://hub.docker.com/_/mysql) | [docker](https://docs.docker.com/get-docker/) image to persist data and [Adminer 4.8](https://www.adminer.org/) to manage the DB. Both are installed and run with [docker-compose](https://docs.docker.com/compose/gettingstarted/):

`docker-compose -f docker-compose.mysql.yml up`

### Project installation
Deps:
- Composer: https://getcomposer.org/download/
- Symfony cli: https://symfony.com/download

Versions used:
- [Symfony 5.4 LTS](https://symfony.com/releases/5.4)
- [php 8.0](https://www.php.net/releases/8.0/en.php) as language level
- [Easyadmin 3.5](https://symfony.com/bundles/EasyAdminBundle/current/index.html) as backend

#### Clone the project and install dependencies:
```
git clone git@github.com:luismisanchez/imdb-easyadmin.git
cd imdb-easyadmin
composer install
```
#### Set DB config and credentials on .env file
`DATABASE_URL="mysql://root:example@127.0.0.1:3306/imdb?serverVersion=8.0"`

`DATABASE_URL="mysql://root:example@127.0.0.1:3306/imdb?serverVersion=mariadb-10.3.32"`

(*) If using MariaDb you must prefix `serverVersion` with `mariadb-`

#### Create database (if not created yet):
`symfony console doctrine:database:create`

#### Doctrine migrate schema:
`symfony console doctrine:migrations:migrate`

#### If using Adminer you could manage created DB:
http://127.0.0.1:8080/
- user: `root`
- password: `example`
- DDBB: `imdb`

#### Start Easyadmin backend:
`symfony server:start`

#### Access Easyadmin backend:
http://127.0.0.1:8000/admin

## Import command
`symfony console app:import-films -h`

```Description:
Import films from csv to the IMDB entities database

Usage:
app:import-films [options] [--] <file>

Arguments:
file                  Path to the .csv file to import

Options:
-u, --update          Update films data and relations. By default the command will import the whole dataset, truncating previously stored data.
-t, --test            For testing purposes, limit dataset to 1000 rows.
-h, --help            Display help for the given command. When no command is given display help for the list command
-q, --quiet           Do not output any message
-V, --version         Display this application version
--ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
-n, --no-interaction  Do not ask any interactive question
-e, --env=ENV         The Environment name. [default: "dev"]
--no-debug        Switch off debug mode.
-v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

### Examples:

#### Truncate films and relations and import whole .csv file:

`symfony console app:import-films IMDbMovies.csv`

```
Step 1
 85999/85999 [============================] 100% 4 mins/4 mins 36.0 MiB -- Creating films and dependencies files from IMDbMovies.csv

Step 2
 34708/34708 [============================] 100%  1 min/1 min  46.0 MiB -- Importing directors from IMDbMovies.csv

Step 3
 417321/417321 [============================] 100% 14 mins/14 mins 96.0 MiB -- Importing actors from IMDbMovies.csv.

 [OK] 85857 films imported.


Cleaning temp files, please wait a moment...
```

#### Update films and relations (be careful, huge time-consuming on large datasets):
`symfony console app:import-films IMDbMovies.csv -u -t`
