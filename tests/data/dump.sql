USE miitest;

DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS articles;

CREATE TABLE `items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `some` VARCHAR(255) NULL,
  `created` INT(11) NOT NULL,
  `updated` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `articles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL DEFAULT "",
  `data` VARCHAR(500) NOT NULL,
  `flag` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted` INT(11) NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
