<?php

namespace A17\Blast\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use A17\Blast\Traits\Helpers;

class PublishStorybookConfig extends Command
{
    use Helpers;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blast:publish-storybook-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish Storybook config files to project directory';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
        $this->vendorPath = $this->getVendorPath();
    }

    /*
     * Executes the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // copy blast default configs to .storybook
        $blastConfigPath = $this->vendorPath . '/.storybook';
        $projectConfigPath = base_path('.storybook');
        $copyFiles = true;

        if ($this->filesystem->exists($projectConfigPath)) {
            $copyFiles = $this->confirm(
                'Config already exists in project directory. Overwrite? This cannot be undone.',
                false,
            );
        }

        if (!$copyFiles) {
            $this->error('Aborting');

            return 0;
        }

        $this->filesystem->copyDirectory($blastConfigPath, $projectConfigPath);

        // Update paths in preview.js
        if ($this->filesystem->exists($projectConfigPath . '/preview.js')) {
            $this->filesystem->replaceInFile(
                '../public/main.css',
                '../vendor/area17/blast/public/main.css',
                $projectConfigPath . '/preview.js',
            );
        }

        // Update paths in main.js
        $mainJsPath = $projectConfigPath . '/main.js';

        if ($this->filesystem->exists($mainJsPath)) {
            $this->filesystem->replaceInFile(
                '../stories/**/*.stories.json',
                '../vendor/area17/blast/stories/**/*.stories.json',
                $mainJsPath,
            );

            $mainJsContents = $this->filesystem->get($mainJsPath);
            preg_match('/addons: [ \t]*\[(.*)\]/sU', $mainJsContents, $matches);

            if (filled($matches)) {
                $this->filesystem->replaceInFile(
                    $matches[1],
                    "
    '../vendor/area17/blast/node_modules/@storybook/addon-links/dist',
    '../vendor/area17/blast/node_modules/@storybook/addon-essentials/dist/actions',
    '../vendor/area17/blast/node_modules/@storybook/addon-essentials/dist/backgrounds',
    '../vendor/area17/blast/node_modules/@storybook/addon-essentials/dist/controls',
    '../vendor/area17/blast/node_modules/@storybook/addon-essentials/dist/docs',
    '../vendor/area17/blast/node_modules/@storybook/addon-essentials/dist/highlight',
    '../vendor/area17/blast/node_modules/@storybook/addon-essentials/dist/measure',
    '../vendor/area17/blast/node_modules/@storybook/addon-essentials/dist/outline',
    '../vendor/area17/blast/node_modules/@storybook/addon-essentials/dist/toolbars',
    '../vendor/area17/blast/node_modules/@storybook/addon-essentials/dist/viewport',
    '../vendor/area17/blast/node_modules/@storybook/addon-a11y',
    '../vendor/area17/blast/node_modules/@storybook/addon-designs',
    '../vendor/area17/blast/node_modules/storybook-source-code-addon',
    '../vendor/area17/blast/node_modules/@etchteam/storybook-addon-status'
  ",
                    $mainJsPath,
                );
            }
        }

        $this->info('Copied files to .storybook in your project directory');
        $this->info(
            'Note that any future changes to the storybook config files in blast will have to be manually applied to the config files in your project.',
        );
    }
}
