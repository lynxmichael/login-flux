<?php
// php/MobileMoneyAPI.php

class MobileMoneyAPI {
    private $config;
    
    public function __construct() {
        $this->config = [
            'orange' => [
                'api_url' => 'https://api.orange.com/orange-money-webpay/dev/v1',
                'token' => 'YOUR_ORANGE_API_TOKEN'
            ],
            'mtn' => [
                'api_url' => 'https://sandbox.momodeveloper.mtn.com',
                'token' => 'YOUR_MTN_API_TOKEN'
            ],
            'moov' => [
                'api_url' => 'https://api.moov-africa.com',
                'token' => 'YOUR_MOOV_API_TOKEN'
            ],
            'wave' => [
                'api_url' => 'https://api.wave.com',
                'token' => 'YOUR_WAVE_API_TOKEN'
            ]
        ];
    }
    
    /**
     * Effectue un dépôt mobile money
     */
    public function deposit($operator, $phone, $amount, $reference) {
        switch ($operator) {
            case 'orange':
                return $this->orangeDeposit($phone, $amount, $reference);
            case 'mtn':
                return $this->mtnDeposit($phone, $amount, $reference);
            case 'moov':
                return $this->moovDeposit($phone, $amount, $reference);
            case 'wave':
                return $this->waveDeposit($phone, $amount, $reference);
            default:
                return ['success' => false, 'error' => 'Opérateur non supporté'];
        }
    }
    
    /**
     * Effectue un retrait mobile money
     */
    public function withdraw($operator, $phone, $amount, $reference) {
        switch ($operator) {
            case 'orange':
                return $this->orangeWithdraw($phone, $amount, $reference);
            case 'mtn':
                return $this->mtnWithdraw($phone, $amount, $reference);
            case 'moov':
                return $this->moovWithdraw($phone, $amount, $reference);
            case 'wave':
                return $this->waveWithdraw($phone, $amount, $reference);
            default:
                return ['success' => false, 'error' => 'Opérateur non supporté'];
        }
    }
    
    /**
     * Effectue un transfert mobile money
     */
    public function transfer($operator, $phone, $amount, $reference, $note = '') {
        switch ($operator) {
            case 'orange':
                return $this->orangeTransfer($phone, $amount, $reference, $note);
            case 'mtn':
                return $this->mtnTransfer($phone, $amount, $reference, $note);
            case 'moov':
                return $this->moovTransfer($phone, $amount, $reference, $note);
            case 'wave':
                return $this->waveTransfer($phone, $amount, $reference, $note);
            default:
                return ['success' => false, 'error' => 'Opérateur non supporté'];
        }
    }
    
    /**
     * API Orange Money
     */
    private function orangeDeposit($phone, $amount, $reference) {
        $data = [
            'merchant_key' => $this->config['orange']['token'],
            'currency' => 'XOF',
            'order_id' => $reference,
            'amount' => $amount,
            'return_url' => 'https://votresite.com/callback/orange',
            'cancel_url' => 'https://votresite.com/cancel',
            'notif_url' => 'https://votresite.com/notif/orange',
            'lang' => 'fr',
            'reference' => $reference
        ];
        
        return $this->makeAPIRequest($this->config['orange']['api_url'] . '/payment', $data);
    }
    
    private function orangeWithdraw($phone, $amount, $reference) {
        $data = [
            'recipient_phone' => $phone,
            'amount' => $amount,
            'reference' => $reference,
            'currency' => 'XOF'
        ];
        
        return $this->makeAPIRequest($this->config['orange']['api_url'] . '/payout', $data);
    }
    
    private function orangeTransfer($phone, $amount, $reference, $note) {
        $data = [
            'recipient_phone' => $phone,
            'amount' => $amount,
            'reference' => $reference,
            'description' => $note,
            'currency' => 'XOF'
        ];
        
        return $this->makeAPIRequest($this->config['orange']['api_url'] . '/transfer', $data);
    }
    
    /**
     * API MTN Mobile Money
     */
    private function mtnDeposit($phone, $amount, $reference) {
        $data = [
            'amount' => $amount,
            'currency' => 'XOF',
            'externalId' => $reference,
            'payer' => [
                'partyIdType' => 'MSISDN',
                'partyId' => $phone
            ],
            'payerMessage' => 'Dépôt FluxIO',
            'payeeNote' => 'Dépôt de fonds'
        ];
        
        return $this->makeAPIRequest($this->config['mtn']['api_url'] . '/collection/v1_0/requesttopay', $data, 'POST', true);
    }
    
    private function mtnWithdraw($phone, $amount, $reference) {
        $data = [
            'amount' => $amount,
            'currency' => 'XOF',
            'externalId' => $reference,
            'payee' => [
                'partyIdType' => 'MSISDN',
                'partyId' => $phone
            ],
            'payerMessage' => 'Retrait FluxIO',
            'payeeNote' => 'Retrait de fonds'
        ];
        
        return $this->makeAPIRequest($this->config['mtn']['api_url'] . '/disbursement/v1_0/transfer', $data, 'POST', true);
    }
    
    private function mtnTransfer($phone, $amount, $reference, $note) {
        $data = [
            'amount' => $amount,
            'currency' => 'XOF',
            'externalId' => $reference,
            'payee' => [
                'partyIdType' => 'MSISDN',
                'partyId' => $phone
            ],
            'payerMessage' => $note ?: 'Transfert FluxIO',
            'payeeNote' => $note ?: 'Transfert de fonds'
        ];
        
        return $this->makeAPIRequest($this->config['mtn']['api_url'] . '/disbursement/v1_0/transfer', $data, 'POST', true);
    }
    
    /**
     * API Moov Africa
     */
    private function moovDeposit($phone, $amount, $reference) {
        $data = [
            'msisdn' => $phone,
            'amount' => $amount,
            'trxID' => $reference,
            'callback_url' => 'https://votresite.com/callback/moov'
        ];
        
        return $this->makeAPIRequest($this->config['moov']['api_url'] . '/payment/request', $data);
    }
    
    private function moovWithdraw($phone, $amount, $reference) {
        $data = [
            'msisdn' => $phone,
            'amount' => $amount,
            'trxID' => $reference
        ];
        
        return $this->makeAPIRequest($this->config['moov']['api_url'] . '/payout', $data);
    }
    
    private function moovTransfer($phone, $amount, $reference, $note) {
        $data = [
            'recipient_msisdn' => $phone,
            'amount' => $amount,
            'trxID' => $reference,
            'description' => $note
        ];
        
        return $this->makeAPIRequest($this->config['moov']['api_url'] . '/transfer', $data);
    }
    
    /**
     * API Wave
     */
    private function waveDeposit($phone, $amount, $reference) {
        $data = [
            'phone' => $phone,
            'amount' => $amount,
            'reference' => $reference
        ];
        
        return $this->makeAPIRequest($this->config['wave']['api_url'] . '/payments', $data);
    }
    
    private function waveWithdraw($phone, $amount, $reference) {
        $data = [
            'phone' => $phone,
            'amount' => $amount,
            'reference' => $reference
        ];
        
        return $this->makeAPIRequest($this->config['wave']['api_url'] . '/payouts', $data);
    }
    
    private function waveTransfer($phone, $amount, $reference, $note) {
        $data = [
            'recipient_phone' => $phone,
            'amount' => $amount,
            'reference' => $reference,
            'description' => $note
        ];
        
        return $this->makeAPIRequest($this->config['wave']['api_url'] . '/transfers', $data);
    }
    
    /**
     * Méthode générique pour les requêtes API
     */
    private function makeAPIRequest($url, $data, $method = 'POST', $isMTN = false) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
        ];
        
        if ($isMTN) {
            $headers[] = 'Authorization: Bearer ' . $this->config['mtn']['token'];
            $headers[] = 'X-Reference-Id: ' . uniqid();
            $headers[] = 'Ocp-Apim-Subscription-Key: ' . $this->config['mtn']['token'];
        } else {
            $headers[] = 'Authorization: Bearer ' . $this->config[parse_url($url, PHP_URL_HOST)]['token'] ?? $this->config['orange']['token'];
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'Erreur cURL: ' . $error];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $responseData, 'reference' => $data['reference'] ?? ''];
        } else {
            return ['success' => false, 'error' => 'Erreur API: ' . ($responseData['message'] ?? 'HTTP ' . $httpCode)];
        }
    }
}
?>