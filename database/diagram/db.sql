-- MySQL Script generated by MySQL Workbench
-- Fri Feb 17 18:33:39 2023
-- Model: New Model    Version: 1.0
-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema db
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema db
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `db` DEFAULT CHARACTER SET utf8mb4 ;
USE `db` ;

-- -----------------------------------------------------
-- Table `db`.`family`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`family` ;

CREATE TABLE IF NOT EXISTS `db`.`family` (
  `idfamily` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(45) NOT NULL,
  `admin` INT NOT NULL,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end` DATE NULL,
  PRIMARY KEY (`idfamily`),
  INDEX `fk_family_user1_idx` (`admin` ASC) VISIBLE,
  CONSTRAINT `fk_family_user1`
    FOREIGN KEY (`admin`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`s3`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`s3` ;

CREATE TABLE IF NOT EXISTS `db`.`s3` (
  `idobject` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(511) NOT NULL,
  `owner` INT NOT NULL,
  `family` INT NULL,
  PRIMARY KEY (`idobject`),
  INDEX `fk_s3_user1_idx` (`owner` ASC) VISIBLE,
  INDEX `fk_s3_family1_idx` (`family` ASC) VISIBLE,
  CONSTRAINT `fk_s3_user1`
    FOREIGN KEY (`owner`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_s3_family1`
    FOREIGN KEY (`family`)
    REFERENCES `db`.`family` (`idfamily`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`user`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`user` ;

CREATE TABLE IF NOT EXISTS `db`.`user` (
  `iduser` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(255) NOT NULL DEFAULT '',
  `last_name` VARCHAR(255) NOT NULL DEFAULT '',
  `phone` VARCHAR(63) NOT NULL DEFAULT '',
  `birthdate` DATE NULL,
  `avatar` INT NULL,
  `theme` TINYINT NOT NULL DEFAULT 0,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`iduser`),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC) VISIBLE,
  INDEX `fk_user_s31_idx` (`avatar` ASC) VISIBLE,
  CONSTRAINT `fk_user_s31`
    FOREIGN KEY (`avatar`)
    REFERENCES `db`.`s3` (`idobject`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`subscription_type`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`subscription_type` ;

CREATE TABLE IF NOT EXISTS `db`.`subscription_type` (
  `idsubscription_type` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  `price` DECIMAL(5,2) NOT NULL,
  PRIMARY KEY (`idsubscription_type`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`family_has_member`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`family_has_member` ;

CREATE TABLE IF NOT EXISTS `db`.`family_has_member` (
  `idfamily` INT NOT NULL,
  `iduser` INT NOT NULL,
  `display_name` VARCHAR(255) NOT NULL,
  INDEX `fk_family_has_member_family_idx` (`idfamily` ASC) VISIBLE,
  INDEX `fk_family_has_member_user1_idx` (`iduser` ASC) VISIBLE,
  CONSTRAINT `fk_family_has_member_family`
    FOREIGN KEY (`idfamily`)
    REFERENCES `db`.`family` (`idfamily`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_family_has_member_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`address`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`address` ;

CREATE TABLE IF NOT EXISTS `db`.`address` (
  `idaddress` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `phone` VARCHAR(63) NOT NULL DEFAULT '',
  `field1` VARCHAR(63) NOT NULL DEFAULT '',
  `field2` VARCHAR(63) NOT NULL DEFAULT '',
  `field3` VARCHAR(63) NOT NULL DEFAULT '',
  `postal` VARCHAR(63) NOT NULL DEFAULT '',
  `city` VARCHAR(63) NOT NULL DEFAULT '',
  `state` VARCHAR(63) NOT NULL DEFAULT '',
  `country` VARCHAR(63) NOT NULL DEFAULT '',
  PRIMARY KEY (`idaddress`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`recipient`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`recipient` ;

CREATE TABLE IF NOT EXISTS `db`.`recipient` (
  `idrecipient` INT NOT NULL AUTO_INCREMENT,
  `idfamily` INT NOT NULL,
  `iduser` INT NULL,
  `display_name` VARCHAR(63) NOT NULL DEFAULT '',
  `avatar` INT NULL,
  `birthdate` DATE NOT NULL,
  `idaddress` INT NOT NULL,
  `referent` INT NOT NULL,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `fk_family_has_recipient_family1_idx` (`idfamily` ASC) VISIBLE,
  INDEX `fk_family_has_recipient_user1_idx` (`iduser` ASC) VISIBLE,
  PRIMARY KEY (`idrecipient`),
  INDEX `fk_recipient_address1_idx` (`idaddress` ASC) VISIBLE,
  INDEX `fk_recipient_user1_idx` (`referent` ASC) VISIBLE,
  INDEX `fk_recipient_s31_idx` (`avatar` ASC) VISIBLE,
  CONSTRAINT `fk_family_has_recipient_family1`
    FOREIGN KEY (`idfamily`)
    REFERENCES `db`.`family` (`idfamily`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_family_has_recipient_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_recipient_address1`
    FOREIGN KEY (`idaddress`)
    REFERENCES `db`.`address` (`idaddress`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_recipient_user1`
    FOREIGN KEY (`referent`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_recipient_s31`
    FOREIGN KEY (`avatar`)
    REFERENCES `db`.`s3` (`idobject`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`subscription`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`subscription` ;

CREATE TABLE IF NOT EXISTS `db`.`subscription` (
  `idsubscription` INT NOT NULL AUTO_INCREMENT,
  `idrecipient` INT NOT NULL,
  `idsubscription_type` INT NOT NULL,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idsubscription`),
  INDEX `fk_subscription_subscription_type1_idx` (`idsubscription_type` ASC) VISIBLE,
  INDEX `fk_subscription_recipient1_idx` (`idrecipient` ASC) VISIBLE,
  CONSTRAINT `fk_subscription_subscription_type1`
    FOREIGN KEY (`idsubscription_type`)
    REFERENCES `db`.`subscription_type` (`idsubscription_type`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `fk_subscription_recipient1`
    FOREIGN KEY (`idrecipient`)
    REFERENCES `db`.`recipient` (`idrecipient`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`user_has_payment`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`user_has_payment` ;

CREATE TABLE IF NOT EXISTS `db`.`user_has_payment` (
  `iduser` INT NOT NULL,
  `idsubscription` INT NULL,
  `date` DATE NOT NULL,
  `amount` DECIMAL(5,2) NOT NULL,
  `paid` TINYINT NOT NULL DEFAULT 0,
  INDEX `fk_user_has_payment_user1_idx` (`iduser` ASC) VISIBLE,
  INDEX `fk_user_has_payment_subscription1_idx` (`idsubscription` ASC) VISIBLE,
  CONSTRAINT `fk_user_has_payment_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_user_has_payment_subscription1`
    FOREIGN KEY (`idsubscription`)
    REFERENCES `db`.`subscription` (`idsubscription`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`publication_type`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`publication_type` ;

CREATE TABLE IF NOT EXISTS `db`.`publication_type` (
  `idpublication_type` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(63) NOT NULL,
  PRIMARY KEY (`idpublication_type`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`layout`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`layout` ;

CREATE TABLE IF NOT EXISTS `db`.`layout` (
  `idlayout` INT NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(45) NOT NULL DEFAULT 'a',
  `description` VARCHAR(45) NOT NULL DEFAULT '',
  `quantity` TINYINT NOT NULL DEFAULT 1,
  `orientation` TINYINT NOT NULL DEFAULT 0,
  `full_page` TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`idlayout`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`background`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`background` ;

CREATE TABLE IF NOT EXISTS `db`.`background` (
  `idbackground` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(63) NOT NULL DEFAULT '',
  `idobject` INT NOT NULL,
  `full_page` TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`idbackground`),
  INDEX `fk_background_s31_idx` (`idobject` ASC) VISIBLE,
  CONSTRAINT `fk_background_s31`
    FOREIGN KEY (`idobject`)
    REFERENCES `db`.`s3` (`idobject`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`publication`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`publication` ;

CREATE TABLE IF NOT EXISTS `db`.`publication` (
  `idpublication` INT NOT NULL AUTO_INCREMENT,
  `author` INT NOT NULL,
  `idfamily` INT NOT NULL,
  `type` INT NOT NULL DEFAULT 1,
  `idlayout` INT NOT NULL DEFAULT 1,
  `idbackground` INT NULL,
  `description` VARCHAR(511) NOT NULL DEFAULT '',
  `private` TINYINT NOT NULL DEFAULT 0,
  `created` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `modified` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`idpublication`),
  INDEX `fk_publication_publication_type1_idx` (`type` ASC) VISIBLE,
  INDEX `fk_publication_user1_idx` (`author` ASC) VISIBLE,
  INDEX `fk_publication_family1_idx` (`idfamily` ASC) VISIBLE,
  INDEX `fk_publication_layout1_idx` (`idlayout` ASC) VISIBLE,
  INDEX `fk_publication_background1_idx` (`idbackground` ASC) VISIBLE,
  CONSTRAINT `fk_publication_publication_type1`
    FOREIGN KEY (`type`)
    REFERENCES `db`.`publication_type` (`idpublication_type`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `fk_publication_user1`
    FOREIGN KEY (`author`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_publication_family1`
    FOREIGN KEY (`idfamily`)
    REFERENCES `db`.`family` (`idfamily`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_publication_layout1`
    FOREIGN KEY (`idlayout`)
    REFERENCES `db`.`layout` (`idlayout`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `fk_publication_background1`
    FOREIGN KEY (`idbackground`)
    REFERENCES `db`.`background` (`idbackground`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`movie`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`movie` ;

CREATE TABLE IF NOT EXISTS `db`.`movie` (
  `idmovie` INT NOT NULL,
  `idpublication` INT NOT NULL,
  `idobject` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`idmovie`),
  INDEX `fk_movie_publication1_idx` (`idpublication` ASC) VISIBLE,
  INDEX `fk_movie_s31_idx` (`idobject` ASC) VISIBLE,
  CONSTRAINT `fk_movie_publication1`
    FOREIGN KEY (`idpublication`)
    REFERENCES `db`.`publication` (`idpublication`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_movie_s31`
    FOREIGN KEY (`idobject`)
    REFERENCES `db`.`s3` (`idobject`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`publication_has_picture`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`publication_has_picture` ;

CREATE TABLE IF NOT EXISTS `db`.`publication_has_picture` (
  `idpublication` INT NOT NULL,
  `idobject` INT NOT NULL,
  `place` TINYINT NOT NULL DEFAULT 0,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  INDEX `fk_publication_has_picture_publication1_idx` (`idpublication` ASC) VISIBLE,
  INDEX `fk_publication_has_picture_s31_idx` (`idobject` ASC) VISIBLE,
  CONSTRAINT `fk_publication_has_picture_publication1`
    FOREIGN KEY (`idpublication`)
    REFERENCES `db`.`publication` (`idpublication`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_publication_has_picture_s31`
    FOREIGN KEY (`idobject`)
    REFERENCES `db`.`s3` (`idobject`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`text`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`text` ;

CREATE TABLE IF NOT EXISTS `db`.`text` (
  `idtext` INT NOT NULL AUTO_INCREMENT,
  `idpublication` INT NOT NULL,
  `content` TEXT NOT NULL,
  PRIMARY KEY (`idtext`),
  INDEX `fk_text_publication1_idx` (`idpublication` ASC) VISIBLE,
  CONSTRAINT `fk_text_publication1`
    FOREIGN KEY (`idpublication`)
    REFERENCES `db`.`publication` (`idpublication`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`gazette`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`gazette` ;

CREATE TABLE IF NOT EXISTS `db`.`gazette` (
  `idgazette` INT NOT NULL AUTO_INCREMENT,
  `idobject` INT NOT NULL,
  `date` DATE NOT NULL,
  `idrecipient` INT NOT NULL,
  PRIMARY KEY (`idgazette`),
  INDEX `fk_gazette_recipient1_idx` (`idrecipient` ASC) VISIBLE,
  INDEX `fk_gazette_s31_idx` (`idobject` ASC) VISIBLE,
  CONSTRAINT `fk_gazette_recipient1`
    FOREIGN KEY (`idrecipient`)
    REFERENCES `db`.`recipient` (`idrecipient`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_gazette_s31`
    FOREIGN KEY (`idobject`)
    REFERENCES `db`.`s3` (`idobject`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`publication_has_like`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`publication_has_like` ;

CREATE TABLE IF NOT EXISTS `db`.`publication_has_like` (
  `idpublication` INT NOT NULL,
  `iduser` INT NOT NULL,
  INDEX `fk_publication_has_like_publication1_idx` (`idpublication` ASC) VISIBLE,
  INDEX `fk_publication_has_like_user1_idx` (`iduser` ASC) VISIBLE,
  CONSTRAINT `fk_publication_has_like_publication1`
    FOREIGN KEY (`idpublication`)
    REFERENCES `db`.`publication` (`idpublication`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_publication_has_like_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`comment`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`comment` ;

CREATE TABLE IF NOT EXISTS `db`.`comment` (
  `idcomment` INT NOT NULL AUTO_INCREMENT,
  `iduser` INT NOT NULL,
  `content` VARCHAR(255) NOT NULL,
  `created` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `modified` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`idcomment`),
  INDEX `fk_comment_user1_idx` (`iduser` ASC) VISIBLE,
  CONSTRAINT `fk_comment_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`publication_has_comment`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`publication_has_comment` ;

CREATE TABLE IF NOT EXISTS `db`.`publication_has_comment` (
  `idpublication` INT NOT NULL,
  `idcomment` INT NOT NULL,
  INDEX `fk_publication_has_comment_publication1_idx` (`idpublication` ASC) VISIBLE,
  INDEX `fk_publication_has_comment_comment1_idx` (`idcomment` ASC) VISIBLE,
  CONSTRAINT `fk_publication_has_comment_publication1`
    FOREIGN KEY (`idpublication`)
    REFERENCES `db`.`publication` (`idpublication`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_publication_has_comment_comment1`
    FOREIGN KEY (`idcomment`)
    REFERENCES `db`.`comment` (`idcomment`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`comment_has_like`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`comment_has_like` ;

CREATE TABLE IF NOT EXISTS `db`.`comment_has_like` (
  `idcomment` INT NOT NULL,
  `iduser` INT NOT NULL,
  INDEX `fk_comment_has_like_comment1_idx` (`idcomment` ASC) VISIBLE,
  INDEX `fk_comment_has_like_user1_idx` (`iduser` ASC) VISIBLE,
  CONSTRAINT `fk_comment_has_like_comment1`
    FOREIGN KEY (`idcomment`)
    REFERENCES `db`.`comment` (`idcomment`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_comment_has_like_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`recurring_payment`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`recurring_payment` ;

CREATE TABLE IF NOT EXISTS `db`.`recurring_payment` (
  `iduser` INT NOT NULL,
  `idsubscription` INT NOT NULL,
  `amount` DECIMAL(5,2) NOT NULL,
  `created` TIMESTAMP NOT NULL,
  INDEX `fk_recurring_payment_user1_idx` (`iduser` ASC) VISIBLE,
  INDEX `fk_recurring_payment_subscription1_idx` (`idsubscription` ASC) VISIBLE,
  CONSTRAINT `fk_recurring_payment_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_recurring_payment_subscription1`
    FOREIGN KEY (`idsubscription`)
    REFERENCES `db`.`subscription` (`idsubscription`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`google_key`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`google_key` ;

CREATE TABLE IF NOT EXISTS `db`.`google_key` (
  `idgoogle_key` BINARY(40) NOT NULL,
  `content` VARCHAR(2047) NOT NULL,
  `expiration` TIMESTAMP NOT NULL,
  PRIMARY KEY (`idgoogle_key`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`firebase_has_user`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`firebase_has_user` ;

CREATE TABLE IF NOT EXISTS `db`.`firebase_has_user` (
  `idfirebase_user` INT NOT NULL AUTO_INCREMENT,
  `iduser` INT NOT NULL,
  `uidfirebase` VARCHAR(63) NOT NULL,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL DEFAULT '',
  INDEX `fk_firebase_uid_user1_idx` (`iduser` ASC) VISIBLE,
  PRIMARY KEY (`idfirebase_user`),
  CONSTRAINT `fk_firebase_uid_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`default_family`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`default_family` ;

CREATE TABLE IF NOT EXISTS `db`.`default_family` (
  `iduser` INT NOT NULL,
  `idfamily` INT NOT NULL,
  INDEX `fk_default_family_user1_idx` (`iduser` ASC) VISIBLE,
  INDEX `fk_default_family_family1_idx` (`idfamily` ASC) VISIBLE,
  PRIMARY KEY (`iduser`),
  CONSTRAINT `fk_default_family_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_default_family_family1`
    FOREIGN KEY (`idfamily`)
    REFERENCES `db`.`family` (`idfamily`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`family_invitation`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`family_invitation` ;

CREATE TABLE IF NOT EXISTS `db`.`family_invitation` (
  `idfamily` INT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `invitee` INT NULL,
  `inviter` INT NOT NULL,
  `approved` TINYINT NOT NULL DEFAULT 0,
  `accepted` TINYINT NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `fk_family_request_family1_idx` (`idfamily` ASC) VISIBLE,
  INDEX `fk_family_invitation_user1_idx` (`inviter` ASC) VISIBLE,
  INDEX `fk_family_invitation_user2_idx` (`invitee` ASC) VISIBLE,
  CONSTRAINT `fk_family_request_family1`
    FOREIGN KEY (`idfamily`)
    REFERENCES `db`.`family` (`idfamily`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_family_invitation_user1`
    FOREIGN KEY (`inviter`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_family_invitation_user2`
    FOREIGN KEY (`invitee`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`family_request`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`family_request` ;

CREATE TABLE IF NOT EXISTS `db`.`family_request` (
  `idfamily` INT NOT NULL,
  `iduser` INT NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `fk_family_request_family2_idx` (`idfamily` ASC) VISIBLE,
  INDEX `fk_family_request_user1_idx` (`iduser` ASC) VISIBLE,
  CONSTRAINT `fk_family_request_family2`
    FOREIGN KEY (`idfamily`)
    REFERENCES `db`.`family` (`idfamily`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_family_request_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`recipient_has_publication`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`recipient_has_publication` ;

CREATE TABLE IF NOT EXISTS `db`.`recipient_has_publication` (
  `idrecipient` INT NOT NULL,
  `idpublication` INT NOT NULL,
  INDEX `fk_recipient_has_publication_recipient1_idx` (`idrecipient` ASC) VISIBLE,
  INDEX `fk_recipient_has_publication_publication1_idx` (`idpublication` ASC) VISIBLE,
  CONSTRAINT `fk_recipient_has_publication_recipient1`
    FOREIGN KEY (`idrecipient`)
    REFERENCES `db`.`recipient` (`idrecipient`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_recipient_has_publication_publication1`
    FOREIGN KEY (`idpublication`)
    REFERENCES `db`.`publication` (`idpublication`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- -----------------------------------------------------
-- Data for table `db`.`subscription_type`
-- -----------------------------------------------------
START TRANSACTION;
USE `db`;
INSERT INTO `db`.`subscription_type` (`idsubscription_type`, `name`, `price`) VALUES (1, 'Mensuel', 5.99);

COMMIT;


-- -----------------------------------------------------
-- Data for table `db`.`publication_type`
-- -----------------------------------------------------
START TRANSACTION;
USE `db`;
INSERT INTO `db`.`publication_type` (`idpublication_type`, `name`) VALUES (1, 'journal');
INSERT INTO `db`.`publication_type` (`idpublication_type`, `name`) VALUES (2, 'pictures');

COMMIT;


-- -----------------------------------------------------
-- Data for table `db`.`layout`
-- -----------------------------------------------------
START TRANSACTION;
USE `db`;
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (2, 'hjl2a', DEFAULT, 2, 1, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (3, 'hjl3a', DEFAULT, 3, 1, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (4, 'hjp1a', DEFAULT, 1, 2, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (5, 'hjp2a', DEFAULT, 2, 2, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (6, 'hjp3a', DEFAULT, 3, 2, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (1, 'hjl1a', DEFAULT, 1, 1, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (7, 'hpl1a', DEFAULT, 1, 1, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (8, 'hpl2a', DEFAULT, 2, 1, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (9, 'hpl2b', DEFAULT, 2, 1, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (10, 'hpl2c', DEFAULT, 2, 1, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (11, 'hpl3a', DEFAULT, 3, 1, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (12, 'hpl3b', DEFAULT, 3, 1, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (13, 'hpl3c', DEFAULT, 3, 1, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (14, 'hpp1a', DEFAULT, 1, 2, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (15, 'hpp2a', DEFAULT, 2, 2, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (16, 'hpp2b', DEFAULT, 2, 2, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (17, 'hpp3a', DEFAULT, 3, 2, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (18, 'hpp3b', DEFAULT, 3, 2, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (19, 'hpp3c', DEFAULT, 3, 2, 0);
INSERT INTO `db`.`layout` (`idlayout`, `identifier`, `description`, `quantity`, `orientation`, `full_page`) VALUES (20, 'hpp3d', DEFAULT, 3, 2, 0);

COMMIT;

