<?php

namespace Phinx\Console\Command;

use Phinx\Util\Util;
use Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SchemaDump extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputArgument::OPTIONAL, 'The target environment');

        $this->setName('schema:dump')
             ->setDescription('Dump existing database to initial migration')
             ->setHelp(
<<<EOT
The <info>schema:dump</info> command will dump the whole database to an initial schema.

<info>phinx schema:dump -e development</info>

EOT
             );

        // Allow the migration path to be chosen non-interactively.
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Specify the path in which to create this migration');
    }

    /**
     * Get the confirmation question asking if the user wants to create the
     * migrations directory.
     *
     * @return ConfirmationQuestion
     */
    protected function getCreateMigrationDirectoryQuestion()
    {
        return new ConfirmationQuestion('Create migrations directory? [y]/n ', true);
    }

    /**
     * Get the question that allows the user to select which migration path to use.
     *
     * @param string[] $paths
     * @return ChoiceQuestion
     */
    protected function getSelectMigrationPathQuestion(array $paths)
    {
        return new ChoiceQuestion('Which migrations path would you like to use?', $paths, 0);
    }

    /**
     * Returns the migration path to create the migration in.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     * @throws \Exception
     */
    protected function getMigrationPath(InputInterface $input, OutputInterface $output)
    {
        // First, try the non-interactive option:
        $path = $input->getOption('path');

        if (!empty($path)) {
            return $path;
        }

        $paths = $this->getConfig()->getMigrationPaths();

        // No paths? That's a problem.
        if (empty($paths)) {
            throw new \Exception('No migration paths set in your Phinx configuration file.');
        }

        $paths = Util::globAll($paths);

        if (empty($paths)) {
            throw new \Exception(
                'You probably used curly braces to define migration path in your Phinx configuration file, ' .
                'but no directories have been matched using this pattern. ' .
                'You need to create a migration directory manually.'
            );
        }

        // Only one path set, so select that:
        if (1 === count($paths)) {
            return array_shift($paths);
        }

        // Ask the user which of their defined paths they'd like to use:
        $helper = $this->getHelper('question');
        $question = $this->getSelectMigrationPathQuestion($paths);

        return $helper->ask($input, $output, $question);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        // get the migration path from the config
        $path = $this->getMigrationPath($input, $output).DIRECTORY_SEPARATOR.'schema';

        if (!file_exists($path)) {
            $helper   = $this->getHelper('question');
            $question = $this->getCreateMigrationDirectoryQuestion();

            if ($helper->ask($input, $output, $question)) {
                mkdir($path, 0755, true);
            }
        }

        $this->verifyMigrationDirectory($path);

        $path = realpath($path);
        $environment = $input->getOption('environment');

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        $envOptions = $this->getConfig()->getEnvironment($environment);
        $output->writeln('<info>using adapter</info> ' . $envOptions['adapter']);
        $output->writeln('<info>using database</info> ' . $envOptions['name']);

        $schemaName = isset($envOptions['schema_name']) ? $envOptions['schema_name'] : '';

        if ($schemaName) {
            $filePath = $path . DIRECTORY_SEPARATOR . $schemaName . '_schema.php';
        } else {
            $filePath = $path . DIRECTORY_SEPARATOR . 'schema.php';
        }

        $start = microtime(true);
        $dump = $this->getManager()->getEnvironment($environment)->schemaDump();
        $end = microtime(true);

        if (!$dump) {
            $output->writeln('<comment>Database is empty. Nothing to dump!</comment>');
            return;
        }

        if (false === file_put_contents($filePath, $dump)) {
            throw new \Exception(
                sprintf('The file "%s" could not be written to', $filePath)
            );
        }

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
}
