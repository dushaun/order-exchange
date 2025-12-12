<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $demoEmails = ['user1@test.com', 'user2@test.com'];

        $assets = [
            ['symbol' => 'BTC', 'amount' => '1.00000000', 'locked_amount' => '0.00000000'],
            ['symbol' => 'ETH', 'amount' => '10.00000000', 'locked_amount' => '0.00000000'],
        ];

        foreach ($demoEmails as $email) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                continue;
            }

            foreach ($assets as $assetData) {
                Asset::updateOrCreate(
                    ['user_id' => $user->id, 'symbol' => $assetData['symbol']],
                    [
                        'amount' => $assetData['amount'],
                        'locked_amount' => $assetData['locked_amount'],
                    ]
                );
            }
        }
    }
}
