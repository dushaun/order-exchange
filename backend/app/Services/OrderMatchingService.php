<?php

namespace App\Services;

use App\Models\Order;

class OrderMatchingService
{
    public function findMatchingOrder(Order $newOrder): ?Order
    {
        if ($newOrder->side === 'buy') {
            return $this->findMatchingSellOrder($newOrder);
        }

        return $this->findMatchingBuyOrder($newOrder);
    }

    public function executeMatch(Order $newOrder, Order $matchedOrder): void
    {
        $newOrder->status = Order::STATUS_FILLED;
        $matchedOrder->status = Order::STATUS_FILLED;

        $newOrder->save();
        $matchedOrder->save();
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
