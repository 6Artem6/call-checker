<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Level;

class CustomizeFormatter
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: (at %extra.file%:%extra.line%)\n%message% %context%\n",
                "Y-m-d H:i:s",
                true,
                true
            );

            $handler->pushProcessor(new IntrospectionProcessor(Level::Debug, ['Illuminate\\']));
            $handler->setFormatter($formatter);
        }
    }
}
