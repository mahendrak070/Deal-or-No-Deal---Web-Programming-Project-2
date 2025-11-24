// Updated by Joshika
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
    
    // Shuffle and assign it to 26 briefcases
    shuffle($values);
    
    for ($i = 1; $i <= 26; $i++) {
        $_SESSION['briefcases'][$i] = [
            'value' => $values[$i - 1],
            'status' => 'closed', // closed, opened, selected
            'symbol' => getBriefcaseSymbol($i)
        ];
    }
    
    // Initialize crazy features variables
    $_SESSION['power_ups'] = [];
    $_SESSION['streak_count'] = 0;
    $_SESSION['double_or_nothing_available'] = false;
    $_SESSION['mystery_swap_available'] = false;
    $_SESSION['lucky_briefcase'] = null;
    $_SESSION['bonus_round'] = false;
    $_SESSION['jackpot_multiplier'] = 1;
    $_SESSION['surprise_events'] = [];
    $_SESSION['used_power_ups'] = [];
}

// Initialize variables if they don't exist (for existing sessions) - MUST be before any code uses them
if (!isset($_SESSION['streak_count'])) {
    $_SESSION['streak_count'] = 0;
}
if (!isset($_SESSION['double_or_nothing_available'])) {
    $_SESSION['double_or_nothing_available'] = false;
}
if (!isset($_SESSION['mystery_swap_available'])) {
    $_SESSION['mystery_swap_available'] = false;
}
if (!isset($_SESSION['lucky_briefcase'])) {
    $_SESSION['lucky_briefcase'] = null;
}
if (!isset($_SESSION['bonus_round'])) {
    $_SESSION['bonus_round'] = false;
}
if (!isset($_SESSION['jackpot_multiplier'])) {
    $_SESSION['jackpot_multiplier'] = 1;
}
if (!isset($_SESSION['surprise_events'])) {
    $_SESSION['surprise_events'] = [];
}
if (!isset($_SESSION['used_power_ups'])) {
    $_SESSION['used_power_ups'] = [];
}
if (!isset($_SESSION['power_ups'])) {
    $_SESSION['power_ups'] = [];
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
                            // Trigger crazy features
                            triggerCrazyFeatures();
                        }
                    }
                }
            }
            break;
            
        case 'deal':
            // Apply jackpot multiplier if active
            if (isset($_SESSION['jackpot_multiplier']) && $_SESSION['jackpot_multiplier'] > 1) {
                $_SESSION['banker_offer'] = applyJackpotMultiplier($_SESSION['banker_offer']);
            }
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
                
                // Increase streak for rejecting offers
                $_SESSION['streak_count']++;
            }
            
            $_SESSION['player_choice'] = 'no_deal';
            break;
            
        case 'final_reveal':
            $_SESSION['game_over'] = true;
            $_SESSION['final_decision'] = 'final_reveal';
            break;
            
        case 'mystery_swap':
            if ($_SESSION['mystery_swap_available']) {
                performMysterySwap();
                $_SESSION['mystery_swap_available'] = false;
                $_SESSION['used_power_ups'][] = 'mystery_swap';
            }
            break;
            
        case 'double_or_nothing':
            if ($_SESSION['double_or_nothing_available'] && $_SESSION['banker_offer'] !== null) {
                performDoubleOrNothing();
            }
            break;
            
        case 'activate_lucky':
            if ($_SESSION['lucky_briefcase'] !== null) {
                activateLuckyBriefcase();
            }
            break;
            
        case 'bonus_round':
            if ($_SESSION['bonus_round']) {
                activateBonusRound();
            }
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
    
    // Streak bonus - if player rejected multiple offers
    if (isset($_SESSION['streak_count']) && $_SESSION['streak_count'] >= 3) {
        $streak_bonus = 1 + ($_SESSION['streak_count'] * 0.05); // 5% per streak
        $offer *= $streak_bonus;
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

// CRAZY FEATURES FUNCTIONS

function triggerCrazyFeatures() {
    // 30% chance for any crazy feature
    $roll = rand(1, 100);
    
    if ($roll <= 10) {
        // Mystery Swap - 10% chance
        $_SESSION['mystery_swap_available'] = true;
        $_SESSION['surprise_events'][] = [
            'type' => 'mystery_swap',
            'message' => '🎲 Mystery Swap Available! Swap your briefcase with another!',
            'icon' => '🎲'
        ];
    } elseif ($roll <= 20) {
        // Double or Nothing - 10% chance (only if offer exists)
        if ($_SESSION['banker_offer'] !== null) {
            $_SESSION['double_or_nothing_available'] = true;
            $_SESSION['surprise_events'][] = [
                'type' => 'double_or_nothing',
                'message' => '💰 Double or Nothing! Double your winnings or lose everything!',
                'icon' => '💰'
            ];
        }
    } elseif ($roll <= 30) {
        // Lucky Briefcase - 10% chance
        $closed_cases = [];
        foreach ($_SESSION['briefcases'] as $num => $case) {
            if ($case['status'] == 'closed' && $num != $_SESSION['selected_briefcase']) {
                $closed_cases[] = $num;
            }
        }
        if (count($closed_cases) > 0) {
            $_SESSION['lucky_briefcase'] = $closed_cases[array_rand($closed_cases)];
            $_SESSION['surprise_events'][] = [
                'type' => 'lucky',
                'message' => '🍀 Lucky Briefcase Found! Briefcase #' . $_SESSION['lucky_briefcase'] . ' has a bonus!',
                'icon' => '🍀'
            ];
        }
    } elseif ($roll <= 35) {
        // Bonus Round - 5% chance
        $_SESSION['bonus_round'] = true;
        $_SESSION['surprise_events'][] = [
            'type' => 'bonus',
            'message' => '🎁 BONUS ROUND! Eliminate one extra briefcase for free!',
            'icon' => '🎁'
        ];
    } elseif ($roll <= 45) {
        // Jackpot Multiplier - 10% chance
        $_SESSION['jackpot_multiplier'] = 1.5 + (rand(1, 5) * 0.5); // 1.5x to 3.5x
        $_SESSION['surprise_events'][] = [
            'type' => 'jackpot',
            'message' => '🎰 JACKPOT MULTIPLIER! All winnings x' . $_SESSION['jackpot_multiplier'] . '!',
            'icon' => '🎰'
        ];
    } elseif ($roll <= 50) {
        // Streak Bonus
        if (isset($_SESSION['streak_count']) && $_SESSION['streak_count'] >= 3) {
            $_SESSION['surprise_events'][] = [
                'type' => 'streak',
                'message' => '🔥 STREAK BONUS! ' . $_SESSION['streak_count'] . ' consecutive No Deals! +25% to next offer!',
                'icon' => '🔥'
            ];
        }
    } elseif ($roll <= 60) {
        // Surprise Reveal - reveal 2 briefcases at once
        $_SESSION['surprise_events'][] = [
            'type' => 'reveal',
            'message' => '✨ SURPRISE! Banker reveals one extra briefcase next round!',
            'icon' => '✨'
        ];
        // Reduce cases to eliminate by 1
        if ($_SESSION['cases_to_eliminate'] > 1) {
            $_SESSION['cases_to_eliminate']--;
        }
    } elseif ($roll <= 65) {
        // Power Briefcase - one briefcase doubles in value
        $closed_cases = [];
        foreach ($_SESSION['briefcases'] as $num => $case) {
            if (($case['status'] == 'closed' || $case['status'] == 'selected') && $num != $_SESSION['selected_briefcase']) {
                $closed_cases[] = $num;
            }
        }
        if (count($closed_cases) > 0) {
            $power_case = $closed_cases[array_rand($closed_cases)];
            $_SESSION['briefcases'][$power_case]['value'] *= 2;
            $_SESSION['surprise_events'][] = [
                'type' => 'power',
                'message' => '⚡ POWER BRIEFCASE! Briefcase #' . $power_case . ' value doubled!',
                'icon' => '⚡'
            ];
        }
    }
}

function performMysterySwap() {
    $closed_cases = [];
    foreach ($_SESSION['briefcases'] as $num => $case) {
        if ($case['status'] == 'closed' && $num != $_SESSION['selected_briefcase']) {
            $closed_cases[] = $num;
        }
    }
    
    if (count($closed_cases) > 0) {
        $swap_case = $closed_cases[array_rand($closed_cases)];
        
        // Swap values
        $temp = $_SESSION['briefcases'][$_SESSION['selected_briefcase']]['value'];
        $_SESSION['briefcases'][$_SESSION['selected_briefcase']]['value'] = $_SESSION['briefcases'][$swap_case]['value'];
        $_SESSION['briefcases'][$swap_case]['value'] = $temp;
        
        $_SESSION['surprise_events'][] = [
            'type' => 'swap_done',
            'message' => '🎲 Mystery Swap Complete! Your briefcase swapped with #' . $swap_case . '!',
            'icon' => '🎲'
        ];
    }
}

function performDoubleOrNothing() {
    if (rand(1, 2) == 1) {
        // Win - double the offer
        $_SESSION['banker_offer'] *= 2;
        $_SESSION['double_or_nothing_available'] = false;
        $_SESSION['surprise_events'][] = [
            'type' => 'double_win',
            'message' => '🎉 DOUBLE OR NOTHING WON! Offer doubled to ' . formatMoney($_SESSION['banker_offer'] * 1000) . '!',
            'icon' => '🎉'
        ];
    } else {
        // Lose - no deal, continue game
        $_SESSION['banker_offer'] = null;
        $_SESSION['double_or_nothing_available'] = false;
        $_SESSION['cases_this_round'] = 0;
        $_SESSION['round']++;
        adjustRoundProgression();
        $_SESSION['surprise_events'][] = [
            'type' => 'double_lose',
            'message' => '😱 DOUBLE OR NOTHING LOST! No deal, continue game!',
            'icon' => '😱'
        ];
    }
}

function activateLuckyBriefcase() {
    if ($_SESSION['lucky_briefcase'] !== null) {
        // Add bonus to banker's next offer
        if ($_SESSION['banker_offer'] !== null) {
            $_SESSION['banker_offer'] *= 1.5; // 50% bonus
        }
        
        $_SESSION['surprise_events'][] = [
            'type' => 'lucky_activated',
            'message' => '🍀 Lucky Briefcase activated! +50% bonus to current offer!',
            'icon' => '🍀'
        ];
        
        $_SESSION['lucky_briefcase'] = null;
    }
}

function activateBonusRound() {
    // Give player one free briefcase elimination
    $_SESSION['bonus_round'] = false;
    $_SESSION['cases_to_eliminate']--; // Reduce by 1 for this round
    
    $_SESSION['surprise_events'][] = [
        'type' => 'bonus_activated',
        'message' => '🎁 Bonus Round! Eliminate one fewer briefcase this round!',
        'icon' => '🎁'
    ];
}

// Apply jackpot multiplier to final winnings
function applyJackpotMultiplier($amount) {
    return $amount * $_SESSION['jackpot_multiplier'];
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
                
                <?php if (isset($_SESSION['streak_count']) && $_SESSION['streak_count'] > 0): ?>
                    <div class="status-item">
                        <h3>Streak</h3>
                        <p><?php echo $_SESSION['streak_count']; ?> 🔥</p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['jackpot_multiplier']) && $_SESSION['jackpot_multiplier'] > 1): ?>
                    <div class="status-item">
                        <h3>Multiplier</h3>
                        <p>x<?php echo $_SESSION['jackpot_multiplier']; ?> 🎰</p>
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

            <?php if (isset($_SESSION['surprise_events']) && count($_SESSION['surprise_events']) > 0): ?>
                <div class="surprise-events">
                    <?php foreach (array_slice($_SESSION['surprise_events'], -3) as $event): ?>
                        <div class="surprise-item surprise-<?php echo $event['type']; ?>">
                            <?php echo $event['icon']; ?> <?php echo htmlspecialchars($event['message']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($_SESSION['banker_offer'] !== null): ?>
                <div class="banker-offer">
                    <h2>The Banker's Offer</h2>
                    <div class="offer-amount"><?php echo formatMoney($_SESSION['banker_offer'] * 1000); ?></div>
                    
                    <?php if (count($_SESSION['bluff_offers']) > 0 && end($_SESSION['bluff_offers'])['round'] == $_SESSION['round']): ?>
                        <p style="color: #ff6b6b; margin: 10px 0; font-size: 0.9em;">⚠️ Banker might be bluffing!</p>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['double_or_nothing_available']) && $_SESSION['double_or_nothing_available']): ?>
                        <a href="?action=double_or_nothing" class="btn btn-crazy" style="background: #ff6b00; margin-bottom: 10px; display: block; text-align: center;">💰 DOUBLE OR NOTHING</a>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['lucky_briefcase']) && $_SESSION['lucky_briefcase'] !== null): ?>
                        <a href="?action=activate_lucky" class="btn btn-crazy" style="background: #4CAF50; margin-bottom: 10px; display: block; text-align: center;">🍀 ACTIVATE LUCKY BRIEFCASE #<?php echo $_SESSION['lucky_briefcase']; ?></a>
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
            
            <?php if (isset($_SESSION['mystery_swap_available']) && $_SESSION['mystery_swap_available']): ?>
                <div class="power-up-panel">
                    <h3>🎲 Mystery Swap Available!</h3>
                    <p>Swap your briefcase with a random closed briefcase!</p>
                    <a href="?action=mystery_swap" class="btn btn-crazy" style="background: #9c27b0; margin-top: 10px; display: inline-block;">ACTIVATE MYSTERY SWAP</a>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['bonus_round']) && $_SESSION['bonus_round']): ?>
                <div class="power-up-panel">
                    <h3>🎁 Bonus Round Active!</h3>
                    <p>Eliminate one fewer briefcase this round!</p>
                    <a href="?action=bonus_round" class="btn btn-crazy" style="background: #ff9800; margin-top: 10px; display: inline-block;">ACTIVATE BONUS ROUND</a>
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
                    
                    // Add lucky briefcase class
                    if (isset($_SESSION['lucky_briefcase']) && $_SESSION['lucky_briefcase'] == $i) {
                        $class .= ' lucky-briefcase';
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
                        <?php 
                        $final_winnings = $_SESSION['banker_offer'];
                        if (isset($_SESSION['jackpot_multiplier']) && $_SESSION['jackpot_multiplier'] > 1) {
                            $final_winnings = applyJackpotMultiplier($final_winnings);
                            echo '<p style="font-size: 0.8em; color: #ffd700;">Jackpot Multiplier Applied: x' . $_SESSION['jackpot_multiplier'] . '</p>';
                        }
                        ?>
                        <div class="final-amount"><?php echo formatMoney($final_winnings * 1000); ?></div>
                    </div>
                <?php else: ?>
                    <div class="result">
                        <p>Your briefcase (#<?php echo $_SESSION['selected_briefcase']; ?>) contains:</p>
                        <?php 
                        $final_winnings = $_SESSION['briefcases'][$_SESSION['selected_briefcase']]['value'];
                        if (isset($_SESSION['jackpot_multiplier']) && $_SESSION['jackpot_multiplier'] > 1) {
                            $final_winnings = applyJackpotMultiplier($final_winnings);
                            echo '<p style="font-size: 0.8em; color: #ffd700;">Jackpot Multiplier Applied: x' . $_SESSION['jackpot_multiplier'] . '</p>';
                        }
                        ?>
                        <div class="final-amount">
                            <?php echo formatMoney($final_winnings * 1000); ?>
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
