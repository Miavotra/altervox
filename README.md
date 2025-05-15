# altervox


Prèrequis: 
  -php > 8.1
  -Symfony
  -Mysql

/************************************/

script pour créer la base de données:

CREATE DATABASE `altervox` COLLATE 'utf8mb3_general_ci';

/************************************/

script pour créer la table des communes pour la base local:

CREATE TABLE communes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    code_postal VARCHAR(10) NOT NULL,
  	latitude DOUBLE DEFAULT NULL,
  	longitude DOUBLE DEFAULT NULL ,
	code_insee VARCHAR(10) DEFAULT NULL
);

/************************************/

commande à exécuter dans le terminal pour charger les données de la table communes

php bin/console app:import:communes

/************************************/
