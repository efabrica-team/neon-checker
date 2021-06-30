# Neon checker

This library helps to find errors in neon files, it can be used in CI tools.

## Installation
Neon checker requires PHP 7.1.0 or newer. You can install it via Composer. This project is not meant to be run as a dependency, so install it globally:

`composer global require efabrica/neon-checker`

or as stand alone project

```
mkdir /var/www/neon-checker
cd /var/www/neon-checker
composer require efabrica/neon-checker
```

## Usage

This library contains two commands. For both commands return code is count of errors found, so you can use them in CI tools.
```
echo $?
1
```

### validate
```shell
neon-checker validate <dirs>...
```

Note: For more information, run `neon-checker validate --help`


The result of command looks like this:
```
Errors found: 1
```

With verbose output you will get:
```
Errors found: 1

Error: Unexpected ',' on line 23, column 10.
File: neons/subdir/test2.neon:23
```

And with very verbose there will be also part of file content with error.
```
Content:
18: 							b:
19: 								c:
20: 									d"
21: 
22: aaa:
23: 	<error>bbb: ccc, ddd</error>
24: 
25: 	a:
26: 		b:
27: 			c:
28: 				d"
```

### disallowed
```shell
neon-checker disallowed [--disallowed-keys DISALLOWED-KEYS] [--disallowed-values DISALLOWED-VALUES] [--] <dirs>...
```

With this command, you can disallow some keys and / or values in neon configs.

For example:

To disallow key `http:frames` e.g.
```neon
http:
    frames: someValue
```

```shell
neon-checker disallowed app/config/ --disallowed-keys="http:frames"
```

To disallow concrete value for this key e.g.
```neon
http:
    frames: yes
```
you can run command with option --disallowed-values
```shell
neon-checker disallowed app/config/ --disallowed-values="http:frames:yes"
```

At least one of options `--disallowed-keys` and `--disallowed-values` has to be set, they can be used multiple times and can be combined. For example:
```shell
neon-checker disallowed app/config/ --disallowed-keys="session:expiration" --disallowed-keys="php:date.timezone" --disallowed-values="http:frames:yes"
```

Output of command is simple:
```
Errors found: 4
```

Or more descriptive with verbose (`-v`) option:
```
Errors found: 4

File app/config/config.neon
contains these disallowed keys:
- session:expiration
- php:date.timezone
contains these disallowed values:
- http:frames:yes

File app/config/config.local.neon
contains these disallowed keys:
- php:date.timezone
```
