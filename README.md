# Persisting Printing in DOMjudge database

Files are placed in `$INSTALLPATH/domserver/webapp/src/DOMJudgeBundle`.

Such a piece of instruction should be executed first.

```sql
CREATE TABLE `print` (
  `printid` int(10) UNSIGNED NOT NULL COMMENT 'Unique ID',
  `time` decimal(32,9) UNSIGNED NOT NULL COMMENT 'Timestamp of the print request',
  `userid` int(4) UNSIGNED NOT NULL COMMENT 'User ID associated to this entry',
  `done` tinyint(1) NOT NULL DEFAULT '0' COMMENT ' 	Has been handed out yet?',
  `sourcecode` longblob NOT NULL COMMENT 'Full source code',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT ' 	Filename as submitted',
  `langid` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Language definition'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Not finished yet.
