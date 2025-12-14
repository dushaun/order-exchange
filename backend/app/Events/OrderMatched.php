<?php

namespace App\Events;

use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $buyerId;

    public int $sellerId;

    public array $orderDetails;

    public array $buyerData;

    public array $sellerData;

    /**
     * Create a new event instance.
     *
     * @param  array{buyOrder: Order, sellOrder: Order, executedPrice: string, amount: string, commission: string}  $matchResult
     */
    public function __construct(
        array $matchResult,
        User $buyer,
        User $seller
    ) {
        $this->buyerId = $buyer->id;
        $this->sellerId = $seller->id;

        $buyOrder = $matchResult['buyOrder'];
        $sellOrder = $matchResult['sellOrder'];

        $this->orderDetails = [
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'symbol' => $buyOrder->symbol,
            'executed_price' => $matchResult['executedPrice'],
            'amount' => $matchResult['amount'],
            'commission' => $matchResult['commission'],
        ];

        $this->buyerData = [
            'balance' => $buyer->balance,
            'assets' => $buyer->assets->map(fn (Asset $a) => [
                'symbol' => $a->symbol,
                'amount' => $a->amount,
                'locked_amount' => $a->locked_amount,
            ])->toArray(),
            'order' => [
                'id' => $buyOrder->id,
                'symbol' => $buyOrder->symbol,
                'side' => $buyOrder->side,
                'price' => $buyOrder->price,
                'amount' => $buyOrder->amount,
                'status' => $buyOrder->status,
            ],
        ];

        $this->sellerData = [
            'balance' => $seller->balance,
            'assets' => $seller->assets->map(fn (Asset $a) => [
                'symbol' => $a->symbol,
                'amount' => $a->amount,
                'locked_amount' => $a->locked_amount,
            ])->toArray(),
            'order' => [
                'id' => $sellOrder->id,
                'symbol' => $sellOrder->symbol,
                'side' => $sellOrder->side,
                'price' => $sellOrder->price,
                'amount' => $sellOrder->amount,
                'status' => $sellOrder->status,
            ],
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->buyerId),
            new PrivateChannel('user.'.$this->sellerId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order_details' => $this->orderDetails,
            'buyer' => $this->buyerData,
            'seller' => $this->sellerData,
        ];
    }
}
