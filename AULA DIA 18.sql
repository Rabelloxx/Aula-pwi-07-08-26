CREATE DATABASE cookies;
show tables;
CREATE TABLE usuarios
(
email varchar(100) NOT NULL,
senha VARCHAR (20) NOT NULL,
nome VARCHAR (100) NOT NULL,
cidade VARCHAR (50) NOT NULL,
estado CHAR (2) NOT NULL,
primary key(email)
);

INSERT INTO usuarios VALUES ('vini.mz@hotmail.com','teste','VINICIUS RABELLO','SÃO PAULO','SP');

