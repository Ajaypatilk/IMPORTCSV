<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Return top 20 customers by spend over the last year.
     */
    public function topSpenders(): JsonResponse
    {
        $customers = DB::table('customers as c')
            ->join('orders as o', 'o.customer_id', '=', 'c.customer_id')
            ->where('o.order_date', '>=', now()->subYear())
            ->groupBy('c.customer_id', 'c.name', 'c.email')
            ->select([
                'c.customer_id',
                'c.name',
                'c.email',
                DB::raw('SUM(o.amount) as total_spent'),
            ])
            ->orderByDesc('total_spent')
            ->limit(20)
            ->get();

        return response()->json($customers);
    }
}

