<?php
// Commission Calculator for Forever Young Tours
// Based on the detailed compensation plan provided

class CommissionCalculator {
    private $db;
    
    // Commission rates based on the compensation plan
    const MCA_OVERRIDE_RATE = 7.5;
    const MCA_PERFORMANCE_BONUS_RATE = 5.0;
    const ADVISOR_PERFORMANCE_BONUS_RATE = 5.0;
    const AMBASSADOR_BONUS_RATE = 2.5;
    const LEVEL_2_OVERRIDE_RATE = 10.0;
    const LEVEL_3_OVERRIDE_RATE = 5.0;
    
    // Advisor commission rates by rank
    const ADVISOR_RATES = [
        'certified_advisor' => 30.0,
        'senior_advisor' => 35.0,
        'executive_advisor' => 40.0
    ];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Calculate all commissions for a completed booking
     */
    public function calculateBookingCommissions($booking_id) {
        // Get booking details
        $booking = $this->getBookingDetails($booking_id);
        if (!$booking) {
            throw new Exception("Booking not found");
        }
        
        // Calculate net commission fund (after deductions)
        $net_commission_fund = $this->calculateNetCommissionFund($booking);
        
        $commissions = [];
        
        // 1. MCA Override (7.5%)
        if ($booking['mca_id']) {
            $commissions[] = $this->createCommissionTransaction(
                $booking_id,
                'mca_override',
                $booking['mca_id'],
                null,
                $net_commission_fund,
                self::MCA_OVERRIDE_RATE,
                $booking
            );
        }
        
        // 2. Advisor Direct Sales Commission (30%, 35%, or 40% based on rank)
        if ($booking['advisor_id']) {
            $advisor = $this->getAdvisorDetails($booking['advisor_id']);
            $advisor_rate = self::ADVISOR_RATES[$advisor['advisor_rank']] ?? 30.0;
            
            $commissions[] = $this->createCommissionTransaction(
                $booking_id,
                'advisor_direct_sales',
                null,
                $booking['advisor_id'],
                $net_commission_fund,
                $advisor_rate,
                $booking
            );
            
            // 3. Level 2 Override (10%)
            $level2_advisor = $this->getUplineAdvisor($booking['advisor_id'], 2);
            if ($level2_advisor) {
                $commissions[] = $this->createCommissionTransaction(
                    $booking_id,
                    'advisor_level2_override',
                    null,
                    $level2_advisor['id'],
                    $net_commission_fund,
                    self::LEVEL_2_OVERRIDE_RATE,
                    $booking
                );
            }
            
            // 4. Level 3 Override (5%)
            $level3_advisor = $this->getUplineAdvisor($booking['advisor_id'], 3);
            if ($level3_advisor) {
                $commissions[] = $this->createCommissionTransaction(
                    $booking_id,
                    'advisor_level3_override',
                    null,
                    $level3_advisor['id'],
                    $net_commission_fund,
                    self::LEVEL_3_OVERRIDE_RATE,
                    $booking
                );
            }
        }
        
        // Save all commission transactions
        foreach ($commissions as $commission) {
            $this->saveCommissionTransaction($commission);
        }
        
        return $commissions;
    }
    
    /**
     * Calculate net commission fund after deductions
     */
    private function calculateNetCommissionFund($booking) {
        $gross_amount = $booking['total_amount'];
        
        // Deduct direct costs, cancellation fees, refunds, etc.
        // This would be customized based on FYT's cost structure
        $direct_costs = $gross_amount * 0.25; // Example: 25% for direct costs
        $net_commission_fund = $gross_amount - $direct_costs;
        
        return $net_commission_fund;
    }
    
    /**
     * Create commission transaction array
     */
    private function createCommissionTransaction($booking_id, $type, $mca_id, $advisor_id, $net_fund, $rate, $booking) {
        $commission_amount = ($net_fund * $rate) / 100;
        
        return [
            'booking_id' => $booking_id,
            'transaction_type' => $type,
            'mca_id' => $mca_id,
            'advisor_id' => $advisor_id,
            'gross_commission' => $booking['total_amount'],
            'net_commission_fund' => $net_fund,
            'commission_percentage' => $rate,
            'commission_amount' => $commission_amount,
            'booking_amount' => $booking['total_amount'],
            'tour_id' => $booking['tour_id'],
            'tour_date' => $booking['tour_date'],
            'commission_month' => date('Y-m'),
            'payment_due_date' => $this->calculatePaymentDueDate(),
            'status' => 'pending'
        ];
    }
    
    /**
     * Save commission transaction to database
     */
    private function saveCommissionTransaction($commission) {
        $sql = "INSERT INTO commission_transactions (
            booking_id, transaction_type, mca_id, advisor_id, gross_commission,
            net_commission_fund, commission_percentage, commission_amount,
            booking_amount, tour_id, tour_date, commission_month,
            payment_due_date, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $commission['booking_id'],
            $commission['transaction_type'],
            $commission['mca_id'],
            $commission['advisor_id'],
            $commission['gross_commission'],
            $commission['net_commission_fund'],
            $commission['commission_percentage'],
            $commission['commission_amount'],
            $commission['booking_amount'],
            $commission['tour_id'],
            $commission['tour_date'],
            $commission['commission_month'],
            $commission['payment_due_date'],
            $commission['status']
        ]);
    }
    
    /**
     * Get booking details
     */
    private function getBookingDetails($booking_id) {
        $stmt = $this->db->prepare("
            SELECT b.*, t.title as tour_title
            FROM bookings b
            LEFT JOIN tours t ON b.tour_id = t.id
            WHERE b.id = ?
        ");
        $stmt->execute([$booking_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get advisor details including rank
     */
    private function getAdvisorDetails($advisor_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM certified_advisors WHERE id = ?
        ");
        $stmt->execute([$advisor_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get upline advisor at specific level
     */
    private function getUplineAdvisor($advisor_id, $level) {
        $stmt = $this->db->prepare("
            SELECT ca.* FROM certified_advisors ca
            JOIN advisor_network an ON ca.id = an.parent_advisor_id
            WHERE an.child_advisor_id = ? AND an.relationship_level = ? AND an.status = 'active'
        ");
        $stmt->execute([$advisor_id, $level]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calculate payment due date (20th of following month)
     */
    private function calculatePaymentDueDate() {
        $next_month = date('Y-m-20', strtotime('+1 month'));
        return $next_month;
    }
    
    /**
     * Process monthly bonus pools
     */
    public function processMonthlyBonuses($month) {
        // MCA Performance Bonus Pool (5%)
        $this->processMCAPerformanceBonuses($month);
        
        // Advisor Performance Bonus Pool (5%)
        $this->processAdvisorPerformanceBonuses($month);
        
        // Outstanding Achievement Ambassador Bonus Pool (2.5%)
        $this->processAmbassadorBonuses($month);
    }
    
    /**
     * Process MCA performance bonuses
     */
    private function processMCAPerformanceBonuses($month) {
        // Get total net commission fund for the month
        $stmt = $this->db->prepare("
            SELECT SUM(net_commission_fund) as total_fund
            FROM commission_transactions
            WHERE commission_month = ?
        ");
        $stmt->execute([$month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_fund = $result['total_fund'] ?? 0;
        
        $bonus_pool = ($total_fund * self::MCA_PERFORMANCE_BONUS_RATE) / 100;
        
        if ($bonus_pool > 0) {
            // Distribute based on MCA performance metrics
            $this->distributeMCABonuses($bonus_pool, $month);
        }
    }
    
    /**
     * Process advisor performance bonuses
     */
    private function processAdvisorPerformanceBonuses($month) {
        // Similar logic for advisor bonuses
        $stmt = $this->db->prepare("
            SELECT SUM(net_commission_fund) as total_fund
            FROM commission_transactions
            WHERE commission_month = ?
        ");
        $stmt->execute([$month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_fund = $result['total_fund'] ?? 0;
        
        $bonus_pool = ($total_fund * self::ADVISOR_PERFORMANCE_BONUS_RATE) / 100;
        
        if ($bonus_pool > 0) {
            $this->distributeAdvisorBonuses($bonus_pool, $month);
        }
    }
    
    /**
     * Process ambassador bonuses
     */
    private function processAmbassadorBonuses($month) {
        // Logic for outstanding achievement bonuses
        $stmt = $this->db->prepare("
            SELECT SUM(net_commission_fund) as total_fund
            FROM commission_transactions
            WHERE commission_month = ?
        ");
        $stmt->execute([$month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_fund = $result['total_fund'] ?? 0;
        
        $bonus_pool = ($total_fund * self::AMBASSADOR_BONUS_RATE) / 100;
        
        if ($bonus_pool > 0) {
            $this->distributeAmbassadorBonuses($bonus_pool, $month);
        }
    }
    
    /**
     * Distribute MCA bonuses based on performance
     */
    private function distributeMCABonuses($bonus_pool, $month) {
        // Get top performing MCAs for the month
        $stmt = $this->db->prepare("
            SELECT mca_id, SUM(commission_amount) as total_commissions
            FROM commission_transactions
            WHERE commission_month = ? AND mca_id IS NOT NULL
            GROUP BY mca_id
            ORDER BY total_commissions DESC
        ");
        $stmt->execute([$month]);
        $mcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Distribute bonus based on performance ranking
        $total_performance = array_sum(array_column($mcas, 'total_commissions'));
        
        foreach ($mcas as $mca) {
            if ($total_performance > 0) {
                $performance_share = $mca['total_commissions'] / $total_performance;
                $bonus_amount = $bonus_pool * $performance_share;
                
                // Create bonus transaction
                $this->createBonusTransaction($mca['mca_id'], null, 'mca_performance_bonus', $bonus_amount, $month);
            }
        }
    }
    
    /**
     * Distribute advisor bonuses
     */
    private function distributeAdvisorBonuses($bonus_pool, $month) {
        // Similar logic for advisors
        $stmt = $this->db->prepare("
            SELECT advisor_id, SUM(commission_amount) as total_commissions
            FROM commission_transactions
            WHERE commission_month = ? AND advisor_id IS NOT NULL
            GROUP BY advisor_id
            ORDER BY total_commissions DESC
        ");
        $stmt->execute([$month]);
        $advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_performance = array_sum(array_column($advisors, 'total_commissions'));
        
        foreach ($advisors as $advisor) {
            if ($total_performance > 0) {
                $performance_share = $advisor['total_commissions'] / $total_performance;
                $bonus_amount = $bonus_pool * $performance_share;
                
                $this->createBonusTransaction(null, $advisor['advisor_id'], 'advisor_performance_bonus', $bonus_amount, $month);
            }
        }
    }
    
    /**
     * Distribute ambassador bonuses
     */
    private function distributeAmbassadorBonuses($bonus_pool, $month) {
        // Logic for outstanding achievement bonuses
        // This could be based on specific criteria like top recruiters, highest sales, etc.
        
        $stmt = $this->db->prepare("
            SELECT advisor_id, COUNT(*) as recruits_count, SUM(commission_amount) as total_commissions
            FROM commission_transactions ct
            JOIN advisor_network an ON ct.advisor_id = an.parent_advisor_id
            WHERE ct.commission_month = ? AND ct.advisor_id IS NOT NULL
            GROUP BY advisor_id
            HAVING recruits_count >= 5 OR total_commissions >= 10000
            ORDER BY total_commissions DESC, recruits_count DESC
            LIMIT 10
        ");
        $stmt->execute([$month]);
        $ambassadors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($ambassadors) > 0) {
            $bonus_per_ambassador = $bonus_pool / count($ambassadors);
            
            foreach ($ambassadors as $ambassador) {
                $this->createBonusTransaction(null, $ambassador['advisor_id'], 'ambassador_bonus', $bonus_per_ambassador, $month);
            }
        }
    }
    
    /**
     * Create bonus transaction
     */
    private function createBonusTransaction($mca_id, $advisor_id, $type, $amount, $month) {
        $sql = "INSERT INTO commission_transactions (
            booking_id, transaction_type, mca_id, advisor_id, gross_commission,
            net_commission_fund, commission_percentage, commission_amount,
            booking_amount, commission_month, payment_due_date, status, description
        ) VALUES (0, ?, ?, ?, 0, ?, 0, ?, 0, ?, ?, 'pending', 'Monthly bonus distribution')";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $type, $mca_id, $advisor_id, $amount, $amount, $month, $this->calculatePaymentDueDate()
        ]);
    }
}
?>
