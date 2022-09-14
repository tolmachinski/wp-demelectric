<?php

namespace Demelectric;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    /**
     * Constructor.
     */
    public function __construct(iterable $commands = [])
    {
        foreach ($commands as $command) {
            $this->add($command);
        }

        parent::__construct();
    }
}
