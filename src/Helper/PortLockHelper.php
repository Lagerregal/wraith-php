<?php

namespace WraithPhp\Helper;

use RuntimeException;

class PortLockHelper
{
    public static function lockSystemPort(int $fileLockPort = 7600)
    {
        $counter = 0;
        do {
            $stream = @stream_socket_server(
                'tcp://0.0.0.0:' . $fileLockPort,
                $errorCode,
                $errorMessage,
                STREAM_SERVER_BIND
            );
            if ($stream === false) {
                // if port is already used (locked) wait a second and try again
                sleep(1);
            }
            $counter++;
            if ($counter > 30) {
                throw new RuntimeException('Lock system port is not possible!');
            }
        } while ($stream === false);
        return $stream;
    }

    public static function releaseSystemPort($stream)
    {
        fclose($stream);
    }
}
