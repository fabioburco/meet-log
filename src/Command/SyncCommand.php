<?php

namespace App\Command;


use App\MeetLog;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    protected function configure()
    {
        $this->setName('sync');
        $this->setDescription('Extract log from google meet');
        $this->addArgument('date', InputArgument::OPTIONAL, 'Date of the Meets');
        $this->addOption('all', '-a', InputOption::VALUE_OPTIONAL,
            'Get all the Meets and ignore whitelist', false);
        $this->addOption('meet', '-m', InputOption::VALUE_OPTIONAL,
            'Get only a specific Meet');
        $this->addOption('full', '-f', InputOption::VALUE_OPTIONAL,
            'Get all available logs (6 months)', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $logger = new ConsoleLogger($output);

        $date = $input->getArgument('date');
        $all = !((false === $input->getOption('all')));
        $code = $input->getOption('meet');
        $full = !((false === $input->getOption('full')));

        try {
            $meet = new MeetLog($logger);
            $meet->getMeets($date, $all, $code, $full);
        } catch (Exception $e) {
            $logger->error("Something went wrong. " . $e->getMessage());
        }
    }
}
