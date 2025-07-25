<?php

namespace App\Enums;

enum TxnType: string
{
    case Deposit = 'deposit';
    case Subtract = 'subtract';
    case ManualDeposit = 'manual_deposit';
    case SendMoney = 'send_money';
    case Exchange = 'exchange';
    case Referral = 'referral';
    case SignupBonus = 'signup_bonus';
    case Bonus = 'bonus';
    case Withdraw = 'withdraw';
    case WithdrawAuto = 'withdraw_auto';
    case ReceiveMoney = 'receive_money';
    case Investment = 'investment';
    case Interest = 'interest';
    case Refund = 'refund';
    case RewardRedeem = 'reward_redeem';
    case PortfolioBonus = 'portfolio_bonus';
}
