DROP TABLE IF EXISTS `wwwsqldesigner`;

CREATE TABLE `wwwsqldesigner` (
  `keyword` varchar(30) NOT NULL default '',
  `data` mediumtext,
  `dt` timestamp,
  PRIMARY KEY  (`keyword`)
);

CREATE  TABLE `relationship_fields` (
  `table1` VARCHAR(255) NOT NULL COMMENT '��� �������' ,
  `field1` VARCHAR(255) NOT NULL COMMENT '��� ����' ,
  `table2` VARCHAR(255) NOT NULL COMMENT '��� �������' ,
  `field2` VARCHAR(255) NOT NULL COMMENT '��� ����' ,
  UNIQUE INDEX `uniqindex` USING BTREE (`table1` ASC, `field1` ASC, `table2` ASC, `field2` ASC) )
COMMENT = '����� ����� ������ ������ ����';