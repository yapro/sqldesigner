DROP TABLE IF EXISTS `wwwsqldesigner`;

CREATE TABLE `wwwsqldesigner` (
  `keyword` varchar(30) NOT NULL default '',
  `data` mediumtext,
  `dt` timestamp,
  PRIMARY KEY  (`keyword`)
);

CREATE  TABLE `sqldesigner` (
  `table1` VARCHAR(255) NOT NULL COMMENT 'имя таблицы' ,
  `field1` VARCHAR(255) NOT NULL COMMENT 'имя поля' ,
  `table2` VARCHAR(255) NOT NULL COMMENT 'имя таблицы' ,
  `field2` VARCHAR(255) NOT NULL COMMENT 'имя поля' ,
  UNIQUE INDEX `uniqindex` USING BTREE (`table1` ASC, `field1` ASC, `table2` ASC, `field2` ASC) )
COMMENT = 'Связи полей таблиц данной базы';