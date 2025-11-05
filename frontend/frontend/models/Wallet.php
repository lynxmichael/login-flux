<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

class Wallet {
    private $conn;
    private $table_wallets = 'wallets';
    private $table_transactions = 'transactions';
    private $table_users = 'users';
    private $table_user_portfolio = 'user_portfolio';
    private $table_orders = 'orders';
    private $table_stocks = 'stocks';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * ✅ Créer un wallet uniquement si inexistant
     */
    public function createWallet($user_id) {
        try {
            $initial_balance = Env::get('WALLET_INITIAL_BALANCE', 10000);
            $currency = Env::get('CURRENCY', 'FCFA');

            $check = $this->conn->prepare("SELECT id FROM $this->table_wallets WHERE user_id = :uid");
            $check->execute(['uid' => $user_id]);

            if ($check->rowCount() > 0) {
                return ['success' => false, 'message' => 'Wallet existe déjà'];
            }

            $query = "INSERT INTO $this->table_wallets (user_id, balance, currency, created_at)
                      VALUES (:uid, :bal, :cur, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                'uid' => $user_id,
                'bal' => $initial_balance,
                'cur' => $currency
            ]);

            return [
                'success' => true,
                'wallet_id' => $this->conn->lastInsertId(),
                'balance' => $initial_balance,
                'currency' => $currency
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ✅ Récupérer solde wallet
     */
    public function getBalance($user_id) {
        $query = "SELECT balance, currency FROM $this->table_wallets WHERE user_id = :uid";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['uid' => $user_id]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            return ['success' => false, 'message' => 'Wallet introuvable'];
        }

        return [
            'success' => true,
            'balance' => (float)$wallet['balance'],
            'currency' => $wallet['currency']
        ];
    }

    /**
     * ✅ Mise à jour du solde avec transaction
     */
    public function updateBalance($user_id, $amount) {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT balance FROM $this->table_wallets WHERE user_id = :uid FOR UPDATE");
            $stmt->execute(['uid' => $user_id]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Wallet introuvable'];
            }

            $newBalance = $wallet['balance'] + $amount;
            if ($newBalance < 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Solde insuffisant'];
            }

            $stmt = $this->conn->prepare("UPDATE $this->table_wallets SET balance = :bal WHERE user_id = :uid");
            $stmt->execute(['bal' => $newBalance, 'uid' => $user_id]);

            $this->conn->commit();
            return ['success' => true, 'balance' => $newBalance];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ✅ Exécuter un ordre d'achat
     */
    public function executeBuyOrder($user_id, $stock_symbol, $quantity, $price) {
        try {
            $this->conn->beginTransaction();

            // Calcul du total et des frais
            $totalCost = $quantity * $price;
            $fees = $totalCost * 0.001; // 0.1% de frais
            $totalWithFees = $totalCost + $fees;

            // Vérifier le solde
            $wallet_stmt = $this->conn->prepare("SELECT balance FROM $this->table_wallets WHERE user_id = :uid FOR UPDATE");
            $wallet_stmt->execute(['uid' => $user_id]);
            $wallet = $wallet_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                throw new Exception('Wallet introuvable');
            }

            if ($wallet['balance'] < $totalWithFees) {
                throw new Exception('Solde insuffisant pour cet achat');
            }

            // Déduire le montant du wallet
            $update_wallet = $this->conn->prepare("UPDATE $this->table_wallets SET balance = balance - :amount WHERE user_id = :uid");
            $update_wallet->execute(['amount' => $totalWithFees, 'uid' => $user_id]);

            // Enregistrer la transaction
            $insert_transaction = $this->conn->prepare("
                INSERT INTO $this->table_transactions (user_id, type, amount, description, status, created_at) 
                VALUES (:user_id, 'buy', :amount, :description, 'completed', NOW())
            ");
            $transaction_desc = "Achat de $quantity actions $stock_symbol à " . number_format($price, 2) . " FCFA";
            $insert_transaction->execute([
                'user_id' => $user_id,
                'amount' => $totalWithFees,
                'description' => $transaction_desc
            ]);

            // Enregistrer l'ordre d'achat
            $insert_order = $this->conn->prepare("
                INSERT INTO $this->table_orders (user_id, stock_symbol, order_type, quantity, price, fees, total_amount, status, operation_date, created_at) 
                VALUES (:user_id, :stock_symbol, 'buy', :quantity, :price, :fees, :total_amount, 'completed', NOW(), NOW())
            ");
            $insert_order->execute([
                'user_id' => $user_id,
                'stock_symbol' => $stock_symbol,
                'quantity' => $quantity,
                'price' => $price,
                'fees' => $fees,
                'total_amount' => $totalWithFees
            ]);

            // Mettre à jour ou insérer dans le portfolio
            $update_portfolio = $this->conn->prepare("
                INSERT INTO $this->table_user_portfolio (user_id, stock_symbol, quantity, average_price, created_at, updated_at) 
                VALUES (:user_id, :stock_symbol, :quantity, :average_price, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                quantity = quantity + VALUES(quantity),
                average_price = ((average_price * quantity) + (VALUES(average_price) * VALUES(quantity))) / (quantity + VALUES(quantity))
            ");
            $update_portfolio->execute([
                'user_id' => $user_id,
                'stock_symbol' => $stock_symbol,
                'quantity' => $quantity,
                'average_price' => $price
            ]);

            // Récupérer le nouveau solde
            $new_balance_stmt = $this->conn->prepare("SELECT balance FROM $this->table_wallets WHERE user_id = :uid");
            $new_balance_stmt->execute(['uid' => $user_id]);
            $new_balance = $new_balance_stmt->fetch(PDO::FETCH_ASSOC)['balance'];

            $this->conn->commit();

            return [
                'success' => true,
                'new_balance' => $new_balance,
                'transaction_id' => $this->conn->lastInsertId()
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ✅ Exécuter un ordre de vente
     */
    public function executeSellOrder($user_id, $stock_symbol, $quantity, $price) {
        try {
            $this->conn->beginTransaction();

            // Vérifier si l'utilisateur possède suffisamment d'actions
            $portfolio_stmt = $this->conn->prepare("SELECT quantity, average_price FROM $this->table_user_portfolio WHERE user_id = :uid AND stock_symbol = :symbol FOR UPDATE");
            $portfolio_stmt->execute(['uid' => $user_id, 'symbol' => $stock_symbol]);
            $portfolio = $portfolio_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$portfolio || $portfolio['quantity'] < $quantity) {
                throw new Exception('Quantité d\'actions insuffisante pour cette vente');
            }

            $totalRevenue = $quantity * $price;
            $fees = $totalRevenue * 0.001; // 0.1% de frais
            $totalAfterFees = $totalRevenue - $fees;

            // Ajouter le montant au wallet
            $update_wallet = $this->conn->prepare("UPDATE $this->table_wallets SET balance = balance + :amount WHERE user_id = :uid");
            $update_wallet->execute(['amount' => $totalAfterFees, 'uid' => $user_id]);

            // Enregistrer la transaction
            $insert_transaction = $this->conn->prepare("
                INSERT INTO $this->table_transactions (user_id, type, amount, description, status, created_at) 
                VALUES (:user_id, 'sell', :amount, :description, 'completed', NOW())
            ");
            $transaction_desc = "Vente de $quantity actions $stock_symbol à " . number_format($price, 2) . " FCFA";
            $insert_transaction->execute([
                'user_id' => $user_id,
                'amount' => $totalAfterFees,
                'description' => $transaction_desc
            ]);

            // Enregistrer l'ordre de vente
            $insert_order = $this->conn->prepare("
                INSERT INTO $this->table_orders (user_id, stock_symbol, order_type, quantity, price, fees, total_amount, status, operation_date, created_at) 
                VALUES (:user_id, :stock_symbol, 'sell', :quantity, :price, :fees, :total_amount, 'completed', NOW(), NOW())
            ");
            $insert_order->execute([
                'user_id' => $user_id,
                'stock_symbol' => $stock_symbol,
                'quantity' => $quantity,
                'price' => $price,
                'fees' => $fees,
                'total_amount' => $totalAfterFees
            ]);

            // Mettre à jour le portfolio
            $new_quantity = $portfolio['quantity'] - $quantity;

            if ($new_quantity > 0) {
                // Mettre à jour la quantité
                $update_portfolio = $this->conn->prepare("
                    UPDATE $this->table_user_portfolio SET quantity = :quantity, updated_at = NOW() 
                    WHERE user_id = :uid AND stock_symbol = :symbol
                ");
                $update_portfolio->execute([
                    'quantity' => $new_quantity,
                    'uid' => $user_id,
                    'symbol' => $stock_symbol
                ]);
            } else {
                // Supprimer l'entrée si quantité = 0
                $delete_portfolio = $this->conn->prepare("
                    DELETE FROM $this->table_user_portfolio WHERE user_id = :uid AND stock_symbol = :symbol
                ");
                $delete_portfolio->execute([
                    'uid' => $user_id,
                    'symbol' => $stock_symbol
                ]);
            }

            // Récupérer le nouveau solde
            $new_balance_stmt = $this->conn->prepare("SELECT balance FROM $this->table_wallets WHERE user_id = :uid");
            $new_balance_stmt->execute(['uid' => $user_id]);
            $new_balance = $new_balance_stmt->fetch(PDO::FETCH_ASSOC)['balance'];

            $this->conn->commit();

            return [
                'success' => true,
                'new_balance' => $new_balance,
                'transaction_id' => $this->conn->lastInsertId()
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ✅ Récupérer l'historique des transactions
     */
    public function getTransactionHistory($user_id, $limit = 10, $offset = 0) {
        try {
            $query = "SELECT * FROM $this->table_transactions 
                      WHERE user_id = :user_id 
                      ORDER BY created_at DESC 
                      LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue('user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'transactions' => $transactions];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ✅ Récupérer le portfolio utilisateur
     */
    public function getUserPortfolio($user_id) {
        try {
            $query = "SELECT up.stock_symbol, up.quantity, up.average_price, s.current_price, s.name,
                             (s.current_price - up.average_price) * up.quantity as unrealized_gain,
                             ((s.current_price - up.average_price) / up.average_price) * 100 as performance_percent
                      FROM $this->table_user_portfolio up
                      JOIN $this->table_stocks s ON up.stock_symbol = s.symbol
                      WHERE up.user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            $portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'portfolio' => $portfolio];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}