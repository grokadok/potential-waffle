-- MySQL Script generated by MySQL Workbench
-- Tue Nov 22 13:37:58 2022
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
-- Table `db`.`user`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`user` ;

CREATE TABLE IF NOT EXISTS `db`.`user` (
  `iduser` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(254) NOT NULL,
  `first_name` VARCHAR(254) NOT NULL DEFAULT '',
  `last_name` VARCHAR(254) NOT NULL DEFAULT '',
  `phone` VARCHAR(45) NOT NULL DEFAULT '',
  `birthday` DATE NULL,
  `theme` TINYINT NOT NULL DEFAULT 0,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`iduser`),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC) VISIBLE)
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
-- Table `db`.`family`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`family` ;

CREATE TABLE IF NOT EXISTS `db`.`family` (
  `idfamily` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `admin` INT NOT NULL,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idfamily`),
  INDEX `fk_family_user1_idx` (`admin` ASC) VISIBLE,
  CONSTRAINT `fk_family_user1`
    FOREIGN KEY (`admin`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`family_has_member`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`family_has_member` ;

CREATE TABLE IF NOT EXISTS `db`.`family_has_member` (
  `idfamily` INT NOT NULL,
  `iduser` INT NOT NULL,
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
-- Table `db`.`recipient`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`recipient` ;

CREATE TABLE IF NOT EXISTS `db`.`recipient` (
  `idrecipient` INT NOT NULL AUTO_INCREMENT,
  `idfamily` INT NOT NULL,
  `iduser` INT NOT NULL,
  INDEX `fk_family_has_recipient_family1_idx` (`idfamily` ASC) VISIBLE,
  INDEX `fk_family_has_recipient_user1_idx` (`iduser` ASC) VISIBLE,
  PRIMARY KEY (`idrecipient`),
  CONSTRAINT `fk_family_has_recipient_family1`
    FOREIGN KEY (`idfamily`)
    REFERENCES `db`.`family` (`idfamily`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_family_has_recipient_user1`
    FOREIGN KEY (`iduser`)
    REFERENCES `db`.`user` (`iduser`)
    ON DELETE CASCADE
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
  `start` DATE NOT NULL,
  `end` DATE NULL,
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
  `idsubscription` INT NOT NULL,
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
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`publication_type`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`publication_type` ;

CREATE TABLE IF NOT EXISTS `db`.`publication_type` (
  `idpublication_type` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`idpublication_type`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`publication`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`publication` ;

CREATE TABLE IF NOT EXISTS `db`.`publication` (
  `idpublication` INT NOT NULL AUTO_INCREMENT,
  `idpublication_type` INT NOT NULL,
  `author` INT NOT NULL,
  `idfamily` INT NOT NULL,
  `description` VARCHAR(511) NOT NULL DEFAULT '',
  `created` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `modified` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`idpublication`),
  INDEX `fk_publication_publication_type1_idx` (`idpublication_type` ASC) VISIBLE,
  INDEX `fk_publication_user1_idx` (`author` ASC) VISIBLE,
  INDEX `fk_publication_family1_idx` (`idfamily` ASC) VISIBLE,
  CONSTRAINT `fk_publication_publication_type1`
    FOREIGN KEY (`idpublication_type`)
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
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`picture`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`picture` ;

CREATE TABLE IF NOT EXISTS `db`.`picture` (
  `idpicture` INT NOT NULL AUTO_INCREMENT,
  `uri` VARCHAR(511) NOT NULL,
  `title` VARCHAR(254) NOT NULL DEFAULT '',
  PRIMARY KEY (`idpicture`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`movie`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`movie` ;

CREATE TABLE IF NOT EXISTS `db`.`movie` (
  `idmovie` INT NOT NULL,
  `idpublication` INT NOT NULL,
  `uri` VARCHAR(511) NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`idmovie`),
  INDEX `fk_movie_publication1_idx` (`idpublication` ASC) VISIBLE,
  CONSTRAINT `fk_movie_publication1`
    FOREIGN KEY (`idpublication`)
    REFERENCES `db`.`publication` (`idpublication`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db`.`publication_has_picture`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `db`.`publication_has_picture` ;

CREATE TABLE IF NOT EXISTS `db`.`publication_has_picture` (
  `idpublication` INT NOT NULL,
  `idpicture` INT NOT NULL,
  INDEX `fk_publication_has_picture_publication1_idx` (`idpublication` ASC) VISIBLE,
  INDEX `fk_publication_has_picture_picture1_idx` (`idpicture` ASC) VISIBLE,
  CONSTRAINT `fk_publication_has_picture_publication1`
    FOREIGN KEY (`idpublication`)
    REFERENCES `db`.`publication` (`idpublication`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_publication_has_picture_picture1`
    FOREIGN KEY (`idpicture`)
    REFERENCES `db`.`picture` (`idpicture`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
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
  `idfamily` INT NOT NULL,
  `uri` VARCHAR(500) NOT NULL,
  `date` DATE NOT NULL,
  PRIMARY KEY (`idgazette`),
  INDEX `fk_gazette_family1_idx` (`idfamily` ASC) VISIBLE,
  CONSTRAINT `fk_gazette_family1`
    FOREIGN KEY (`idfamily`)
    REFERENCES `db`.`family` (`idfamily`)
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
  `start` TIMESTAMP NOT NULL,
  `end` TIMESTAMP NULL,
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
  `iduser` INT NOT NULL,
  `uidfirebase` VARCHAR(63) NOT NULL,
  `name` VARCHAR(254) NOT NULL DEFAULT '',
  `email` VARCHAR(254) NOT NULL DEFAULT '',
  PRIMARY KEY (`iduser`),
  INDEX `fk_firebase_uid_user1_idx` (`iduser` ASC) VISIBLE,
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


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
