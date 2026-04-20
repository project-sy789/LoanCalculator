<?php
require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'save_loan':
        saveLoan($conn);
        break;
    case 'get_loans':
        getLoans($conn);
        break;
    case 'get_loan_details':
        getLoanDetails($conn);
        break;
    case 'update_payment':
        updatePayment($conn);
        break;
    case 'delete_loan':
        deleteLoan($conn);
        break;
    case 'get_finance_summary':
        getFinanceSummary($conn);
        break;
    case 'add_transaction':
        addTransaction($conn);
        break;
    case 'get_transactions':
        getTransactions($conn);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
        break;
}

function saveLoan($conn) {
    $data = json_decode(file_get_contents("php://input"));

    if(!empty($data->borrower_name) && !empty($data->principal)) {
        try {
            $conn->beginTransaction();

            $query = "INSERT INTO loans SET 
                        borrower_name = :borrower_name,
                        principal = :principal,
                        loan_type = :loan_type,
                        duration = :duration,
                        duration_unit = :duration_unit,
                        pmt_amount = :pmt_amount,
                        interest_rate = :interest_rate,
                        start_date = :start_date,
                        payment_time = :payment_time,
                        total_payment = :total_payment";

            $stmt = $conn->prepare($query);

            $stmt->bindParam(':borrower_name', $data->borrower_name);
            $stmt->bindParam(':principal', $data->principal);
            $stmt->bindParam(':loan_type', $data->loan_type);
            $stmt->bindParam(':duration', $data->duration);
            $stmt->bindParam(':duration_unit', $data->duration_unit);
            $stmt->bindParam(':pmt_amount', $data->pmt_amount);
            $stmt->bindParam(':interest_rate', $data->interest_rate);
            $stmt->bindParam(':start_date', $data->start_date);
            $stmt->bindParam(':payment_time', $data->payment_time);
            $stmt->bindParam(':total_payment', $data->total_payment);

            if($stmt->execute()) {
                $loan_id = $conn->lastInsertId();

                foreach($data->schedule as $item) {
                    $q_pmt = "INSERT INTO payments SET 
                                loan_id = :loan_id,
                                period_num = :period_num,
                                amount = :amount,
                                principal_paid = :principal_paid,
                                interest_paid = :interest_paid,
                                status = :status";
                    $s_pmt = $conn->prepare($q_pmt);
                    $s_pmt->bindValue(':loan_id', $loan_id);
                    $s_pmt->bindValue(':period_num', $item->period);
                    $s_pmt->bindValue(':amount', $item->payment);
                    $s_pmt->bindValue(':principal_paid', $item->principal);
                    $s_pmt->bindValue(':interest_paid', $item->interest);
                    $status = 0; 
                    $s_pmt->bindValue(':status', $status);
                    $s_pmt->execute();
                }

                $conn->commit();
                echo json_encode(["message" => "Loan saved successfully", "loan_id" => $loan_id]);
            } else {
                $conn->rollBack();
                echo json_encode(["message" => "Unable to save loan"]);
            }
        } catch(Exception $e) {
            $conn->rollBack();
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["message" => "Incomplete data"]);
    }
}

// ... existing functions ...

function getLoans($conn) {
    $query = "SELECT * FROM loans ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($loans);
}

function getLoanDetails($conn) {
    $loan_id = isset($_GET['id']) ? $_GET['id'] : die();
    $query = "SELECT * FROM loans WHERE id = ? LIMIT 0,1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $loan_id);
    $stmt->execute();
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    if($loan) {
        $q_pmt = "SELECT * FROM payments WHERE loan_id = ? ORDER BY period_num ASC";
        $s_pmt = $conn->prepare($q_pmt);
        $s_pmt->bindParam(1, $loan_id);
        $s_pmt->execute();
        $payments = $s_pmt->fetchAll(PDO::FETCH_ASSOC);
        $loan['schedule'] = $payments;
        echo json_encode($loan);
    } else {
        echo json_encode(["message" => "Loan not found"]);
    }
}

function updatePayment($conn) {
    $data = json_decode(file_get_contents("php://input"));
    if(!empty($data->payment_id)) {
        $query = "UPDATE payments SET status = :status, paid_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':status', $data->status);
        $stmt->bindParam(':id', $data->payment_id);
        if($stmt->execute()) {
            echo json_encode(["message" => "Payment updated"]);
        } else {
            echo json_encode(["message" => "Unable to update payment"]);
        }
    } else {
        echo json_encode(["message" => "Invalid ID"]);
    }
}

function deleteLoan($conn) {
    $loan_id = isset($_GET['id']) ? $_GET['id'] : die();
    $query = "DELETE FROM loans WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $loan_id);
    if($stmt->execute()) {
        echo json_encode(["message" => "Loan deleted"]);
    } else {
        echo json_encode(["message" => "Unable to delete loan"]);
    }
}

// --- NEW FINANCE FUNCTIONS ---

function getFinanceSummary($conn) {
    // Total Principal Out
    $q1 = "SELECT SUM(principal) as total_principal FROM loans WHERE status = 'active'";
    $s1 = $conn->prepare($q1);
    $s1->execute();
    $r1 = $s1->fetch(PDO::FETCH_ASSOC);

    // Total Payments Collected (Revenue)
    $q2 = "SELECT 
            SUM(amount) as total_revenue,
            SUM(principal_paid) as total_principal_returned,
            SUM(interest_paid) as total_interest_profit
           FROM payments WHERE status = 1";
    $s2 = $conn->prepare($q2);
    $s2->execute();
    $r2 = $s2->fetch(PDO::FETCH_ASSOC);

    // Manual Transactions
    $q3 = "SELECT 
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as other_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses
           FROM transactions";
    $s3 = $conn->prepare($q3);
    $s3->execute();
    $r3 = $s3->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "active_capital" => $r1['total_principal'] ? (float)$r1['total_principal'] : 0,
        "total_revenue" => $r2['total_revenue'] ? (float)$r2['total_revenue'] : 0,
        "principal_returned" => $r2['total_principal_returned'] ? (float)$r2['total_principal_returned'] : 0,
        "interest_profit" => $r2['total_interest_profit'] ? (float)$r2['total_interest_profit'] : 0,
        "other_income" => $r3['other_income'] ? (float)$r3['other_income'] : 0,
        "total_expenses" => $r3['total_expenses'] ? (float)$r3['total_expenses'] : 0,
        "net_profit" => ($r2['total_interest_profit'] + $r3['other_income']) - $r3['total_expenses']
    ]);
}

function addTransaction($conn) {
    $data = json_decode(file_get_contents("php://input"));
    if(!empty($data->type) && !empty($data->amount)) {
        $query = "INSERT INTO transactions SET 
                    type = :type,
                    category = :category,
                    amount = :amount,
                    description = :description,
                    transaction_date = :transaction_date";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':type', $data->type);
        $stmt->bindParam(':category', $data->category);
        $stmt->bindParam(':amount', $data->amount);
        $stmt->bindParam(':description', $data->description);
        $stmt->bindParam(':transaction_date', $data->transaction_date);

        if($stmt->execute()) {
            echo json_encode(["message" => "Transaction recorded"]);
        } else {
            echo json_encode(["message" => "Error"]);
        }
    }
}

function getTransactions($conn) {
    $query = "SELECT * FROM transactions ORDER BY transaction_date DESC, created_at DESC LIMIT 50";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>
