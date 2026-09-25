<?php

namespace App\Traits;

use CodeIgniter\HTTP\ResponseInterface;

trait ApiResponseTrait
{
    protected function apiSuccess(mixed $data = null, int $status = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'ok'   => true,
                'data' => $data,
                'meta' => $this->apiMeta(),
            ]);
    }

    protected function apiError(
        string $code,
        string $message,
        int $status = 400,
        array $fields = []
    ): ResponseInterface {
        $error = [
            'code'    => $code,
            'message' => $message,
        ];

        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'ok'    => false,
                'error' => $error,
                'meta'  => $this->apiMeta(),
            ]);
    }

    private function apiMeta(): array
    {
        return [
            'request_id' => bin2hex(random_bytes(8)),
            'server_time' => date(DATE_ATOM),
        ];
    }
}
