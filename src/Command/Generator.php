<?php

namespace Demelectric\Command;

use Breakmedia\Ms3Connector\Service\Logger;
use Demelectric\Service\PdfGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Generator extends Command
{

    protected Logger $logger;
    protected PdfGenerator $generator;

    public function __construct(Logger $logger, PdfGenerator $generator)
    {
        $this->logger = $logger;
        $this->generator = $generator;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('deme:generate-pdf');
        $this->setDescription('Generate PDF datasheets for all products');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->setOutput($output);

        $this->generator->generate();
        return self::SUCCESS;
    }
}
