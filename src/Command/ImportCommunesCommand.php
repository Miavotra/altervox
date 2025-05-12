<?php
// src/Command/ImportCommunesCommand.php

namespace App\Command;

use Doctrine\DBAL\Connection;
use League\Csv\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'app:import:communes')]
class ImportCommunesCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '512M'); // ou plus, selon besoin
        // Localiser le fichier CSV
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/../../data')->name('laposte_hexasmal.csv');

        if (!$finder->hasResults()) {
            $output->writeln('<error>Fichier CSV introuvable dans le dossier data.</error>');
            return Command::FAILURE;
        }

        $file = null;
        foreach ($finder as $foundFile) {
            $file = $foundFile->getRealPath();
            break; // On ne prend que le premier fichier trouvé
        }

        if (!$file) {
            $output->writeln(messages: '<error>Impossible de localiser le fichier CSV.</error>');
            return Command::FAILURE;
        }

        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();
        $inserted = 0;
        
        $stmt = $this->connection->prepare(
            'INSERT INTO communes (nom, code_postal, code_insee, latitude, longitude)
             VALUES (:nom, :code_postal, :code_insee, :latitude, :longitude)'
        );
        $this->connection->beginTransaction();
        try {
            foreach ($csv->getRecords() as $record) {
                $nom = ucwords(strtolower($record['nom_de_la_commune']));
                $codePostal = $record['code_postal'];
                $codeInsee = $record['code_commune_insee'];
                $latitude = $record['latitude'];
                $longitude = $record['longitude'];
        
                $stmt->executeStatement([
                    'nom' => $nom,
                    'code_postal' => $codePostal,
                    'code_insee' => $codeInsee,
                    'latitude' => $latitude ? $latitude : "0",
                    'longitude' => $longitude ? $longitude : "0",
                ]);
            }
        
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $output->writeln('<error>Erreur : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        
        
        $output->writeln("<info>$inserted communes importées avec succès.</info>");

        return Command::SUCCESS;
    }
}
