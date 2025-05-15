<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class ExportController extends AbstractController
{
    #[Route('/formulaire', name: 'form_page')]
    public function showForm(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render('export/form.html.twig');
    }

    #[Route('/export-txt', name: 'export_txt', methods: ['POST'])]
    public function exportToTxt(Request $request): StreamedResponse
    {
        // Récupérer les champs simples
        $fields = [
            'Mot clé principal' => $request->request->get('is_mcp').PHP_EOL . PHP_EOL . PHP_EOL,
            'Nom' => $request->request->get('nom'),
            'Prénom' => $request->request->get('prenom'),
            'Nom de la société' => $request->request->get('societe'),
            'Adresse' => $request->request->get('adresse'),
            'Code postal' => $request->request->get('code_postal'),
            'Commune' => $request->request->get('commune'),
            'Adresse Mail' => $request->request->get('email'),
            'Téléphone' => $request->request->get('telephone'),
            'Horaires' => $request->request->get('horaires'),
            'URL souhaitée' => $request->request->get('url').PHP_EOL,
            'Mot clé' => $request->request->get('motCleLists').PHP_EOL,
            'Présentation' => "\nTEXT".PHP_EOL .PHP_EOL,
            'Description contact' => "\nTEXT".PHP_EOL.PHP_EOL.PHP_EOL,
        ];
        
        // Traiter les activités
        $activites = $request->request->all('activites');
        $activitesText = PHP_EOL .  count($activites)  . " Pages activitées" ;
        $activitesText .= PHP_EOL . "<[-- Pages activitées à créer --]>" . PHP_EOL;
        if ($activites) {
            foreach ($activites as $act) {
                $titre = $act['titre'] ?? '';
                if ($titre) {
                    $activitesText .= "§/P/" . $titre . "§TITRE\nMETADESCRIPTION\nTEXTE".PHP_EOL . PHP_EOL;
                }
            }
        }
        $fields['Pages activitées'] = $activitesText;

        // Traiter les pages géographiques
        $pagesGeo = $request->request->all('pages_geo');
        $pagesGeoText = PHP_EOL . count($pagesGeo['communes']) . " Pages géographiques" ;
        $pagesGeoText .= PHP_EOL . "<[-- Pages géographiques à créer --]>" . PHP_EOL;
        if ($pagesGeo) {
            foreach ($pagesGeo['communes'] as $key => $page) {
                $communes = str_replace(['(',')'],'', $pagesGeo['communes'][$key]) ?? '';
                $codeinsee = $pagesGeo['codeinsee'][$key] != "" ? $pagesGeo['codeinsee'][$key] :'0';
                $motcle = $pagesGeo['motcles'][$key] ?? '';
                if($fields['Mot clé principal'] == $codeinsee) 
                    $fields['Mot clé principal'] = $motcle . " " . $communes;
                if ($communes || $motcle) {
                    $pagesGeoText .= "§/C/" . $codeinsee . "/" . $motcle . " " . $communes . "\nMETADESCRIPTION \nTEXTE".PHP_EOL . PHP_EOL;
                }
            }
        }
        $fields['Pages géographiques'] = $pagesGeoText;

        // Générer la réponse en téléchargement
        $response = new StreamedResponse(function () use ($fields) {
            $handle = fopen('php://output', 'w');
            foreach ($fields as $label => $value) { 
                if($label == 'Pages activitées' || $label == 'Pages géographiques')
                    fwrite($handle, "$value\n"); 
                else 
                    fwrite($handle, "$label: $value\n"); 
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $request->request->get('societe') . '-ITW.txt"');

        return $response;
    }
    #[Route('/autocomplete-communes', name: 'autocomplete_communes')]
    public function autocomplete(Request $request, Connection $connection): JsonResponse
    {
        $term = $request->query->get('term', '');

        $results = $connection->fetchAllAssociative(
            'SELECT nom, code_postal,code_insee FROM communes WHERE nom LIKE :term OR code_postal LIKE :term LIMIT 15',
            ['term' => "%$term%"]
        );

        $suggestions = array_map(fn($row) => [
            'label' => "{$row['nom']} ({$row['code_postal']})",
            'value' => "{$row['nom']} ({$row['code_postal']})",
            'code_insee' => "{$row['code_insee']}",
        ], $results);

        return new JsonResponse($suggestions);
    }
}