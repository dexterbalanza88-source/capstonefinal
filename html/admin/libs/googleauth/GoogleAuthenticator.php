<?php
// html/admin/libs/googleauth/GoogleAuthenticator.php
class GoogleAuthenticator {
    protected $_codeLength = 6;

    public function createSecret($secretLength = 16) {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $secretLength; $i++) {
            $secret .= $validChars[random_int(0, strlen($validChars) - 1)];
        }
        return $secret;
    }

    public function getQRCodeGoogleUrl($name, $secret, $issuer = null) {
        $label = rawurlencode($name);
        $issuerParam = $issuer ? '&issuer=' . rawurlencode($issuer) : '';
        $otpauth = "otpauth://totp/{$label}?secret={$secret}{$issuerParam}";
        return "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . rawurlencode($otpauth);
    }

    public function verifyCode($secret, $code, $discrepancy = 2) {
        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, str_pad($code, $this->_codeLength, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        return false;
    }

    public function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) $timeSlice = floor(time() / 30);
        $secretKey = $this->_base32Decode($secret);
        $time = str_pad(pack('N*', $timeSlice), 8, chr(0), STR_PAD_LEFT);
        $hm = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        $value = unpack("N", $hashpart)[1] & 0x7FFFFFFF;
        $modulo = pow(10, $this->_codeLength);
        return str_pad($value % $modulo, $this->_codeLength, '0', STR_PAD_LEFT);
    }

    protected function _base32Decode($secret) {
        if (empty($secret)) return '';
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        $secret = strtoupper($secret);
        $secret = rtrim($secret, '=');
        $binaryString = '';
        for ($i = 0; $i < strlen($secret); $i++) {
            if (!isset($base32charsFlipped[$secret[$i]])) return false;
            $binaryString .= str_pad(decbin($base32charsFlipped[$secret[$i]]), 5, '0', STR_PAD_LEFT);
        }
        $eightBits = str_split($binaryString, 8);
        $decoded = '';
        foreach ($eightBits as $bits) {
            if (strlen($bits) == 8) $decoded .= chr(bindec($bits));
        }
        return $decoded;
    }
}
