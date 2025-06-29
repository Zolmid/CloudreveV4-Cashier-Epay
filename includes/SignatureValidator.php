<?php
require_once 'Logger.php';
class SignatureValidator {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function verify() {
        $communicationKey = $this->db->getConfig('communication_key', '');
        if (empty($communicationKey)) {
            // If no key is set, we allow the request. 
            // This makes the signature verification optional.
            return true;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->verifyPostRequest($communicationKey);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return $this->verifyGetRequest($communicationKey);
        }

        // Allow other methods like OPTIONS to pass without verification
        return true;
    }

    private function verifyPostRequest($key) {
        $signatureWithTimestamp = $this->getSignatureFromHeader();
        Logger::debug('Verifying POST request', ['signature_header' => $signatureWithTimestamp]);
        if (!$signatureWithTimestamp) return false;

        list($signature, $timestamp) = $this->extractSignatureAndTimestamp($signatureWithTimestamp);
        Logger::debug('Extracted signature and timestamp', ['signature' => $signature, 'timestamp' => $timestamp]);

        $currentTime = $_SERVER['REQUEST_TIME'] ?? time();
        $isTimestampValid = $this->isTimestampValid($timestamp, $currentTime);
        Logger::debug('Timestamp validation', ['is_valid' => $isTimestampValid, 'current_time' => $currentTime, 'received_timestamp' => $timestamp]);

        if (!$signature || !$timestamp || !$isTimestampValid) {
            return false;
        }

        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestBody = file_get_contents('php://input');
        $signedHeaderStr = $this->getSignedHeaderString();

        Logger::debug('Raw components for signature', [
            'headers' => $signedHeaderStr,
            'body' => $requestBody
        ]);

        // Per specification, the Body field must be the raw request body string.
        $signContentRaw = [
            'Path'   => $requestPath,
            'Header' => $signedHeaderStr,
            'Body'   => $requestBody,
        ];
        // Per user feedback, slashes should not be escaped.
        $signContent = json_encode($signContentRaw, JSON_UNESCAPED_SLASHES);
        Logger::debug('POST sign content', ['content' => $signContent]);

        return $this->validateSignature($signContent, $timestamp, $signature, $key);
    }

    private function verifyGetRequest($key) {
        $signatureWithTimestamp = $_GET['sign'] ?? '';
        Logger::debug('Verifying GET request', ['signature_param' => $signatureWithTimestamp]);
        if (empty($signatureWithTimestamp)) return false;

        list($signature, $timestamp) = $this->extractSignatureAndTimestamp($signatureWithTimestamp);

        $currentTime = $_SERVER['REQUEST_TIME'] ?? time();
        $isTimestampValid = $this->isTimestampValid($timestamp, $currentTime);
        Logger::debug('Timestamp validation', ['is_valid' => $isTimestampValid, 'current_time' => $currentTime, 'received_timestamp' => $timestamp]);

        if (!$signature || !$timestamp || !$isTimestampValid) {
            return false;
        }

        // For GET requests, the sign content is just the request path.
        $signContent = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        Logger::debug('GET sign content', ['content' => $signContent]);

        return $this->validateSignature($signContent, $timestamp, $signature, $key);
    }
    
    private function getSignatureFromHeader() {
        $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $prefix = 'Bearer Cr ';
        if (strpos($authorizationHeader, $prefix) !== 0) {
            return false;
        }
        return substr($authorizationHeader, strlen($prefix));
    }

    private function extractSignatureAndTimestamp($input) {
        $parts = explode(':', $input);
        if (count($parts) !== 2) {
            return [null, null];
        }
        return [$parts[0], (int)$parts[1]];
    }

    private function isTimestampValid($timestamp, $currentTime = null) {
        if ($currentTime === null) {
            $currentTime = $_SERVER['REQUEST_TIME'] ?? time();
        }
        // Per specification, timestamp must be greater than current time.
        return $timestamp > $currentTime;
    }

    private function getSignedHeaderString() {
        $signedHeaders = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_X_CR_') === 0) {
                $headerName = str_replace('HTTP_', '', $key);
                $headerName = str_replace('_', '-', $headerName);
                // Per specification, do not lowercase header names.
                $signedHeaders[$headerName] = $value;
            }
        }
        ksort($signedHeaders);

        $parts = [];
        foreach ($signedHeaders as $key => $value) {
            $parts[] = "$key=$value";
        }
        return implode('&', $parts);
    }

    private function validateSignature($signContent, $timestamp, $signature, $key) {
        $signContentFinal = "$signContent:$timestamp";
        // Log the final string raw for easy comparison, per user request.
        Logger::debug('Final string for signature generation: ' . $signContentFinal);
        $expectedRawSignature = hash_hmac('sha256', $signContentFinal, $key, true);
        $expectedBase64UrlSignature = $this->base64url_encode($expectedRawSignature);

        Logger::debug('Signature validation details', [
            'communication_key' => $key,
            'expected_signature_base64url' => $expectedBase64UrlSignature,
            'received_signature' => $signature
        ]);
        
        $decodedSignature = $this->base64url_decode($signature);
        if ($decodedSignature === false) {
            return false;
        }

        return hash_equals($expectedRawSignature, $decodedSignature);
    }

    private function base64url_decode($data) {
        $paddedData = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($paddedData);
    }

    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}