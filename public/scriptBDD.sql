
CREATE TABLE entreprise (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255)  NULL,
    prenom VARCHAR(255)  NULL,
    societe VARCHAR(255)  NULL,
    adresse VARCHAR(255)  NULL,
    commune VARCHAR(10)  NULL,
    telephone VARCHAR(255)  NULL,
    societe VARCHAR(10)  NULL,
    horaires VARCHAR(10)  NULL,
    url VARCHAR(10)  NULL,
    presentation TEXT  NULL,
    description_contact TEXT  NULL,
);
CREATE TABLE activites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255)  NULL,
    presentation TEXT  NULL,
    meta_description TEXT  NULL,
);
CREATE TABLE mots_cles_geographiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255)  NULL,
    mot_cle VARCHAR(255)  NULL,
    code_postal VARCHAR(255)  NULL,
    identifiant_insee VARCHAR(255)  NULL,
    description_html TEXT  NULL,
    meta_description TEXT  NULL,
    latitude VARCHAR(25)  NULL,
    longitude VARCHAR(25)  NULL,
    page_principale BOOLEAN  NULL
);
