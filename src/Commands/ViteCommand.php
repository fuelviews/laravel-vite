<?php

namespace Fuelviews\Vite\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\confirm;

class ViteCommand extends Command
{
    public $signature = 'vite:install';

    public $description = 'Install vite config and supporting dependencies';

    /**
     * Execute the console command.
     *
     * Publishes Tailwind CSS and PostCSS configuration files, the application's main CSS file,
     * and installs specified npm packages that are not already installed.
     *
     * @return int Returns 0 if successful, or an error code on failure.
     *
     * @throws JsonException
     */
    public function handle(): int
    {
        $this->publishConfig('vite.config.js');

        $devDependencies = [
            'dotenv',
            'laravel-vite-plugin',
            'vite'
        ];

        $this->installNodePackages($devDependencies);

        return self::SUCCESS;
    }

    /**
     * Publishes a configuration file from the package's stubs to the project base path.
     * If the file already exists, it prompts the user for permission to overwrite.
     *
     * @param  string  $configFileName  The name of the config file to publish.
     */
    protected function publishConfig(string $configFileName): void
    {
        $stubPath = __DIR__."/../../resources/$configFileName.stub";
        $destinationPath = base_path($configFileName);

        if (File::exists($destinationPath)) {
            if (confirm("$configFileName already exists. Do you want to overwrite it?", false)) {
                File::copy($stubPath, $destinationPath);
                $this->info("$configFileName has been overwritten successfully.");
            } else {
                $this->warn("Skipping $configFileName installation.");
            }
        } elseif (confirm("$configFileName does not exist. Would you like to install it now?", true)) {
            File::copy($stubPath, $destinationPath);
            $this->info("$configFileName has been installed successfully.");
        }
    }

    /**
     * Installs the specified npm packages if they are not already included in the project's package.json.
     * It consolidates the installation of all necessary packages into a single npm command for efficiency.
     *
     * @param  array  $packageNames  An array of npm package names to install.
     *
     * @throws JsonException
     */
    protected function installNodePackages(array $packageNames): void
    {
        $packageJsonPath = base_path('package.json');
        $packageJsonContent = File::get($packageJsonPath);
        $packageJson = json_decode($packageJsonContent, true, 512, JSON_THROW_ON_ERROR);

        $packagesToInstall = [];
        foreach ($packageNames as $packageName) {
            if (! isset($packageJson['devDependencies'][$packageName])) {
                $packagesToInstall[] = $packageName;
            }
        }

        if (! empty($packagesToInstall)) {
            $packageInstallString = implode(' ', $packagesToInstall);
            $command = "npm install $packageInstallString --save-dev";

            $process = Process::fromShellCommandline($command, null, null, STDIN, null);
            $process->setTty(Process::isTtySupported());
            $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->info('Node packages installed successfully.');
        }
    }
}
