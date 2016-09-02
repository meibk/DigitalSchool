<?php

use Symfony\Component\Filesystem\Filesystem;

class UpgradeScript101
{

    protected $kernel;

    protected $version;

    public function __construct ($kernel, $version)
    {
        $this->kernel = $kernel;
        $this->version = $version;
    }

    public function execute()
    {
        $rootDir = realpath($this->kernel->getParameter('kernel.root_dir') . '/../');

        $originDir = "{$rootDir}/plugins/Homework/HomeworkBundle/Resources/public";
        $targetDir = "{$rootDir}/web/bundles/homework";

        $filesystem = new Filesystem();

        if ($filesystem->exists($targetDir)) {
            $filesystem->remove($targetDir);
        }

        $filesystem->mirror($originDir, $targetDir, null, array('override' => true, 'delete' => true));

    }

}