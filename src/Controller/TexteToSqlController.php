<?php
// src/Controller/TexteToSqlController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse, ResponseHeaderBag};
use Symfony\Component\Routing\Annotation\Route;

class TexteToSqlController extends AbstractController
{
    #[Route('/generateur/sql/process', name: 'generateur_sql_process', methods: ['POST'])]
    public function process(Request $request): Response
    {
        $content = $request->request->get('txt_content');
        $lines = explode("\n", $content);

        $entreprise = [];
        $activites = [];
        $geo_pages = [];

        $current_section = 'entreprise';
        $current_activite = [];
        $current_geo = [];
        // Générer le contenu SQL
        $sql = "
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
                menu VARCHAR(255)  NULL,
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
            );\n\n
        ";


        foreach ($lines as $line) {
            $line = trim($line);

            // Changement de section
            if (str_starts_with($line, '<[-- Pages activitées à créer --]>')) {
                $current_section = 'activite';
                continue;
            }

            if (str_starts_with($line, '<[-- Pages géographiques à créer --]>')) {
                $current_section = 'geo';
                continue;
            }

            // Traitement selon section
            switch ($current_section) {
                case 'entreprise':
                    if (preg_match('/^(.+?):\s*(.+)$/', $line, $matches)) {
                        $key = strtolower(str_replace(' ', '_', $matches[1]));
                        $entreprise[$key] = $matches[2];
                    }
                    break;

                case 'activite':
                    if (str_starts_with($line, '§/P/')) {
                        if (!empty($current_activite)) {
                            $activites[] = $current_activite;
                            $current_activite = [];
                            $current_activite['menu'] = $titre[1];
                            $current_activite['titre'] = $titre[2];
                        } else {
                            $titre = explode('§', $line);
                            $current_activite['menu'] = $titre[1];
                            $current_activite['titre'] = $titre[2];
                        }
                        continue;
                    }

                    if (empty($current_activite['meta_description'])) {
                        $current_activite['meta_description'] = $line;
                    } elseif (empty($current_activite['presentation'])) {
                        $current_activite['presentation'] = $line;
                    } else {
                        $current_activite['presentation'] .= $line;
                    }
                    break;

                case 'geo':
                    if (str_starts_with($line, '§/C/')) {
                        if (!empty($current_geo)) {
                            $geo_pages[] = $current_geo;
                            $current_geo = [];
                        }

                        $parts = explode('/', trim($line, '§/'));
                        $current_geo['identifiant_insee'] = $parts[1] ?? '';
                        $current_geo['mot_cle'] = $parts[2] ?? '';
                        $current_geo['nom'] = $parts[2] ?? '';
                        continue;
                    }

                    if (empty($current_geo['meta_description'])) {
                        $current_geo['meta_description'] = $line;
                    } else {
                        $current_geo['description_html'] = ($current_geo['description_html'] ?? '') . $line . "\n";
                    }
                    break;
            }
        }

        // Derniers éléments
        if (!empty($current_activite)) {
            $activites[] = $current_activite;
        }
        if (!empty($current_geo)) {
            $geo_pages[] = $current_geo;
        }


        // Table entreprise
        $sql .= "INSERT INTO entreprise (nom, prenom, societe, adresse, commune, telephone, horaires, url, presentation, description_contact)\nVALUES (\n";
        $sql .= "'" . addslashes($entreprise['nom']) . "', ";
        $sql .= "'" . addslashes($entreprise['prénom']) . "', ";
        $sql .= "'" . addslashes($entreprise['nom_de_la_société']) . "', ";
        $sql .= "'" . addslashes($entreprise['adresse']) . "', ";
        $sql .= "'" . addslashes($entreprise['commune']) . "', ";
        $sql .= "'" . addslashes($entreprise['téléphone']) . "', ";
        $sql .= "'" . addslashes($entreprise['horaires']) . "', ";
        $sql .= "'" . addslashes($entreprise['url_souhaitée']) . "', ";
        $sql .= "'" . addslashes($entreprise['presentation'] ?? '') . "', ";
        $sql .= "'" . addslashes($entreprise['description_contact'] ?? '') . "'\n);\n\n";

        // Table activites
        foreach ($activites as $act) {
            $sql .= "INSERT INTO activites (menu, titre, presentation, meta_description)\nVALUES (\n";
            $sql .= "'" . addslashes($act['menu']) . "', ";
            $sql .= "'" . addslashes($act['titre']) . "', ";
            $sql .= "'" . addslashes($act['presentation']) . "', ";
            $sql .= "'" . addslashes($act['meta_description']) . "'\n);\n\n";
        }

        // Table mots_cles_geographiques
        foreach ($geo_pages as $geo) {
            $coord = ["0","1"];
            $sql .= "INSERT INTO mots_cles_geographiques (identifiant_insee, mot_cle, nom, description_html, meta_description, latitude, longitude)\nVALUES (\n";
            $sql .= "'" . addslashes($geo['identifiant_insee']) . "', ";
            $sql .= "'" . addslashes($geo['mot_cle']) . "', ";
            $sql .= "'" . addslashes($geo['nom']) . "', ";
            $sql .= "'" . addslashes(trim($geo['description_html'])) . "', ";
            $sql .= "'" . addslashes($geo['meta_description']) . ",";
            $sql .= "'" . $coord[0] . ",";
            $sql .= "'" . $coord[1] . "'\n);\n\n";
        }

        // Nom du fichier SQL
        $filename = 'export_' . date('Ymd_His') . '.sql';

        // Création de la réponse téléchargée
        $response = new StreamedResponse(function () use ($sql) {
            echo $sql;
        });

        // $response->headers->set('Content-Type', 'application/sql');
        $response->headers->set('Content-Type', 'text/plain');

        $response->headers->set('Content-Disposition', ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . $filename . '"');

        return $response;


    }

    #[Route('/generateur/sql', name: 'generateur_sql')]
    public function index(): Response
    {
        return $this->render('generateur_sql/index.html.twig');
    }

}