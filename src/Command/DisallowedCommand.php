<?php

namespace NeonChecker\Command;

use InvalidArgumentException;
use Nette\Neon\Neon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class DisallowedCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('disallowed')
            ->setDescription('Command find all disallowed directives in all *.neon files in selected dirs')
            ->addArgument('dirs', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'List of directories to check')
            ->addOption('disallowed-keys', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'List of disallowed keys in configs. Use `:` as next level separator. For example http:frames')
            ->addOption('disallowed-values', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'List of disallowed values in configs. Use `:` as next level separator. Last part is disallowed value. For example http:frames:yes')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string[] $dirs */
        $dirs = $input->getArgument('dirs');

        /** @var string[] $disallowedKeys */
        $disallowedKeys = array_map(function (string $disallowedKey): array {
            return explode(':', $disallowedKey);
        }, $input->getOption('disallowed-keys'));

        /** @var string[] $plainDisallowedValues */
        $disallowedValues = $input->getOption('disallowed-values');


        if ($disallowedKeys === [] && $disallowedValues === []) {
            throw new InvalidArgumentException('Setup disallowed-keys and / or disallowed-values options first');
        }

        $errors = [];
        $errorsCount = 0;
        foreach (Finder::create()->files()->name('*.neon')->in($dirs) as $file) {
            $path = (string)$file;

            $content = file_get_contents($path) ?: '';
            if ($content === '') {
                continue;
            }
            $config = Neon::decode($content);

            $disallowedKeysInConfig = $this->checkDisallowedKeys($config, $disallowedKeys);
            $disallowedValuesInConfig = $this->checkDisallowedValues($config, $disallowedValues);

            if ($disallowedKeysInConfig) {
                $errors[$path]['disallowed-keys'] = $disallowedKeysInConfig;
                $errorsCount += count($disallowedKeysInConfig);
            }

            if ($disallowedValuesInConfig) {
                $errors[$path]['disallowed-values'] = $disallowedValuesInConfig;
                $errorsCount += count($disallowedValuesInConfig);
            }
        }

        $output->writeln('Errors found: ' . $errorsCount);
        foreach ($errors as $file => $fileErrors) {
            $output->writeln('', Output::VERBOSITY_VERBOSE);
            $output->writeln('File ' . $file, Output::VERBOSITY_VERBOSE);
            if (isset($fileErrors['disallowed-keys'])) {
                $output->writeln('contains these disallowed keys:', Output::VERBOSITY_VERBOSE);
                foreach ($fileErrors['disallowed-keys'] as $disallowedKey) {
                    $output->writeln("- $disallowedKey", Output::VERBOSITY_VERBOSE);
                }
            }

            if (isset($fileErrors['disallowed-values'])) {
                $output->writeln('contains these disallowed values:', Output::VERBOSITY_VERBOSE);
                foreach ($fileErrors['disallowed-values'] as $disallowedValue) {
                    $output->writeln("- $disallowedValue", Output::VERBOSITY_VERBOSE);
                }
            }
        }
        return $errorsCount;
    }

    private function transformDisallowedValue(string $disallowedValue): array
    {
        $indent = 1;
        while (preg_match_all('/:/', $disallowedValue) > 1) {
            $disallowedValue = preg_replace('/:/', "\n" . str_repeat(' ', $indent * 4), $disallowedValue, 1);
        }
        $disallowedValue = str_replace(": ", ":", $disallowedValue);
        $disallowedValue = str_replace(":", ": ", $disallowedValue);
        $disallowedValue = str_replace("\n", ":\n", $disallowedValue);
        return Neon::decode($disallowedValue);
    }

    private function checkDisallowedKeys(array $config, array $disallowedKeys): array
    {
        $disallowedKeysInConfig = [];
        foreach ($disallowedKeys as $disallowedKeyParts) {
            $conf = $config;
            foreach ($disallowedKeyParts as $disallowedKeyPart) {
                if (!isset($conf[$disallowedKeyPart])) {
                    continue 2;
                }
                $conf = $conf[$disallowedKeyPart];
            }
            $disallowedKeysInConfig[] = implode(':', $disallowedKeyParts);
        }
        return $disallowedKeysInConfig;
    }

    private function checkDisallowedValues(array $config, array $disallowedValues): array
    {
        $disallowedValuesInConfig = [];
        foreach ($disallowedValues as $disallowedValue) {
            $transformedDisallowedValue = $this->transformDisallowedValue($disallowedValue);
            if (!$this->arrayRecursiveDiff($transformedDisallowedValue, $config)) {
                $disallowedValuesInConfig[] = $disallowedValue;
            }
        }
        return $disallowedValuesInConfig;
    }

    private function arrayRecursiveDiff(array $array1, array $array2): array
    {
        $diff = [];
        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $array2)) {
                $diff[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                $recursiveDiff = $this->arrayRecursiveDiff($value, $array2[$key]);
                if (count($recursiveDiff)) {
                    $diff[$key] = $recursiveDiff;
                }
            } elseif ($value !== $array2[$key]) {
                $diff[$key] = $value;
            }
        }
        return $diff;
    }
}
