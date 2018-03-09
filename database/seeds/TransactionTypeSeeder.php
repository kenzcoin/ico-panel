<?php

use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert User Role
        DB::table('transaction_type')->insert([
            'name' => 'Ethereum'
        ]);

        DB::table('transaction_type')->insert([
            'name' => 'Bank Transfer'
        ]);

        DB::table('transaction_type')->insert([
            'name' => 'Credit Card'
        ]);
    }
}
