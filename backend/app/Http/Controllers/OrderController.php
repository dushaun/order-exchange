<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetOrdersRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function index(GetOrdersRequest $request): JsonResponse
    {
        $symbol = $request->validated()['symbol'];

        $buyOrders = Order::where('symbol', $symbol)
            ->where('status', Order::STATUS_OPEN)
            ->where('side', 'buy')
            ->orderByDesc('price')
            ->orderBy('created_at', 'asc')
            ->select(['id', 'symbol', 'side', 'price', 'amount', 'created_at'])
            ->get();

        $sellOrders = Order::where('symbol', $symbol)
            ->where('status', Order::STATUS_OPEN)
            ->where('side', 'sell')
            ->orderBy('price', 'asc')
            ->orderBy('created_at', 'asc')
            ->select(['id', 'symbol', 'side', 'price', 'amount', 'created_at'])
            ->get();

        return response()->json([
            'buy_orders' => $buyOrders,
            'sell_orders' => $sellOrders,
        ], 200);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($request, $validated) {
            $user = User::where('id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($validated['side'] === 'buy') {
                $totalCost = bcmul($validated['amount'], $validated['price'], 8);

                if (bccomp($user->balance, $totalCost, 8) < 0) {
                    return [
                        'error' => 'insufficient_balance',
                        'required' => $totalCost,
                        'available' => $user->balance,
                    ];
                }

                $user->balance = bcsub($user->balance, $totalCost, 8);
                $user->save();
            } elseif ($validated['side'] === 'sell') {
                $asset = Asset::where('user_id', $user->id)
                    ->where('symbol', $validated['symbol'])
                    ->lockForUpdate()
                    ->first();

                if (! $asset || bccomp($asset->amount, $validated['amount'], 8) < 0) {
                    return [
                        'error' => 'insufficient_assets',
                        'required' => $validated['amount'],
                        'available' => $asset ? $asset->amount : '0.00000000',
                        'symbol' => $validated['symbol'],
                    ];
                }

                $asset->amount = bcsub($asset->amount, $validated['amount'], 8);
                $asset->locked_amount = bcadd($asset->locked_amount, $validated['amount'], 8);
                $asset->save();
            }

            $order = Order::create([
                'user_id' => $user->id,
                'symbol' => $validated['symbol'],
                'side' => $validated['side'],
                'price' => $validated['price'],
                'amount' => $validated['amount'],
                'status' => Order::STATUS_OPEN,
            ]);

            // synchronous matching for demo, ideally will be queued
            $matchingService = app(OrderMatchingService::class);
            $matchedOrder = $matchingService->findMatchingOrder($order);

            if ($matchedOrder) {
                $matchResult = $matchingService->executeMatch($order, $matchedOrder);

                Log::info('Order matched with commission', [
                    'commission' => $matchResult['commission'],
                    'executed_price' => $matchResult['executedPrice'],
                    'amount' => $matchResult['amount'],
                ]);
            }

            return $order->fresh();
        });

        if (is_array($result) && isset($result['error'])) {
            if ($result['error'] === 'insufficient_assets') {
                $message = sprintf(
                    'Insufficient %s. You need %s but only have %s available.',
                    $result['symbol'],
                    $result['required'],
                    $result['available']
                );
            } else {
                $message = sprintf(
                    'Insufficient USD balance. You need $%s but only have $%s available.',
                    number_format((float) $result['required'], 2),
                    number_format((float) $result['available'], 2)
                );
            }

            return response()->json(['message' => $message], 409);
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $result,
        ], 201);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only cancel your own orders',
            ], 403);
        }

        if ($order->status !== Order::STATUS_OPEN) {
            return response()->json([
                'message' => 'Only open orders can be cancelled',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            if ($order->side === 'buy') {
                $user = User::where('id', $order->user_id)
                    ->lockForUpdate()
                    ->first();

                $refundAmount = bcmul($order->amount, $order->price, 8);
                $user->balance = bcadd($user->balance, $refundAmount, 8);
                $user->save();
            } else {
                $asset = Asset::where('user_id', $order->user_id)
                    ->where('symbol', $order->symbol)
                    ->lockForUpdate()
                    ->first();

                $asset->locked_amount = bcsub($asset->locked_amount, $order->amount, 8);
                $asset->amount = bcadd($asset->amount, $order->amount, 8);
                $asset->save();
            }

            $order->status = Order::STATUS_CANCELLED;
            $order->save();
        });

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => $order->fresh(),
        ], 200);
    }
}
