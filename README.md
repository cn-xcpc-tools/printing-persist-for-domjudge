# Persisting Printing in DOMjudge database

For 7.0.x, Files in `DOMJudgeBundle` are placed in `$INSTALLPATH/domserver/webapp/src/DOMJudgeBundle`. If some file is existed there initially, please check the difference between those and 7.0.1 manually.

For 7.1.1, Files in `App-7.1.1` are placed in `$INSTALLPATH/domserver/webapp/`. You can replace files arbitrarily.

For 7.3.3, Files in `App-7.3.3` are placed in `$INSTALLPATH/domserver/webapp/`. You can replace files arbitrarily.

For 8.1.4, Files in `App-8.1.4` are placed in `$INSTALLPATH/domserver/webapp/`. You can replace files arbitrarily. **After that**, you need to add these lines after `public static $classMap = array (`:

```
        'App\\Controller\\API\\PrintingController' => '/opt/domjudge/domserver/webapp/src/Controller/API/PrintingController.php',
        'App\\Controller\\Jury\\PrintsController' => '/opt/domjudge/domserver/webapp/src/Controller/Jury/PrintsController.php',
        'App\\Entity\\Prints' => '/opt/domjudge/domserver/webapp/src/Entity/Prints.php',
        'App\\Entity\\PrintWithSourceCode' => '/opt/domjudge/domserver/webapp/src/Entity/PrintWithSourceCode.php',
        'App\\Service\\PrintingService' => '/opt/domjudge/domserver/webapp/src/Service/PrintingService.php',
```

After modifying an existing DOMjudge install, you may need to `rm -rf $INSTALLPATH/domserver/webapp/var/cache/prod`.

Such a piece of SQL should be executed first.

```sql
CREATE TABLE `print` (
  `printid` int(10) UNSIGNED NOT NULL COMMENT 'Unique ID',
  `time` decimal(32,9) UNSIGNED NOT NULL COMMENT 'Timestamp of the print request',
  `userid` int(4) UNSIGNED NOT NULL COMMENT 'User ID associated to this entry',
  `done` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Has been handed out yet?',
  `processed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Has been printed?',
  `sourcecode` longblob NOT NULL COMMENT 'Full source code',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Filename as submitted',
  `langid` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Language definition'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `print` ADD PRIMARY KEY (`printid`);

ALTER TABLE `print` MODIFY `printid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique ID', AUTO_INCREMENT=1;

ALTER TABLE `print` ADD CONSTRAINT `delete_with_user` FOREIGN KEY (`userid`) REFERENCES `user`(`userid`) ON DELETE CASCADE ON UPDATE NO ACTION;
```

