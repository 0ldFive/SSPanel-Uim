<?php

declare(strict_types=1);

namespace App\Utils;

use Psr\Http\Message\ResponseInterface;

final class ResponseHelper
{
    public static function successfully(
        Response $response,
        string $msg
    ): ResponseInterface {
        return $response->withJson([
            'ret' => 1,
            'msg' => $msg,
        ]);
    }

    public static function error(Response $response, mixed $msg): ResponseInterface
    {
        return $response->withJson([
            'ret' => 0,
            'msg' => $msg,
        ]);
    }

    public static function buildTableConfig(array $data, mixed $uri): array
    {
        return [
            'total_column' => $data,
            'default_show_column' => array_keys($data),
            'ajax_url' => $uri,
        ];
    }
}
