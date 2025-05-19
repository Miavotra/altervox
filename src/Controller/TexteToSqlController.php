<?php
// src/Controller/TexteToSqlController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse, ResponseHeaderBag};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

use App\Entity\Communes;
use App\Repository\CommunesRepository;

class TexteToSqlController extends AbstractController
{
    #[Route('/generateur/sql/process', name: 'generateur_sql_process', methods: ['POST'])]
    public function process(Request $request, CommunesRepository $communesRepository,SluggerInterface $slugger): Response
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
            DROP TABLES IF EXISTS entreprise;
            CREATE TABLE entreprise (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(255)  NULL,
                prenom VARCHAR(255)  NULL,
                societe VARCHAR(255)  NULL,
                adresse VARCHAR(255)  NULL,
                commune VARCHAR(255)  NULL,
                telephone VARCHAR(255)  NULL,
                horaires VARCHAR(100)  NULL,
                linkedin VARCHAR(100)  NULL,
                portable VARCHAR(100)  NULL,
                google_business VARCHAR(100)  NULL,
                rcs VARCHAR(100)  NULL,
                certification VARCHAR(100)  NULL,
                assurance VARCHAR(100)  NULL,
                logo VARCHAR(100)  NULL,
                info_sup VARCHAR(100)  NULL,
                url VARCHAR(100)  NULL,
                presentation TEXT  NULL,
                description_contact TEXT  NULL
            );
            DROP TABLES IF EXISTS urls;
            CREATE TABLE urls (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(255)  NULL,
                a_id int  NULL,
                c_id int  NULL
            );
            DROP TABLES IF EXISTS activites;
            CREATE TABLE activites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                menu VARCHAR(255)  NULL,
                titre VARCHAR(255)  NULL,
                presentation TEXT  NULL,
                meta_description TEXT  NULL
            );
            DROP TABLES IF EXISTS mots_cles_geographiques;
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
                page_principale INT  NULL
            );\n\n
        ";

        $cp_mcp = "";

        foreach ($lines as $line) {
            $line = trim($line);

            // Changement de section
            if (str_starts_with($line, '##')) {
                continue;
            }
            if (str_starts_with($line, '§/P/')) {
                $current_section = 'activite';
            }

            if (str_starts_with($line, '§/C/')) {
                $current_section = 'geo';
            }
            if (str_starts_with($line, 'Mot clé principal: ')) {
                if (preg_match('/^(.+?):\s*(.+)$/', $line, $matches)) {
                    $cp_mcp = $matches[2];
                }
            }

            // Traitement selon section
            switch ($current_section) {
                case 'entreprise':
                    if (preg_match('/^(.+?):\s*(.+)$/', $line, $matches)) {
                        $key = strtolower(str_replace(' ', '_', $matches[1]));
                        $entreprise[$key] = $matches[2] ? $matches[2] : "";
                    }
                    break;

                case 'activite':
                    if (str_starts_with($line, '§/P/')) {
                        if (!empty($current_activite)) {
                            $activites[] = $current_activite;
                            $current_activite = [];
                            $titre = explode('§', $line);
                            $menu = explode("/P/" , $titre[1]);
                            $current_activite['menu'] = $menu[1];
                            $current_activite['titre'] = $titre[2];
                        } else {
                            $titre = explode('§', $line);
                            $menu = explode("/P/" , $titre[1]);
                            $current_activite['menu'] = $menu[1];
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
        $sql .= "INSERT INTO entreprise (nom, prenom, societe, adresse, commune, telephone, horaires, url, linkedin, portable, google_business, rcs, certification, assurance, logo, info_sup, presentation, description_contact)\nVALUES (\n";
        $sql .= "'" . addslashes($entreprise['nom']) . "', ";
        $sql .= "'" . addslashes($entreprise['prénom']) . "', ";
        $sql .= "'" . addslashes($entreprise['nom_de_la_société']) . "', ";
        $sql .= "'" . addslashes($entreprise['adresse']) . "', ";
        $sql .= "'" . addslashes($entreprise['commune']) . "', ";
        $sql .= "'" . addslashes($entreprise['téléphone']) . "', ";
        $sql .= "'" . addslashes($entreprise['horaires']) . "', ";
        $sql .= "'" . addslashes($entreprise['linkedin']) . "', ";
        $sql .= "'" . addslashes($entreprise['url_souhaitée']) . "', ";
        $sql .= "'" . addslashes($entreprise['portable'] ?? "") . "', ";
        $sql .= "'" . addslashes($entreprise['google_business'] ??  "") . "', ";
        $sql .= "'" . addslashes($entreprise['rcs'] ?? "000 000 000") . "', ";
        $sql .= "'" . addslashes($entreprise['certification'] ?? "") . "', ";
        $sql .= "'" . addslashes($entreprise['assurance'] ?? "") . "', ";
        $sql .= "'" . addslashes($entreprise['logo'] ?? "") . "', ";
        $sql .= "'" . addslashes($entreprise['info_sup'] ??  "") . "', ";
        $sql .= "'" . addslashes($entreprise['présentation'] ?? '') . "', ";
        $sql .= "'" . addslashes($entreprise['description_contact'] ?? '') . "'\n);\n\n";

        // Table activites
        $a_id= 1;
        foreach ($activites as $act) {
            $sql .= "INSERT INTO activites (id, menu, titre, presentation, meta_description)\nVALUES (\n";
            $sql .= "" . $a_id . ", ";
            $sql .= "'" . addslashes($act['menu']) . "', ";
            $sql .= "'" . addslashes($act['titre']) . "', ";
            $sql .= "'" . addslashes($act['presentation']) . "', ";
            $sql .= "'" . addslashes($act['meta_description']) . "'\n);\n\n";
            $sql .= "INSERT INTO urls (slug, a_id, c_id) VALUES (";
            $sql .= "'" . $slugger->slug(addslashes($act['menu'])) . "', " . $a_id .", 0);\n";
            $a_id++;
        }
        $c_id= 1;
        // Table mots_cles_geographiques
        foreach ($geo_pages as $geo) {
            $comu = new Communes();
            $comu = $communesRepository->findOneBy(["code_insee" => strval($geo['identifiant_insee'])]);
            $isMC = $geo['nom'] == $cp_mcp ? 1 : 0;
            $mc = explode($comu->getNom(), addslashes($geo['mot_cle']));
            $sql .= "INSERT INTO mots_cles_geographiques (id, identifiant_insee, code_postal,mot_cle, nom, description_html, meta_description, latitude, longitude, page_principale)\nVALUES (\n";
            $sql .= "'" . $c_id . "', ";
            $sql .= "'" . addslashes($geo['identifiant_insee']) . "', ";
            $sql .= "'" . $comu->getCodePostal() . "', ";
            $sql .= "'" . $mc[0] . "', ";
            $sql .= "'" . addslashes($geo['nom']) . "', ";
            $sql .= "'" . addslashes(trim($geo['description_html'])) . "', ";
            $sql .= "'" . addslashes($geo['meta_description']) . "',";
            $sql .= "'" . $comu->getLatitude() . "',";
            $sql .= "'" . $comu->getLongitude() . "',";
            $sql .= "" . $isMC . "\n);\n";
            $sql .= "INSERT INTO urls (slug, a_id, c_id) VALUES (";
            $sql .= "'" . $slugger->slug(addslashes($geo['nom'])) . "', 0, " . $c_id .");\n";
            $c_id++;
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