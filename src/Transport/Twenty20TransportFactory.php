<?php

namespace Twenty20\Mailer\Transport;

use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Twenty20\Mailer\Facades\Mailer;

class Twenty20TransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        return new Twenty20Transport(app(Mailer::class));
    }
}
