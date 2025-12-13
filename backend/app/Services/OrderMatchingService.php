<?php

namespace App\Services;

use App\Models\Order;

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
