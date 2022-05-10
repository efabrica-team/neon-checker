<?php

namespace NeonChecker\Command;

use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Type of check. Available values: `normal`, `translate`. For `translate`, all values must not be object', 'normal')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string[] $dirs */
        $dirs = $input->getArgument('dirs');
        $dirs = array_filter($dirs, 'is_dir');

        /** @var string $type */
        $type = $input->getOption('type');

        $errors = $this->findErrors($dirs, $type);

        $errorsCount = count($errors);
        $output->writeln('Errors found: ' . $errorsCount . "\n");

        foreach ($errors as $error) {
            $output->writeln('Error: ' . $error['error'], Output::VERBOSITY_VERBOSE);
            $output->writeln('File: ' . $error['file'] . ($error['line'] ? ':' . $error['line'] : ''), Output::VERBOSITY_VERBOSE);

            if ($error['line'] === null) {
                $output->writeln("\nContent (can be different than original content because of decode / encode calls):\n" . $error['content'], Output::VERBOSITY_VERY_VERBOSE);
            } else {
                $contentRows = explode("\n", $error['content']);
                $contentRowsCount = count($contentRows);

                $newContentRows = [];
                $cipherCount = strlen((string)$contentRowsCount);
                for ($i = max(1,$error['line'] - 5); $i <= min($contentRowsCount, $error['line'] + 5); ++$i) {
                    $newContentRows[] = str_pad((string)$i, $cipherCount, ' ', STR_PAD_LEFT) . ': ' . ($error['line'] === $i ? '<error>' . $contentRows[$i - 1] . '</error>' : $contentRows[$i - 1]);
                }
                $newContent = implode("\n", $newContentRows);
                $output->writeln("\nContent:\n" . $newContent, Output::VERBOSITY_VERY_VERBOSE);
            }

            $output->writeln("\n", Output::VERBOSITY_VERBOSE);
        }
        return $errorsCount;
    }

    private function findErrors(array $dirs, string $type): array
    {
        $errors = [];
        if ($dirs === []) {
            return $errors;
        }
        foreach (Finder::create()->files()->name('*.neon')->in($dirs) as $file) {
            $path = (string)$file;
            $content = file_get_contents($path) ?: '';
            try {
                $decoded = Neon::decode($content);
                if ($type === 'translate') {
                    $flat = [];
                    $flat = $this->arrayToFlat($decoded, $flat);
                    foreach ($flat as $key => $value) {
                        if (!is_scalar($value)) {
                            $errors[] = [
                                'file' => $path,
                                'error' => 'Value for key ' . $key . ' is parsed as object (probably contains `()` or it is datetime or null)',
                                'content' => Neon::encode($value),
                                'line' => null, // unknown
                                'column' => null, // unknown
                            ];
                        }
                    }
                }
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
        return $errors;
    }

    private function arrayToFlat(array $texts, array $flat): array
    {
        foreach ($texts as $key => $value) {
            if (!is_array($value)) {
                $flat[$key] = $value;
                continue;
            }
            $flat = $this->arrayToFlat($this->shiftArrayKey($value, $key), $flat);
        }
        return $flat;
    }

    private function shiftArrayKey(array $texts, string $parentKey): array
    {
        $newTexts = [];
        foreach ($texts as $key => $value) {
            $newTexts[$parentKey . '.' . $key] = $value;
        }
        return $newTexts;
    }
}
