<?php

namespace Concerto\PanelBundle\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class RDataCacheService
{
    private $tmp_file = null;
    private $tmp_cache_dir;
    private $first;
    private $projectDir;

    public function __construct($projectDir)
    {
        $this->projectDir = $projectDir;
    }

    protected function getCacheDirectory()
    {
        return "{$this->projectDir}/src/Concerto/PanelBundle/Resources/public/rcache/";
    }

    public function createNewFunctionCacheSet()
    {
        if (!is_dir($this->getCacheDirectory()))
            mkdir($this->getCacheDirectory(), 0755, true);

        $this->tmp_file = tempnam($this->getCacheDirectory(), 'rch');
        $fh = fopen($this->tmp_file, "w+");

        if ($fh === FALSE)
            throw new RuntimeException(
                "Unable to open cache file in {$this->tmp_cache_dir} for writing. Please check your permissions."
            );

        $this->tmp_cache_dir = $this->tmp_file . "_html";
        if (!mkdir($this->tmp_cache_dir))
            throw new RuntimeException(
                "Unable to create cache directory in {$this->tmp_cache_dir}. Please check your permissions."
            );
        $this->tmp_cache_dir .= DIRECTORY_SEPARATOR;

        fwrite($fh, "[");
        fclose($fh);
        $this->first = true;
    }

    public function addRFunction($library, $method_name, $documentation_html, $arguments, $defaults)
    {
        if (is_null($this->tmp_file))
            throw new LogicException("addRMethod must be used after creating a cache set first.");
        $fh = fopen($this->tmp_file, "a+");
        fwrite(
            $fh, ($this->first ? '' : ',') . "{\"lib\":\"$library\",\"fun\":\"$method_name\"}"
        );
        fclose($fh);
        file_put_contents($this->tmp_cache_dir . $method_name . ".html", $this->attachArgumentsMetadata($documentation_html, $arguments, $defaults));

        $this->first = false;
    }

    public function saveCache()
    {
        if (is_null($this->tmp_file))
            throw new LogicException("saveCache must be used after creating a cache set first.");
        $fh = fopen($this->tmp_file, "a+");
        fwrite($fh, "]");
        fclose($fh);

        if (!is_dir($this->getCacheDirectory()))
            mkdir($this->getCacheDirectory(), 0755, true);

        chmod($this->tmp_file, 0755);
        rename($this->tmp_file, $this->getCacheDirectory() . 'functionIndex.json');

        $fs = new Filesystem();
        $html_dir = $this->getCacheDirectory() . 'html';
        if ($fs->exists($html_dir)) {
            $files = new \RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($html_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            $fs->remove($files);
            $fs->remove($html_dir);
        }

        rename($this->tmp_cache_dir, $html_dir);
    }

    // I want to store it nicely without breaking the backwards compatibility, and without changing the HTML structure
    private function attachArgumentsMetadata($html, $arguments, $defaults)
    {
        return str_ireplace('<body', "<body args='" . json_encode((array)$arguments) . "' defs='" . json_encode((array)$defaults) . "'", $html);
    }

}
