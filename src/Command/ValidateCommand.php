<?php

namespace NeonChecker\Command;

use Nette\Neon\Neon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Throwable;

class ValidateCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('validate')
            ->setAliases(['check'])
            ->setDescription('Command tries to decode all *.neon files in selected dirs and if it is not successful, prints error')
            ->addArgument('dirs', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'List of directories to check')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string[] $dirs */
        $dirs = $input->getArgument('dirs');
        $errors = [];
        foreach (Finder::create()->files()->name('*.neon')->in($dirs) as $file) {
            $path = (string)$file;
            $content = file_get_contents($path) ?: '';
            try {
                Neon::decode($content);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                preg_match('/on line (.*?), column (.*?)\./', $message, $matches);
                $line = isset($matches[1]) ? (int)$matches[1] : null;
                $column = isset($matches[2]) ? (int)$matches[2] : null;
                $errors[] = [
                    'file' => $path,
                    'error' => $message,
                    'content' => $content,
                    'line' => $line,
                    'column' => $column,
                ];
            }
        }

        $errorsCount = count($errors);
        $output->writeln('Errors found: ' . $errorsCount . "\n");

        foreach ($errors as $error) {
            $output->writeln('Error: ' . $error['error'], Output::VERBOSITY_VERBOSE);
            $output->writeln('File: ' . $error['file'] . ':' . $error['line'], Output::VERBOSITY_VERBOSE);

            $contentRows = explode("\n", $error['content']);
            $contentRowsCount = count($contentRows);
            $newContentRows = [];

            $cipherCount = strlen((string)$contentRowsCount);
            for ($i = max(1,$error['line'] - 5); $i <= min($contentRowsCount, $error['line'] + 5); ++$i) {
                $newContentRows[] = str_pad((string)$i, $cipherCount, ' ', STR_PAD_LEFT) . ': ' . ($error['line'] === $i ? '<error>' . $contentRows[$i - 1] . '</error>' : $contentRows[$i - 1]);
            }

            $newContent = implode("\n", $newContentRows);
            $output->writeln("\nContent:\n" . $newContent, Output::VERBOSITY_VERY_VERBOSE);
            $output->writeln("\n", Output::VERBOSITY_VERBOSE);
        }
        return $errorsCount;
    }
}
