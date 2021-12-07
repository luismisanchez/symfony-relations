<?php

namespace App\Command;

use App\Entity\Actor;
use App\Entity\Director;
use App\Entity\Film;
use App\Repository\ActorsRepository;
use App\Repository\DirectorsRepository;
use App\Repository\FilmsRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[AsCommand(
    name: 'app:import-films',
    description: 'Import films from csv to the IMDB entities database',
)]
class ImportFilmsCommand extends Command
{
    private Filesystem $filesystem;
    private array $headers;
    private EntityManagerInterface $entityManager;
    private ProgressBar $progressBar;
    private ActorsRepository $actorsRepository;
    private DirectorsRepository $directorsRepository;
    private FilmsRepository $filmsRepository;
    private Connection $connection;
    private Serializer $decoder;
    private SymfonyStyle $io;
    private InputInterface $input;
    private OutputInterface $output;

    public function __construct(EntityManagerInterface $entityManager)
    {
        // allow more memory for dev
        ini_set('memory_limit', '256M');

        // Filesystem utils and Entity manager
        $this->filesystem = new Filesystem();
        $this->entityManager = $entityManager;

        // Disable sql logger to free up some ram
        $this->connection = $this->entityManager->getConnection();
        $this->connection->getConfiguration()?->setSQLLogger(null);

        // set entities repositories
        $this->actorsRepository = $this->entityManager->getRepository(Actor::class);
        $this->directorsRepository = $this->entityManager->getRepository(Director::class);
        $this->filmsRepository = $this->entityManager->getRepository(Film::class);

        // CSV decoder
        $this->decoder = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);

        parent::__construct();

    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the .csv file to import')
            ->addOption(
                'update',
                'u',
                InputOption::VALUE_NONE,
                'Update films data and relations. By default the command will import the whole dataset, truncating previously stored data.'
            )
            ->addOption(
                'test',
                't',
                InputOption::VALUE_NONE,
                'For testing purposes, limit dataset to 1000 rows.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $this->io = new SymfonyStyle($input, $output);
        $this->input = $input;
        $this->output = $output;
        $file_name = $input->getArgument('file');
        $filePath = Path::normalize($file_name);

        try {

            if ($this->filesystem->exists($filePath)) {

                // count lines to set up the progress bar (extract headers)
                $lines = $this->countLines($filePath) - 1;
                if ($input->getOption('test')) {
                    $lines = 1000;
                }

                // Show progress bar
                $this->progressBar = new ProgressBar($output, $lines);
                $this->progressBar->setFormat(
                    ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%'
                );

                // Command update option
                if ($input->getOption('update')) {

                    $this->progressBar->setMessage(
                        sprintf('Updating database with .csv file. Found %s films', $lines)
                    );
                    $this->progressBar->start();

                    // parse large file in smaller parts to avoid RAM exhaustion
                    $this->chunkFile($filePath, 1024, function ($chunk, &$handle, $iteration) {

                        // each row is treated as a chunk
                        if ($chunk) {

                            // csv headers are in the first chunk
                            if ($iteration === 0) {

                                $this->headers = $chunk;

                            } else {

                                $filmsRepository = $this->filmsRepository;

                                $film = array_combine($this->headers, $chunk);

                                // we are assuming imdb_title_id is the unique identifier in the csv
                                if ($existingFilm = $filmsRepository->findOneBy(
                                    ['imdbTitleId' => $film['imdb_title_id']]
                                )) {
                                    // Update film if matching record
                                    $existingFilm = $this->updateFilm($film, $existingFilm);

                                } else {
                                    // Create film if not found
                                    $existingFilm = $this->createNewFilm($film);
                                }

                                // Add Actors to the film
                                $actors = explode(',', $film['actors']);
                                $this->addActorsTo($existingFilm, $actors);

                                // Add Director(s) to the film
                                $directors = explode(',', $film['director']);
                                $this->addDirectorsTo($existingFilm, $directors);

                                // free memory
                                $existingFilm = $directors = $actors = null;
                                unset($actors, $directors, $existingFilm);

                                // flush/clear each 100 rows && garbage collect
                                if ($iteration % 100 === 0) {
                                    $this->entityManager->flush();
                                    $this->entityManager->clear();
                                    gc_collect_cycles();
                                }

                                $this->progressBar->advance();

                                // testing performance on 1000 rows
                                if ($iteration % 1000 === 0 && $this->input->getOption('test')) {
                                    $this->progressBar->finish();
                                    $this->io->success('1000 films imported.');

                                    return Command::SUCCESS;
                                }
                            }
                        }
                    });

                    // Flush pending persisted entities
                    $this->entityManager->flush();
                    $this->entityManager->clear();

                    $this->progressBar->finish();
                    $this->io->success(sprintf('%d films imported.', $lines));

                    return Command::SUCCESS;
                }

                $output->writeln("Step 1");
                // command default options
                $this->progressBar->setMessage(
                    sprintf('Creating films and dependencies files from %s', $filePath)
                );
                $this->progressBar->start();

                try {
                    // truncate all tables
                    $this->truncateAll();

                    // prepare .csv file in less ram consuming chunks
                    // max lines per file: 1000 (configured on method)
                    $files = $this->makeCsvChunks($filePath);

                    $test = 0;
                    foreach ($files as $file) {

                        // decode each file as a (csv) array
                        $rows = $this->decoder->decode(file_get_contents($file), 'csv');
                        $numRows = count($rows);

                        foreach ($rows as $film) {

                            // create directors relation files
                            $this->createDirectorFiles($film);

                            // create actors relation files
                            $this->createActorFiles($film);

                            // create the new film
                            $this->createNewFilm($film);

                        }

                        $this->progressBar->advance($numRows);

                        // flush each file (1000 films)
                        $this->entityManager->flush();
                        $this->entityManager->clear();

                        // test breaks the import at 1000 rows
                        if ($test === 0 && $input->getOption('test')) {
                            break;
                        }
                        $test++;

                    }
                    // end creating films
                    $this->progressBar->finish();

                    // create directors and film relation from files
                    $directors = glob('var/temp/directors/*');
                    if ($directors) {

                        $output->writeln("\r\n\r\nStep 2");
                        $this->progressBar = new ProgressBar($output, count($directors));
                        $this->progressBar->setFormat(
                            ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%'
                        );
                        $this->progressBar->setMessage(
                            sprintf('Importing directors from %s', $filePath)
                        );
                        $this->progressBar->start();

                        // Import directors with film relations from files
                        $this->createDirectors($directors);

                        // flush pending persisted data
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                        $this->progressBar->finish();
                        // end importing directors and relations
                    }


                    // create actors and film relation files
                    $actors = glob('var/temp/actors/*');
                    if ($actors) {

                        $output->writeln("\r\n\r\nStep 3");
                        $this->progressBar = new ProgressBar($output, count($actors));
                        $this->progressBar->setFormat(
                            ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%'
                        );
                        $this->progressBar->setMessage(
                            sprintf(
                                'Importing actors from %s',
                                $filePath
                            )
                        );
                        $this->progressBar->start();

                        // Import directors with film relations from files
                        $this->createActors($actors);

                        // flush pending persisted data
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                        $this->progressBar->finish();
                        // end importing actors and relations
                    }


                } catch (\Doctrine\DBAL\Exception $e) {
                    $this->io->error(sprintf('Exception: %s', $e->getMessage()));

                    return Command::FAILURE;
                }

                $this->progressBar->finish();
                $this->io->success(sprintf('%d films imported.', $lines));

                return Command::SUCCESS;
            }

            // File do not exist
            $this->io->error(sprintf('File not found. Please check correct path to %s', $filePath));

            return Command::FAILURE;

        } catch (IOExceptionInterface $exception) {

            $this->io->error(sprintf('Exception: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

    }

    /**
     * Parse large csv file as chunks to avoid memory exhaustion (Update option)
     *
     * @param $file
     * @param $chunkSize
     * @param $callback
     * @return void
     */
    protected function chunkFile($file, $chunkSize, $callback): void
    {
        try {
            $handle = fopen($file, 'rb');
            $i = 0;
            while (!feof($handle)) {
                call_user_func_array($callback, [fgetcsv($handle, $chunkSize), &$handle, $i]);
                $i++;
            }

            fclose($handle);

        } catch (Exception $e) {
            trigger_error("chunkFile::".$e->getMessage(), E_USER_NOTICE);
        }
    }


    /**
     * Create new Film
     *
     * @param array $film
     * @return Film
     */
    protected function createNewFilm(array $film): Film
    {
        $existingFilm = new Film();
        if ($film['imdb_title_id']) {
            $existingFilm
                ->setImdbTitleId($film['imdb_title_id'])
                ->setTitle($film['title'])
                ->setDatePublished($film['date_published'])
                ->setGenre($film['genre'])
                ->setDuration((int)$film['duration'])
                ->setProductionCompany($film['production_company']);
            $this->entityManager->persist($existingFilm);
        }

        return $existingFilm;
    }

    /**
     * Update existing Film
     *
     * @param $film
     * @param $existingFilm
     * @return Film
     */
    protected function updateFilm($film, $existingFilm): Film
    {
        $existingFilm
            ->setTitle($film['title'])
            ->setDatePublished($film['date_published'])
            ->setGenre($film['genre'])
            ->setDuration($film['duration'])
            ->setProductionCompany($film['production_company']);
        $this->entityManager->persist($existingFilm);

        return $existingFilm;
    }

    /**
     * Add Actors to existing film (Update option)
     *
     * @param $existingFilm
     * @param $actors
     */
    protected function addActorsTo($existingFilm, $actors): void
    {
        $actorsRepository = $this->actorsRepository;
        foreach ($actors as $actor) {
            if ($existingActor = $actorsRepository->findOneBy(['name' => $actor])) {
                $existingActor->addFilm($existingFilm);
            } else {
                $existingActor = new Actor();
                $existingActor
                    ->setName($actor)
                    ->addFilm($existingFilm);
            }
            $this->entityManager->persist($existingActor);
        }

    }

    /**
     * Add Directors to existing film. (Update option)
     *
     * @param $existingFilm
     * @param $directors
     */
    protected function addDirectorsTo($existingFilm, $directors): void
    {
        $directorsRepository = $this->directorsRepository;
        foreach ($directors as $director) {
            if ($existingDirector = $directorsRepository->findOneBy(['name' => $director])) {
                $existingDirector->addFilm($existingFilm);
            } else {
                $existingDirector = new Director();
                $existingDirector
                    ->setName($director)
                    ->addFilm($existingFilm);
            }
            $this->entityManager->persist($existingDirector);
        }

    }

    /**
     * Count lines of a given file (batch processes util)
     *
     * @param string $filePath
     * @return int
     */
    private function countLines(string $filePath): int
    {
        $lineCount = 0;
        $handle = fopen($filePath, 'rb');
        while (!feof($handle)) {
            fgets($handle);
            $lineCount++;
        }
        fclose($handle);

        return $lineCount;
    }

    /**
     * Truncate all tables in database and clean temp files from previous executions
     *
     * @throws \Doctrine\DBAL\Exception
     */
    private function truncateAll(): void
    {
        $this->clean();

        $connection = $this->connection;
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTables();
        $query = 'SET FOREIGN_KEY_CHECKS = 0;';

        foreach ($tables as $table) {
            $name = $table->getName();
            if ($name !== 'doctrine_migration_versions') {
                $query .= 'TRUNCATE '.$name.';';
            }
        }
        $query .= 'SET FOREIGN_KEY_CHECKS = 1;';
        $connection->executeQuery($query, array(), array());
    }

    /**
     * Chunk csv on smaller parts, adding row Ids
     *
     * @param string $filePath
     * @return bool|array
     */
    private function makeCsvChunks(string $filePath): bool|array
    {

        $path = pathinfo($filePath);
        $tempFile = 'var/temp/'.$path['basename'];

        // add id column to original headers
        $headers = "id,".implode(",", $this->getHeadersFromCsv($filePath));

        // copy original file to temp path
        $this->filesystem->mkdir('var/temp');
        $this->filesystem->copy($filePath, $tempFile);
        // create paths for actors and directors
        $this->filesystem->mkdir('var/temp/actors');
        $this->filesystem->mkdir('var/temp/directors');

        $tempInfo = pathinfo($tempFile);
        $tempResource = fopen($tempFile, 'rb');

        // chunk line counter
        $lineCount = 1;

        // first id we will add
        $fileCount = 1;

        $maxLines = 1000; // max lines chunk will have
        while (!feof($tempResource)) {

            // file counter leading zeros
            $fileCounter = str_pad($fileCount, 4, '0', STR_PAD_LEFT);

            // create file chunks
            $chunk = fopen($tempInfo['dirname'].DIRECTORY_SEPARATOR.$fileCounter."-".$tempInfo['basename'], 'wb');

            // headers are set on the first chunk only, add them to others
            if ((int)$fileCounter > 1) {
                fwrite($chunk, trim($headers)."\r\n");
            }
            // pass and id to each line (first id(0) is passed by the first chunk headers)
            $id = $id ?? 0;
            while ($lineCount <= $maxLines) {
                $readLine = fgets($tempResource);
                $line = $id.",".$readLine;
                // if on first line of first chunk, just modify headers
                if ((int)$fileCounter === 1 && $lineCount === 1) {
                    $line = "id,".$readLine;
                }
                $content = trim($line);
                fwrite($chunk, $content."\r\n");
                $lineCount++;
                $id++;
            }
            fclose($chunk);
            // prepare line count for next file chunk
            $lineCount = 1;
            $fileCount++;
        }
        fclose($tempResource);

        // remove temp file
        $this->filesystem->remove($tempFile);

        // return list of chunks as array
        return glob($tempInfo['dirname'].DIRECTORY_SEPARATOR."*.*");

    }

    /**
     * return first line of a CSV file (intended to use as csv headers)
     *
     * @param string $filePath
     * @return array
     */
    private function getHeadersFromCsv(string $filePath): array
    {
        $f = fopen($filePath, 'rb');
        $line = fgetcsv($f);
        fclose($f);

        return $line;
    }

    /**
     * Persist Actors found in files and add film dependencies
     *
     * @param array $actorFiles
     */
    private function createActors(array $actorFiles): void
    {
        // each actorFile contains lines with name,filmId
        $batchSize = 0;
        foreach ($actorFiles as $file) {

            // create the director
            $handle = fopen($file, 'rb');
            $existingActor = new Actor();
            if ($relation = fgetcsv($handle)) {
                $existingActor = new Actor();
                $existingActor->setName($relation[0]);
            }
            fclose($handle);

            $this->entityManager->persist($existingActor);

            // add Director to Film
            $handle = fopen($file, 'rb');
            while (!feof($handle)) {
                if ($relation = fgetcsv($handle)) {
                    // Add director to film
                    $filmsRepository = $this->filmsRepository;
                    if ($existingFilm = $filmsRepository->find($relation[1])) {
                        $existingFilm->addActor($existingActor);
                    }
                }
            }
            fclose($handle);

            /** @noinspection DisconnectedForeachInstructionInspection */
            $this->progressBar->advance();

            // flush each 1000 actors
            if ($batchSize % 1000 === 0) {
                // flush each file (1000 films)
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
            $batchSize++;
        }
    }

    /**
     * Persist Directors found in files and add film dependencies
     *
     * @param array $directorFiles
     */
    private function createDirectors(array $directorFiles): void
    {
        // each directorFile contains lines with name,filmId
        $batchSize = 0;
        foreach ($directorFiles as $file) {

            // create the director
            $handle = fopen($file, 'rb');
            $existingDirector = new Director();
            if ($relation = fgetcsv($handle)) {
                $existingDirector = new Director();
                $existingDirector->setName($relation[0]);
            }
            fclose($handle);

            $this->entityManager->persist($existingDirector);

            // add Actor to film
            $handle = fopen($file, 'rb');
            while (!feof($handle)) {
                if ($relation = fgetcsv($handle)) {
                    // Add director to film
                    $filmsRepository = $this->filmsRepository;
                    if ($existingFilm = $filmsRepository->find($relation[1])) {
                        $existingFilm->addDirector($existingDirector);
                    }
                }
            }
            fclose($handle);

            /** @noinspection DisconnectedForeachInstructionInspection */
            $this->progressBar->advance();

            // flush each 1000 directors
            if ($batchSize % 1000 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
            $batchSize++;
        }

    }

    /**
     * Remove temp files created
     */
    private function clean(): void
    {
        $this->filesystem->remove('var/temp');
    }

    /**
     * Create a temp file for each Director found with film dependencies
     *
     * @param mixed $film
     */
    private function createDirectorFiles(mixed $film): void
    {
        if (isset($film['director'])) {
            $filmDirectors = explode(',', $film['director']);
            foreach ($filmDirectors as $director) {
                // safe filename string
                $fileName = md5(trim($director));
                $resource = fopen(
                    "var/temp/directors/".$fileName.".csv",
                    'ab'
                );
                fwrite($resource, '"'.trim($director).'",'.trim($film['id'])."\r\n");
                fclose($resource);
            }
        }
    }

    /**
     * Create a temp file for each Actor found with film dependencies
     *
     * @param mixed $film
     */
    private function createActorFiles(mixed $film): void
    {
        if (isset($film['actors'])) {
            $filmActors = explode(',', $film['actors']);
            foreach ($filmActors as $actor) {
                // safe filename string
                $fileName = md5(trim($actor));
                $resource = fopen(
                    "var/temp/actors/".$fileName.".csv",
                    'ab'
                );
                fwrite($resource, '"'.trim($actor).'",'.trim($film['id'])."\r\n");
                fclose($resource);
            }
        }
    }

    /**
     * Call clean method on completion
     */
    public function __destruct()
    {
        $this->clean();
    }

}
