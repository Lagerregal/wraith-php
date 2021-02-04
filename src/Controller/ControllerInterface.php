<?php

namespace WraithPhp\Controller;

use WraithPhp\Configuration;

interface ControllerInterface
{
    public function exec(Configuration $config): void;
}
