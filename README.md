MySQL find and replace
======================

**NOTE** There is a better way now - use [interconnectit/Search-Replace-DB](https://github.com/interconnectit/Search-Replace-DB).
It has verbosity control, collation converter and works both with command
line and web interface.  It's just better.

This script performs a find and replace operation across all fields, of all
tables in a specified MySQL database.  On top of regular fields, it also 
processes serialized values.

This code is heavily based on MySQL search and replace script written by 
David Coveney, and he deserves all the credit.  The original script is here:

http://davidcoveney.com/782/mysql-database-search-replace-with-serialized-php/

My changes so far are:

* CLI, not web based. In the future, it will support both environments.
* Fail on any MySQL error. Original code was just printing out the message.
* Run-time configuration through CLI options
* Lots of coding style changes (work in progress).
* Composer installer

Install
-------

Install with composer like so:

```
{
	"require": {
		"mamchenkov/mysql-find-and-replace": "1.0.*"
	}
}
```

Usage
-----

Always, always, always backup your database before usage:
```
$ mysqldump test_db > test_db.backup.sql
```

Run the script like so:

```
$ ./bin/mysql-replace.php database=test_db find=foo replace=bar
```

