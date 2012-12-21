MySQL find and replace
======================

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

Run the script like so:

```
$ mysqldump test_db > test_db.backup.sql
$ php -f searchandreplace.php database=test_db find=foo replace=bar
```

