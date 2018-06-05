--
-- extension AMIVAuthentication users SQL schema
--
CREATE TABLE /*_*/amiv_users (
  `external_id` VARCHAR(255) NOT NULL,
  `internal_id` INT(10) NOT NULL,
  PRIMARY KEY (`external_id`),
  UNIQUE INDEX `external_id_UNIQUE` (`external_id` ASC),
  UNIQUE INDEX `internal_id_UNIQUE` (`internal_id` ASC));
  