<?php
// php/TransactionManager.php
require_once 'models/Wallet.php';

class TransactionManager {
    private $pdo;
    private $walletModel;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->walletModel = new Wallet();
    }
    
    /**
     * Effectue un dépôt
     */
    public function deposit($user_id, $amount, $operator, $phone, $method = 'mobile_money') {
        $this->pdo->beginTransaction();
        
        try {
            // Simuler l'appel API
            $apiResult = $this->simulateAPIRequest('deposit', $operator, $phone, $amount);
            
            if (!$apiResult['success']) {
                throw new Exception($apiResult['error']);
            }
            
            // Utiliser le modèle Wallet pour mettre à jour le solde
            $updateResult = $this->walletModel->updateBalance($user_id, $amount);
            
            if (!$updateResult['success']) {
                throw new Exception($updateResult['message']);
            }
            
            // Récupérer l'ID du wallet
            $walletInfo = $this->walletModel->getBalance($user_id);
            if (!$walletInfo['success']) {
                throw new Exception('Impossible de récupérer les informations du wallet');
            }
            
            // Insérer la transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions 
                (user_id, wallet_id, type, amount, fees, status, description, metadata, created_at, updated_at) 
                VALUES (?, ?, 'deposit', ?, ?, 'completed', ?, ?, NOW(), NOW())
            ");
            
            $fees = $this->calculateFees($amount, 'deposit', $method);
            $description = "Dépôt via " . $this->getOperatorName($operator) . " ($phone)";
            $metadata = json_encode([
                'operator' => $operator,
                'phone' => $phone,
                'method' => $method,
                'fees' => $fees,
                'api_reference' => $apiResult['reference'],
                'api_response' => $apiResult
            ]);
            
            // Note: Nous devons récupérer l'ID du wallet depuis la base
            $walletId = $this->getWalletId($user_id);
            
            $stmt->execute([$user_id, $walletId, $amount, $fees, $description, $metadata]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'transaction_id' => $this->pdo->lastInsertId(),
                'fees' => $fees,
                'net_amount' => $amount - $fees,
                'reference' => $apiResult['reference'],
                'message' => "Dépôt de " . number_format($amount, 0, ',', ' ') . " FCFA réussi via " . $this->getOperatorName($operator),
                'new_balance' => $updateResult['balance']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Effectue un retrait
     */
    public function withdraw($user_id, $amount, $operator, $phone, $method = 'mobile_money') {
        $this->pdo->beginTransaction();
        
        try {
            // Vérifier le solde disponible via le modèle Wallet
            $balanceResult = $this->walletModel->getBalance($user_id);
            
            if (!$balanceResult['success']) {
                throw new Exception($balanceResult['message']);
            }
            
            $fees = $this->calculateFees($amount, 'withdrawal', $method);
            $total_amount = $amount + $fees;
            
            if ($balanceResult['balance'] < $total_amount) {
                throw new Exception("Solde insuffisant. Solde disponible: " . number_format($balanceResult['balance'], 0, ',', ' ') . " FCFA");
            }
            
            // Simuler l'appel API
            $apiResult = $this->simulateAPIRequest('withdrawal', $operator, $phone, $amount);
            
            if (!$apiResult['success']) {
                throw new Exception($apiResult['error']);
            }
            
            // Utiliser le modèle Wallet pour mettre à jour le solde (montant négatif pour retrait)
            $updateResult = $this->walletModel->updateBalance($user_id, -$total_amount);
            
            if (!$updateResult['success']) {
                throw new Exception($updateResult['message']);
            }
            
            // Insérer la transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions 
                (user_id, wallet_id, type, amount, fees, status, description, metadata, created_at, updated_at) 
                VALUES (?, ?, 'withdrawal', ?, ?, 'completed', ?, ?, NOW(), NOW())
            ");
            
            $description = "Retrait vers " . $this->getOperatorName($operator) . " ($phone)";
            $metadata = json_encode([
                'operator' => $operator,
                'phone' => $phone,
                'method' => $method,
                'fees' => $fees,
                'api_reference' => $apiResult['reference'],
                'api_response' => $apiResult
            ]);
            
            $walletId = $this->getWalletId($user_id);
            $stmt->execute([$user_id, $walletId, $amount, $fees, $description, $metadata]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'transaction_id' => $this->pdo->lastInsertId(),
                'fees' => $fees,
                'total_debited' => $total_amount,
                'reference' => $apiResult['reference'],
                'message' => "Retrait de " . number_format($amount, 0, ',', ' ') . " FCFA réussi vers " . $this->getOperatorName($operator),
                'new_balance' => $updateResult['balance']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Effectue un transfert
     */
    public function transfer($user_id, $amount, $operator, $phone, $note = '') {
        $this->pdo->beginTransaction();
        
        try {
            // Vérifier le solde disponible via le modèle Wallet
            $balanceResult = $this->walletModel->getBalance($user_id);
            
            if (!$balanceResult['success']) {
                throw new Exception($balanceResult['message']);
            }
            
            $fees = $this->calculateFees($amount, 'transfer', 'mobile_money');
            $total_amount = $amount + $fees;
            
            if ($balanceResult['balance'] < $total_amount) {
                throw new Exception("Solde insuffisant. Solde disponible: " . number_format($balanceResult['balance'], 0, ',', ' ') . " FCFA");
            }
            
            // Simuler l'appel API
            $apiResult = $this->simulateAPIRequest('transfer', $operator, $phone, $amount);
            
            if (!$apiResult['success']) {
                throw new Exception($apiResult['error']);
            }
            
            // Utiliser le modèle Wallet pour mettre à jour le solde (montant négatif pour transfert)
            $updateResult = $this->walletModel->updateBalance($user_id, -$total_amount);
            
            if (!$updateResult['success']) {
                throw new Exception($updateResult['message']);
            }
            
            // Insérer la transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions 
                (user_id, wallet_id, type, amount, fees, status, description, metadata, created_at, updated_at) 
                VALUES (?, ?, 'transfer', ?, ?, 'completed', ?, ?, NOW(), NOW())
            ");
            
            $description = "Transfert vers " . $this->getOperatorName($operator) . " ($phone)";
            if (!empty($note)) {
                $description .= " - " . $note;
            }
            
            $metadata = json_encode([
                'operator' => $operator,
                'phone' => $phone,
                'fees' => $fees,
                'note' => $note,
                'recipient_phone' => $phone,
                'api_reference' => $apiResult['reference'],
                'api_response' => $apiResult
            ]);
            
            $walletId = $this->getWalletId($user_id);
            $stmt->execute([$user_id, $walletId, $amount, $fees, $description, $metadata]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'transaction_id' => $this->pdo->lastInsertId(),
                'fees' => $fees,
                'total_debited' => $total_amount,
                'amount_received' => $amount,
                'reference' => $apiResult['reference'],
                'message' => "Transfert de " . number_format($amount, 0, ',', ' ') . " FCFA réussi vers " . $this->getOperatorName($operator),
                'new_balance' => $updateResult['balance']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère l'ID du wallet d'un utilisateur
     */
    private function getWalletId($user_id) {
        $stmt = $this->pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wallet = $stmt->fetch();
        
        if (!$wallet) {
            throw new Exception("Wallet non trouvé pour cet utilisateur");
        }
        
        return $wallet['id'];
    }
    
    /**
     * Simule un appel API avec délai réaliste
     */
    private function simulateAPIRequest($operation, $operator, $phone, $amount) {
        // Simulation de délai réseau
        usleep(2000000); // 2 secondes
        
        // Simulation de succès (90% de chance de succès)
        if (rand(1, 100) <= 90) {
            return [
                'success' => true,
                'reference' => strtoupper($operator) . '_' . uniqid(),
                'message' => "Transaction {$operation} approuvée"
            ];
        } else {
            return [
                'success' => false,
                'error' => "Échec de la transaction {$operation}. Veuillez réessayer."
            ];
        }
    }
    
    private function calculateFees($amount, $type, $method) {
        $fees = 0;
        
        switch ($type) {
            case 'deposit':
                if ($method === 'mobile_money') {
                    $fees = $amount * 0.01;
                } else if ($method === 'bank_transfer') {
                    $fees = $amount * 0.005;
                }
                break;
                
            case 'withdrawal':
                $fees = $amount * 0.015;
                break;
                
            case 'transfer':
                $fees = $amount * 0.01;
                break;
        }
        
        $min_fees = 100;
        $fees = max($fees, $min_fees);
        
        $max_fees = 5000;
        $fees = min($fees, $max_fees);
        
        return round($fees, 2);
    }
    
    private function getOperatorName($operatorCode) {
        $operators = [
            'orange' => 'Orange Money',
            'mtn' => 'MTN Mobile Money', 
            'wave' => 'Wave',
            'moov' => 'Moov Money'
        ];
        return $operators[$operatorCode] ?? $operatorCode;
    }
}
?>