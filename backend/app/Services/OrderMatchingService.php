<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Order;
use App\Models\User;

class OrderMatchingService
{
    public const COMMISSION_RATE = '0.015';

    public function findMatchingOrder(Order $newOrder): ?Order
    {
        if ($newOrder->side === 'buy') {
            return $this->findMatchingSellOrder($newOrder);
        }

        return $this->findMatchingBuyOrder($newOrder);
    }

    /**
     * @return array{buyOrder: Order, sellOrder: Order, executedPrice: string, amount: string, commission: string}
     */
    public function executeMatch(Order $newOrder, Order $matchedOrder): array
    {
        $newOrder->status = Order::STATUS_FILLED;
        $matchedOrder->status = Order::STATUS_FILLED;

        $newOrder->save();
        $matchedOrder->save();

        if ($newOrder->side === 'buy') {
            $buyOrder = $newOrder;
            $sellOrder = $matchedOrder;
        } else {
            $buyOrder = $matchedOrder;
            $sellOrder = $newOrder;
        }

        $executedPrice = $matchedOrder->price;
        $amount = $newOrder->amount;
        $commission = $this->calculateCommission($amount, $executedPrice);

        return [
            'buyOrder' => $buyOrder,
            'sellOrder' => $sellOrder,
            'executedPrice' => $executedPrice,
            'amount' => $amount,
            'commission' => $commission,
        ];
    }

    public function calculateCommission(string $amount, string $executedPrice): string
    {
        $usdValue = bcmul($amount, $executedPrice, 8);

        return bcmul($usdValue, self::COMMISSION_RATE, 8);
    }

    /**
     * @param  array{buyOrder: Order, sellOrder: Order, executedPrice: string, amount: string, commission: string}  $matchResult
     */
    public function executeTransfers(array $matchResult): void
    {
        $buyOrder = $matchResult['buyOrder'];
        $sellOrder = $matchResult['sellOrder'];
        $amount = $matchResult['amount'];
        $executedPrice = $matchResult['executedPrice'];
        $commission = $matchResult['commission'];
        $symbol = $buyOrder->symbol;

        $usdValue = bcmul($amount, $executedPrice, 8);
        $sellerCredit = bcsub($usdValue, $commission, 8);

        $seller = User::where('id', $sellOrder->user_id)
            ->lockForUpdate()
            ->first();
        $seller->balance = bcadd($seller->balance, $sellerCredit, 8);
        $seller->save();

        $sellerAsset = Asset::where('user_id', $sellOrder->user_id)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();
        $sellerAsset->locked_amount = bcsub($sellerAsset->locked_amount, $amount, 8);
        $sellerAsset->save();

        Asset::firstOrCreate(
            ['user_id' => $buyOrder->user_id, 'symbol' => $symbol],
            ['amount' => '0.00000000', 'locked_amount' => '0.00000000']
        );
        $buyerAsset = Asset::where('user_id', $buyOrder->user_id)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();
        $buyerAsset->amount = bcadd($buyerAsset->amount, $amount, 8);
        $buyerAsset->save();
    }

    private function findMatchingSellOrder(Order $buyOrder): ?Order
    {
        return Order::where('symbol', $buyOrder->symbol)
            ->where('side', 'sell')
            ->where('status', Order::STATUS_OPEN)
            ->where('price', '<=', $buyOrder->price)
            ->where('amount', $buyOrder->amount)
            ->where('user_id', '!=', $buyOrder->user_id)
            ->orderBy('price', 'asc')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->first();
    }

    private function findMatchingBuyOrder(Order $sellOrder): ?Order
    {
        return Order::where('symbol', $sellOrder->symbol)
            ->where('side', 'buy')
            ->where('status', Order::STATUS_OPEN)
            ->where('price', '>=', $sellOrder->price)
            ->where('amount', $sellOrder->amount)
            ->where('user_id', '!=', $sellOrder->user_id)
            ->orderBy('price', 'desc')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->first();
    }
}
