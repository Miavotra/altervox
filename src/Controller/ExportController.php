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
            'Nom' => $request->request->get('nom'),
            'Prénom' => $request->request->get('prenom'),
            'Nom de la société' => $request->request->get('societe'),
            'Adresse' => $request->request->get('adresse'),
            'Code postal' => $request->request->get('code_postal'),
            'Commune' => $request->request->get('commune'),
            'Adresse Mail' => $request->request->get('email'),
            'Téléphone' => $request->request->get('telephone'),
            'Horaires' => $request->request->get('horaires'),
            'URL souhaitée' => $request->request->get('url'),
        ];

        // Traiter les activités
        $activites = $request->request->all('activites');
        $activitesText = "";
        if ($activites) {
            foreach ($activites as $act) {
                $titre = $act['titre'] ?? '';
                if ($titre) {
                    $activitesText .= "§/P/$titre\n";
                }
            }
        }
        $fields['Pages activiées'] = $activitesText;

        // Traiter les pages géographiques
        $pagesGeo = $request->request->all('pages_geo');
        $pagesGeoText = "";
        if ($pagesGeo) {
            foreach ($pagesGeo as $page) {
                $communes = $page['communes'] ?? '';
                $codeinsee = $page['codeinsee'] ?? '';
                $motcle = $page['motcle'] ?? '';
                if ($communes || $motcle) {
                    $pagesGeoText .= "§/C/$codeinsee/$motcle $communes\n";
                }
            }
        }
        $fields['Pages géographiques'] = $pagesGeoText;

        // Générer la réponse en téléchargement
        $response = new StreamedResponse(function () use ($fields) {
            $handle = fopen('php://output', 'w');
            foreach ($fields as $label => $value) {
                if($label =="Pages activiées" || $label == "Pages activiées"){
                    fwrite($handle, PHP_EOL);
                }
                fwrite($handle, "$label: $value\n");
                if($label =="Pages activiées" || $label == "Pages activiées"){
                    fwrite($handle, PHP_EOL);
                }
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="infos_societe.txt"');

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
