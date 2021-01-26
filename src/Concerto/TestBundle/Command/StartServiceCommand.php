<?php

namespace Concerto\TestBundle\Command;

use Concerto\PanelBundle\Service\AdministrationService;
use Concerto\TestBundle\Service\ASessionRunnerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class StartServiceCommand extends Command
{

    private $testRunnerSettings;
    private $sessionRunnerService;
    private $projectDir;
    private $administrationService;

    public function __construct($testRunnerSettings, ASessionRunnerService $sessionRunnerService, $projectDir, AdministrationService $administrationService)
    {
        parent::__construct();

        $this->testRunnerSettings = $testRunnerSettings;
        $this->sessionRunnerService = $sessionRunnerService;
        $this->projectDir = $projectDir;
        $this->administrationService = $administrationService;
    }

    protected function configure()
    {
        $this->setName("concerto:service:start")->setDescription("Start R service process.");
    }

    private function getCommand()
    {
        //@TODO add windows
        $servicePath = "{$this->projectDir}/src/Concerto/TestBundle/Resources/R/service.R";
        $logPath = "{$this->projectDir}/var/logs/service.log";

        return "nohup {$this->testRunnerSettings["rscript_exec"]} --no-save --no-restore --quiet '$servicePath' >> '$logPath' 2>&1 & echo $!";
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("starting service...");

        $cmd = $this->getCommand();
        $process = new Process($cmd);
        $process->inheritEnvironmentVariables(true);

        $r_environ_path = $this->testRunnerSettings["r_environ_service_path"];
        $r_profile_path = $this->testRunnerSettings['r_profile_service_path'];
        $env = [
            "R_GC_MEM_GROW" => 0,
            "R_ENVIRON_USER" => $r_environ_path != null ? $r_environ_path : "{$this->projectDir}/app/config/R/.Renviron_service",
            "R_PROFILE_USER" => $r_profile_path != null ? $r_profile_path : "{$this->projectDir}/app/config/R/.Rprofile_service"
        ];
        $process->setEnv($env);

        $process->mustRun();
        if ($process->getExitCode() == 0) {
            $output->writeln("service started");
        } else {
            $output->writeln("something went wrong: non zero exit code");
        }
    }

}
