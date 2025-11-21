<?php
session_start();

// Initialize game if not started
if (!isset($_SESSION['game_started'])) {
    $_SESSION['game_started'] = true;
    $_SESSION['selected_briefcase'] = null;
    $_SESSION['briefcases'] = [];
    $_SESSION['eliminated'] = [];
    $_SESSION['round'] = 1;
    $_SESSION['cases_this_round'] = 0;
    $_SESSION['cases_to_eliminate'] = 6; // Start with 6 cases per round
    $_SESSION['offer_history'] = [];
    $_SESSION['banker_offer'] = null;
    $_SESSION['game_over'] = false;
    $_SESSION['final_decision'] = null;
    $_SESSION['volatile_events'] = [];
    $_SESSION['bluff_offers'] = [];
    $_SESSION['revealed_values'] = [];
    $_SESSION['round_progression'] = [];
    $_SESSION['player_choice'] = null;
    
    // Standard Deal or No Deal values (in thousands)
    $values = [0.01, 1, 5, 10, 25, 50, 75, 100, 200, 300, 400, 500, 750, 1000, 5000, 10000, 25000, 50000, 75000, 100000, 200000, 300000, 400000, 500000, 750000, 1000000];
    
    // Shuffle and assign to 26 briefcases
    shuffle($values);
    
    for ($i = 1; $i <= 26; $i++) {
        $_SESSION['briefcases'][$i] = [
            'value' => $values[$i - 1],
            'status' => 'closed', // closed, opened, selected
            'symbol' => getBriefcaseSymbol($i)
        ];
    }
}

// Handle game actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'select_briefcase':
            if ($_SESSION['selected_briefcase'] === null && isset($_GET['case'])) {
                $_SESSION['selected_briefcase'] = (int)$_GET['case'];
                $_SESSION['briefcases'][$_SESSION['selected_briefcase']]['status'] = 'selected';
            }
            break;
            
        case 'eliminate':
            if (isset($_GET['case']) && $_SESSION['selected_briefcase'] !== null) {
                $caseNum = (int)$_GET['case'];
                if ($caseNum != $_SESSION['selected_briefcase'] && $_SESSION['briefcases'][$caseNum]['status'] == 'closed') {
                    $_SESSION['briefcases'][$caseNum]['status'] = 'opened';
                    $_SESSION['eliminated'][] = $caseNum;
                    $_SESSION['cases_this_round']++;
                    
                    // Add to revealed values for progressive revelation
                    $_SESSION['revealed_values'][] = [
                        'case' => $caseNum,
                        'value' => $_SESSION['briefcases'][$caseNum]['value']
                    ];
                    
                    // Check if round is complete
                    if ($_SESSION['cases_this_round'] >= $_SESSION['cases_to_eliminate']) {
                        // Check if only selected case remains
                        $remaining_count = 0;
                        foreach ($_SESSION['briefcases'] as $case) {
                            if ($case['status'] == 'closed' || $case['status'] == 'selected') {
                                $remaining_count++;
                            }
                        }
                        
                        if ($remaining_count <= 1) {
                            // Final round - generate final offer
                            generateBankerOffer();
                        } else {
                            // Generate banker offer
                            generateBankerOffer();
                            // Trigger volatile market event
                            triggerVolatileEvent();
                        }
                    }
                }
            }
            break;
            
        case 'deal':
            $_SESSION['game_over'] = true;
            $_SESSION['final_decision'] = 'deal';
            $_SESSION['player_choice'] = 'deal';
            break;
            
        case 'no_deal':
            $_SESSION['banker_offer'] = null;
            
            // Check if only selected case remains
            $remaining_count = 0;
            foreach ($_SESSION['briefcases'] as $case) {
                if ($case['status'] == 'closed' || $case['status'] == 'selected') {
                    $remaining_count++;
                }
            }
            
            if ($remaining_count <= 1) {
                // Game over - final reveal
                $_SESSION['game_over'] = true;
                $_SESSION['final_decision'] = 'final_reveal';
            } else {
                $_SESSION['cases_this_round'] = 0;
                $_SESSION['round']++;
                
                // Dynamic round progression - adjust cases to eliminate
                adjustRoundProgression();
                
                // Progressive value revelation - reveal some values
                progressiveRevelation();
            }
            
            $_SESSION['player_choice'] = 'no_deal';
            break;
            
        case 'final_reveal':
            $_SESSION['game_over'] = true;
            $_SESSION['final_decision'] = 'final_reveal';
            break;
            
        case 'restart':
            session_destroy();
            header('Location: index.php');
            exit;
    }
}

function getBriefcaseSymbol($number) {
    // All briefcases use the same briefcase emoji
    return '💼';
}

function generateBankerOffer() {
    $remaining_cases = [];
    $selected_value = null;
    
    foreach ($_SESSION['briefcases'] as $num => $case) {
        if ($case['status'] == 'closed') {
            $remaining_cases[] = $case['value'];
        } elseif ($case['status'] == 'selected') {
            $selected_value = $case['value'];
        }
    }
    
    if (count($remaining_cases) == 0) return;
    
    // Calculate average
    $average = array_sum($remaining_cases) / count($remaining_cases);
    
    // Algorithmic banker calculation with multiple variables
    $round_factor = 1 - ($_SESSION['round'] * 0.08); // Banker gets more generous in later rounds
    $volatility_factor = calculateVolatility($remaining_cases);
    $pressure_factor = calculatePressureFactor();
    $bluff_factor = calculateBluffFactor();
    
    // Base offer
    $base_offer = $average * $round_factor;
    
    // Apply volatility (if high-value cases remain, offer is lower %)
    $volatility_modifier = 1 - ($volatility_factor * 0.15);
    
    // Apply pressure (if few cases remain, offer increases)
    $cases_remaining = count($remaining_cases);
    $pressure_modifier = 1 + ((27 - $cases_remaining) / 27) * 0.3;
    
    // Strategic bluff offer
    if (rand(1, 100) <= 30 && $_SESSION['round'] >= 3) {
        // Banker bluffs with a lower offer
        $bluff_modifier = 0.7;
        $offer = $base_offer * $volatility_modifier * $pressure_modifier * $bluff_modifier;
        $_SESSION['bluff_offers'][] = [
            'round' => $_SESSION['round'],
            'offer' => $offer,
            'reason' => 'bluff'
        ];
    } else {
        $offer = $base_offer * $volatility_modifier * $pressure_modifier;
    }
    
    // Ensure offer is reasonable
    $min_offer = min($remaining_cases) * 0.8;
    $max_offer = max($remaining_cases) * 1.2;
    $offer = max($min_offer, min($max_offer, $offer));
    
    $_SESSION['banker_offer'] = round($offer, 2);
    $_SESSION['offer_history'][] = [
        'round' => $_SESSION['round'],
        'offer' => $_SESSION['banker_offer'],
        'timestamp' => time()
    ];
}

function calculateVolatility($values) {
    if (count($values) < 2) return 0.5;
    sort($values);
    $median = $values[floor(count($values) / 2)];
    $max = max($values);
    $min = min($values);
    
    if ($max - $min == 0) return 0;
    
    // High volatility if big range and high max value
    $volatility = ($max - $min) / ($max + $min);
    if ($max > 500000) $volatility *= 1.5;
    
    return min(1, $volatility);
}

function calculatePressureFactor() {
    $remaining = 26 - count($_SESSION['eliminated']) - 1; // -1 for selected
    if ($remaining <= 5) return 0.9; // High pressure
    if ($remaining <= 10) return 0.7; // Medium pressure
    return 0.5; // Low pressure
}

function calculateBluffFactor() {
    // Banker more likely to bluff if player rejected good offers
    if (count($_SESSION['offer_history']) >= 2) {
        $recent_offers = array_slice($_SESSION['offer_history'], -2);
        $trend = $recent_offers[1]['offer'] > $recent_offers[0]['offer'];
        return $trend ? 0.8 : 0.3;
    }
    return 0.5;
}

function triggerVolatileEvent() {
    // 25% chance of volatile market event
    if (rand(1, 100) <= 25) {
        $event_types = [
            'market_crash' => ['name' => 'Market Crash!', 'multiplier' => 0.8, 'icon' => '📉'],
            'market_surge' => ['name' => 'Market Surge!', 'multiplier' => 1.2, 'icon' => '📈'],
            'inflation' => ['name' => 'Inflation Alert!', 'multiplier' => 1.1, 'icon' => '💰'],
            'deflation' => ['name' => 'Deflation Wave!', 'multiplier' => 0.9, 'icon' => '💸']
        ];
        
        $event = array_rand($event_types);
        $event_data = $event_types[$event];
        
        // Apply multiplier to remaining closed briefcases
        foreach ($_SESSION['briefcases'] as $num => &$case) {
            if ($case['status'] == 'closed' || $case['status'] == 'selected') {
                $case['value'] = round($case['value'] * $event_data['multiplier'], 2);
            }
        }
        
        $_SESSION['volatile_events'][] = [
            'round' => $_SESSION['round'],
            'type' => $event,
            'name' => $event_data['name'],
            'icon' => $event_data['icon'],
            'multiplier' => $event_data['multiplier']
        ];
    }
}

function adjustRoundProgression() {
    // Dynamic round structure - non-linear progression
    $cases_remaining = 26 - count($_SESSION['eliminated']) - 1;
    
    if ($cases_remaining <= 1) {
        $_SESSION['cases_to_eliminate'] = 0; // Final round
    } elseif ($cases_remaining <= 5) {
        $_SESSION['cases_to_eliminate'] = 1; // Eliminate 1 at a time
    } elseif ($cases_remaining <= 10) {
        $_SESSION['cases_to_eliminate'] = 2; // Eliminate 2 at a time
    } elseif ($_SESSION['round'] <= 3) {
        $_SESSION['cases_to_eliminate'] = 6; // Early rounds: 6 cases
    } else {
        $_SESSION['cases_to_eliminate'] = 3; // Mid rounds: 3 cases
    }
    
    // Mid-game value reassignment (rare)
    if ($_SESSION['round'] == 4 && rand(1, 100) <= 20) {
        $closed_cases = [];
        foreach ($_SESSION['briefcases'] as $num => $case) {
            if ($case['status'] == 'closed' || $case['status'] == 'selected') {
                $closed_cases[] = $num;
            }
        }
        
        if (count($closed_cases) > 2) {
            shuffle($closed_cases);
            // Swap values between two random closed cases
            $case1 = $closed_cases[0];
            $case2 = $closed_cases[1];
            $temp = $_SESSION['briefcases'][$case1]['value'];
            $_SESSION['briefcases'][$case1]['value'] = $_SESSION['briefcases'][$case2]['value'];
            $_SESSION['briefcases'][$case2]['value'] = $temp;
            
            $_SESSION['round_progression'][] = [
                'round' => $_SESSION['round'],
                'event' => 'value_reassignment',
                'message' => 'Banker rearranged some briefcases!'
            ];
        }
    }
}

function progressiveRevelation() {
    // Reveal some values gradually
    if (count($_SESSION['revealed_values']) < count($_SESSION['eliminated'])) {
        $revealed_count = floor(count($_SESSION['eliminated']) / 3);
        if ($revealed_count > 0 && count($_SESSION['revealed_values']) < $revealed_count) {
            // This is handled when cases are eliminated
        }
    }
}

function formatMoney($amount) {
    if ($amount >= 1000) {
        return '$' . number_format($amount / 1000, ($amount >= 100) ? 0 : 1) . 'K';
    }
    return '$' . number_format($amount, 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal or No Deal: High-Stakes Negotiation</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Deal or No Deal</h1>
            <h2>High-Stakes Negotiation</h2>
        </header>

        <?php if (!$_SESSION['game_over']): ?>
            <div class="game-status">
                <div class="status-item">
                    <h3>Round</h3>
                    <p><?php echo $_SESSION['round']; ?></p>
                </div>
                <div class="status-item">
                    <h3>This Round</h3>
                    <p><?php echo $_SESSION['cases_this_round']; ?>/<?php echo $_SESSION['cases_to_eliminate']; ?></p>
                </div>
                <?php if ($_SESSION['selected_briefcase']): ?>
                    <div class="status-item">
                        <h3>Your Briefcase</h3>
                        <p>#<?php echo $_SESSION['selected_briefcase']; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (count($_SESSION['volatile_events']) > 0): ?>
                    <div class="events-panel">
                        <?php $last_event = end($_SESSION['volatile_events']); ?>
                        <div class="event-item">
                            <?php echo $last_event['icon']; ?> <?php echo $last_event['name']; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php
            // Get all possible values
            $all_values = [0.01, 1, 5, 10, 25, 50, 75, 100, 200, 300, 400, 500, 750, 1000, 5000, 10000, 25000, 50000, 75000, 100000, 200000, 300000, 400000, 500000, 750000, 1000000];
            
            // Get eliminated values
            $eliminated_values = [];
            foreach ($_SESSION['briefcases'] as $num => $case) {
                if ($case['status'] == 'opened') {
                    $eliminated_values[] = $case['value'];
                }
            }
            ?>
            
            <div class="values-board">
                <h3>Prize Values</h3>
                <div class="values-list">
                    <?php foreach ($all_values as $value): 
                        $is_eliminated = in_array($value, $eliminated_values);
                        $class = $is_eliminated ? 'eliminated' : 'available';
                    ?>
                        <div class="value-item <?php echo $class; ?>">
                            <?php echo formatMoney($value * 1000); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($_SESSION['banker_offer'] !== null): ?>
                <div class="banker-offer">
                    <h2>The Banker's Offer</h2>
                    <div class="offer-amount"><?php echo formatMoney($_SESSION['banker_offer'] * 1000); ?></div>
                    
                    <?php if (count($_SESSION['bluff_offers']) > 0 && end($_SESSION['bluff_offers'])['round'] == $_SESSION['round']): ?>
                        <p style="color: #ff6b6b; margin: 10px 0; font-size: 0.9em;">⚠️ Banker might be bluffing!</p>
                    <?php endif; ?>
                    
                    <div class="offer-buttons">
                        <a href="?action=deal" class="btn btn-deal">DEAL</a>
                        <?php 
                        $remaining_count = 0;
                        foreach ($_SESSION['briefcases'] as $case) {
                            if ($case['status'] == 'closed' || $case['status'] == 'selected') {
                                $remaining_count++;
                            }
                        }
                        if ($remaining_count <= 1): ?>
                            <a href="?action=final_reveal" class="btn btn-no-deal">KEEP BRIEFCASE</a>
                        <?php else: ?>
                            <a href="?action=no_deal" class="btn btn-no-deal">NO DEAL</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($_SESSION['selected_briefcase'] === null): ?>
                <div class="instruction">
                    <h3>Select Your Briefcase!</h3>
                    <p>Choose one briefcase to keep for the entire game.</p>
                </div>
            <?php elseif ($_SESSION['banker_offer'] === null): 
                $remaining_count = 0;
                foreach ($_SESSION['briefcases'] as $case) {
                    if ($case['status'] == 'closed' || $case['status'] == 'selected') {
                        $remaining_count++;
                    }
                }
                
                if ($remaining_count <= 1): ?>
                    <div class="instruction final-round">
                        <h3>Final Round!</h3>
                        <p>Last briefcase remaining. Banker's final offer or keep your briefcase.</p>
                    </div>
                <?php else: ?>
                    <div class="instruction">
                        <h3>Eliminate <?php echo $_SESSION['cases_to_eliminate'] - $_SESSION['cases_this_round']; ?> More Briefcase(s)</h3>
                        <p>Click briefcases to eliminate and reveal values.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="stage-area">
                <div class="curtain"></div>
                <div class="briefcases-container">
                <?php for ($i = 1; $i <= 26; $i++): 
                    $case = $_SESSION['briefcases'][$i];
                    $class = 'briefcase-item ' . $case['status'];
                    
                    if ($case['status'] == 'opened') {
                        $class .= ' opened';
                    } elseif ($case['status'] == 'selected') {
                        $class .= ' selected';
                    }
                ?>
                    <div class="<?php echo $class; ?>">
                        <?php if ($case['status'] == 'opened'): ?>
                            <div class="briefcase-opened">
                                <div class="briefcase-number"><?php echo $i; ?></div>
                                <div class="briefcase-value"><?php echo formatMoney($case['value'] * 1000); ?></div>
                            </div>
                        <?php else: ?>
                            <a href="?action=<?php echo ($_SESSION['selected_briefcase'] === null) ? 'select_briefcase' : 'eliminate'; ?>&case=<?php echo $i; ?>" 
                               class="briefcase-link">
                                <span class="briefcase-emoji"><?php echo $case['symbol']; ?></span>
                                <span class="briefcase-number-label"><?php echo $i; ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="game-over">
                <h2>Game Over</h2>
                
                <?php if ($_SESSION['final_decision'] == 'deal'): ?>
                    <div class="result">
                        <p>You accepted the deal!</p>
                        <div class="final-amount"><?php echo formatMoney($_SESSION['banker_offer'] * 1000); ?></div>
                    </div>
                <?php else: ?>
                    <div class="result">
                        <p>Your briefcase (#<?php echo $_SESSION['selected_briefcase']; ?>) contains:</p>
                        <div class="final-amount">
                            <?php echo formatMoney($_SESSION['briefcases'][$_SESSION['selected_briefcase']]['value'] * 1000); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (count($_SESSION['offer_history']) > 0): ?>
                    <div class="offer-history">
                        <h3>Offer History</h3>
                        <table>
                            <tr>
                                <th>Round</th>
                                <th>Offer</th>
                            </tr>
                            <?php foreach ($_SESSION['offer_history'] as $offer): ?>
                                <tr>
                                    <td><?php echo $offer['round']; ?></td>
                                    <td><?php echo formatMoney($offer['offer'] * 1000); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
                
                <a href="?action=restart" class="btn btn-restart">Play Again</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
