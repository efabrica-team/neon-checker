# Neon checker

This library helps to find errors in neon files, it can be used in CI tools.

## Usage
`neon-checker check <dirs>...`

For more information, run `neon-checker check --help`

The result looks like:

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

Return code is count of errors found, so you can use it in CI tools.
```
echo $?
1
```

## Installation
Neon checker requires PHP 7.1.0 or newer. You can install it via Composer. This project is not meant to be run as a dependency, so install it globally:

`composer global require efabrica/neon-checker`

or as stand alone project

```
mkdir /var/www/neon-checker
cd /var/www/neon-checker
composer require efabrica/neon-checker
```
