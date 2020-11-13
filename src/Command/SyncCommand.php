<?php

namespace App\Command;


use App\MeetLog;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    protected function configure()
    {
        $this->setName('sync');
        $this->setDescription('Extract log from google meet');
        $this->addArgument('date', InputArgument::OPTIONAL, 'User password');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $meet = new MeetLog();

        $meet->getMeets($input->getArgument('date'));
    }
}
