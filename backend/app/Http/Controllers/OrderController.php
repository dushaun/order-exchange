<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
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

            return Order::create([
                'user_id' => $user->id,
                'symbol' => $validated['symbol'],
                'side' => $validated['side'],
                'price' => $validated['price'],
                'amount' => $validated['amount'],
                'status' => Order::STATUS_OPEN,
            ]);
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
}
