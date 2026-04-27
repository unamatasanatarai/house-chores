<?php

class Response {
    public static function success($data = [], $meta = [], $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data,
            'meta' => $meta
        ]);
        exit;
    }

    public static function error($code, $message, $details = [], $status = 400) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ]
        ]);
        exit;
    }
}
